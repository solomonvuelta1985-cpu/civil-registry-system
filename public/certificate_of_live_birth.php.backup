<?php
/**
 * Certificate of Live Birth - Entry Form (PHP Version)
 * Includes database connectivity and server-side processing
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Optional: Check if user is authenticated
// require_once '../includes/auth.php';
// if (!isLoggedIn()) {
//     header('Location: ../login.php');
//     exit;
// }

// Get record ID if editing (optional)
$edit_mode = false;
$record = null;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $edit_mode = true;
    $record_id = sanitize_input($_GET['id']);

    // Fetch record from database
    try {
        $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = :id AND status = 'Active'");
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
    <title><?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Live Birth - Entry Form</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            padding: 12px 0;
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
            padding: 10px 16px;
            margin: 2px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
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
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 22px;
            background: var(--accent-color);
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.6);
        }

        .sidebar-menu li a [data-lucide] {
            min-width: 28px;
            transition: all 0.3s ease;
        }

        .sidebar-menu li a:hover [data-lucide] {
            transform: scale(1.08);
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
            margin: 12px 16px;
        }

        .sidebar-heading {
            padding: 14px 20px 8px;
            font-size: 10.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.05em;
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
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
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
        input[type="datetime-local"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        input[type="text"]:disabled,
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
                padding: 11px 16px;
                margin: 2px 12px;
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
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <div>
                <h4><i data-lucide="file-badge"></i> Civil Registry</h4>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <!-- User Profile Dropdown (Mobile) -->
                <div class="user-profile-dropdown">
                    <button class="user-profile-btn" id="mobileUserProfileBtn" type="button">
                        <div class="user-avatar">AU</div>
                    </button>

                    <div class="user-dropdown-menu" id="mobileUserDropdownMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-user-info">
                                <div class="user-avatar large">AU</div>
                                <div>
                                    <div class="dropdown-user-name">Admin User</div>
                                    <div class="dropdown-user-email">admin</div>
                                    <span class="dropdown-user-badge">ADMIN</span>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item logout-item">
                            <i data-lucide="log-out"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
                <button type="button" id="mobileSidebarToggle">
                    <i data-lucide="menu"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i data-lucide="file-badge"></i> <span>Civil Registry</span></h4>
        </div>

        <ul class="sidebar-menu">
            <!-- Main Section -->
            <li class="sidebar-heading">Overview</li>
            <li>
                <a href="../admin/dashboard.php" title="Dashboard">
                    <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            <!-- Certificates Section -->
            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">Certificates</li>
            <li>
                <a href="certificate_of_live_birth.php" class="active" title="Birth Certificates">
                    <i data-lucide="baby"></i> <span>Birth Certificates</span>
                </a>
            </li>
            <li>
                <a href="#" title="Marriage Certificates">
                    <i data-lucide="heart"></i> <span>Marriage Certificates</span>
                </a>
            </li>
            <li>
                <a href="#" title="Death Certificates">
                    <i data-lucide="cross"></i> <span>Death Certificates</span>
                </a>
            </li>

            <!-- Records Section -->
            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">Management</li>
            <li>
                <a href="#" title="Search Records">
                    <i data-lucide="file-search"></i> <span>Search Records</span>
                </a>
            </li>
            <li>
                <a href="#" title="Reports">
                    <i data-lucide="bar-chart-3"></i> <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="#" title="Archives">
                    <i data-lucide="archive"></i> <span>Archives</span>
                </a>
            </li>

            <!-- System Section -->
            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">System</li>
            <li>
                <a href="#" title="Users">
                    <i data-lucide="users"></i> <span>Users</span>
                </a>
            </li>
            <li>
                <a href="#" title="Settings">
                    <i data-lucide="settings"></i> <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Top Navigation Bar (Desktop) -->
    <div class="top-navbar" id="topNavbar">
        <button type="button" id="sidebarCollapse" title="Toggle Sidebar">
            <i data-lucide="menu"></i>
        </button>
        <div class="top-navbar-info">
            <span class="welcome-text">Welcome, Admin User</span>
        </div>

        <!-- User Profile Dropdown -->
        <div class="user-profile-dropdown">
            <button class="user-profile-btn" id="userProfileBtn" type="button">
                <div class="user-avatar">AU</div>
                <div class="user-profile-info">
                    <span class="user-name">Admin User</span>
                    <span class="user-role">Administrator</span>
                </div>
                <i data-lucide="chevron-down" class="dropdown-arrow"></i>
            </button>

            <div class="user-dropdown-menu" id="userDropdownMenu">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <div class="user-avatar large">AU</div>
                        <div>
                            <div class="dropdown-user-name">Admin User</div>
                            <div class="dropdown-user-email">admin</div>
                            <span class="dropdown-user-badge">ADMIN</span>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item logout-item">
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="content">
        <div class="page-container">
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
                <i data-lucide="file-text"></i>
                <?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Live Birth - Entry Form
            </h1>
            <p class="page-subtitle">Complete the form below to register a new birth certificate</p>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>
        </div>

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

                        <!-- Child's Name -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="child_first_name">
                                    Child's First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="child_first_name"
                                    name="child_first_name"
                                    required
                                    placeholder="Enter child's first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['child_first_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="child_middle_name">
                                    Child's Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="child_middle_name"
                                    name="child_middle_name"
                                    placeholder="Enter child's middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['child_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="child_last_name">
                                    Child's Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="child_last_name"
                                    name="child_last_name"
                                    required
                                    placeholder="Enter child's last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['child_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <!-- Date of Birth -->
                        <div class="form-group">
                            <label for="child_date_of_birth">
                                Child's Date of Birth <span class="required">*</span>
                            </label>
                            <input
                                type="date"
                                id="child_date_of_birth"
                                name="child_date_of_birth"
                                required
                                value="<?php echo $edit_mode ? htmlspecialchars($record['child_date_of_birth'] ?? '') : ''; ?>"
                            >
                        </div>

                        <!-- Place of Birth -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="child_place_of_birth">
                                    Barangay/Hospital <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="child_place_of_birth"
                                    name="child_place_of_birth"
                                    required
                                    placeholder="Enter barangay or hospital name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['child_place_of_birth'] ?? '') : ''; ?>"
                                >
                                <span class="help-text">Enter the specific barangay or hospital where the child was born</span>
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

                    <!-- Birth Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="baby"></i>
                                Birth Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="type_of_birth">
                                    Type of Birth <span class="required">*</span>
                                </label>
                                <select id="type_of_birth" name="type_of_birth" required>
                                    <option value="">-- Select Type --</option>
                                    <?php
                                    $birth_types = ['Single', 'Twin', 'Triplets', 'Quadruplets', 'Other'];
                                    foreach ($birth_types as $type) {
                                        $selected = ($edit_mode && $record['type_of_birth'] === $type) ? 'selected' : '';
                                        echo "<option value='$type' $selected>$type</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group" id="type_of_birth_other_group" style="display: <?php echo ($edit_mode && $record['type_of_birth'] === 'Other') ? 'block' : 'none'; ?>;">
                                <label for="type_of_birth_other">
                                    Specify Other Type
                                </label>
                                <input
                                    type="text"
                                    id="type_of_birth_other"
                                    name="type_of_birth_other"
                                    placeholder="Please specify"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['type_of_birth_other'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="birth_order">
                                    Birth Order
                                </label>
                                <select id="birth_order" name="birth_order">
                                    <option value="">-- Select Order --</option>
                                    <?php
                                    $birth_orders = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', 'Other'];
                                    foreach ($birth_orders as $order) {
                                        $selected = ($edit_mode && $record['birth_order'] === $order) ? 'selected' : '';
                                        echo "<option value='$order' $selected>$order</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group" id="birth_order_other_group" style="display: <?php echo ($edit_mode && $record['birth_order'] === 'Other') ? 'block' : 'none'; ?>;">
                                <label for="birth_order_other">
                                    Specify Other Order
                                </label>
                                <input
                                    type="text"
                                    id="birth_order_other"
                                    name="birth_order_other"
                                    placeholder="Please specify"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['birth_order_other'] ?? '') : ''; ?>"
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
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="mother_first_name"
                                    name="mother_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['mother_first_name']) : ''; ?>"
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
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="mother_last_name"
                                    name="mother_last_name"
                                    required
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['mother_last_name']) : ''; ?>"
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

                    <!-- Marriage Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="heart"></i>
                                Marriage Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_marriage">
                                    Date of Marriage
                                </label>
                                <input
                                    type="date"
                                    id="date_of_marriage"
                                    name="date_of_marriage"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['date_of_marriage'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="place_of_marriage">
                                    Place of Marriage
                                </label>
                                <input
                                    type="text"
                                    id="place_of_marriage"
                                    name="place_of_marriage"
                                    placeholder="Enter place of marriage"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['place_of_marriage'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="button-group sticky-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            <?php echo $edit_mode ? 'Update Record' : 'Save Record'; ?>
                        </button>
                        <?php if (!$edit_mode): ?>
                        <button type="button" class="btn btn-success" id="saveAndNewBtn">
                            <i data-lucide="plus"></i>
                            Save & Add New
                        </button>
                        <?php endif; ?>
                        <button type="reset" class="btn btn-secondary">
                            <i data-lucide="rotate-ccw"></i>
                            Reset Form
                        </button>
                        <a href="../admin/dashboard.php" class="btn btn-warning">
                            <i data-lucide="arrow-left"></i>
                            Back to Dashboard
                        </a>
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

        </div> <!-- Close page-container -->
    </div> <!-- Close content -->

    <!-- JavaScript -->
    <script>
        const editMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

        // Show/hide "Other" input fields based on dropdown selection
        document.getElementById('type_of_birth').addEventListener('change', function() {
            const otherGroup = document.getElementById('type_of_birth_other_group');
            const otherInput = document.getElementById('type_of_birth_other');

            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        });

        document.getElementById('birth_order').addEventListener('change', function() {
            const otherGroup = document.getElementById('birth_order_other_group');
            const otherInput = document.getElementById('birth_order_other');

            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        });

        // PDF file preview
        document.getElementById('pdf_file').addEventListener('change', function(e) {
            const file = e.target.files[0];

            if (file) {
                // Validate file type
                if (file.type !== 'application/pdf') {
                    showAlert('danger', 'Please upload a valid PDF file.');
                    this.value = '';
                    return;
                }

                // Validate file size (10MB)
                if (file.size > 10485760) {
                    showAlert('danger', 'File size exceeds 10MB. Please upload a smaller file.');
                    this.value = '';
                    return;
                }

                // Show preview
                const uploadArea = document.getElementById('pdfUploadArea');
                const previewArea = document.getElementById('pdfPreviewArea');

                if (uploadArea && previewArea) {
                    const pdfPreview = document.getElementById('pdfPreview');
                    const pdfFileName = document.getElementById('pdfFileName');

                    const fileURL = URL.createObjectURL(file);
                    pdfPreview.src = fileURL;
                    pdfFileName.textContent = file.name;

                    uploadArea.classList.add('hidden');
                    previewArea.classList.remove('hidden');
                }
            }
        });

        // Form submission
        document.getElementById('certificateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(false);
        });

        // Save and New button (only for non-edit mode)
        const saveAndNewBtn = document.getElementById('saveAndNewBtn');
        if (saveAndNewBtn) {
            saveAndNewBtn.addEventListener('click', function() {
                if (document.getElementById('certificateForm').checkValidity()) {
                    submitForm(true);
                } else {
                    document.getElementById('certificateForm').reportValidity();
                }
            });
        }

        // Submit form function
        function submitForm(addNew) {
            const formData = new FormData(document.getElementById('certificateForm'));
            formData.append('add_new', addNew ? '1' : '0');

            const apiEndpoint = editMode ? '../api/certificate_of_live_birth_update.php' : '../api/certificate_of_live_birth_save.php';

            fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);

                    if (addNew && !editMode) {
                        // Reset form for new entry
                        setTimeout(() => {
                            document.getElementById('certificateForm').reset();
                            resetPdfPreview();
                        }, 1500);
                    } else {
                        // Redirect to dashboard after 2 seconds
                        setTimeout(() => {
                            window.location.href = '../admin/dashboard.php';
                        }, 2000);
                    }
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while saving the record.');
            });
        }

        // Reset PDF preview
        function resetPdfPreview() {
            const uploadArea = document.getElementById('pdfUploadArea');
            const previewArea = document.getElementById('pdfPreviewArea');

            if (uploadArea && previewArea) {
                const pdfPreview = document.getElementById('pdfPreview');

                uploadArea.classList.remove('hidden');
                previewArea.classList.add('hidden');
                pdfPreview.src = '';
            }
        }

        // Show alert message
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;

            const icon = type === 'success' ? 'check-circle' :
                        type === 'danger' ? 'alert-circle' :
                        type === 'warning' ? 'alert-triangle' :
                        'info';

            alertDiv.innerHTML = `
                <i data-lucide="${icon}"></i>
                <span>${message}</span>
            `;

            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertDiv);

            // Initialize Lucide icons in the new alert
            lucide.createIcons();

            // Scroll to top to show alert
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Set current date as default for registration (only for new records)
        window.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            lucide.createIcons();

            // PDF Column Toggle Functionality
            const pdfColumn = document.getElementById('pdfColumn');
            const formLayout = document.querySelector('.form-layout');
            const togglePdfBtn = document.getElementById('togglePdfBtn');
            const floatingToggleBtn = document.getElementById('floatingToggleBtn');
            let pdfVisible = true;

            // Load saved state from localStorage
            const savedState = localStorage.getItem('pdfColumnVisible');
            if (savedState !== null) {
                pdfVisible = savedState === 'true';
                if (!pdfVisible) {
                    pdfColumn.classList.add('hidden');
                    formLayout.classList.add('pdf-hidden');
                    floatingToggleBtn.classList.add('show');
                }
            }

            // Toggle PDF button click handler
            if (togglePdfBtn) {
                togglePdfBtn.addEventListener('click', function() {
                    pdfVisible = !pdfVisible;

                    if (pdfVisible) {
                        // Show PDF column
                        pdfColumn.classList.remove('hidden');
                        formLayout.classList.remove('pdf-hidden');
                        floatingToggleBtn.classList.remove('show');
                        togglePdfBtn.innerHTML = '<i data-lucide="eye-off"></i>';
                        togglePdfBtn.title = 'Hide PDF Upload';
                    } else {
                        // Hide PDF column
                        pdfColumn.classList.add('hidden');
                        formLayout.classList.add('pdf-hidden');
                        floatingToggleBtn.classList.add('show');
                        togglePdfBtn.innerHTML = '<i data-lucide="eye"></i>';
                        togglePdfBtn.title = 'Show PDF Upload';
                    }

                    // Reinitialize Lucide icons for the toggle button
                    lucide.createIcons();

                    // Save state to localStorage
                    localStorage.setItem('pdfColumnVisible', pdfVisible);
                });
            }

            // Floating toggle button click handler
            if (floatingToggleBtn) {
                floatingToggleBtn.addEventListener('click', function() {
                    pdfVisible = true;
                    pdfColumn.classList.remove('hidden');
                    formLayout.classList.remove('pdf-hidden');
                    floatingToggleBtn.classList.remove('show');
                    togglePdfBtn.innerHTML = '<i data-lucide="eye-off"></i>';
                    togglePdfBtn.title = 'Hide PDF Upload';

                    // Reinitialize Lucide icons
                    lucide.createIcons();

                    // Save state to localStorage
                    localStorage.setItem('pdfColumnVisible', 'true');
                });
            }

            if (!editMode) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');

                const defaultDate = `${year}-${month}-${day}`;
                document.getElementById('date_of_registration').value = defaultDate;
            }

            // Scanner Functionality for DS-530 II
            const scanDocumentBtn = document.getElementById('scanDocumentBtn');
            const scanStatus = document.getElementById('scanStatus');
            const pdfFileInput = document.getElementById('pdf_file');

            if (scanDocumentBtn) {
                scanDocumentBtn.addEventListener('click', async function() {
                    try {
                        // Show scanning status
                        scanStatus.className = 'scan-status scanning';
                        scanStatus.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Initializing scanner...';
                        scanStatus.classList.remove('hidden');
                        lucide.createIcons();

                        // Check if scanner service is available
                        // This will need to be replaced with actual scanner SDK integration
                        // For now, this is a placeholder that shows how it would work

                        // Option 1: Using Dynamic Web TWAIN (Commercial SDK)
                        // Option 2: Using a local scanner service/bridge
                        // Option 3: Using browser's getUserMedia with document scanner

                        const scannerAvailable = await checkScannerAvailability();

                        if (!scannerAvailable) {
                            throw new Error('DS-530 II scanner not detected. Please ensure the scanner is connected and drivers are installed.');
                        }

                        // Update status
                        scanStatus.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Scanning document...';
                        lucide.createIcons();

                        // Perform scan operation
                        const scannedFile = await performScan();

                        if (scannedFile) {
                            // Create a File object from the scanned data
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(scannedFile);
                            pdfFileInput.files = dataTransfer.files;

                            // Show success message
                            scanStatus.className = 'scan-status success';
                            scanStatus.innerHTML = '<i data-lucide="check-circle"></i> Document scanned successfully! PDF attached.';
                            lucide.createIcons();

                            // Trigger change event to update preview if exists
                            pdfFileInput.dispatchEvent(new Event('change', { bubbles: true }));

                            // Hide status after 5 seconds
                            setTimeout(() => {
                                scanStatus.classList.add('hidden');
                            }, 5000);
                        }

                    } catch (error) {
                        // Show error message
                        scanStatus.className = 'scan-status error';
                        scanStatus.innerHTML = `<i data-lucide="alert-circle"></i> ${error.message}`;
                        lucide.createIcons();

                        // Hide error after 10 seconds
                        setTimeout(() => {
                            scanStatus.classList.add('hidden');
                        }, 10000);
                    }
                });
            }

            // Check if scanner is available
            async function checkScannerAvailability() {
                // TODO: Implement actual scanner detection
                // This would connect to the scanner service/SDK

                // For demonstration, check if scanner service is running
                // You would replace this with actual scanner SDK calls
                try {
                    // Example: Check if local scanner service is running
                    const response = await fetch('http://localhost:18622/scanner/status', {
                        method: 'GET',
                        mode: 'cors'
                    }).catch(() => null);

                    if (response && response.ok) {
                        const data = await response.json();
                        return data.available && data.model.includes('DS-530');
                    }

                    // If service is not available, show instructions
                    return false;
                } catch (error) {
                    return false;
                }
            }

            // Perform scan operation
            async function performScan() {
                // TODO: Implement actual scanning logic
                // This would use the scanner SDK (Dynamic Web TWAIN, etc.)

                try {
                    // Example: Call local scanner service
                    const response = await fetch('http://localhost:18622/scanner/scan', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            scanner: 'DS-530 II',
                            format: 'pdf',
                            quality: 'high',
                            colorMode: 'color',
                            resolution: 300
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Scanning failed. Please try again.');
                    }

                    const blob = await response.blob();
                    const filename = `scanned_${Date.now()}.pdf`;

                    return new File([blob], filename, { type: 'application/pdf' });
                } catch (error) {
                    throw new Error('Scanner communication error: ' + error.message);
                }
            }

            // Add CSS for spinning animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .spin {
                    animation: spin 1s linear infinite;
                    display: inline-block;
                }
            `;
            document.head.appendChild(style);

            // Sidebar functionality
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
            const body = document.body;

            // Desktop: Toggle sidebar collapse/expand
            if (sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function() {
                    body.classList.toggle('sidebar-collapsed');

                    // Save state to localStorage
                    const isCollapsed = body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
            }

            // Mobile: Toggle sidebar visibility
            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                    body.classList.toggle('sidebar-open');
                });
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    body.classList.remove('sidebar-open');
                });
            }

            // Close mobile sidebar when clicking a menu link
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add click animation
                    this.style.transition = 'all 0.15s ease';
                    this.style.transform = 'scale(0.96) translateX(3px)';

                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);

                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('active');
                        sidebarOverlay.classList.remove('active');
                        body.classList.remove('sidebar-open');
                    }
                });
            });

            // Restore sidebar state on page load
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && window.innerWidth > 768) {
                body.classList.add('sidebar-collapsed');
            }

            // Animate active menu item on page load
            const activeMenuItem = document.querySelector('.sidebar-menu a.active');
            if (activeMenuItem) {
                activeMenuItem.style.animation = 'none';
                setTimeout(() => {
                    activeMenuItem.style.animation = '';
                }, 10);
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    body.classList.remove('sidebar-open');
                }
            });

            // User Profile Dropdown Functionality
            const userProfileBtn = document.getElementById('userProfileBtn');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            const mobileUserProfileBtn = document.getElementById('mobileUserProfileBtn');
            const mobileUserDropdownMenu = document.getElementById('mobileUserDropdownMenu');

            // Desktop dropdown
            if (userProfileBtn && userDropdownMenu) {
                userProfileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userProfileBtn.classList.toggle('active');
                    userDropdownMenu.classList.toggle('show');

                    // Close mobile dropdown if open
                    if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                        mobileUserProfileBtn.classList.remove('active');
                        mobileUserDropdownMenu.classList.remove('show');
                    }
                });
            }

            // Mobile dropdown
            if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                mobileUserProfileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileUserProfileBtn.classList.toggle('active');
                    mobileUserDropdownMenu.classList.toggle('show');

                    // Close desktop dropdown if open
                    if (userProfileBtn && userDropdownMenu) {
                        userProfileBtn.classList.remove('active');
                        userDropdownMenu.classList.remove('show');
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (userProfileBtn && userDropdownMenu) {
                    if (!userProfileBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                        userProfileBtn.classList.remove('active');
                        userDropdownMenu.classList.remove('show');
                    }
                }

                if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                    if (!mobileUserProfileBtn.contains(e.target) && !mobileUserDropdownMenu.contains(e.target)) {
                        mobileUserProfileBtn.classList.remove('active');
                        mobileUserDropdownMenu.classList.remove('show');
                    }
                }
            });

            // Close dropdown when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (userProfileBtn && userDropdownMenu) {
                        userProfileBtn.classList.remove('active');
                        userDropdownMenu.classList.remove('show');
                    }
                    if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                        mobileUserProfileBtn.classList.remove('active');
                        mobileUserDropdownMenu.classList.remove('show');
                    }
                }
            });
        });
    </script>
</body>
</html>
