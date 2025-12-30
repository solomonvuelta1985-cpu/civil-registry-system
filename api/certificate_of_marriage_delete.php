<?php
/**
 * Certificate of Marriage - Delete API
 * Soft delete (mark as Deleted) instead of permanent deletion
 */

require_once '../includes/session_config.php';
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Optional: Check authentication
// if (!isLoggedIn()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
//     exit;
// }

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get record ID
    $data = json_decode(file_get_contents('php://input'), true);
    $record_id = sanitize_input($data['id'] ?? '');

    if (empty($record_id)) {
        echo json_encode(['success' => false, 'message' => 'Record ID is required.']);
        exit;
    }

    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM certificate_of_marriage WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    // Soft delete - update status to 'Deleted'
    $sql = "UPDATE certificate_of_marriage SET status = 'Deleted', updated_by = :updated_by WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    $updated_by = $_SESSION['user_id'] ?? 1;

    if ($stmt->execute([':updated_by' => $updated_by, ':id' => $record_id])) {
        echo json_encode([
            'success' => true,
            'message' => 'Marriage certificate deleted successfully.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}
