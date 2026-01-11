<?php
/**
 * Record Details API
 * Fetches complete record details for the preview modal
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get parameters
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$record_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';

// Validate parameters
if ($record_id <= 0 || empty($record_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Configuration for different record types
$record_configs = [
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'permission' => 'marriage_view'
    ],
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'permission' => 'birth_view'
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'permission' => 'death_view'
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'permission' => 'marriage_license_view'
    ]
];

// Validate record type
if (!isset($record_configs[$record_type])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record type']);
    exit;
}

$config = $record_configs[$record_type];

// Check permission
if (!hasPermission($config['permission'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Fetch record
    $sql = "SELECT * FROM {$config['table']} WHERE id = :id AND status = 'Active'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $record_id, PDO::PARAM_INT);
    $stmt->execute();

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    // Return record data
    echo json_encode([
        'success' => true,
        'record' => $record,
        'record_type' => $record_type
    ]);

} catch (PDOException $e) {
    error_log("Record details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
