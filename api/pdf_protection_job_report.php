<?php
/** Download a complete PDF protection job report as CSV or JSON. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Not authenticated');
}
if (getUserRole() !== 'Admin') {
    http_response_code(403);
    exit('Admin access required');
}

$jobId = (int)($_GET['job_id'] ?? 0);
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
if ($jobId <= 0 || !in_array($format, ['csv', 'json'], true)) {
    http_response_code(400);
    exit('Valid job_id and format are required.');
}

$jobStmt = $pdo->prepare('SELECT * FROM pdf_protection_jobs WHERE id = :id LIMIT 1');
$jobStmt->execute([':id' => $jobId]);
$job = $jobStmt->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    http_response_code(404);
    exit('Job not found.');
}

$itemStmt = $pdo->prepare(
    'SELECT id, source_path, destination_path, source_filename, source_size,
            source_mtime, source_hash, destination_hash, expected_hash,
            cert_type, record_id, registry_no, match_method, status,
            attempts, last_error, reviewed_by, reviewed_at, imported_at,
            created_at, updated_at
       FROM pdf_protection_job_items
      WHERE job_id = :job_id
      ORDER BY id ASC'
);
$itemStmt->execute([':job_id' => $jobId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="pdf-protection-job-' . $jobId . '.json"');
    echo json_encode(['job' => $job, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pdf-protection-job-' . $jobId . '.csv"');
$out = fopen('php://output', 'wb');
fputcsv($out, ['job_id', 'job_type', 'job_status', 'source_root', 'destination_root', 'total_items', 'scanned_items', 'copied_items', 'skipped_items', 'failed_items', 'review_items']);
fputcsv($out, [$job['id'], $job['job_type'], $job['status'], $job['source_root'], $job['destination_root'], $job['total_items'], $job['scanned_items'], $job['copied_items'], $job['skipped_items'], $job['failed_items'], $job['review_items']]);
fputcsv($out, []);
fputcsv($out, ['item_id', 'source_path', 'destination_path', 'source_filename', 'source_size', 'source_mtime', 'source_hash', 'destination_hash', 'expected_hash', 'cert_type', 'record_id', 'registry_no', 'match_method', 'status', 'attempts', 'last_error', 'reviewed_by', 'reviewed_at', 'imported_at', 'created_at', 'updated_at']);
foreach ($items as $item) {
    fputcsv($out, array_values($item));
}
fclose($out);
