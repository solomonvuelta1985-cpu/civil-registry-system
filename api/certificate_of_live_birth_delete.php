<?php
/**
 * Certificate of Live Birth - Delete API
 * Handles soft delete (status change) and hard delete (permanent removal)
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    // Get record ID
    $record_id = sanitize_input($_POST['record_id'] ?? '');
    $delete_type = sanitize_input($_POST['delete_type'] ?? 'soft'); // 'soft' or 'hard'

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Check if record exists
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = :id");
    $stmt->execute([':id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Record not found.', null, 404);
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        if ($delete_type === 'hard') {
            // Hard delete: Permanently remove from database
            $stmt = $pdo->prepare("DELETE FROM certificate_of_live_birth WHERE id = :id");
            $stmt->execute([':id' => $record_id]);

            // Delete associated PDF file
            if (!empty($record['pdf_filename'])) {
                delete_file($record['pdf_filename']);
            }

            // Log activity
            log_activity(
                $pdo,
                'HARD_DELETE_CERTIFICATE',
                "Permanently deleted Certificate of Live Birth: Registry No. {$record['registry_no']} (ID: {$record_id})",
                $_SESSION['user_id'] ?? null
            );

            $message = "Certificate of Live Birth permanently deleted.";

        } else {
            // Soft delete: Change status to 'Deleted'
            $stmt = $pdo->prepare("UPDATE certificate_of_live_birth SET status = 'Deleted', updated_at = NOW(), updated_by = :updated_by WHERE id = :id");
            $stmt->execute([
                ':id' => $record_id,
                ':updated_by' => $_SESSION['user_id'] ?? null
            ]);

            // Log activity
            log_activity(
                $pdo,
                'SOFT_DELETE_CERTIFICATE',
                "Soft deleted Certificate of Live Birth: Registry No. {$record['registry_no']} (ID: {$record_id})",
                $_SESSION['user_id'] ?? null
            );

            $message = "Certificate of Live Birth moved to trash.";
        }

        // Commit transaction
        $pdo->commit();

        json_response(true, $message, ['id' => $record_id], 200);

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();

        // Log error
        error_log("Database Delete Error: " . $e->getMessage());

        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected Error: " . $e->getMessage());

    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
?>
