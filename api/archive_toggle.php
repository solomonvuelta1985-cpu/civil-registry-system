<?php
/**
 * Archive Toggle API
 * Archives an Active record OR unarchives an Archived record (single record).
 *
 * Request (POST):
 *   - record_id    : integer, required
 *   - record_type  : 'birth' | 'marriage' | 'death' | 'marriage_license', required
 *   - action       : 'archive' | 'unarchive', required
 *
 * Status transitions:
 *   - archive:   status 'Active'   -> 'Archived'
 *   - unarchive: status 'Archived' -> 'Active'
 *
 * Records with status 'Deleted' cannot be archived/unarchived — use Trash instead.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Authentication
if (!isLoggedIn()) {
    json_response(false, 'Unauthorized access. Please log in.', null, 401);
    exit;
}

// Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
    exit;
}

// Record type -> table + label
$type_map = [
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'label' => 'Certificate of Live Birth',
    ],
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'label' => 'Certificate of Marriage',
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'label' => 'Certificate of Death',
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'label' => 'Application for Marriage License',
    ],
];

try {
    $record_id   = sanitize_input($_POST['record_id']   ?? '');
    $record_type = sanitize_input($_POST['record_type'] ?? '');
    $action      = sanitize_input($_POST['action']      ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
        exit;
    }
    if (!isset($type_map[$record_type])) {
        json_response(false, 'Invalid record type.', null, 400);
        exit;
    }
    if (!in_array($action, ['archive', 'unarchive'], true)) {
        json_response(false, 'Invalid action. Must be "archive" or "unarchive".', null, 400);
        exit;
    }

    $config = $type_map[$record_type];

    // Permission check — uses the central helper from auth.php
    if (!canArchive($record_type)) {
        json_response(false, 'You do not have permission to archive this record.', null, 403);
        exit;
    }

    // Verify record exists and current status matches the action
    $stmt = $pdo->prepare("SELECT id, registry_no, status FROM {$config['table']} WHERE id = :id");
    $stmt->execute([':id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        json_response(false, 'Record not found.', null, 404);
        exit;
    }

    // Block archive/unarchive on deleted (trashed) records
    if ($record['status'] === 'Deleted') {
        json_response(false, 'This record is in the Trash. Restore it first before archiving.', null, 400);
        exit;
    }

    if ($action === 'archive') {
        if ($record['status'] !== 'Active') {
            json_response(false, 'Only Active records can be archived.', null, 400);
            exit;
        }
        $new_status = 'Archived';
        $log_action = 'ARCHIVE_CERTIFICATE';
        $log_verb   = 'Archived';
        $success_msg = "{$config['label']} archived successfully.";
    } else { // unarchive
        if ($record['status'] !== 'Archived') {
            json_response(false, 'Only Archived records can be unarchived.', null, 400);
            exit;
        }
        $new_status = 'Active';
        $log_action = 'UNARCHIVE_CERTIFICATE';
        $log_verb   = 'Unarchived';
        $success_msg = "{$config['label']} unarchived successfully.";
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE {$config['table']}
             SET status = :status, updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id"
        );
        $stmt->execute([
            ':status'     => $new_status,
            ':updated_by' => $_SESSION['user_id'] ?? null,
            ':id'         => $record_id,
        ]);

        log_activity(
            $pdo,
            $log_action,
            "{$log_verb} {$config['label']}: Registry No. {$record['registry_no']} (ID: {$record_id})",
            $_SESSION['user_id'] ?? null
        );

        $pdo->commit();

        json_response(true, $success_msg, [
            'id'         => $record_id,
            'new_status' => $new_status,
        ], 200);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Archive Toggle DB Error: " . $e->getMessage());
        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    error_log("Archive Toggle Error: " . $e->getMessage());
    json_response(false, 'An unexpected error occurred.', null, 500);
}
