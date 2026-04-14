<?php
/**
 * Certificate of Marriage - Entry Form (PHP Version)
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

// Partial date mode state (for date_of_registration)
$partial_date_mode   = false;
$partial_date_format = 'full';
$partial_date_month  = '';
$partial_date_year   = '';
$partial_date_day    = '';
if ($edit_mode && $record) {
    $fmt = $record['date_of_registration_format'] ?? 'full';
    if ($fmt !== 'full') {
        $partial_date_mode   = true;
        $partial_date_format = $fmt;
        $partial_date_month  = $record['date_of_registration_partial_month'] ?? '';
        $partial_date_year   = $record['date_of_registration_partial_year'] ?? '';
        $partial_date_day    = $record['date_of_registration_partial_day'] ?? '';
        if ($fmt === 'month_year' && !empty($record['date_of_registration'])) {
            if (!$partial_date_month) $partial_date_month = date('n', strtotime($record['date_of_registration']));
            if (!$partial_date_year)  $partial_date_year  = date('Y', strtotime($record['date_of_registration']));
        }
    }
}

// Partial DOB state helper
$init_partial_dob = function(?array $rec, string $prefix) {
    $state = ['mode' => false, 'format' => 'full', 'month' => '', 'year' => '', 'day' => ''];
    if (!$rec) return $state;
    $fmtKey   = $prefix . '_format';
    $fmt      = $rec[$fmtKey] ?? 'full';
    if ($fmt === 'full') return $state;
    $state['mode']   = true;
    $state['format'] = $fmt;
    $state['month']  = $rec[$prefix . '_partial_month'] ?? '';
    $state['year']   = $rec[$prefix . '_partial_year']  ?? '';
    $state['day']    = $rec[$prefix . '_partial_day']   ?? '';
    if ($fmt === 'month_year' && !empty($rec[$prefix])) {
        if (!$state['month']) $state['month'] = date('n', strtotime($rec[$prefix]));
        if (!$state['year'])  $state['year']  = date('Y', strtotime($rec[$prefix]));
    }
    return $state;
};
$husband_dob = $init_partial_dob($edit_mode ? $record : null, 'husband_date_of_birth');
$wife_dob    = $init_partial_dob($edit_mode ? $record : null, 'wife_date_of_birth');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfTokenMeta() ?>
    <title>Marriage Certificate - Civil Registry System</title>

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
    <link rel="stylesheet" href="../assets/css/certificate-forms-shared.css?v=2.1">
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
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
                <div class="form-type-info">
                    <h2 class="form-type-title">
                        <?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Marriage
                        <span class="form-type-badge"><?php echo $edit_mode ? 'Edit Mode' : 'Marriage Record'; ?></span>
                    </h2>
                    <p class="form-type-subtitle"><?php echo $edit_mode ? 'Update the marriage certificate information below' : 'Complete the form below to register a marriage certificate'; ?></p>
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
                            <div class="progress-step" data-section="husband_section">
                                <span class="step-number">2</span>
                                <span class="step-label">Husband</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="wife_section">
                                <span class="step-number">3</span>
                                <span class="step-label">Wife</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="marriage_section">
                                <span class="step-number">4</span>
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
                                placeholder="Enter registry number (e.g., REG-2025-00001)"
                                value="<?php echo $edit_mode ? htmlspecialchars($record['registry_no']) : ''; ?>"
                            >
                            <span class="help-text">Optional - Can be any format including single digit numbers</span>
                        </div>

                        <!-- Partial date toggle for Date of Registration -->
                        <div style="margin-bottom:0.75rem; padding:0.75rem 1rem; background:var(--bg-secondary, #f8f9fa); border-radius:6px; border:1px solid var(--border-color, #e0e0e0);">
                            <label style="display:flex; align-items:center; gap:0.625rem; cursor:pointer; font-weight:500; font-size:0.9rem;">
                                <input
                                    type="checkbox"
                                    id="partial_date_toggle"
                                    style="width:1rem; height:1rem; cursor:pointer;"
                                    <?php echo $partial_date_mode ? 'checked' : ''; ?>
                                >
                                Date of registration is incomplete (partial date)
                            </label>
                        </div>

                        <!-- Full date input -->
                        <div class="form-group" id="full_date_group">
                            <label for="date_of_registration">
                                Date of Registration <span class="required">*</span>
                            </label>
                            <input
                                type="date"
                                id="date_of_registration"
                                name="date_of_registration"
                                <?php echo !$partial_date_mode ? 'required' : 'disabled'; ?>
                                value="<?php echo ($edit_mode && !$partial_date_mode && !empty($record['date_of_registration'])) ? date('Y-m-d', strtotime($record['date_of_registration'])) : ''; ?>"
                            >
                        </div>

                        <!-- Partial date inputs -->
                        <div id="partial_date_group" style="<?php echo $partial_date_mode ? '' : 'display:none;'; ?>">
                            <div class="form-group">
                                <label for="partial_date_type">Date Type <span class="required">*</span></label>
                                <select id="partial_date_type" name="partial_date_type" <?php echo $partial_date_mode ? '' : 'disabled'; ?>>
                                    <option value="">-- Select Type --</option>
                                    <option value="month_only"  <?= $partial_date_format === 'month_only'  ? 'selected' : '' ?>>Month Only</option>
                                    <option value="year_only"   <?= $partial_date_format === 'year_only'   ? 'selected' : '' ?>>Year Only</option>
                                    <option value="month_year"  <?= $partial_date_format === 'month_year'  ? 'selected' : '' ?>>Month and Year Only</option>
                                    <option value="month_day"   <?= $partial_date_format === 'month_day'   ? 'selected' : '' ?>>Month and Date Only</option>
                                    <option value="na"          <?= $partial_date_format === 'na'          ? 'selected' : '' ?>>N/A (no date)</option>
                                </select>
                            </div>
                            <div class="form-group" id="partial_month_group" style="<?php echo in_array($partial_date_format, ['month_only','month_year','month_day']) ? '' : 'display:none;'; ?>">
                                <label for="partial_date_month">Month</label>
                                <select id="partial_date_month" name="partial_date_month" <?= in_array($partial_date_format, ['month_only','month_year','month_day']) ? '' : 'disabled' ?>>
                                    <option value="">-- Select Month --</option>
                                    <?php
                                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                    foreach ($months as $i => $mname): $mval = $i + 1; ?>
                                    <option value="<?= $mval ?>" <?= ((int)$partial_date_month === $mval) ? 'selected' : '' ?>><?= $mname ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="partial_year_group" style="<?php echo in_array($partial_date_format, ['year_only','month_year']) ? '' : 'display:none;'; ?>">
                                <label for="partial_date_year">Year</label>
                                <input type="number" id="partial_date_year" name="partial_date_year"
                                    min="1800" max="<?= date('Y') + 1 ?>" placeholder="e.g. 1995"
                                    value="<?= htmlspecialchars($partial_date_year ?? '') ?>"
                                    <?= in_array($partial_date_format, ['year_only','month_year']) ? '' : 'disabled' ?>>
                            </div>
                            <div class="form-group" id="partial_day_group" style="<?php echo ($partial_date_format === 'month_day') ? '' : 'display:none;'; ?>">
                                <label for="partial_date_day">Day</label>
                                <select id="partial_date_day" name="partial_date_day" <?= ($partial_date_format === 'month_day') ? '' : 'disabled' ?>>
                                    <option value="">-- Select Day --</option>
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?= $d ?>" <?= ((int)$partial_date_day === $d) ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="date_of_registration_format" name="date_of_registration_format" value="<?= htmlspecialchars($partial_date_format) ?>">
                    </div>

                    <!-- Husband's Information Section -->
                    <div class="form-section" id="husband_section">
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

                        <!-- Partial DOB toggle for Husband -->
                        <div style="margin-bottom:0.75rem; padding:0.75rem 1rem; background:var(--bg-secondary, #f8f9fa); border-radius:6px; border:1px solid var(--border-color, #e0e0e0);">
                            <label style="display:flex; align-items:center; gap:0.625rem; cursor:pointer; font-weight:500; font-size:0.9rem;">
                                <input type="checkbox" id="husband_dob_partial_toggle" style="width:1rem; height:1rem; cursor:pointer;" <?php echo $husband_dob['mode'] ? 'checked' : ''; ?>>
                                Husband's date of birth is incomplete (partial date)
                            </label>
                        </div>
                        <div class="form-row">
                            <div class="form-group" id="husband_dob_full_group">
                                <label for="husband_date_of_birth">
                                    Date of Birth
                                </label>
                                <input
                                    type="date"
                                    id="husband_date_of_birth"
                                    name="husband_date_of_birth"
                                    <?php echo $husband_dob['mode'] ? 'disabled' : ''; ?>
                                    value="<?php echo ($edit_mode && !$husband_dob['mode'] && !empty($record['husband_date_of_birth'])) ? htmlspecialchars(date('Y-m-d', strtotime($record['husband_date_of_birth']))) : ''; ?>"
                                >
                            </div>

                            <div id="husband_dob_partial_group" style="<?php echo $husband_dob['mode'] ? '' : 'display:none;'; ?>">
                                <div class="form-group">
                                    <label for="husband_dob_partial_type">Date Type <span class="required">*</span></label>
                                    <select id="husband_dob_partial_type" <?php echo $husband_dob['mode'] ? '' : 'disabled'; ?>>
                                        <option value="">-- Select Type --</option>
                                        <option value="month_only"  <?= $husband_dob['format'] === 'month_only'  ? 'selected' : '' ?>>Month Only</option>
                                        <option value="year_only"   <?= $husband_dob['format'] === 'year_only'   ? 'selected' : '' ?>>Year Only</option>
                                        <option value="month_year"  <?= $husband_dob['format'] === 'month_year'  ? 'selected' : '' ?>>Month and Year Only</option>
                                        <option value="month_day"   <?= $husband_dob['format'] === 'month_day'   ? 'selected' : '' ?>>Month and Date Only</option>
                                        <option value="na"          <?= $husband_dob['format'] === 'na'          ? 'selected' : '' ?>>N/A (no date)</option>
                                    </select>
                                </div>
                                <div class="form-group" id="husband_dob_partial_month_group" style="<?php echo in_array($husband_dob['format'], ['month_only','month_year','month_day']) ? '' : 'display:none;'; ?>">
                                    <label for="husband_dob_partial_month">Month</label>
                                    <select id="husband_dob_partial_month" name="husband_date_of_birth_partial_month" <?= in_array($husband_dob['format'], ['month_only','month_year','month_day']) ? '' : 'disabled' ?>>
                                        <option value="">-- Select Month --</option>
                                        <?php
                                        $h_months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                        foreach ($h_months as $i => $mname): $mval = $i + 1; ?>
                                        <option value="<?= $mval ?>" <?= ((int)$husband_dob['month'] === $mval) ? 'selected' : '' ?>><?= $mname ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" id="husband_dob_partial_year_group" style="<?php echo in_array($husband_dob['format'], ['year_only','month_year']) ? '' : 'display:none;'; ?>">
                                    <label for="husband_dob_partial_year">Year</label>
                                    <input type="number" id="husband_dob_partial_year" name="husband_date_of_birth_partial_year"
                                        min="1800" max="<?= date('Y') + 1 ?>" placeholder="e.g. 1985"
                                        value="<?= htmlspecialchars($husband_dob['year'] ?? '') ?>"
                                        <?= in_array($husband_dob['format'], ['year_only','month_year']) ? '' : 'disabled' ?>>
                                </div>
                                <div class="form-group" id="husband_dob_partial_day_group" style="<?php echo ($husband_dob['format'] === 'month_day') ? '' : 'display:none;'; ?>">
                                    <label for="husband_dob_partial_day">Day</label>
                                    <select id="husband_dob_partial_day" name="husband_date_of_birth_partial_day" <?= ($husband_dob['format'] === 'month_day') ? '' : 'disabled' ?>>
                                        <option value="">-- Select Day --</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= ((int)$husband_dob['day'] === $d) ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" id="husband_date_of_birth_format" name="husband_date_of_birth_format" value="<?= htmlspecialchars($husband_dob['format']) ?>">

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

                        <div class="form-group">
                            <label for="husband_citizenship">Citizenship</label>
                            <?php
                            $citizenship_options = ['Filipino', 'American', 'Chinese', 'Japanese', 'Korean', 'British', 'Australian', 'Canadian', 'Indian', 'Other'];
                            $husband_cit_val = $edit_mode ? ($record['husband_citizenship'] ?? '') : '';
                            $husband_cit_is_other = $husband_cit_val !== '' && !in_array($husband_cit_val, array_diff($citizenship_options, ['Other']));
                            ?>
                            <select id="husband_citizenship" name="husband_citizenship">
                                <option value="">-- Select Citizenship --</option>
                                <?php foreach ($citizenship_options as $opt):
                                    $sel = ($husband_cit_is_other && $opt === 'Other') || (!$husband_cit_is_other && $husband_cit_val === $opt) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="husband_citizenship_other_group" style="display: <?php echo $husband_cit_is_other ? 'block' : 'none'; ?>;">
                            <label for="husband_citizenship_other">Specify Citizenship</label>
                            <input
                                type="text"
                                id="husband_citizenship_other"
                                name="husband_citizenship_other"
                                placeholder="Please specify"
                                value="<?php echo $husband_cit_is_other ? htmlspecialchars($husband_cit_val) : ''; ?>"
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
                    <div class="form-section" id="wife_section">
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

                        <!-- Partial DOB toggle for Wife -->
                        <div style="margin-bottom:0.75rem; padding:0.75rem 1rem; background:var(--bg-secondary, #f8f9fa); border-radius:6px; border:1px solid var(--border-color, #e0e0e0);">
                            <label style="display:flex; align-items:center; gap:0.625rem; cursor:pointer; font-weight:500; font-size:0.9rem;">
                                <input type="checkbox" id="wife_dob_partial_toggle" style="width:1rem; height:1rem; cursor:pointer;" <?php echo $wife_dob['mode'] ? 'checked' : ''; ?>>
                                Wife's date of birth is incomplete (partial date)
                            </label>
                        </div>
                        <div class="form-row">
                            <div class="form-group" id="wife_dob_full_group">
                                <label for="wife_date_of_birth">
                                    Date of Birth
                                </label>
                                <input
                                    type="date"
                                    id="wife_date_of_birth"
                                    name="wife_date_of_birth"
                                    <?php echo $wife_dob['mode'] ? 'disabled' : ''; ?>
                                    value="<?php echo ($edit_mode && !$wife_dob['mode'] && !empty($record['wife_date_of_birth'])) ? htmlspecialchars(date('Y-m-d', strtotime($record['wife_date_of_birth']))) : ''; ?>"
                                >
                            </div>

                            <div id="wife_dob_partial_group" style="<?php echo $wife_dob['mode'] ? '' : 'display:none;'; ?>">
                                <div class="form-group">
                                    <label for="wife_dob_partial_type">Date Type <span class="required">*</span></label>
                                    <select id="wife_dob_partial_type" <?php echo $wife_dob['mode'] ? '' : 'disabled'; ?>>
                                        <option value="">-- Select Type --</option>
                                        <option value="month_only"  <?= $wife_dob['format'] === 'month_only'  ? 'selected' : '' ?>>Month Only</option>
                                        <option value="year_only"   <?= $wife_dob['format'] === 'year_only'   ? 'selected' : '' ?>>Year Only</option>
                                        <option value="month_year"  <?= $wife_dob['format'] === 'month_year'  ? 'selected' : '' ?>>Month and Year Only</option>
                                        <option value="month_day"   <?= $wife_dob['format'] === 'month_day'   ? 'selected' : '' ?>>Month and Date Only</option>
                                        <option value="na"          <?= $wife_dob['format'] === 'na'          ? 'selected' : '' ?>>N/A (no date)</option>
                                    </select>
                                </div>
                                <div class="form-group" id="wife_dob_partial_month_group" style="<?php echo in_array($wife_dob['format'], ['month_only','month_year','month_day']) ? '' : 'display:none;'; ?>">
                                    <label for="wife_dob_partial_month">Month</label>
                                    <select id="wife_dob_partial_month" name="wife_date_of_birth_partial_month" <?= in_array($wife_dob['format'], ['month_only','month_year','month_day']) ? '' : 'disabled' ?>>
                                        <option value="">-- Select Month --</option>
                                        <?php
                                        $w_months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                        foreach ($w_months as $i => $mname): $mval = $i + 1; ?>
                                        <option value="<?= $mval ?>" <?= ((int)$wife_dob['month'] === $mval) ? 'selected' : '' ?>><?= $mname ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" id="wife_dob_partial_year_group" style="<?php echo in_array($wife_dob['format'], ['year_only','month_year']) ? '' : 'display:none;'; ?>">
                                    <label for="wife_dob_partial_year">Year</label>
                                    <input type="number" id="wife_dob_partial_year" name="wife_date_of_birth_partial_year"
                                        min="1800" max="<?= date('Y') + 1 ?>" placeholder="e.g. 1985"
                                        value="<?= htmlspecialchars($wife_dob['year'] ?? '') ?>"
                                        <?= in_array($wife_dob['format'], ['year_only','month_year']) ? '' : 'disabled' ?>>
                                </div>
                                <div class="form-group" id="wife_dob_partial_day_group" style="<?php echo ($wife_dob['format'] === 'month_day') ? '' : 'display:none;'; ?>">
                                    <label for="wife_dob_partial_day">Day</label>
                                    <select id="wife_dob_partial_day" name="wife_date_of_birth_partial_day" <?= ($wife_dob['format'] === 'month_day') ? '' : 'disabled' ?>>
                                        <option value="">-- Select Day --</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= ((int)$wife_dob['day'] === $d) ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" id="wife_date_of_birth_format" name="wife_date_of_birth_format" value="<?= htmlspecialchars($wife_dob['format']) ?>">

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

                        <div class="form-group">
                            <label for="wife_citizenship">Citizenship</label>
                            <?php
                            $wife_cit_val = $edit_mode ? ($record['wife_citizenship'] ?? '') : '';
                            $wife_cit_is_other = $wife_cit_val !== '' && !in_array($wife_cit_val, array_diff($citizenship_options, ['Other']));
                            ?>
                            <select id="wife_citizenship" name="wife_citizenship">
                                <option value="">-- Select Citizenship --</option>
                                <?php foreach ($citizenship_options as $opt):
                                    $sel = ($wife_cit_is_other && $opt === 'Other') || (!$wife_cit_is_other && $wife_cit_val === $opt) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="wife_citizenship_other_group" style="display: <?php echo $wife_cit_is_other ? 'block' : 'none'; ?>;">
                            <label for="wife_citizenship_other">Specify Citizenship</label>
                            <input
                                type="text"
                                id="wife_citizenship_other"
                                name="wife_citizenship_other"
                                placeholder="Please specify"
                                value="<?php echo $wife_cit_is_other ? htmlspecialchars($wife_cit_val) : ''; ?>"
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
                    <div class="form-section" id="marriage_section">
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

                        <div class="form-group">
                            <label for="nature_of_solemnization">
                                Nature of Solemnization <span class="required">*</span>
                            </label>
                            <select id="nature_of_solemnization" name="nature_of_solemnization" required>
                                <option value="">-- Select Type --</option>
                                <?php
                                $solemnization_types = ['Church', 'Civil', 'Other Religious Sect'];
                                foreach ($solemnization_types as $type) {
                                    $selected = ($edit_mode && isset($record['nature_of_solemnization']) && $record['nature_of_solemnization'] === $type) ? 'selected' : '';
                                    echo "<option value='$type' $selected>$type</option>";
                                }
                                ?>
                            </select>
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

        <!-- Floating Toggle Button - opens PDF drawer -->
        <button type="button" id="floatingToggleBtn" class="floating-toggle-btn" title="Open PDF Upload">
            <i data-lucide="file-text"></i>
        </button>

        </div> <!-- Close dashboard-container -->
    </div> <!-- Close main-content -->
</div> <!-- Close page-wrapper -->

    <!-- Shared Certificate Form Handler -->
    <script>window.APP_BASE = '<?= rtrim(BASE_URL, '/') ?>';</script>
    <script src="../assets/js/certificate-form-handler.js?v=2.1"></script>

    <!-- Marriage Certificate Specific Logic -->
    <script>
        const editMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'marriage',
            apiEndpoint: '../api/certificate_of_marriage_save.php',
            updateEndpoint: '../api/certificate_of_marriage_update.php'
        });

        // Citizenship "Other" toggle — Husband
        document.getElementById('husband_citizenship').addEventListener('change', function() {
            const otherGroup = document.getElementById('husband_citizenship_other_group');
            const otherInput = document.getElementById('husband_citizenship_other');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.focus();
            } else {
                otherGroup.style.display = 'none';
                otherInput.value = '';
            }
        });

        // Citizenship "Other" toggle — Wife
        document.getElementById('wife_citizenship').addEventListener('change', function() {
            const otherGroup = document.getElementById('wife_citizenship_other_group');
            const otherInput = document.getElementById('wife_citizenship_other');
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

    <!-- Initialize OCR Modal -->
    <script>
        // Initialize professional modal OCR interface
        window.ocrModal = new OCRModal({
            autoProcess: true,
            autoFill: false,
            confidenceThreshold: 75,
            formType: 'marriage'
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

            const percent = totalRequiredAll > 0 ? Math.round((totalFilledAll / totalRequiredAll) * 100) : 0;
            const fillEl = document.getElementById('progressOverallFill');
            const percentEl = document.getElementById('progressPercent');
            if (fillEl) fillEl.style.width = percent + '%';
            if (percentEl) percentEl.textContent = percent + '%';
        }

        // Click on progress step to scroll to section
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

        // ── Partial Date Toggle for Date of Registration ──────────────────────
        (function() {
            const toggle       = document.getElementById('partial_date_toggle');
            const fullGroup    = document.getElementById('full_date_group');
            const partialGroup = document.getElementById('partial_date_group');
            const fullInput    = document.getElementById('date_of_registration');
            const typeSelect   = document.getElementById('partial_date_type');
            const monthGroup   = document.getElementById('partial_month_group');
            const yearGroup    = document.getElementById('partial_year_group');
            const dayGroup     = document.getElementById('partial_day_group');
            const monthSelect  = document.getElementById('partial_date_month');
            const yearInput    = document.getElementById('partial_date_year');
            const daySelect    = document.getElementById('partial_date_day');
            const formatHidden = document.getElementById('date_of_registration_format');

            if (!toggle) return;

            function applyTypeSelection(typeVal) {
                const needsMonth = (typeVal === 'month_only' || typeVal === 'month_year' || typeVal === 'month_day');
                const needsYear  = (typeVal === 'year_only'  || typeVal === 'month_year');
                const needsDay   = (typeVal === 'month_day');
                monthGroup.style.display = needsMonth ? '' : 'none';
                monthSelect.disabled     = !needsMonth;
                if (!needsMonth) monthSelect.value = '';
                yearGroup.style.display = needsYear ? '' : 'none';
                yearInput.disabled      = !needsYear;
                if (!needsYear) yearInput.value = '';
                dayGroup.style.display = needsDay ? '' : 'none';
                daySelect.disabled     = !needsDay;
                if (!needsDay) daySelect.value = '';
                formatHidden.value = typeVal || 'full';
                if (typeof updateFormProgress === 'function') updateFormProgress();
            }

            function applyPartialMode(isPartial) {
                fullGroup.style.display    = isPartial ? 'none' : '';
                fullInput.disabled         = isPartial;
                fullInput.required         = !isPartial;
                if (isPartial) fullInput.value = '';
                partialGroup.style.display = isPartial ? '' : 'none';
                typeSelect.disabled        = !isPartial;
                if (isPartial) {
                    applyTypeSelection(typeSelect.value);
                } else {
                    monthGroup.style.display = 'none';
                    yearGroup.style.display  = 'none';
                    dayGroup.style.display   = 'none';
                    monthSelect.disabled     = true;
                    yearInput.disabled       = true;
                    daySelect.disabled       = true;
                    formatHidden.value       = 'full';
                }
                if (typeof updateFormProgress === 'function') updateFormProgress();
            }

            toggle.addEventListener('change', function() { applyPartialMode(this.checked); });
            typeSelect.addEventListener('change', function() { applyTypeSelection(this.value); });
            applyPartialMode(toggle.checked);
        })();

        // ── Partial DOB Toggles (Husband + Wife) ──────────────────────────────
        function wireDobPartialToggle(prefix) {
            const toggle       = document.getElementById(prefix + '_partial_toggle');
            const fullGroup    = document.getElementById(prefix + '_full_group');
            const partialGroup = document.getElementById(prefix + '_partial_group');
            const fullInput    = document.getElementById(prefix.replace('_dob', '_date_of_birth'));
            const typeSelect   = document.getElementById(prefix + '_partial_type');
            const monthGroup   = document.getElementById(prefix + '_partial_month_group');
            const yearGroup    = document.getElementById(prefix + '_partial_year_group');
            const dayGroup     = document.getElementById(prefix + '_partial_day_group');
            const monthSelect  = document.getElementById(prefix + '_partial_month');
            const yearInput    = document.getElementById(prefix + '_partial_year');
            const daySelect    = document.getElementById(prefix + '_partial_day');
            const formatHidden = document.getElementById(prefix.replace('_dob', '_date_of_birth') + '_format');

            if (!toggle) return;

            function applyTypeSelection(typeVal) {
                const needsMonth = (typeVal === 'month_only' || typeVal === 'month_year' || typeVal === 'month_day');
                const needsYear  = (typeVal === 'year_only'  || typeVal === 'month_year');
                const needsDay   = (typeVal === 'month_day');
                monthGroup.style.display = needsMonth ? '' : 'none';
                monthSelect.disabled     = !needsMonth;
                if (!needsMonth) monthSelect.value = '';
                yearGroup.style.display = needsYear ? '' : 'none';
                yearInput.disabled      = !needsYear;
                if (!needsYear) yearInput.value = '';
                dayGroup.style.display = needsDay ? '' : 'none';
                daySelect.disabled     = !needsDay;
                if (!needsDay) daySelect.value = '';
                formatHidden.value = typeVal || 'full';
            }
            function applyPartialMode(isPartial) {
                fullGroup.style.display    = isPartial ? 'none' : '';
                fullInput.disabled         = isPartial;
                if (isPartial) fullInput.value = '';
                partialGroup.style.display = isPartial ? '' : 'none';
                typeSelect.disabled        = !isPartial;
                if (isPartial) {
                    applyTypeSelection(typeSelect.value);
                } else {
                    monthGroup.style.display = 'none';
                    yearGroup.style.display  = 'none';
                    dayGroup.style.display   = 'none';
                    monthSelect.disabled     = true;
                    yearInput.disabled       = true;
                    daySelect.disabled       = true;
                    formatHidden.value       = 'full';
                }
            }
            toggle.addEventListener('change', function() { applyPartialMode(this.checked); });
            typeSelect.addEventListener('change', function() { applyTypeSelection(this.value); });
            applyPartialMode(toggle.checked);
        }
        wireDobPartialToggle('husband_dob');
        wireDobPartialToggle('wife_dob');

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
