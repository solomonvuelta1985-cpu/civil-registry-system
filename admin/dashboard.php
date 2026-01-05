<?php
/**
 * Dashboard - Civil Registry Document Management System (CRDMS)
 * Main admin dashboard with analytics and statistics
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Optional: Check if user is authenticated
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../public/login.php');
//     exit;
// }

// $pdo is now available from config.php

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
    // Get total birth certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active'");
    $stats['total_births'] = $stmt->fetch()['count'] ?? 0;

    // Get total marriage certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active'");
    $stats['total_marriages'] = $stmt->fetch()['count'] ?? 0;

    // Get total death certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active'");
    $stats['total_deaths'] = $stmt->fetch()['count'] ?? 0;

    // Get total marriage licenses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active'");
    $stats['total_licenses'] = $stmt->fetch()['count'] ?? 0;

    // Get this month's birth certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['this_month_births'] = (int)($result['count'] ?? 0);

    // Get this month's marriage certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['this_month_marriages'] = (int)($result['count'] ?? 0);

    // Get this month's death certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['this_month_deaths'] = (int)($result['count'] ?? 0);

    // Get this month's marriage licenses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['this_month_licenses'] = (int)($result['count'] ?? 0);

    // Get last month's statistics for trend
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['last_month_births'] = (int)($result['count'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['last_month_marriages'] = (int)($result['count'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['last_month_deaths'] = (int)($result['count'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['last_month_licenses'] = (int)($result['count'] ?? 0);

    // Calculate trends
    $stats['birth_trend'] = $stats['last_month_births'] > 0
        ? round((($stats['this_month_births'] - $stats['last_month_births']) / $stats['last_month_births']) * 100)
        : ($stats['this_month_births'] > 0 ? 100 : 0);

    $stats['marriage_trend'] = $stats['last_month_marriages'] > 0
        ? round((($stats['this_month_marriages'] - $stats['last_month_marriages']) / $stats['last_month_marriages']) * 100)
        : ($stats['this_month_marriages'] > 0 ? 100 : 0);

    $stats['death_trend'] = $stats['last_month_deaths'] > 0
        ? round((($stats['this_month_deaths'] - $stats['last_month_deaths']) / $stats['last_month_deaths']) * 100)
        : ($stats['this_month_deaths'] > 0 ? 100 : 0);

    $stats['license_trend'] = $stats['last_month_licenses'] > 0
        ? round((($stats['this_month_licenses'] - $stats['last_month_licenses']) / $stats['last_month_licenses']) * 100)
        : ($stats['this_month_licenses'] > 0 ? 100 : 0);

    // Get monthly data for chart (last 6 months)
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_label = date('M', strtotime("-$i months"));

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $births = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $marriages = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM certificate_of_death WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $deaths = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM application_for_marriage_license WHERE status = 'Active' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$month]);
        $licenses = $stmt->fetch()['count'] ?? 0;

        $monthly_chart_data[] = [
            'month' => $month_label,
            'births' => $births,
            'marriages' => $marriages,
            'deaths' => $deaths,
            'licenses' => $licenses
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

    // Get archival/digitization progress statistics
    $archival_stats = [
        'total_legacy_records' => 0,
        'digitized_records' => 0,
        'pending_digitization' => 0,
        'digitization_percentage' => 0
    ];

    // Count total records
    $total_records = $stats['total_births'] + $stats['total_marriages'] + $stats['total_deaths'] + $stats['total_licenses'];

    // Count digitized records (those with PDFs uploaded)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT certificate_id) as count
        FROM pdf_attachments
        WHERE certificate_type = 'birth' AND is_current_version = 1
    ");
    $digitized_births = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT certificate_id) as count
        FROM pdf_attachments
        WHERE certificate_type = 'marriage' AND is_current_version = 1
    ");
    $digitized_marriages = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT certificate_id) as count
        FROM pdf_attachments
        WHERE certificate_type = 'death' AND is_current_version = 1
    ");
    $digitized_deaths = $stmt->fetch()['count'] ?? 0;

    $archival_stats['digitized_records'] = $digitized_births + $digitized_marriages + $digitized_deaths;
    $archival_stats['total_legacy_records'] = $total_records;
    $archival_stats['pending_digitization'] = $total_records - $archival_stats['digitized_records'];
    $archival_stats['digitization_percentage'] = $total_records > 0
        ? round(($archival_stats['digitized_records'] / $total_records) * 100, 1)
        : 0;

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        :root {

            /* Material Design 3 - Dynamic Color System */
            --md-sys-color-primary: #6750A4;
            --md-sys-color-on-primary: #FFFFFF;
            --md-sys-color-primary-container: #EADDFF;
            --md-sys-color-on-primary-container: #21005D;

            --md-sys-color-secondary: #625B71;
            --md-sys-color-on-secondary: #FFFFFF;
            --md-sys-color-secondary-container: #E8DEF8;
            --md-sys-color-on-secondary-container: #1D192B;

            --md-sys-color-surface: #FFFBFE;
            --md-sys-color-surface-variant: #E7E0EC;
            --md-sys-color-on-surface: #1C1B1F;
            --md-sys-color-on-surface-variant: #49454F;

            --md-sys-color-background: #FFFBFE;
            --md-sys-color-on-background: #1C1B1F;

            --md-sys-color-error: #B3261E;
            --md-sys-color-on-error: #FFFFFF;

            --md-sys-color-outline: #79747E;
            --md-sys-color-outline-variant: #CAC4D0;

            /* Semantic Colors */
            --color-success: #2E7D32;
            --color-success-container: #C8E6C9;
            --color-warning: #F57C00;
            --color-warning-container: #FFE0B2;
            --color-info: #0288D1;
            --color-info-container: #B3E5FC;

            /* Elevation - Material Design 3 */
            --md-sys-elevation-1: 0px 1px 2px rgba(0, 0, 0, 0.3), 0px 1px 3px 1px rgba(0, 0, 0, 0.15);
            --md-sys-elevation-2: 0px 1px 2px rgba(0, 0, 0, 0.3), 0px 2px 6px 2px rgba(0, 0, 0, 0.15);
            --md-sys-elevation-3: 0px 4px 8px 3px rgba(0, 0, 0, 0.15), 0px 1px 3px rgba(0, 0, 0, 0.3);
            --md-sys-elevation-4: 0px 6px 10px 4px rgba(0, 0, 0, 0.15), 0px 2px 3px rgba(0, 0, 0, 0.3);
            --md-sys-elevation-5: 0px 8px 12px 6px rgba(0, 0, 0, 0.15), 0px 4px 4px rgba(0, 0, 0, 0.3);

            /* Shape */
            --md-sys-shape-corner-none: 0px;
            --md-sys-shape-corner-extra-small: 4px;
            --md-sys-shape-corner-small: 8px;
            --md-sys-shape-corner-medium: 12px;
            --md-sys-shape-corner-large: 16px;
            --md-sys-shape-corner-extra-large: 28px;

            /* Typography Scale */
            --md-sys-typescale-display-large: 57px;
            --md-sys-typescale-headline-large: 32px;
            --md-sys-typescale-headline-medium: 28px;
            --md-sys-typescale-title-large: 22px;
            --md-sys-typescale-title-medium: 16px;
            --md-sys-typescale-body-large: 16px;
            --md-sys-typescale-body-medium: 14px;
            --md-sys-typescale-label-large: 14px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f9fafb;
            color: #1f2937;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
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

        /* Search & Filter Bar */
        .search-filter-bar {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 20px;
            margin-bottom: 28px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .date-range-selector {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
        }

        .date-range-label {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-right: 4px;
        }

        .date-range-btn {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .date-range-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .date-range-btn.active {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            background-color: #f8fafc;
            color: #0f172a;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: #6750A4;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(103, 80, 164, 0.1);
        }

        .search-box input::placeholder {
            color: #94a3b8;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 0.9375rem;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 10px 18px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            background-color: #ffffff;
            color: #64748b;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .filter-chip.active {
            background-color: #6750A4;
            border-color: #6750A4;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(103, 80, 164, 0.2);
        }

        /* Header */
        .dashboard-header {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
        }

        .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title h1 i {
            color: #6750A4;
        }

        .header-title p {
            color: #64748b;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background-color: #6750A4;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #5a47a1;
        }

        .btn-success {
            background-color: #2E7D32;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #27692b;
        }

        .btn-warning {
            background-color: #FF9800;
            color: #ffffff;
        }

        .btn-warning:hover {
            background-color: #f57c00;
        }

        .btn-info {
            background-color: #0288D1;
            color: #ffffff;
        }

        .btn-info:hover {
            background-color: #0277bd;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 24px;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 12px 0 0 12px;
        }

        .stat-card.blue::before { background: #2196F3; }
        .stat-card.green::before { background: #4CAF50; }
        .stat-card.purple::before { background: #9C27B0; }
        .stat-card.red::before { background: #E91E63; }
        .stat-card.orange::before { background: #FF9800; }
        .stat-card.indigo::before { background: #673AB7; }
        .stat-card.gray::before { background: #607D8B; }
        .stat-card.teal::before { background: #009688; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            flex-shrink: 0;
        }

        .stat-card.blue .stat-icon {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        .stat-card.green .stat-icon {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        .stat-card.purple .stat-icon {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9C27B0;
        }
        .stat-card.red .stat-icon {
            background-color: rgba(233, 30, 99, 0.1);
            color: #E91E63;
        }
        .stat-card.orange .stat-icon {
            background-color: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }
        .stat-card.indigo .stat-icon {
            background-color: rgba(103, 58, 183, 0.1);
            color: #673AB7;
        }
        .stat-card.gray .stat-icon {
            background-color: rgba(96, 125, 139, 0.1);
            color: #607D8B;
        }
        .stat-card.teal .stat-icon {
            background-color: rgba(0, 150, 136, 0.1);
            color: #009688;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stat-label-info {
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #e0e7ff;
            color: #6366f1;
            border-radius: 50%;
            font-size: 10px;
            cursor: help;
            position: relative;
        }

        .stat-label-info:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            background-color: #0f172a;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-label-info:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #0f172a;
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
            font-size: 0.8125rem;
            font-weight: 600;
            margin-top: 10px;
            padding: 4px 8px;
            border-radius: 6px;
            background-color: #f8fafc;
        }

        .stat-trend.up {
            color: #047857;
            background-color: #ecfdf5;
        }

        .stat-trend.down {
            color: #dc2626;
            background-color: #fef2f2;
        }

        .stat-trend.neutral {
            color: #64748b;
            background-color: #f1f5f9;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .chart-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chart-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .chart-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .chart-subtitle {
            color: #64748b;
            font-size: 0.875rem;
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
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: #6750A4;
            color: #ffffff;
            border: none;
            box-shadow: 0 6px 20px rgba(103, 80, 164, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fab-main:hover {
            background-color: #5a47a1;
            transform: scale(1.08);
            box-shadow: 0 8px 24px rgba(103, 80, 164, 0.5);
        }

        .fab-main:active {
            transform: scale(0.95);
        }

        .fab-main.active {
            transform: rotate(45deg);
            background-color: #dc2626;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }

        .fab-main.active:hover {
            background-color: #b91c1c;
            transform: rotate(45deg) scale(1.08);
            box-shadow: 0 8px 24px rgba(220, 38, 38, 0.5);
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
            background-color: #0f172a;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
            letter-spacing: -0.01em;
        }

        .fab-button {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }

        .fab-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .fab-button:active {
            transform: scale(0.95);
        }

        .fab-button.primary {
            background-color: #6750A4;
            color: #ffffff;
        }

        .fab-button.success {
            background-color: #2E7D32;
            color: #ffffff;
        }

        .fab-button.warning {
            background-color: #FF9800;
            color: #ffffff;
        }

        .fab-button.info {
            background-color: #0288D1;
            color: #ffffff;
        }

        .fab-button.secondary {
            background-color: #625B71;
            color: #ffffff;
        }

        /* Recent Activity */
        .activity-section {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .activity-section:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .activity-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .activity-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }

        .activity-title i {
            color: #6750A4;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .activity-item:hover {
            background-color: #fafbfc;
            margin: 0 -12px;
            padding: 16px 12px;
            border-radius: 8px;
            transform: translateX(4px);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-user-info {
            font-size: 0.75rem;
            color: #64748b;
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
            background: #f0fdf4;
            color: #166534;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .activity-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .activity-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.125rem;
        }

        .activity-icon.birth {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .activity-icon.marriage {
            background-color: rgba(233, 30, 99, 0.1);
            color: #E91E63;
        }

        .activity-icon.death {
            background-color: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .activity-icon.license {
            background-color: rgba(103, 58, 183, 0.1);
            color: #673AB7;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-name {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            font-size: 0.9375rem;
            letter-spacing: -0.01em;
        }

        .activity-meta {
            font-size: 0.8125rem;
            color: #64748b;
            font-weight: 500;
        }

        .activity-time {
            font-size: 0.8125rem;
            color: #94a3b8;
            white-space: nowrap;
            font-weight: 600;
            padding: 4px 10px;
            background-color: #f8fafc;
            border-radius: 6px;
        }

        /* System Highlights Alert Strip - Clean Design */
        .system-highlights {
            background: #fbbf24;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: none;
            border: none;
        }

        .highlights-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0;
        }

        .highlights-header i {
            color: #ffffff;
            font-size: 1rem;
        }

        .highlights-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #000000;
            letter-spacing: 0;
        }

        .highlights-grid {
            display: none;
        }

        /* Archival Progress Module - Clean Solid Blue */
        .archival-progress-card {
            background: #3b82f6;
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            box-shadow: none;
            margin-bottom: 24px;
            border: none;
        }

        .archival-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .archival-header i {
            font-size: 1.125rem;
        }

        .archival-title {
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0;
        }

        .archival-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .archival-stat-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 12px;
            backdrop-filter: none;
            border: none;
        }

        .archival-stat-label {
            font-size: 0.6875rem;
            opacity: 0.85;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 500;
        }

        .archival-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1;
        }

        .archival-progress-bar {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            height: 10px;
            overflow: hidden;
            margin-top: 6px;
        }

        .archival-progress-fill {
            background: #ffffff;
            height: 100%;
            border-radius: 6px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: none;
        }

        .archival-progress-text {
            font-size: 0.8125rem;
            margin-top: 10px;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Security Status Card - Clean White Design */
        .security-status-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: none;
            margin-bottom: 24px;
        }

        .security-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 0;
            border-bottom: none;
        }

        .security-header i {
            font-size: 1rem;
            color: #3b82f6;
        }

        .security-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: 0;
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
            padding: 12px;
            background-color: #f8fafc;
            border-radius: 6px;
            border: none;
            transition: all 0.2s ease;
        }

        .security-item:hover {
            background-color: #f1f5f9;
        }

        .security-item.warning-border {
            border: none;
            background-color: #fef3c7;
        }

        .security-icon {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.125rem;
        }

        .security-icon.success {
            background-color: #dcfce7;
            color: #22c55e;
        }

        .security-icon.info {
            background-color: #dbeafe;
            color: #3b82f6;
        }

        .security-icon.warning {
            background-color: #fef3c7;
            color: #fbbf24;
        }

        .security-content {
            flex: 1;
            min-width: 0;
        }

        .security-label {
            font-size: 0.6875rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 3px;
        }

        .security-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .security-value.text-warning {
            color: #f59e0b;
        }

        /* Calendar & Notes Section - Clean Design */
        .calendar-notes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .calendar-widget {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .notes-widget {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            color: #1e293b;
        }

        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .widget-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #0f172a;
        }

        .widget-title i {
            color: #0ea5e9;
            font-size: 1rem;
        }

        .widget-action-btn {
            padding: 6px 12px;
            background: #0ea5e9;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .widget-action-btn:hover {
            background: #0284c7;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25);
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
            padding: 14px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .event-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        .event-item.priority-high {
            border-left-color: #ef4444;
        }

        .event-item.priority-urgent {
            border-left-color: #dc2626;
            background: #fef2f2;
        }

        .event-item.priority-medium {
            border-left-color: #f59e0b;
        }

        .event-item.priority-low {
            border-left-color: #64748b;
        }

        .event-date-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50px;
            min-width: 50px;
            height: 50px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .event-month {
            font-size: 0.625rem;
            font-weight: 700;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-day {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .event-content {
            flex: 1;
            min-width: 0;
        }

        .event-title {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
            font-size: 0.9375rem;
        }

        .event-meta {
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .event-type-badge {
            padding: 2px 8px;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
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
            padding: 12px 16px;
            background: #ffffff;
            border-radius: 10px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .calendar-month-year {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .calendar-nav-btn {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            color: #0ea5e9;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .calendar-nav-btn:hover {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25);
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
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            font-size: 0.9375rem;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            position: relative;
            font-weight: 600;
            color: #1e293b;
            min-height: 50px;
            width: 100%;
            max-width: 50px;
            margin: 0 auto;
        }

        .calendar-day-cell:hover:not(.empty):not(.today) {
            background: #e0f2fe;
            border-color: #0ea5e9;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
            transform: scale(1.1);
        }

        .calendar-day-cell.empty {
            background: transparent;
            border: none;
            cursor: default;
        }

        .calendar-day-cell.today {
            background: #0ea5e9;
            color: #ffffff;
            border-color: #0284c7;
            font-weight: 700;
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
        }

        .calendar-day-cell.today:hover {
            background: #0284c7;
            box-shadow: 0 8px 24px rgba(14, 165, 233, 0.5);
            transform: scale(1.15);
        }

        .calendar-day-cell.has-event {
            background: #f0f9ff;
            border-color: #0ea5e9;
            border-width: 2.5px;
        }

        .calendar-day-cell.has-event:hover {
            background: #e0f2fe;
            border-color: #0284c7;
        }

        .calendar-day-cell.today.has-event {
            background: #0ea5e9;
            border-color: #0284c7;
        }

        .calendar-event-count {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #0ea5e9;
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
            box-shadow: 0 2px 6px rgba(14, 165, 233, 0.4);
            border: 2px solid #ffffff;
        }

        .calendar-day-cell.today .calendar-event-count {
            background: #fbbf24;
            color: #1e293b;
            border-color: #0ea5e9;
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
            background: #3b82f6;
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
            padding: 12px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .note-item:hover {
            background: #e0f2fe;
            border-color: #0ea5e9;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.15);
        }

        .note-item.pinned {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .note-item.pinned:hover {
            background: #fde68a;
            border-color: #f59e0b;
        }

        .note-item.pinned::before {
            content: '\f08d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 10px;
            color: #f59e0b;
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
            color: #0f172a;
            font-size: 0.875rem;
            flex: 1;
            padding-right: 20px;
        }

        .note-content-preview {
            font-size: 0.75rem;
            color: #64748b;
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
            color: #64748b;
            flex-wrap: wrap;
        }

        .note-type-badge {
            padding: 3px 8px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 6px;
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
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

        /* Chart Insights */
        .chart-insight {
            background-color: #f0f9ff;
            border-left: 3px solid #3b82f6;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .chart-insight-text {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.875rem;
            color: #1e40af;
            font-weight: 500;
            line-height: 1.5;
        }

        .chart-insight-text i {
            color: #3b82f6;
            font-size: 1rem;
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
            background: #ffffff;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: #0ea5e9;
            font-size: 1.5rem;
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
            background: #0ea5e9;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.25);
        }

        .modal-btn-primary:hover {
            background: #0284c7;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);
            transform: translateY(-1px);
        }

        .modal-btn-danger {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
        }

        .modal-btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
            transform: translateY(-1px);
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
                padding: 20px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 20px;
            }

            .chart-container {
                height: 250px;
            }

            .chart-card {
                padding: 20px;
            }

            .activity-section {
                padding: 20px;
            }

            .quick-actions-fab {
                bottom: 20px;
                right: 20px;
            }

            .fab-main {
                width: 52px;
                height: 52px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="dashboard-container">
            <!-- Header -->
            <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user_first_name); ?>! Here's your civil registry overview.</p>
                </div>
                <div class="header-actions">
                    <a href="../public/certificate_of_live_birth.php" class="btn btn-primary">
                        <i class="fas fa-baby"></i> New Birth
                    </a>
                    <a href="../public/certificate_of_marriage.php" class="btn btn-success">
                        <i class="fas fa-ring"></i> New Marriage
                    </a>
                    <a href="../public/certificate_of_death.php" class="btn btn-warning">
                        <i class="fas fa-cross"></i> New Death
                    </a>
                    <a href="../public/application_for_marriage_license.php" class="btn btn-info">
                        <i class="fas fa-clipboard-check"></i> New License
                    </a>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="search-filter-bar" role="search" aria-label="Dashboard search and filters">
            <div class="search-box">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="text" id="dashboardSearch" placeholder="Search certificates, registry numbers, names..." aria-label="Search certificates">
            </div>
            <div class="filter-group" role="group" aria-label="Certificate type filters">
                <button class="filter-chip active" data-filter="all" aria-pressed="true">
                    <i class="fas fa-layer-group" aria-hidden="true"></i> All
                </button>
                <button class="filter-chip" data-filter="birth" aria-pressed="false">
                    <i class="fas fa-baby" aria-hidden="true"></i> Birth
                </button>
                <button class="filter-chip" data-filter="marriage" aria-pressed="false">
                    <i class="fas fa-ring" aria-hidden="true"></i> Marriage
                </button>
                <button class="filter-chip" data-filter="death" aria-pressed="false">
                    <i class="fas fa-cross" aria-hidden="true"></i> Death
                </button>
                <button class="filter-chip" data-filter="license" aria-pressed="false">
                    <i class="fas fa-clipboard-check" aria-hidden="true"></i> License
                </button>
            </div>
            <div class="date-range-selector" role="group" aria-label="Date range filters">
                <span class="date-range-label">Period:</span>
                <button class="date-range-btn active" data-range="monthly" aria-pressed="true">Monthly</button>
                <button class="date-range-btn" data-range="quarterly" aria-pressed="false">Quarterly</button>
                <button class="date-range-btn" data-range="yearly" aria-pressed="false">Yearly</button>
            </div>
        </div>

        <!-- System Highlights Alert Strip -->
        <div class="system-highlights">
            <div class="highlights-header">
                <i class="fas fa-bell"></i>
                <span class="highlights-title">System Highlights - Requires Attention</span>
            </div>
            <div class="highlights-grid">
                <div class="highlight-item">
                    <div class="highlight-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-label">Total Records</div>
                        <div class="highlight-value"><?php echo number_format($stats['total_births'] + $stats['total_marriages'] + $stats['total_deaths'] + $stats['total_licenses']); ?></div>
                    </div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-label">This Month</div>
                        <div class="highlight-value"><?php echo number_format($stats['this_month_births'] + $stats['this_month_marriages'] + $stats['this_month_deaths'] + $stats['this_month_licenses']); ?></div>
                    </div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-label">Recent Activity</div>
                        <div class="highlight-value"><?php echo count($recent_activities); ?></div>
                    </div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon success">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="highlight-content">
                        <div class="highlight-label">System Status</div>
                        <div class="highlight-value" style="font-size: 1rem; color: #22c55e;">Active</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archival Progress Module -->
        <div class="archival-progress-card" role="region" aria-label="Digitization progress">
            <div class="archival-header">
                <i class="fas fa-archive" aria-hidden="true"></i>
                <h2 class="archival-title">Archival & Digitization Progress</h2>
            </div>
            <div class="archival-stats-grid">
                <div class="archival-stat-item">
                    <div class="archival-stat-label">Total Records</div>
                    <div class="archival-stat-value"><?php echo number_format($archival_stats['total_legacy_records']); ?></div>
                </div>
                <div class="archival-stat-item">
                    <div class="archival-stat-label">Digitized</div>
                    <div class="archival-stat-value"><?php echo number_format($archival_stats['digitized_records']); ?></div>
                </div>
                <div class="archival-stat-item">
                    <div class="archival-stat-label">Pending</div>
                    <div class="archival-stat-value"><?php echo number_format($archival_stats['pending_digitization']); ?></div>
                </div>
                <div class="archival-stat-item">
                    <div class="archival-stat-label">Progress</div>
                    <div class="archival-stat-value"><?php echo $archival_stats['digitization_percentage']; ?>%</div>
                </div>
            </div>
            <div class="archival-progress-bar" role="progressbar" aria-valuenow="<?php echo $archival_stats['digitization_percentage']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Digitization progress">
                <div class="archival-progress-fill" style="width: <?php echo $archival_stats['digitization_percentage']; ?>%"></div>
            </div>
            <p class="archival-progress-text">
                <?php if ($archival_stats['digitization_percentage'] == 100): ?>
                    <i class="fas fa-check-circle"></i> All records have been digitized!
                <?php elseif ($archival_stats['digitization_percentage'] >= 75): ?>
                    <i class="fas fa-thumbs-up"></i> Excellent progress! <?php echo $archival_stats['pending_digitization']; ?> records remaining.
                <?php elseif ($archival_stats['digitization_percentage'] >= 50): ?>
                    <i class="fas fa-chart-line"></i> Good progress! <?php echo $archival_stats['pending_digitization']; ?> records remaining.
                <?php elseif ($archival_stats['digitization_percentage'] > 0): ?>
                    <i class="fas fa-tasks"></i> Digitization in progress. <?php echo $archival_stats['pending_digitization']; ?> records remaining.
                <?php else: ?>
                    <i class="fas fa-info-circle"></i> Ready to start digitizing legacy records.
                <?php endif; ?>
            </p>
        </div>

        <!-- Security & System Status -->
        <div class="security-status-card" role="region" aria-label="Security and system status">
            <div class="security-header">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <h2 class="security-title">Security & System Status</h2>
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
                        <div class="security-value" style="font-size: 0.875rem; color: #22c55e;">Operational</div>
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
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
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
                    <a href="calendar.php" style="color: #3b82f6; font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                        View Full Calendar <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Notes Widget -->
            <div class="notes-widget" role="region" aria-label="System notes">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-sticky-note" aria-hidden="true"></i>
                        <span>Recent Notes</span>
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
                        <a href="notes.php" style="color: #3b82f6; font-size: 0.875rem; font-weight: 600; text-decoration: none;">
                            View All Notes <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                        <i class="fas fa-baby"></i>
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
                        <i class="fas fa-ring"></i>
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
                        <i class="fas fa-heart"></i>
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
                        <i class="fas fa-cross"></i>
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
                        <i class="fas fa-clipboard-check"></i>
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
                        <i class="fas fa-calendar-minus"></i>
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
                        <i class="fas fa-lightbulb"></i>
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
                        <i class="fas fa-chart-pie"></i>
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
                <h3 class="activity-title"><i class="fas fa-clock"></i> Recent Activity</h3>
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
                                    echo $activity['type'] === 'birth' ? 'baby' :
                                        ($activity['type'] === 'marriage' ? 'ring' :
                                        ($activity['type'] === 'death' ? 'cross' : 'clipboard-check'));
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
                    <i class="fas fa-baby"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Marriage Certificate</span>
                <a href="../public/certificate_of_marriage.php" class="fab-button success">
                    <i class="fas fa-ring"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Death Certificate</span>
                <a href="../public/certificate_of_death.php" class="fab-button warning">
                    <i class="fas fa-cross"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">New Marriage License</span>
                <a href="../public/application_for_marriage_license.php" class="fab-button info">
                    <i class="fas fa-clipboard-check"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">Generate Report</span>
                <a href="../admin/reports.php" class="fab-button secondary">
                    <i class="fas fa-file-pdf"></i>
                </a>
            </div>
        </div>
        <button class="fab-main" id="fabMain">
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
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#2196F3',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#2196F3',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Marriage Certificates',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'marriages')); ?>,
                        borderColor: '#E91E63',
                        backgroundColor: 'rgba(233, 30, 99, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#E91E63',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#E91E63',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Death Certificates',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'deaths')); ?>,
                        borderColor: '#FF9800',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#FF9800',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#FF9800',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Marriage Licenses',
                        data: <?php echo json_encode(array_column($monthly_chart_data, 'licenses')); ?>,
                        borderColor: '#673AB7',
                        backgroundColor: 'rgba(103, 58, 183, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#673AB7',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#673AB7',
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
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1C1B1F',
                        bodyColor: '#49454F',
                        borderColor: '#CAC4D0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        font: {
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
                            color: '#49454F'
                        },
                        grid: {
                            color: 'rgba(202, 196, 208, 0.3)',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            color: '#49454F'
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
                            '#2196F3',
                            '#E91E63',
                            '#FF9800'
                        ],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
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
                                color: '#1f2937'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(31, 41, 55, 0.95)',
                            titleColor: '#ffffff',
                            bodyColor: '#e5e7eb',
                            borderColor: '#374151',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            font: {
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
                const response = await fetch(`api/calendar_events.php?start_date=${date}&end_date=${date}`);
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
                const response = await fetch(`api/calendar_events.php?id=${eventId}`);
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
                    const response = await fetch('api/calendar_events.php', {
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
                const response = await fetch('api/calendar_events.php', {
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
                const response = await fetch('api/calendar_events.php', {
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

        // Submit Note Form via AJAX
        async function submitNoteForm(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('noteSubmitBtn');
            const messageDiv = document.getElementById('noteFormMessage');
            const originalBtnText = submitBtn.innerHTML;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

            const formData = {
                note_title: document.getElementById('note_title').value,
                note_type: document.getElementById('note_type').value,
                note_content: document.getElementById('note_content').value,
                is_pinned: document.getElementById('is_pinned').checked ? '1' : '0'
            };

            try {
                const response = await fetch('api/notes.php', {
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
                        closeNoteModal();
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to create note');
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

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const eventModal = document.getElementById('eventModal');
                const noteModal = document.getElementById('noteModal');
                const viewEventsModal = document.getElementById('viewEventsModal');

                if (eventModal && eventModal.classList.contains('active')) {
                    closeEventModal();
                }
                if (noteModal && noteModal.classList.contains('active')) {
                    closeNoteModal();
                }
                if (viewEventsModal && viewEventsModal.classList.contains('active')) {
                    closeViewEventsModal();
                }
            }
        });
    </script>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
