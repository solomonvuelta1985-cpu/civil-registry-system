<?php
/**
 * Archive Bulk API
 * Archive or unarchive MANY records of a single record type in one request.
 *
 * Request (POST):
 *   - record_type : 'birth' | 'marriage' | 'death' | 'marriage_license', required
 *   - action      : 'archive' | 'unarchive', required
 *   - ids[]       : array of record IDs (or ids as comma-separated string), required
 *
 * Implementation notes:
 *   - All IDs must belong to the same record_type (one table per call).
 *   - Only records in the correct starting status are updated:
 *       archive   -> only Active records are touched
 *       unarchive -> only Archived records are touched
 *   - Records with status 'Deleted' are skipped (protects trashed records).
 *   - Returns counts of affected and skipped IDs so the UI can inform the user.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    json_response(false, 'Unauthorized access. Please log in.', null, 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
    exit;
}

$type_map = [
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'label' => 'Birth Records',
    ],
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'label' => 'Marriage Records',
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'label' => 'Death Records',
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'label' => 'Marriage License Applications',
    ],
];

try {
    $record_type = sanitize_input($_POST['record_type'] ?? '');
    $action      = sanitize_input($_POST['action']      ?? '');

    // Accept ids as array or comma-separated string
    $raw_ids = $_POST['ids'] ?? [];
    if (is_string($raw_ids)) {
        $raw_ids = array_filter(array_map('trim', explode(',', $raw_ids)));
    }
    if (!is_array($raw_ids)) {
        $raw_ids = [];
    }

    // Filter to positive integers only
    $ids = [];
    foreach ($raw_ids as $id) {
        $int_id = (int)$id;
        if ($int_id > 0) {
            $ids[] = $int_id;
        }
    }
    $ids = array_values(array_unique($ids));

    if (!isset($type_map[$record_type])) {
        json_response(false, 'Invalid record type.', null, 400);
        exit;
    }
    if (!in_array($action, ['archive', 'unarchive'], true)) {
        json_response(false, 'Invalid action. Must be "archive" or "unarchive".', null, 400);
        exit;
    }
    if (empty($ids)) {
        json_response(false, 'No valid record IDs provided.', null, 400);
        exit;
    }
    // Guard against absurdly large requests
    if (count($ids) > 500) {
        json_response(false, 'Too many records in a single request (max 500).', null, 400);
        exit;
    }

    $config = $type_map[$record_type];

    if (!canArchive($record_type)) {
        json_response(false, 'You do not have permission to archive these records.', null, 403);
        exit;
    }

    // Determine status transition
    if ($action === 'archive') {
        $from_status = 'Active';
        $to_status   = 'Archived';
        $log_action  = 'BULK_ARCHIVE_CERTIFICATES';
        $verb        = 'archived';
    } else {
        $from_status = 'Archived';
        $to_status   = 'Active';
        $log_action  = 'BULK_UNARCHIVE_CERTIFICATES';
        $verb        = 'unarchived';
    }

    // Build placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $pdo->beginTransaction();

    try {
        // Update only rows that are in the expected starting status.
        // This protects Deleted (trash) rows automatically and keeps the
        // operation idempotent if re-submitted.
        $sql = "UPDATE {$config['table']}
                SET status = ?, updated_at = NOW(), updated_by = ?
                WHERE status = ? AND id IN ($placeholders)";

        $bind = array_merge(
            [$to_status, $_SESSION['user_id'] ?? null, $from_status],
            $ids
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);

        $affected = $stmt->rowCount();
        $skipped  = count($ids) - $affected;

        log_activity(
            $pdo,
            $log_action,
            "Bulk {$verb} {$affected} {$config['label']} (requested: " . count($ids) . ", skipped: {$skipped})",
            $_SESSION['user_id'] ?? null
        );

        $pdo->commit();

        $message = $affected > 0
            ? ucfirst($verb) . " {$affected} record" . ($affected === 1 ? '' : 's') . "."
                . ($skipped > 0 ? " {$skipped} skipped (wrong status or not found)." : '')
            : "No records were {$verb}. They may already be in the target status or in the Trash.";

        json_response($affected > 0, $message, [
            'affected'  => $affected,
            'skipped'   => $skipped,
            'requested' => count($ids),
        ], 200);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Archive Bulk DB Error: " . $e->getMessage());
        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    error_log("Archive Bulk Error: " . $e->getMessage());
    json_response(false, 'An unexpected error occurred.', null, 500);
}
