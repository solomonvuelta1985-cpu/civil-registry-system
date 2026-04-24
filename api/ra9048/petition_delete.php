<?php
/**
 * RA 9048 Petition — Delete API
 * Soft delete (status → Deleted) and hard delete (permanent removal)
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

requireAdminApi('Only administrators can delete petition records.');
requireCSRFToken();

try {
    $record_id   = sanitize_input($_POST['record_id'] ?? '');
    $delete_type = sanitize_input($_POST['delete_type'] ?? 'soft');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Check if record exists
    $stmt = $pdo_ra->prepare("SELECT * FROM petitions WHERE id = :id");
    $stmt->execute([':id' => (int) $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Record not found.', null, 404);
    }

    $pdo_ra->beginTransaction();

    if ($delete_type === 'hard') {
        $stmt = $pdo_ra->prepare("DELETE FROM petitions WHERE id = :id");
        $stmt->execute([':id' => $record_id]);

        if (!empty($record['pdf_filename'])) {
            delete_file($record['pdf_filename']);
        }

        log_activity($pdo, 'RA9048 Petition Hard Deleted', "Permanently deleted Petition #{$record_id} ({$record['petition_type']}) for {$record['document_owner_names']}", $_SESSION['user_id'] ?? null);

        $message = 'Petition record permanently deleted.';
    } else {
        $stmt = $pdo_ra->prepare("UPDATE petitions SET status = 'Deleted', updated_by = :updated_by WHERE id = :id");
        $stmt->execute([
            ':id'         => $record_id,
            ':updated_by' => $_SESSION['user_id'] ?? null,
        ]);

        log_activity($pdo, 'RA9048 Petition Deleted', "Soft deleted Petition #{$record_id} ({$record['petition_type']}) for {$record['document_owner_names']}", $_SESSION['user_id'] ?? null);

        $message = 'Petition record moved to trash.';
    }

    $pdo_ra->commit();

    json_response(true, $message, ['id' => $record_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Petition delete error: " . $e->getMessage());
    json_response(false, 'Database error. Please try again.', null, 500);
}
