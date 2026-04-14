<?php
/**
 * Dashboard - Civil Registry Document Management System (CRDMS)
 * Main admin dashboard with analytics and statistics
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
requireAuth();

// Initialize statistics
$stats = [
    'total_births' => 0,
    'total_marriages' => 0,
    'total_deaths' => 0,
    'total_licenses' => 0,
    'this_month_births' => 0,
    'this_month_marriages' => 0,
    'this_month_deaths' => 0,
    'this_month_licenses' => 0,
    'last_month_births' => 0,
    'last_month_marriages' => 0,
    'last_month_deaths' => 0,
    'last_month_licenses' => 0,
    'birth_trend' => 0,
    'marriage_trend' => 0,
    'death_trend' => 0,
    'license_trend' => 0
];

$recent_activities = [];
$monthly_chart_data = [];
$certificate_distribution = [];

try {
    // PDF integrity issue count (last 30 days)
    $stmt = $pdo->query(
        "SELECT COUNT(*) as count FROM security_logs
          WHERE event_type = 'PDF_INTEGRITY_FAILURE'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stats['pdf_integrity_issues'] = (int)($stmt->fetch()['count'] ?? 0);

    // ── Single query for ALL totals, this month, and last month counts ──
    $combined_sql = "
        SELECT
            cert_type,
            COUNT(*) AS total,
            SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS this_month,
            SUM(CASE WHEN YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                      AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 ELSE 0 END) AS last_month
        FROM (
            SELECT 'birth' AS cert_type, created_at FROM certificate_of_live_birth WHERE status = 'Active'
            UNION ALL
            SELECT 'marriage', created_at FROM certificate_of_marriage WHERE status = 'Active'
            UNION ALL
            SELECT 'death', created_at FROM certificate_of_death WHERE status = 'Active'
            UNION ALL
            SELECT 'license', created_at FROM application_for_marriage_license WHERE status = 'Active'
        ) combined
        GROUP BY cert_type
    ";
    $rows = $pdo->query($combined_sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $type = $row['cert_type'];
        $map = ['birth' => 'births', 'marriage' => 'marriages', 'death' => 'deaths', 'license' => 'licenses'];
        $key = $map[$type] ?? $type;
        $stats["total_{$key}"] = (int)$row['total'];
        $stats["this_month_{$key}"] = (int)$row['this_month'];
        $stats["last_month_{$key}"] = (int)$row['last_month'];
    }

    // Calculate trends
    foreach (['births', 'marriages', 'deaths', 'licenses'] as $key) {
        $trend_key = str_replace('s', '', $key) . '_trend'; // birth_trend, marriage_trend, etc.
        if ($key === 'licenses') $trend_key = 'license_trend';
        $this_m = $stats["this_month_{$key}"] ?? 0;
        $last_m = $stats["last_month_{$key}"] ?? 0;
        $stats[$trend_key] = $last_m > 0
            ? round((($this_m - $last_m) / $last_m) * 100)
            : ($this_m > 0 ? 100 : 0);
    }

    // ── Single query for chart data (last 6 months) ──
    $six_months_ago = date('Y-m-01', strtotime('-5 months'));
    $chart_sql = "
        SELECT cert_type, DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM (
            SELECT 'birth' AS cert_type, created_at FROM certificate_of_live_birth WHERE status = 'Active' AND created_at >= :start
            UNION ALL
            SELECT 'marriage', created_at FROM certificate_of_marriage WHERE status = 'Active' AND created_at >= :start2
            UNION ALL
            SELECT 'death', created_at FROM certificate_of_death WHERE status = 'Active' AND created_at >= :start3
            UNION ALL
            SELECT 'license', created_at FROM application_for_marriage_license WHERE status = 'Active' AND created_at >= :start4
        ) combined
        GROUP BY cert_type, ym
        ORDER BY ym
    ";
    $chart_stmt = $pdo->prepare($chart_sql);
    $chart_stmt->execute([':start' => $six_months_ago, ':start2' => $six_months_ago, ':start3' => $six_months_ago, ':start4' => $six_months_ago]);
    $chart_rows = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build lookup: chart_lookup['2026-03']['birth'] = 5
    $chart_lookup = [];
    foreach ($chart_rows as $row) {
        $chart_lookup[$row['ym']][$row['cert_type']] = (int)$row['cnt'];
    }

    // Build monthly_chart_data array
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $month_label = date('M', strtotime("-$i months"));
        $monthly_chart_data[] = [
            'month' => $month_label,
            'births' => $chart_lookup[$ym]['birth'] ?? 0,
            'marriages' => $chart_lookup[$ym]['marriage'] ?? 0,
            'deaths' => $chart_lookup[$ym]['death'] ?? 0,
            'licenses' => $chart_lookup[$ym]['license'] ?? 0
        ];
    }

    // Get recent activities with user info (all certificate types combined)
    $recent_births = $pdo->query("
        SELECT 'birth' as type, b.id, b.registry_no, CONCAT(b.child_first_name, ' ', b.child_last_name) as name, b.created_at,
               u.full_name as created_by_name, u.role as created_by_role, 'CREATE' as action_type
        FROM certificate_of_live_birth b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.status = 'Active'
        ORDER BY b.created_at DESC
        LIMIT 5
    ")->fetchAll();

    $recent_marriages = $pdo->query("
        SELECT 'marriage' as type, m.id, m.registry_no, CONCAT(m.husband_first_name, ' ', m.husband_last_name, ' & ', m.wife_first_name, ' ', m.wife_last_name) as name, m.created_at,
               u.full_name as created_by_name, u.role as created_by_role, 'CREATE' as action_type
        FROM certificate_of_marriage m
        LEFT JOIN users u ON m.created_by = u.id
        WHERE m.status = 'Active'
        ORDER BY m.created_at DESC
        LIMIT 5
    ")->fetchAll();

    $recent_deaths = $pdo->query("
        SELECT 'death' as type, d.id, d.registry_no, CONCAT(d.deceased_first_name, ' ', d.deceased_last_name) as name, d.created_at,
               u.full_name as created_by_name, u.role as created_by_role, 'CREATE' as action_type
        FROM certificate_of_death d
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.status = 'Active'
        ORDER BY d.created_at DESC
        LIMIT 5
    ")->fetchAll();

    $recent_licenses = $pdo->query("
        SELECT 'license' as type, l.id, l.registry_no, CONCAT(l.groom_first_name, ' ', l.groom_last_name, ' & ', l.bride_first_name, ' ', l.bride_last_name) as name, l.created_at,
               u.full_name as created_by_name, u.role as created_by_role, 'CREATE' as action_type
        FROM application_for_marriage_license l
        LEFT JOIN users u ON l.created_by = u.id
        WHERE l.status = 'Active'
        ORDER BY l.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Merge and sort recent activities
    $recent_activities = array_merge($recent_births, $recent_marriages, $recent_deaths, $recent_licenses);
    usort($recent_activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_activities = array_slice($recent_activities, 0, 10);

    // Get security stats (last login, failed attempts)
    $security_stats = [
        'last_login' => null,
        'failed_login_count' => 0,
        'active_users' => 0
    ];

    // Get last login time for current user
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $security_stats['last_login'] = $stmt->fetch()['last_login'] ?? null;
    }

    // Count failed login attempts in last 24 hours (from activity_logs)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM activity_logs
        WHERE action = 'FAILED_LOGIN'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $security_stats['failed_login_count'] = $stmt->fetch()['count'] ?? 0;

    // Count active users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'");
    $security_stats['active_users'] = $stmt->fetch()['count'] ?? 0;

    // Certificate distribution
    $certificate_distribution = [
        ['type' => 'Birth Certificates', 'count' => $stats['total_births']],
        ['type' => 'Marriage Certificates', 'count' => $stats['total_marriages']],
        ['type' => 'Death Certificates', 'count' => $stats['total_deaths']],
        ['type' => 'Marriage Licenses', 'count' => $stats['total_licenses']]
    ];

    // Get today's calendar events
    $today_events = [];
    $stmt = $pdo->query("
        SELECT * FROM vw_today_events
        ORDER BY event_time ASC
        LIMIT 5
    ");
    $today_events = $stmt->fetchAll();

    // Get upcoming events (next 7 days)
    $upcoming_events = [];
    $stmt = $pdo->query("
        SELECT * FROM vw_upcoming_events
        WHERE days_until_event <= 7
        ORDER BY event_date ASC, event_time ASC
        LIMIT 10
    ");
    $upcoming_events = $stmt->fetchAll();

    // Get pinned notes
    $pinned_notes = [];
    $stmt = $pdo->query("
        SELECT * FROM vw_pinned_notes
        LIMIT 5
    ");
    $pinned_notes = $stmt->fetchAll();

    // Get recent notes (last 5)
    $recent_notes = [];
    $stmt = $pdo->query("
        SELECT n.*, u.full_name as created_by_name, u.role as created_by_role
        FROM system_notes n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE n.deleted_at IS NULL AND n.status = 'active'
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $recent_notes = $stmt->fetchAll();

    // Get calendar statistics
    $calendar_stats = [
        'total_events' => 0,
        'today_events' => count($today_events),
        'upcoming_events' => count($upcoming_events),
        'total_notes' => 0,
        'pinned_notes' => count($pinned_notes)
    ];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM calendar_events WHERE deleted_at IS NULL");
    $calendar_stats['total_events'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM system_notes WHERE deleted_at IS NULL AND status = 'active'");
    $calendar_stats['total_notes'] = $stmt->fetch()['count'] ?? 0;

    // Generate calendar grid for current month
    $current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

    // Get first day of month and number of days
    $first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
    $number_of_days = date('t', $first_day_of_month);
    $day_of_week = date('w', $first_day_of_month); // 0 (Sunday) to 6 (Saturday)

    // Get events for this month
    $month_start = date('Y-m-01', $first_day_of_month);
    $month_end = date('Y-m-t', $first_day_of_month);

    $stmt = $pdo->prepare("
        SELECT event_date, COUNT(*) as event_count
        FROM calendar_events
        WHERE event_date >= ? AND event_date <= ?
          AND deleted_at IS NULL
          AND status != 'cancelled'
        GROUP BY event_date
    ");
    $stmt->execute([$month_start, $month_end]);
    $events_by_date = [];
    while ($row = $stmt->fetch()) {
        $events_by_date[$row['event_date']] = $row['event_count'];
    }

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

$user_name = $_SESSION['full_name'] ?? 'Admin User';
$user_first_name = explode(' ', $user_name)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Civil Registry System</title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <script src="<?= asset_url('chartjs') ?>"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        :root {
            /* Institutional / Governmental Design System */
            --gov-primary: #0f2847;          /* Deep institutional navy */
            --gov-primary-600: #1e3a5f;      /* Navy hover/active */
            --gov-primary-50: #eef2f8;       /* Navy tint surface */
            --gov-accent: #c9a961;           /* Muted institutional gold */
            --gov-accent-600: #b08f48;
            --gov-accent-50: #faf6ec;

            --gov-blue: #1d4ed8;             /* Action blue */
            --gov-blue-50: #eff6ff;

            --gov-surface: #ffffff;
            --gov-surface-alt: #f8fafc;
            --gov-bg: #f4f6fa;               /* Formal cool page bg */
            --gov-border: #dde3ed;
            --gov-border-strong: #c5cfdc;

            --gov-text: #0f172a;
            --gov-text-muted: #475569;
            --gov-text-subtle: #64748b;

            --gov-success: #0f766e;
            --gov-success-50: #ecfdf5;
            --gov-warning: #b45309;
            --gov-warning-50: #fffbeb;
            --gov-danger: #991b1b;
            --gov-danger-50: #fef2f2;

            /* Stat accent family (muted institutional tones) */
            --stat-navy: #0f2847;
            --stat-teal: #0f766e;
            --stat-slate: #475569;
            --stat-maroon: #991b1b;
            --stat-amber: #b45309;
            --stat-indigo: #3730a3;
            --stat-gray: #64748b;
            --stat-gold: #a17d2b;

            /* Subtle shadow tuned to navy */
            --gov-shadow-sm: 0 1px 2px rgba(15, 40, 71, 0.04);
            --gov-shadow-md: 0 2px 6px rgba(15, 40, 71, 0.06), 0 1px 2px rgba(15, 40, 71, 0.04);
            --gov-shadow-lg: 0 8px 24px rgba(15, 40, 71, 0.08);

            /* Formal radii */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 10px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gov-bg);
            color: var(--gov-text);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            font-feature-settings: "tnum" 1, "ss01" 1;
        }

        .content {
            margin-left: 280px;
            padding: 88px 20px 20px 20px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .sidebar-collapsed .content {
            margin-left: 72px;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Search & Filter Bar — Formal */
        .search-filter-bar {
            background-color: var(--gov-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: var(--gov-shadow-sm);
        }

        .date-range-selector {
            display: inline-flex;
            align-items: center;
            margin-left: auto;
            gap: 10px;
        }

        .date-range-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--gov-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .date-range-group {
            display: inline-flex;
            border: 1px solid var(--gov-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--gov-surface);
        }

        .date-range-btn {
            padding: 8px 14px;
            border: none;
            border-right: 1px solid var(--gov-border);
            background: transparent;
            color: var(--gov-text-muted);
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .date-range-btn:last-child { border-right: none; }

        .date-range-btn:hover {
            background: var(--gov-primary-50);
            color: var(--gov-primary);
        }

        .date-range-btn.active {
            background: var(--gov-primary);
            color: #ffffff;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid var(--gov-border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: inherit;
            background-color: var(--gov-surface);
            color: var(--gov-text);
            transition: all 0.15s ease;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--gov-primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(15, 40, 71, 0.08);
        }

        .search-box input::placeholder {
            color: #94a3b8;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gov-text-subtle);
            font-size: 0.875rem;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--gov-border);
            background-color: var(--gov-surface);
            color: var(--gov-text-muted);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }

        .filter-chip:hover {
            background-color: var(--gov-primary-50);
            border-color: var(--gov-primary-600);
            color: var(--gov-primary);
        }

        .filter-chip.active {
            background-color: var(--gov-primary);
            border-color: var(--gov-primary);
            color: #ffffff;
            box-shadow: inset 0 -2px 0 var(--gov-accent);
        }

        .filter-chip.active i { color: var(--gov-accent); }

        /* Header — Official Document Banner */
        .dashboard-header {
            background-color: var(--gov-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            border-top: 3px solid var(--gov-accent);
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: var(--gov-shadow-sm);
            position: relative;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
        }

        .header-identity {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .header-seal {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--gov-accent-50);
            border: 2px solid var(--gov-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(201, 169, 97, 0.3);
        }

        .header-seal img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--gov-accent-600);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-eyebrow::after {
            content: '';
            flex: 0 0 28px;
            height: 1px;
            background: var(--gov-accent);
        }

        .header-title h1 {
            font-size: 1.625rem;
            font-weight: 700;
            color: var(--gov-primary);
            margin-bottom: 2px;
            letter-spacing: -0.015em;
            line-height: 1.2;
        }

        .header-title p {
            color: var(--gov-text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
        }

        .header-date {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gov-text-muted);
            letter-spacing: 0.01em;
            padding: 6px 12px;
            background: var(--gov-surface-alt);
            border: 1px solid var(--gov-border);
            border-radius: var(--radius-sm);
            font-variant-numeric: tabular-nums;
        }

        .header-date i {
            color: var(--gov-accent-600);
            margin-right: 6px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
            letter-spacing: 0.01em;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--gov-shadow-md);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background-color: var(--gov-primary);
            color: #ffffff;
            border-color: var(--gov-primary);
        }

        .btn-primary:hover {
            background-color: var(--gov-primary-600);
            border-color: var(--gov-accent);
        }

        .btn-success {
            background-color: var(--gov-surface);
            color: var(--gov-primary);
            border-color: var(--gov-border-strong);
        }

        .btn-success:hover {
            background-color: var(--gov-primary-50);
            border-color: var(--gov-primary);
            color: var(--gov-primary);
        }

        .btn-warning {
            background-color: var(--gov-surface);
            color: var(--gov-primary);
            border-color: var(--gov-border-strong);
        }

        .btn-warning:hover {
            background-color: var(--gov-primary-50);
            border-color: var(--gov-primary);
            color: var(--gov-primary);
        }

        .btn-info {
            background-color: var(--gov-surface);
            color: var(--gov-primary);
            border-color: var(--gov-border-strong);
        }

        .btn-info:hover {
            background-color: var(--gov-primary-50);
            border-color: var(--gov-primary);
            color: var(--gov-primary);
        }

        /* Statistics Grid — Institutional Style */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: var(--gov-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            padding: 20px 22px;
            position: relative;
            transition: all 0.15s ease;
            box-shadow: var(--gov-shadow-sm);
            overflow: hidden;
        }

        .stat-card:hover {
            border-color: var(--gov-border-strong);
            box-shadow: var(--gov-shadow-md);
            transform: translateY(-1px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.blue::before   { background: var(--stat-navy); }
        .stat-card.green::before  { background: var(--stat-teal); }
        .stat-card.purple::before { background: var(--stat-gold); }
        .stat-card.red::before    { background: var(--stat-maroon); }
        .stat-card.orange::before { background: var(--stat-amber); }
        .stat-card.indigo::before { background: var(--stat-indigo); }
        .stat-card.gray::before   { background: var(--stat-slate); }
        .stat-card.teal::before   { background: var(--stat-teal); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .stat-card.blue .stat-icon   { background-color: rgba(15, 40, 71, 0.08);  color: var(--stat-navy); }
        .stat-card.green .stat-icon  { background-color: rgba(15, 118, 110, 0.08); color: var(--stat-teal); }
        .stat-card.purple .stat-icon { background-color: rgba(161, 125, 43, 0.1);  color: var(--stat-gold); }
        .stat-card.red .stat-icon    { background-color: rgba(153, 27, 27, 0.08);  color: var(--stat-maroon); }
        .stat-card.orange .stat-icon { background-color: rgba(180, 83, 9, 0.08);   color: var(--stat-amber); }
        .stat-card.indigo .stat-icon { background-color: rgba(55, 48, 163, 0.08);  color: var(--stat-indigo); }
        .stat-card.gray .stat-icon   { background-color: rgba(71, 85, 105, 0.08);  color: var(--stat-slate); }
        .stat-card.teal .stat-icon   { background-color: rgba(15, 118, 110, 0.08); color: var(--stat-teal); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gov-primary);
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .stat-label {
            color: var(--gov-text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .stat-label-info {
            width: 14px;
            height: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--gov-primary-50);
            color: var(--gov-primary);
            border-radius: 50%;
            font-size: 9px;
            cursor: help;
            position: relative;
        }

        .stat-label-info:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            background-color: var(--gov-primary);
            color: #ffffff;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: var(--gov-shadow-lg);
            text-transform: none;
            letter-spacing: 0;
        }

        .stat-label-info:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: var(--gov-primary);
        }

        .stat-empty-state {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 6px;
            font-style: italic;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 10px;
            padding: 3px 8px;
            border-radius: var(--radius-xs);
            background-color: var(--gov-surface-alt);
            border: 1px solid var(--gov-border);
            font-variant-numeric: tabular-nums;
        }

        .stat-trend.up {
            color: var(--gov-success);
            background-color: var(--gov-success-50);
            border-color: rgba(15, 118, 110, 0.2);
        }

        .stat-trend.down {
            color: var(--gov-danger);
            background-color: var(--gov-danger-50);
            border-color: rgba(153, 27, 27, 0.2);
        }

        .stat-trend.neutral {
            color: var(--gov-text-subtle);
            background-color: var(--gov-surface-alt);
        }

        /* Charts Section — Formal Style */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .chart-card {
            background-color: var(--gov-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            padding: 24px;
            box-shadow: var(--gov-shadow-sm);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .chart-card:hover {
            box-shadow: var(--gov-shadow-md);
            border-color: var(--gov-border-strong);
        }

        .chart-header {
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--gov-border);
            position: relative;
        }

        .chart-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 48px;
            height: 2px;
            background: var(--gov-accent);
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gov-primary);
            margin-bottom: 4px;
            letter-spacing: -0.005em;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .chart-subtitle {
            color: var(--gov-text-subtle);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .chart-container {
            position: relative;
            height: 320px;
        }

        /* Quick Actions Widget */
        .quick-actions-fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            z-index: 1000;
        }

        .fab-main {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--gov-primary);
            color: #ffffff;
            border: 2px solid var(--gov-accent);
            box-shadow: 0 6px 20px rgba(15, 40, 71, 0.35);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.2s ease;
        }

        .fab-main:hover {
            background-color: var(--gov-primary-600);
            transform: scale(1.06);
            box-shadow: 0 10px 28px rgba(15, 40, 71, 0.45), 0 0 0 4px rgba(201, 169, 97, 0.2);
        }

        .fab-main:active {
            transform: scale(0.95);
        }

        .fab-main.active {
            transform: rotate(45deg);
            background-color: var(--gov-danger);
            border-color: var(--gov-danger);
            box-shadow: 0 6px 20px rgba(153, 27, 27, 0.4);
        }

        .fab-main.active:hover {
            background-color: #7f1d1d;
            transform: rotate(45deg) scale(1.06);
            box-shadow: 0 8px 24px rgba(153, 27, 27, 0.5);
        }

        .fab-menu {
            position: absolute;
            bottom: 70px;
            right: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fab-menu.active {
            opacity: 1;
            pointer-events: all;
        }

        .fab-action {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: flex-end;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fab-menu.active .fab-action {
            transform: translateY(0);
            opacity: 1;
        }

        .fab-menu.active .fab-action:nth-child(1) { transition-delay: 0.05s; }
        .fab-menu.active .fab-action:nth-child(2) { transition-delay: 0.1s; }
        .fab-menu.active .fab-action:nth-child(3) { transition-delay: 0.15s; }
        .fab-menu.active .fab-action:nth-child(4) { transition-delay: 0.2s; }
        .fab-menu.active .fab-action:nth-child(5) { transition-delay: 0.25s; }

        .fab-label {
            background-color: var(--gov-primary);
            color: #ffffff;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            box-shadow: var(--gov-shadow-lg);
            white-space: nowrap;
            letter-spacing: 0;
            border: 1px solid var(--gov-accent);
        }

        .fab-button {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            background-color: var(--gov-surface);
            box-shadow: var(--gov-shadow-md);
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .fab-button:hover {
            transform: scale(1.05);
            border-color: var(--gov-accent);
            box-shadow: 0 6px 16px rgba(15, 40, 71, 0.15);
        }

        .fab-button:active {
            transform: scale(0.95);
        }

        .fab-button.primary   { color: var(--stat-navy); }
        .fab-button.success   { color: var(--stat-teal); }
        .fab-button.warning   { color: var(--stat-maroon); }
        .fab-button.info      { color: var(--stat-indigo); }
        .fab-button.secondary { color: var(--stat-slate); }

        /* Recent Activity — Table-like Formal Rows */
        .activity-section {
            background-color: var(--gov-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--gov-border);
            padding: 24px;
            box-shadow: var(--gov-shadow-sm);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .activity-section:hover {
            box-shadow: var(--gov-shadow-md);
            border-color: var(--gov-border-strong);
        }

        .activity-header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gov-border);
            position: relative;
        }

        .activity-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 48px;
            height: 2px;
            background: var(--gov-accent);
        }

        .activity-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--gov-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .activity-title i {
            color: var(--gov-accent-600);
            font-size: 0.9375rem;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 4px;
            border-bottom: 1px solid var(--gov-border);
            transition: background-color 0.15s ease;
            cursor: pointer;
        }

        .activity-item:hover {
            background-color: var(--gov-surface-alt);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-user-info {
            font-size: 0.75rem;
            color: var(--gov-text-subtle);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-action-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: var(--gov-success-50);
            color: var(--gov-success);
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 1px solid rgba(15, 118, 110, 0.15);
        }

        .activity-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background: var(--gov-primary-50);
            color: var(--gov-primary);
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid rgba(15, 40, 71, 0.1);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.9375rem;
            border: 1px solid var(--gov-border);
        }

        .activity-icon.birth {
            background-color: rgba(15, 40, 71, 0.06);
            color: var(--stat-navy);
            border-color: rgba(15, 40, 71, 0.15);
        }

        .activity-icon.marriage {
            background-color: rgba(161, 125, 43, 0.08);
            color: var(--stat-gold);
            border-color: rgba(161, 125, 43, 0.2);
        }

        .activity-icon.death {
            background-color: rgba(71, 85, 105, 0.08);
            color: var(--stat-slate);
            border-color: rgba(71, 85, 105, 0.2);
        }

        .activity-icon.license {
            background-color: rgba(55, 48, 163, 0.06);
            color: var(--stat-indigo);
            border-color: rgba(55, 48, 163, 0.15);
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-name {
            font-weight: 600;
            color: var(--gov-text);
            margin-bottom: 3px;
            font-size: 0.9375rem;
            letter-spacing: -0.005em;
        }

        .activity-meta {
            font-size: 0.8125rem;
            color: var(--gov-text-muted);
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--gov-text-muted);
            white-space: nowrap;
            font-weight: 600;
            padding: 4px 10px;
            background-color: var(--gov-surface-alt);
            border-radius: var(--radius-xs);
            border: 1px solid var(--gov-border);
            font-variant-numeric: tabular-nums;
        }

        /* Security Status Card — Institutional */
        .security-status-card {
            background: var(--gov-surface);
            border-radius: var(--radius-md);
            padding: 20px 22px;
            border: 1px solid var(--gov-border);
            box-shadow: var(--gov-shadow-sm);
            margin-bottom: 24px;
        }

        .security-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gov-border);
            position: relative;
        }

        .security-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 48px;
            height: 2px;
            background: var(--gov-accent);
        }

        .security-header i {
            font-size: 0.9375rem;
            color: var(--gov-accent-600);
        }

        .security-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--gov-primary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background-color: var(--gov-surface-alt);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gov-border);
            transition: all 0.15s ease;
        }

        .security-item:hover {
            border-color: var(--gov-border-strong);
            background-color: var(--gov-primary-50);
        }

        .security-item.warning-border {
            border-color: rgba(180, 83, 9, 0.3);
            background-color: var(--gov-warning-50);
        }

        .security-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
            border: 1px solid var(--gov-border);
        }

        .security-icon.success {
            background-color: var(--gov-success-50);
            color: var(--gov-success);
            border-color: rgba(15, 118, 110, 0.2);
        }

        .security-icon.info {
            background-color: var(--gov-primary-50);
            color: var(--gov-primary);
            border-color: rgba(15, 40, 71, 0.15);
        }

        .security-icon.warning {
            background-color: var(--gov-warning-50);
            color: var(--gov-warning);
            border-color: rgba(180, 83, 9, 0.2);
        }

        .security-content {
            flex: 1;
            min-width: 0;
        }

        .security-label {
            font-size: 0.6875rem;
            color: var(--gov-text-subtle);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 3px;
        }

        .security-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--gov-primary);
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .security-value.text-warning {
            color: var(--gov-warning);
        }

        /* Calendar & Notes Section — Institutional */
        .calendar-notes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .calendar-widget {
            background: var(--gov-surface);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            border: 1px solid var(--gov-border);
            box-shadow: var(--gov-shadow-sm);
        }

        .notes-widget {
            background: var(--gov-surface);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            border: 1px solid var(--gov-border);
            box-shadow: var(--gov-shadow-sm);
            color: var(--gov-text);
        }

        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gov-border);
            position: relative;
        }

        .widget-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 48px;
            height: 2px;
            background: var(--gov-accent);
        }

        .widget-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--gov-primary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .widget-title i {
            color: var(--gov-accent-600);
            font-size: 0.9375rem;
        }

        .widget-action-btn {
            padding: 6px 12px;
            background: var(--gov-primary);
            color: #ffffff;
            border: 1px solid var(--gov-primary);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            letter-spacing: 0.02em;
        }

        .widget-action-btn:hover {
            background: var(--gov-primary-600);
            border-color: var(--gov-accent);
            box-shadow: var(--gov-shadow-md);
        }

        .widget-action-btn i {
            font-size: 0.75rem;
        }

        /* Calendar Events List */
        .events-list {
            list-style: none;
            max-height: 400px;
            overflow-y: auto;
        }

        .event-item {
            display: flex;
            align-items: start;
            gap: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
            background: var(--gov-surface-alt);
            border: 1px solid var(--gov-border);
            border-left: 3px solid var(--gov-primary);
            border-radius: var(--radius-sm);
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .event-item:hover {
            background: var(--gov-primary-50);
            border-color: var(--gov-border-strong);
            border-left-color: var(--gov-accent);
        }

        .event-item.priority-high {
            border-left-color: var(--gov-danger);
        }

        .event-item.priority-urgent {
            border-left-color: var(--gov-danger);
            background: var(--gov-danger-50);
        }

        .event-item.priority-medium {
            border-left-color: var(--gov-warning);
        }

        .event-item.priority-low {
            border-left-color: var(--gov-text-subtle);
        }

        .event-date-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50px;
            min-width: 50px;
            height: 50px;
            background: var(--gov-surface);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gov-border);
            flex-shrink: 0;
        }

        .event-month {
            font-size: 0.625rem;
            font-weight: 700;
            color: var(--gov-accent-600);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .event-day {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gov-primary);
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .event-content {
            flex: 1;
            min-width: 0;
        }

        .event-title {
            font-weight: 600;
            color: var(--gov-text);
            margin-bottom: 4px;
            font-size: 0.9375rem;
        }

        .event-meta {
            font-size: 0.75rem;
            color: var(--gov-text-subtle);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .event-type-badge {
            padding: 2px 8px;
            background: var(--gov-primary-50);
            color: var(--gov-primary);
            border-radius: var(--radius-xs);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 1px solid rgba(15, 40, 71, 0.1);
        }

        .event-time {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Calendar Month Navigation */
        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: var(--gov-surface-alt);
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            border: 1px solid var(--gov-border);
        }

        .calendar-month-year {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--gov-primary);
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .calendar-nav-btn {
            background: var(--gov-surface);
            border: 1px solid var(--gov-border);
            color: var(--gov-primary);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: var(--radius-xs);
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .calendar-nav-btn:hover {
            background: var(--gov-primary);
            border-color: var(--gov-primary);
            color: #ffffff;
        }

        /* Calendar Wrapper */
        .calendar-wrapper {
            padding: 0;
        }

        /* Calendar Day Headers */
        .calendar-day-headers {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 8px;
            padding: 0 12px;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 0.6875rem;
            font-weight: 700;
            color: var(--gov-text-subtle);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 4px 0;
        }

        /* Calendar Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            padding: 12px;
            background: transparent;
        }

        .calendar-day-cell {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 0.875rem;
            border-radius: var(--radius-xs);
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--gov-surface);
            border: 1px solid var(--gov-border);
            position: relative;
            font-weight: 600;
            color: var(--gov-text);
            min-height: 46px;
            width: 100%;
            max-width: 50px;
            margin: 0 auto;
            font-variant-numeric: tabular-nums;
        }

        .calendar-day-cell:hover:not(.empty):not(.today) {
            background: var(--gov-primary-50);
            border-color: var(--gov-primary);
            color: var(--gov-primary);
        }

        .calendar-day-cell.empty {
            background: transparent;
            border: none;
            cursor: default;
        }

        .calendar-day-cell.today {
            background: var(--gov-surface);
            color: var(--gov-primary);
            border: 2px solid var(--gov-accent);
            font-weight: 800;
            box-shadow: 0 0 0 2px rgba(201, 169, 97, 0.15);
        }

        .calendar-day-cell.today:hover {
            background: var(--gov-accent-50);
        }

        .calendar-day-cell.has-event {
            background: var(--gov-primary-50);
            border-color: var(--gov-primary);
            color: var(--gov-primary);
        }

        .calendar-day-cell.has-event:hover {
            background: var(--gov-primary);
            color: #ffffff;
        }

        .calendar-day-cell.today.has-event {
            background: var(--gov-accent-50);
            border-color: var(--gov-accent);
        }

        .calendar-event-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--gov-primary);
            color: #ffffff;
            font-size: 0.625rem;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 1px 3px rgba(15, 40, 71, 0.3);
            border: 2px solid var(--gov-surface);
            font-variant-numeric: tabular-nums;
        }

        .calendar-day-cell.today .calendar-event-count {
            background: var(--gov-accent);
            color: var(--gov-primary);
            border-color: var(--gov-surface);
        }

        .day-number {
            position: relative;
            z-index: 1;
        }

        .event-indicator {
            position: absolute;
            bottom: 4px;
            width: 4px;
            height: 4px;
            background: var(--gov-accent);
            border-radius: 50%;
        }

        /* Notes List */
        .notes-list {
            list-style: none;
            max-height: 400px;
            overflow-y: auto;
        }

        /* Notes List */
        .note-item {
            padding: 12px 14px;
            margin-bottom: 8px;
            background: var(--gov-surface-alt);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gov-border);
            transition: all 0.15s ease;
            cursor: pointer;
            position: relative;
        }

        .note-item:hover {
            background: var(--gov-primary-50);
            border-color: var(--gov-border-strong);
        }

        .note-item.pinned {
            background: var(--gov-accent-50);
            border-color: var(--gov-accent);
            border-left: 3px solid var(--gov-accent);
        }

        .note-item.pinned:hover {
            background: #f5ecd3;
        }

        .note-item.pinned::before {
            content: '\f08d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 10px;
            color: var(--gov-accent-600);
            font-size: 0.75rem;
        }

        .note-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .note-title {
            font-weight: 600;
            color: var(--gov-primary);
            font-size: 0.875rem;
            flex: 1;
            padding-right: 20px;
        }

        .note-content-preview {
            font-size: 0.75rem;
            color: var(--gov-text-muted);
            line-height: 1.4;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .note-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.6875rem;
            color: var(--gov-text-subtle);
            flex-wrap: wrap;
        }

        .note-type-badge {
            padding: 2px 8px;
            background: var(--gov-primary-50);
            color: var(--gov-primary);
            border-radius: var(--radius-xs);
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border: 1px solid rgba(15, 40, 71, 0.1);
        }

        .note-author {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 0.9375rem;
            margin-bottom: 16px;
        }

        /* Chart Insights — Formal Callout */
        .chart-insight {
            background-color: var(--gov-accent-50);
            border-left: 3px solid var(--gov-accent);
            padding: 12px 16px;
            border-radius: var(--radius-xs);
            margin-bottom: 16px;
            border-top: 1px solid rgba(201, 169, 97, 0.3);
            border-right: 1px solid rgba(201, 169, 97, 0.3);
            border-bottom: 1px solid rgba(201, 169, 97, 0.3);
        }

        .chart-insight-text {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.8125rem;
            color: var(--gov-text);
            font-weight: 500;
            line-height: 1.55;
        }

        .chart-insight-text strong {
            color: var(--gov-primary);
            font-weight: 700;
        }

        .chart-insight-text i {
            color: var(--gov-accent-600);
            font-size: 0.9375rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9998;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-container {
            background: var(--gov-surface);
            border-radius: var(--radius-md);
            border-top: 3px solid var(--gov-accent);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(15, 40, 71, 0.25);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--gov-border);
            background: var(--gov-surface);
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gov-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .modal-title i {
            color: var(--gov-accent-600);
            font-size: 1.125rem;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 1.125rem;
        }

        .modal-close:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }

        .modal-body {
            padding: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
            font-size: 0.9375rem;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-family: inherit;
            background: #ffffff;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            background: #f8fafc;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 2px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-radius: 0 0 16px 16px;
        }

        .modal-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modal-btn-cancel {
            background: #f8fafc;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
        }

        .modal-btn-cancel:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            color: #475569;
        }

        .modal-btn-primary {
            background: var(--gov-primary);
            color: #ffffff;
            border: 1px solid var(--gov-primary);
        }

        .modal-btn-primary:hover {
            background: var(--gov-primary-600);
            border-color: var(--gov-accent);
            box-shadow: var(--gov-shadow-md);
            transform: translateY(-1px);
        }

        .modal-btn-danger {
            background: var(--gov-danger);
            color: #ffffff;
            border: 1px solid var(--gov-danger);
        }

        .modal-btn-danger:hover {
            background: #7f1d1d;
            box-shadow: var(--gov-shadow-md);
            transform: translateY(-1px);
        }

        /* Wide modal for All Events / All Notes */
        .modal-container-wide {
            max-width: 800px;
        }

        .all-events-list, .all-notes-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 60vh;
            overflow-y: auto;
        }

        .all-event-item, .all-note-item {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s ease;
        }

        .all-event-item:hover, .all-note-item:hover {
            background: #f8fafc;
        }

        .all-event-item:last-child, .all-note-item:last-child {
            border-bottom: none;
        }

        .all-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .all-item-title {
            font-weight: 600;
            font-size: 0.9375rem;
            color: #0f172a;
        }

        .all-item-actions {
            display: flex;
            gap: 6px;
        }

        .all-item-action-btn {
            padding: 4px 10px;
            border-radius: 6px;
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .all-item-action-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background: #eff6ff;
        }

        .all-item-action-btn.delete:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fef2f2;
        }

        .all-item-action-btn.pin {
            color: #f59e0b;
            border-color: #fcd34d;
        }

        .all-item-action-btn.pin:hover {
            background: #fffbeb;
        }

        .all-item-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.8125rem;
            color: #64748b;
        }

        .all-item-meta i {
            font-size: 0.75rem;
        }

        .all-item-description {
            margin-top: 6px;
            font-size: 0.8125rem;
            color: #64748b;
            line-height: 1.5;
        }

        .all-note-content-preview {
            margin-top: 6px;
            font-size: 0.8125rem;
            color: #475569;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .all-item-pinned {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
        }

        .modal-filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .modal-filter-bar select {
            padding: 6px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.8125rem;
            color: #334155;
            background: #ffffff;
        }

        .modal-filter-bar select:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .modal-empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #94a3b8;
        }

        .modal-empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .modal-empty-state p {
            font-size: 0.9375rem;
        }

        .modal-loading {
            text-align: center;
            padding: 48px 20px;
        }

        .modal-loading i {
            font-size: 2rem;
            color: #3b82f6;
        }

        .modal-loading p {
            margin-top: 16px;
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Event List in View Modal */
        .event-list {
            list-style: none;
            max-height: 450px;
            overflow-y: auto;
            padding: 4px;
        }

        .event-list-item {
            padding: 20px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 14px;
            transition: all 0.25s ease;
        }

        .event-list-item:hover {
            background: #f0f9ff;
            border-color: #0ea5e9;
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.12);
            transform: translateY(-2px);
        }

        .event-list-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
            gap: 12px;
        }

        .event-list-item-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.0625rem;
            flex: 1;
            line-height: 1.3;
        }

        .event-list-item-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .event-action-btn {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.8125rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .event-action-btn.edit {
            background: #0ea5e9;
            color: #ffffff;
        }

        .event-action-btn.edit:hover {
            background: #0284c7;
            box-shadow: 0 3px 10px rgba(14, 165, 233, 0.3);
            transform: translateY(-1px);
        }

        .event-action-btn.delete {
            background: #ef4444;
            color: #ffffff;
        }

        .event-action-btn.delete:hover {
            background: #dc2626;
            box-shadow: 0 3px 10px rgba(239, 68, 68, 0.3);
            transform: translateY(-1px);
        }

        .event-list-item-meta {
            display: flex;
            gap: 12px;
            font-size: 0.8125rem;
            color: #64748b;
            margin-bottom: 6px;
        }

        .event-list-item-meta i {
            width: 14px;
        }

        .event-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 6px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .event-priority-badge.low {
            background: #f1f5f9;
            color: #475569;
        }

        .event-priority-badge.medium {
            background: #fef3c7;
            color: #854d0e;
        }

        .event-priority-badge.high {
            background: #fed7aa;
            color: #9a3412;
        }

        .event-priority-badge.urgent {
            background: #fecaca;
            color: #991b1b;
        }

        .event-list-item-description {
            font-size: 0.875rem;
            color: #475569;
            line-height: 1.5;
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0;
            }

            .dashboard-header {
                padding: 18px 20px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .header-seal {
                width: 48px;
                height: 48px;
            }

            .header-title h1 {
                font-size: 1.375rem;
            }

            .calendar-notes-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 18px;
            }

            .chart-container {
                height: 250px;
            }

            .chart-card {
                padding: 18px;
            }

            .activity-section {
                padding: 18px;
            }

            .quick-actions-fab {
                bottom: 20px;
                right: 20px;
            }

            .fab-main {
                width: 52px;
                height: 52px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="dashboard-container">
            <!-- Header — Official Document Banner -->
            <div class="dashboard-header">
                <div class="header-content">
                    <div class="header-identity">
                        <div class="header-seal" aria-hidden="true">
                            <img src="../assets/img/LOGO1.png" alt="Civil Registry Office Seal">
                        </div>
                        <div class="header-title">
                            <div class="header-eyebrow">Civil Registry Office</div>
                            <h1>Administrative Dashboard</h1>
                            <p>Welcome, <?php echo htmlspecialchars($user_first_name); ?>. Official overview of registry operations.</p>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="header-date">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i>
                            <?php echo date('l, d F Y'); ?>
                        </div>
                        <div class="header-actions">
                            <a href="../public/certificate_of_live_birth.php" class="btn btn-primary">
                                <i class="fas fa-file-lines"></i> New Birth
                            </a>
                            <a href="../public/certificate_of_marriage.php" class="btn btn-success">
                                <i class="fas fa-file-signature"></i> New Marriage
                            </a>
                            <a href="../public/certificate_of_death.php" class="btn btn-warning">
                                <i class="fas fa-file-lines"></i> New Death
                            </a>
                            <a href="../public/application_for_marriage_license.php" class="btn btn-info">
                                <i class="fas fa-stamp"></i> New License
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Search & Filter Bar -->
        <div class="search-filter-bar" role="search" aria-label="Dashboard search and filters">
            <div class="search-box">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" id="dashboardSearch" placeholder="Search records by name, registry number, or date..." aria-label="Search records">
            </div>
            <div class="filter-group" role="group" aria-label="Certificate type filters">
                <button class="filter-chip active" data-filter="all" aria-pressed="true">
                    <i class="fas fa-folder-open" aria-hidden="true"></i> All
                </button>
                <button class="filter-chip" data-filter="birth" aria-pressed="false">
                    <i class="fas fa-file-lines" aria-hidden="true"></i> Birth
                </button>
                <button class="filter-chip" data-filter="marriage" aria-pressed="false">
                    <i class="fas fa-file-signature" aria-hidden="true"></i> Marriage
                </button>
                <button class="filter-chip" data-filter="death" aria-pressed="false">
                    <i class="fas fa-file-lines" aria-hidden="true"></i> Death
                </button>
                <button class="filter-chip" data-filter="license" aria-pressed="false">
                    <i class="fas fa-stamp" aria-hidden="true"></i> License
                </button>
            </div>
            <div class="date-range-selector" role="group" aria-label="Date range filters">
                <span class="date-range-label">Period</span>
                <div class="date-range-group">
                    <button class="date-range-btn active" data-range="monthly" aria-pressed="true">Monthly</button>
                    <button class="date-range-btn" data-range="quarterly" aria-pressed="false">Quarterly</button>
                    <button class="date-range-btn" data-range="yearly" aria-pressed="false">Yearly</button>
                </div>
            </div>
        </div>

        <!-- Security & System Status -->
        <div class="security-status-card" role="region" aria-label="Security and system status">
            <div class="security-header">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <h2 class="security-title">Security &amp; System Status</h2>
            </div>
            <div class="security-grid">
                <div class="security-item">
                    <div class="security-icon success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="security-content">
                        <div class="security-label">Active Users</div>
                        <div class="security-value"><?php echo $security_stats['active_users']; ?></div>
                    </div>
                </div>
                <div class="security-item">
                    <div class="security-icon info">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="security-content">
                        <div class="security-label">Last Login</div>
                        <div class="security-value" style="font-size: 0.875rem;">
                            <?php
                            if ($security_stats['last_login']) {
                                $last_login_time = strtotime($security_stats['last_login']);
                                $time_diff = time() - $last_login_time;
                                if ($time_diff < 3600) {
                                    echo floor($time_diff / 60) . ' mins ago';
                                } elseif ($time_diff < 86400) {
                                    echo floor($time_diff / 3600) . ' hours ago';
                                } else {
                                    echo date('M d, Y h:i A', $last_login_time);
                                }
                            } else {
                                echo 'First Login';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="security-item <?php echo $security_stats['failed_login_count'] > 5 ? 'warning-border' : ''; ?>">
                    <div class="security-icon <?php echo $security_stats['failed_login_count'] > 5 ? 'warning' : 'success'; ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="security-content">
                        <div class="security-label">Failed Logins (24h)</div>
                        <div class="security-value <?php echo $security_stats['failed_login_count'] > 5 ? 'text-warning' : ''; ?>">
                            <?php echo $security_stats['failed_login_count']; ?>
                        </div>
                    </div>
                </div>
                <div class="security-item">
                    <div class="security-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="security-content">
                        <div class="security-label">System Health</div>
                        <div class="security-value" style="font-size: 0.875rem; color: var(--gov-success);">Operational</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar & Notes Grid -->
        <div class="calendar-notes-grid">
            <!-- Calendar Widget -->
            <div class="calendar-widget" role="region" aria-label="Calendar events">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-calendar-days" aria-hidden="true"></i>
                        <span>Upcoming Events</span>
                    </h3>
                    <button class="widget-action-btn" onclick="openEventModal()" aria-label="Add new event">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                </div>

                <!-- Calendar Month Navigation -->
                <div class="calendar-header">
                    <button class="calendar-nav-btn" onclick="navigateMonth(-1)" aria-label="Previous month">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h4 class="calendar-month-year">
                        <?php echo date('F Y', $first_day_of_month); ?>
                    </h4>
                    <button class="calendar-nav-btn" onclick="navigateMonth(1)" aria-label="Next month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div class="calendar-wrapper">
                    <!-- Day Headers -->
                    <div class="calendar-day-headers">
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="calendar-grid">
                        <?php
                        // Add empty cells for days before month starts
                        for ($i = 0; $i < $day_of_week; $i++) {
                            echo '<div class="calendar-day-cell empty"></div>';
                        }

                        // Add cells for each day of the month
                        $today = date('Y-m-d');
                        for ($day = 1; $day <= $number_of_days; $day++) {
                            $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                            $is_today = ($current_date === $today);
                            $has_events = isset($events_by_date[$current_date]);
                            $classes = 'calendar-day-cell';
                            if ($is_today) $classes .= ' today';
                            if ($has_events) $classes .= ' has-event';

                            echo '<div class="' . $classes . '" data-date="' . $current_date . '">';
                            if ($has_events) {
                                echo '<span class="calendar-event-count" title="' . $events_by_date[$current_date] . ' event(s)">' . $events_by_date[$current_date] . '</span>';
                            }
                            echo '<span class="day-number">' . $day . '</span>';
                            echo '</div>';
                        }

                        // Fill remaining cells to complete the grid
                        $total_cells = $day_of_week + $number_of_days;
                        $remaining_cells = (7 - ($total_cells % 7)) % 7;
                        for ($i = 0; $i < $remaining_cells; $i++) {
                            echo '<div class="calendar-day-cell empty"></div>';
                        }
                        ?>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                    <a href="javascript:void(0)" onclick="openAllEventsModal()" style="color: var(--gov-primary); font-size: 0.8125rem; font-weight: 600; text-decoration: none; cursor: pointer; letter-spacing: 0.02em; text-transform: uppercase;">
                        View Full Calendar <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Notes Widget -->
            <div class="notes-widget" role="region" aria-label="System notes">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-clipboard" aria-hidden="true"></i>
                        <span>Official Notes</span>
                    </h3>
                    <button class="widget-action-btn" onclick="openNoteModal()" aria-label="Add new note">
                        <i class="fas fa-plus"></i> Add Note
                    </button>
                </div>

                <?php if (empty($recent_notes) && empty($pinned_notes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <p>No notes yet</p>
                        <button class="widget-action-btn" onclick="openNoteModal()">
                            <i class="fas fa-plus"></i> Create First Note
                        </button>
                    </div>
                <?php else: ?>
                    <ul class="notes-list" role="list">
                        <?php
                        // Show pinned notes first, then recent
                        $all_notes = array_merge($pinned_notes, $recent_notes);
                        $seen_ids = [];
                        foreach ($all_notes as $note):
                            if (in_array($note['id'], $seen_ids)) continue;
                            $seen_ids[] = $note['id'];
                        ?>
                            <li class="note-item <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>" role="listitem">
                                <div class="note-header">
                                    <div class="note-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                </div>
                                <div class="note-content-preview"><?php echo htmlspecialchars(substr($note['content'], 0, 120)) . (strlen($note['content']) > 120 ? '...' : ''); ?></div>
                                <div class="note-meta">
                                    <span class="note-type-badge"><?php echo ucfirst(str_replace('_', ' ', $note['note_type'])); ?></span>
                                    <span class="note-author">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($note['created_by_name'] ?? 'Unknown'); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php
                                        $time_diff = time() - strtotime($note['created_at']);
                                        if ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . 'h ago';
                                        } else {
                                            echo date('M d', strtotime($note['created_at']));
                                        }
                                        ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="text-align: center; margin-top: 16px;">
                        <a href="javascript:void(0)" onclick="openAllNotesModal()" style="color: var(--gov-primary); font-size: 0.8125rem; font-weight: 600; text-decoration: none; cursor: pointer; letter-spacing: 0.02em; text-transform: uppercase;">
                            View All Notes <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PDF Integrity Status Widget -->
        <?php if (getUserRole() === 'Admin'): ?>
        <?php $pdf_has_issues = $stats['pdf_integrity_issues'] > 0; ?>
        <div style="margin-bottom:24px;">
            <a href="../admin/pdf_integrity_report.php" style="text-decoration:none;">
                <div style="display:flex;align-items:center;gap:14px;padding:14px 18px;border-radius:var(--radius-md);background:<?= $pdf_has_issues ? 'var(--gov-danger-50)' : 'var(--gov-success-50)' ?>;border:1px solid <?= $pdf_has_issues ? 'rgba(153,27,27,0.2)' : 'rgba(15,118,110,0.2)' ?>;border-left:3px solid <?= $pdf_has_issues ? 'var(--gov-danger)' : 'var(--gov-success)' ?>;box-shadow:var(--gov-shadow-sm);">
                    <i data-lucide="<?= $pdf_has_issues ? 'shield-alert' : 'shield-check' ?>"
                       style="width:24px;height:24px;color:<?= $pdf_has_issues ? 'var(--gov-danger)' : 'var(--gov-success)' ?>;flex-shrink:0;"></i>
                    <div>
                        <div style="font-weight:700;font-size:0.875rem;color:<?= $pdf_has_issues ? 'var(--gov-danger)' : 'var(--gov-success)' ?>;text-transform:uppercase;letter-spacing:0.04em;">
                            Document Integrity:
                            <?= $pdf_has_issues
                                ? $stats['pdf_integrity_issues'] . ' issue(s) detected (last 30 days)'
                                : 'All checks passed' ?>
                        </div>
                        <div style="font-size:0.8125rem;color:var(--gov-text-muted);margin-top:2px;font-weight:500;">
                            Open the full integrity report and restore backups
                        </div>
                    </div>
                    <i data-lucide="chevron-right" style="width:18px;height:18px;color:var(--gov-text-subtle);margin-left:auto;"></i>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Total Birth Certificates -->
            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_births'] > 0 ? number_format($stats['total_births']) : '—'; ?></div>
                        <div class="stat-label">
                            <span>Total Birth Certificates</span>
                            <i class="fas fa-info-circle stat-label-info" data-tooltip="All birth certificates registered in the system"></i>
                        </div>
                        <?php if ($stats['total_births'] == 0): ?>
                            <div class="stat-empty-state">No birth certificates registered yet</div>
                        <?php elseif ($stats['birth_trend'] != 0): ?>
                            <div class="stat-trend <?php echo $stats['birth_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $stats['birth_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs($stats['birth_trend']); ?>% from last month
                            </div>
                        <?php else: ?>
                            <div class="stat-trend neutral">
                                <i class="fas fa-minus"></i>
                                No change from last month
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-lines"></i>
                    </div>
                </div>
            </div>

            <!-- Total Marriage Certificates -->
            <div class="stat-card red">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_marriages']); ?></div>
                        <div class="stat-label">Total Marriage Certificates</div>
                        <?php if ($stats['marriage_trend'] != 0): ?>
                            <div class="stat-trend <?php echo $stats['marriage_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $stats['marriage_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs($stats['marriage_trend']); ?>% from last month
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
            </div>

            <!-- This Month Births -->
            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['this_month_births'] > 0 ? number_format($stats['this_month_births']) : '—'; ?></div>
                        <div class="stat-label">
                            <span>Births This Month</span>
                            <i class="fas fa-info-circle stat-label-info" data-tooltip="Registered in <?php echo date('F Y'); ?>"></i>
                        </div>
                        <?php if ($stats['this_month_births'] == 0): ?>
                            <div class="stat-empty-state">No births recorded this month</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- This Month Marriages -->
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['this_month_marriages'] > 0 ? number_format($stats['this_month_marriages']) : '—'; ?></div>
                        <div class="stat-label">
                            <span>Marriages This Month</span>
                            <i class="fas fa-info-circle stat-label-info" data-tooltip="Registered in <?php echo date('F Y'); ?>"></i>
                        </div>
                        <?php if ($stats['this_month_marriages'] == 0): ?>
                            <div class="stat-empty-state">No marriages recorded this month</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
            </div>

            <!-- Total Death Certificates -->
            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_deaths']); ?></div>
                        <div class="stat-label">Total Death Certificates</div>
                        <?php if ($stats['death_trend'] != 0): ?>
                            <div class="stat-trend <?php echo $stats['death_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $stats['death_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs($stats['death_trend']); ?>% from last month
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-lines"></i>
                    </div>
                </div>
            </div>

            <!-- Total Marriage Licenses -->
            <div class="stat-card indigo">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_licenses']); ?></div>
                        <div class="stat-label">Total Marriage Licenses</div>
                        <?php if ($stats['license_trend'] != 0): ?>
                            <div class="stat-trend <?php echo $stats['license_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $stats['license_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs($stats['license_trend']); ?>% from last month
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-stamp"></i>
                    </div>
                </div>
            </div>

            <!-- This Month Deaths -->
            <div class="stat-card gray">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['this_month_deaths']); ?></div>
                        <div class="stat-label">Deaths This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-xmark"></i>
                    </div>
                </div>
            </div>

            <!-- This Month Licenses -->
            <div class="stat-card teal">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['this_month_licenses']); ?></div>
                        <div class="stat-label">Licenses This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Monthly Trend Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Monthly Registration Trends</h3>
                    <p class="chart-subtitle">Last 6 months overview</p>
                </div>
                <?php
                // Calculate insight for monthly trends
                $total_all_months = array_sum(array_column($monthly_chart_data, 'births')) +
                                   array_sum(array_column($monthly_chart_data, 'marriages')) +
                                   array_sum(array_column($monthly_chart_data, 'deaths')) +
                                   array_sum(array_column($monthly_chart_data, 'licenses'));
                $total_births_6m = array_sum(array_column($monthly_chart_data, 'births'));
                $birth_percentage = $total_all_months > 0 ? round(($total_births_6m / $total_all_months) * 100) : 0;

                // Find peak month
                $monthly_totals = [];
                foreach ($monthly_chart_data as $month_data) {
                    $monthly_totals[] = $month_data['births'] + $month_data['marriages'] + $month_data['deaths'] + $month_data['licenses'];
                }
                $peak_month_index = array_search(max($monthly_totals), $monthly_totals);
                $peak_month = $monthly_chart_data[$peak_month_index]['month'] ?? 'N/A';
                ?>
                <div class="chart-insight">
                    <div class="chart-insight-text">
                        <i class="fas fa-circle-info"></i>
                        <span>
                            <?php if ($total_all_months > 0): ?>
                                <strong><?php echo $peak_month; ?></strong> had the highest registration activity with <strong><?php echo max($monthly_totals); ?> total records</strong>.
                                Birth certificates account for <strong><?php echo $birth_percentage; ?>%</strong> of all registrations in the last 6 months.
                            <?php else: ?>
                                No registration data available for the last 6 months. Start adding records to see trends.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>

            <!-- Certificate Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Certificate Distribution</h3>
                    <p class="chart-subtitle">Total active certificates</p>
                </div>
                <?php
                // Calculate insight for distribution
                $total_certs = $stats['total_births'] + $stats['total_marriages'] + $stats['total_deaths'] + $stats['total_licenses'];
                if ($total_certs > 0) {
                    $distribution = [
                        'Birth' => $stats['total_births'],
                        'Marriage' => $stats['total_marriages'],
                        'Death' => $stats['total_deaths'],
                        'License' => $stats['total_licenses']
                    ];
                    arsort($distribution);
                    $dominant_type = key($distribution);
                    $dominant_percentage = round((current($distribution) / $total_certs) * 100);
                }
                ?>
                <div class="chart-insight">
                    <div class="chart-insight-text">
                        <i class="fas fa-circle-info"></i>
                        <span>
                            <?php if ($total_certs > 0): ?>
                                <strong><?php echo $dominant_type; ?> certificates</strong> represent the largest category at <strong><?php echo $dominant_percentage; ?>%</strong> of all records,
                                with a total of <strong><?php echo number_format($distribution[$dominant_type]); ?> certificates</strong> in the system.
                            <?php else: ?>
                                No certificates registered yet. Start by adding birth, marriage, death, or license records.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="activity-section">
            <div class="activity-header">
                <h3 class="activity-title"><i class="fas fa-clock-rotate-left"></i> Recent Activity</h3>
            </div>

            <?php if (empty($recent_activities)): ?>
                <p style="text-align: center; color: #6c757d; padding: 40px 0;">No recent activity found.</p>
            <?php else: ?>
                <ul class="activity-list" role="list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <?php
                            // Determine the URL for click-through
                            $view_url = '';
                            switch($activity['type']) {
                                case 'birth':
                                    $view_url = '../public/records_viewer.php?type=birth&id=' . $activity['id'];
                                    break;
                                case 'marriage':
                                    $view_url = '../public/records_viewer.php?type=marriage&id=' . $activity['id'];
                                    break;
                                case 'death':
                                    $view_url = '../public/records_viewer.php?type=death&id=' . $activity['id'];
                                    break;
                                case 'license':
                                    $view_url = '../public/records_viewer.php?type=license&id=' . $activity['id'];
                                    break;
                            }
                        ?>
                        <li class="activity-item" onclick="window.location.href='<?php echo $view_url; ?>'" role="listitem" tabindex="0" aria-label="View <?php echo $activity['type']; ?> record for <?php echo htmlspecialchars($activity['name']); ?>">
                            <div class="activity-icon <?php echo $activity['type']; ?>" aria-hidden="true">
                                <i class="fas fa-<?php
                                    echo $activity['type'] === 'birth' ? 'file-lines' :
                                        ($activity['type'] === 'marriage' ? 'file-signature' :
                                        ($activity['type'] === 'death' ? 'file-lines' : 'stamp'));
                                ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-name"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="activity-meta">
                                    <?php
                                        echo ucfirst($activity['type']);
                                        echo $activity['type'] === 'license' ? ' Application' : ' Certificate';
                                    ?> &bull; Registry #<?php echo htmlspecialchars($activity['registry_no']); ?>
                                </div>
                                <div class="activity-user-info">
                                    <span class="activity-action-badge">
                                        <i class="fas fa-plus-circle"></i> <?php echo $activity['action_type']; ?>
                                    </span>
                                    <span>&bull;</span>
                                    <span>By: <strong><?php echo htmlspecialchars($activity['created_by_name'] ?? 'System'); ?></strong></span>
                                    <?php if (!empty($activity['created_by_role'])): ?>
                                        <span class="activity-role-badge">
                                            <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($activity['created_by_role']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php
                                    $time_diff = time() - strtotime($activity['created_at']);
                                    if ($time_diff < 3600) {
                                        echo floor($time_diff / 60) . ' mins ago';
                                    } elseif ($time_diff < 86400) {
                                        echo floor($time_diff / 3600) . ' hours ago';
                                    } else {
                                        echo floor($time_diff / 86400) . ' days ago';
                                    }
                                ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions FAB -->
    <div class="quick-actions-fab">
        <div class="fab-menu" id="fabMenu">
            <div class="fab-action">
                <span class="fab-label">New Birth Certificate</span>
                <a href="../public/certificate_of_live_birth.php" class="fab-button primary">
                    <i class="fas fa-file-lines"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Marriage Certificate</span>
                <a href="../public/certificate_of_marriage.php" class="fab-button success">
                    <i class="fas fa-file-signature"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Death Certificate</span>
                <a href="../public/certificate_of_death.php" class="fab-button warning">
                    <i class="fas fa-file-lines"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Marriage License</span>
                <a href="../public/application_for_marriage_license.php" class="fab-button info">
                    <i class="fas fa-stamp"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">Generate Report</span>
                <a href="../admin/reports.php" class="fab-button secondary">
                    <i class="fas fa-file-pdf"></i>
                </a>
            </div>
        </div>
        <button class="fab-main" id="fabMain" aria-label="Quick actions menu">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <script>
        // Quick Actions FAB Toggle
        const fabMain = document.getElementById('fabMain');
        const fabMenu = document.getElementById('fabMenu');

        fabMain.addEventListener('click', () => {
            fabMain.classList.toggle('active');
            fabMenu.classList.toggle('active');
        });

        // Close FAB when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.quick-actions-fab')) {
                fabMain.classList.remove('active');
                fabMenu.classList.remove('active');
            }
        });

        // Filter Chips Functionality with Accessibility
        const filterChips = document.querySelectorAll('.filter-chip');
        const activityItems = document.querySelectorAll('.activity-item');

        filterChips.forEach(chip => {
            chip.addEventListener('click', () => {
                // Remove active class from all chips
                filterChips.forEach(c => {
                    c.classList.remove('active');
                    c.setAttribute('aria-pressed', 'false');
                });
                // Add active class to clicked chip
                chip.classList.add('active');
                chip.setAttribute('aria-pressed', 'true');

                const filter = chip.dataset.filter;

                // Filter activity items
                activityItems.forEach(item => {
                    const icon = item.querySelector('.activity-icon');
                    if (filter === 'all') {
                        item.style.display = 'flex';
                    } else {
                        if (icon.classList.contains(filter)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
            });
        });

        // Date Range Filter Functionality
        const dateRangeBtns = document.querySelectorAll('.date-range-btn');

        dateRangeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all date range buttons
                dateRangeBtns.forEach(b => {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                // Add active class to clicked button
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');

                const range = btn.dataset.range;
                console.log('Date range changed to:', range);

                // TODO: Implement AJAX call to reload dashboard data with new date range
                // For now, just show a message
                // In production, this would trigger a data refresh via AJAX
            });
        });

        // Keyboard Navigation for Activity Items
        activityItems.forEach(item => {
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });

        // Search Functionality
        const searchInput = document.getElementById('dashboardSearch');
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();

            activityItems.forEach(item => {
                const name = item.querySelector('.activity-name').textContent.toLowerCase();
                const meta = item.querySelector('.activity-meta').textContent.toLowerCase();

                if (name.includes(searchTerm) || meta.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Count-up Animation for Statistics
        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value.toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animate all stat numbers on page load
        window.addEventListener('load', () => {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const text = stat.textContent.trim();
                // Skip animation for non-numeric values (like em dash)
                if (text === '—' || text === '-' || text === '') {
                    return;
                }
                const finalValue = parseInt(text.replace(/,/g, '')) || 0;
                stat.textContent = '0';
                stat.classList.add('animate');
                setTimeout(() => {
                    animateValue(stat, 0, finalValue, 1500);
                }, 300);
            });
        });

        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_chart_data, 'month')); ?>,
                datasets: [
                    {
                        label: 'Birth Certificates',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'births')); ?>,
                        borderColor: '#0f2847',
                        backgroundColor: 'rgba(15, 40, 71, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0f2847',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#0f2847',
                        pointHoverBorderColor: '#c9a961',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Marriage Certificates',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'marriages')); ?>,
                        borderColor: '#c9a961',
                        backgroundColor: 'rgba(201, 169, 97, 0.1)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#c9a961',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#c9a961',
                        pointHoverBorderColor: '#0f2847',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Death Certificates',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'deaths')); ?>,
                        borderColor: '#991b1b',
                        backgroundColor: 'rgba(153, 27, 27, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#991b1b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#991b1b',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Marriage Licenses',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'licenses')); ?>,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0f766e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#0f766e',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                family: 'Inter',
                                size: 13,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f2847',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        borderColor: '#c9a961',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        titleFont: {
                            family: 'Inter',
                            weight: '700'
                        },
                        bodyFont: {
                            family: 'Inter'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            color: '#475569'
                        },
                        grid: {
                            color: 'rgba(221, 227, 237, 0.6)',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            color: '#475569'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionData = <?php echo json_encode(array_column($certificate_distribution, 'count')); ?>;
        const hasData = distributionData.some(value => value > 0);

        if (hasData) {
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($certificate_distribution, 'type')); ?>,
                    datasets: [{
                        data: distributionData,
                        backgroundColor: [
                            '#0f2847',
                            '#c9a961',
                            '#991b1b',
                            '#0f766e'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    family: 'Inter',
                                    size: 12,
                                    weight: '500'
                                },
                                color: '#0f172a'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f2847',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            borderColor: '#c9a961',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            titleFont: {
                                family: 'Inter',
                                weight: '700'
                            },
                            bodyFont: {
                                family: 'Inter'
                            },
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1500,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } else {
            // Display "No data" message
            distributionCtx.font = '14px Inter';
            distributionCtx.fillStyle = '#9ca3af';
            distributionCtx.textAlign = 'center';
            distributionCtx.textBaseline = 'middle';
            distributionCtx.fillText('No data available', distributionCtx.canvas.width / 2, distributionCtx.canvas.height / 2);
        }

        // Calendar Month Navigation
        function navigateMonth(direction) {
            const urlParams = new URLSearchParams(window.location.search);
            let month = urlParams.get('month') ? parseInt(urlParams.get('month')) : <?php echo date('n'); ?>;
            let year = urlParams.get('year') ? parseInt(urlParams.get('year')) : <?php echo date('Y'); ?>;

            month += direction;

            if (month > 12) {
                month = 1;
                year++;
            } else if (month < 1) {
                month = 12;
                year--;
            }

            window.location.href = `?month=${month}&year=${year}`;
        }

        // Calendar Day Click Handler
        const calendarDayCells = document.querySelectorAll('.calendar-day-cell:not(.empty)');
        calendarDayCells.forEach(cell => {
            cell.addEventListener('click', () => {
                const date = cell.dataset.date;
                const hasEvents = cell.classList.contains('has-event');

                // If has events, show them. Otherwise, open create modal
                if (hasEvents) {
                    viewEventsForDate(date);
                } else {
                    openEventModal(date);
                }
            });

            // Keyboard accessibility
            cell.setAttribute('tabindex', '0');
            cell.setAttribute('role', 'button');
            cell.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    cell.click();
                }
            });
        });

        // Modal Functions - Event Modal
        function openEventModal(date = null) {
            const modal = document.getElementById('eventModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Pre-fill date if provided
            if (date) {
                document.getElementById('event_date').value = date;
            } else {
                // Set to today's date
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('event_date').value = today;
            }
        }

        function closeEventModal() {
            const modal = document.getElementById('eventModal');
            const eventForm = document.getElementById('eventForm');
            const submitBtn = document.getElementById('eventSubmitBtn');
            const messageDiv = document.getElementById('eventFormMessage');

            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            eventForm.reset();

            // Reset form state for create mode
            delete eventForm.dataset.eventId;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Create Event';
            messageDiv.style.display = 'none';
        }

        // Modal Functions - Note Modal
        function openNoteModal() {
            const modal = document.getElementById('noteModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNoteModal() {
            const modal = document.getElementById('noteModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('noteForm').reset();
            delete document.getElementById('noteForm').dataset.noteId;
            document.getElementById('noteSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Create Note';
            const messageDiv = document.getElementById('noteFormMessage');
            messageDiv.style.display = 'none';
        }

        // View Events Modal Functions
        let currentViewDate = null;

        async function viewEventsForDate(date) {
            currentViewDate = date;
            const modal = document.getElementById('viewEventsModal');
            const loading = document.getElementById('viewEventsLoading');
            const eventsList = document.getElementById('eventsList');
            const noEventsMsg = document.getElementById('noEventsMessage');
            const modalTitle = document.getElementById('viewEventsModalTitle');

            // Format date for display
            const dateObj = new Date(date + 'T00:00:00');
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            modalTitle.textContent = `Events on ${formattedDate}`;

            // Show modal and loading
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            loading.style.display = 'block';
            eventsList.style.display = 'none';
            noEventsMsg.style.display = 'none';

            try {
                const response = await fetch(`../api/calendar_events.php?start_date=${date}&end_date=${date}`);
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.events && data.events.length > 0) {
                    // Display events
                    eventsList.innerHTML = data.events.map(event => `
                        <li class="event-list-item">
                            <div class="event-list-item-header">
                                <div class="event-list-item-title">${escapeHtml(event.title)}</div>
                                <div class="event-list-item-actions">
                                    <button class="event-action-btn edit" onclick="editEvent(${event.id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="event-action-btn delete" onclick="confirmDeleteEvent(${event.id}, '${escapeHtml(event.title)}')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            <div class="event-list-item-meta">
                                <span class="event-type-badge">${event.event_type}</span>
                                <span class="event-priority-badge ${event.priority}">${event.priority}</span>
                                ${event.event_time ? `<span><i class="fas fa-clock"></i> ${formatTime(event.event_time)}</span>` : ''}
                            </div>
                            ${event.description ? `<div class="event-list-item-description">${escapeHtml(event.description)}</div>` : ''}
                        </li>
                    `).join('');
                    eventsList.style.display = 'block';
                } else {
                    noEventsMsg.style.display = 'block';
                }
            } catch (error) {
                loading.style.display = 'none';
                noEventsMsg.style.display = 'block';
                console.error('Error fetching events:', error);
            }
        }

        function closeViewEventsModal() {
            const modal = document.getElementById('viewEventsModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            currentViewDate = null;
        }

        function openEventModalFromView() {
            closeViewEventsModal();
            openEventModal(currentViewDate);
        }

        // Utility Functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatTime(time) {
            if (!time) return '';
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        // Edit Event Function
        async function editEvent(eventId) {
            try {
                const response = await fetch(`../api/calendar_events.php?id=${eventId}`);
                const data = await response.json();

                if (data.success && data.event) {
                    const event = data.event;

                    // Pre-fill the form with event data
                    document.getElementById('event_title').value = event.title;
                    document.getElementById('event_type').value = event.event_type;
                    document.getElementById('event_date').value = event.event_date;
                    document.getElementById('event_time').value = event.event_time || '';
                    document.getElementById('event_priority').value = event.priority;
                    document.getElementById('event_description').value = event.description || '';

                    // Store event ID for update
                    document.getElementById('eventForm').dataset.eventId = eventId;

                    // Change button text
                    document.getElementById('eventSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Event';

                    // Close view modal and open edit modal
                    closeViewEventsModal();
                    openEventModal();
                } else {
                    alert('Failed to load event data');
                }
            } catch (error) {
                console.error('Error loading event:', error);
                alert('Error loading event data');
            }
        }

        // Delete Event Function
        async function confirmDeleteEvent(eventId, eventTitle) {
            if (confirm(`Are you sure you want to delete the event "${eventTitle}"?`)) {
                try {
                    const response = await fetch('../api/calendar_events.php', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ event_id: eventId })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Event deleted successfully');
                        window.location.reload();
                    } else {
                        alert('Failed to delete event: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error deleting event:', error);
                    alert('Error deleting event');
                }
            }
        }

        // Update Event Function
        async function updateEvent(eventId) {
            const submitBtn = document.getElementById('eventSubmitBtn');
            const messageDiv = document.getElementById('eventFormMessage');
            const originalBtnText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            const formData = {
                event_id: eventId,
                event_title: document.getElementById('event_title').value,
                event_type: document.getElementById('event_type').value,
                event_date: document.getElementById('event_date').value,
                event_time: document.getElementById('event_time').value,
                event_priority: document.getElementById('event_priority').value,
                event_description: document.getElementById('event_description').value
            };

            try {
                const response = await fetch('../api/calendar_events.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.style.display = 'block';
                    messageDiv.style.background = '#dcfce7';
                    messageDiv.style.color = '#166534';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;

                    setTimeout(() => {
                        const eventForm = document.getElementById('eventForm');
                        delete eventForm.dataset.eventId;
                        document.getElementById('eventSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Create Event';
                        closeEventModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to update event');
                }
            } catch (error) {
                messageDiv.style.display = 'block';
                messageDiv.style.background = '#fee2e2';
                messageDiv.style.color = '#991b1b';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error.message;

                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }

        // Submit Event Form via AJAX (handles both create and update)
        async function submitEventForm(e) {
            e.preventDefault();

            const eventForm = document.getElementById('eventForm');
            const eventId = eventForm.dataset.eventId;

            // Check if this is an update or create
            if (eventId) {
                await updateEvent(eventId);
            } else {
                await createEvent();
            }
        }

        // Create Event Function
        async function createEvent() {
            const submitBtn = document.getElementById('eventSubmitBtn');
            const messageDiv = document.getElementById('eventFormMessage');
            const originalBtnText = submitBtn.innerHTML;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

            const formData = {
                event_title: document.getElementById('event_title').value,
                event_type: document.getElementById('event_type').value,
                event_date: document.getElementById('event_date').value,
                event_time: document.getElementById('event_time').value,
                event_priority: document.getElementById('event_priority').value,
                event_description: document.getElementById('event_description').value
            };

            try {
                const response = await fetch('../api/calendar_events.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    messageDiv.style.display = 'block';
                    messageDiv.style.background = '#dcfce7';
                    messageDiv.style.color = '#166534';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;

                    // Close modal after 1.5 seconds and reload page
                    setTimeout(() => {
                        closeEventModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to create event');
                }
            } catch (error) {
                // Show error message
                messageDiv.style.display = 'block';
                messageDiv.style.background = '#fee2e2';
                messageDiv.style.color = '#991b1b';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error.message;

                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }

        // Submit Note Form via AJAX (handles both create and update)
        async function submitNoteForm(e) {
            e.preventDefault();

            const noteForm = document.getElementById('noteForm');
            const noteId = noteForm.dataset.noteId;
            const submitBtn = document.getElementById('noteSubmitBtn');
            const messageDiv = document.getElementById('noteFormMessage');
            const originalBtnText = submitBtn.innerHTML;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = noteId
                ? '<i class="fas fa-spinner fa-spin"></i> Updating...'
                : '<i class="fas fa-spinner fa-spin"></i> Creating...';

            const formData = {
                note_title: document.getElementById('note_title').value,
                note_type: document.getElementById('note_type').value,
                note_content: document.getElementById('note_content').value,
                is_pinned: document.getElementById('is_pinned').checked ? '1' : '0'
            };

            if (noteId) {
                formData.note_id = noteId;
            }

            try {
                const response = await fetch('../api/notes.php', {
                    method: noteId ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    messageDiv.style.display = 'block';
                    messageDiv.style.background = '#dcfce7';
                    messageDiv.style.color = '#166534';
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;

                    // Close modal after 1.5 seconds and reload page
                    setTimeout(() => {
                        closeNoteModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || (noteId ? 'Failed to update note' : 'Failed to create note'));
                }
            } catch (error) {
                // Show error message
                messageDiv.style.display = 'block';
                messageDiv.style.background = '#fee2e2';
                messageDiv.style.color = '#991b1b';
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + error.message;

                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }

        // Close event modal and reset form
        function closeEventModal() {
            const modal = document.getElementById('eventModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('eventForm').reset();
            delete document.getElementById('eventForm').dataset.eventId;
            document.getElementById('eventSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Create Event';
            const messageDiv = document.getElementById('eventFormMessage');
            messageDiv.style.display = 'none';
        }
    </script>

    </div> <!-- Close .content -->

    <!-- Add Event Modal -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-calendar-plus"></i>
                    Add New Event
                </h2>
                <button class="modal-close" onclick="closeEventModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="eventForm" onsubmit="submitEventForm(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="event_title">Event Title <span class="required">*</span></label>
                        <input type="text" id="event_title" name="event_title" required placeholder="Enter event title">
                    </div>

                    <div class="form-group">
                        <label for="event_type">Event Type <span class="required">*</span></label>
                        <select id="event_type" name="event_type" required>
                            <option value="">Select event type</option>
                            <option value="registration">Registration</option>
                            <option value="deadline">Deadline</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="digitization">Digitization</option>
                            <option value="meeting">Meeting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date <span class="required">*</span></label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label for="event_time">Event Time</label>
                        <input type="time" id="event_time" name="event_time">
                    </div>

                    <div class="form-group">
                        <label for="event_priority">Priority</label>
                        <select id="event_priority" name="event_priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_description">Description</label>
                        <textarea id="event_description" name="event_description" placeholder="Add event details..."></textarea>
                    </div>

                    <div id="eventFormMessage" style="display: none; padding: 12px; border-radius: 8px; margin-top: 16px; font-size: 0.875rem; font-weight: 500;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeEventModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn modal-btn-primary" id="eventSubmitBtn">
                        <i class="fas fa-check"></i> Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div class="modal-overlay" id="noteModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-sticky-note"></i>
                    Add New Note
                </h2>
                <button class="modal-close" onclick="closeNoteModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="noteForm" onsubmit="submitNoteForm(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="note_title">Note Title <span class="required">*</span></label>
                        <input type="text" id="note_title" name="note_title" required placeholder="Enter note title">
                    </div>

                    <div class="form-group">
                        <label for="note_type">Note Type <span class="required">*</span></label>
                        <select id="note_type" name="note_type" required>
                            <option value="">Select note type</option>
                            <option value="operational">Operational</option>
                            <option value="administrative">Administrative</option>
                            <option value="technical">Technical</option>
                            <option value="audit">Audit</option>
                            <option value="compliance">Compliance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="note_content">Content <span class="required">*</span></label>
                        <textarea id="note_content" name="note_content" required placeholder="Write your note here..."></textarea>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="is_pinned" name="is_pinned" value="1" style="width: auto; margin: 0;">
                        <label for="is_pinned" style="margin: 0; font-weight: 500; cursor: pointer;">Pin this note to dashboard</label>
                    </div>

                    <div id="noteFormMessage" style="display: none; padding: 12px; border-radius: 8px; margin-top: 16px; font-size: 0.875rem; font-weight: 500;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeNoteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="modal-btn modal-btn-primary" id="noteSubmitBtn">
                        <i class="fas fa-check"></i> Create Note
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Events Modal -->
    <div class="modal-overlay" id="viewEventsModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-calendar-day"></i>
                    <span id="viewEventsModalTitle">Events</span>
                </h2>
                <button class="modal-close" onclick="closeViewEventsModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="viewEventsLoading" style="text-align: center; padding: 40px; display: none;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3b82f6;"></i>
                    <p style="margin-top: 16px; color: #64748b;">Loading events...</p>
                </div>
                <ul class="event-list" id="eventsList"></ul>
                <div id="noEventsMessage" style="text-align: center; padding: 40px; color: #94a3b8; display: none;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 16px;"></i>
                    <p>No events on this date</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeViewEventsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="openEventModalFromView()">
                    <i class="fas fa-plus"></i> Add Event
                </button>
            </div>
        </div>
    </div>

    <!-- All Events Modal -->
    <div class="modal-overlay" id="allEventsModal">
        <div class="modal-container modal-container-wide">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-calendar-alt"></i>
                    All Events
                </h2>
                <button class="modal-close" onclick="closeAllEventsModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px 24px;">
                <div class="modal-filter-bar">
                    <select id="allEventsFilterType" onchange="filterAllEvents()">
                        <option value="all">All Types</option>
                        <option value="registration">Registration</option>
                        <option value="deadline">Deadline</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="digitization">Digitization</option>
                        <option value="meeting">Meeting</option>
                        <option value="other">Other</option>
                    </select>
                    <select id="allEventsFilterPriority" onchange="filterAllEvents()">
                        <option value="all">All Priorities</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div id="allEventsLoading" class="modal-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading events...</p>
                </div>
                <ul class="all-events-list" id="allEventsList" style="display: none;"></ul>
                <div id="allEventsEmpty" class="modal-empty-state" style="display: none;">
                    <i class="fas fa-calendar-times"></i>
                    <p>No events found</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeAllEventsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="closeAllEventsModal(); openEventModal();">
                    <i class="fas fa-plus"></i> Add Event
                </button>
            </div>
        </div>
    </div>

    <!-- All Notes Modal -->
    <div class="modal-overlay" id="allNotesModal">
        <div class="modal-container modal-container-wide">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-sticky-note"></i>
                    All Notes
                </h2>
                <button class="modal-close" onclick="closeAllNotesModal()" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px 24px;">
                <div class="modal-filter-bar">
                    <select id="allNotesFilterType" onchange="filterAllNotes()">
                        <option value="all">All Types</option>
                        <option value="operational">Operational</option>
                        <option value="administrative">Administrative</option>
                        <option value="technical">Technical</option>
                        <option value="audit">Audit</option>
                        <option value="compliance">Compliance</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div id="allNotesLoading" class="modal-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading notes...</p>
                </div>
                <ul class="all-notes-list" id="allNotesList" style="display: none;"></ul>
                <div id="allNotesEmpty" class="modal-empty-state" style="display: none;">
                    <i class="fas fa-clipboard"></i>
                    <p>No notes found</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeAllNotesModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="modal-btn modal-btn-primary" onclick="closeAllNotesModal(); openNoteModal();">
                    <i class="fas fa-plus"></i> Add Note
                </button>
            </div>
        </div>
    </div>

    <script>
        // Attach event listeners to modals (must run after modals are in DOM)

        // Close modals when clicking on overlay
        const eventModalEl = document.getElementById('eventModal');
        if (eventModalEl) {
            eventModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'eventModal') {
                    closeEventModal();
                }
            });
        }

        const noteModalEl = document.getElementById('noteModal');
        if (noteModalEl) {
            noteModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'noteModal') {
                    closeNoteModal();
                }
            });
        }

        const viewEventsModalEl = document.getElementById('viewEventsModal');
        if (viewEventsModalEl) {
            viewEventsModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'viewEventsModal') {
                    closeViewEventsModal();
                }
            });
        }

        // Close overlay listeners for All Events and All Notes modals
        const allEventsModalEl = document.getElementById('allEventsModal');
        if (allEventsModalEl) {
            allEventsModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'allEventsModal') {
                    closeAllEventsModal();
                }
            });
        }

        const allNotesModalEl = document.getElementById('allNotesModal');
        if (allNotesModalEl) {
            allNotesModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'allNotesModal') {
                    closeAllNotesModal();
                }
            });
        }

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const eventModal = document.getElementById('eventModal');
                const noteModal = document.getElementById('noteModal');
                const viewEventsModal = document.getElementById('viewEventsModal');
                const allEventsModal = document.getElementById('allEventsModal');
                const allNotesModal = document.getElementById('allNotesModal');

                if (eventModal && eventModal.classList.contains('active')) {
                    closeEventModal();
                }
                if (noteModal && noteModal.classList.contains('active')) {
                    closeNoteModal();
                }
                if (viewEventsModal && viewEventsModal.classList.contains('active')) {
                    closeViewEventsModal();
                }
                if (allEventsModal && allEventsModal.classList.contains('active')) {
                    closeAllEventsModal();
                }
                if (allNotesModal && allNotesModal.classList.contains('active')) {
                    closeAllNotesModal();
                }
            }
        });

        // ========== All Events Modal Functions ==========
        let allEventsData = [];

        async function openAllEventsModal() {
            const modal = document.getElementById('allEventsModal');
            const loading = document.getElementById('allEventsLoading');
            const list = document.getElementById('allEventsList');
            const empty = document.getElementById('allEventsEmpty');

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            loading.style.display = 'block';
            list.style.display = 'none';
            empty.style.display = 'none';

            // Reset filters
            document.getElementById('allEventsFilterType').value = 'all';
            document.getElementById('allEventsFilterPriority').value = 'all';

            try {
                const response = await fetch('../api/calendar_events.php');
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.events && data.events.length > 0) {
                    allEventsData = data.events;
                    renderAllEvents(allEventsData);
                } else {
                    allEventsData = [];
                    empty.style.display = 'block';
                }
            } catch (error) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                console.error('Error fetching events:', error);
            }
        }

        function closeAllEventsModal() {
            const modal = document.getElementById('allEventsModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function renderAllEvents(events) {
            const list = document.getElementById('allEventsList');
            const empty = document.getElementById('allEventsEmpty');

            if (events.length === 0) {
                list.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            list.innerHTML = events.map(event => {
                const dateObj = new Date(event.event_date + 'T00:00:00');
                const dateStr = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                const timeStr = event.event_time ? formatTime(event.event_time) : '';

                return `
                    <li class="all-event-item">
                        <div class="all-item-header">
                            <div class="all-item-title">${escapeHtml(event.title)}</div>
                            <div class="all-item-actions">
                                <button class="all-item-action-btn" onclick="closeAllEventsModal(); editEvent(${event.id});">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="all-item-action-btn delete" onclick="confirmDeleteEvent(${event.id}, '${escapeHtml(event.title)}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="all-item-meta">
                            <span class="event-type-badge">${event.event_type}</span>
                            <span class="event-priority-badge ${event.priority}">${event.priority}</span>
                            <span><i class="fas fa-calendar"></i> ${dateStr}</span>
                            ${timeStr ? `<span><i class="fas fa-clock"></i> ${timeStr}</span>` : ''}
                            ${event.created_by_name ? `<span><i class="fas fa-user"></i> ${escapeHtml(event.created_by_name)}</span>` : ''}
                        </div>
                        ${event.description ? `<div class="all-item-description">${escapeHtml(event.description)}</div>` : ''}
                    </li>
                `;
            }).join('');

            list.style.display = 'block';
            empty.style.display = 'none';
        }

        function filterAllEvents() {
            const typeFilter = document.getElementById('allEventsFilterType').value;
            const priorityFilter = document.getElementById('allEventsFilterPriority').value;

            let filtered = allEventsData;
            if (typeFilter !== 'all') {
                filtered = filtered.filter(e => e.event_type === typeFilter);
            }
            if (priorityFilter !== 'all') {
                filtered = filtered.filter(e => e.priority === priorityFilter);
            }
            renderAllEvents(filtered);
        }

        // ========== All Notes Modal Functions ==========
        let allNotesData = [];

        async function openAllNotesModal() {
            const modal = document.getElementById('allNotesModal');
            const loading = document.getElementById('allNotesLoading');
            const list = document.getElementById('allNotesList');
            const empty = document.getElementById('allNotesEmpty');

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            loading.style.display = 'block';
            list.style.display = 'none';
            empty.style.display = 'none';

            // Reset filter
            document.getElementById('allNotesFilterType').value = 'all';

            try {
                const response = await fetch('../api/notes.php');
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.notes && data.notes.length > 0) {
                    allNotesData = data.notes;
                    renderAllNotes(allNotesData);
                } else {
                    allNotesData = [];
                    empty.style.display = 'block';
                }
            } catch (error) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                console.error('Error fetching notes:', error);
            }
        }

        function closeAllNotesModal() {
            const modal = document.getElementById('allNotesModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function renderAllNotes(notes) {
            const list = document.getElementById('allNotesList');
            const empty = document.getElementById('allNotesEmpty');

            if (notes.length === 0) {
                list.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            list.innerHTML = notes.map(note => {
                const dateObj = new Date(note.created_at);
                const dateStr = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                const isPinned = note.is_pinned == 1;
                const pinnedClass = isPinned ? 'all-item-pinned' : '';
                const contentPreview = note.content.length > 150 ? note.content.substring(0, 150) + '...' : note.content;

                return `
                    <li class="all-note-item ${pinnedClass}">
                        <div class="all-item-header">
                            <div class="all-item-title">
                                ${isPinned ? '<i class="fas fa-thumbtack" style="color: #f59e0b; margin-right: 6px; font-size: 0.75rem;"></i>' : ''}
                                ${escapeHtml(note.title)}
                            </div>
                            <div class="all-item-actions">
                                <button class="all-item-action-btn pin" onclick="togglePinNote(${note.id})" title="${isPinned ? 'Unpin' : 'Pin to dashboard'}">
                                    <i class="fas fa-thumbtack"></i> ${isPinned ? 'Unpin' : 'Pin'}
                                </button>
                                <button class="all-item-action-btn" onclick="closeAllNotesModal(); editNote(${note.id});">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="all-item-action-btn delete" onclick="confirmDeleteNote(${note.id}, '${escapeHtml(note.title)}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="all-note-content-preview">${escapeHtml(contentPreview)}</div>
                        <div class="all-item-meta">
                            <span class="note-type-badge">${note.note_type.replace('_', ' ')}</span>
                            ${note.created_by_name ? `<span><i class="fas fa-user"></i> ${escapeHtml(note.created_by_name)}</span>` : ''}
                            <span><i class="fas fa-clock"></i> ${dateStr}</span>
                        </div>
                    </li>
                `;
            }).join('');

            list.style.display = 'block';
            empty.style.display = 'none';
        }

        function filterAllNotes() {
            const typeFilter = document.getElementById('allNotesFilterType').value;

            let filtered = allNotesData;
            if (typeFilter !== 'all') {
                filtered = filtered.filter(n => n.note_type === typeFilter);
            }
            renderAllNotes(filtered);
        }

        // Edit Note Function
        async function editNote(noteId) {
            try {
                const response = await fetch(`../api/notes.php?id=${noteId}`);
                const data = await response.json();

                if (data.success && data.note) {
                    const note = data.note;

                    document.getElementById('note_title').value = note.title;
                    document.getElementById('note_type').value = note.note_type;
                    document.getElementById('note_content').value = note.content;
                    document.getElementById('is_pinned').checked = note.is_pinned == 1;

                    // Store note ID for update
                    document.getElementById('noteForm').dataset.noteId = noteId;

                    // Change button text
                    document.getElementById('noteSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Note';

                    openNoteModal();
                } else {
                    alert('Failed to load note data');
                }
            } catch (error) {
                console.error('Error loading note:', error);
                alert('Error loading note data');
            }
        }

        // Delete Note Function
        async function confirmDeleteNote(noteId, noteTitle) {
            if (confirm(`Are you sure you want to delete the note "${noteTitle}"?`)) {
                try {
                    const response = await fetch('../api/notes.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ note_id: noteId })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Note deleted successfully');
                        window.location.reload();
                    } else {
                        alert('Failed to delete note: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error deleting note:', error);
                    alert('Error deleting note');
                }
            }
        }

        // Toggle Pin Note Function
        async function togglePinNote(noteId) {
            try {
                const response = await fetch('../api/notes.php?action=toggle_pin', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ note_id: noteId })
                });

                const data = await response.json();

                if (data.success) {
                    // Refresh the modal
                    openAllNotesModal();
                } else {
                    alert('Failed to toggle pin: ' + data.message);
                }
            } catch (error) {
                console.error('Error toggling pin:', error);
                alert('Error toggling pin status');
            }
        }
    </script>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
