<?php
/**
 * Users Delete API
 * Delete (soft delete) user
 */

header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Check authentication and permission
if (!isLoggedIn() || !hasPermission('users_delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
requireCSRFToken();

// Only accept POST or DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(requestBody(), true);

// Validate user ID
if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = (int)$input['id'];

// Prevent self-deletion
if ($user_id === (int)getUserId()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

try {
    $pdo->beginTransaction();
    // Check if user exists
    $check_sql = "SELECT id, username, role, status FROM users WHERE id = :id FOR UPDATE";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $user_id]);
    $existing_user = $check_stmt->fetch();

    if (!$existing_user) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if (!canManageUserRole((string)$existing_user['role'])) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($existing_user['role'] === 'Super Admin' && $existing_user['status'] === 'Active') {
        $countStmt = $pdo->query("SELECT id FROM users WHERE role='Super Admin' AND status='Active' ORDER BY id FOR UPDATE");
        if (count($countStmt->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'At least one active Super Admin account must remain.']);
            exit;
        }
    }

    // Soft delete - set status to Inactive
    $sql = "UPDATE users SET status = 'Inactive' WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $pdo->commit();

    // Log activity
    log_activity($pdo, 'delete', "Deleted user: {$existing_user['username']}", $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('users_delete failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to deactivate user.']);
}
