<?php
/**
 * Security Logs Viewer
 * Monitor security events, login attempts, and suspicious activities
 * Admin access only
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin access
requireAdmin();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
$offset = ($page - 1) * $per_page;

// Filters
$severity_filter = isset($_GET['severity']) ? $_GET['severity'] : '';
$event_type_filter = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where_clauses = [];
$params = [];

if ($severity_filter) {
    $where_clauses[] = "severity = :severity";
    $params[':severity'] = $severity_filter;
}

if ($event_type_filter) {
    $where_clauses[] = "event_type = :event_type";
    $params[':event_type'] = $event_type_filter;
}

if ($search_term) {
    $where_clauses[] = "(details LIKE :search OR ip_address LIKE :search2)";
    $params[':search'] = "%{$search_term}%";
    $params[':search2'] = "%{$search_term}%";
}

if ($date_from) {
    $where_clauses[] = "DATE(created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_clauses[] = "DATE(created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM security_logs {$where_sql}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get logs
$sql = "SELECT sl.*, u.username, u.full_name
        FROM security_logs sl
        LEFT JOIN users u ON sl.user_id = u.id
        {$where_sql}
        ORDER BY sl.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Get event type counts
$event_stats_sql = "SELECT event_type, COUNT(*) as count FROM security_logs {$where_sql} GROUP BY event_type ORDER BY count DESC LIMIT 10";
$event_stats_stmt = $pdo->prepare($event_stats_sql);
$event_stats_stmt->execute($params);
$event_stats = $event_stats_stmt->fetchAll();

// Get severity counts
$severity_stats_sql = "SELECT severity, COUNT(*) as count FROM security_logs {$where_sql} GROUP BY severity";
$severity_stats_stmt = $pdo->prepare($severity_stats_sql);
$severity_stats_stmt->execute($params);
$severity_stats = $severity_stats_stmt->fetchAll();

// Get recent failed logins
$failed_logins_sql = "SELECT ip_address, COUNT(*) as attempts, MAX(created_at) as last_attempt
                      FROM security_logs
                      WHERE event_type = 'LOGIN_FAILED'
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      GROUP BY ip_address
                      ORDER BY attempts DESC
                      LIMIT 5";
$failed_logins_stmt = $pdo->query($failed_logins_sql);
$failed_logins = $failed_logins_stmt->fetchAll();

// Get all unique event types for filter
$event_types_sql = "SELECT DISTINCT event_type FROM security_logs ORDER BY event_type";
$event_types = $pdo->query($event_types_sql)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - <?php echo APP_SHORT_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .severity-LOW { color: #28a745; }
        .severity-MEDIUM { color: #ffc107; }
        .severity-HIGH { color: #fd7e14; }
        .severity-CRITICAL { color: #dc3545; }

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

        .form-group {
            display: flex;
            flex-direction: column;
        }

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
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

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
        }

        tr:hover {
            background: #f8f9fa;
        }

        .severity-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-low {
            background: #d4edda;
            color: #155724;
        }

        .badge-medium {
            background: #fff3cd;
            color: #856404;
        }

        .badge-high {
            background: #fff4e5;
            color: #e67700;
        }

        .badge-critical {
            background: #f8d7da;
            color: #721c24;
        }

        .event-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #e9ecef;
            color: #495057;
            font-family: 'Courier New', monospace;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            padding: 20px;
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

        .alert-list {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .alert-list h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: #dc3545;
        }

        .alert-item {
            padding: 12px;
            border-left: 3px solid #dc3545;
            background: #fff5f5;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .alert-item strong {
            color: #dc3545;
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

        .back-link:hover {
            opacity: 1;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>
                <i data-lucide="shield-alert" style="width: 32px; height: 32px;"></i>
                Security Event Logs
            </h1>
            <p>Monitor security events, login attempts, and suspicious activities</p>
            <a href="dashboard.php" class="back-link">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Events</h3>
                <div class="stat-value"><?php echo number_format($total_records); ?></div>
            </div>
            <?php foreach ($severity_stats as $stat): ?>
                <div class="stat-card">
                    <h3><?php echo $stat['severity']; ?> Severity</h3>
                    <div class="stat-value severity-<?php echo $stat['severity']; ?>">
                        <?php echo number_format($stat['count']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Failed Login Attempts Alert -->
        <?php if ($failed_logins): ?>
        <div class="alert-list">
            <h3>⚠️ Recent Failed Login Attempts (Last 24 Hours)</h3>
            <?php foreach ($failed_logins as $failed): ?>
                <div class="alert-item">
                    <strong><?php echo $failed['attempts']; ?> failed attempts</strong> from IP:
                    <code><?php echo htmlspecialchars($failed['ip_address']); ?></code>
                    <span class="text-muted">
                        (Last: <?php echo format_datetime($failed['last_attempt']); ?>)
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-section">
            <h3 style="margin-bottom: 0;">Filter & Search</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Severity</label>
                        <select name="severity" class="form-control">
                            <option value="">All Severities</option>
                            <option value="LOW" <?php echo $severity_filter === 'LOW' ? 'selected' : ''; ?>>Low</option>
                            <option value="MEDIUM" <?php echo $severity_filter === 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                            <option value="HIGH" <?php echo $severity_filter === 'HIGH' ? 'selected' : ''; ?>>High</option>
                            <option value="CRITICAL" <?php echo $severity_filter === 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Event Type</label>
                        <select name="event_type" class="form-control">
                            <option value="">All Event Types</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $event_type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Search (IP or Details)</label>
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
                            <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
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
                    <a href="security_logs.php" class="btn btn-secondary">
                        <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-table-container">
            <div class="table-header">
                <h2>Security Events</h2>
                <div style="color: #6c757d; font-size: 0.875rem;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
            </div>

            <?php if ($logs): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Event Type</th>
                            <th>Severity</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="timestamp">
                                    <?php echo format_datetime($log['created_at']); ?>
                                </td>
                                <td>
                                    <span class="event-badge">
                                        <?php echo htmlspecialchars($log['event_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="severity-badge badge-<?php echo strtolower($log['severity']); ?>">
                                        <?php echo $log['severity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                        <div class="text-muted"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                </td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">First</a>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Previous</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Next</a>
                        <a href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">Last</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-logs">
                    <i data-lucide="inbox" style="width: 64px; height: 64px;"></i>
                    <h3>No Security Events Found</h3>
                    <p>No events match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
