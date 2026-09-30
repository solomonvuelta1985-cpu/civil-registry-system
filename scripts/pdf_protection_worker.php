<?php
/**
 * CLI worker for PDF recovery remapping and incremental backup.
 *
 * Examples:
 *   php scripts/pdf_protection_worker.php --mode=backup --source=uploads --destination=E:\\iSCAN-PDF-Backup
 *   php scripts/pdf_protection_worker.php --mode=recovery --source=E:\\PDF-Source --destination=uploads --dry-run
 *
 * The worker is intentionally CLI-only. A scheduler or admin-controlled
 * process can launch it, while the job tables provide progress for a UI/API.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_fingerprint_registry.php';
require_once __DIR__ . '/../includes/pdf_protection_jobs.php';
require_once __DIR__ . '/../includes/pdf_backup_manifest.php';

$options = getopt('', [
    'mode:',
    'source:',
    'destination:',
    'job-id::',
    'dry-run',
    'limit::',
]);

$mode = strtolower(trim((string)($options['mode'] ?? '')));
$source = (string)($options['source'] ?? '');
$destination = (string)($options['destination'] ?? '');
$jobId = isset($options['job-id']) ? (int)$options['job-id'] : null;
$dryRun = array_key_exists('dry-run', $options);
$limit = max(0, (int)($options['limit'] ?? 0));

if (!in_array($mode, ['backup', 'recovery'], true) || $source === '' || $destination === '') {
    fwrite(STDERR, "Usage: php scripts/pdf_protection_worker.php --mode=backup|recovery --source=<root> --destination=<root> [--dry-run] [--limit=N]\n");
    exit(2);
}

function pdf_worker_is_excluded(string $relative): bool {
    $first = strtolower((string)strtok(str_replace('\\', '/', $relative), '/'));
    return in_array($first, ['backup', 'backups', '.staging', '.quarantine'], true);
}

function pdf_worker_filter_manifest(array $manifest): array {
    foreach (array_keys($manifest) as $relative) {
        if (pdf_worker_is_excluded($relative)) {
            unset($manifest[$relative]);
        }
    }
    return $manifest;
}

function pdf_worker_record_indexes(PDO $pdo): array {
    $tables = [
        'birth'            => 'certificate_of_live_birth',
        'death'            => 'certificate_of_death',
        'marriage'         => 'certificate_of_marriage',
        'marriage_license' => 'application_for_marriage_license',
    ];
    $byHash = [];
    $byPath = [];

    foreach ($tables as $type => $table) {
        $stmt = $pdo->query(
            "SELECT id, registry_no, pdf_filename, pdf_filepath, pdf_hash, status
               FROM {$table}
              WHERE status = 'Active'
                AND pdf_filename IS NOT NULL
                AND pdf_filename <> ''"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['cert_type'] = $type;
            $row['table_name'] = $table;
            $hash = strtolower(trim((string)($row['pdf_hash'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/', $hash)) {
                $byHash[$hash][] = $row;
            }
            $path = str_replace('\\', '/', ltrim((string)$row['pdf_filename'], '/'));
            if ($path !== '') {
                $byPath[$path][] = $row;
            }
        }
    }

    return ['by_hash' => $byHash, 'by_path' => $byPath, 'tables' => $tables];
}

function pdf_worker_install_verified(string $source, string $destination, string $expectedHash): string {
    if (is_file($destination)) {
        $existingHash = hash_file('sha256', $destination);
        if (is_string($existingHash) && hash_equals(strtolower($expectedHash), strtolower($existingHash))) {
            return strtolower($existingHash);
        }

        $quarantine = $destination . '.corrupt-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!rename($destination, $quarantine)) {
            throw new RuntimeException('Could not quarantine the existing destination PDF.');
        }
        try {
            return copy_pdf_verified($source, $destination, $expectedHash);
        } catch (Throwable $e) {
            if (!is_file($destination) && is_file($quarantine)) {
                @rename($quarantine, $destination);
            }
            throw $e;
        }
    }

    return copy_pdf_verified($source, $destination, $expectedHash);
}

function pdf_worker_update_record(PDO $pdo, array $record, string $relativePath, string $hash): void {
    $stmt = $pdo->prepare(
        "UPDATE {$record['table_name']}
            SET pdf_filename = :filename,
                pdf_filepath = :filepath,
                pdf_hash = :hash,
                updated_at = NOW()
          WHERE id = :id"
    );
    $stmt->execute([
        ':filename' => $relativePath,
        ':filepath' => UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath),
        ':hash'     => strtolower($hash),
        ':id'       => (int)$record['id'],
    ]);
}

function pdf_worker_add_item(PDO $pdo, int $jobId, array $sourceItem, array $fields = []): int {
    return add_pdf_protection_job_item($pdo, $jobId, array_merge([
        'source_path'    => $sourceItem['absolute_path'],
        'source_filename'=> $sourceItem['filename'],
        'source_size'    => $sourceItem['size'],
        'source_mtime'   => $sourceItem['mtime'],
        'source_hash'    => $sourceItem['hash'],
    ], $fields));
}

function pdf_worker_progress(int $processed, int $total, string $relative, string $status): void {
    if ($processed === 1 || $processed === $total || ($processed % 25) === 0) {
        $percent = $total > 0 ? round(($processed / $total) * 100, 1) : 100;
        echo sprintf("[%s%%] %d/%d %-18s %s\n", $percent, $processed, $total, $status, $relative);
    }
}

$sourceRoot = realpath($source);
if ($sourceRoot === false || !is_dir($sourceRoot)) {
    fwrite(STDERR, "Source root does not exist or is not a directory.\n");
    exit(2);
}

$destinationRoot = realpath($destination);
if ($destinationRoot === false) {
    if (!mkdir($destination, 0755, true)) {
        fwrite(STDERR, "Destination root could not be created.\n");
        exit(2);
    }
    $destinationRoot = realpath($destination);
}

if ($destinationRoot === false) {
    fwrite(STDERR, "Destination root is unavailable.\n");
    exit(2);
}

$sourcePrefix = rtrim(str_replace('\\', '/', $sourceRoot), '/') . '/';
$destinationPrefix = rtrim(str_replace('\\', '/', $destinationRoot), '/') . '/';
if (str_starts_with($destinationPrefix, $sourcePrefix) || str_starts_with($sourcePrefix, $destinationPrefix)) {
    fwrite(STDERR, "Source and destination must be separate roots.\n");
    exit(2);
}

if ($jobId === null || $jobId <= 0) {
    $jobId = create_pdf_protection_job($pdo, $mode, $sourceRoot, $destinationRoot, null);
}

try {
    update_pdf_protection_job($pdo, $jobId, 'scanning');
    $sourceManifest = pdf_worker_filter_manifest(scan_pdf_manifest($sourceRoot));
    $destinationManifest = is_dir($destinationRoot)
        ? pdf_worker_filter_manifest(scan_pdf_manifest($destinationRoot))
        : [];
    $comparison = compare_pdf_manifests($sourceManifest, $destinationManifest);
    $records = pdf_worker_record_indexes($pdo);

    $items = array_values($sourceManifest);
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }
    update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : 'running', [
        'total_items' => count($items),
        'scanned_items' => 0,
    ]);

    $summary = [
        'job_id' => $jobId,
        'mode' => $mode,
        'dry_run' => $dryRun,
        'total' => count($items),
        'copied_or_imported' => 0,
        'already_present' => 0,
        'skipped' => 0,
        'needs_review' => 0,
        'unmatched' => 0,
        'invalid' => 0,
        'corrupt_source' => 0,
        'failed' => 0,
    ];

    foreach ($items as $index => $sourceItem) {
        $processed = $index + 1;
        $relative = $sourceItem['relative_path'];
        $status = 'scanned';
        $fields = ['destination_path' => null, 'match_method' => 'none'];

        try {
            if (!$sourceItem['valid']) {
                $status = 'invalid_pdf';
                $summary['invalid']++;
            } elseif ($mode === 'backup') {
                $expected = $records['by_path'][$relative][0] ?? null;
                if ($expected !== null && preg_match('/^[a-f0-9]{64}$/i', (string)$expected['pdf_hash'])) {
                    if (!hash_equals(strtolower($expected['pdf_hash']), strtolower($sourceItem['hash']))) {
                        $status = 'corrupt_source';
                        $summary['corrupt_source']++;
                    }
                }

                $fields['destination_path'] = pdf_manifest_join_path($destinationRoot, $relative);
                $fields['expected_hash'] = $sourceItem['hash'];
                $fields['match_method'] = $expected !== null ? 'exact_hash' : 'none';
                if ($status !== 'corrupt_source') {
                    $destinationItem = $destinationManifest[$relative] ?? null;
                    if ($destinationItem !== null && $destinationItem['valid']
                        && hash_equals(strtolower($sourceItem['hash']), strtolower($destinationItem['hash']))) {
                        $status = 'already_present';
                        $summary['already_present']++;
                    } elseif ($dryRun) {
                        $status = $destinationItem === null ? 'matched' : 'skipped';
                        $summary[$status === 'matched' ? 'copied_or_imported' : 'skipped']++;
                    } else {
                        copy_pdf_verified($sourceItem['absolute_path'], $fields['destination_path'], $sourceItem['hash']);
                        $status = 'imported';
                        $summary['copied_or_imported']++;
                    }
                }
            } else {
                $matches = $records['by_hash'][strtolower((string)$sourceItem['hash'])] ?? [];
                if (count($matches) !== 1) {
                    $status = count($matches) > 1 ? 'needs_review' : 'unmatched';
                    $summary[$status === 'needs_review' ? 'needs_review' : 'unmatched']++;
                } else {
                    $record = $matches[0];
                    $targetRelative = str_replace('\\', '/', ltrim((string)$record['pdf_filename'], '/'));
                    if ($targetRelative === '') {
                        $targetRelative = 'recovered/' . $record['cert_type'] . '/record_' . (int)$record['id']
                            . '/recovered_' . strtolower($sourceItem['hash']) . '.pdf';
                    }
                    $targetAbsolute = pdf_manifest_join_path(UPLOAD_DIR, $targetRelative);
                    $fields = [
                        'destination_path' => $targetAbsolute,
                        'expected_hash'    => $record['pdf_hash'],
                        'cert_type'        => $record['cert_type'],
                        'record_id'        => (int)$record['id'],
                        'registry_no'      => $record['registry_no'],
                        'match_method'     => 'exact_hash',
                    ];

                    $existingHash = is_file($targetAbsolute) ? hash_file('sha256', $targetAbsolute) : null;
                    if (is_string($existingHash) && hash_equals(strtolower($sourceItem['hash']), strtolower($existingHash))) {
                        $status = 'already_present';
                        $summary['already_present']++;
                    } elseif ($dryRun) {
                        $status = 'matched';
                        $summary['copied_or_imported']++;
                    } else {
                        pdf_worker_install_verified($sourceItem['absolute_path'], $targetAbsolute, $sourceItem['hash']);
                        $pdo->beginTransaction();
                        try {
                            pdf_worker_update_record($pdo, $record, $targetRelative, $sourceItem['hash']);
                            $pdo->commit();
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            throw $e;
                        }
                        $status = 'imported';
                        $summary['copied_or_imported']++;
                    }
                }
            }

            $itemId = pdf_worker_add_item($pdo, $jobId, $sourceItem, array_merge($fields, [
                'status' => $status,
            ]));
            update_pdf_protection_job_item($pdo, $itemId, $status, [
                'imported_at' => $status === 'imported' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (Throwable $e) {
            $status = 'failed';
            $summary['failed']++;
            $itemId = pdf_worker_add_item($pdo, $jobId, $sourceItem, array_merge($fields, [
                'status' => $status,
                'last_error' => $e->getMessage(),
            ]));
            update_pdf_protection_job_item($pdo, $itemId, $status, ['last_error' => $e->getMessage()]);
        }

        pdf_worker_progress($processed, count($items), $relative, $status);
        if (($processed % 25) === 0 || $processed === count($items)) {
            $fields = [
                'total_items'   => count($items),
                'scanned_items' => $processed,
                'copied_items'  => $summary['copied_or_imported'],
                'skipped_items' => $summary['already_present'] + $summary['skipped'],
                'failed_items'  => $summary['failed'] + $summary['invalid'] + $summary['corrupt_source'],
                'review_items'  => $summary['needs_review'] + $summary['unmatched'],
            ];
            update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : 'running', $fields);
        }
    }

    $finalStatus = ($summary['failed'] + $summary['invalid'] + $summary['corrupt_source'] + $summary['needs_review'] + $summary['unmatched']) > 0
        ? 'completed_with_errors' : 'completed';
    update_pdf_protection_job($pdo, $jobId, $dryRun ? 'preview_ready' : $finalStatus, [
        'total_items'   => $summary['total'],
        'scanned_items' => $summary['total'],
        'copied_items'  => $summary['copied_or_imported'],
        'skipped_items' => $summary['already_present'] + $summary['skipped'],
        'failed_items'  => $summary['failed'] + $summary['invalid'] + $summary['corrupt_source'],
        'review_items'  => $summary['needs_review'] + $summary['unmatched'],
    ]);

    echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
    exit($finalStatus === 'completed' || $dryRun ? 0 : 3);
} catch (Throwable $e) {
    update_pdf_protection_job($pdo, $jobId, 'failed', ['last_error' => $e->getMessage()]);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

