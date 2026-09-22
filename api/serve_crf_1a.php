<?php
/**
 * Securely serve an issued CRF No. 1A PDF.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_1a.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}
if (!hasPermission(crf_1a_view_permission())) {
    http_response_code(403);
    exit('Forbidden');
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    exit('Invalid issuance id');
}

$stmt = $pdo->prepare(
    "SELECT crf_number, pdf_filepath, pdf_filename, pdf_hash
     FROM crf_1a_issuances WHERE id = :id AND status = 'Active' LIMIT 1"
);
$stmt->execute([':id' => (int)$id]);
$issuance = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$issuance) {
    http_response_code(404);
    exit('CRF No. 1A not found');
}

$relativePath = str_replace('\\', '/', (string)$issuance['pdf_filepath']);
if (!preg_match('~^crf_1a/\d{4}/[A-Z0-9_]+/CRF1A-\d{4}-\d{6}\.pdf$~', $relativePath)) {
    http_response_code(403);
    exit('Forbidden');
}

$absPath = realpath(UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
$basePath = realpath(UPLOAD_PATH);
$prefix = $basePath ? rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if ($absPath === false || $basePath === false || strpos($absPath, $prefix) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    exit('File not found');
}

if (!empty($issuance['pdf_hash']) && !hash_equals((string)$issuance['pdf_hash'], (string)hash_file('sha256', $absPath))) {
    logSecurityEvent('crf_1a_hash_mismatch', 'high', ['issuance_id' => (int)$id], getUserId());
    http_response_code(409);
    exit('The stored CRF PDF failed integrity verification.');
}

$filename = basename((string)$issuance['pdf_filename']) ?: basename($absPath);
$download = !empty($_GET['download']);
$accessAction = strtolower(trim((string)($_GET['action'] ?? '')));
if ($accessAction === 'print') {
    crf_1a_record_history($pdo, (int)$id, (string)$issuance['crf_number'], 'printed', 'PDF opened for printing');
} elseif ($accessAction === 'download' || $download) {
    crf_1a_record_history($pdo, (int)$id, (string)$issuance['crf_number'], 'downloaded', 'PDF downloaded');
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absPath));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');
readfile($absPath);
exit;
