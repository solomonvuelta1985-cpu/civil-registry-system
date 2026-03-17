<?php
/**
 * Certificate of Live Birth - Entry Form (PHP Version)
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
$required_permission = $edit_mode_check ? 'birth_edit' : 'birth_create';
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
    <title>Birth Certificate - Civil Registry System</title>

    <!-- Google Fonts (online only; system fonts used when OFFLINE_MODE=true) -->
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">

    <!-- Lucide Icons -->
    <script src="<?= asset_url('lucide') ?>"></script>

    <!-- Notiflix - Modern Notification Library -->
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../assets/js/notiflix-config.js"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

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

        .main-content-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            background: #ffffff;
            min-height: calc(100vh - 64px);
        }

        .form-content-container {
            padding: 0;
        }

        /* ========================================
           HEADER WITH LOGO
           ======================================== */
        .system-header {
            background: #1e40af;
            padding: clamp(12px, 2vw, 16px) clamp(15px, 2.5vw, 20px);
            margin: 0;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: clamp(12px, 2vw, 18px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Form Type Indicator Bar */
        .form-type-indicator {
            background: #ffffff;
            padding: clamp(12px, 2vw, 16px) clamp(15px, 2.5vw, 20px);
            margin: 0;
            display: flex;
            align-items: center;
            gap: clamp(12px, 2vw, 16px);
            border-bottom: 3px solid var(--form-accent-color, #3b82f6);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-type-icon {
            width: clamp(44px, 6vw, 52px);
            height: clamp(44px, 6vw, 52px);
            border-radius: 12px;
            background: var(--form-accent-color, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .form-type-icon svg {
            width: clamp(22px, 3vw, 26px);
            height: clamp(22px, 3vw, 26px);
            color: #ffffff;
            stroke-width: 2;
        }

        .form-type-info {
            flex: 1;
        }

        .form-type-title {
            font-size: clamp(1rem, 2vw, 1.15rem);
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: clamp(0.65rem, 1.2vw, 0.72rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--form-accent-color, #3b82f6);
            color: #ffffff;
        }

        .form-type-subtitle {
            font-size: clamp(0.75rem, 1.4vw, 0.85rem);
            color: #6b7280;
            margin: 0;
        }

        /* Form-specific accent colors */
        .form-birth {
            --form-accent-color: #3b82f6;
        }

        .form-marriage {
            --form-accent-color: #ec4899;
        }

        .form-death {
            --form-accent-color: #64748b;
        }

        .form-marriage-license {
            --form-accent-color: #f43f5e;
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
            margin-bottom: clamp(6px, 1vw, 8px);
            font-size: clamp(0.75rem, 1.4vw, 0.8125rem);
        }

        label .required {
            color: #dc3545;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="datetime-local"],
        input[type="date"],
        input[type="time"],
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
        input[type="time"]:focus,
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

        /* Form Validation States */
        input.is-invalid,
        select.is-invalid,
        textarea.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right clamp(9px, 1.5vw, 11px) center;
            background-size: clamp(14px, 1.8vw, 16px) clamp(14px, 1.8vw, 16px);
            padding-right: clamp(30px, 4vw, 35px);
        }

        input.is-valid,
        select.is-valid,
        textarea.is-valid {
            border-color: #198754;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right clamp(9px, 1.5vw, 11px) center;
            background-size: clamp(14px, 1.8vw, 16px) clamp(14px, 1.8vw, 16px);
            padding-right: clamp(30px, 4vw, 35px);
        }

        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: clamp(4px, 0.8vw, 6px);
            font-size: clamp(0.7rem, 1.3vw, 0.75rem);
            color: #dc3545;
        }

        input.is-invalid ~ .invalid-feedback,
        select.is-invalid ~ .invalid-feedback,
        textarea.is-invalid ~ .invalid-feedback {
            display: block;
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

        .skeleton-button {
            height: clamp(32px, 4vw, 38px);
            width: clamp(100px, 15vw, 150px);
            border-radius: clamp(3px, 0.6vw, 4px);
        }

        .skeleton-toggle-btn {
            height: clamp(32px, 3.5vw, 36px);
            width: clamp(80px, 12vw, 100px);
            border-radius: 6px;
        }

        .form-column.loading .form-group,
        .form-column.loading .form-row > div {
            opacity: 0;
        }

        .form-column.loading .skeleton {
            opacity: 1;
        }

        /* Prevent scrollbars during skeleton loading */
        body.skeleton-loading {
            overflow-x: hidden;
        }

        .form-column.skeleton-loading-active {
            overflow: hidden;
        }

        /* ========================================
           FORM PROGRESS INDICATOR (Sticky Corporate)
           ======================================== */
        .form-progress-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #ffffff;
            padding: 0;
            margin: 0 0 clamp(15px, 2.5vw, 20px) 0;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-progress-bar.is-stuck {
            background: #1e293b;
            border-bottom-color: transparent;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .progress-top-row {
            display: flex;
            align-items: center;
            padding: clamp(10px, 1.5vw, 14px) clamp(15px, 2.5vw, 20px);
            gap: clamp(10px, 1.5vw, 16px);
        }

        .form-progress {
            display: flex;
            align-items: center;
            gap: 0;
            flex: 1;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .form-progress::-webkit-scrollbar {
            display: none;
        }

        /* --- Step styles --- */
        .progress-step {
            display: flex;
            align-items: center;
            gap: clamp(8px, 1vw, 10px);
            padding: 0 clamp(10px, 1.5vw, 16px);
            font-size: clamp(0.72rem, 1.2vw, 0.8rem);
            font-weight: 500;
            color: #94a3b8;
            background: transparent;
            border: none;
            white-space: nowrap;
            cursor: pointer;
            transition: color 0.25s ease;
            flex-shrink: 0;
            position: relative;
            height: clamp(32px, 4vw, 36px);
        }

        .progress-step:hover {
            color: #64748b;
        }

        .progress-step.active {
            color: #1e293b;
            font-weight: 600;
        }

        .progress-step.completed {
            color: #1e293b;
        }

        .progress-step .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: clamp(22px, 2.8vw, 26px);
            height: clamp(22px, 2.8vw, 26px);
            border-radius: 50%;
            font-size: clamp(0.65rem, 1.1vw, 0.72rem);
            font-weight: 600;
            border: 2px solid #d1d5db;
            background: #ffffff;
            color: #9ca3af;
            transition: all 0.25s ease;
        }

        .progress-step.active .step-number {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #ffffff;
        }

        .progress-step.completed .step-number {
            border-color: #1e40af;
            background: #1e40af;
            color: #ffffff;
            font-size: 0;
        }

        .progress-step.completed .step-number::after {
            content: '\2713';
            font-size: clamp(0.65rem, 1vw, 0.72rem);
            font-weight: 700;
            line-height: 1;
        }

        .progress-connector {
            width: clamp(24px, 3.5vw, 40px);
            height: 0;
            border-top: 1px dashed #d1d5db;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .progress-connector.completed {
            border-top-style: solid;
            border-top-color: #1e40af;
            border-top-width: 2px;
        }

        .progress-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            padding-left: clamp(14px, 2vw, 20px);
            flex-shrink: 0;
        }

        .progress-percent-label {
            font-size: clamp(0.62rem, 1vw, 0.7rem);
            font-weight: 500;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .progress-percent {
            font-size: clamp(0.78rem, 1.3vw, 0.88rem);
            font-weight: 700;
            color: #1e293b;
            font-variant-numeric: tabular-nums;
            min-width: 32px;
            text-align: right;
        }

        /* Overall progress bar at bottom edge */
        .progress-overall {
            height: 2px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-overall-fill {
            height: 100%;
            background: #1e40af;
            transition: width 0.4s ease;
            width: 0%;
        }

        /* --- Stuck (dark) overrides --- */
        .form-progress-bar.is-stuck .progress-step {
            color: #64748b;
        }
        .form-progress-bar.is-stuck .progress-step:hover {
            color: #cbd5e1;
        }
        .form-progress-bar.is-stuck .progress-step.active {
            color: #ffffff;
        }
        .form-progress-bar.is-stuck .progress-step.completed {
            color: #93c5fd;
        }
        .form-progress-bar.is-stuck .step-number {
            border-color: #475569;
            background: transparent;
            color: #64748b;
        }
        .form-progress-bar.is-stuck .progress-step.active .step-number {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #ffffff;
        }
        .form-progress-bar.is-stuck .progress-step.completed .step-number {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #ffffff;
        }
        .form-progress-bar.is-stuck .progress-connector {
            border-top-color: #475569;
        }
        .form-progress-bar.is-stuck .progress-connector.completed {
            border-top-color: #3b82f6;
        }
        .form-progress-bar.is-stuck .progress-percent {
            color: #ffffff;
        }
        .form-progress-bar.is-stuck .progress-percent-label {
            color: #64748b;
        }
        .form-progress-bar.is-stuck .progress-overall {
            background: #334155;
        }
        .form-progress-bar.is-stuck .progress-overall-fill {
            background: #3b82f6;
        }

        @media (max-width: 768px) {
            .progress-top-row {
                padding: 8px 12px;
            }

            .progress-step .step-label {
                display: none;
            }

            .progress-step {
                padding: 0 6px;
                height: 30px;
            }

            .progress-connector {
                width: 16px;
            }

            .progress-meta {
                padding-left: 10px;
                gap: 4px;
            }

            .progress-percent-label {
                display: none;
            }
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
            input[type="datetime-local"],
            input[type="date"],
            input[type="time"],
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
                        <h1 class="system-title">Civil Registry Document Management System (CRDMS)</h1>
                        <p class="system-subtitle">Lalawigan ng Cagayan - Bayan ng Baggao</p>
                    </div>
                </div>

                <!-- Form Type Indicator with Progress -->
                <div class="form-type-indicator form-birth">
                    <div class="form-type-icon">
                        <i data-lucide="baby"></i>
                    </div>
                    <div class="form-type-info">
                        <h2 class="form-type-title">
                            Certificate of Live Birth
                            <span class="form-type-badge"><?php echo $edit_mode ? 'Edit Mode' : 'Birth Record'; ?></span>
                        </h2>
                        <p class="form-type-subtitle"><?php echo $edit_mode ? 'Update the birth certificate information below' : 'Complete the form below to register a new birth certificate'; ?></p>
                    </div>
                </div>

                <!-- Sticky Progress Bar -->
                <div class="form-progress-bar" id="formProgressBar">
                    <div class="progress-top-row">
                        <div class="form-progress" id="formProgress">
                            <div class="progress-step active" data-section="registry_section">
                                <span class="step-number">1</span>
                                <span class="step-label">Registry</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="birth_section">
                                <span class="step-number">2</span>
                                <span class="step-label">Birth Info</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="mother_section">
                                <span class="step-number">3</span>
                                <span class="step-label">Mother</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="father_section">
                                <span class="step-number">4</span>
                                <span class="step-label">Father</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="marriage_section">
                                <span class="step-number">5</span>
                                <span class="step-label">Marriage</span>
                            </div>
                        </div>
                        <div class="progress-meta">
                            <span class="progress-percent-label">Complete</span>
                            <span class="progress-percent" id="progressPercent">0%</span>
                        </div>
                    </div>
                    <div class="progress-overall">
                        <div class="progress-overall-fill" id="progressOverallFill"></div>
                    </div>
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
                    <div class="form-section" id="registry_section">
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
                                placeholder="e.g., 2014-1423 or 99-123456 (optional)"
                                pattern="^\d{2,4}-\d{4,6}$"
                                title="Format: XXXX-XXXX or XX-XXXXXX (numbers and dash only)"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['registry_no']) : ''; ?>"
                            >
                            <span class="help-text">Optional - Format: XXXX-XXXX or XX-XXXXXX (e.g., 2014-1423 or 99-123456). Leave blank if the registry number has not yet been assigned by the Civil Registrar.</span>
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

                        <!-- Date and Time of Birth -->
                        <div class="form-row">
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

                            <div class="form-group">
                                <label for="time_of_birth">
                                    Time of Birth
                                </label>
                                <input
                                    type="time"
                                    id="time_of_birth"
                                    name="time_of_birth"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['time_of_birth'] ?? '') : ''; ?>"
                                >
                                <span class="help-text">Optional - Enter time if available on the certificate</span>
                            </div>
                        </div>

                        <!-- Place of Birth -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="place_type">
                                    Place Type <span class="required">*</span>
                                </label>
                                <select id="place_type" name="place_type" required>
                                    <option value="">-- Select Place Type --</option>
                                    <?php
                                    $place_types = ['Hospital/Clinic', 'Home', 'Barangay Health Center', 'Other'];
                                    foreach ($place_types as $pt) {
                                        $selected = ($edit_mode && isset($record['place_type']) && $record['place_type'] === $pt) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($pt) . "\" $selected>" . htmlspecialchars($pt) . "</option>";
                                    }
                                    ?>
                                </select>
                                <span class="help-text">Select where the birth occurred (hospital, home, health center, etc.)</span>
                            </div>

                            <div class="form-group" id="child_place_of_birth_group" style="display: <?php echo ($edit_mode && !empty($record['place_type'])) ? 'block' : 'none'; ?>;">
                                <label for="child_place_of_birth">
                                    <span id="place_label">Location</span> <span class="required">*</span>
                                </label>
                                <select
                                    id="child_place_of_birth"
                                    name="child_place_of_birth"
                                >
                                    <option value="">-- Select Location --</option>
                                </select>
                                <span class="help-text">Select the specific location where the child was born</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="barangay">
                                    Barangay <span class="required">*</span>
                                </label>
                                <select id="barangay" name="barangay" required>
                                    <option value="">-- Select Barangay --</option>
                                </select>
                                <span class="help-text">Select the barangay where the birth occurred</span>
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
                    <div class="form-section" id="birth_section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="baby"></i>
                                Birth Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="child_sex">
                                    Sex <span class="required">*</span>
                                </label>
                                <select id="child_sex" name="child_sex" required>
                                    <option value="">-- Select Sex --</option>
                                    <?php
                                    $sexes = ['Male', 'Female'];
                                    foreach ($sexes as $sex) {
                                        $selected = ($edit_mode && isset($record['child_sex']) && $record['child_sex'] === $sex) ? 'selected' : '';
                                        echo "<option value='$sex' $selected>$sex</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="legitimacy_status">
                                    Legitimacy Status <span class="required">*</span>
                                </label>
                                <select id="legitimacy_status" name="legitimacy_status" required>
                                    <option value="">-- Select Status --</option>
                                    <?php
                                    $statuses = ['Legitimate', 'Illegitimate'];
                                    foreach ($statuses as $status) {
                                        $selected = ($edit_mode && isset($record['legitimacy_status']) && $record['legitimacy_status'] === $status) ? 'selected' : '';
                                        echo "<option value='$status' $selected>$status</option>";
                                    }
                                    ?>
                                </select>
                            </div>
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
                    <div class="form-section" id="mother_section">
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

                            <div class="form-group">
                                <label for="mother_citizenship">
                                    Citizenship
                                </label>
                                <?php
                                $citizenship_options = ['Filipino', 'American', 'Chinese', 'Japanese', 'Korean', 'British', 'Australian', 'Canadian', 'Indian', 'Other'];
                                $mother_cit_val = $edit_mode ? ($record['mother_citizenship'] ?? '') : '';
                                $mother_cit_is_other = $mother_cit_val !== '' && !in_array($mother_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="mother_citizenship" name="mother_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($mother_cit_is_other && $opt === 'Other') || (!$mother_cit_is_other && $mother_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="mother_citizenship_other_group" style="display: <?php echo $mother_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="mother_citizenship_other">
                                    Specify Citizenship
                                </label>
                                <input
                                    type="text"
                                    id="mother_citizenship_other"
                                    name="mother_citizenship_other"
                                    placeholder="Please specify"
                                    value="<?php echo $mother_cit_is_other ? htmlspecialchars($mother_cit_val) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Father's Information Section -->
                    <div class="form-section" id="father_section" style="<?php echo ($edit_mode && isset($record['legitimacy_status']) && $record['legitimacy_status'] === 'Illegitimate') ? 'display:none;' : ''; ?>">
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

                            <div class="form-group">
                                <label for="father_citizenship">
                                    Citizenship
                                </label>
                                <?php
                                $father_cit_val = $edit_mode ? ($record['father_citizenship'] ?? '') : '';
                                $father_cit_is_other = $father_cit_val !== '' && !in_array($father_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="father_citizenship" name="father_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($father_cit_is_other && $opt === 'Other') || (!$father_cit_is_other && $father_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="father_citizenship_other_group" style="display: <?php echo $father_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="father_citizenship_other">
                                    Specify Citizenship
                                </label>
                                <input
                                    type="text"
                                    id="father_citizenship_other"
                                    name="father_citizenship_other"
                                    placeholder="Please specify"
                                    value="<?php echo $father_cit_is_other ? htmlspecialchars($father_cit_val) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Marriage Information Section (hidden when illegitimate) -->
                    <div class="form-section" id="marriage_section" style="<?php echo ($edit_mode && isset($record['legitimacy_status']) && $record['legitimacy_status'] === 'Illegitimate') ? 'display:none;' : ''; ?>">
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
                        <iframe id="pdfPreview" src="../api/serve_pdf.php?file=<?php echo urlencode($record['pdf_filename']); ?>"></iframe>
                    </div>
                    <div class="pdf-info">
                        <i data-lucide="info"></i>
                        <span>Current File: <span class="pdf-filename"><?php echo htmlspecialchars(basename($record['pdf_filename'])); ?></span></span>
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

    <!-- Birth Certificate Specific Logic -->
    <script>
        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'birth',
            apiEndpoint: '../api/certificate_of_live_birth_save.php',
            updateEndpoint: '../api/certificate_of_live_birth_update.php'
        });

        // Barangay list for Baggao, Cagayan
        const barangays = [
            'Adaoag', 'Agaman (Proper)', 'Agaman Norte', 'Agaman Sur', 'Alba', 'Annayatan',
            'Asassi', 'Asinga-Via', 'Awallan', 'Bacagan', 'Bagunot', 'Barsat East',
            'Barsat West', 'Bitag Grande', 'Bitag Pequeño', 'Bunugan', 'C. Verzosa (Valley Cove)',
            'Canagatan', 'Carupian', 'Catugay', 'Dabbac Grande', 'Dalin', 'Dalla',
            'Hacienda Intal', 'Ibulo', 'Imurung', 'J. Pallagao', 'Lasilat', 'Mabini',
            'Masical', 'Mocag', 'Nangalinan', 'Poblacion (Centro)', 'Remus', 'San Antonio',
            'San Francisco', 'San Isidro', 'San Jose', 'San Miguel', 'San Vicente',
            'Santa Margarita', 'Santor', 'Taguing', 'Taguntungan', 'Tallang', 'Taytay',
            'Temblique', 'Tungel'
        ];

        const hospitals = [
            'Baggao District Hospital',
            'Municipal Health Office'
        ];

        const healthCenters = [
            'Baggao Rural Health Unit',
            'Barangay Health Station'
        ];

        // Populate Barangay dropdown
        const barangaySelect = document.getElementById('barangay');
        barangays.forEach(brgy => {
            const option = document.createElement('option');
            option.value = brgy;
            option.textContent = brgy;
            barangaySelect.appendChild(option);
        });

        // Set barangay value in edit mode
        <?php if ($edit_mode && !empty($record['barangay'])): ?>
        barangaySelect.value = '<?php echo htmlspecialchars($record['barangay']); ?>';
        <?php endif; ?>

        // Place of Birth Cascading Dropdown Logic
        const placeTypeSelect = document.getElementById('place_type');
        const placeOfBirthGroup = document.getElementById('child_place_of_birth_group');
        const placeOfBirthSelect = document.getElementById('child_place_of_birth');
        const placeLabel = document.getElementById('place_label');

        // Handle place type change
        placeTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;

            if (selectedType && selectedType !== 'Home' && selectedType !== 'Other') {
                placeOfBirthSelect.value = '';

                Notiflix.Loading.pulse('Loading locations...', {
                    svgColor: '#0d6efd',
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    messageColor: '#ffffff'
                });

                setTimeout(() => {
                    placeOfBirthSelect.innerHTML = '<option value="">-- Select Location --</option>';

                    let locations = [];
                    if (selectedType === 'Hospital/Clinic') {
                        locations = hospitals;
                        placeLabel.textContent = 'Hospital/Clinic';
                    } else if (selectedType === 'Barangay Health Center') {
                        locations = healthCenters;
                        placeLabel.textContent = 'Health Center';
                    }

                    locations.forEach(location => {
                        const option = document.createElement('option');
                        option.value = location;
                        option.textContent = location;
                        placeOfBirthSelect.appendChild(option);
                    });

                    placeOfBirthGroup.style.display = 'block';
                    placeOfBirthSelect.required = true;

                    Notiflix.Loading.remove();
                    Notiflix.Notify.success(`${locations.length} locations loaded!`, {
                        timeout: 2000,
                        position: 'right-top'
                    });
                }, 300);
            } else if (selectedType === 'Home' || selectedType === 'Other') {
                // For Home/Other, hide location dropdown - barangay is enough
                placeOfBirthGroup.style.display = 'none';
                placeOfBirthSelect.required = false;
                placeOfBirthSelect.value = '';
                placeOfBirthSelect.innerHTML = '<option value="">-- Select Location --</option>';
            } else {
                placeOfBirthGroup.style.display = 'none';
                placeOfBirthSelect.required = false;
                placeOfBirthSelect.value = '';
                placeOfBirthSelect.innerHTML = '<option value="">-- Select Location --</option>';
            }
        });

        // Handle edit mode - populate dropdowns if editing
        <?php if ($edit_mode && !empty($record['child_place_of_birth'])): ?>
        (function() {
            const savedLocation = '<?php echo htmlspecialchars($record['child_place_of_birth']); ?>';
            const savedPlaceType = '<?php echo htmlspecialchars($record['place_type'] ?? ''); ?>';

            if (savedPlaceType && placeTypeSelect.value !== savedPlaceType) {
                placeTypeSelect.value = savedPlaceType;
            }

            if (savedPlaceType) {
                placeTypeSelect.dispatchEvent(new Event('change'));
                setTimeout(() => {
                    placeOfBirthSelect.value = savedLocation;
                }, 450);
            }
        })();
        <?php endif; ?>

        // Legitimacy Status - Show/Hide Father & Marriage sections
        const legitimacySelect = document.getElementById('legitimacy_status');
        const fatherSection = document.getElementById('father_section');
        const marriageSection = document.getElementById('marriage_section');
        const fatherProgressStep = document.querySelector('.progress-step[data-section="father_section"]');
        const marriageProgressStep = document.querySelector('.progress-step[data-section="marriage_section"]');

        legitimacySelect.addEventListener('change', function() {
            const isIllegitimate = this.value === 'Illegitimate';

            if (isIllegitimate) {
                // Hide father and marriage sections with animation
                fatherSection.style.display = 'none';
                marriageSection.style.display = 'none';

                // Remove required from hidden fields
                fatherSection.querySelectorAll('[required]').forEach(el => {
                    el.removeAttribute('required');
                    el.dataset.wasRequired = 'true';
                });
                marriageSection.querySelectorAll('[required]').forEach(el => {
                    el.removeAttribute('required');
                    el.dataset.wasRequired = 'true';
                });

                // Dim progress steps
                if (fatherProgressStep) fatherProgressStep.style.opacity = '0.4';
                if (marriageProgressStep) marriageProgressStep.style.opacity = '0.4';

                Notiflix.Notify.info('Father and Marriage sections hidden for illegitimate status.', {
                    timeout: 3000,
                    position: 'right-top'
                });
            } else {
                // Show father and marriage sections
                fatherSection.style.display = '';
                marriageSection.style.display = '';

                // Restore required fields
                fatherSection.querySelectorAll('[data-was-required]').forEach(el => {
                    el.setAttribute('required', '');
                    delete el.dataset.wasRequired;
                });
                marriageSection.querySelectorAll('[data-was-required]').forEach(el => {
                    el.setAttribute('required', '');
                    delete el.dataset.wasRequired;
                });

                // Restore progress steps
                if (fatherProgressStep) fatherProgressStep.style.opacity = '';
                if (marriageProgressStep) marriageProgressStep.style.opacity = '';
            }

            // Update progress indicator
            updateFormProgress();
        });

        // Sticky detection for progress bar
        (function() {
            const progressBar = document.getElementById('formProgressBar');
            if (!progressBar) return;

            const observer = new IntersectionObserver(
                ([entry]) => {
                    progressBar.classList.toggle('is-stuck', !entry.isIntersecting);
                },
                { threshold: [1], rootMargin: '-1px 0px 0px 0px' }
            );

            // Create a sentinel element right before the progress bar
            const sentinel = document.createElement('div');
            sentinel.style.height = '1px';
            sentinel.style.marginBottom = '-1px';
            progressBar.parentNode.insertBefore(sentinel, progressBar);
            observer.observe(sentinel);
        })();

        // Form Progress Indicator Logic
        function updateFormProgress() {
            const sections = document.querySelectorAll('.form-section[id]');
            const steps = document.querySelectorAll('.progress-step');
            const connectors = document.querySelectorAll('.progress-connector');

            let totalFilledAll = 0;
            let totalRequiredAll = 0;

            sections.forEach((section, index) => {
                if (section.style.display === 'none') return;

                const step = steps[index];
                const inputs = section.querySelectorAll('input[required], select[required]');
                let filledCount = 0;
                let totalCount = inputs.length;

                inputs.forEach(input => {
                    if (input.value && input.value.trim() !== '') {
                        filledCount++;
                    }
                });

                totalFilledAll += filledCount;
                totalRequiredAll += totalCount;

                if (step) {
                    step.classList.remove('active', 'completed');
                    if (totalCount > 0 && filledCount === totalCount) {
                        step.classList.add('completed');
                        if (connectors[index]) connectors[index].classList.add('completed');
                    } else if (filledCount > 0) {
                        step.classList.add('active');
                    }
                    if (connectors[index] && !(totalCount > 0 && filledCount === totalCount)) {
                        connectors[index].classList.remove('completed');
                    }
                }
            });

            // Update overall progress bar
            const percent = totalRequiredAll > 0 ? Math.round((totalFilledAll / totalRequiredAll) * 100) : 0;
            const fillEl = document.getElementById('progressOverallFill');
            const percentEl = document.getElementById('progressPercent');
            if (fillEl) fillEl.style.width = percent + '%';
            if (percentEl) percentEl.textContent = percent + '%';
        }

        // Click on progress step to scroll to section (offset for sticky bar)
        document.querySelectorAll('.progress-step').forEach(step => {
            step.addEventListener('click', function() {
                const sectionId = this.dataset.section;
                const section = document.getElementById(sectionId);
                if (section && section.style.display !== 'none') {
                    const progressBar = document.getElementById('formProgressBar');
                    const offset = progressBar ? progressBar.offsetHeight + 10 : 0;
                    const top = section.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        });

        // Listen for input changes to update progress
        document.getElementById('certificateForm').addEventListener('input', updateFormProgress);
        document.getElementById('certificateForm').addEventListener('change', updateFormProgress);

        // Initial progress update
        updateFormProgress();

        // Type of Birth "Other" toggle
        document.getElementById('type_of_birth').addEventListener('change', function() {
            const otherGroup = document.getElementById('type_of_birth_other_group');
            const otherInput = document.getElementById('type_of_birth_other');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.focus();
            } else {
                otherGroup.style.display = 'none';
                otherInput.value = '';
            }
        });

        // Birth Order "Other" toggle
        document.getElementById('birth_order').addEventListener('change', function() {
            const otherGroup = document.getElementById('birth_order_other_group');
            const otherInput = document.getElementById('birth_order_other');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.focus();
            } else {
                otherGroup.style.display = 'none';
                otherInput.value = '';
            }
        });

        // Citizenship "Other" toggle — Mother
        document.getElementById('mother_citizenship').addEventListener('change', function() {
            const otherGroup = document.getElementById('mother_citizenship_other_group');
            const otherInput = document.getElementById('mother_citizenship_other');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.focus();
            } else {
                otherGroup.style.display = 'none';
                otherInput.value = '';
            }
        });

        // Citizenship "Other" toggle — Father
        document.getElementById('father_citizenship').addEventListener('change', function() {
            const otherGroup = document.getElementById('father_citizenship_other_group');
            const otherInput = document.getElementById('father_citizenship_other');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.focus();
            } else {
                otherGroup.style.display = 'none';
                otherInput.value = '';
            }
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
    <script src="<?= asset_url('pdfjs') ?>"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= asset_url("pdfjs_worker") ?>';</script>
    <script src="<?= asset_url('tesseractjs') ?>"></script>
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
            formType: 'birth'
        });
    </script>
</body>
</html>
