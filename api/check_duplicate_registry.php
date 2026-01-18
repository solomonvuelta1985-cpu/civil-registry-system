<?php
/**
 * Check Duplicate Registry Number API
 * Checks if a registry number already exists in the database
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    $record_type = sanitize_input($_POST['record_type'] ?? '');
    $record_id = sanitize_input($_POST['record_id'] ?? null); // For edit mode

    // Log the request for debugging
    error_log("Duplicate Check Request - Registry: {$registry_no}, Type: {$record_type}, RecordID: " . ($record_id ?? 'null'));

    // Validation
    if (empty($registry_no)) {
        json_response(false, 'Registry number is required.', null, 400);
    }

    if (empty($record_type)) {
        json_response(false, 'Record type is required.', null, 400);
    }

    // Map record types to table names
    $table_map = [
        'birth' => 'certificate_of_live_birth',
        'marriage' => 'certificate_of_marriage',
        'death' => 'certificate_of_death',
        'marriage_license' => 'application_for_marriage_license'
    ];

    if (!isset($table_map[$record_type])) {
        json_response(false, 'Invalid record type.', null, 400);
    }

    $table_name = $table_map[$record_type];

    // Check if registry number exists (excluding current record if in edit mode)
    $sql = "SELECT id, registry_no FROM {$table_name}
            WHERE registry_no = :registry_no
            AND status = 'Active'";

    // If editing, exclude the current record
    if (!empty($record_id)) {
        $sql .= " AND id != :record_id";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':registry_no', $registry_no);

    if (!empty($record_id)) {
        $stmt->bindParam(':record_id', $record_id);
    }

    $stmt->execute();
    $existing_record = $stmt->fetch();

    if ($existing_record) {
        json_response(false, 'Registry number already exists in the records.', [
            'exists' => true,
            'registry_no' => $existing_record['registry_no'],
            'record_id' => $existing_record['id']
        ], 200);
    } else {
        json_response(true, 'Registry number is available.', [
            'exists' => false
        ], 200);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    json_response(false, 'Database error occurred.', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    json_response(false, 'An error occurred.', null, 500);
}
?>
