<?php
/**
 * Certificate of Live Birth - Entry Form (PHP Version)
 * Includes database connectivity and server-side processing
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

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
    <?= csrfTokenMeta() ?>
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

    <!-- Shared Certificate Form Styles -->
    <link rel="stylesheet" href="../assets/css/certificate-forms-shared.css">
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
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
                const requiredInputs = section.querySelectorAll('input[required], select[required]');
                let filledCount = 0;
                let totalCount = requiredInputs.length;

                requiredInputs.forEach(input => {
                    if (input.value && input.value.trim() !== '') {
                        filledCount++;
                    }
                });

                totalFilledAll += filledCount;
                totalRequiredAll += totalCount;

                // For sections with no required fields, check if any field is filled
                let sectionHasData = false;
                if (totalCount === 0) {
                    const allInputs = section.querySelectorAll('input, select, textarea');
                    allInputs.forEach(input => {
                        if (input.value && input.value.trim() !== '' && input.type !== 'hidden') {
                            sectionHasData = true;
                        }
                    });
                }

                if (step) {
                    step.classList.remove('active', 'completed');
                    if ((totalCount > 0 && filledCount === totalCount) || (totalCount === 0 && sectionHasData)) {
                        step.classList.add('completed');
                        if (connectors[index]) connectors[index].classList.add('completed');
                    } else if (filledCount > 0) {
                        step.classList.add('active');
                    }
                    if (connectors[index] && !((totalCount > 0 && filledCount === totalCount) || (totalCount === 0 && sectionHasData))) {
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
