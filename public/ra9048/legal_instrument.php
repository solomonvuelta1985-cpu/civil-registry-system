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
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="scale"></i> Instrument Type</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="instrument_type" class="form-label required-field">Type of Legal Instrument</label>
                                        <select id="instrument_type" name="instrument_type" class="form-input" required>
                                            <option value="">— Select —</option>
                                            <option value="AUSF" <?= ($record['instrument_type'] ?? '') === 'AUSF' ? 'selected' : '' ?>>AUSF — Affidavit to Use Surname of Father</option>
                                            <option value="Supplemental" <?= ($record['instrument_type'] ?? '') === 'Supplemental' ? 'selected' : '' ?>>Supplemental Report</option>
                                            <option value="Legitimation" <?= ($record['instrument_type'] ?? '') === 'Legitimation' ? 'selected' : '' ?>>Legitimation</option>
                                        </select>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="applicable_law" class="form-label">Applicable Law</label>
                                        <input type="text" id="applicable_law" name="applicable_law" class="form-input" value="<?= escape_html($record['applicable_law'] ?? '') ?>" placeholder="Auto-populated based on type">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Filing Information -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="calendar"></i> Filing Information</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="date_of_filing" class="form-label required-field">Date of Filing</label>
                                        <input type="date" id="date_of_filing" name="date_of_filing" class="form-input" value="<?= escape_html($record['date_of_filing'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="registry_number" class="form-label">Registry Number</label>
                                        <input type="text" id="registry_number" name="registry_number" class="form-input" value="<?= escape_html($record['registry_number'] ?? '') ?>" placeholder="Registry number">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Person Details -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="user"></i> Person Details</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="document_owner_names" class="form-label required-field">Document Owner/s</label>
                                        <input type="text" id="document_owner_names" name="document_owner_names" class="form-input" value="<?= escape_html($record['document_owner_names'] ?? '') ?>" placeholder="Name of child/person" required>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="affiant_names" class="form-label">Affiant/s</label>
                                        <input type="text" id="affiant_names" name="affiant_names" class="form-input" value="<?= escape_html($record['affiant_names'] ?? '') ?>" placeholder="Name of affiant/s">
                                    </div>
                                </div>
                                <div class="form-row ra9048-conditional-field" id="fatherNameRow">
                                    <div class="form-group form-group-half">
                                        <label for="father_name" class="form-label">Father's Name</label>
                                        <input type="text" id="father_name" name="father_name" class="form-input" value="<?= escape_html($record['father_name'] ?? '') ?>" placeholder="Father's full name">
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="mother_name" class="form-label">Mother's Name</label>
                                        <input type="text" id="mother_name" name="mother_name" class="form-input" value="<?= escape_html($record['mother_name'] ?? '') ?>" placeholder="Mother's full name">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Document Details -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="file-text"></i> Document Details</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="document_type" class="form-label">Type of Document</label>
                                        <select id="document_type" name="document_type" class="form-input">
                                            <option value="">— Select —</option>
                                            <option value="COLB" <?= ($record['document_type'] ?? '') === 'COLB' ? 'selected' : '' ?>>COLB — Certificate of Live Birth</option>
                                            <option value="COM" <?= ($record['document_type'] ?? '') === 'COM' ? 'selected' : '' ?>>COM — Certificate of Marriage</option>
                                            <option value="COD" <?= ($record['document_type'] ?? '') === 'COD' ? 'selected' : '' ?>>COD — Certificate of Death</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row ra9048-conditional-field" id="supplementalInfoRow">
                                    <div class="form-group">
                                        <label for="supplemental_info" class="form-label">Supplemental Information</label>
                                        <textarea id="supplemental_info" name="supplemental_info" class="form-input" rows="3" placeholder="Describe what was omitted or needs to be supplemented"><?= escape_html($record['supplemental_info'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="form-row ra9048-conditional-field" id="legitimationDateRow">
                                    <div class="form-group form-group-half">
                                        <label for="legitimation_date" class="form-label">Legitimation Date</label>
                                        <input type="date" id="legitimation_date" name="legitimation_date" class="form-input" value="<?= escape_html($record['legitimation_date'] ?? '') ?>">
                                        <small style="color: #64748b; font-size: 0.78rem;">Date parents were married</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea id="remarks" name="remarks" class="form-input" rows="3" placeholder="Optional remarks"><?= escape_html($record['remarks'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: PDF Upload -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="upload"></i> PDF Upload</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="pdf_file" class="form-label">Upload PDF Document</label>
                                        <input type="file" id="pdf_file" name="pdf_file" class="form-input" accept=".pdf">
                                        <small style="color: #64748b; font-size: 0.78rem;">Max 5MB. PDF files only.</small>
                                        <?php if ($edit_mode && !empty($record['pdf_filename'])): ?>
                                            <p style="margin-top: 6px; font-size: 0.82rem; color: #475569;">
                                                <i data-lucide="paperclip" style="width:14px;height:14px;display:inline;vertical-align:middle;"></i>
                                                Current file: <strong><?= escape_html(basename($record['pdf_filename'])) ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </fieldset>

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
                    </div><!-- /.form-layout -->
                </form>

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
