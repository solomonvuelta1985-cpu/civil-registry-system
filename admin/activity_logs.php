<?php
/**
 * Activity Logs Viewer
 * Audit trail for CREATE, UPDATE, DELETE, ARCHIVE, RESTORE actions
 * Admin access only
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
if (!in_array($per_page, [25, 50, 100, 250], true)) {
    $per_page = 50;
}
$offset = ($page - 1) * $per_page;

// Filters
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where_clauses = [];
$params = [];

if ($action_filter) {
    $where_clauses[] = "al.action = :action";
    $params[':action'] = $action_filter;
}

if ($user_filter !== '' && $user_filter !== null) {
    $where_clauses[] = "al.user_id = :user_id";
    $params[':user_id'] = (int)$user_filter;
}

if ($search_term) {
    $where_clauses[] = "(al.details LIKE :search OR al.ip_address LIKE :search2)";
    $params[':search'] = "%{$search_term}%";
    $params[':search2'] = "%{$search_term}%";
}

if ($date_from) {
    $where_clauses[] = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_clauses[] = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Total count (filtered)
$count_sql = "SELECT COUNT(*) as total FROM activity_logs al {$where_sql}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetch()['total'];
$total_pages = max(1, (int)ceil($total_records / $per_page));

// Logs with user info
$sql = "SELECT al.*, u.username, u.full_name, u.role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        {$where_sql}
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Stats — today, this week, most active user, most common action
$today_count = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$week_count = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$all_time_count = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

$most_active_user = $pdo->query("
    SELECT u.full_name, u.username, COUNT(*) as activity_count
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.user_id IS NOT NULL
      AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY al.user_id
    ORDER BY activity_count DESC
    LIMIT 1
")->fetch();

$top_action = $pdo->query("
    SELECT action, COUNT(*) as count
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action
    ORDER BY count DESC
    LIMIT 1
")->fetch();

// Distinct actions for filter dropdown
$actions_sql = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$actions = $pdo->query($actions_sql)->fetchAll(PDO::FETCH_COLUMN);

// Users list for filter dropdown
$users_sql = "SELECT id, username, full_name FROM users ORDER BY full_name";
$users = $pdo->query($users_sql)->fetchAll();

/**
 * Classify an action into a category key.
 * Used for both badge color and dropdown grouping.
 */
function activity_category($action) {
    $a = strtoupper($action);
    if ($a === 'LOGIN' || $a === 'LOGOUT') return 'auth';
    if (strpos($a, 'HARD_DELETE') !== false) return 'hard-delete';
    if (strpos($a, 'SOFT_DELETE') !== false || strpos($a, 'DELETE') !== false) return 'soft-delete';
    if (strpos($a, 'CREATE') !== false || strpos($a, 'REGISTER') !== false) return 'create';
    if (strpos($a, 'UPDATE') !== false || strpos($a, 'EDIT') !== false) return 'update';
    if (strpos($a, 'UNARCHIVE') !== false || strpos($a, 'RESTORE') !== false) return 'restore';
    if (strpos($a, 'ARCHIVE') !== false) return 'archive';
    return 'other';
}

function activity_badge_class($action) {
    return 'badge-' . activity_category($action);
}

$CATEGORY_LABELS = [
    'create'      => 'Create',
    'update'      => 'Update / Edit',
    'soft-delete' => 'Delete (Soft)',
    'hard-delete' => 'Delete (Permanent)',
    'archive'     => 'Archive',
    'restore'     => 'Restore / Unarchive',
    'auth'        => 'Authentication',
    'other'       => 'Other',
];

// Group distinct actions by category for the dropdown
$grouped_actions = [];
foreach ($actions as $act) {
    $cat = activity_category($act);
    $grouped_actions[$cat][] = $act;
}
// Sort each group alphabetically
foreach ($grouped_actions as &$list) { sort($list); }
unset($list);
// Preserve category display order
$grouped_actions_ordered = [];
foreach ($CATEGORY_LABELS as $cat => $_) {
    if (!empty($grouped_actions[$cat])) {
        $grouped_actions_ordered[$cat] = $grouped_actions[$cat];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo APP_SHORT_NAME; ?></title>
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8f9fa;
            color: #212529;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 12px;
            opacity: 0.9;
            transition: opacity 0.2s;
        }

        .back-link:hover { opacity: 1; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-card h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
        }

        .stat-sub {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 4px;
            font-weight: 500;
        }

        .filter-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .form-group { display: flex; flex-direction: column; }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #495057;
        }

        .form-control {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }

        .logs-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }

        th {
            padding: 14px 16px;
            text-align: left;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.875rem;
            vertical-align: top;
        }

        tr:hover { background: #f8f9fa; }

        .action-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-family: 'Courier New', monospace;
            white-space: nowrap;
        }

        .badge-create      { background: #d4edda; color: #155724; }
        .badge-update      { background: #d1ecf1; color: #0c5460; }
        .badge-soft-delete { background: #fff3cd; color: #856404; }
        .badge-hard-delete { background: #f8d7da; color: #721c24; }
        .badge-archive     { background: #fff4e5; color: #e67700; }
        .badge-restore     { background: #e2e3ff; color: #383d9e; }
        .badge-auth        { background: #e7f5ff; color: #0b5ed7; }
        .badge-other       { background: #e9ecef; color: #495057; }

        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
            margin-left: 4px;
        }

        .role-Admin   { background: #d4edda; color: #155724; }
        .role-Encoder { background: #d1ecf1; color: #0c5460; }
        .role-Viewer  { background: #e9ecef; color: #495057; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            padding: 20px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: #495057;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .no-logs {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-logs i {
            color: #dee2e6;
            margin-bottom: 16px;
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .timestamp {
            white-space: nowrap;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }

        .details-cell {
            max-width: 420px;
            word-break: break-word;
        }

        code {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <div class="container">
        <div class="page-header">
            <h1>
                <i data-lucide="history" style="width: 32px; height: 32px;"></i>
                User Activity Logs
            </h1>
            <p>Audit trail of create, edit, delete, archive, and restore actions across all records</p>
            <a href="dashboard.php" class="back-link">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Activities Today</h3>
                <div class="stat-value"><?php echo number_format($today_count); ?></div>
                <div class="stat-sub">All-time: <?php echo number_format($all_time_count); ?></div>
            </div>
            <div class="stat-card">
                <h3>Last 7 Days</h3>
                <div class="stat-value"><?php echo number_format($week_count); ?></div>
                <div class="stat-sub">Rolling week</div>
            </div>
            <div class="stat-card">
                <h3>Most Active User (7d)</h3>
                <div class="stat-value" style="font-size: 1.1rem;">
                    <?php if ($most_active_user && $most_active_user['full_name']): ?>
                        <?php echo htmlspecialchars($most_active_user['full_name']); ?>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub">
                    <?php if ($most_active_user): ?>
                        <?php echo number_format($most_active_user['activity_count']); ?> actions
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Top Action (7d)</h3>
                <div class="stat-value" style="font-size: 1.1rem; font-family: 'Courier New', monospace;">
                    <?php if ($top_action): ?>
                        <?php echo htmlspecialchars($top_action['action']); ?>
                    <?php else: ?>
                        <span class="text-muted">No data</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub">
                    <?php if ($top_action): ?>
                        <?php echo number_format($top_action['count']); ?> times
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h3 style="margin-bottom: 0;">Filter & Search</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Action</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($grouped_actions_ordered as $cat => $list): ?>
                                <optgroup label="<?php echo htmlspecialchars($CATEGORY_LABELS[$cat]); ?>">
                                    <?php foreach ($list as $act): ?>
                                        <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action_filter === $act ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($act); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)$user_filter === (string)$u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Search (Details or IP)</label>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <label>Per Page</label>
                        <select name="per_page" class="form-control">
                            <option value="25"  <?php echo $per_page === 25  ? 'selected' : ''; ?>>25</option>
                            <option value="50"  <?php echo $per_page === 50  ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                            <option value="250" <?php echo $per_page === 250 ? 'selected' : ''; ?>>250</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 16px; display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
                        Apply Filters
                    </button>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-table-container">
            <div class="table-header">
                <h2>Activity Events (<?php echo number_format($total_records); ?> total)</h2>
                <div style="color: #6c757d; font-size: 0.875rem;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
            </div>

            <?php if ($logs): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="timestamp">
                                    <?php echo format_datetime($log['created_at']); ?>
                                </td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <strong><?php echo htmlspecialchars($log['full_name'] ?: $log['username']); ?></strong>
                                        <?php if ($log['role']): ?>
                                            <span class="role-badge role-<?php echo htmlspecialchars($log['role']); ?>">
                                                <?php echo htmlspecialchars($log['role']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">System / Deleted User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="action-badge <?php echo activity_badge_class($log['action']); ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td class="details-cell">
                                    <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['ip_address'])): ?>
                                        <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php
                    $qs_base = $_GET;
                    unset($qs_base['page']);
                    $qs = http_build_query($qs_base);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=1&<?php echo $qs; ?>">First</a>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo $qs; ?>">Previous</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo $qs; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo $qs; ?>">Next</a>
                        <a href="?page=<?php echo $total_pages; ?>&<?php echo $qs; ?>">Last</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-logs">
                    <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                    <h3>No Activity Logs Found</h3>
                    <p>No activities match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
