<?php
/**
 * Create Batch Upload
 * Initialize a new batch upload operation
 */

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = $_SESSION['user_id'] ?? 1;

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
    log_activity($pdo, $user_id, 'BATCH_UPLOAD', "Created batch: $batch_name ($total_files files)");

    echo json_encode([
        'success' => true,
        'message' => 'Batch created successfully',
        'batch_id' => $batch_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
