<?php
/**
 * Application for Marriage License - Entry Form (PHP Version)
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
$required_permission = $edit_mode_check ? 'marriage_license_edit' : 'marriage_license_create';
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
        $stmt = $pdo->prepare("SELECT * FROM application_for_marriage_license WHERE id = :id AND status = 'Active'");
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
    <title>Application for Marriage License - Civil Registry System</title>

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
            <div class="form-type-indicator form-marriage-license">
                <div class="form-type-info">
                    <h2 class="form-type-title">
                        <?php echo $edit_mode ? 'Edit' : 'New'; ?> Application for Marriage License
                        <span class="form-type-badge"><?php echo $edit_mode ? 'Edit Mode' : 'License Application'; ?></span>
                    </h2>
                    <p class="form-type-subtitle"><?php echo $edit_mode ? 'Update the marriage license application information below' : 'Complete the form below to register a marriage license application'; ?></p>
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
                            <div class="progress-step" data-section="groom_section">
                                <span class="step-number">2</span>
                                <span class="step-label">Groom</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="bride_section">
                                <span class="step-number">3</span>
                                <span class="step-label">Bride</span>
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="registry_no">
                                    Registry Number
                                </label>
                                <input
                                    type="text"
                                    id="registry_no"
                                    name="registry_no"
                                    placeholder="Enter registry number"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['registry_no']) : ''; ?>"
                                >
                                <span class="help-text">Optional - Can be any format</span>
                            </div>

                            <div class="form-group">
                                <label for="date_of_application">
                                    Date of Application <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="date_of_application"
                                    name="date_of_application"
                                    required
                                    value="<?php echo $edit_mode ? date('Y-m-d', strtotime($record['date_of_application'])) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Groom's Information Section -->
                    <div class="form-section" id="groom_section">
                        <div class="section-header groom">
                            <h2 class="section-title">
                                <i data-lucide="user"></i>
                                Groom's Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="groom_first_name"
                                    name="groom_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_first_name']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="groom_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="groom_middle_name"
                                    name="groom_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="groom_last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="groom_last_name"
                                    name="groom_last_name"
                                    required
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_last_name']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="groom_date_of_birth"
                                    name="groom_date_of_birth"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_date_of_birth']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="groom_place_of_birth">
                                    Place of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="groom_place_of_birth"
                                    name="groom_place_of_birth"
                                    required
                                    placeholder="Enter place of birth"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_place_of_birth']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_citizenship">
                                    Citizenship <span class="required">*</span>
                                </label>
                                <?php
                                $citizenship_options = ['Filipino', 'American', 'Chinese', 'Japanese', 'Korean', 'British', 'Australian', 'Canadian', 'Indian', 'Other'];
                                $groom_cit_val = $edit_mode ? ($record['groom_citizenship'] ?? '') : '';
                                $groom_cit_is_other = $groom_cit_val !== '' && !in_array($groom_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="groom_citizenship" name="groom_citizenship" required>
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($groom_cit_is_other && $opt === 'Other') || (!$groom_cit_is_other && $groom_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="groom_citizenship_other_group" style="display: <?php echo $groom_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="groom_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="groom_citizenship_other" name="groom_citizenship_other" placeholder="Please specify" value="<?php echo $groom_cit_is_other ? htmlspecialchars($groom_cit_val) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="groom_residence">
                                    Residence <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="groom_residence"
                                    name="groom_residence"
                                    required
                                    placeholder="Enter complete address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_residence']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <!-- Groom's Father Information -->
                        <h3 style="font-size: 0.9rem; color: #495057; margin: 15px 0 10px 0; padding-left: 10px; border-left: 2px solid #2196f3;">Father's Name</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_father_first_name">First Name</label>
                                <input
                                    type="text"
                                    id="groom_father_first_name"
                                    name="groom_father_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_father_first_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="groom_father_middle_name">Middle Name</label>
                                <input
                                    type="text"
                                    id="groom_father_middle_name"
                                    name="groom_father_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_father_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="groom_father_last_name">Last Name</label>
                                <input
                                    type="text"
                                    id="groom_father_last_name"
                                    name="groom_father_last_name"
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_father_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_father_citizenship">Citizenship</label>
                                <?php
                                $gf_cit_val = $edit_mode ? ($record['groom_father_citizenship'] ?? '') : '';
                                $gf_cit_is_other = $gf_cit_val !== '' && !in_array($gf_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="groom_father_citizenship" name="groom_father_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($gf_cit_is_other && $opt === 'Other') || (!$gf_cit_is_other && $gf_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="groom_father_citizenship_other_group" style="display: <?php echo $gf_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="groom_father_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="groom_father_citizenship_other" name="groom_father_citizenship_other" placeholder="Please specify" value="<?php echo $gf_cit_is_other ? htmlspecialchars($gf_cit_val) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="groom_father_residence">Residence</label>
                                <input
                                    type="text"
                                    id="groom_father_residence"
                                    name="groom_father_residence"
                                    placeholder="Enter address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_father_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <!-- Groom's Mother Information -->
                        <h3 style="font-size: 0.9rem; color: #495057; margin: 15px 0 10px 0; padding-left: 10px; border-left: 2px solid #2196f3;">Mother's Maiden Name</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_mother_first_name">First Name</label>
                                <input
                                    type="text"
                                    id="groom_mother_first_name"
                                    name="groom_mother_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_mother_first_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="groom_mother_middle_name">Middle Name</label>
                                <input
                                    type="text"
                                    id="groom_mother_middle_name"
                                    name="groom_mother_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_mother_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="groom_mother_last_name">Last Name</label>
                                <input
                                    type="text"
                                    id="groom_mother_last_name"
                                    name="groom_mother_last_name"
                                    placeholder="Enter maiden last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_mother_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="groom_mother_citizenship">Citizenship</label>
                                <?php
                                $gm_cit_val = $edit_mode ? ($record['groom_mother_citizenship'] ?? '') : '';
                                $gm_cit_is_other = $gm_cit_val !== '' && !in_array($gm_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="groom_mother_citizenship" name="groom_mother_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($gm_cit_is_other && $opt === 'Other') || (!$gm_cit_is_other && $gm_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="groom_mother_citizenship_other_group" style="display: <?php echo $gm_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="groom_mother_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="groom_mother_citizenship_other" name="groom_mother_citizenship_other" placeholder="Please specify" value="<?php echo $gm_cit_is_other ? htmlspecialchars($gm_cit_val) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="groom_mother_residence">Residence</label>
                                <input
                                    type="text"
                                    id="groom_mother_residence"
                                    name="groom_mother_residence"
                                    placeholder="Enter address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['groom_mother_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Bride's Information Section -->
                    <div class="form-section" id="bride_section">
                        <div class="section-header bride">
                            <h2 class="section-title">
                                <i data-lucide="user-check"></i>
                                Bride's Information
                            </h2>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="bride_first_name"
                                    name="bride_first_name"
                                    required
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_first_name']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="bride_middle_name">
                                    Middle Name
                                </label>
                                <input
                                    type="text"
                                    id="bride_middle_name"
                                    name="bride_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="bride_last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="bride_last_name"
                                    name="bride_last_name"
                                    required
                                    placeholder="Enter maiden last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_last_name']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_date_of_birth">
                                    Date of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="bride_date_of_birth"
                                    name="bride_date_of_birth"
                                    required
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_date_of_birth']) : ''; ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="bride_place_of_birth">
                                    Place of Birth <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="bride_place_of_birth"
                                    name="bride_place_of_birth"
                                    required
                                    placeholder="Enter place of birth"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_place_of_birth']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_citizenship">
                                    Citizenship <span class="required">*</span>
                                </label>
                                <?php
                                $bride_cit_val = $edit_mode ? ($record['bride_citizenship'] ?? '') : '';
                                $bride_cit_is_other = $bride_cit_val !== '' && !in_array($bride_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="bride_citizenship" name="bride_citizenship" required>
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($bride_cit_is_other && $opt === 'Other') || (!$bride_cit_is_other && $bride_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="bride_citizenship_other_group" style="display: <?php echo $bride_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="bride_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="bride_citizenship_other" name="bride_citizenship_other" placeholder="Please specify" value="<?php echo $bride_cit_is_other ? htmlspecialchars($bride_cit_val) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="bride_residence">
                                    Residence <span class="required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="bride_residence"
                                    name="bride_residence"
                                    required
                                    placeholder="Enter complete address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_residence']) : ''; ?>"
                                >
                            </div>
                        </div>

                        <!-- Bride's Father Information -->
                        <h3 style="font-size: 0.9rem; color: #495057; margin: 15px 0 10px 0; padding-left: 10px; border-left: 2px solid #e91e63;">Father's Name</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_father_first_name">First Name</label>
                                <input
                                    type="text"
                                    id="bride_father_first_name"
                                    name="bride_father_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_father_first_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="bride_father_middle_name">Middle Name</label>
                                <input
                                    type="text"
                                    id="bride_father_middle_name"
                                    name="bride_father_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_father_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="bride_father_last_name">Last Name</label>
                                <input
                                    type="text"
                                    id="bride_father_last_name"
                                    name="bride_father_last_name"
                                    placeholder="Enter last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_father_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_father_citizenship">Citizenship</label>
                                <?php
                                $bf_cit_val = $edit_mode ? ($record['bride_father_citizenship'] ?? '') : '';
                                $bf_cit_is_other = $bf_cit_val !== '' && !in_array($bf_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="bride_father_citizenship" name="bride_father_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($bf_cit_is_other && $opt === 'Other') || (!$bf_cit_is_other && $bf_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="bride_father_citizenship_other_group" style="display: <?php echo $bf_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="bride_father_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="bride_father_citizenship_other" name="bride_father_citizenship_other" placeholder="Please specify" value="<?php echo $bf_cit_is_other ? htmlspecialchars($bf_cit_val) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="bride_father_residence">Residence</label>
                                <input
                                    type="text"
                                    id="bride_father_residence"
                                    name="bride_father_residence"
                                    placeholder="Enter address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_father_residence'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <!-- Bride's Mother Information -->
                        <h3 style="font-size: 0.9rem; color: #495057; margin: 15px 0 10px 0; padding-left: 10px; border-left: 2px solid #e91e63;">Mother's Maiden Name</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_mother_first_name">First Name</label>
                                <input
                                    type="text"
                                    id="bride_mother_first_name"
                                    name="bride_mother_first_name"
                                    placeholder="Enter first name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_mother_first_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="bride_mother_middle_name">Middle Name</label>
                                <input
                                    type="text"
                                    id="bride_mother_middle_name"
                                    name="bride_mother_middle_name"
                                    placeholder="Enter middle name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_mother_middle_name'] ?? '') : ''; ?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="bride_mother_last_name">Last Name</label>
                                <input
                                    type="text"
                                    id="bride_mother_last_name"
                                    name="bride_mother_last_name"
                                    placeholder="Enter maiden last name"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_mother_last_name'] ?? '') : ''; ?>"
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bride_mother_citizenship">Citizenship</label>
                                <?php
                                $bm_cit_val = $edit_mode ? ($record['bride_mother_citizenship'] ?? '') : '';
                                $bm_cit_is_other = $bm_cit_val !== '' && !in_array($bm_cit_val, array_diff($citizenship_options, ['Other']));
                                ?>
                                <select id="bride_mother_citizenship" name="bride_mother_citizenship">
                                    <option value="">-- Select Citizenship --</option>
                                    <?php foreach ($citizenship_options as $opt):
                                        $sel = ($bm_cit_is_other && $opt === 'Other') || (!$bm_cit_is_other && $bm_cit_val === $opt) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $sel; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" id="bride_mother_citizenship_other_group" style="display: <?php echo $bm_cit_is_other ? 'block' : 'none'; ?>;">
                                <label for="bride_mother_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="bride_mother_citizenship_other" name="bride_mother_citizenship_other" placeholder="Please specify" value="<?php echo $bm_cit_is_other ? htmlspecialchars($bm_cit_val) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="bride_mother_residence">Residence</label>
                                <input
                                    type="text"
                                    id="bride_mother_residence"
                                    name="bride_mother_residence"
                                    placeholder="Enter address"
                                    value="<?php echo $edit_mode ? htmlspecialchars($record['bride_mother_residence'] ?? '') : ''; ?>"
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
                            Application PDF Upload
                        </h3>
                        <button type="button" id="togglePdfBtn" class="toggle-pdf-btn" title="Hide PDF Upload">
                            <i data-lucide="eye-off"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="pdf_file">
                            Upload PDF Document <?php echo !$edit_mode ? '<span class="required">*</span>' : ''; ?>
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

        </div> <!-- Close dashboard-container -->
    </div> <!-- Close main-content -->
</div> <!-- Close page-wrapper -->

    <!-- Shared Certificate Form Handler -->
    <script src="../assets/js/certificate-form-handler.js"></script>

    <!-- Marriage License Specific Logic -->
    <script>
        const editMode = <?php echo $edit_mode ? 'true' : 'false'; ?>;

        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'marriage_license',
            apiEndpoint: '../api/application_for_marriage_license_save.php',
            updateEndpoint: '../api/application_for_marriage_license_update.php'
        });

        // Citizenship "Other" toggle handlers
        (function() {
            const citizenshipFields = [
                'groom_citizenship',
                'groom_father_citizenship',
                'groom_mother_citizenship',
                'bride_citizenship',
                'bride_father_citizenship',
                'bride_mother_citizenship'
            ];
            citizenshipFields.forEach(function(fieldId) {
                const select = document.getElementById(fieldId);
                const otherGroup = document.getElementById(fieldId + '_other_group');
                if (select && otherGroup) {
                    select.addEventListener('change', function() {
                        otherGroup.style.display = this.value === 'Other' ? 'block' : 'none';
                        if (this.value !== 'Other') {
                            const otherInput = document.getElementById(fieldId + '_other');
                            if (otherInput) otherInput.value = '';
                        }
                    });
                }
            });
        })();

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
    </script>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <!-- Certificate Skeleton Loader -->
    <script src="../assets/js/certificate-skeleton-loader.js"></script>

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
            formType: 'marriage_license'
        });
    </script>
</body>
</html>
