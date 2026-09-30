<?php
/**
 * Hash-aware incremental PDF backup worker.
 * Copies only new/changed PDFs and skips identical content even if it already
 * exists on the destination drive under a different relative path.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_protection_jobs.php';
require_once __DIR__ . '/../includes/pdf_backup_manifest.php';

$options = getopt('', ['source:', 'destination:', 'dry-run', 'limit::']);
$source = (string)($options['source'] ?? '');
$destination = (string)($options['destination'] ?? '');
$dryRun = array_key_exists('dry-run', $options);
$limit = max(0, (int)($options['limit'] ?? 0));
if ($source === '' || $destination === '') {
    fwrite(STDERR, "Usage: php scripts/pdf_incremental_backup.php --source=<uploads-root> --destination=<external-root> [--dry-run] [--limit=N]\n");
    exit(2);
}

function incremental_skip_path(string $relative): bool {
    $first = strtolower((string)strtok(str_replace('\\', '/', $relative), '/'));
    return in_array($first, ['backup', 'backups', '.staging', '.quarantine'], true);
}

function incremental_filter(array $manifest): array {
    foreach (array_keys($manifest) as $path) {
        if (incremental_skip_path($path)) unset($manifest[$path]);
    }
    return $manifest;
}

function incremental_expected_hashes(PDO $pdo): array {
    $tables = ['certificate_of_live_birth','certificate_of_death','certificate_of_marriage','application_for_marriage_license'];
    $byPath = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT pdf_filename, pdf_hash FROM {$table} WHERE status='Active' AND pdf_filename IS NOT NULL AND pdf_filename<>''");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $path = str_replace('\\', '/', ltrim((string)$row['pdf_filename'], '/'));
            $hash = strtolower(trim((string)($row['pdf_hash'] ?? '')));
            if ($path !== '' && preg_match('/^[a-f0-9]{64}$/', $hash)) $byPath[$path] = $hash;
        }
    }
    return $byPath;
}

function incremental_install(string $source, string $destination, string $hash): string {
    if (is_file($destination)) {
        $oldHash = hash_file('sha256', $destination);
        if (is_string($oldHash) && hash_equals(strtolower($hash), strtolower($oldHash))) return strtolower($oldHash);
        $version = $destination . '.previous-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!rename($destination, $version)) throw new RuntimeException('Could not preserve the previous destination version.');
        try { return copy_pdf_verified($source, $destination, $hash); }
        catch (Throwable $e) { if (!is_file($destination)) @rename($version, $destination); throw $e; }
    }
    return copy_pdf_verified($source, $destination, $hash);
}

function incremental_job_item(array $item, array $fields, string $status): array {
    return array_merge([
        'source_path' => $item['absolute_path'],
        'source_filename' => $item['filename'],
        'source_size' => $item['size'],
        'source_mtime' => $item['mtime'],
        'source_hash' => $item['hash'],
    ], $fields, ['status' => $status]);
}

$sourceRoot = realpath($source);
if ($sourceRoot === false || !is_dir($sourceRoot)) { fwrite(STDERR, "Invalid source root.\n"); exit(2); }
$destinationRoot = realpath($destination);
if ($destinationRoot === false) {
    if (!mkdir($destination, 0755, true)) { fwrite(STDERR, "Could not create destination root.\n"); exit(2); }
    $destinationRoot = realpath($destination);
}
if ($destinationRoot === false) { fwrite(STDERR, "Invalid destination root.\n"); exit(2); }

$sourcePrefix = rtrim(str_replace('\\', '/', $sourceRoot), '/') . '/';
$destinationPrefix = rtrim(str_replace('\\', '/', $destinationRoot), '/') . '/';
if (str_starts_with($sourcePrefix, $destinationPrefix) || str_starts_with($destinationPrefix, $sourcePrefix)) {
    fwrite(STDERR, "Source and destination must be separate roots.\n"); exit(2);
}

$jobId = create_pdf_protection_job($pdo, 'backup', $sourceRoot, $destinationRoot, null);
try {
    update_pdf_protection_job($pdo, $jobId, 'scanning');
    $sourceManifest = incremental_filter(scan_pdf_manifest($sourceRoot));
    $destinationManifest = incremental_filter(scan_pdf_manifest($destinationRoot));
    $destinationByHash = [];
    foreach ($destinationManifest as $path => $item) {
        if ($item['valid'] && isset($item['hash'])) $destinationByHash[strtolower($item['hash'])][] = $path;
    }
    $expectedHashes = incremental_expected_hashes($pdo);
    $items = array_values($sourceManifest);
    if ($limit > 0) $items = array_slice($items, 0, $limit);
    update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : 'running', ['total_items' => count($items)]);
    $summary = ['job_id'=>$jobId,'dry_run'=>$dryRun,'total'=>count($items),'copied'=>0,'already_present'=>0,'failed'=>0,'corrupt_source'=>0,'invalid'=>0];

    foreach ($items as $index => $item) {
        $status = 'scanned';
        $fields = ['destination_path'=>null,'match_method'=>'none'];
        try {
            $relative = $item['relative_path'];
            if (!$item['valid']) {
                $status = 'invalid_pdf'; $summary['invalid']++;
            } elseif (isset($expectedHashes[$relative]) && !hash_equals($expectedHashes[$relative], strtolower($item['hash']))) {
                $status = 'corrupt_source'; $summary['corrupt_source']++;
            } else {
                $samePath = $destinationManifest[$relative] ?? null;
                $sameHashElsewhere = $destinationByHash[strtolower($item['hash'])][0] ?? null;
                $fields['match_method'] = isset($expectedHashes[$relative]) ? 'exact_hash' : 'none';
                if ($samePath !== null && $samePath['valid'] && hash_equals(strtolower($item['hash']), strtolower($samePath['hash']))) {
                    $status = 'already_present'; $fields['destination_path'] = pdf_manifest_join_path($destinationRoot, $relative); $summary['already_present']++;
                } elseif ($sameHashElsewhere !== null) {
                    $status = 'already_present'; $fields['destination_path'] = pdf_manifest_join_path($destinationRoot, $sameHashElsewhere); $fields['match_method'] = 'exact_hash'; $summary['already_present']++;
                } elseif ($dryRun) {
                    $status = 'matched'; $fields['destination_path'] = pdf_manifest_join_path($destinationRoot, $relative); $summary['copied']++;
                } else {
                    $fields['destination_path'] = pdf_manifest_join_path($destinationRoot, $relative);
                    incremental_install($item['absolute_path'], $fields['destination_path'], $item['hash']);
                    $status = 'imported'; $summary['copied']++;
                }
            }
            $itemId = add_pdf_protection_job_item($pdo, $jobId, incremental_job_item($item, $fields, $status));
            update_pdf_protection_job_item($pdo, $itemId, $status, [
                'destination_hash' => ($status === 'imported' || $status === 'already_present') ? strtolower($item['hash']) : null,
                'imported_at' => $status === 'imported' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (Throwable $e) {
            $status = 'failed'; $summary['failed']++;
            $itemId = add_pdf_protection_job_item($pdo, $jobId, incremental_job_item($item, $fields, $status));
            update_pdf_protection_job_item($pdo, $itemId, $status, ['last_error'=>$e->getMessage()]);
        }
        $done = $index + 1; $total = count($items); $pct = $total > 0 ? round($done / $total * 100, 1) : 100;
        if ($done === 1 || $done === $total || $done % 25 === 0) echo "[{$pct}%] {$done}/{$total} {$status} {$item['relative_path']}\n";
        if ($done === $total || $done % 25 === 0) {
            update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : 'running', [
                'scanned_items'=>$done, 'copied_items'=>$summary['copied'], 'skipped_items'=>$summary['already_present'],
                'failed_items'=>$summary['failed'] + $summary['invalid'] + $summary['corrupt_source'],
            ]);
        }
    }
    $final = ($summary['failed'] + $summary['invalid'] + $summary['corrupt_source']) > 0 ? 'completed_with_errors' : 'completed';
    update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : $final, [
        'scanned_items'=>$summary['total'], 'copied_items'=>$summary['copied'], 'skipped_items'=>$summary['already_present'],
        'failed_items'=>$summary['failed'] + $summary['invalid'] + $summary['corrupt_source'],
    ]);
    echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(($final === 'completed' || $dryRun) ? 0 : 3);
} catch (Throwable $e) {
    update_pdf_protection_job($pdo, $jobId, 'failed', ['last_error'=>$e->getMessage()]);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL); exit(1);
}

