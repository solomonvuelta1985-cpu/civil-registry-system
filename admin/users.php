<?php
/**
 * User Management - View, Search, Edit, Delete Users
 * Civil Registry Records Management System
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check authentication and permission
requireAuth();
if (!hasPermission('users_view')) {
    header('Location: ../public/login.php');
    exit;
}

// Get user permissions for UI
$can_create = hasPermission('users_create');
$can_edit = hasPermission('users_edit');
$can_delete = hasPermission('users_delete');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Civil Registry</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #1a1a1a;
            font-size: 0.875rem;
            line-height: 1.5;
        }

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

        /* Mobile Header */
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
        }

        #mobileSidebarToggle {
            background: none;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
        }

        /* Sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

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
            transition: width 0.3s;
            overflow: hidden;
        }

        .sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
            min-height: 64px;
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
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }

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

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 2px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s;
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
        }

        .sidebar-menu li a [data-lucide] {
            width: 20px;
            height: 20px;
            min-width: 20px;
            flex-shrink: 0;
            margin-right: 12px;
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
        }

        /* Top Navbar */
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
            z-index: 100;
            transition: left 0.3s;
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
        }

        #sidebarCollapse:hover {
            background: #f3f4f6;
            color: var(--accent-color);
        }

        .top-navbar-info {
            margin-left: 16px;
        }

        .welcome-text {
            color: #6b7280;
            font-size: 13.5px;
            font-weight: 500;
        }

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
            transition: all 0.2s;
        }

        .user-profile-btn:hover {
            background: #f9fafb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            font-size: 13.5px;
            transition: background 0.15s;
        }

        .dropdown-menu a:hover {
            background: #f3f4f6;
        }

        .dropdown-menu a [data-lucide] {
            width: 18px;
            height: 18px;
            color: #6b7280;
        }

        .dropdown-divider {
            border-top: 1px solid #e5e7eb;
            margin: 4px 0;
        }

        .dropdown-menu a.text-danger {
            color: #dc2626;
        }

        .dropdown-menu a.text-danger [data-lucide] {
            color: #dc2626;
        }

        /* Main Content */
        .content {
            margin-left: var(--sidebar-width);
            padding-top: 64px;
            min-height: 100vh;
            background: #f8f9fa;
            transition: margin-left 0.3s;
        }

        .sidebar-collapsed .content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .page-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }

        .page-title [data-lucide] {
            color: #3b82f6;
        }

        /* Buttons */
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            font-family: inherit;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background-color: #10b981;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background-color: #f59e0b;
            color: #ffffff;
        }

        .btn-warning:hover {
            background-color: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background-color: #ef4444;
            color: #ffffff;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #6b7280;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #374151;
        }

        /* Search Section */
        .search-section {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }

        .search-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9375rem;
            background-color: #f9fafb;
            transition: all 0.2s ease-in-out;
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .search-input-wrapper::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.875rem;
            background-color: #f9fafb;
            min-width: 140px;
            cursor: pointer;
            font-family: inherit;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
        }

        /* Table */
        .table-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.8125rem;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        td {
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 0.875rem;
        }

        tr:hover {
            background: #f9fafb;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-encoder {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-viewer {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Actions */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
            background: transparent;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .action-btn.edit {
            color: #3b82f6;
            background: #eff6ff;
        }

        .action-btn.edit:hover {
            background: #dbeafe;
        }

        .action-btn.delete {
            color: #ef4444;
            background: #fef2f2;
        }

        .action-btn.delete:hover {
            background: #fee2e2;
        }

        /* Pagination */
        .pagination-wrapper {
            padding: 20px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .pagination-info {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .pagination {
            display: flex;
            gap: 4px;
        }

        .pagination button,
        .pagination a {
            padding: 8px 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            border-radius: 8px;
            color: #374151;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
        }

        .pagination button:hover,
        .pagination a:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .pagination button.active,
        .pagination a.active {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
        }

        .modal-close {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6b7280;
        }

        .empty-state [data-lucide] {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 3000;
        }

        .toast {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            animation: toastSlide 0.3s ease;
            min-width: 300px;
        }

        @keyframes toastSlide {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .toast.success {
            border-left: 4px solid #10b981;
        }

        .toast.error {
            border-left: 4px solid #ef4444;
        }

        .toast-icon {
            width: 24px;
            height: 24px;
        }

        .toast.success .toast-icon {
            color: #10b981;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast-message {
            flex: 1;
            font-size: 0.875rem;
            color: #374151;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
            }

            .top-navbar {
                left: 0;
            }

            .mobile-header {
                display: block;
            }

            .content {
                padding-top: 120px;
            }

            .sidebar-overlay.active {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input-wrapper {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .action-btns {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
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
    <div class="top-navbar">
        <button id="sidebarCollapse">
            <i data-lucide="panel-left-close"></i>
        </button>
        <div class="top-navbar-info">
            <span class="welcome-text">User Management</span>
        </div>

        <div class="user-profile-dropdown">
            <button class="user-profile-btn" id="userDropdownBtn">
                <div class="user-avatar"><?php echo strtoupper(substr(getUserFullName(), 0, 1)); ?></div>
                <div class="user-profile-info">
                    <span class="user-name"><?php echo htmlspecialchars(getUserFullName()); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars(getUserRole()); ?></span>
                </div>
                <i data-lucide="chevron-down" class="dropdown-arrow"></i>
            </button>
            <div class="dropdown-menu" id="userDropdownMenu">
                <a href="#"><i data-lucide="user"></i> My Profile</a>
                <a href="#"><i data-lucide="settings"></i> Settings</a>
                <div class="dropdown-divider"></div>
                <a href="../public/logout.php" class="text-danger"><i data-lucide="log-out"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i data-lucide="users"></i>
                    User Management
                </h1>
                <?php if ($can_create): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i data-lucide="user-plus"></i>
                    Add New User
                </button>
                <?php endif; ?>
            </div>

            <!-- Search & Filter -->
            <div class="search-section">
                <div class="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" id="searchInput" placeholder="Search by username, name, or email...">
                    </div>
                    <select class="filter-select" id="roleFilter">
                        <option value="">All Roles</option>
                        <option value="Admin">Admin</option>
                        <option value="Encoder">Encoder</option>
                        <option value="Viewer">Viewer</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Users Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i data-lucide="loader-2" class="spinner"></i>
                                        <h3>Loading users...</h3>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info" id="paginationInfo">Showing 0 of 0 users</div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New User</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="id">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" name="full_name" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter email address">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="Encoder">Encoder</option>
                                <option value="Viewer">Viewer</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span id="passwordHint">(min 6 characters)</span></label>
                        <input type="password" id="password" name="password" placeholder="Enter password">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveUser()" id="saveBtn">
                    <i data-lucide="save"></i>
                    Save User
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Delete User</h3>
                <button class="modal-close" onclick="closeDeleteModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="color: #4b5563; text-align: center;">
                    Are you sure you want to delete <strong id="deleteUserName"></strong>?<br>
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">
                    <i data-lucide="trash-2"></i>
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // State
        let currentPage = 1;
        let perPage = 10;
        let users = [];
        let deleteUserId = null;
        let isEditing = false;

        // Permissions from PHP
        const canCreate = <?php echo $can_create ? 'true' : 'false'; ?>;
        const canEdit = <?php echo $can_edit ? 'true' : 'false'; ?>;
        const canDelete = <?php echo $can_delete ? 'true' : 'false'; ?>;

        // Load users on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search
            let searchTimeout;
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadUsers();
                }, 300);
            });

            // Filters
            document.getElementById('roleFilter').addEventListener('change', () => {
                currentPage = 1;
                loadUsers();
            });

            document.getElementById('statusFilter').addEventListener('change', () => {
                currentPage = 1;
                loadUsers();
            });

            // Sidebar toggle
            document.getElementById('sidebarCollapse').addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
            });

            // Mobile sidebar
            document.getElementById('mobileSidebarToggle')?.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('show');
                document.getElementById('sidebarOverlay').classList.toggle('active');
            });

            document.getElementById('sidebarOverlay').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.remove('show');
                this.classList.remove('active');
            });

            // User dropdown
            document.getElementById('userDropdownBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('userDropdownMenu').classList.toggle('show');
            });

            document.addEventListener('click', function() {
                document.getElementById('userDropdownMenu').classList.remove('show');
            });

            // Close modal on overlay click
            document.getElementById('userModal').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });

            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) closeDeleteModal();
            });
        }

        async function loadUsers() {
            const search = document.getElementById('searchInput').value;
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;

            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                search: search,
                role: role,
                status: status
            });

            try {
                const response = await fetch(`../api/users_list.php?${params}`);
                const data = await response.json();

                if (data.success) {
                    users = data.data;
                    renderUsers(users);
                    renderPagination(data.pagination);
                } else {
                    showToast(data.message || 'Failed to load users', 'error');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showToast('Failed to load users', 'error');
            }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');

            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i data-lucide="users"></i>
                                <h3>No users found</h3>
                                <p>Try adjusting your search or filters</p>
                            </div>
                        </td>
                    </tr>
                `;
                lucide.createIcons();
                return;
            }

            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="user-avatar" style="width: 36px; height: 36px; font-size: 12px;">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #111827;">${escapeHtml(user.full_name)}</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">@${escapeHtml(user.username)}</div>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(user.email || '-')}</td>
                    <td><span class="badge badge-${user.role.toLowerCase()}">${user.role}</span></td>
                    <td><span class="badge badge-${user.status.toLowerCase()}">${user.status}</span></td>
                    <td>${user.last_login_formatted}</td>
                    <td>${user.created_at_formatted}</td>
                    <td>
                        <div class="action-btns">
                            ${canEdit ? `
                            <button class="action-btn edit" onclick="editUser(${user.id})" title="Edit">
                                <i data-lucide="pencil"></i>
                            </button>
                            ` : ''}
                            ${canDelete ? `
                            <button class="action-btn delete" onclick="deleteUser(${user.id}, '${escapeHtml(user.full_name)}')" title="Delete">
                                <i data-lucide="trash-2"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');

            lucide.createIcons();
        }

        function renderPagination(pagination) {
            const { current_page, total_pages, total, per_page } = pagination;
            const start = (current_page - 1) * per_page + 1;
            const end = Math.min(current_page * per_page, total);

            document.getElementById('paginationInfo').textContent =
                total > 0 ? `Showing ${start}-${end} of ${total} users` : 'No users found';

            const paginationDiv = document.getElementById('pagination');

            if (total_pages <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `<button onclick="goToPage(${current_page - 1})" ${current_page === 1 ? 'disabled' : ''}>
                <i data-lucide="chevron-left"></i>
            </button>`;

            // Page numbers
            for (let i = 1; i <= total_pages; i++) {
                if (i === 1 || i === total_pages || (i >= current_page - 2 && i <= current_page + 2)) {
                    html += `<button onclick="goToPage(${i})" class="${i === current_page ? 'active' : ''}">${i}</button>`;
                } else if (i === current_page - 3 || i === current_page + 3) {
                    html += `<button disabled>...</button>`;
                }
            }

            // Next button
            html += `<button onclick="goToPage(${current_page + 1})" ${current_page === total_pages ? 'disabled' : ''}>
                <i data-lucide="chevron-right"></i>
            </button>`;

            paginationDiv.innerHTML = html;
            lucide.createIcons();
        }

        function goToPage(page) {
            currentPage = page;
            loadUsers();
        }

        function openCreateModal() {
            isEditing = false;
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('username').disabled = false;
            document.getElementById('password').required = true;
            document.getElementById('passwordHint').textContent = '(min 6 characters)';
            document.getElementById('userModal').classList.add('show');
            lucide.createIcons();
        }

        async function editUser(id) {
            isEditing = true;
            document.getElementById('modalTitle').textContent = 'Edit User';

            try {
                const response = await fetch(`../api/users_get.php?id=${id}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.data;
                    document.getElementById('userId').value = user.id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('username').disabled = true;
                    document.getElementById('fullName').value = user.full_name;
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('role').value = user.role;
                    document.getElementById('status').value = user.status;
                    document.getElementById('password').value = '';
                    document.getElementById('password').required = false;
                    document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
                    document.getElementById('userModal').classList.add('show');
                    lucide.createIcons();
                } else {
                    showToast(data.message || 'Failed to load user', 'error');
                }
            } catch (error) {
                showToast('Failed to load user', 'error');
            }
        }

        function closeModal() {
            document.getElementById('userModal').classList.remove('show');
        }

        async function saveUser() {
            const form = document.getElementById('userForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            // Validation
            if (!data.full_name.trim()) {
                showToast('Full name is required', 'error');
                return;
            }

            if (!isEditing) {
                if (!data.username.trim() || !/^[a-zA-Z0-9_]{3,50}$/.test(data.username)) {
                    showToast('Username must be 3-50 alphanumeric characters', 'error');
                    return;
                }
                if (!data.password || data.password.length < 6) {
                    showToast('Password must be at least 6 characters', 'error');
                    return;
                }
            }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="spinner"></i> Saving...';
            lucide.createIcons();

            try {
                const url = isEditing ? '../api/users_update.php' : '../api/users_save.php';
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'User saved successfully', 'success');
                    closeModal();
                    loadUsers();
                } else {
                    showToast(result.message || 'Failed to save user', 'error');
                }
            } catch (error) {
                showToast('Failed to save user', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save"></i> Save User';
                lucide.createIcons();
            }
        }

        function deleteUser(id, name) {
            deleteUserId = id;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            deleteUserId = null;
        }

        async function confirmDelete() {
            if (!deleteUserId) return;

            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="spinner"></i> Deleting...';
            lucide.createIcons();

            try {
                const response = await fetch('../api/users_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: deleteUserId })
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'User deleted successfully', 'success');
                    closeDeleteModal();
                    loadUsers();
                } else {
                    showToast(result.message || 'Failed to delete user', 'error');
                }
            } catch (error) {
                showToast('Failed to delete user', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="trash-2"></i> Delete';
                lucide.createIcons();
            }
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="toast-icon"></i>
                <span class="toast-message">${escapeHtml(message)}</span>
            `;
            container.appendChild(toast);
            lucide.createIcons();

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // CSS for spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .spinner {
                animation: spin 0.8s linear infinite;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
