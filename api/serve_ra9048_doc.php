<?php
/**
 * Secure DOCX serve endpoint for RA 9048 generated documents.
 * Mirrors api/serve_pdf.php but allows .docx instead of .pdf, and only
 * serves files under uploads/ra9048/generated/.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if ($file === '') {
    http_response_code(400);
    echo 'Missing file parameter';
    exit;
}

// Path-traversal guard
if (strpos($file, '..') !== false || strpos($file, "\0") !== false) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Normalize separators
$file = str_replace('\\', '/', $file);

// Whitelist allowed characters
if (!preg_match('/^[a-zA-Z0-9\/\_\-\.]+$/', $file)) {
    http_response_code(400);
    echo 'Invalid file path';
    exit;
}

// Must live under ra9048/generated/
if (strpos($file, 'ra9048/generated/') !== 0) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Allowed extensions
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, ['docx'], true)) {
    http_response_code(400);
    echo 'Only DOCX files can be served';
    exit;
}

$absPath = realpath(UPLOAD_PATH . $file);
$baseAbs = realpath(UPLOAD_PATH);

if ($absPath === false || $baseAbs === false || strpos($absPath, $baseAbs) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$mime     = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
$filename = basename($absPath);

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absPath));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($absPath);
exit;
