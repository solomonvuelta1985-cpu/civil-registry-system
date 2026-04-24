<?php
/**
 * RA 9048/10172 — Court Decree Form
 * Registration of court orders and decrees (Adoption, Annulment, Legal Separation, etc.)
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
        $stmt = $pdo_ra->prepare("SELECT * FROM court_decrees WHERE id = :id AND status = 'Active'");
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
    <title><?= $edit_mode ? 'Edit' : 'New' ?> Court Decree - RA 9048/10172 - <?= APP_SHORT_NAME ?></title>

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
                <div class="form-type-indicator" style="--form-accent-color: #f59e0b;">
                    <div class="form-type-info">
                        <h2 class="form-type-title"><?= $edit_mode ? 'Edit Court Decree' : 'New Court Decree' ?></h2>
                        <p class="form-type-subtitle">Registration of Court Orders & Decrees</p>
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

                            <!-- Section: Decree Type -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="gavel"></i> Decree Type</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="decree_type" class="form-label required-field">Type of Court Decree</label>
                                        <select id="decree_type" name="decree_type" class="form-input" required>
                                            <option value="">— Select —</option>
                                            <option value="Adoption" <?= ($record['decree_type'] ?? '') === 'Adoption' ? 'selected' : '' ?>>Adoption</option>
                                            <option value="Annulment" <?= ($record['decree_type'] ?? '') === 'Annulment' ? 'selected' : '' ?>>Annulment</option>
                                            <option value="Legal Separation" <?= ($record['decree_type'] ?? '') === 'Legal Separation' ? 'selected' : '' ?>>Legal Separation</option>
                                            <option value="Correction of Entry" <?= ($record['decree_type'] ?? '') === 'Correction of Entry' ? 'selected' : '' ?>>Correction of Entry</option>
                                            <option value="Naturalization" <?= ($record['decree_type'] ?? '') === 'Naturalization' ? 'selected' : '' ?>>Naturalization</option>
                                            <option value="Recognition" <?= ($record['decree_type'] ?? '') === 'Recognition' ? 'selected' : '' ?>>Recognition</option>
                                            <option value="Other" <?= ($record['decree_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group form-group-half ra9048-conditional-field" id="decreeTypeOtherRow">
                                        <label for="decree_type_other" class="form-label">Specify Other Type</label>
                                        <input type="text" id="decree_type_other" name="decree_type_other" class="form-input" value="<?= escape_html($record['decree_type_other'] ?? '') ?>" placeholder="Specify decree type">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Court Information -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="building-2"></i> Court Information</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="court_region" class="form-label">Region</label>
                                        <input type="text" id="court_region" name="court_region" class="form-input" value="<?= escape_html($record['court_region'] ?? '') ?>" placeholder="e.g., Region II">
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="court_branch" class="form-label">Branch</label>
                                        <input type="text" id="court_branch" name="court_branch" class="form-input" value="<?= escape_html($record['court_branch'] ?? '') ?>" placeholder="e.g., Branch 25">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="court_city_municipality" class="form-label">City / Municipality</label>
                                        <input type="text" id="court_city_municipality" name="court_city_municipality" class="form-input" value="<?= escape_html($record['court_city_municipality'] ?? '') ?>" placeholder="e.g., Tuguegarao City">
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="court_province" class="form-label">Province</label>
                                        <input type="text" id="court_province" name="court_province" class="form-input" value="<?= escape_html($record['court_province'] ?? '') ?>" placeholder="e.g., Cagayan">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="case_number" class="form-label">Case Number</label>
                                        <input type="text" id="case_number" name="case_number" class="form-input" value="<?= escape_html($record['case_number'] ?? '') ?>" placeholder="Court case number">
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="date_of_decree" class="form-label">Date of Decree</label>
                                        <input type="date" id="date_of_decree" name="date_of_decree" class="form-input" value="<?= escape_html($record['date_of_decree'] ?? '') ?>">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Filing & Person Details -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="user"></i> Filing & Person Details</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="date_of_filing" class="form-label">Date of Filing</label>
                                        <input type="date" id="date_of_filing" name="date_of_filing" class="form-input" value="<?= escape_html($record['date_of_filing'] ?? '') ?>">
                                        <small style="color: #64748b; font-size: 0.78rem;">Date filed at civil registry (optional)</small>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="registry_number" class="form-label">Registry Number</label>
                                        <input type="text" id="registry_number" name="registry_number" class="form-input" value="<?= escape_html($record['registry_number'] ?? '') ?>" placeholder="Registry number">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="document_owner_names" class="form-label required-field">Document Owner/s</label>
                                        <input type="text" id="document_owner_names" name="document_owner_names" class="form-input" value="<?= escape_html($record['document_owner_names'] ?? '') ?>" placeholder="Name of document owner/s" required>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="petitioner_names" class="form-label">Petitioner/s</label>
                                        <input type="text" id="petitioner_names" name="petitioner_names" class="form-input" value="<?= escape_html($record['petitioner_names'] ?? '') ?>" placeholder="Name of petitioner/s">
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
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="decree_details" class="form-label">Decree Details</label>
                                        <textarea id="decree_details" name="decree_details" class="form-input" rows="3" placeholder="Summary of the court decree"><?= escape_html($record['decree_details'] ?? '') ?></textarea>
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
                                $ra9048_records_url = 'records.php?type=court_decree';
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
    // --- Decree Type "Other" toggle ---
    const decreeSelect = document.getElementById('decree_type');
    const otherRow = document.getElementById('decreeTypeOtherRow');

    function updateDecreeFields() {
        otherRow.classList.toggle('ra9048-conditional-visible', decreeSelect.value === 'Other');
    }

    decreeSelect.addEventListener('change', updateDecreeFields);
    updateDecreeFields();
    </script>

    <script src="../../assets/js/certificate-form-handler.js"></script>
    <script>
        const formHandler = new CertificateFormHandler({
            formType: 'court_decree',
            apiEndpoint: '../../api/ra9048/court_decree_save.php',
            updateEndpoint: '../../api/ra9048/court_decree_update.php'
        });
    </script>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
