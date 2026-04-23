<?php
/**
 * Record Unlink API — Remove a double registration link
 * POST: Unlinks two previously linked records (admin only)
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

// Admin only
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only administrators can unlink records']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
$reason = sanitize_input($input['reason'] ?? '');

if ($link_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid link ID']);
    exit;
}

if (empty($reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A reason is required for unlinking']);
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

    $user_id = $_SESSION['user_id'] ?? null;

    $pdo->beginTransaction();

    $sql = "UPDATE record_links SET
                status = 'unlinked',
                unlinked_reason = :reason,
                unlinked_by = :user_id,
                unlinked_at = NOW()
            WHERE id = :id AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reason'  => $reason,
        ':user_id' => $user_id,
        ':id'      => $link_id,
    ]);

    log_activity(
        $pdo,
        'UNLINK_DOUBLE_REGISTRATION',
        "Unlinked double registration link #{$link_id}. Primary: {$link['primary_certificate_type']} ID:{$link['primary_certificate_id']}, Duplicate: {$link['duplicate_certificate_type']} ID:{$link['duplicate_certificate_id']}. Reason: {$reason}. link_id:{$link_id}",
        $user_id
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Records unlinked successfully. Both records are now independent.',
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Record unlink error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
