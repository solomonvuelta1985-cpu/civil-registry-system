<?php
/** Read-only database-to-PDF reconciliation helpers. */
require_once __DIR__ . '/pdf_backup_manifest.php';

function pdf_reconciliation_tables(): array
{
    return ['birth' => 'certificate_of_live_birth', 'death' => 'certificate_of_death', 'marriage' => 'certificate_of_marriage', 'marriage_license' => 'application_for_marriage_license'];
}

function pdf_reconciliation_count_records(PDO $pdo): int
{
    $total = 0;
    foreach (pdf_reconciliation_tables() as $table) $total += (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'Active'")->fetchColumn();
    return $total;
}

function pdf_reconciliation_fetch_records(PDO $pdo, int $offset, int $limit): array
{
    $rows = [];
    $remainingOffset = max(0, $offset);
    $remainingLimit = max(1, $limit);
    foreach (pdf_reconciliation_tables() as $type => $table) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'Active'")->fetchColumn();
        if ($remainingOffset >= $count) { $remainingOffset -= $count; continue; }
        $take = min($remainingLimit, $count - $remainingOffset);
        $stmt = $pdo->prepare("SELECT id, registry_no, pdf_filename, pdf_filepath, pdf_hash, status FROM {$table} WHERE status = 'Active' ORDER BY id ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $take, PDO::PARAM_INT); $stmt->bindValue(':offset', $remainingOffset, PDO::PARAM_INT); $stmt->execute();
        $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetched as $row) { $row['cert_type'] = $type; $row['table_name'] = $table; $rows[] = $row; }
        $remainingLimit -= count($fetched); $remainingOffset = 0;
        if ($remainingLimit <= 0) break;
    }
    return $rows;
}

function pdf_reconciliation_record_rows(PDO $pdo): array
{
    $rows = [];
    foreach (pdf_reconciliation_tables() as $type => $table) {
        $stmt = $pdo->query("SELECT id, registry_no, pdf_filename, pdf_filepath, pdf_hash, status FROM {$table} WHERE status = 'Active' ORDER BY id ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row['cert_type'] = $type; $row['table_name'] = $table; $rows[] = $row; }
    }
    return $rows;
}

function pdf_reconciliation_classify_record(string $root, array $record): array
{
    $relative = str_replace('\\', '/', ltrim((string)($record['pdf_filename'] ?? ''), '/'));
    $base = ['cert_type' => $record['cert_type'], 'record_id' => (int)$record['id'], 'registry_no' => $record['registry_no'], 'relative_path' => $relative, 'expected_hash' => strtolower(trim((string)($record['pdf_hash'] ?? ''))), 'actual_hash' => null, 'size' => null, 'status' => 'missing', 'message' => null];
    if ($relative === '' || preg_match('~(^|/)\.\.?(/|$)~', $relative)) { $base['status'] = 'invalid_record'; $base['message'] = 'The active record has no safe relative PDF path.'; return $base; }
    if (!preg_match('/^[a-f0-9]{64}$/i', $base['expected_hash'])) { $base['status'] = 'invalid_record'; $base['message'] = 'The active record has no valid SHA-256 PDF hash.'; return $base; }
    try { $absolute = pdf_manifest_join_path($root, $relative); } catch (Throwable $e) { $base['status'] = 'invalid_record'; $base['message'] = $e->getMessage(); return $base; }
    if (!is_file($absolute) || !is_readable($absolute)) { $base['message'] = 'Expected PDF is missing or unreadable.'; return $base; }
    $base['size'] = (int)filesize($absolute); $hash = hash_file('sha256', $absolute); $base['actual_hash'] = is_string($hash) ? strtolower($hash) : null;
    if (!is_string($hash)) { $base['status'] = 'unreadable'; $base['message'] = 'Could not calculate the PDF hash.'; return $base; }
    if (!hash_equals($base['expected_hash'], $base['actual_hash'])) { $base['status'] = 'corrupt'; $base['message'] = 'The file hash differs from the active database record.'; return $base; }
    $base['status'] = 'healthy'; $base['message'] = 'PDF exists and matches the database hash.'; return $base;
}

function pdf_reconciliation_duplicate_indexes(array $records): array
{
    $paths = []; $hashes = [];
    foreach ($records as $record) {
        $path = str_replace('\\', '/', ltrim((string)($record['pdf_filename'] ?? ''), '/')); $hash = strtolower(trim((string)($record['pdf_hash'] ?? ''))); $key = $record['cert_type'] . ':' . (int)$record['id'];
        if ($path !== '') $paths[$path][] = $key; if (preg_match('/^[a-f0-9]{64}$/', $hash)) $hashes[$hash][] = $key;
    }
    return ['paths' => $paths, 'hashes' => $hashes];
}

function pdf_reconciliation_apply_duplicates(array $item, array $indexes): array
{
    $pathMatches = $indexes['paths'][$item['relative_path']] ?? []; $hashMatches = $indexes['hashes'][$item['expected_hash']] ?? [];
    if (count($pathMatches) > 1 || count($hashMatches) > 1) { $item['status'] = 'duplicate'; $item['message'] = 'The PDF path or hash is referenced by multiple active records.'; $item['duplicate_records'] = array_values(array_unique(array_merge($pathMatches, $hashMatches))); }
    return $item;
}

function pdf_reconciliation_scan_orphans(string $root, array $expectedPaths): array
{
    $manifest = scan_pdf_manifest($root); $orphans = [];
    foreach ($manifest as $relative => $item) {
        $relative = str_replace('\\', '/', $relative); if (isset($expectedPaths[$relative])) continue;
        $orphans[] = ['cert_type' => null, 'record_id' => null, 'registry_no' => null, 'relative_path' => $relative, 'expected_hash' => null, 'actual_hash' => $item['hash'], 'size' => (int)$item['size'], 'status' => $item['valid'] ? 'orphan' : 'invalid_pdf', 'message' => $item['valid'] ? 'PDF exists but no active database record references this path.' : implode('; ', $item['errors'])];
    }
    return $orphans;
}
