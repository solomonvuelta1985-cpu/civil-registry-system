<?php
/**
 * Certificate of Marriage - Entry Form (PHP Version)
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
$required_permission = $edit_mode_check ? 'marriage_edit' : 'marriage_create';
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
        $stmt = $pdo->prepare("SELECT * FROM certificate_of_marriage WHERE id = :id AND status = 'Active'");
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
    <title>Marriage Certificate - Civil Registry System</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

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
            margin: 0 0 clamp(15px, 2.5vw, 20px) 0;
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
            flex-wrap: wrap;
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
        <div class="page-container">
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

            <!-- Form Type Indicator -->
            <div class="form-type-indicator form-marriage">
                <div class="form-type-icon">
                    <i data-lucide="heart"></i>
                </div>
                <div class="form-type-info">
                    <h2 class="form-type-title">
                        <?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Marriage
                        <span class="form-type-badge">Marriage Record</span>
                    </h2>
                    <p class="form-type-subtitle">Complete the form below to register a marriage certificate</p>
                </div>
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
                                placeholder="Enter registry number (e.g., REG-2025-00001)"
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

                    <!-- Husband's Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="user"></i>
                                Husband's Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="husband_first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="husband_first_name"
                                    name="husband_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_first_name']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="husband_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="husband_middle_name"
                                    name="husband_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="husband_last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="husband_last_name"
                                    name="husband_last_name"
                                    required
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_last_name']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="husband_date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="husband_date_of_birth"
                                    name="husband_date_of_birth"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_date_of_birth']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="husband_place_of_birth">
                                    Place of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="husband_place_of_birth"
                                    name="husband_place_of_birth"
                                    required
                                    placeholder="Enter place of birth"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_place_of_birth']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="husband_residence">
                                Residence <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                id="husband_residence"
                                name="husband_residence"
                                required
                                placeholder="Enter complete address"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['husband_residence']) : ''; ?>"
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="husband_father_name">
                                    Name of Father
                                </label>
                                <input
                                    type="text"
                                    id="husband_father_name"
                                    name="husband_father_name"
                                    placeholder="Enter father's full name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_father_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="husband_father_residence">
                                    Father's Residence
                                </label>
                                <input
                                    type="text"
                                    id="husband_father_residence"
                                    name="husband_father_residence"
                                    placeholder="Enter father's address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_father_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="husband_mother_name">
                                    Name of Mother
                                </label>
                                <input
                                    type="text"
                                    id="husband_mother_name"
                                    name="husband_mother_name"
                                    placeholder="Enter mother's full name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_mother_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="husband_mother_residence">
                                    Mother's Residence
                                </label>
                                <input
                                    type="text"
                                    id="husband_mother_residence"
                                    name="husband_mother_residence"
                                    placeholder="Enter mother's address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['husband_mother_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Wife's Information Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="user-check"></i>
                                Wife's Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="wife_first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="wife_first_name"
                                    name="wife_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_first_name']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="wife_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="wife_middle_name"
                                    name="wife_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="wife_last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="wife_last_name"
                                    name="wife_last_name"
                                    required
                                    placeholder="Enter maiden last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_last_name']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="wife_date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="wife_date_of_birth"
                                    name="wife_date_of_birth"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_date_of_birth']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="wife_place_of_birth">
                                    Place of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="wife_place_of_birth"
                                    name="wife_place_of_birth"
                                    required
                                    placeholder="Enter place of birth"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_place_of_birth']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="wife_residence">
                                Residence <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                id="wife_residence"
                                name="wife_residence"
                                required
                                placeholder="Enter complete address"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['wife_residence']) : ''; ?>"
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="wife_father_name">
                                    Name of Father
                                </label>
                                <input
                                    type="text"
                                    id="wife_father_name"
                                    name="wife_father_name"
                                    placeholder="Enter father's full name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_father_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="wife_father_residence">
                                    Father's Residence
                                </label>
                                <input
                                    type="text"
                                    id="wife_father_residence"
                                    name="wife_father_residence"
                                    placeholder="Enter father's address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_father_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="wife_mother_name">
                                    Name of Mother
                                </label>
                                <input
                                    type="text"
                                    id="wife_mother_name"
                                    name="wife_mother_name"
                                    placeholder="Enter mother's full name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_mother_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="wife_mother_residence">
                                    Mother's Residence
                                </label>
                                <input
                                    type="text"
                                    id="wife_mother_residence"
                                    name="wife_mother_residence"
                                    placeholder="Enter mother's address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['wife_mother_residence'] ?? '') : ''; ?>"
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
                                    Date of Marriage <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="date_of_marriage"
                                    name="date_of_marriage"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['date_of_marriage']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="place_of_marriage">
                                    Place of Marriage <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="place_of_marriage"
                                    name="place_of_marriage"
                                    required
                                    placeholder="Enter place of marriage"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['place_of_marriage']) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="button-group sticky-buttons">
                        <button type="submit" class="btn btn-primary" aria-label="Save certificate record">
                            <i data-lucide="save" aria-hidden="true"></i>
                            <span><?php echo $edit_mode ? 'Update Record' : 'Save Record'; ?></span>
                        </button>
                        <?php if (!$edit_mode): ?>
                        <button type="button" class="btn btn-success" id="saveAndNewBtn" aria-label="Save this record and create another">
                            <i data-lucide="plus-circle" aria-hidden="true"></i>
                            <span>Save & Add New</span>
                        </button>
                        <?php endif; ?>
                        <button type="reset" class="btn btn-danger" aria-label="Reset form to empty state">
                            <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                            <span>Reset Form</span>
                        </button>
                        <a href="../admin/dashboard.php" class="btn btn-secondary" data-action="back" aria-label="Return to dashboard">
                            <i data-lucide="arrow-left" aria-hidden="true"></i>
                            <span>Back to Dashboard</span>
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

        </div> <!-- Close dashboard-container -->
    </div> <!-- Close main-content -->
</div> <!-- Close page-wrapper -->

    <!-- Shared Certificate Form Handler -->
    <script src="../assets/js/certificate-form-handler.js"></script>

    <!-- Marriage Certificate Specific Logic -->
    <script>
        const editMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'marriage',
            apiEndpoint: '../api/certificate_of_marriage_save.php'
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

    <!-- Initialize OCR Modal -->
    <script>
        // Initialize professional modal OCR interface
        window.ocrModal = new OCRModal({
            autoProcess: true,
            autoFill: false,
            confidenceThreshold: 75,
            formType: 'marriage'
        });

        // Form Skeleton Loading on Page Load
        document.addEventListener('DOMContentLoaded', function() {
            const formColumn = document.querySelector('.form-column');
            if (!formColumn) return;

            // Add class to prevent scrollbars
            document.body.classList.add('skeleton-loading');
            formColumn.classList.add('skeleton-loading-active');

            // Find ALL input fields, selects, and textareas in the form
            const allInputs = formColumn.querySelectorAll('input[type="text"], input[type="date"], input[type="datetime-local"], select, textarea');

            // Hide all inputs and create skeleton for each
            allInputs.forEach(input => {
                // Hide the real input using visibility instead of position absolute
                input.style.visibility = 'hidden';
                input.style.height = '0';
                input.style.margin = '0';
                input.style.padding = '0';
                input.style.border = 'none';

                // Create skeleton placeholder
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton skeleton-input';

                // Insert skeleton before the input
                input.parentNode.insertBefore(skeleton, input);
            });

            // Also add skeleton to labels
            const allLabels = formColumn.querySelectorAll('label');
            allLabels.forEach(label => {
                label.style.opacity = '0';

                const labelSkeleton = document.createElement('div');
                labelSkeleton.className = 'skeleton skeleton-label';
                label.parentNode.insertBefore(labelSkeleton, label);
            });

            // Add skeleton to section titles
            const sectionTitles = formColumn.querySelectorAll('.section-title');
            sectionTitles.forEach(title => {
                title.style.opacity = '0';

                const titleSkeleton = document.createElement('div');
                titleSkeleton.className = 'skeleton skeleton-section-title';
                titleSkeleton.style.marginBottom = '12px';
                title.parentNode.insertBefore(titleSkeleton, title);
            });

            // Add skeleton to help text
            const helpTexts = formColumn.querySelectorAll('.help-text');
            helpTexts.forEach(helpText => {
                helpText.style.opacity = '0';

                const helpSkeleton = document.createElement('div');
                helpSkeleton.className = 'skeleton skeleton-help-text';
                helpText.parentNode.insertBefore(helpSkeleton, helpText);
            });

            // Add skeleton to PDF column
            const pdfColumn = document.querySelector('.pdf-column');
            if (pdfColumn) {
                // Hide PDF preview title
                const pdfTitle = pdfColumn.querySelector('.pdf-preview-title');
                if (pdfTitle) {
                    pdfTitle.style.opacity = '0';
                    const pdfTitleSkeleton = document.createElement('div');
                    pdfTitleSkeleton.className = 'skeleton skeleton-pdf-header';
                    pdfTitle.parentNode.insertBefore(pdfTitleSkeleton, pdfTitle);
                }

                // Hide PDF upload/preview area
                const pdfUploadArea = pdfColumn.querySelector('.pdf-upload-area, .pdf-preview-container');
                if (pdfUploadArea) {
                    pdfUploadArea.style.opacity = '0';
                    const pdfAreaSkeleton = document.createElement('div');
                    pdfAreaSkeleton.className = 'skeleton skeleton-pdf-area';
                    pdfUploadArea.parentNode.insertBefore(pdfAreaSkeleton, pdfUploadArea);
                }

                // Hide PDF labels in upload section
                const pdfLabels = pdfColumn.querySelectorAll('label');
                pdfLabels.forEach(label => {
                    label.style.opacity = '0';
                    const labelSkeleton = document.createElement('div');
                    labelSkeleton.className = 'skeleton skeleton-label';
                    label.parentNode.insertBefore(labelSkeleton, label);
                });

                // Hide PDF help texts
                const pdfHelpTexts = pdfColumn.querySelectorAll('.help-text');
                pdfHelpTexts.forEach(helpText => {
                    helpText.style.opacity = '0';
                    const helpSkeleton = document.createElement('div');
                    helpSkeleton.className = 'skeleton skeleton-help-text';
                    helpText.parentNode.insertBefore(helpSkeleton, helpText);
                });

                // Hide toggle PDF button
                const togglePdfBtn = pdfColumn.querySelector('#togglePdfBtn, .toggle-pdf-btn');
                if (togglePdfBtn) {
                    togglePdfBtn.style.opacity = '0';
                    const btnSkeleton = document.createElement('div');
                    btnSkeleton.className = 'skeleton skeleton-toggle-btn';
                    togglePdfBtn.parentNode.insertBefore(btnSkeleton, togglePdfBtn);
                }

                // Hide file input and scan button in upload-scanner-container
                const uploadContainer = pdfColumn.querySelector('.upload-scanner-container');
                if (uploadContainer) {
                    const fileInput = uploadContainer.querySelector('input[type="file"]');
                    const scanBtn = uploadContainer.querySelector('.btn-scan, #scanDocumentBtn');

                    if (fileInput) {
                        fileInput.style.opacity = '0';
                        const fileSkeleton = document.createElement('div');
                        fileSkeleton.className = 'skeleton skeleton-button';
                        fileSkeleton.style.flex = '1';
                        fileInput.parentNode.insertBefore(fileSkeleton, fileInput);
                    }

                    if (scanBtn) {
                        scanBtn.style.opacity = '0';
                        const scanSkeleton = document.createElement('div');
                        scanSkeleton.className = 'skeleton skeleton-button';
                        scanBtn.parentNode.insertBefore(scanSkeleton, scanBtn);
                    }
                }
            }

            // Remove all skeletons and show real content after delay
            setTimeout(() => {
                // Remove scrollbar prevention classes
                document.body.classList.remove('skeleton-loading');
                formColumn.classList.remove('skeleton-loading-active');

                // Remove all skeleton elements
                const skeletons = document.querySelectorAll('.skeleton');
                skeletons.forEach(skeleton => skeleton.remove());

                // Show all real inputs with fade effect
                allInputs.forEach(input => {
                    input.style.visibility = 'visible';
                    input.style.height = '';
                    input.style.margin = '';
                    input.style.padding = '';
                    input.style.border = '';
                    input.style.opacity = '0';
                    input.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        input.style.opacity = '1';
                    }, 10);
                });

                // Show all labels with fade effect
                allLabels.forEach(label => {
                    label.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        label.style.opacity = '1';
                    }, 10);
                });

                // Show all section titles with fade effect
                sectionTitles.forEach(title => {
                    title.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        title.style.opacity = '1';
                    }, 10);
                });

                // Show all help texts with fade effect
                helpTexts.forEach(helpText => {
                    helpText.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        helpText.style.opacity = '1';
                    }, 10);
                });

                // Show PDF column elements with fade effect
                if (pdfColumn) {
                    const pdfTitle = pdfColumn.querySelector('.pdf-preview-title');
                    const pdfUploadArea = pdfColumn.querySelector('.pdf-upload-area, .pdf-preview-container');
                    const pdfLabels = pdfColumn.querySelectorAll('label');
                    const pdfHelpTexts = pdfColumn.querySelectorAll('.help-text');
                    const togglePdfBtn = pdfColumn.querySelector('#togglePdfBtn, .toggle-pdf-btn');
                    const uploadContainer = pdfColumn.querySelector('.upload-scanner-container');

                    if (pdfTitle) {
                        pdfTitle.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => { pdfTitle.style.opacity = '1'; }, 10);
                    }
                    if (pdfUploadArea) {
                        pdfUploadArea.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => { pdfUploadArea.style.opacity = '1'; }, 10);
                    }
                    pdfLabels.forEach(label => {
                        label.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => { label.style.opacity = '1'; }, 10);
                    });
                    pdfHelpTexts.forEach(helpText => {
                        helpText.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => { helpText.style.opacity = '1'; }, 10);
                    });
                    if (togglePdfBtn) {
                        togglePdfBtn.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => { togglePdfBtn.style.opacity = '1'; }, 10);
                    }
                    if (uploadContainer) {
                        const fileInput = uploadContainer.querySelector('input[type="file"]');
                        const scanBtn = uploadContainer.querySelector('.btn-scan, #scanDocumentBtn');
                        if (fileInput) {
                            fileInput.style.transition = 'opacity 0.3s ease';
                            setTimeout(() => { fileInput.style.opacity = '1'; }, 10);
                        }
                        if (scanBtn) {
                            scanBtn.style.transition = 'opacity 0.3s ease';
                            setTimeout(() => { scanBtn.style.opacity = '1'; }, 10);
                        }
                    }
                }
            }, 400);
        });
    </script>
</body>
</html>
