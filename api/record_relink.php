<?php
/**
 * Record Re-link API — Restore a previously unlinked double registration
 * POST: Admin only. Sets status back to 'active' if neither record is bound elsewhere.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only administrators can re-link records']);
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

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed. Please refresh the page and try again.']);
    exit;
}

$link_id = (int)($input['link_id'] ?? 0);
if ($link_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid link ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM record_links WHERE id = :id AND status = 'unlinked' LIMIT 1");
    $stmt->execute([':id' => $link_id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Unlinked link not found (it may have already been re-linked).']);
        exit;
    }

    // Either record may have been linked to a different record after the unlink — block re-link in that case
    if (
        is_record_linked($pdo, (int)$link['primary_certificate_id'], $link['primary_certificate_type']) ||
        is_record_linked($pdo, (int)$link['duplicate_certificate_id'], $link['duplicate_certificate_type'])
    ) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'One or both records are now linked to a different record. Cannot re-link.']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null;

    $pdo->beginTransaction();

    $sql = "UPDATE record_links SET
                status = 'active',
                unlinked_reason = NULL,
                unlinked_by = NULL,
                unlinked_at = NULL
            WHERE id = :id AND status = 'unlinked'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $link_id]);

    log_activity(
        $pdo,
        'RELINK_DOUBLE_REGISTRATION',
        "Re-linked double registration #{$link_id}. Primary: {$link['primary_certificate_type']} ID:{$link['primary_certificate_id']}, Duplicate: {$link['duplicate_certificate_type']} ID:{$link['duplicate_certificate_id']}. link_id:{$link_id}",
        $user_id
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Records re-linked. The duplicate is again blocked from issuance.',
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Record relink error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
