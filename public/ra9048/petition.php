<?php
/**
 * RA 9048/10172 — Petition Form (CCE / CFN)
 * Manual entry form for Correction of Clerical Error and Change of First Name
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
        $stmt = $pdo_ra->prepare("SELECT * FROM petitions WHERE id = :id AND status = 'Active'");
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
    <title><?= $edit_mode ? 'Edit' : 'New' ?> Petition - RA 9048/10172 - <?= APP_SHORT_NAME ?></title>

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
                <div class="form-type-indicator" style="--form-accent-color: #3b82f6;">
                    <div class="form-type-info">
                        <h2 class="form-type-title"><?= $edit_mode ? 'Edit Petition' : 'New Petition' ?></h2>
                        <p class="form-type-subtitle">RA 9048 / RA 10172 — Correction of Clerical Error / Change of First Name</p>
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

                            <!-- Section: Petition Type -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="file-pen"></i> Petition Type</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label class="form-label required-field">Petition Type</label>
                                        <div style="display: flex; gap: 16px; padding-top: 6px;">
                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem;">
                                                <input type="radio" name="petition_type" value="CCE" <?= ($record['petition_type'] ?? '') === 'CCE' ? 'checked' : '' ?> required>
                                                CCE — Correction of Clerical Error
                                            </label>
                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem;">
                                                <input type="radio" name="petition_type" value="CFN" <?= ($record['petition_type'] ?? '') === 'CFN' ? 'checked' : '' ?>>
                                                CFN — Change of First Name
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label class="form-label">Fee Amount</label>
                                        <div class="ra9048-fee-display" id="feeDisplay">
                                            <i data-lucide="philippine-peso" style="width:16px;height:16px;"></i>
                                            <span id="feeText"><?= isset($record['fee_amount']) ? number_format($record['fee_amount'], 2) : '—' ?></span>
                                        </div>
                                        <input type="hidden" name="fee_amount" id="fee_amount" value="<?= $record['fee_amount'] ?? '' ?>">
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
                                        <label for="special_law" class="form-label">Special Law</label>
                                        <input type="text" id="special_law" name="special_law" class="form-input" value="<?= escape_html($record['special_law'] ?? '') ?>" placeholder="Auto-populated based on petition type">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="document_owner_names" class="form-label required-field">Document Owner/s</label>
                                        <input type="text" id="document_owner_names" name="document_owner_names" class="form-input" value="<?= escape_html($record['document_owner_names'] ?? '') ?>" placeholder="Name of document owner/s" required>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="petitioner_names" class="form-label required-field">Name of Petitioner/s</label>
                                        <input type="text" id="petitioner_names" name="petitioner_names" class="form-input" value="<?= escape_html($record['petitioner_names'] ?? '') ?>" placeholder="Name of petitioner/s" required>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Section: Document Details -->
                            <fieldset class="form-section">
                                <legend class="section-title"><i data-lucide="file-text"></i> Document Details</legend>
                                <div class="form-row">
                                    <div class="form-group form-group-half">
                                        <label for="document_type" class="form-label required-field">Type of Document</label>
                                        <select id="document_type" name="document_type" class="form-input" required>
                                            <option value="">— Select —</option>
                                            <option value="COLB" <?= ($record['document_type'] ?? '') === 'COLB' ? 'selected' : '' ?>>COLB — Certificate of Live Birth</option>
                                            <option value="COM" <?= ($record['document_type'] ?? '') === 'COM' ? 'selected' : '' ?>>COM — Certificate of Marriage</option>
                                            <option value="COD" <?= ($record['document_type'] ?? '') === 'COD' ? 'selected' : '' ?>>COD — Certificate of Death</option>
                                        </select>
                                    </div>
                                    <div class="form-group form-group-half">
                                        <label for="petition_of" class="form-label">Petition (of what correction)</label>
                                        <input type="text" id="petition_of" name="petition_of" class="form-input" value="<?= escape_html($record['petition_of'] ?? '') ?>" placeholder="Describe the correction being petitioned">
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
                                // Custom toolbar for RA 9048 forms
                                $ra9048_records_url = 'records.php?type=petition';
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
    // --- Petition Type toggle: CCE/CFN ---
    document.querySelectorAll('input[name="petition_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const feeDisplay = document.getElementById('feeDisplay');
            const feeText = document.getElementById('feeText');
            const feeInput = document.getElementById('fee_amount');
            const specialLaw = document.getElementById('special_law');

            if (this.value === 'CCE') {
                feeText.textContent = '1,000.00';
                feeInput.value = '1000.00';
                feeDisplay.classList.remove('fee-cfn');
                specialLaw.value = 'RA 9048';
            } else if (this.value === 'CFN') {
                feeText.textContent = '3,000.00';
                feeInput.value = '3000.00';
                feeDisplay.classList.add('fee-cfn');
                specialLaw.value = 'RA 9048 / RA 10172';
            }
        });
    });

    // Trigger on load if a value is already selected
    const checkedRadio = document.querySelector('input[name="petition_type"]:checked');
    if (checkedRadio) checkedRadio.dispatchEvent(new Event('change'));
    </script>

    <script src="../../assets/js/certificate-form-handler.js"></script>
    <script>
        const formHandler = new CertificateFormHandler({
            formType: 'petition',
            apiEndpoint: '../../api/ra9048/petition_save.php',
            updateEndpoint: '../../api/ra9048/petition_update.php'
        });
    </script>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
