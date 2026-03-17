<?php
/**
 * Secure PDF Serve Endpoint
 * Serves PDF files after authentication and permission checks.
 * Prevents direct access to uploads/ directory.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Get the file parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    http_response_code(400);
    echo 'Missing file parameter';
    exit;
}

// Security: block path traversal
if (strpos($file, '..') !== false || strpos($file, "\0") !== false) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Normalize slashes
$file = str_replace('\\', '/', $file);

// Only allow alphanumeric, slashes, underscores, hyphens, dots
if (!preg_match('/^[a-zA-Z0-9\/\_\-\.]+$/', $file)) {
    http_response_code(400);
    echo 'Invalid file path';
    exit;
}

// Must end with .pdf
if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(400);
    echo 'Only PDF files can be served';
    exit;
}

// Determine certificate type from path for permission check
// Path format: {type}/{year}/filename.pdf OR just filename.pdf (legacy)
$parts = explode('/', $file);
$type = null;

if (count($parts) >= 2) {
    // New format: birth/2026/cert_xxx.pdf
    $type = $parts[0];
} else {
    // Legacy format: cert_xxx.pdf (flat uploads/)
    // Try to determine type from filename prefix
    $filename = $parts[0];
    if (strpos($filename, 'marriage_license_') === 0) {
        $type = 'marriage_license';
    } elseif (strpos($filename, 'marriage_') === 0) {
        $type = 'marriage';
    } elseif (strpos($filename, 'death_') === 0) {
        $type = 'death';
    } else {
        // Default: cert_ prefix is used for both birth and death
        $type = 'birth';
    }
}

// Permission mapping
$permission_map = [
    'birth' => 'birth_view',
    'death' => 'death_view',
    'marriage' => 'marriage_view',
    'marriage_license' => 'marriage_license_view'
];

if (isset($permission_map[$type]) && !hasPermission($permission_map[$type])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Build full file path
$full_path = UPLOAD_DIR . $file;

// Resolve to real path and verify it's within uploads directory
$real_path = realpath($full_path);
$real_upload_dir = realpath(UPLOAD_DIR);

if ($real_path === false || strpos($real_path, $real_upload_dir) !== 0) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

if (!is_file($real_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($real_path);
exit;
