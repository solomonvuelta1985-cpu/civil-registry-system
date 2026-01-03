<?php
/**
 * Users Update API
 * Update existing user
 */

header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication and permission
if (!isLoggedIn() || !hasPermission('users_edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST or PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
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

// Validate required fields
$required = ['full_name', 'role'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
        exit;
    }
}

// Validate role
$allowed_roles = ['Admin', 'Encoder', 'Viewer'];
if (!in_array($input['role'], $allowed_roles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Validate email if provided
if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password if provided (min 6 chars)
if (!empty($input['password']) && strlen($input['password']) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if user exists
    $check_sql = "SELECT id, username FROM users WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $user_id]);
    $existing_user = $check_stmt->fetch();

    if (!$existing_user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check if email already exists for another user
    if (!empty($input['email'])) {
        $check_email_sql = "SELECT id FROM users WHERE email = :email AND id != :id";
        $check_email_stmt = $pdo->prepare($check_email_sql);
        $check_email_stmt->execute([':email' => $input['email'], ':id' => $user_id]);
        if ($check_email_stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
    }

    // Build update query
    $update_fields = [
        'full_name = :full_name',
        'email = :email',
        'role = :role',
        'status = :status'
    ];

    $params = [
        ':id' => $user_id,
        ':full_name' => $input['full_name'],
        ':email' => $input['email'] ?? null,
        ':role' => $input['role'],
        ':status' => $input['status'] ?? 'Active'
    ];

    // Add password if provided
    if (!empty($input['password'])) {
        $update_fields[] = 'password = :password';
        $params[':password'] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Log activity
    logActivity('update', 'users', $user_id, "Updated user: {$existing_user['username']}");

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
