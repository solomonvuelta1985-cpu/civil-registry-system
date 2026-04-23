<?php
/**
 * Duplicate Search API
 * GET: Find potential duplicates for a given birth record
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$record_id = (int)($_GET['id'] ?? 0);
$certificate_type = sanitize_input($_GET['type'] ?? 'birth');

if ($record_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

// Check view permission for the certificate type
$perm_map = [
    'birth' => 'birth_view',
    'marriage' => 'marriage_view',
    'death' => 'death_view',
];
$required_perm = $perm_map[$certificate_type] ?? null;
if (!$required_perm || !hasPermission($required_perm)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $duplicates = find_potential_duplicates($pdo, $record_id, $certificate_type);

    echo json_encode([
        'success' => true,
        'record_id' => $record_id,
        'certificate_type' => $certificate_type,
        'count' => count($duplicates),
        'duplicates' => $duplicates,
    ]);

} catch (Exception $e) {
    error_log("Duplicate search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error searching for duplicates']);
}
