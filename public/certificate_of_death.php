<?php
/**
 * Certificate of Death - Entry Form (PHP Version)
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
    <?= csrfTokenMeta() ?>
    <title>Death Certificate - Civil Registry System</title>

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

                <!-- Form Type Indicator -->
                <div class="form-type-indicator form-death">
                    <div class="form-type-info">
                        <h2 class="form-type-title">
                            <?php echo $edit_mode ? 'Edit' : 'New'; ?> Certificate of Death
                            <span class="form-type-badge"><?php echo $edit_mode ? 'Edit Mode' : 'Death Record'; ?></span>
                        </h2>
                        <p class="form-type-subtitle"><?php echo $edit_mode ? 'Update the death certificate information below' : 'Complete the form below to register a death certificate'; ?></p>
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
                            <div class="progress-step" data-section="deceased_section">
                                <span class="step-number">2</span>
                                <span class="step-label">Deceased</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="place_section">
                                <span class="step-number">3</span>
                                <span class="step-label">Place</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="father_section">
                                <span class="step-number">4</span>
                                <span class="step-label">Father</span>
                            </div>
                            <div class="progress-connector"></div>
                            <div class="progress-step" data-section="mother_section">
                                <span class="step-number">5</span>
                                <span class="step-label">Mother</span>
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
                    <div class="form-section" id="deceased_section">
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
                                    Date of Birth
                                </label>
                                <input
                                    type="date"
                                    id="date_of_birth"
                                    name="date_of_birth"
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
                    <div class="form-section" id="place_section">
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
                    <div class="form-section" id="father_section">
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="father_citizenship">Citizenship</label>
                                <?php
                                $citizenship_options = ['Filipino', 'American', 'Chinese', 'Japanese', 'Korean', 'British', 'Australian', 'Canadian', 'Indian', 'Other'];
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
                                <label for="father_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="father_citizenship_other" name="father_citizenship_other" placeholder="Please specify" value="<?php echo $father_cit_is_other ? htmlspecialchars($father_cit_val) : ''; ?>">
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_citizenship">Citizenship</label>
                                <?php
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
                                <label for="mother_citizenship_other">Specify Citizenship</label>
                                <input type="text" id="mother_citizenship_other" name="mother_citizenship_other" placeholder="Please specify" value="<?php echo $mother_cit_is_other ? htmlspecialchars($mother_cit_val) : ''; ?>">
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
    <script>window.APP_BASE = '<?= rtrim(BASE_URL, '/') ?>';</script>
    <script src="../assets/js/certificate-form-handler.js"></script>

    <!-- Death Certificate Specific Logic -->
    <script>
        // Initialize the form handler
        const formHandler = new CertificateFormHandler({
            formType: 'death',
            apiEndpoint: '../api/certificate_of_death_save.php',
            updateEndpoint: '../api/certificate_of_death_update.php'
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

        // Citizenship "Other" toggle handlers
        (function() {
            ['father_citizenship', 'mother_citizenship'].forEach(function(fieldId) {
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
            formType: 'death'
        });
    </script>
</body>
</html>
