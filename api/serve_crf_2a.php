<?php
/** Securely serve an issued CRF No. 2A PDF. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_2a.php';
if (!isLoggedIn()) { http_response_code(401); exit('Unauthorized'); }
if (!hasPermission(crf_2a_view_permission())) { http_response_code(403); exit('Forbidden'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit('Invalid issuance id'); }
$stmt = $pdo->prepare("SELECT crf_number, pdf_filepath, pdf_filename, pdf_hash FROM crf_2a_issuances WHERE id = :id AND status = 'Active' LIMIT 1");
$stmt->execute([':id' => (int)$id]); $issuance = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$issuance) { http_response_code(404); exit('CRF No. 2A not found'); }
$relative = str_replace('\\', '/', (string)$issuance['pdf_filepath']);
if (!preg_match('~^crf_2a/\d{4}/[A-Z0-9_]+/CRF2A-\d{4}-\d{6}\.pdf$~', $relative)) { http_response_code(403); exit('Forbidden'); }
$abs = realpath(UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relative)); $base = realpath(UPLOAD_PATH); $prefix = $base ? rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (!$abs || !$base || strpos($abs, $prefix) !== 0 || !is_file($abs)) { http_response_code(404); exit('File not found'); }
if (!empty($issuance['pdf_hash']) && !hash_equals((string)$issuance['pdf_hash'], (string)hash_file('sha256', $abs))) { logSecurityEvent('crf_2a_hash_mismatch', 'high', ['issuance_id' => (int)$id], getUserId()); http_response_code(409); exit('The stored CRF PDF failed integrity verification.'); }
$accessAction = strtolower(trim((string)($_GET['action'] ?? '')));
$download = !empty($_GET['download']);
if ($accessAction === 'print') {
    crf_2a_record_history($pdo, (int)$id, (string)$issuance['crf_number'], 'printed', 'PDF opened for printing');
} elseif ($accessAction === 'download' || $download) {
    crf_2a_record_history($pdo, (int)$id, (string)$issuance['crf_number'], 'downloaded', 'PDF downloaded');
}
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/pdf'); header('Content-Length: ' . filesize($abs)); header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename((string)$issuance['pdf_filename']) . '"'); header('Cache-Control: private, max-age=0, must-revalidate'); header('X-Content-Type-Options: nosniff'); readfile($abs); exit;
