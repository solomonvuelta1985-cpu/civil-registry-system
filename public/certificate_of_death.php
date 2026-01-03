<?php
/**
 * Certificate of Death - Entry Form (PHP Version)
 * Includes database connectivity and server-side processing
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permission - need create for new, edit for existing
$edit_mode_check = isset($_GET['id']) && !empty($_GET['id']);
$required_permission = $edit_mode_check ? 'death_edit' : 'death_create';
if (!hasPermission($required_permission)) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

// Get record ID if editing (optional)
$edit_mode = false;
$record = null;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $edit_mode = true;
    $record_id = sanitize_input($_GET['id']);

    // Fetch record from database
    try {
        $stmt = $pdo->prepare("SELECT * FROM certificate_of_death WHERE id = :id AND status = 'Active'");
        $stmt->execute([':id' => $record_id]);
        $record = $stmt->fetch();

        if (!$record) {
            $_SESSION['error'] = "Record not found.";
            header('Location: ../admin/dashboard.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "Error loading record.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Death Certificate - Civil Registry System</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* ========================================
           RESET & BASE STYLES
           ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f5f5f5;
            color: #212529;
            font-size: clamp(0.8rem, 1.5vw, 0.875rem);
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* ========================================
           CONTAINER & LAYOUT
           ======================================== */
        .page-container {
            max-width: 100%;
            margin: 0;
            background-color: #ffffff;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
        }

        /* ========================================
           MAIN LAYOUT WITH SIDENAV
           ======================================== */
        .app-container {
            display: flex;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        /* ========================================
           SIDEBAR & NAV VARS
           ======================================== */
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #051f3a;
            --sidebar-item-hover: rgba(59, 130, 246, 0.1);
            --sidebar-item-active: rgba(59, 130, 246, 0.2);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-color: #3b82f6;
        }

        /* ========================================
           MOBILE HEADER
           ======================================== */
        .mobile-header {
            display: none;
            background: var(--sidebar-bg);
            color: var(--text-primary);
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
        }

        .mobile-header h4 [data-lucide] {
            color: var(--accent-color);
            margin-right: 10px;
        }

        #mobileSidebarToggle {
            background: none;
            border: none;
            color: var(--text-primary);
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

        .user-profile-btn:active,
        .user-profile-btn.active {
            background: #f3f4f6;
            border-color: var(--accent-color);
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
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .user-avatar.large {
            width: 48px;
            height: 48px;
            font-size: 16px;
        }

        .user-profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }

        .user-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #111827;
            line-height: 1.3;
        }

        .user-role {
            font-size: 11.5px;
            color: #6b7280;
            line-height: 1.3;
            text-transform: capitalize;
        }

        .dropdown-arrow {
            color: #9ca3af;
            transition: transform 0.2s ease;
        }

        .user-profile-btn.active .dropdown-arrow {
            transform: rotate(180deg);
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

        .dropdown-header {
            padding: 20px;
        }

        .dropdown-user-info {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .dropdown-user-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 2px;
        }

        .dropdown-user-email {
            font-size: 12.5px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .dropdown-user-badge {
            display: inline-block;
            padding: 4px 10px;
            background: var(--accent-color);
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 600;
            border-radius: 6px;
        }

        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: #374151;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0 0 12px 12px;
        }

        .dropdown-item:hover {
            background: #f9fafb;
            color: #111827;
            padding-left: 24px;
        }

        .dropdown-item.logout-item {
            color: #dc2626;
        }

        .dropdown-item.logout-item:hover {
            background: #fef2f2;
            color: #b91c1c;
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
            color: var(--text-primary);
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
        }

        .sidebar-header h4 [data-lucide] {
            min-width: 28px;
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
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 3px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            margin: 4px 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
            gap: 12px;
        }

        .sidebar-menu li a:hover {
            background: var(--sidebar-item-hover);
            color: var(--text-primary);
            transform: translateX(3px);
        }

        .sidebar-menu li a.active {
            background: var(--sidebar-item-active);
            color: #b7ff9a;
            font-weight: 600;
            animation: menuItemActivate 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes menuItemActivate {
            0% { background: transparent; transform: translateX(-4px); }
            60% { transform: translateX(5px); }
            100% { background: var(--sidebar-item-active); transform: translateX(0); }
        }

        .sidebar-menu li a.active::before {
            content: '';
            position: absolute;
            left: -14px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 28px;
            background: var(--accent-color);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.6);
        }

        .sidebar-menu li a [data-lucide] {
            width: 20px;
            height: 20px;
            min-width: 20px;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .sidebar-menu li a:hover [data-lucide] {
            transform: scale(1.1);
        }

        .sidebar-collapsed .sidebar-menu li a {
            justify-content: center;
            padding: 14px 10px;
        }

        .sidebar-collapsed .sidebar-menu li a span {
            display: none;
        }

        .sidebar-collapsed .sidebar-menu li a.active::before {
            left: -10px;
        }

        .sidebar-divider {
            border-top: 1px solid rgba(148, 163, 184, 0.15);
            margin: 18px 16px;
        }

        .sidebar-heading {
            padding: 18px 20px 10px;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.08em;
        }

        .sidebar-collapsed .sidebar-heading {
            text-indent: -9999px;
            padding: 8px 0;
        }

        /* Tooltips for collapsed sidebar */
        .sidebar-collapsed .sidebar-menu li a::after {
            content: attr(title);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #0f172a;
            color: #f1f5f9;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1001;
            margin-left: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .sidebar-collapsed .sidebar-menu li a:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* ========================================
           MAIN CONTENT
           ======================================== */
        .content {
            margin-left: var(--sidebar-width);
            padding: 0;
            padding-top: 64px;
            min-height: 100vh;
            background: #ffffff;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed .content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* ========================================
           HEADER WITH LOGO
           ======================================== */
        .system-header {
            background: #495057;
            padding: clamp(12px, 2vw, 16px) clamp(15px, 2.5vw, 20px);
            margin: 0 0 clamp(15px, 2.5vw, 20px) 0;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: clamp(12px, 2vw, 18px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .system-logo {
            width: clamp(50px, 8vw, 70px);
            height: clamp(50px, 8vw, 70px);
            border-radius: 50%;
            background-color: #ffffff;
            padding: clamp(4px, 0.8vw, 6px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .system-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .system-title-container {
            flex: 1;
        }

        .system-title {
            font-size: clamp(0.95rem, 2vw, 1.15rem);
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            line-height: 1.3;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .system-subtitle {
            font-size: clamp(0.7rem, 1.3vw, 0.8rem);
            color: rgba(255, 255, 255, 0.9);
            margin-top: clamp(2px, 0.4vw, 4px);
            font-weight: 400;
        }

        .page-header {
            text-align: center;
            margin: 0 clamp(15px, 2.5vw, 20px) clamp(15px, 2.5vw, 20px) clamp(15px, 2.5vw, 20px);
            padding-bottom: clamp(10px, 2vw, 15px);
            border-bottom: 2px solid #dee2e6;
        }

        .page-title {
            font-size: clamp(1.1rem, 2.5vw, 1.35rem);
            font-weight: 600;
            color: #212529;
            margin-bottom: clamp(4px, 0.8vw, 6px);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(6px, 1.2vw, 10px);
        }

        .page-subtitle {
            font-size: clamp(0.75rem, 1.5vw, 0.85rem);
            color: #6c757d;
            font-weight: 400;
        }

        /* ========================================
           TWO COLUMN LAYOUT
           ======================================== */
        .form-layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: clamp(12px, 2vw, 20px);
            margin: 0 clamp(15px, 2.5vw, 20px);
            padding-bottom: clamp(15px, 2.5vw, 20px);
        }

        @media (max-width: 1024px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
        }

        .form-column {
            background-color: #ffffff;
        }

        .pdf-column {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: clamp(4px, 0.8vw, 6px);
            padding: clamp(12px, 2vw, 16px);
            position: sticky;
            top: clamp(8px, 1.5vw, 12px);
            height: fit-content;
            max-height: calc(100vh - 20px);
            overflow-y: auto;
        }

        /* ========================================
           FORM SECTIONS
           ======================================== */
        .form-section {
            margin-bottom: clamp(15px, 2.5vw, 20px);
        }

        .section-header {
            background-color: #f8f9fa;
            border-left: 3px solid #0d6efd;
            padding: clamp(8px, 1.5vw, 12px) clamp(10px, 1.8vw, 14px);
            margin-bottom: clamp(12px, 2vw, 16px);
            border-radius: clamp(3px, 0.6vw, 4px);
        }

        .section-title {
            font-size: clamp(0.9rem, 1.8vw, 1rem);
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: clamp(6px, 1.2vw, 8px);
        }

        .section-title svg {
            width: clamp(16px, 2vw, 18px);
            height: clamp(16px, 2vw, 18px);
            stroke: #0d6efd;
        }

        /* ========================================
           FORM GROUPS & INPUTS
           ======================================== */
        .form-group {
            margin-bottom: clamp(10px, 1.8vw, 14px);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(150px, 25vw, 180px), 1fr));
            gap: clamp(10px, 1.8vw, 14px);
            margin-bottom: clamp(10px, 1.8vw, 14px);
        }

        label {
            display: block;
            font-weight: 500;
            color: #495057;
            margin-bottom: clamp(4px, 0.8vw, 6px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
        }

        label .required {
            color: #dc3545;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: clamp(7px, 1.3vw, 9px) clamp(9px, 1.5vw, 11px);
            border: 1px solid #ced4da;
            border-radius: clamp(3px, 0.6vw, 4px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
            font-family: inherit;
            color: #212529;
            background-color: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="datetime-local"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        input[type="text"]:disabled,
        input[type="number"]:disabled,
        select:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        input[type="file"] {
            padding: clamp(6px, 1.2vw, 8px);
            border: 2px dashed #ced4da;
            border-radius: clamp(3px, 0.6vw, 4px);
            width: 100%;
            font-size: clamp(0.7rem, 1.3vw, 0.75rem);
            background-color: #f8f9fa;
            cursor: pointer;
            transition: border-color 0.15s;
        }

        input[type="file"]:hover {
            border-color: #0d6efd;
        }

        /* ========================================
           BUTTONS
           ======================================== */
        .button-group {
            display: flex;
            gap: clamp(8px, 1.5vw, 10px);
            margin-top: clamp(15px, 2.5vw, 20px);
            flex-wrap: wrap;
        }

        .sticky-buttons {
            position: sticky;
            bottom: 0;
            background: #ffffff;
            padding: clamp(15px, 2.5vw, 20px) 0;
            margin-top: clamp(20px, 3vw, 30px);
            z-index: 50;
            border-top: 2px solid #dee2e6;
        }

        .btn {
            padding: clamp(6px, 1.2vw, 8px) clamp(12px, 2vw, 15px);
            border-radius: clamp(3px, 0.6vw, 4px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
            font-weight: 500;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: clamp(5px, 1vw, 6px);
            text-decoration: none;
        }

        .btn svg {
            width: clamp(14px, 1.8vw, 16px);
            height: clamp(14px, 1.8vw, 16px);
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0b5ed7;
        }

        .btn-success {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #157347;
            border-color: #157347;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #5c636a;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000000;
        }

        .btn-warning:hover {
            background-color: #ffca2c;
            border-color: #ffca2c;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff;
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #bb2d3b;
        }

        /* ========================================
           PDF PREVIEW SECTION
           ======================================== */
        .pdf-preview-header {
            margin-bottom: clamp(10px, 1.8vw, 12px);
            padding-bottom: clamp(8px, 1.5vw, 10px);
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pdf-preview-title {
            font-size: clamp(0.85rem, 1.6vw, 0.95rem);
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: clamp(5px, 1vw, 6px);
        }

        .toggle-pdf-btn {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #495057;
        }

        .toggle-pdf-btn:hover {
            background: #f8f9fa;
            border-color: #0d6efd;
            color: #0d6efd;
        }

        .toggle-pdf-btn svg {
            width: 16px;
            height: 16px;
        }

        /* PDF Column hidden state */
        .pdf-column.hidden {
            display: none;
        }

        /* Form layout when PDF is hidden */
        .form-layout.pdf-hidden {
            grid-template-columns: 1fr;
        }

        /* Floating toggle button when PDF is hidden */
        .floating-toggle-btn {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #0d6efd;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-toggle-btn:hover {
            background: #0b5ed7;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 16px rgba(13, 110, 253, 0.4);
        }

        .floating-toggle-btn.show {
            display: flex;
        }

        .floating-toggle-btn svg {
            width: 24px;
            height: 24px;
        }

        .pdf-preview-title svg {
            width: clamp(16px, 2vw, 18px);
            height: clamp(16px, 2vw, 18px);
            stroke: #0d6efd;
        }

        /* Upload Scanner Container */
        .upload-scanner-container {
            display: flex;
            gap: clamp(8px, 1.5vw, 12px);
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .upload-scanner-container input[type="file"] {
            flex: 1;
            min-width: 200px;
        }

        .btn-scan {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #ffffff;
            border: none;
            padding: clamp(8px, 1.5vw, 10px) clamp(14px, 2.2vw, 18px);
            border-radius: clamp(4px, 0.8vw, 6px);
            font-size: clamp(0.75rem, 1.4vw, 0.875rem);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: clamp(5px, 1vw, 8px);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
            white-space: nowrap;
        }

        .btn-scan:hover {
            background: linear-gradient(135deg, #218838 0%, #1ab886 100%);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
            transform: translateY(-1px);
        }

        .btn-scan:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(40, 167, 69, 0.2);
        }

        .btn-scan svg {
            width: 18px;
            height: 18px;
        }

        .scan-status {
            margin-top: clamp(8px, 1.5vw, 10px);
            padding: clamp(8px, 1.5vw, 10px) clamp(12px, 2vw, 14px);
            border-radius: clamp(4px, 0.8vw, 6px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scan-status.scanning {
            background-color: #cfe2ff;
            border: 1px solid #6ea8fe;
            color: #084298;
        }

        .scan-status.success {
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            color: #0f5132;
        }

        .scan-status.error {
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #842029;
        }

        .pdf-upload-area {
            border: 2px dashed #ced4da;
            border-radius: clamp(4px, 0.8vw, 6px);
            padding: clamp(20px, 3vw, 30px);
            text-align: center;
            background-color: #ffffff;
            transition: all 0.15s;
            cursor: pointer;
        }

        .pdf-upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }

        .pdf-upload-area svg {
            width: clamp(40px, 6vw, 50px);
            height: clamp(40px, 6vw, 50px);
            stroke: #6c757d;
            margin-bottom: clamp(10px, 2vw, 15px);
        }

        .pdf-upload-text {
            color: #6c757d;
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
            margin-bottom: clamp(6px, 1.2vw, 8px);
        }

        .pdf-upload-hint {
            color: #adb5bd;
            font-size: clamp(0.7rem, 1.3vw, 0.75rem);
        }

        .pdf-preview-container {
            width: 100%;
            min-height: clamp(300px, 40vw, 400px);
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: clamp(4px, 0.8vw, 6px);
            overflow: hidden;
        }

        .pdf-preview-container iframe {
            width: 100%;
            height: clamp(400px, 50vw, 600px);
            border: none;
        }

        .pdf-info {
            margin-top: clamp(10px, 1.8vw, 12px);
            padding: clamp(8px, 1.5vw, 10px);
            background-color: #e7f1ff;
            border-left: 3px solid #0d6efd;
            border-radius: clamp(3px, 0.6vw, 4px);
            font-size: clamp(0.7rem, 1.3vw, 0.75rem);
        }

        .pdf-info svg {
            width: clamp(14px, 1.8vw, 16px);
            height: clamp(14px, 1.8vw, 16px);
            stroke: #084298;
            display: inline-block;
            vertical-align: middle;
            margin-right: clamp(4px, 0.8vw, 6px);
        }

        .pdf-filename {
            font-weight: 600;
            color: #084298;
            font-size: clamp(0.7rem, 1.3vw, 0.75rem);
            word-break: break-all;
        }

        /* ========================================
           ALERTS & NOTIFICATIONS
           ======================================== */
        .alert {
            padding: clamp(8px, 1.5vw, 11px) clamp(10px, 1.8vw, 13px);
            border-radius: clamp(3px, 0.6vw, 4px);
            margin-bottom: clamp(12px, 2vw, 16px);
            display: flex;
            align-items: center;
            gap: clamp(6px, 1.2vw, 8px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
        }

        .alert svg {
            width: clamp(16px, 2vw, 18px);
            height: clamp(16px, 2vw, 18px);
            flex-shrink: 0;
        }

        .alert-success {
            background-color: #d1e7dd;
            border-left: 3px solid #198754;
            color: #0f5132;
        }

        .alert-success svg {
            stroke: #0f5132;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-left: 3px solid #dc3545;
            color: #842029;
        }

        .alert-danger svg {
            stroke: #842029;
        }

        .alert-info {
            background-color: #cff4fc;
            border-left: 3px solid #0dcaf0;
            color: #055160;
        }

        .alert-info svg {
            stroke: #055160;
        }

        .alert-warning {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            color: #664d03;
        }

        .alert-warning svg {
            stroke: #664d03;
        }

        .hidden {
            display: none;
        }

        /* ========================================
           SKELETON LOADING STYLES
           ======================================== */
        .skeleton {
            background: linear-gradient(
                90deg,
                #E5E7EB 0%,
                #F3F4F6 50%,
                #E5E7EB 100%
            );
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
            border-radius: clamp(3px, 0.6vw, 4px);
            height: 20px;
            width: 100%;
        }

        @keyframes skeleton-loading {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        .skeleton-input {
            height: clamp(32px, 4vw, 38px);
            border-radius: clamp(3px, 0.6vw, 4px);
        }

        .skeleton-label {
            height: clamp(16px, 2vw, 18px);
            width: 40%;
            margin-bottom: clamp(4px, 0.8vw, 6px);
        }

        .skeleton-section-title {
            height: clamp(20px, 2.5vw, 24px);
            width: 60%;
        }

        .skeleton-help-text {
            height: clamp(12px, 1.8vw, 14px);
            width: 80%;
            margin-top: clamp(3px, 0.6vw, 4px);
        }

        .skeleton-pdf-header {
            height: clamp(18px, 2.2vw, 22px);
            width: 50%;
            margin-bottom: clamp(10px, 1.8vw, 12px);
        }

        .skeleton-pdf-area {
            height: clamp(200px, 30vw, 300px);
            border-radius: clamp(4px, 0.8vw, 6px);
        }

        .form-column.loading .form-group,
        .form-column.loading .form-row > div {
            opacity: 0;
        }

        .form-column.loading .skeleton {
            opacity: 1;
        }

        /* ========================================
           HELPER TEXT
           ======================================== */
        .help-text {
            font-size: clamp(0.68rem, 1.25vw, 0.72rem);
            color: #6c757d;
            margin-top: clamp(3px, 0.6vw, 4px);
            font-style: italic;
            line-height: 1.4;
        }

        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */
        @media (max-width: 1200px) {
            .form-layout {
                grid-template-columns: 1fr;
            }

            .pdf-column {
                position: static;
                max-height: none;
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
                padding: 0;
                padding-top: 70px;
                background: #ffffff;
            }

            .sidebar-collapsed .content {
                margin-left: 0;
            }

            .page-container {
                padding: 0;
            }

            /* Disable tooltips on mobile */
            .sidebar-collapsed .sidebar-menu li a::after {
                display: none;
            }

            /* Show text on mobile even in collapsed mode */
            .sidebar-collapsed .sidebar-menu li a span,
            .sidebar-collapsed .sidebar-header h4 span,
            .sidebar-collapsed .sidebar-heading {
                display: inline;
                font-size: inherit;
                text-indent: 0;
            }

            .sidebar-collapsed .sidebar-menu li a {
                justify-content: flex-start;
                padding: 14px 18px;
                margin: 4px 14px;
                gap: 12px;
            }

            /* User Profile Dropdown - Mobile adjustments */
            .user-profile-info {
                display: none;
            }

            .dropdown-arrow {
                display: none;
            }

            .user-dropdown-menu {
                min-width: 260px;
                right: -8px;
            }

            .system-header {
                flex-direction: column;
                text-align: center;
                gap: clamp(8px, 1.5vw, 12px);
                margin: 0 0 clamp(12px, 2vw, 15px) 0;
            }

            .system-logo {
                width: 60px;
                height: 60px;
            }

            .system-title {
                font-size: 0.9rem;
            }

            .system-subtitle {
                font-size: 0.7rem;
            }

            .page-header {
                margin-bottom: clamp(12px, 2vw, 15px);
            }

            .page-title {
                flex-direction: column;
                gap: clamp(4px, 0.8vw, 6px);
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: clamp(8px, 1.5vw, 10px);
            }

            .button-group {
                flex-direction: column;
                gap: clamp(6px, 1.2vw, 8px);
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .section-header {
                padding: clamp(6px, 1.2vw, 8px) clamp(8px, 1.5vw, 10px);
            }

            .form-section {
                margin-bottom: clamp(12px, 2vw, 15px);
            }
        }

        @media (max-width: 480px) {
            body {
                font-size: 0.75rem;
            }

            .system-logo {
                width: 50px;
                height: 50px;
            }

            .system-title {
                font-size: 0.8rem;
                letter-spacing: 0.3px;
            }

            .system-subtitle {
                font-size: 0.65rem;
            }

            .page-title {
                font-size: 1rem;
            }

            .section-title {
                font-size: 0.85rem;
            }

            label {
                font-size: 0.72rem;
            }

            input[type="text"],
            input[type="number"],
            input[type="datetime-local"],
            input[type="date"],
            select,
            textarea {
                font-size: 0.72rem;
                padding: 6px 8px;
            }

            .btn {
                font-size: 0.72rem;
                padding: 5px 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/mobile_header.php'; ?>

    <?php include '../includes/sidebar_nav.php'; ?>

    <?php include '../includes/top_navbar.php'; ?>

    <!-- Main Content Area -->
    <div class="content">
        <div class="main-content-wrapper">
            <div class="form-content-container">
                <!-- System Header with Logo -->
                <div class="system-header">
                <div class="system-logo">
                    <img src="../assets/img/LOGO1.png" alt="Bayan ng Baggao Logo">
                </div>
                <div class="system-title-container">
                    <h1 class="system-title">Civil Registry Records Management System</h1>
                    <p class="system-subtitle">Lalawigan ng Cagayan - Bayan ng Baggao</p>
                </div>
            </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Death - Entry Form
            </h1>
            <p class="page-subtitle">Complete the form below to register a death certificate</p>
        </div>

        <!-- Alert Messages -->
        <?php include '../includes/form_alerts.php'; ?>

        <!-- Main Form -->
        <form id="certificateForm" enctype="multipart/form-data">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
            <?php endif; ?>

            <div class="form-layout">
                <!-- LEFT COLUMN: Form Fields -->
                <div class="form-column">

                    <!-- Registry Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="clipboard-list"></i>
                                Registry Information
                            </h2>
                        </div>

                        <div class="form-group">
                            <label for="registry_no">
                                Registry Number
                            </label>
                            <input
                                type="text"
                                id="registry_no"
                                name="registry_no"
                                placeholder="Enter registry number (e.g., REG-2025-00001 or single digit)"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['registry_no']) : ''; ?>"
                            >
                            <span class="help-text">Optional - Can be any format including single digit numbers</span>
                        </div>

                        <div class="form-group">
                            <label for="date_of_registration">
                                Date of Registration <span class="required">*</span>
                            </label>
                            <input
                                type="date"
                                id="date_of_registration"
                                name="date_of_registration"
                                required
                                value="<?php echo $edit_mode ? date('Y-m-d', strtotime($record['date_of_registration'])) : ''; ?>"
                            >
                        </div>
                    </div>

                    <!-- Deceased Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="user"></i>
                                Deceased Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="deceased_first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="deceased_first_name"
                                    name="deceased_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['deceased_first_name']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="deceased_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="deceased_middle_name"
                                    name="deceased_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['deceased_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="deceased_last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="deceased_last_name"
                                    name="deceased_last_name"
                                    required
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['deceased_last_name']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['date_of_birth']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="date_of_death">
                                    Date of Death <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="date_of_death"
                                    name="date_of_death"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['date_of_death']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="age">
                                    Age (Years) <span class="required">*</span>
                                </label>
                                <input
                                    type="number"
                                    id="age"
                                    name="age"
                                    required
                                    readonly
                                    placeholder="Auto-calculated"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['age']) : ''; ?>"
                                    style="background-color: #e9ecef; cursor: not-allowed;"
                                >
                                <span class="help-text">Automatically calculated from date of birth and date of death</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="occupation">
                                Occupation
                            </label>
                            <input
                                type="text"
                                id="occupation"
                                name="occupation"
                                placeholder="Enter occupation"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['occupation'] ?? '') : ''; ?>"
                            >
                        </div>
                    </div>

                    <!-- Place of Death Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="map-pin"></i>
                                Place of Death
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="place_of_death">
                                    Barangay/Hospital <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="place_of_death"
                                    name="place_of_death"
                                    required
                                    placeholder="Enter barangay or hospital name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['place_of_death']) : ''; ?>"
                                >
                                <span class="help-text">Enter the specific barangay or hospital where death occurred</span>
                            </div>

                            <div class="form-group">
                                <label for="municipality">
                                    Municipality
                                </label>
                                <input
                                    type="text"
                                    id="municipality"
                                    name="municipality"
                                    value="Baggao"
                                    readonly
                                    disabled
                                    style="background-color: #e9ecef; cursor: not-allowed;"
                                >
                            </div>

                            <div class="form-group">
                                <label for="province">
                                    Province
                                </label>
                                <input
                                    type="text"
                                    id="province"
                                    name="province"
                                    value="Cagayan"
                                    readonly
                                    disabled
                                    style="background-color: #e9ecef; cursor: not-allowed;"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Father's Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="user-check"></i>
                                Father's Name
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="father_first_name">
                                    First Name
                                </label>
                                <input
                                    type="text"
                                    id="father_first_name"
                                    name="father_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['father_first_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="father_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="father_middle_name"
                                    name="father_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['father_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="father_last_name">
                                    Last Name
                                </label>
                                <input
                                    type="text"
                                    id="father_last_name"
                                    name="father_last_name"
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['father_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Mother's Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="user"></i>
                                Mother's Maiden Name
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_first_name">
                                    First Name
                                </label>
                                <input
                                    type="text"
                                    id="mother_first_name"
                                    name="mother_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['mother_first_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="mother_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="mother_middle_name"
                                    name="mother_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['mother_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="mother_last_name">
                                    Last Name
                                </label>
                                <input
                                    type="text"
                                    id="mother_last_name"
                                    name="mother_last_name"
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['mother_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="sticky-buttons">
                        <?php include '../includes/form_buttons.php'; ?>
                    </div>

                </div>

                <!-- RIGHT COLUMN: PDF Preview -->
                <div class="pdf-column" id="pdfColumn">
                    <div class="pdf-preview-header">
                        <h3 class="pdf-preview-title">
                            <i data-lucide="file-text"></i>
                            Certificate PDF Upload
                        </h3>
                        <button type="button" id="togglePdfBtn" class="toggle-pdf-btn" title="Hide PDF Upload">
                            <i data-lucide="eye-off"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="pdf_file">
                            Upload PDF Certificate <?php echo !$edit_mode ? '<span class="required">*</span>' : ''; ?>
                        </label>

                        <div class="upload-scanner-container">
                            <input
                                type="file"
                                id="pdf_file"
                                name="pdf_file"
                                accept=".pdf"
                                <?php echo !$edit_mode ? 'required' : ''; ?>
                            >

                            <button type="button" id="scanDocumentBtn" class="btn-scan" title="Scan using DS-530 II">
                                <i data-lucide="scan"></i>
                                Scan Document
                            </button>
                        </div>

                        <div id="scanStatus" class="scan-status hidden"></div>

                        <span class="help-text">Maximum file size: 10MB. Only PDF files are accepted.</span>
                        <span class="help-text">Use the "Scan Document" button to scan directly from DS-530 II scanner.</span>
                        <?php if ($edit_mode && !empty($record['pdf_filename'])): ?>
                            <span class="help-text">Leave empty to keep existing file.</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($edit_mode && !empty($record['pdf_filename'])): ?>
                    <div class="pdf-preview-container">
                        <iframe id="pdfPreview" src="../uploads/<?php echo htmlspecialchars($record['pdf_filename']); ?>"></iframe>
                    </div>
                    <div class="pdf-info">
                        <i data-lucide="info"></i>
                        <span>Current File: <span class="pdf-filename"><?php echo htmlspecialchars($record['pdf_filename']); ?></span></span>
                    </div>
                    <?php else: ?>
                    <div id="pdfUploadArea" class="pdf-upload-area">
                        <i data-lucide="upload-cloud"></i>
                        <p class="pdf-upload-text">Click "Choose File" above to upload PDF</p>
                        <p class="pdf-upload-hint">The PDF will be previewed here after upload</p>
                    </div>

                    <div id="pdfPreviewArea" class="hidden">
                        <div class="pdf-preview-container">
                            <iframe id="pdfPreview" src=""></iframe>
                        </div>
                        <div class="pdf-info">
                            <i data-lucide="info"></i>
                            <span>File: <span id="pdfFileName" class="pdf-filename"></span></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Floating Toggle Button (shown when PDF is hidden) -->
        <button type="button" id="floatingToggleBtn" class="floating-toggle-btn" title="Show PDF Upload">
            <i data-lucide="eye"></i>
        </button>

            </div> <!-- Close form-content-container -->
        </div> <!-- Close main-content-wrapper -->
    </div> <!-- Close content -->

    <!-- Shared Certificate Form Handler -->
    <script src="../assets/js/certificate-form-handler.js"></script>

    <!-- Death Certificate Specific Logic -->
    <script>
        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'death',
            apiEndpoint: '../api/certificate_of_death_save.php'
        });

        // Death-specific: Calculate age automatically based on date of birth and date of death
        function calculateAge() {
            const dateOfBirth = document.getElementById('date_of_birth').value;
            const dateOfDeath = document.getElementById('date_of_death').value;
            const ageInput = document.getElementById('age');

            if (dateOfBirth && dateOfDeath) {
                const birthDate = new Date(dateOfBirth);
                const deathDate = new Date(dateOfDeath);

                // Check if death date is after birth date
                if (deathDate < birthDate) {
                    formHandler.showAlert('warning', 'Date of death cannot be before date of birth.');
                    ageInput.value = '';
                    return;
                }

                // Calculate age in years
                let age = deathDate.getFullYear() - birthDate.getFullYear();
                const monthDiff = deathDate.getMonth() - birthDate.getMonth();

                // Adjust age if birthday hasn't occurred yet in the death year
                if (monthDiff < 0 || (monthDiff === 0 && deathDate.getDate() < birthDate.getDate())) {
                    age--;
                }

                ageInput.value = age;
            } else {
                ageInput.value = '';
            }
        }

        // Add event listeners for age calculation
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('date_of_birth').addEventListener('change', calculateAge);
            document.getElementById('date_of_death').addEventListener('change', calculateAge);
        });
    </script>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <!-- OCR Feature Integration - Professional Modal System -->
    <!-- Page Range Selector -->
    <link rel="stylesheet" href="../assets/css/ocr-page-selector.css">
    <script src="../assets/js/ocr-page-selector.js"></script>

    <!-- Server-side OCR (FAST!) -->
    <script src="../assets/js/ocr-server-client.js"></script>

    <!-- Browser OCR (Fallback) -->
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';</script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <script src="../assets/js/ocr-processor.js"></script>

    <!-- Core OCR Integration -->
    <script src="../assets/js/ocr-field-mapper.js"></script>
    <script src="../assets/js/ocr-modal.js"></script>

    <!-- Certificate Skeleton Loader -->
    <script src="../assets/js/certificate-skeleton-loader.js"></script>

    <!-- Initialize OCR Modal -->
    <script>
        // Initialize professional modal OCR interface
        window.ocrModal = new OCRModal({
            autoProcess: true,
            autoFill: false,
            confidenceThreshold: 75,
            formType: 'death'
        });
    </script>
</body>
</html>
