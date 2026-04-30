<?php
/**
 * Secure DOCX/PDF serve endpoint for RA 9048 generated documents.
 * Only serves files under uploads/ra9048/generated/.
 *
 * Query params:
 *   file=ra9048/generated/petition_X/foo.docx   (or .pdf)
 *   inline=1                                    Optional. Serve with
 *       Content-Disposition: inline so the browser renders it (PDF preview
 *       in an <iframe>) instead of forcing a download.
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
if (!in_array($ext, ['docx', 'pdf'], true)) {
    http_response_code(400);
    echo 'Only DOCX or PDF files can be served';
    exit;
}

$absPath = realpath(UPLOAD_PATH . $file);
$baseAbs = realpath(UPLOAD_PATH);

if ($absPath === false || $baseAbs === false || strpos($absPath, $baseAbs) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$mime = $ext === 'pdf'
    ? 'application/pdf'
    : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
$filename = basename($absPath);

// inline=1 lets the browser render the file (PDF preview in <iframe>) instead
// of forcing a download. Only valid for PDF — DOCX has no native browser viewer.
$inline = !empty($_GET['inline']) && $ext === 'pdf';
$disposition = $inline ? 'inline' : 'attachment';

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absPath));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;
