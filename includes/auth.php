<?php
/**
 * Authentication and Authorization Helper
 * Civil Registry Records Management System
 */

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user's ID
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's full name
 */
function getUserFullName() {
    return $_SESSION['full_name'] ?? 'User';
}

/**
 * Get current user's username
 */
function getUsername() {
    return $_SESSION['username'] ?? '';
}

/**
 * Check if user has a specific permission
 */
function hasPermission($permission_name) {
    global $pdo;

    if (!isLoggedIn()) {
        return false;
    }

    $role = getUserRole();

    // Admin has all permissions
    if ($role === 'Admin') {
        return true;
    }

    try {
        $sql = "SELECT COUNT(*) as count FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = :role AND p.name = :permission";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => $role, ':permission' => $permission_name]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if user has any of the given permissions
 */
function hasAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the given permissions
 */
function hasAllPermissions($permissions) {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Check if current user is Admin
 */
function isAdmin() {
    return getUserRole() === 'Admin';
}

/**
 * Check if current user is Encoder
 */
function isEncoder() {
    return getUserRole() === 'Encoder';
}

/**
 * Check if current user is Viewer
 */
function isViewer() {
    return getUserRole() === 'Viewer';
}

/**
 * Require authentication - redirect to login if not logged in
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ../public/login.php');
        exit;
    }
}

/**
 * Require specific permission - show 403 if not authorized
 */
function requirePermission($permission_name) {
    requireAuth();

    if (!hasPermission($permission_name)) {
        http_response_code(403);
        include __DIR__ . '/../public/403.php';
        exit;
    }
}

/**
 * Require Admin role
 */
function requireAdmin() {
    requireAuth();

    if (!isAdmin()) {
        http_response_code(403);
        include __DIR__ . '/../public/403.php';
        exit;
    }
}

/**
 * Get all permissions for a role
 */
function getRolePermissions($role) {
    global $pdo;

    try {
        $sql = "SELECT p.name, p.description, p.module
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = :role
                ORDER BY p.module, p.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Authenticate user by username and password
 */
function authenticateUser($username, $password) {
    global $pdo;

    try {
        $sql = "SELECT id, username, password, full_name, email, role, status
                FROM users WHERE username = :username AND status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([':id' => $user['id']]);

            return $user;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Set user session after successful login
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'] ?? '';
}

/**
 * Logout user
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

/**
 * Log activity
 */
function logActivity($action, $module, $record_id = null, $details = null) {
    global $pdo;

    if (!isLoggedIn()) {
        return;
    }

    try {
        $sql = "INSERT INTO activity_logs (user_id, action, module, record_id, details, ip_address, created_at)
                VALUES (:user_id, :action, :module, :record_id, :details, :ip_address, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => getUserId(),
            ':action' => $action,
            ':module' => $module,
            ':record_id' => $record_id,
            ':details' => $details,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        // Silently fail - don't break the application for logging errors
    }
}
