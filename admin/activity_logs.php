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

// Encoding leaderboard — counts rows the user actually created in the
// certificate tables (ground truth via created_by), for today / this week
// (Mon-Sun) / this month / all-time. Soft-deleted records are excluded;
// archived records still count. Sourcing from the certificate tables rather
// than activity_logs avoids gaps when a save endpoint forgets to call
// log_activity or falls back to admin when the session is missing.
$leaderboard_sql = "
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.role,
        SUM(CASE WHEN DATE(c.created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_count,
        SUM(CASE WHEN YEARWEEK(c.created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS week_count,
        SUM(CASE WHEN YEAR(c.created_at) = YEAR(CURDATE()) AND MONTH(c.created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS month_count,
        COUNT(*) AS total_count
    FROM (
        SELECT created_by, created_at FROM certificate_of_live_birth        WHERE status <> 'Deleted' AND created_by IS NOT NULL
        UNION ALL
        SELECT created_by, created_at FROM certificate_of_death             WHERE status <> 'Deleted' AND created_by IS NOT NULL
        UNION ALL
        SELECT created_by, created_at FROM certificate_of_marriage          WHERE status <> 'Deleted' AND created_by IS NOT NULL
        UNION ALL
        SELECT created_by, created_at FROM application_for_marriage_license WHERE status <> 'Deleted' AND created_by IS NOT NULL
    ) c
    INNER JOIN users u ON c.created_by = u.id
    GROUP BY u.id, u.full_name, u.username, u.role
    ORDER BY month_count DESC, total_count DESC
";
$leaderboard = $pdo->query($leaderboard_sql)->fetchAll();

// Totals row (sum across all users)
$totals = [
    'today' => 0, 'week' => 0, 'month' => 0, 'total' => 0,
];
foreach ($leaderboard as $row) {
    $totals['today'] += (int)$row['today_count'];
    $totals['week']  += (int)$row['week_count'];
    $totals['month'] += (int)$row['month_count'];
    $totals['total'] += (int)$row['total_count'];
}

// KPI: total events across the whole audit log (unfiltered)
$kpi_total_events = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$kpi_events_today = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$kpi_events_week  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();
$kpi_active_today = 0;
foreach ($leaderboard as $row) {
    if ((int)$row['today_count'] > 0) { $kpi_active_today++; }
}

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
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        :root {
            --c-primary: #3b82f6;
            --c-primary-hover: #2563eb;
            --c-primary-soft: #dbeafe;
            --c-primary-strong: #1d4ed8;
            --c-bg: #f8fafc;
            --c-surface: #ffffff;
            --c-border: #e5e7eb;
            --c-divider: #f1f5f9;
            --c-text: #0f172a;
            --c-text-muted: #64748b;
            --c-text-soft: #94a3b8;
            --c-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.06);
            --c-shadow-lg: 0 4px 12px rgba(15, 23, 42, 0.06);
            --radius-sm: 8px;
            --radius-md: 10px;
            --radius-lg: 12px;
            --mono: 'JetBrains Mono', 'SF Mono', Consolas, 'Courier New', monospace;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--c-bg);
            color: var(--c-text);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        a { text-decoration: none; color: inherit; }

        .page-container {
            padding: 24px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ===== Page header ===== */
        .page-header {
            background: var(--c-surface);
            padding: 20px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            border: 1px solid var(--c-border);
            box-shadow: var(--c-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .page-icon-tile {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--c-primary-soft);
            color: var(--c-primary-strong);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .page-icon-tile i { width: 22px; height: 22px; }

        .page-title-stack h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--c-text);
            margin: 0;
            line-height: 1.25;
        }

        .page-title-stack p {
            color: var(--c-text-muted);
            font-size: 0.825rem;
            margin: 2px 0 0;
        }

        /* ===== KPI strip ===== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--c-shadow);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .kpi-card:hover {
            transform: translateY(-1px);
            box-shadow: var(--c-shadow-lg);
        }

        .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .kpi-icon i { width: 20px; height: 20px; }

        .kpi-icon.blue   { background: #dbeafe; color: #1d4ed8; }
        .kpi-icon.green  { background: #dcfce7; color: #15803d; }
        .kpi-icon.amber  { background: #fef3c7; color: #b45309; }
        .kpi-icon.violet { background: #ede9fe; color: #6d28d9; }

        .kpi-text { display: flex; flex-direction: column; min-width: 0; }
        .kpi-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--c-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--c-text);
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }
        .kpi-sub {
            font-size: 0.72rem;
            color: var(--c-text-soft);
            margin-top: 1px;
        }

        /* ===== Card (shared) ===== */
        .card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--c-shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--c-divider);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--c-text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .card-title i { width: 18px; height: 18px; color: var(--c-primary); }

        .card-subtitle {
            font-size: 0.78rem;
            color: var(--c-text-muted);
            margin-top: 2px;
            font-weight: 400;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #047857;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .live-pill::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
            animation: live-pulse 1.8s ease-out infinite;
        }
        @keyframes live-pulse {
            0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55); }
            70%  { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* ===== Leaderboard table ===== */
        .table-wrapper { overflow-x: auto; }

        .leaderboard-table { width: 100%; border-collapse: collapse; }

        .leaderboard-table thead { background: #f8fafc; }

        .leaderboard-table th {
            padding: 10px 16px;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--c-text-muted);
            border-bottom: 1px solid var(--c-border);
            white-space: nowrap;
        }

        .leaderboard-table th small {
            display: block;
            font-size: 0.62rem;
            font-weight: 400;
            color: var(--c-text-soft);
            text-transform: none;
            letter-spacing: 0;
            margin-top: 1px;
        }

        .leaderboard-table th.num,
        .leaderboard-table td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .leaderboard-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--c-divider);
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .leaderboard-table tbody tr:last-child td { border-bottom: none; }
        .leaderboard-table tbody tr:hover { background: #f8fafc; }

        .leaderboard-table .rank {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--c-divider);
            color: var(--c-text-muted);
            font-weight: 700;
            font-size: 0.78rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
        }
        .leaderboard-table tbody tr:nth-child(1) .rank {
            background: linear-gradient(135deg, #fde68a, #f59e0b);
            color: #78350f;
        }
        .leaderboard-table tbody tr:nth-child(2) .rank {
            background: linear-gradient(135deg, #f1f5f9, #cbd5e1);
            color: #334155;
        }
        .leaderboard-table tbody tr:nth-child(3) .rank {
            background: linear-gradient(135deg, #fed7aa, #c2410c);
            color: #fff;
        }

        .lb-user-cell { display: flex; flex-direction: column; gap: 2px; }
        .lb-user-name {
            font-weight: 600;
            color: var(--c-text);
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .lb-user-handle {
            color: var(--c-text-soft);
            font-size: 0.75rem;
            font-family: var(--mono);
        }

        .num-active { color: #15803d; font-weight: 600; }
        .num-zero   { color: #cbd5e1; }
        .num-total  { color: var(--c-text); font-weight: 700; }

        .all-time-cell {
            position: relative;
            min-width: 110px;
        }
        .all-time-bar {
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: 6px;
            height: 3px;
            background: var(--c-divider);
            border-radius: 999px;
            overflow: hidden;
        }
        .all-time-bar > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #60a5fa, #3b82f6);
            border-radius: 999px;
        }

        .leaderboard-table tfoot { background: #f8fafc; }
        .leaderboard-table tfoot td {
            padding: 12px 16px;
            border-top: 1px solid var(--c-border);
            border-bottom: none;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ===== Filter bar ===== */
        .filter-bar {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--c-shadow);
            margin-bottom: 16px;
        }

        .filter-bar-form { padding: 14px 16px; }

        .filter-bar-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-wrap {
            flex: 1;
            min-width: 240px;
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-input-wrap i {
            position: absolute;
            left: 12px;
            width: 16px; height: 16px;
            color: var(--c-text-soft);
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--c-border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--c-text);
            background: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--c-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .filter-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border: 1px solid var(--c-border);
            background: #ffffff;
            color: var(--c-text);
            border-radius: var(--radius-sm);
            font-size: 0.825rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .filter-toggle:hover { background: #f8fafc; border-color: #cbd5e1; }
        .filter-toggle i { width: 16px; height: 16px; }
        .filter-toggle .chev { transition: transform 0.2s; }
        details[open] .filter-toggle .chev { transform: rotate(180deg); }

        .filter-toggle-badge {
            background: var(--c-primary);
            color: white;
            font-size: 0.68rem;
            padding: 1px 7px;
            border-radius: 999px;
            font-weight: 700;
            margin-left: 4px;
        }

        details.filter-collapse { display: contents; }
        details.filter-collapse > summary {
            list-style: none;
            cursor: pointer;
        }
        details.filter-collapse > summary::-webkit-details-marker { display: none; }

        .filter-panel {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--c-divider);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--c-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .form-control {
            padding: 9px 12px;
            border: 1px solid var(--c-border);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--c-text);
            background: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--c-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        /* ===== Buttons ===== */
        .btn {
            padding: 9px 16px;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            font-size: 0.825rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn i { width: 15px; height: 15px; }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--c-primary); color: #fff; }
        .btn-primary:hover { background: var(--c-primary-hover); }
        .btn-ghost {
            background: #fff;
            color: var(--c-text);
            border-color: var(--c-border);
        }
        .btn-ghost:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn:focus-visible {
            outline: 2px solid var(--c-primary);
            outline-offset: 2px;
        }

        /* ===== Active filter chips ===== */
        .active-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            align-items: center;
        }
        .active-chips .chips-label {
            font-size: 0.75rem;
            color: var(--c-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-right: 4px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 4px 4px 10px;
            background: var(--c-primary-soft);
            color: var(--c-primary-strong);
            font-size: 0.78rem;
            font-weight: 500;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
        }
        .chip strong { font-weight: 600; }
        .chip a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            color: var(--c-primary-strong);
            background: rgba(255,255,255,0.6);
            transition: background 0.15s;
        }
        .chip a:hover { background: #fff; }
        .chip a i { width: 12px; height: 12px; }

        .chip-clear-all {
            font-size: 0.78rem;
            color: var(--c-text-muted);
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--c-border);
            background: #fff;
            transition: background 0.15s, color 0.15s;
        }
        .chip-clear-all:hover { background: #f8fafc; color: var(--c-text); }

        /* ===== Logs table ===== */
        .logs-table { width: 100%; border-collapse: collapse; }
        .logs-table thead { background: #f8fafc; position: sticky; top: 0; z-index: 1; }
        .logs-table th {
            padding: 11px 16px;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--c-text-muted);
            border-bottom: 1px solid var(--c-border);
            white-space: nowrap;
        }
        .logs-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--c-divider);
            font-size: 0.85rem;
            vertical-align: top;
        }
        .logs-table tbody tr {
            border-left: 3px solid transparent;
            transition: background 0.15s;
        }
        .logs-table tbody tr:hover { background: #f8fafc; }
        .logs-table tbody tr.row-create      { border-left-color: #10b981; }
        .logs-table tbody tr.row-update      { border-left-color: #0ea5e9; }
        .logs-table tbody tr.row-soft-delete { border-left-color: #f59e0b; }
        .logs-table tbody tr.row-hard-delete { border-left-color: #ef4444; }
        .logs-table tbody tr.row-archive     { border-left-color: #fb923c; }
        .logs-table tbody tr.row-restore     { border-left-color: #6366f1; }
        .logs-table tbody tr.row-auth        { border-left-color: #3b82f6; }
        .logs-table tbody tr.row-other       { border-left-color: #94a3b8; }

        /* ===== Action badges ===== */
        .action-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .badge-create      { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .badge-update      { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .badge-soft-delete { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .badge-hard-delete { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .badge-archive     { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
        .badge-restore     { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
        .badge-auth        { background: #ecfeff; color: #155e75; border-color: #a5f3fc; }
        .badge-other       { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

        /* ===== Avatar + user cell ===== */
        .user-cell { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.78rem;
            flex-shrink: 0;
            box-shadow: inset 0 -1px 0 rgba(0,0,0,0.08);
        }
        .user-meta { display: flex; flex-direction: column; min-width: 0; }
        .user-name {
            font-weight: 600;
            color: var(--c-text);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .user-handle {
            font-size: 0.75rem;
            color: var(--c-text-soft);
            font-family: var(--mono);
        }

        /* ===== Role badge ===== */
        .role-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 600;
            background: var(--c-divider);
            color: var(--c-text-muted);
            letter-spacing: 0.3px;
            border: 1px solid transparent;
        }
        .role-Admin   { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .role-Encoder { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .role-Viewer  { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

        /* ===== Pagination ===== */
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            border-top: 1px solid var(--c-divider);
            background: #fcfcfd;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pagination-info {
            color: var(--c-text-muted);
            font-size: 0.8rem;
            font-variant-numeric: tabular-nums;
        }
        .pagination-info strong { color: var(--c-text); font-weight: 600; }

        .pagination {
            display: inline-flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .pagination a,
        .pagination span {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--c-border);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--c-text);
            font-size: 0.825rem;
            font-weight: 500;
            transition: all 0.15s;
        }
        .pagination a:hover {
            background: var(--c-primary-soft);
            color: var(--c-primary-strong);
            border-color: #bfdbfe;
        }
        .pagination .active {
            background: var(--c-primary);
            border-color: var(--c-primary);
            color: #fff;
        }
        .pagination .nav-icon i { width: 16px; height: 16px; }
        .pagination .ellipsis { border: none; background: transparent; color: var(--c-text-soft); }

        /* ===== Empty state ===== */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: var(--c-text-muted);
        }
        .empty-state .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--c-divider);
            color: var(--c-text-soft);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }
        .empty-state .empty-icon i { width: 28px; height: 28px; }
        .empty-state h3 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--c-text);
            margin-bottom: 6px;
        }
        .empty-state p { font-size: 0.85rem; margin-bottom: 16px; }

        /* ===== Misc ===== */
        .timestamp {
            white-space: nowrap;
            font-family: var(--mono);
            font-size: 0.78rem;
            color: var(--c-text);
        }
        .details-cell {
            max-width: 480px;
            word-break: break-word;
            color: var(--c-text);
            font-size: 0.83rem;
            line-height: 1.5;
        }
        code.ip-code {
            font-family: var(--mono);
            font-size: 0.78rem;
            color: var(--c-text);
            background: var(--c-divider);
            padding: 2px 7px;
            border-radius: 6px;
        }
        .text-muted { color: var(--c-text-muted); font-size: 0.85rem; }
        .text-soft  { color: var(--c-text-soft); }

        /* ===== Responsive ===== */
        @media (max-width: 1100px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 720px) {
            .page-container { padding: 16px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .kpi-grid { grid-template-columns: 1fr; }
            .filter-panel { grid-template-columns: 1fr; }
        }

        /* Focus rings */
        a:focus-visible,
        button:focus-visible,
        select:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--c-primary);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h4><i data-lucide="file-badge"></i> Civil Registry</h4>
            <button id="mobileSidebarToggle">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include '../includes/sidebar_nav.php'; ?>

    <!-- Top Navbar -->
    <?php include '../includes/top_navbar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="page-container">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <span class="page-icon-tile" aria-hidden="true">
                        <i data-lucide="history"></i>
                    </span>
                    <div class="page-title-stack">
                        <h1>Activity Logs</h1>
                        <p>Audit trail of create, edit, delete, archive, and restore actions across all records</p>
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn-ghost">
                    <i data-lucide="arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>

            <!-- KPI Strip -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-icon blue" aria-hidden="true"><i data-lucide="database"></i></span>
                    <div class="kpi-text">
                        <span class="kpi-label">Total Events</span>
                        <span class="kpi-value"><?php echo number_format($kpi_total_events); ?></span>
                        <span class="kpi-sub">All-time audit entries</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon green" aria-hidden="true"><i data-lucide="activity"></i></span>
                    <div class="kpi-text">
                        <span class="kpi-label">Events Today</span>
                        <span class="kpi-value"><?php echo number_format($kpi_events_today); ?></span>
                        <span class="kpi-sub"><?php echo date('M j, Y'); ?></span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon amber" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
                    <div class="kpi-text">
                        <span class="kpi-label">This Week</span>
                        <span class="kpi-value"><?php echo number_format($kpi_events_week); ?></span>
                        <span class="kpi-sub">Mon–Sun</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <span class="kpi-icon violet" aria-hidden="true"><i data-lucide="users-round"></i></span>
                    <div class="kpi-text">
                        <span class="kpi-label">Active Encoders Today</span>
                        <span class="kpi-value"><?php echo number_format($kpi_active_today); ?></span>
                        <span class="kpi-sub">Unique users with encodes</span>
                    </div>
                </div>
            </div>

            <!-- Encoding Leaderboard -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">
                            <i data-lucide="trophy"></i>
                            Encoding Productivity by User
                        </h2>
                        <span class="card-subtitle">Records encoded per user across all certificate types (soft-deleted excluded)</span>
                    </div>
                    <span class="live-pill">Live</span>
                </div>
                <?php if ($leaderboard):
                    $top_total = 0;
                    foreach ($leaderboard as $row) {
                        if ((int)$row['total_count'] > $top_total) { $top_total = (int)$row['total_count']; }
                    }
                ?>
                    <div class="table-wrapper">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th style="width: 56px;">#</th>
                                    <th>User</th>
                                    <th class="num">Today</th>
                                    <th class="num">This Week<small>(Mon–Sun)</small></th>
                                    <th class="num">This Month</th>
                                    <th class="num">All-Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $i => $row):
                                    $pct = $top_total > 0 ? min(100, round(((int)$row['total_count'] / $top_total) * 100)) : 0;
                                    $palette = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#6366f1'];
                                    $avatar_color = $palette[((int)$row['id']) % count($palette)];
                                    $initial = strtoupper(substr($row['full_name'] ?: $row['username'], 0, 1));
                                ?>
                                    <tr>
                                        <td><span class="rank"><?php echo $i + 1; ?></span></td>
                                        <td>
                                            <div class="user-cell">
                                                <span class="avatar" style="background: <?php echo $avatar_color; ?>;"><?php echo htmlspecialchars($initial); ?></span>
                                                <div class="user-meta">
                                                    <span class="user-name">
                                                        <?php echo htmlspecialchars($row['full_name'] ?: $row['username']); ?>
                                                        <?php if ($row['role']): ?>
                                                            <span class="role-badge role-<?php echo htmlspecialchars($row['role']); ?>">
                                                                <?php echo htmlspecialchars($row['role']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="user-handle">@<?php echo htmlspecialchars($row['username']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="num <?php echo $row['today_count'] > 0 ? 'num-active' : 'num-zero'; ?>">
                                            <?php echo number_format($row['today_count']); ?>
                                        </td>
                                        <td class="num <?php echo $row['week_count'] > 0 ? 'num-active' : 'num-zero'; ?>">
                                            <?php echo number_format($row['week_count']); ?>
                                        </td>
                                        <td class="num <?php echo $row['month_count'] > 0 ? 'num-active' : 'num-zero'; ?>">
                                            <?php echo number_format($row['month_count']); ?>
                                        </td>
                                        <td class="num num-total all-time-cell">
                                            <?php echo number_format($row['total_count']); ?>
                                            <span class="all-time-bar" aria-hidden="true">
                                                <span style="width: <?php echo $pct; ?>%;"></span>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td></td>
                                    <td>Total (all users)</td>
                                    <td class="num"><?php echo number_format($totals['today']); ?></td>
                                    <td class="num"><?php echo number_format($totals['week']); ?></td>
                                    <td class="num"><?php echo number_format($totals['month']); ?></td>
                                    <td class="num"><?php echo number_format($totals['total']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 32px 20px;">
                        <p class="text-muted">No encoding activity recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <?php
                $any_filter_active = $action_filter !== '' || $user_filter !== '' || $search_term !== '' || $date_from !== '' || $date_to !== '';
                $advanced_filter_count = ($action_filter !== '' ? 1 : 0)
                                       + ($user_filter !== '' ? 1 : 0)
                                       + ($date_from !== '' ? 1 : 0)
                                       + ($date_to !== '' ? 1 : 0);
            ?>
            <div class="filter-bar">
                <form method="GET" action="" class="filter-bar-form">
                    <details class="filter-collapse" <?php echo $advanced_filter_count > 0 ? 'open' : ''; ?>>
                        <div class="filter-bar-row">
                            <div class="search-input-wrap">
                                <i data-lucide="search"></i>
                                <input
                                    type="text"
                                    id="filter-search"
                                    name="search"
                                    class="search-input"
                                    placeholder="Search details or IP address…"
                                    value="<?php echo htmlspecialchars($search_term); ?>"
                                    aria-label="Search details or IP">
                            </div>

                            <summary class="filter-toggle" role="button" aria-label="Toggle advanced filters">
                                <i data-lucide="sliders-horizontal"></i>
                                Filters
                                <?php if ($advanced_filter_count > 0): ?>
                                    <span class="filter-toggle-badge"><?php echo $advanced_filter_count; ?></span>
                                <?php endif; ?>
                                <i data-lucide="chevron-down" class="chev"></i>
                            </summary>

                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="filter"></i>
                                Apply
                            </button>
                            <a href="activity_logs.php" class="btn btn-ghost">
                                <i data-lucide="x"></i>
                                Clear
                            </a>

                            <!-- Preserve per_page when filters panel is collapsed -->
                            <input type="hidden" name="per_page" value="<?php echo (int)$per_page; ?>" id="hidden-per-page">
                        </div>

                        <div class="filter-panel">
                            <div class="form-group">
                                <label for="filter-action">Action</label>
                                <select name="action" id="filter-action" class="form-control">
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
                                <label for="filter-user">User</label>
                                <select name="user_id" id="filter-user" class="form-control">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)$user_filter === (string)$u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filter-date-from">Date From</label>
                                <input type="date" id="filter-date-from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="form-group">
                                <label for="filter-date-to">Date To</label>
                                <input type="date" id="filter-date-to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="form-group">
                                <label for="filter-per-page">Per Page</label>
                                <select name="per_page" id="filter-per-page" class="form-control" onchange="document.getElementById('hidden-per-page').disabled = true;">
                                    <option value="25"  <?php echo $per_page === 25  ? 'selected' : ''; ?>>25</option>
                                    <option value="50"  <?php echo $per_page === 50  ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                                    <option value="250" <?php echo $per_page === 250 ? 'selected' : ''; ?>>250</option>
                                </select>
                            </div>
                        </div>
                    </details>
                </form>
            </div>

            <?php if ($any_filter_active):
                // Helper: build a URL with one filter param removed
                $build_remove_url = function($remove_key) {
                    $qs = $_GET;
                    unset($qs[$remove_key]);
                    unset($qs['page']);
                    return 'activity_logs.php' . ($qs ? '?' . http_build_query($qs) : '');
                };
                $user_label = '';
                if ($user_filter !== '') {
                    foreach ($users as $u) {
                        if ((string)$u['id'] === (string)$user_filter) {
                            $user_label = $u['full_name'] ?: $u['username'];
                            break;
                        }
                    }
                }
            ?>
                <div class="active-chips">
                    <span class="chips-label">Active filters:</span>
                    <?php if ($action_filter !== ''): ?>
                        <span class="chip">
                            <span><strong>Action:</strong> <?php echo htmlspecialchars($action_filter); ?></span>
                            <a href="<?php echo htmlspecialchars($build_remove_url('action')); ?>" aria-label="Remove action filter"><i data-lucide="x"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($user_filter !== ''): ?>
                        <span class="chip">
                            <span><strong>User:</strong> <?php echo htmlspecialchars($user_label ?: $user_filter); ?></span>
                            <a href="<?php echo htmlspecialchars($build_remove_url('user_id')); ?>" aria-label="Remove user filter"><i data-lucide="x"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($search_term !== ''): ?>
                        <span class="chip">
                            <span><strong>Search:</strong> <?php echo htmlspecialchars($search_term); ?></span>
                            <a href="<?php echo htmlspecialchars($build_remove_url('search')); ?>" aria-label="Remove search filter"><i data-lucide="x"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($date_from !== ''): ?>
                        <span class="chip">
                            <span><strong>From:</strong> <?php echo htmlspecialchars($date_from); ?></span>
                            <a href="<?php echo htmlspecialchars($build_remove_url('date_from')); ?>" aria-label="Remove date-from filter"><i data-lucide="x"></i></a>
                        </span>
                    <?php endif; ?>
                    <?php if ($date_to !== ''): ?>
                        <span class="chip">
                            <span><strong>To:</strong> <?php echo htmlspecialchars($date_to); ?></span>
                            <a href="<?php echo htmlspecialchars($build_remove_url('date_to')); ?>" aria-label="Remove date-to filter"><i data-lucide="x"></i></a>
                        </span>
                    <?php endif; ?>
                    <a href="activity_logs.php" class="chip-clear-all">Clear all</a>
                </div>
            <?php endif; ?>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i data-lucide="list"></i>
                        Activity Events
                        <span class="text-soft" style="font-weight:500; font-size:0.82rem;">(<?php echo number_format($total_records); ?> total)</span>
                    </h2>
                    <span class="card-subtitle" style="margin-top:0;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                </div>

                <?php if ($logs): ?>
                    <div class="table-wrapper">
                        <table class="logs-table">
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
                                <?php foreach ($logs as $log):
                                    $cat = activity_category($log['action']);
                                    $palette = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#6366f1'];
                                    $uid = (int)($log['user_id'] ?? 0);
                                    $avatar_color = $uid > 0 ? $palette[$uid % count($palette)] : '#94a3b8';
                                    $initial = strtoupper(substr($log['full_name'] ?: ($log['username'] ?: '?'), 0, 1));
                                ?>
                                    <tr class="row-<?php echo $cat; ?>">
                                        <td class="timestamp">
                                            <?php echo format_datetime($log['created_at']); ?>
                                        </td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <div class="user-cell">
                                                    <span class="avatar" style="background: <?php echo $avatar_color; ?>;"><?php echo htmlspecialchars($initial); ?></span>
                                                    <div class="user-meta">
                                                        <span class="user-name">
                                                            <?php echo htmlspecialchars($log['full_name'] ?: $log['username']); ?>
                                                            <?php if ($log['role']): ?>
                                                                <span class="role-badge role-<?php echo htmlspecialchars($log['role']); ?>">
                                                                    <?php echo htmlspecialchars($log['role']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="user-handle">@<?php echo htmlspecialchars($log['username']); ?></span>
                                                    </div>
                                                </div>
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
                                                <code class="ip-code"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                            <?php else: ?>
                                                <span class="text-soft">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php
                        $qs_base = $_GET;
                        unset($qs_base['page']);
                        $qs = http_build_query($qs_base);
                        $range_start = $offset + 1;
                        $range_end = min($offset + $per_page, $total_records);
                    ?>
                    <div class="pagination-bar">
                        <div class="pagination-info">
                            Showing <strong><?php echo number_format($range_start); ?></strong>–<strong><?php echo number_format($range_end); ?></strong>
                            of <strong><?php echo number_format($total_records); ?></strong> events
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1&<?php echo $qs; ?>" class="nav-icon" aria-label="First page"><i data-lucide="chevrons-left"></i></a>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo $qs; ?>" class="nav-icon" aria-label="Previous page"><i data-lucide="chevron-left"></i></a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            if ($start > 1) { echo '<span class="ellipsis">…</span>'; }
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="active" aria-current="page"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo $qs; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($end < $total_pages) { echo '<span class="ellipsis">…</span>'; } ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo $qs; ?>" class="nav-icon" aria-label="Next page"><i data-lucide="chevron-right"></i></a>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo $qs; ?>" class="nav-icon" aria-label="Last page"><i data-lucide="chevrons-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true"><i data-lucide="inbox"></i></span>
                        <h3>No Activity Logs Found</h3>
                        <p>No activities match your current filters.</p>
                        <?php if ($any_filter_active): ?>
                            <a href="activity_logs.php" class="btn btn-ghost">
                                <i data-lucide="x"></i>
                                Clear filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
