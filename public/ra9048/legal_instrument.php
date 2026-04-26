<?php
/**
 * RA 9048/10172 — Legal Instrument Form
 * AUSF (Affidavit to Use Surname of Father), Supplemental Report, Legitimation
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

requireAuth();

// Edit mode detection
$edit_mode = false;
$record = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $pdo_ra->prepare("SELECT * FROM legal_instruments WHERE id = :id AND status = 'Active'");
        $stmt->execute([':id' => (int) $_GET['id']]);
        $record = $stmt->fetch();
        if ($record) {
            $edit_mode = true;
        }
    } catch (PDOException $e) {
        // Record not found or DB error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfTokenMeta() ?>
    <title><?= $edit_mode ? 'Edit' : 'New' ?> Legal Instrument - RA 9048/10172 - <?= APP_SHORT_NAME ?></title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../../assets/js/notiflix-config.js"></script>

    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/certificate-forms-shared.css?v=2.1">
    <link rel="stylesheet" href="../../assets/css/ra9048.css?v=1.0">
</head>
<body>
    <?php include '../../includes/preloader.php'; ?>
    <?php include '../../includes/mobile_header.php'; ?>
    <?php include '../../includes/sidebar_nav.php'; ?>
    <?php include '../../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="main-content-wrapper">
            <div class="form-content-container">
                <!-- System Header -->
                <div class="system-header">
                    <div class="system-logo">
                        <img src="../../assets/img/LOGO1.png" alt="Logo">
                    </div>
                    <div class="system-title-container">
                        <h1 class="system-title">Civil Registry Document Management System (CRDMS)</h1>
                        <p class="system-subtitle">Lalawigan ng Cagayan - Bayan ng Baggao</p>
                    </div>
                </div>

                <!-- Form Type Indicator -->
                <div class="form-type-indicator" style="--form-accent-color: #8b5cf6;">
                    <div class="form-type-info">
                        <h2 class="form-type-title"><?= $edit_mode ? 'Edit Legal Instrument' : 'New Legal Instrument' ?></h2>
                        <p class="form-type-subtitle">AUSF / Supplemental Report / Legitimation</p>
                    </div>
                </div>

                <!-- Alert Messages -->
                <div id="formAlerts"></div>

                <form id="certificateForm" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                    <?php endif; ?>

                    <div class="form-layout">
                        <div class="form-column">

                            <!-- Section: Instrument Type -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <i data-lucide="scale"></i>
                                        Instrument Type
                                    </h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="instrument_type">Type of Legal Instrument <span class="required">*</span></label>
                                        <select id="instrument_type" name="instrument_type" required>
                                            <option value="">— Select —</option>
                                            <option value="AUSF" <?= ($record['instrument_type'] ?? '') === 'AUSF' ? 'selected' : '' ?>>AUSF — Affidavit to Use Surname of Father</option>
                                            <option value="Supplemental" <?= ($record['instrument_type'] ?? '') === 'Supplemental' ? 'selected' : '' ?>>Supplemental Report</option>
                                            <option value="Legitimation" <?= ($record['instrument_type'] ?? '') === 'Legitimation' ? 'selected' : '' ?>>Legitimation</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="applicable_law">Applicable Law</label>
                                        <input type="text" id="applicable_law" name="applicable_law" value="<?= escape_html($record['applicable_law'] ?? '') ?>" placeholder="Auto-populated based on type">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Filing Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <i data-lucide="calendar"></i>
                                        Filing Information
                                    </h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date_of_filing">Date of Filing <span class="required">*</span></label>
                                        <input type="date" id="date_of_filing" name="date_of_filing" value="<?= escape_html($record['date_of_filing'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="registry_number">Registry Number</label>
                                        <input type="text" id="registry_number" name="registry_number" value="<?= escape_html($record['registry_number'] ?? '') ?>" placeholder="Registry number">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Person Details -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <i data-lucide="user"></i>
                                        Person Details
                                    </h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="document_owner_names">Document Owner/s <span class="required">*</span></label>
                                        <input type="text" id="document_owner_names" name="document_owner_names" value="<?= escape_html($record['document_owner_names'] ?? '') ?>" placeholder="Name of child/person" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="affiant_names">Affiant/s</label>
                                        <input type="text" id="affiant_names" name="affiant_names" value="<?= escape_html($record['affiant_names'] ?? '') ?>" placeholder="Name of affiant/s">
                                    </div>
                                </div>
                                <div class="form-row ra9048-conditional-field" id="fatherNameRow">
                                    <div class="form-group">
                                        <label for="father_name">Father's Name</label>
                                        <input type="text" id="father_name" name="father_name" value="<?= escape_html($record['father_name'] ?? '') ?>" placeholder="Father's full name">
                                    </div>
                                    <div class="form-group">
                                        <label for="mother_name">Mother's Name</label>
                                        <input type="text" id="mother_name" name="mother_name" value="<?= escape_html($record['mother_name'] ?? '') ?>" placeholder="Mother's full name">
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Document Details -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <i data-lucide="file-text"></i>
                                        Document Details
                                    </h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="document_type">Type of Document</label>
                                        <select id="document_type" name="document_type">
                                            <option value="">— Select —</option>
                                            <option value="COLB" <?= ($record['document_type'] ?? '') === 'COLB' ? 'selected' : '' ?>>COLB — Certificate of Live Birth</option>
                                            <option value="COM" <?= ($record['document_type'] ?? '') === 'COM' ? 'selected' : '' ?>>COM — Certificate of Marriage</option>
                                            <option value="COD" <?= ($record['document_type'] ?? '') === 'COD' ? 'selected' : '' ?>>COD — Certificate of Death</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="ra9048-conditional-field" id="supplementalInfoRow">
                                    <div class="form-group">
                                        <label for="supplemental_info">Supplemental Information</label>
                                        <textarea id="supplemental_info" name="supplemental_info" rows="3" placeholder="Describe what was omitted or needs to be supplemented"><?= escape_html($record['supplemental_info'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="ra9048-conditional-field" id="legitimationDateRow">
                                    <div class="form-group">
                                        <label for="legitimation_date">Legitimation Date</label>
                                        <input type="date" id="legitimation_date" name="legitimation_date" value="<?= escape_html($record['legitimation_date'] ?? '') ?>">
                                        <span class="help-text">Date parents were married</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="remarks">Remarks</label>
                                    <textarea id="remarks" name="remarks" rows="3" placeholder="Optional remarks"><?= escape_html($record['remarks'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="sticky-buttons">
                                <?php
                                $ra9048_records_url = 'records.php?type=legal_instrument';
                                ?>
                                <?php if ($edit_mode): ?>
                                <div class="form-toolbar" id="formToolbar" data-form-toolbar>
                                    <div class="toolbar-group toolbar-primary">
                                        <a href="<?= $ra9048_records_url ?>" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" title="Back to Records">
                                            <i data-lucide="arrow-left"></i> <span>Records</span>
                                        </a>
                                        <button type="button" class="toolbar-btn toolbar-btn-ghost-danger" data-action="cancel-edit" aria-label="Cancel editing" onclick="window.location.href='<?= $ra9048_records_url ?>'">
                                            <i data-lucide="x"></i> <span>Cancel</span>
                                        </button>
                                    </div>
                                    <div class="toolbar-divider"></div>
                                    <div class="toolbar-group toolbar-secondary">
                                        <button type="submit" class="toolbar-btn toolbar-btn-primary" data-action="save" title="Save changes (Ctrl+S)">
                                            <span class="toolbar-spinner"></span>
                                            <i data-lucide="refresh-cw"></i> <span>Update</span> <kbd>Ctrl+S</kbd>
                                        </button>
                                    </div>
                                    <div class="toolbar-status">
                                        <span class="toolbar-unsaved"><span class="toolbar-unsaved-text">Unsaved changes</span></span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="form-toolbar" id="formToolbar" data-form-toolbar>
                                    <div class="toolbar-group toolbar-primary">
                                        <button type="submit" class="toolbar-btn toolbar-btn-primary" data-action="save" title="Save and return to records (Ctrl+S)">
                                            <span class="toolbar-spinner"></span>
                                            <i data-lucide="save"></i> <span>Save</span> <kbd>Ctrl+S</kbd>
                                        </button>
                                        <button type="button" class="toolbar-btn toolbar-btn-outline" data-action="save-and-new" title="Save and start a new record (Ctrl+Shift+S)">
                                            <i data-lucide="plus"></i> <span>Save & New</span>
                                        </button>
                                    </div>
                                    <div class="toolbar-divider"></div>
                                    <div class="toolbar-group toolbar-secondary">
                                        <button type="button" class="toolbar-btn toolbar-btn-ghost-danger" data-action="reset-form">
                                            <i data-lucide="rotate-ccw"></i> <span>Reset</span>
                                        </button>
                                        <a href="index.php" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" title="Back to Transactions">
                                            <i data-lucide="arrow-left"></i> <span>Back</span>
                                        </a>
                                    </div>
                                    <div class="toolbar-status">
                                        <span class="toolbar-unsaved"><span class="toolbar-unsaved-text">Unsaved changes</span></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div><!-- /.form-column -->

                <!-- RIGHT COLUMN: PDF Preview Drawer -->
                <div class="pdf-column" id="pdfColumn">
                    <div class="pdf-preview-header">
                        <h3 class="pdf-preview-title">
                            <i data-lucide="file-text"></i>
                            PDF Upload
                        </h3>
                        <button type="button" id="togglePdfBtn" class="toggle-pdf-btn" title="Hide PDF Upload">
                            <i data-lucide="eye-off"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label for="pdf_file">
                            Upload PDF Document
                        </label>

                        <div class="upload-scanner-container">
                            <input
                                type="file"
                                id="pdf_file"
                                name="pdf_file"
                                accept=".pdf"
                            >
                        </div>

                        <span class="help-text">Maximum file size: 5MB. Only PDF files are accepted.</span>
                        <?php if ($edit_mode && !empty($record['pdf_filename'])): ?>
                            <span class="help-text">Leave empty to keep existing file.</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($edit_mode && !empty($record['pdf_filename'])): ?>
                    <div class="pdf-preview-container">
                        <iframe id="pdfPreview" src="../../api/serve_pdf.php?file=<?= urlencode($record['pdf_filename']) ?>"></iframe>
                    </div>
                    <div class="pdf-info">
                        <i data-lucide="info"></i>
                        <span>Current File: <span class="pdf-filename"><?= escape_html(basename($record['pdf_filename'])) ?></span></span>
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
                    </div><!-- /.form-layout -->
                </form>

        <!-- Floating Toggle Button - opens PDF drawer -->
        <button type="button" id="floatingToggleBtn" class="floating-toggle-btn" title="Open PDF Upload">
            <i data-lucide="file-text"></i>
        </button>

            </div>
        </div>
    </div>

    <script>
    // --- Instrument Type conditional fields ---
    const instrumentSelect = document.getElementById('instrument_type');
    const fatherNameRow = document.getElementById('fatherNameRow');
    const supplementalInfoRow = document.getElementById('supplementalInfoRow');
    const legitimationDateRow = document.getElementById('legitimationDateRow');
    const applicableLaw = document.getElementById('applicable_law');

    function updateInstrumentFields() {
        const type = instrumentSelect.value;

        // Father's Name: shown for AUSF and Legitimation
        fatherNameRow.classList.toggle('ra9048-conditional-visible', type === 'AUSF' || type === 'Legitimation');

        // Supplemental Info: shown for Supplemental only
        supplementalInfoRow.classList.toggle('ra9048-conditional-visible', type === 'Supplemental');

        // Legitimation Date: shown for Legitimation only
        legitimationDateRow.classList.toggle('ra9048-conditional-visible', type === 'Legitimation');

        // Auto-populate applicable law
        if (type === 'AUSF') {
            applicableLaw.value = 'RA 9255';
        } else if (type === 'Supplemental') {
            applicableLaw.value = '';
        } else if (type === 'Legitimation') {
            applicableLaw.value = 'Family Code of the Philippines';
        }
    }

    instrumentSelect.addEventListener('change', updateInstrumentFields);

    // Trigger on load
    updateInstrumentFields();
    </script>

    <script src="../../assets/js/certificate-form-handler.js"></script>
    <script>
        const formHandler = new CertificateFormHandler({
            formType: 'legal_instrument',
            apiEndpoint: '../../api/ra9048/legal_instrument_save.php',
            updateEndpoint: '../../api/ra9048/legal_instrument_update.php'
        });
    </script>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
