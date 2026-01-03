<?php
/**
 * Users List API
 * Returns paginated list of users with filtering and sorting
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

try {
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? min(100, max(5, (int)$_GET['per_page'])) : 10;
    $offset = ($page - 1) * $per_page;

    // Search
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Sorting
    $allowed_sort = ['id', 'username', 'full_name', 'email', 'role', 'status', 'created_at', 'last_login'];
    $sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort) ? $_GET['sort_by'] : 'created_at';
    $sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

    // Filters
    $role_filter = isset($_GET['role']) && in_array($_GET['role'], ['Admin', 'Encoder', 'Viewer']) ? $_GET['role'] : '';
    $status_filter = isset($_GET['status']) && in_array($_GET['status'], ['Active', 'Inactive']) ? $_GET['status'] : '';

    // Build query
    $where_clauses = [];
    $params = [];

    if (!empty($search)) {
        $where_clauses[] = "(username LIKE :search OR full_name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if (!empty($role_filter)) {
        $where_clauses[] = "role = :role";
        $params[':role'] = $role_filter;
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "status = :status";
        $params[':status'] = $status_filter;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Count total
    $count_sql = "SELECT COUNT(*) as total FROM users {$where_sql}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $per_page);

    // Fetch users
    $sql = "SELECT id, username, full_name, email, role, status, created_at, last_login
            FROM users {$where_sql}
            ORDER BY {$sort_by} {$sort_order}
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    // Format dates
    foreach ($users as &$user) {
        $user['created_at_formatted'] = date('M d, Y', strtotime($user['created_at']));
        $user['last_login_formatted'] = $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';
    }

    echo json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
