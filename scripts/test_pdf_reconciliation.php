<?php
/** Read-only regression test for the database-to-PDF reconciliation helpers. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdf_reconciliation.php';

try {
    $root = realpath(UPLOAD_DIR);
    if ($root === false || !is_dir($root)) throw new RuntimeException('Uploads root is unavailable.');
    $total = pdf_reconciliation_count_records($pdo);
    $all = pdf_reconciliation_record_rows($pdo);
    if ($total !== count($all)) throw new RuntimeException('Record count helper does not match record rows.');
    if (count(pdf_reconciliation_fetch_records($pdo, 0, 5)) > 5) throw new RuntimeException('Record pagination exceeded its limit.');
    $indexes = pdf_reconciliation_duplicate_indexes($all);
    $allowed = ['healthy','missing','corrupt','duplicate','invalid_record','unreadable'];
    foreach (array_slice($all, 0, 5) as $record) {
        $item = pdf_reconciliation_apply_duplicates(pdf_reconciliation_classify_record($root, $record), $indexes);
        if (!in_array($item['status'], $allowed, true)) throw new RuntimeException('Unexpected record status: ' . $item['status']);
    }
    $orphans = pdf_reconciliation_scan_orphans($root, array_fill_keys(array_map(static fn(array $row): string => str_replace('\\', '/', ltrim((string)($row['pdf_filename'] ?? ''), '/')), $all), true));
    echo json_encode(['pass' => true, 'active_records' => $total, 'sampled' => min(5, $total), 'orphans_found' => count($orphans)], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
