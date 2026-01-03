<?php
/**
 * Dashboard - Civil Registry Records Management System
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
    'this_month_births' => 0,
    'this_month_marriages' => 0,
    'last_month_births' => 0,
    'last_month_marriages' => 0,
    'birth_trend' => 0,
    'marriage_trend' => 0
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

    // Get this month's birth certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month_births'] = $stmt->fetch()['count'] ?? 0;

    // Get this month's marriage certificates
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['this_month_marriages'] = $stmt->fetch()['count'] ?? 0;

    // Get last month's statistics for trend
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_live_birth WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stats['last_month_births'] = $stmt->fetch()['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM certificate_of_marriage WHERE status = 'Active' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
    $stats['last_month_marriages'] = $stmt->fetch()['count'] ?? 0;

    // Calculate trends
    $stats['birth_trend'] = $stats['last_month_births'] > 0
        ? round((($stats['this_month_births'] - $stats['last_month_births']) / $stats['last_month_births']) * 100)
        : 0;

    $stats['marriage_trend'] = $stats['last_month_marriages'] > 0
        ? round((($stats['this_month_marriages'] - $stats['last_month_marriages']) / $stats['last_month_marriages']) * 100)
        : 0;

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

        $monthly_chart_data[] = [
            'month' => $month_label,
            'births' => $births,
            'marriages' => $marriages
        ];
    }

    // Get recent activities (both births and marriages combined)
    $recent_births = $pdo->query("
        SELECT 'birth' as type, registry_no, CONCAT(child_first_name, ' ', child_last_name) as name, created_at
        FROM certificate_of_live_birth
        WHERE status = 'Active'
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();

    $recent_marriages = $pdo->query("
        SELECT 'marriage' as type, registry_no, CONCAT(husband_first_name, ' ', husband_last_name, ' & ', wife_first_name, ' ', wife_last_name) as name, created_at
        FROM certificate_of_marriage
        WHERE status = 'Active'
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Merge and sort recent activities
    $recent_activities = array_merge($recent_births, $recent_marriages);
    usort($recent_activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_activities = array_slice($recent_activities, 0, 8);

    // Certificate distribution
    $certificate_distribution = [
        ['type' => 'Birth Certificates', 'count' => $stats['total_births']],
        ['type' => 'Marriage Certificates', 'count' => $stats['total_marriages']],
        ['type' => 'Death Certificates', 'count' => $stats['total_deaths']]
    ];

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

    <style>
        :root {
            /* Sidebar & Navigation */
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #051f3a;
            --sidebar-item-hover: rgba(59, 130, 246, 0.1);
            --sidebar-item-active: rgba(59, 130, 246, 0.2);
            --text-primary-nav: #f1f5f9;
            --text-secondary-nav: #94a3b8;
            --accent-color: #3b82f6;

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
            margin-left: 260px;
            padding: 84px 20px 20px 20px;
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
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
            background-color: #ffffff;
            color: #1f2937;
            transition: border-color 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #6750A4;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.875rem;
        }

        .filter-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            color: #6b7280;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-chip:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }

        .filter-chip.active {
            background-color: #6750A4;
            border-color: #6750A4;
            color: #ffffff;
        }

        /* Header */
        .dashboard-header {
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 24px;
            margin-bottom: 24px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .header-title p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
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

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 20px;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
        }

        .stat-card.blue::before { background: #2196F3; }
        .stat-card.green::before { background: #4CAF50; }
        .stat-card.purple::before { background: #9C27B0; }
        .stat-card.red::before { background: #E91E63; }
        .stat-card.orange::before { background: #FF9800; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
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

        .stat-number {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 8px;
        }

        .stat-trend.up {
            color: #059669;
        }

        .stat-trend.down {
            color: #dc2626;
        }

        .stat-trend.neutral {
            color: #6b7280;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .chart-card {
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 20px;
        }

        .chart-header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .chart-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Quick Actions Widget */
        .quick-actions-fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            z-index: 1000;
        }

        .fab-main {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background-color: #6750A4;
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: background-color 0.2s ease;
        }

        .fab-main:hover {
            background-color: #5a47a1;
        }

        .fab-main.active {
            transform: rotate(45deg);
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

        .fab-label {
            background-color: #1f2937;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
        }

        .fab-button {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            transition: opacity 0.2s ease;
            text-decoration: none;
        }

        .fab-button:hover {
            opacity: 0.9;
        }

        .fab-button.primary {
            background-color: #6750A4;
            color: #ffffff;
        }

        .fab-button.success {
            background-color: #2E7D32;
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
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 20px;
        }

        .activity-header {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .activity-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }

        .activity-icon.birth {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .activity-icon.marriage {
            background-color: rgba(233, 30, 99, 0.1);
            color: #E91E63;
        }

        .activity-content {
            flex: 1;
        }

        .activity-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
            font-size: 0.875rem;
        }

        .activity-meta {
            font-size: 0.8125rem;
            color: #6b7280;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #9ca3af;
            white-space: nowrap;
        }

        /* ========================================
           MOBILE HEADER
           ======================================== */
        .mobile-header {
            display: none;
            background: var(--sidebar-bg);
            color: var(--text-primary-nav);
            padding: 16px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }

        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
        }

        .mobile-header h4 [data-lucide] {
            color: var(--accent-color);
            margin-right: 10px;
        }

        #mobileSidebarToggle {
            background: none;
            border: none;
            color: var(--text-primary-nav);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        #mobileSidebarToggle:hover {
            background: rgba(59, 130, 246, 0.15);
            transform: scale(1.05);
        }

        /* ========================================
           SIDEBAR OVERLAY
           ======================================== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
            backdrop-filter: blur(2px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* ========================================
           TOP NAVBAR (DESKTOP)
           ======================================== */
        .top-navbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 64px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            padding: 0;
            z-index: 100;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed .top-navbar {
            left: var(--sidebar-collapsed-width);
        }

        #sidebarCollapse {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #374151;
            cursor: pointer;
            padding: 10px;
            margin-left: 20px;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #sidebarCollapse:hover {
            background: #f3f4f6;
            color: var(--accent-color);
            transform: scale(1.05);
        }

        .top-navbar-info {
            margin-left: 16px;
        }

        .welcome-text {
            color: #6b7280;
            font-size: 13.5px;
            font-weight: 500;
        }

        /* ========================================
           USER PROFILE DROPDOWN
           ======================================== */
        .user-profile-dropdown {
            margin-left: auto;
            margin-right: 20px;
            position: relative;
        }

        .user-profile-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px 6px 6px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .user-profile-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .user-profile-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #111827;
        }

        .user-role {
            font-size: 11.5px;
            color: #6b7280;
        }

        .dropdown-arrow {
            color: #9ca3af;
            transition: transform 0.2s ease;
        }

        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 280px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .user-dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: #374151;
            text-decoration: none;
            font-size: 13.5px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f9fafb;
        }

        .dropdown-item.logout-item {
            color: #dc2626;
        }

        /* ========================================
           SIDEBAR NAVIGATION
           ======================================== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--text-primary-nav);
            z-index: 1000;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            background: var(--sidebar-bg);
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
            min-height: 64px;
            display: flex;
            align-items: center;
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h4 [data-lucide] {
            color: var(--accent-color);
        }

        .sidebar-collapsed .sidebar-header h4 span {
            display: none;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 0;
            margin: 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }

        /* Custom scrollbar for webkit browsers */
        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.3);
            border-radius: 3px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background-color: rgba(148, 163, 184, 0.5);
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            margin: 4px 14px;
            color: var(--text-secondary-nav);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            gap: 12px;
        }

        .sidebar-menu li a:hover {
            background: var(--sidebar-item-hover);
            color: var(--text-primary-nav);
            transform: translateX(3px);
        }

        .sidebar-menu li a.active {
            background: var(--sidebar-item-active);
            color: var(--text-primary-nav);
        }

        .sidebar-menu li a [data-lucide] {
            width: 20px;
            height: 20px;
            min-width: 20px;
        }

        .sidebar-collapsed .sidebar-menu li a span {
            display: none;
        }

        .sidebar-heading {
            padding: 12px 18px 6px;
            margin: 12px 14px 4px;
            color: rgba(148, 163, 184, 0.6);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-collapsed .sidebar-heading {
            display: none;
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(148, 163, 184, 0.15);
            margin: 12px 20px;
        }

        .sidebar-collapsed .sidebar-divider {
            margin: 12px 14px;
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
            .mobile-header {
                display: block;
            }

            .top-navbar {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-collapsed .sidebar {
                width: 280px;
            }

            .content {
                margin-left: 0;
                padding: 90px 12px 12px 12px;
            }

            .sidebar-collapsed .content {
                margin-left: 0;
            }

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
                        <i class="fas fa-baby"></i> New Birth Certificate
                    </a>
                    <a href="../public/certificate_of_marriage.php" class="btn btn-success">
                        <i class="fas fa-ring"></i> New Marriage Certificate
                    </a>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="dashboardSearch" placeholder="Search certificates, registry numbers, names...">
            </div>
            <div class="filter-group">
                <button class="filter-chip active" data-filter="all">
                    <i class="fas fa-layer-group"></i> All
                </button>
                <button class="filter-chip" data-filter="birth">
                    <i class="fas fa-baby"></i> Birth
                </button>
                <button class="filter-chip" data-filter="marriage">
                    <i class="fas fa-ring"></i> Marriage
                </button>
                <button class="filter-chip" data-filter="death">
                    <i class="fas fa-cross"></i> Death
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Total Birth Certificates -->
            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_births']); ?></div>
                        <div class="stat-label">Total Birth Certificates</div>
                        <?php if ($stats['birth_trend'] != 0): ?>
                            <div class="stat-trend <?php echo $stats['birth_trend'] > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $stats['birth_trend'] > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs($stats['birth_trend']); ?>% from last month
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
                        <div class="stat-number"><?php echo number_format($stats['this_month_births']); ?></div>
                        <div class="stat-label">Births This Month</div>
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
                        <div class="stat-number"><?php echo number_format($stats['this_month_marriages']); ?></div>
                        <div class="stat-label">Marriages This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <!-- Total Death Certificates (placeholder) -->
            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo number_format($stats['total_deaths']); ?></div>
                        <div class="stat-label">Total Death Certificates</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-cross"></i>
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
                <ul class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon <?php echo $activity['type']; ?>">
                                <i class="fas fa-<?php echo $activity['type'] === 'birth' ? 'baby' : 'ring'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-name"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="activity-meta">
                                    <?php echo ucfirst($activity['type']); ?> Certificate &bull; Registry #<?php echo htmlspecialchars($activity['registry_no']); ?>
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
                <span class="fab-label">Search Records</span>
                <a href="../public/marriage_records.php" class="fab-button info">
                    <i class="fas fa-search"></i>
                </a>
            </div>
            <div class="fab-action">
                <span class="fab-label">Generate Report</span>
                <a href="#" class="fab-button secondary">
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

        // Filter Chips Functionality
        const filterChips = document.querySelectorAll('.filter-chip');
        const activityItems = document.querySelectorAll('.activity-item');

        filterChips.forEach(chip => {
            chip.addEventListener('click', () => {
                // Remove active class from all chips
                filterChips.forEach(c => c.classList.remove('active'));
                // Add active class to clicked chip
                chip.classList.add('active');

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
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
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
    </script>

    </div> <!-- Close .content -->

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
