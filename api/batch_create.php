<?php
/**
 * Create Batch Upload
 * Initialize a new batch upload operation
 */

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new Exception('Invalid request method');
    }
    requireAuth();
    requireCSRFToken();

    $user_id = (int)$_SESSION['user_id'];

    // Get parameters
    $batch_name = isset($_POST['batch_name']) ? sanitize_input($_POST['batch_name']) : null;
    $certificate_type = isset($_POST['certificate_type']) ? sanitize_input($_POST['certificate_type']) : null;
    $total_files = isset($_POST['total_files']) ? (int)$_POST['total_files'] : 0;
    $auto_ocr = isset($_POST['auto_ocr']) ? (bool)$_POST['auto_ocr'] : true;
    $auto_validate = isset($_POST['auto_validate']) ? (bool)$_POST['auto_validate'] : true;

    // Validate
    if (!$batch_name || !$certificate_type || $total_files <= 0) {
        throw new Exception('Missing required parameters');
    }

    $valid_types = ['birth', 'marriage', 'death'];
    if (!in_array($certificate_type, $valid_types)) {
        throw new Exception('Invalid certificate type');
    }
    if (!hasPermission($certificate_type . '_create')) {
        http_response_code(403);
        throw new Exception('You do not have permission to create this batch');
    }
    if ($total_files > 100 || strlen($batch_name) > 200) throw new Exception('Batch limits exceeded');

    // Create batch record
    $stmt = $pdo->prepare("
        INSERT INTO batch_uploads
        (batch_name, certificate_type, total_files, auto_ocr, auto_validate, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'uploading', ?)
    ");

    $stmt->execute([
        $batch_name,
        $certificate_type,
        $total_files,
        $auto_ocr ? 1 : 0,
        $auto_validate ? 1 : 0,
        $user_id
    ]);

    $batch_id = $pdo->lastInsertId();

    // Log activity
    log_activity($pdo, 'BATCH_UPLOAD', "Created batch: $batch_name ($total_files files)", $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Batch created successfully',
        'batch_id' => $batch_id
    ]);

} catch (Exception $e) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }
    error_log('batch_create error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => ($e instanceof PDOException)
            ? 'Request could not be completed. Please try again.'
            : $e->getMessage()
    ]);
}
