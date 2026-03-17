<?php
/**
 * API: Verify PDF Backup Integrity
 * Checks that a backup file is a valid, uncorrupted PDF before it is restored.
 * Admin access required.
 *
 * POST params:
 *   csrf_token  (string) CSRF token
 *   backup_id   (int)    ID from pdf_backups table
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required']); exit; }

requireCSRFToken();

$backup_id = (int)($_POST['backup_id'] ?? 0);
if ($backup_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid backup ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM pdf_backups WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $backup_id]);
    $backup = $stmt->fetch();

    if (!$backup) {
        echo json_encode(['success' => false, 'valid' => false, 'reason' => 'Backup record not found']);
        exit;
    }

    $backup_path = UPLOAD_DIR . $backup['backup_path'];

    if (!file_exists($backup_path)) {
        echo json_encode(['success' => true, 'valid' => false, 'reason' => 'Backup file not found on disk']);
        exit;
    }

    // Magic bytes check
    $handle = fopen($backup_path, 'rb');
    $header = fread($handle, 5);
    fclose($handle);
    if ($header !== '%PDF-') {
        echo json_encode(['success' => true, 'valid' => false, 'reason' => 'Backup file is not a valid PDF (missing PDF header)']);
        exit;
    }

    // Hash check (if we have a stored hash)
    if (!empty($backup['file_hash'])) {
        $actual = hash_file('sha256', $backup_path);
        if (!hash_equals($backup['file_hash'], $actual)) {
            echo json_encode(['success' => true, 'valid' => false, 'reason' => 'Backup file hash mismatch — the backup itself may be corrupted']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'valid' => true, 'reason' => 'Backup file is valid']);

} catch (PDOException $e) {
    error_log('pdf_backup_verify error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
