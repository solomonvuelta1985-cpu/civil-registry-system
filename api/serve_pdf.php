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
require_once '../includes/security.php';

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
// Path format: {type}/{year}/{last_name}/filename.pdf, {type}/{last_name}/filename.pdf, or just filename.pdf (legacy)
$parts = explode('/', $file);
$type = null;

if (count($parts) >= 2) {
    // Organized format: first segment is always the certificate type
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

if (!isset($permission_map[$type])) {
    http_response_code(403); echo 'Forbidden'; exit;
}
if (!hasPermission($permission_map[$type])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Build full file path
$full_path = UPLOAD_DIR . $file;

// Resolve to real path and verify it's within uploads directory
$real_path = realpath($full_path);
$real_upload_dir = realpath(UPLOAD_DIR);

if ($real_path === false || $real_upload_dir === false
    || strpos($real_path, rtrim($real_upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

if (!is_file($real_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Database table allowlist used for record binding and hash verification.
$table_map = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

// Require a current, active database record for every served file. This binds
// a path to its certificate and prevents authenticated users from reading an
// unrelated file placed anywhere under uploads/.
$base_name = basename($file);
$recordFound = false;
$stored_hash = null;
$recordType = $type;
foreach ($table_map as $candidateType => $candidateTable) {
    try {
        $stmt = $pdo->prepare("SELECT pdf_hash FROM {$candidateTable}
            WHERE (pdf_filename = :name OR pdf_filename = :path OR pdf_filepath LIKE :suffix)
              AND status = 'Active' LIMIT 1");
        $stmt->execute([':name' => $base_name, ':path' => $file, ':suffix' => '%' . $base_name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $recordFound = true;
            $recordType = $candidateType;
            $stored_hash = $row['pdf_hash'] ?? null;
            break;
        }
    } catch (PDOException $e) {
        error_log('serve_pdf record lookup error: ' . $e->getMessage());
        http_response_code(503); exit('File authorization is temporarily unavailable');
    }
}
if (!$recordFound || $recordType !== $type || !hasPermission($permission_map[$recordType])) {
    http_response_code(403); echo 'Access denied'; exit;
}

// PDF integrity check — verify stored hash matches file on disk
if ($recordFound) {
    try {
        if ($stored_hash !== null && $stored_hash !== '') {
            if (!is_string($stored_hash) || !preg_match('/^[a-f0-9]{64}$/i', $stored_hash)) {
                http_response_code(409);
                exit('Invalid stored PDF integrity metadata');
            }
            $actual_hash = hash_file('sha256', $real_path);
            if (!is_string($actual_hash) || !hash_equals(strtolower($stored_hash), strtolower($actual_hash))) {
                error_log("PDF_INTEGRITY_FAILURE: {$real_path} stored={$stored_hash} actual={$actual_hash}");
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('PDF_INTEGRITY_FAILURE', 'HIGH',
                        json_encode(['file' => basename($real_path), 'type' => $type]), $_SESSION['user_id'] ?? null);
                }
                http_response_code(409);
                header('Content-Type: application/json');
                echo json_encode([
                    'success'       => false,
                    'message'       => 'PDF integrity check failed — this file may be corrupted.',
                    'recovery_hint' => 'Go to Admin → PDF Backup Manager to restore a previous version.',
                ]);
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log('serve_pdf hash check error: ' . $e->getMessage());
        http_response_code(503);
        exit('File integrity verification is temporarily unavailable');
    }
}

// Serve the PDF
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($real_path));
header('Content-Disposition: inline; filename="' . basename($real_path) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($real_path);
exit;
