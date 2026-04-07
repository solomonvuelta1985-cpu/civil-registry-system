<?php
/**
 * Trash Restore API
 * Restores a soft-deleted record (status='Deleted') back to Active
 * Supports: birth, marriage, death, marriage_license
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
    exit;
}

// Admin-only — same rule as delete (only admins manage the Trash)
requireAdminApi('Only administrators can restore records from Trash.');

// CSRF protection
requireCSRFToken();

// Map record type to table and label
$type_map = [
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'label' => 'Certificate of Live Birth'
    ],
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'label' => 'Certificate of Marriage'
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'label' => 'Certificate of Death'
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'label' => 'Application for Marriage License'
    ],
];

try {
    $record_id = sanitize_input($_POST['record_id'] ?? '');
    $record_type = sanitize_input($_POST['record_type'] ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
        exit;
    }

    if (!isset($type_map[$record_type])) {
        json_response(false, 'Invalid record type.', null, 400);
        exit;
    }

    $config = $type_map[$record_type];

    // Admin check already happened at top of file via requireAdminApi().
    // Verify record exists and is currently deleted
    $stmt = $pdo->prepare("SELECT id, registry_no, status FROM {$config['table']} WHERE id = :id");
    $stmt->execute([':id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Record not found.', null, 404);
        exit;
    }

    if ($record['status'] !== 'Deleted') {
        json_response(false, 'Record is not in trash.', null, 400);
        exit;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE {$config['table']}
             SET status = 'Active', updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id"
        );
        $stmt->execute([
            ':updated_by' => $_SESSION['user_id'] ?? null,
            ':id' => $record_id
        ]);

        log_activity(
            $pdo,
            'RESTORE_CERTIFICATE',
            "Restored {$config['label']}: Registry No. {$record['registry_no']} (ID: {$record_id})",
            $_SESSION['user_id'] ?? null
        );

        $pdo->commit();

        json_response(true, "{$config['label']} restored successfully.", ['id' => $record_id], 200);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Trash Restore DB Error: " . $e->getMessage());
        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    error_log("Trash Restore Error: " . $e->getMessage());
    json_response(false, 'An unexpected error occurred.', null, 500);
}
