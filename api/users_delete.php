<?php
/**
 * Users Delete API
 * Delete (soft delete) user
 */

header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication and permission
if (!isLoggedIn() || !hasPermission('users_delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST or DELETE
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Validate user ID
if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = (int)$input['id'];

// Prevent self-deletion
if ($user_id === getUserId()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

try {
    // Check if user exists
    $check_sql = "SELECT id, username, role FROM users WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $user_id]);
    $existing_user = $check_stmt->fetch();

    if (!$existing_user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Soft delete - set status to Inactive
    $sql = "UPDATE users SET status = 'Inactive' WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);

    // Log activity
    logActivity('delete', 'users', $user_id, "Deleted user: {$existing_user['username']}");

    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
