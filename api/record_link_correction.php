<?php
/**
 * Record Link Correction API
 * PUT: Update correction_status and correction_notes for RA 9048 tracking
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$link_id = (int)($input['link_id'] ?? 0);
$correction_status = sanitize_input($input['correction_status'] ?? '');
$correction_notes = sanitize_input($input['correction_notes'] ?? '');

if ($link_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid link ID']);
    exit;
}

$valid_statuses = ['none', 'pending', 'filed', 'completed'];
if (!in_array($correction_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid correction status']);
    exit;
}

try {
    // Verify the link exists and is active
    $stmt = $pdo->prepare("SELECT * FROM record_links WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute([':id' => $link_id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Active link not found']);
        exit;
    }

    // Check permission based on the primary certificate type
    $perm = $link['primary_certificate_type'] . '_link';
    if (!hasPermission($perm)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;

    $sql = "UPDATE record_links SET
                correction_status = :status,
                correction_notes = :notes,
                needs_correction = :needs
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $correction_status,
        ':notes'  => $correction_notes ?: null,
        ':needs'  => ($correction_status !== 'none' && $correction_status !== 'completed') ? 1 : 0,
        ':id'     => $link_id,
    ]);

    log_activity(
        $pdo,
        'UPDATE_CORRECTION_STATUS',
        "Updated correction status for link #{$link_id} to '{$correction_status}'. link_id:{$link_id}",
        $user_id
    );

    echo json_encode([
        'success' => true,
        'message' => 'Correction status updated to: ' . ucfirst($correction_status),
    ]);

} catch (PDOException $e) {
    error_log("Correction update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
