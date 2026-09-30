<?php
/** Shared inventory and processing helpers for resumable PDF protection jobs. */
require_once __DIR__ . '/pdf_backup_manifest.php';
require_once __DIR__ . '/pdf_protection_jobs.php';

function pdf_resumable_is_excluded(string $relative): bool
{
    $first = strtolower((string)strtok(str_replace('\\', '/', $relative), '/'));
    return in_array($first, ['backup', 'backups', '.staging', '.quarantine'], true);
}

function pdf_resumable_filter_manifest(array $manifest): array
{
    foreach (array_keys($manifest) as $relative) {
        if (pdf_resumable_is_excluded($relative)) unset($manifest[$relative]);
    }
    return $manifest;
}

function pdf_resumable_record_indexes(PDO $pdo): array
{
    $tables = ['birth' => 'certificate_of_live_birth', 'death' => 'certificate_of_death', 'marriage' => 'certificate_of_marriage', 'marriage_license' => 'application_for_marriage_license'];
    $byHash = [];
    $byPath = [];
    foreach ($tables as $type => $table) {
        $stmt = $pdo->query("SELECT id, registry_no, pdf_filename, pdf_filepath, pdf_hash, status FROM {$table} WHERE pdf_filename IS NOT NULL AND pdf_filename <> '' AND status = 'Active'");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['cert_type'] = $type;
            $row['table_name'] = $table;
            $hash = strtolower(trim((string)($row['pdf_hash'] ?? '')));
            if (!preg_match('/^[a-f0-9]{64}$/', $hash)) continue;
            $byHash[$hash][] = $row;
            $path = str_replace('\\', '/', ltrim((string)$row['pdf_filename'], '/'));
            if ($path !== '') $byPath[$path][] = $row;
        }
    }
    return ['by_hash' => $byHash, 'by_path' => $byPath, 'tables' => $tables];
}

function pdf_resumable_install_verified(string $source, string $destination, string $expectedHash): string
{
    if (is_file($destination)) {
        $existingHash = hash_file('sha256', $destination);
        if (is_string($existingHash) && hash_equals(strtolower($expectedHash), strtolower($existingHash))) return strtolower($existingHash);
        $quarantine = $destination . '.previous-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!rename($destination, $quarantine)) throw new RuntimeException('Could not quarantine the previous destination PDF.');
        try {
            return copy_pdf_verified($source, $destination, $expectedHash);
        } catch (Throwable $e) {
            if (!is_file($destination) && is_file($quarantine)) @rename($quarantine, $destination);
            throw $e;
        }
    }
    return copy_pdf_verified($source, $destination, $expectedHash);
}

function pdf_resumable_update_record(PDO $pdo, array $record, string $relativePath, string $hash): void
{
    $stmt = $pdo->prepare("UPDATE {$record['table_name']} SET pdf_filename=:filename, pdf_filepath=:filepath, pdf_hash=:hash, updated_at=NOW() WHERE id=:id");
    $stmt->execute([':filename' => $relativePath, ':filepath' => UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath), ':hash' => strtolower($hash), ':id' => (int)$record['id']]);
}

function pdf_resumable_job_state(PDO $pdo, int $jobId): ?string
{
    $stmt = $pdo->prepare('SELECT status FROM pdf_protection_jobs WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $jobId]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string)$value;
}

function pdf_resumable_ensure_inventory(PDO $pdo, int $jobId, array $manifest): array
{
    $select = $pdo->prepare('SELECT * FROM pdf_protection_job_items WHERE job_id=:job_id AND source_path=:source_path LIMIT 1');
    $update = $pdo->prepare("UPDATE pdf_protection_job_items SET source_filename=:source_filename, source_size=:source_size, source_mtime=:source_mtime, source_hash=:source_hash, status=CASE WHEN source_hash<>:source_hash THEN 'queued' ELSE status END, last_error=CASE WHEN source_hash<>:source_hash THEN NULL ELSE last_error END, updated_at=NOW() WHERE id=:id");
    $items = [];
    foreach ($manifest as $relative => $sourceItem) {
        $select->execute([':job_id' => $jobId, ':source_path' => $sourceItem['absolute_path']]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $itemId = add_pdf_protection_job_item($pdo, $jobId, ['source_path' => $sourceItem['absolute_path'], 'source_filename' => basename($relative), 'source_size' => $sourceItem['size'], 'source_mtime' => $sourceItem['mtime'], 'source_hash' => $sourceItem['hash'], 'status' => 'queued']);
            $existing = ['id' => $itemId, 'status' => 'queued', 'source_hash' => $sourceItem['hash'], 'attempts' => 0];
        } elseif (strtolower((string)$existing['source_hash']) !== strtolower((string)$sourceItem['hash'])) {
            $update->execute([':source_filename' => basename($relative), ':source_size' => (int)$sourceItem['size'], ':source_mtime' => $sourceItem['mtime'], ':source_hash' => $sourceItem['hash'], ':id' => (int)$existing['id']]);
            $existing['status'] = 'queued';
            $existing['source_hash'] = $sourceItem['hash'];
        }
        $existing['relative_path'] = $relative;
        $existing['source_item'] = $sourceItem;
        $items[(int)$existing['id']] = $existing;
    }
    return $items;
}

function pdf_resumable_refresh_counters(PDO $pdo, int $jobId, string $status): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_items, COALESCE(SUM(status<>'queued'),0) AS scanned_items, COALESCE(SUM(status='imported'),0) AS copied_items, COALESCE(SUM(status IN ('already_present','skipped')),0) AS skipped_items, COALESCE(SUM(status IN ('failed','invalid_pdf','corrupt_source')),0) AS failed_items, COALESCE(SUM(status IN ('needs_review','unmatched','duplicate_source')),0) AS review_items, COALESCE(SUM(source_size),0) AS bytes_scanned FROM pdf_protection_job_items WHERE job_id=:job_id");
    $stmt->execute([':job_id' => $jobId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach (['total_items', 'scanned_items', 'copied_items', 'skipped_items', 'failed_items', 'review_items', 'bytes_scanned'] as $field) $counts[$field] = (int)($counts[$field] ?? 0);
    update_pdf_protection_job($pdo, $jobId, $status, $counts);
}

function pdf_resumable_increment_attempt(PDO $pdo, int $itemId): int
{
    $stmt = $pdo->prepare('UPDATE pdf_protection_job_items SET attempts=attempts+1, updated_at=NOW() WHERE id=:id');
    $stmt->execute([':id' => $itemId]);
    $read = $pdo->prepare('SELECT attempts FROM pdf_protection_job_items WHERE id=:id');
    $read->execute([':id' => $itemId]);
    return (int)$read->fetchColumn();
}
