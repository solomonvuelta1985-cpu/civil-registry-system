<?php
/**
 * Certificate of Marriage - Delete API
 * Soft delete (mark as Deleted) instead of permanent deletion
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    json_response(false, 'Unauthorized access. Please log in.', null, 401);
    exit;
}

// Check delete permission
if (!hasPermission('marriage_delete')) {
    json_response(false, 'You do not have permission to delete marriage records.', null, 403);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
    exit;
}

try {
    // Get record ID from POST data
    $record_id = sanitize_input($_POST['record_id'] ?? '');
    $delete_type = sanitize_input($_POST['delete_type'] ?? 'soft');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
        exit;
    }

    // Check if record exists
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_marriage WHERE id = :id");
    $stmt->execute([':id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Record not found.', null, 404);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        if ($delete_type === 'hard') {
            // Hard delete: Permanently remove from database
            $stmt = $pdo->prepare("DELETE FROM certificate_of_marriage WHERE id = :id");
            $stmt->execute([':id' => $record_id]);

            // Delete associated PDF file if exists
            if (!empty($record['pdf_filename'])) {
                delete_file($record['pdf_filename']);
            }

            // Log activity
            log_activity(
                $pdo,
                'HARD_DELETE_CERTIFICATE',
                "Permanently deleted Certificate of Marriage: Registry No. {$record['registry_no']} (ID: {$record_id})",
                $_SESSION['user_id'] ?? null
            );

            $message = "Marriage certificate permanently deleted.";
        } else {
            // Soft delete - update status to 'Deleted'
            $stmt = $pdo->prepare("UPDATE certificate_of_marriage SET status = 'Deleted', updated_at = NOW(), updated_by = :updated_by WHERE id = :id");
            $stmt->execute([
                ':updated_by' => $_SESSION['user_id'] ?? null,
                ':id' => $record_id
            ]);

            // Log activity
            log_activity(
                $pdo,
                'SOFT_DELETE_CERTIFICATE',
                "Soft deleted Certificate of Marriage: Registry No. {$record['registry_no']} (ID: {$record_id})",
                $_SESSION['user_id'] ?? null
            );

            $message = "Marriage certificate moved to trash.";
        }

        // Commit transaction
        $pdo->commit();

        json_response(true, $message, ['id' => $record_id], 200);

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Database Delete Error: " . $e->getMessage());
        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    error_log("Unexpected Error: " . $e->getMessage());
    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
