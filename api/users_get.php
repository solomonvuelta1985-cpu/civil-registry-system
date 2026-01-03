<?php
/**
 * Get Single User API
 * Returns user details by ID
 */

header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication and permission
if (!isLoggedIn() || !hasPermission('users_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate user ID
if (empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = (int)$_GET['id'];

try {
    $sql = "SELECT id, username, full_name, email, role, status, created_at, last_login
            FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Format dates
    $user['created_at_formatted'] = date('M d, Y', strtotime($user['created_at']));
    $user['last_login_formatted'] = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';

    echo json_encode([
        'success' => true,
        'data' => $user
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
