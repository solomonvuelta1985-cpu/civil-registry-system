<?php
/**
 * Authentication and Authorization Helper
 * Civil Registry Document Management System (CRDMS)
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
    if (!isLoggedIn()) {
        return false;
    }

    // Admin has all permissions
    if (getUserRole() === 'Admin') {
        return true;
    }

    // Check cached permissions in session (loaded at login).
    // Once per request, compare the cached count against the DB count so that
    // permissions granted/revoked after login are picked up without requiring
    // a logout/login cycle.
    $permissions = $_SESSION['permissions'] ?? null;
    if ($permissions !== null) {
        static $permissions_verified = false;
        if (!$permissions_verified) {
            $permissions_verified = true;
            global $pdo;
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role = :role");
                $stmt->execute([':role' => getUserRole()]);
                $db_count = (int)($stmt->fetch()['cnt'] ?? 0);
                if ($db_count !== (int)($_SESSION['permissions_count'] ?? -1)) {
                    // Permission set changed since login — refresh the session cache
                    $perms = getRolePermissions(getUserRole());
                    $_SESSION['permissions'] = array_column($perms, 'name');
                    $_SESSION['permissions_count'] = $db_count;
                    $permissions = $_SESSION['permissions'];
                }
            } catch (PDOException $e) {
                // Keep using cached permissions if DB check fails
            }
        }
        return in_array($permission_name, $permissions, true);
    }

    // Fallback: query database if session cache is missing (e.g. old sessions)
    global $pdo;
    try {
        $sql = "SELECT COUNT(*) as count FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = :role AND p.name = :permission";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => getUserRole(), ':permission' => $permission_name]);
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
 * Archive permission map — central place to resolve record type -> archive permission name.
 * Used by all archive-related code (APIs, records viewer, archives page).
 * To change the permission for a record type, change it in ONE place here.
 */
function getArchivePermissionName($record_type) {
    $map = [
        'birth'            => 'birth_archive',
        'marriage'         => 'marriage_archive',
        'death'            => 'death_archive',
        'marriage_license' => 'marriage_license_archive',
    ];
    return $map[$record_type] ?? null;
}

/**
 * Check if current user can archive/unarchive records of the given type.
 * Wrapping the permission check in a helper gives us a single line to change
 * later if we split, rename, or consolidate archive permissions.
 */
function canArchive($record_type) {
    $perm = getArchivePermissionName($record_type);
    return $perm !== null && hasPermission($perm);
}

/**
 * Return the list of all 4 archive permission names.
 * Useful for "at least one archive permission" checks (e.g. admin/archives.php access).
 */
function getAllArchivePermissions() {
    return [
        'birth_archive',
        'marriage_archive',
        'death_archive',
        'marriage_license_archive',
    ];
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
 * Require Admin role for an API endpoint.
 * Emits a JSON 403 (instead of an HTML 403 page) and logs the denied attempt
 * to the activity log so admins can audit probing.
 *
 * Usage at the top of an admin-only API:
 *   requireAdminApi('Only administrators can delete records.');
 */
function requireAdminApi($message = 'Only administrators can perform this action.') {
    if (!isLoggedIn()) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(401);
        }
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.', 'data' => null]);
        exit;
    }

    if (!isAdmin()) {
        // Audit denied attempt
        global $pdo;
        if (function_exists('log_activity') && isset($pdo)) {
            $endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';
            $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            log_activity(
                $pdo,
                'ADMIN_ACTION_DENIED',
                "Non-admin user (role=" . (getUserRole() ?? 'unknown') . ") attempted admin-only endpoint {$endpoint} from {$ip}",
                getUserId()
            );
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(403);
        }
        echo json_encode(['success' => false, 'message' => $message, 'data' => null]);
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

    // Cache permissions in session to avoid DB queries on every page load
    $perms = getRolePermissions($user['role']);
    $_SESSION['permissions'] = array_column($perms, 'name');
    $_SESSION['permissions_count'] = count($_SESSION['permissions']);
}

/**
 * Logout user
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

// NOTE: Activity logging is handled by log_activity() in includes/functions.php.
// Use: log_activity($pdo, $action, $details, $user_id)
