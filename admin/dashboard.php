<?php
/**
 * Dashboard - Civil Registry Document Management System (CRDMS)
 * Main admin dashboard with analytics and statistics
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

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
$security_stats = ['last_login' => null, 'failed_login_count' => 0, 'active_users' => 0];
$today_events = $upcoming_events = $pinned_notes = $recent_notes = $events_by_date = [];
$stats['pdf_integrity_issues'] = 0;
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$number_of_days = date('t', $first_day_of_month);
$day_of_week = date('w', $first_day_of_month);

try {
    // PDF integrity issue count (last 30 days)
    $stmt = $pdo->query(
        "SELECT COUNT(*) as count FROM security_logs
          WHERE event_type = 'PDF_INTEGRITY_FAILURE'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stats['pdf_integrity_issues'] = (int)($stmt->fetch()['count'] ?? 0);

    // Double registration link counts (wrapped in own try/catch — table may not exist yet)
    try {
        $dr_stmt = $pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_links,
                SUM(CASE WHEN status = 'active' AND needs_correction = 1 THEN 1 ELSE 0 END) AS needs_correction
             FROM record_links"
        );
        $dr_row = $dr_stmt->fetch(PDO::FETCH_ASSOC);
        $stats['double_reg_active'] = (int)($dr_row['active_links'] ?? 0);
        $stats['double_reg_needs_correction'] = (int)($dr_row['needs_correction'] ?? 0);
    } catch (PDOException $e) {
        $stats['double_reg_active'] = 0;
        $stats['double_reg_needs_correction'] = 0;
    }

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
    <?= csrfTokenMeta() ?>
    <script>
    (function () {
        const originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            init = init || {};
            const method = String(init.method || 'GET').toUpperCase();
            const url = typeof input === 'string' ? input : input.url;
            if (['POST','PUT','PATCH','DELETE'].includes(method) && /^\.\.?\/api\//.test(url)) {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const headers = new Headers(init.headers || {});
                if (token) headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
            return originalFetch(input, init);
        };
    }());
    </script>
    <title>Dashboard - Civil Registry System</title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <script src="<?= asset_url('chartjs') ?>"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <link rel="stylesheet" href="../assets/css/dashboard.css?v=20260908">
</head>
<body class="dashboard-page" data-sidebar-breakpoint="1024">
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>
    <?php
        $grand_total_records = array_sum(array_map(static fn($key) => $stats['total_' . $key] ?? 0, ['births', 'marriages', 'deaths', 'licenses']));
        $this_month_total = array_sum(array_map(static fn($key) => $stats['this_month_' . $key] ?? 0, ['births', 'marriages', 'deaths', 'licenses']));
        $categories = [
            ['key' => 'births', 'type' => 'birth', 'label' => 'Births', 'name' => 'Birth Certificates', 'icon' => 'file-lines', 'trend' => 'birth_trend'],
            ['key' => 'marriages', 'type' => 'marriage', 'label' => 'Marriages', 'name' => 'Marriage Certificates', 'icon' => 'file-signature', 'trend' => 'marriage_trend'],
            ['key' => 'deaths', 'type' => 'death', 'label' => 'Deaths', 'name' => 'Death Certificates', 'icon' => 'file-minus', 'trend' => 'death_trend'],
            ['key' => 'licenses', 'type' => 'license', 'label' => 'Marriage Licenses', 'name' => 'Marriage Licenses', 'icon' => 'stamp', 'trend' => 'license_trend']
        ];
        $monthly_totals = array_map(static fn($month) => $month['births'] + $month['marriages'] + $month['deaths'] + $month['licenses'], $monthly_chart_data);
        $total_all_months = array_sum($monthly_totals);
        $peak_month_index = $monthly_totals ? array_search(max($monthly_totals), $monthly_totals) : false;
    ?>
    <main class="content" id="mainContent">
        <div class="dashboard-container">
            <header class="dashboard-header">
                <div class="header-identity">
                    <img class="header-seal" src="../assets/img/LOGO1.png" alt="Civil Registry Office seal">
                    <div>
                        <p class="header-eyebrow">Civil Registry Records / iSCAN</p>
                        <h1>Civil Registry Dashboard</h1>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="header-date"><i class="fas fa-calendar-days" aria-hidden="true"></i> <?= date('l, F j, Y') ?></span>
                    <a class="button-secondary" href="../public/folder_browser.php"><i class="fas fa-table-list" aria-hidden="true"></i> Records</a>
                    <a class="button-primary" href="reports.php"><i class="fas fa-chart-line" aria-hidden="true"></i> View Reports</a>
                </div>
            </header>

            <section class="stats-grid" aria-label="Registry statistics">
                <article class="stat-card total">
                    <div class="stat-copy"><h2 class="stat-label">Total Records</h2><p class="stat-number" data-value="<?= (int) $grand_total_records ?>"><?= number_format($grand_total_records) ?></p><p class="stat-caption">All active records</p></div>
                    <span class="stat-icon" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
                </article>
                <article class="stat-card month">
                    <div class="stat-copy"><h2 class="stat-label">This Month</h2><p class="stat-number" data-value="<?= (int) $this_month_total ?>"><?= number_format($this_month_total) ?></p><p class="stat-caption">Registered in <?= date('F Y') ?></p></div>
                    <span class="stat-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                </article>
                <?php foreach ($categories as $category): ?>
                    <?php $trend = $stats[$category['trend']] ?? 0; ?>
                    <article class="stat-card <?= $category['type'] ?>">
                        <div class="stat-copy">
                            <h2 class="stat-label"><?= $category['label'] ?></h2>
                            <p class="stat-number" data-value="<?= (int) ($stats['total_' . $category['key']] ?? 0) ?>"><?= number_format($stats['total_' . $category['key']] ?? 0) ?></p>
                            <p class="stat-caption"><strong><?= number_format($stats['this_month_' . $category['key']] ?? 0) ?></strong> this month</p>
                        </div>
                        <span class="stat-icon" aria-hidden="true"><i <?= $category['type'] === 'death' ? 'data-lucide="file-minus"' : 'class="fas fa-' . $category['icon'] . '"' ?>></i></span>
                        <p class="stat-trend <?= $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'neutral') ?>">
                            <i class="fas fa-<?= $trend > 0 ? 'arrow-up' : ($trend < 0 ? 'arrow-down' : 'minus') ?>" aria-hidden="true"></i>
                            <?= $trend == 0 ? 'No change' : number_format(abs($trend)) . '%' ?> <span>monthly vs. last month</span>
                        </p>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="charts-section" aria-label="Registration analytics">
                <article class="panel trend-panel">
                    <div class="panel-header"><div><h2>Monthly Registration Trends</h2><p>Active records registered over the last six months</p></div><span class="period-badge">6 months</span></div>
                    <div class="trend-chart-wrap"><canvas id="monthlyTrendChart" role="img" aria-label="Monthly registrations by certificate type"></canvas></div>
                    <details class="chart-data-details"><summary>View monthly data</summary><div class="table-scroll"><table class="chart-data-table"><caption class="sr-only">Monthly registrations by certificate type</caption><thead><tr><th scope="col">Month</th><?php foreach ($categories as $category): ?><th scope="col"><?= $category['label'] ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($monthly_chart_data as $month): ?><tr><th scope="row"><?= htmlspecialchars($month['month']) ?></th><?php foreach ($categories as $category): ?><td><?= number_format($month[$category['key']]) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></details>
                    <p class="chart-footnote"><i class="fas fa-circle-info" aria-hidden="true"></i>
                    <?php if ($total_all_months > 0 && $peak_month_index !== false): ?>
                        <span><strong><?= htmlspecialchars($monthly_chart_data[$peak_month_index]['month']) ?></strong> had the most activity: <strong><?= number_format($monthly_totals[$peak_month_index]) ?> records</strong>.</span>
                    <?php else: ?><span>No registrations in the last six months. New records will appear here.</span><?php endif; ?>
                    </p>
                </article>
                <article class="panel distribution-panel">
                    <div class="panel-header"><div><h2>Certificate Distribution</h2><p>Share of all active records</p></div><span class="period-badge">All time</span></div>
                    <div class="distribution-chart-wrap">
                        <canvas id="distributionChart" role="img" aria-label="Certificate distribution; exact counts and percentages listed below" <?= $grand_total_records === 0 ? 'hidden' : '' ?>></canvas>
                        <div class="distribution-center" aria-hidden="true"><strong><?= number_format($grand_total_records) ?></strong><span><?= $grand_total_records > 0 ? 'Total records' : 'No records yet' ?></span></div>
                    </div>
                    <ul class="distribution-list" aria-label="Certificate counts and percentages">
                        <?php foreach ($categories as $category): ?>
                            <?php $count = $stats['total_' . $category['key']] ?? 0; ?>
                            <li class="<?= $category['type'] ?>"><span class="category-dot" aria-hidden="true"></span><span class="distribution-label"><?= $category['name'] ?></span><strong><?= number_format($count) ?></strong><span class="distribution-percentage"><?= $grand_total_records > 0 ? number_format($count / $grand_total_records * 100, 1) . '%' : '—' ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </section>

            <section class="panel activity-section" aria-labelledby="activityHeading">
                <div class="panel-header activity-header">
                    <div><h2 id="activityHeading">Recent Activity</h2><p>Latest registrations across the registry</p></div>
                    <a class="text-link" href="../public/records_viewer.php">View All Records <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="activity-filters" role="group" aria-label="Filter activity by type">
                    <?php foreach (['all' => 'All records', 'birth' => 'Births', 'marriage' => 'Marriages', 'death' => 'Deaths', 'license' => 'Licenses'] as $type => $label): ?>
                        <button type="button" class="activity-tab <?= $type === 'all' ? 'active' : '' ?>" data-filter="<?= $type ?>" aria-pressed="<?= $type === 'all' ? 'true' : 'false' ?>"><?= $label ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="activity-columns" aria-hidden="true"><span>Registry / Person</span><span>Category</span><span>Created by</span><span>Added</span><span></span></div>
                <ul class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <?php
                            $view_url = '../public/records_viewer.php?type=' . rawurlencode($activity['type']) . '&id=' . (int)$activity['id'];
                            $age_seconds = max(0, time() - strtotime($activity['created_at']));
                            $relative_time = $age_seconds < 60 ? 'Just now' : ($age_seconds < 3600 ? floor($age_seconds / 60) . ' mins ago' : ($age_seconds < 86400 ? floor($age_seconds / 3600) . ' hours ago' : floor($age_seconds / 86400) . ' days ago'));
                        ?>
                        <li class="activity-item" data-type="<?= htmlspecialchars($activity['type'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="activity-person"><span class="activity-avatar <?= htmlspecialchars($activity['type'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr(trim($activity['name']), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><div><strong class="activity-registry"><?= htmlspecialchars($activity['registry_no'] ?: 'No registry number', ENT_QUOTES, 'UTF-8') ?></strong><span class="activity-name"><?= htmlspecialchars($activity['name'], ENT_QUOTES, 'UTF-8') ?></span></div></div>
                            <span class="category-badge <?= htmlspecialchars($activity['type'], ENT_QUOTES, 'UTF-8') ?>"><?= $activity['type'] === 'license' ? 'Marriage License' : htmlspecialchars(ucfirst($activity['type'])) . ' Certificate' ?></span>
                            <div class="activity-author"><span><?= htmlspecialchars($activity['created_by_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($activity['created_by_role'])): ?><small><?= htmlspecialchars($activity['created_by_role'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div>
                            <time class="activity-time" datetime="<?= htmlspecialchars(date('c', strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(date('M j, Y g:i A', strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?>"><?= $relative_time ?></time>
                            <a class="record-view" href="<?= htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8') ?>" aria-label="View <?= htmlspecialchars($activity['type'] . ' record ' . $activity['registry_no'] . ' for ' . $activity['name'], ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-eye" aria-hidden="true"></i> View</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="activity-empty" id="activityEmpty" role="status" <?= $recent_activities ? 'hidden' : '' ?>>No recent activity found.</p>
            </section>

            <div class="section-heading office-heading"><h2>Office Workspace</h2><p>Your calendar and team notes</p></div>
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
                    <h4 class="calendar-month-year" id="calendarMonthYear">
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
                    <div class="calendar-grid" id="calendarGrid">
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
                            <li class="note-item <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>" role="button" tabindex="0"
                                data-note-id="<?php echo (int) $note['id']; ?>"
                                data-note-title="<?php echo htmlspecialchars((string) $note['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-note-content="<?php echo htmlspecialchars((string) $note['content'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-note-type="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $note['note_type'])), ENT_QUOTES, 'UTF-8'); ?>"
                                data-note-author="<?php echo htmlspecialchars((string) ($note['created_by_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-note-created-at="<?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($note['created_at'])), ENT_QUOTES, 'UTF-8'); ?>"
                                aria-label="Open note <?php echo htmlspecialchars((string) $note['title'], ENT_QUOTES, 'UTF-8'); ?>">
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

<div class="section-heading system-heading"><h2>System Overview</h2><p>Account activity and document checks</p></div>
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


            <div class="system-checks">
                <?php if (getUserRole() === 'Admin'): ?>
                    <?php $pdf_has_issues = ($stats['pdf_integrity_issues'] ?? 0) > 0; ?>
                    <a class="system-check <?= $pdf_has_issues ? 'has-issues' : 'healthy' ?>" href="pdf_integrity_report.php"><span class="check-icon"><i data-lucide="<?= $pdf_has_issues ? 'shield-alert' : 'shield-check' ?>" aria-hidden="true"></i></span><div><strong>Document Integrity</strong><p><?= $pdf_has_issues ? number_format($stats['pdf_integrity_issues']) . ' issue(s) detected in the last 30 days' : 'All checks passed' ?></p><small>View the integrity report and restore backups</small></div><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                <?php endif; ?>
                <?php if (($stats['double_reg_active'] ?? 0) > 0): ?>
                    <a class="system-check has-issues" href="../public/double_registration.php"><span class="check-icon"><i data-lucide="link-2" aria-hidden="true"></i></span><div><strong>Double Registrations</strong><p><?= number_format($stats['double_reg_active']) ?> active links<?php if (($stats['double_reg_needs_correction'] ?? 0) > 0): ?> · <?= number_format($stats['double_reg_needs_correction']) ?> need correction<?php endif; ?></p><small>Review linked records and corrections</small></div><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
        </div>

    <script>
        // Filter semantic rows without overriding their responsive display layout.
        const activityTabs = document.querySelectorAll('.activity-tab');
        const activityItems = document.querySelectorAll('.activity-item');
        activityTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                activityTabs.forEach(button => {
                    const selected = button === tab;
                    button.classList.toggle('active', selected);
                    button.setAttribute('aria-pressed', String(selected));
                });
                let visibleCount = 0;
                activityItems.forEach(item => {
                    item.hidden = tab.dataset.filter !== 'all' && item.dataset.type !== tab.dataset.filter;
                    if (!item.hidden) visibleCount++;
                });
                const empty = document.getElementById('activityEmpty');
                empty.hidden = visibleCount > 0;
                empty.textContent = tab.dataset.filter === 'all' ? 'No recent activity found.' : 'No recent ' + tab.textContent.toLowerCase() + ' in this activity list.';
            });
        });

        // Count the summary values into view without changing the server-rendered
        // totals. Reduced-motion users receive the final values immediately.
        (function animateStatNumbers() {
            const counters = document.querySelectorAll('.stat-number[data-value]');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const format = value => Math.round(value).toLocaleString();

            counters.forEach(counter => {
                const target = Number(counter.dataset.value) || 0;
                if (reducedMotion || target === 0) {
                    counter.textContent = format(target);
                    return;
                }

                const duration = 950;
                const startedAt = performance.now();
                const tick = now => {
                    const progress = Math.min((now - startedAt) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    counter.textContent = format(target * eased);
                    if (progress < 1) {
                        window.requestAnimationFrame(tick);
                    } else {
                        counter.textContent = format(target);
                    }
                };
                window.requestAnimationFrame(tick);
            });
        }());

        // Reuse the existing server datasets. Charts never delay the visible totals.
        (function renderDashboardCharts() {
            if (typeof Chart === 'undefined') {
                document.querySelector('.chart-data-details').open = true;
                return;
            }
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const colors = ['#2563eb', '#7c3aed', '#64748b', '#d97706'];
            const tints = ['rgba(37,99,235,.06)', 'rgba(124,58,237,.05)', 'rgba(100,116,139,.04)', 'rgba(217,119,6,.04)'];
            const labels = <?php echo json_encode(array_column($categories, 'name')); ?>;
            const series = <?php echo json_encode(array_map(static fn($category) => array_column($monthly_chart_data, $category['key']), $categories)); ?>;
            const distribution = <?php echo json_encode(array_map(static fn($category) => $stats['total_' . $category['key']] ?? 0, $categories)); ?>;
            const tooltip = {
                backgroundColor: '#172033', titleColor: '#fff', bodyColor: '#e2e8f0',
                padding: 12, cornerRadius: 10, usePointStyle: true,
                titleFont: {family: 'Inter, Segoe UI, sans-serif', weight: '600'},
                bodyFont: {family: 'Inter, Segoe UI, sans-serif'}
            };
            new Chart(document.getElementById('monthlyTrendChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_chart_data, 'month')); ?>,
                    datasets: labels.map((label, index) => ({
                        label, data: series[index], borderColor: colors[index], backgroundColor: tints[index],
                        borderWidth: 2.5, tension: .3, fill: true, pointRadius: 3,
                        pointHoverRadius: 5, pointBackgroundColor: colors[index], pointBorderColor: '#fff', pointBorderWidth: 2
                    }))
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    animation: reducedMotion ? false : {duration: 900, easing: 'easeOutQuart'},
                    interaction: {intersect: false, mode: 'index'},
                    plugins: {
                        legend: {position: 'bottom', labels: {color: '#526078', usePointStyle: true, pointStyle: 'circle', boxWidth: 7, boxHeight: 7, padding: 16, font: {family: 'Inter, Segoe UI, sans-serif', size: 11}}},
                        tooltip
                    },
                    scales: {
                        y: {beginAtZero: true, border: {display: false}, ticks: {precision: 0, maxTicksLimit: 6, color: '#66758c', font: {size: 11}}, grid: {color: '#edf1f7', drawTicks: false}},
                        x: {border: {display: false}, ticks: {color: '#66758c', padding: 12, font: {size: 11}}, grid: {display: false}}
                    }
                }
            });
            if (distribution.some(value => value > 0)) {
                new Chart(document.getElementById('distributionChart'), {
                    type: 'doughnut',
                    data: {labels, datasets: [{data: distribution, backgroundColor: colors, borderWidth: 4, borderColor: '#fff', hoverOffset: 3}]},
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '76%',
                        animation: reducedMotion ? false : {duration: 1000, easing: 'easeOutQuart'},
                        plugins: {
                            legend: {display: false},
                            tooltip: {...tooltip, callbacks: {label(context) {
                                const total = distribution.reduce((sum, value) => sum + value, 0);
                                return ' ' + context.label + ': ' + context.parsed.toLocaleString() + ' (' + (context.parsed / total * 100).toFixed(1) + '%)';
                            }}}
                        }
                    }
                });
            }
        }());

        // Calendar Month Navigation. The month changes in place so the dashboard
        // stays in context and the rest of the widgets keep their current state.
        let calendarState = {
            month: <?php echo (int) $current_month; ?>,
            year: <?php echo (int) $current_year; ?>
        };
        let calendarLoading = false;

        function bindCalendarDayCells() {
            document.querySelectorAll('#calendarGrid .calendar-day-cell:not(.empty)').forEach(cell => {
                cell.setAttribute('tabindex', '0');
                cell.setAttribute('role', 'button');
                cell.addEventListener('click', () => {
                    const date = cell.dataset.date;
                    if (cell.classList.contains('has-event')) {
                        viewEventsForDate(date);
                    } else {
                        openEventModal(date);
                    }
                });
                cell.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        cell.click();
                    }
                });
            });
        }

        function renderCalendar(month, year, eventsByDate) {
            const grid = document.getElementById('calendarGrid');
            const title = document.getElementById('calendarMonthYear');
            if (!grid || !title) return;

            title.textContent = new Intl.DateTimeFormat(undefined, {month: 'long', year: 'numeric'}).format(new Date(year, month - 1, 1));
            const firstDay = new Date(year, month - 1, 1).getDay();
            const dayCount = new Date(year, month, 0).getDate();
            const today = new Date();
            const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            let html = '';

            for (let index = 0; index < firstDay; index++) {
                html += '<div class="calendar-day-cell empty"></div>';
            }
            for (let day = 1; day <= dayCount; day++) {
                const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const hasEvents = Object.prototype.hasOwnProperty.call(eventsByDate, date);
                const classes = ['calendar-day-cell'];
                if (date === todayKey) classes.push('today');
                if (hasEvents) classes.push('has-event');
                const count = hasEvents ? Number(eventsByDate[date]) || 0 : 0;
                html += `<div class="${classes.join(' ')}" data-date="${date}">`;
                if (hasEvents && count > 0) {
                    html += `<span class="calendar-event-count" title="${count} event(s)">${count}</span>`;
                }
                html += `<span class="day-number">${day}</span></div>`;
            }
            const remainder = (7 - ((firstDay + dayCount) % 7)) % 7;
            for (let index = 0; index < remainder; index++) {
                html += '<div class="calendar-day-cell empty"></div>';
            }
            grid.innerHTML = html;
            bindCalendarDayCells();
        }

        function navigateMonth(direction) {
            if (calendarLoading) return;
            let month = calendarState.month + Number(direction);
            let year = calendarState.year;
            if (month > 12) { month = 1; year++; }
            if (month < 1) { month = 12; year--; }

            const monthText = String(month).padStart(2, '0');
            const startDate = `${year}-${monthText}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            const endDate = `${year}-${monthText}-${String(lastDay).padStart(2, '0')}`;
            const buttons = document.querySelectorAll('.calendar-nav-btn');
            calendarLoading = true;
            buttons.forEach(button => { button.disabled = true; });
            window.history.replaceState({}, '', `?month=${month}&year=${year}`);

            fetch(`../api/calendar_events.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`, {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(response => response.ok ? response.json() : Promise.reject(new Error('Calendar request failed')))
                .then(payload => {
                    const eventsByDate = {};
                    (payload.events || []).forEach(event => {
                        if (event.event_date) eventsByDate[event.event_date] = (eventsByDate[event.event_date] || 0) + 1;
                    });
                    renderCalendar(month, year, eventsByDate);
                    calendarState = {month, year};
                })
                .catch(() => {
                    // Keep the current month visible if the calendar endpoint is unavailable.
                })
                .finally(() => {
                    calendarLoading = false;
                    buttons.forEach(button => { button.disabled = false; });
                });
        }

        bindCalendarDayCells();

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

        // Open a compact detail view for a dashboard note. The full notes list
        // remains available through "View All Notes" below the widget.
        function openNoteDetailModal(noteElement) {
            if (!noteElement) return;
            const modal = document.getElementById('noteDetailModal');
            if (!modal) return;
            document.getElementById('noteDetailTitle').textContent = noteElement.dataset.noteTitle || 'Note';
            document.getElementById('noteDetailType').textContent = noteElement.dataset.noteType || 'Note';
            document.getElementById('noteDetailAuthor').textContent = noteElement.dataset.noteAuthor || 'Unknown';
            document.getElementById('noteDetailDate').textContent = noteElement.dataset.noteCreatedAt || '';
            document.getElementById('noteDetailContent').textContent = noteElement.dataset.noteContent || '';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.modal-close')?.focus();
        }

        function closeNoteDetailModal() {
            const modal = document.getElementById('noteDetailModal');
            if (!modal) return;
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = 'auto';
        }

        document.querySelectorAll('.note-item[data-note-id]').forEach(note => {
            note.addEventListener('click', (event) => {
                if (event.target.closest('a, button')) return;
                openNoteDetailModal(note);
            });
            note.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openNoteDetailModal(note);
                }
            });
        });

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
                                    <button class="event-action-btn edit" onclick="editEvent(${Number(event.id) || 0})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="event-action-btn delete" onclick="confirmDeleteEvent(${Number(event.id)})">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            <div class="event-list-item-meta">
                                <span class="event-type-badge">${escapeHtml(String(event.event_type || ''))}</span>
                                <span class="event-priority-badge ${escapeHtml(String(event.priority || 'medium').replace(/[^a-z-]/gi, ''))}">${escapeHtml(String(event.priority || ''))}</span>
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
        async function confirmDeleteEvent(eventId, eventTitle = '') {
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
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + escapeHtml(data.message || 'Saved successfully');

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
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + escapeHtml(error.message || 'Request failed');

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
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + escapeHtml(data.message || 'Saved successfully');

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
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + escapeHtml(error.message || 'Request failed');

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
                    messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + escapeHtml(data.message || 'Saved successfully');

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
                messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + escapeHtml(error.message || 'Request failed');

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

    </main>

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

    <!-- Note Detail Modal -->
    <div class="modal-overlay" id="noteDetailModal" aria-hidden="true">
        <div class="modal-container note-detail-modal" role="dialog" aria-modal="true" aria-labelledby="noteDetailTitle">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-note-sticky" aria-hidden="true"></i>
                    <span id="noteDetailTitle">Note</span>
                </h2>
                <button class="modal-close" onclick="closeNoteDetailModal()" aria-label="Close note details">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="note-detail-meta">
                    <span class="note-type-badge" id="noteDetailType">Note</span>
                    <span><i class="fas fa-user" aria-hidden="true"></i> <span id="noteDetailAuthor">Unknown</span></span>
                    <span><i class="fas fa-clock" aria-hidden="true"></i> <span id="noteDetailDate"></span></span>
                </div>
                <div class="note-detail-content" id="noteDetailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeNoteDetailModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
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

        const noteDetailModalEl = document.getElementById('noteDetailModal');
        if (noteDetailModalEl) {
            noteDetailModalEl.addEventListener('click', (e) => {
                if (e.target.id === 'noteDetailModal') {
                    closeNoteDetailModal();
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
                const noteDetailModal = document.getElementById('noteDetailModal');
                const viewEventsModal = document.getElementById('viewEventsModal');
                const allEventsModal = document.getElementById('allEventsModal');
                const allNotesModal = document.getElementById('allNotesModal');

                if (eventModal && eventModal.classList.contains('active')) {
                    closeEventModal();
                }
                if (noteModal && noteModal.classList.contains('active')) {
                    closeNoteModal();
                }
                if (noteDetailModal && noteDetailModal.classList.contains('active')) {
                    closeNoteDetailModal();
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
                                <button class="all-item-action-btn" onclick="closeAllEventsModal(); editEvent(${Number(event.id) || 0});">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="all-item-action-btn delete" onclick="confirmDeleteEvent(${Number(event.id)})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="all-item-meta">
                            <span class="event-type-badge">${escapeHtml(String(event.event_type || ''))}</span>
                            <span class="event-priority-badge ${escapeHtml(String(event.priority || 'medium').replace(/[^a-z-]/gi, ''))}">${escapeHtml(String(event.priority || ''))}</span>
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
                                <button class="all-item-action-btn pin" onclick="togglePinNote(${Number(note.id) || 0})" title="${isPinned ? 'Unpin' : 'Pin to dashboard'}">
                                    <i class="fas fa-thumbtack"></i> ${isPinned ? 'Unpin' : 'Pin'}
                                </button>
                                <button class="all-item-action-btn" onclick="closeAllNotesModal(); editNote(${Number(note.id) || 0});">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="all-item-action-btn delete" onclick="confirmDeleteNote(${Number(note.id)})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="all-note-content-preview">${escapeHtml(contentPreview)}</div>
                        <div class="all-item-meta">
                            <span class="note-type-badge">${escapeHtml(String(note.note_type || '').replace('_', ' '))}</span>
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
        async function confirmDeleteNote(noteId) {
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
