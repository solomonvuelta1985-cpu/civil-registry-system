<?php
/**
 * Users Save API
 * Create new user
 */

header('Content-Type: application/json');

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check authentication and permission
if (!isLoggedIn() || !hasPermission('users_create')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['username', 'password', 'full_name', 'role'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
        exit;
    }
}

// Validate username (alphanumeric, 3-50 chars)
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $input['username'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username must be 3-50 alphanumeric characters']);
    exit;
}

// Validate password (min 6 chars)
if (strlen($input['password']) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
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

try {
    // Check if username already exists
    $check_sql = "SELECT id FROM users WHERE username = :username";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':username' => $input['username']]);
    if ($check_stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    // Check if email already exists (if provided)
    if (!empty($input['email'])) {
        $check_email_sql = "SELECT id FROM users WHERE email = :email";
        $check_email_stmt = $pdo->prepare($check_email_sql);
        $check_email_stmt->execute([':email' => $input['email']]);
        if ($check_email_stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
    }

    // Hash password
    $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);

    // Insert user
    $sql = "INSERT INTO users (username, password, full_name, email, role, status, created_at)
            VALUES (:username, :password, :full_name, :email, :role, :status, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username' => $input['username'],
        ':password' => $hashed_password,
        ':full_name' => $input['full_name'],
        ':email' => $input['email'] ?? null,
        ':role' => $input['role'],
        ':status' => $input['status'] ?? 'Active'
    ]);

    $user_id = $pdo->lastInsertId();

    // Log activity
    logActivity('create', 'users', $user_id, "Created user: {$input['username']}");

    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $user_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
