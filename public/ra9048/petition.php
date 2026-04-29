<?php
/**
 * RA 9048 / RA 10172 — Petition Form (CCE-minor / CCE-RA10172 / CFN)
 *
 * Captures every field needed to auto-generate the 5 office documents:
 *   - Petition (CCE Form 1.1 or CFN Form 4.1)
 *   - Order for Publication             (CFN + CCE_10172 only)
 *   - Public Notice / Publication Slide (CFN + CCE_10172 only)
 *   - Certificate of Posting            (all subtypes)
 *   - Certification of Proof of Filing  (all subtypes)
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
$corrections = [];
$supporting_docs = [];
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $pdo_ra->prepare("SELECT * FROM petitions WHERE id = :id AND status = 'Active'");
        $stmt->execute([':id' => (int) $_GET['id']]);
        $record = $stmt->fetch();
        if ($record) {
            $edit_mode = true;

            $stmt = $pdo_ra->prepare("SELECT * FROM petition_corrections WHERE petition_id = :id ORDER BY item_no ASC");
            $stmt->execute([':id' => (int) $_GET['id']]);
            $corrections = $stmt->fetchAll();

            $stmt = $pdo_ra->prepare("SELECT * FROM petition_supporting_docs WHERE petition_id = :id ORDER BY item_no ASC");
            $stmt->execute([':id' => (int) $_GET['id']]);
            $supporting_docs = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Record not found or DB error
    }
}

$subtype = $record['petition_subtype'] ?? '';
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
    <link rel="stylesheet" href="../../assets/css/ra9048-petition-form.css?v=1.0">
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

                <div id="formAlerts"></div>

                <form id="certificateForm" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="petition_type" id="petition_type_hidden" value="<?= escape_html($record['petition_type'] ?? '') ?>">

                    <div class="form-layout">
                        <div class="form-column">

                            <!-- ============================================== -->
                            <!-- SECTION 1: Petition Type & Number              -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="file-pen"></i> Petition Type</h2>
                                </div>
                                <div class="form-group">
                                    <label>Petition Subtype <span class="required">*</span></label>
                                    <div class="subtype-radio-group">
                                        <label class="subtype-radio">
                                            <input type="radio" name="petition_subtype" value="CCE_minor" <?= $subtype === 'CCE_minor' ? 'checked' : '' ?> required>
                                            <div>
                                                <strong>CCE — R.A. 9048</strong>
                                                <small>Clerical / typographical errors. Posting only.</small>
                                            </div>
                                        </label>
                                        <label class="subtype-radio">
                                            <input type="radio" name="petition_subtype" value="CCE_10172" <?= $subtype === 'CCE_10172' ? 'checked' : '' ?>>
                                            <div>
                                                <strong>CCE — R.A. 9048 as amended by R.A. 10172</strong>
                                                <small>Sex / day / month of birth. Posting + publication.</small>
                                            </div>
                                        </label>
                                        <label class="subtype-radio">
                                            <input type="radio" name="petition_subtype" value="CFN" <?= $subtype === 'CFN' ? 'checked' : '' ?>>
                                            <div>
                                                <strong>CFN — R.A. 9048</strong>
                                                <small>Change of first name. Posting + publication.</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="petition_number_seq">Petition Number <span class="required">*</span></label>
                                        <div class="petition-number-wrap">
                                            <span class="petition-number-prefix" id="petitionNumberPrefix">CCE-</span>
                                            <input type="text" id="petition_number_seq" placeholder="0130-2025" autocomplete="off"
                                                   value="<?= escape_html(preg_replace('/^(CCE|CFN)-/i', '', $record['petition_number'] ?? '')) ?>" required>
                                        </div>
                                        <input type="hidden" name="petition_number" id="petition_number" value="<?= escape_html($record['petition_number'] ?? '') ?>">
                                        <span class="help-text" id="petitionNumberHelp">Format: <code>0130-2025</code> (sequence-year). Prefix is set automatically.</span>
                                    </div>
                                    <div class="form-group">
                                        <label>Fee Amount</label>
                                        <div class="ra9048-fee-display" id="feeDisplay">
                                            <i data-lucide="philippine-peso" style="width:16px;height:16px;"></i>
                                            <span id="feeText"><?= isset($record['fee_amount']) ? number_format($record['fee_amount'], 2) : '—' ?></span>
                                        </div>
                                        <input type="hidden" name="fee_amount" id="fee_amount" value="<?= $record['fee_amount'] ?? '' ?>">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date_of_filing">Date of Filing <span class="required">*</span></label>
                                        <input type="date" id="date_of_filing" name="date_of_filing" value="<?= escape_html($record['date_of_filing'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="special_law">Applicable Law (auto)</label>
                                        <input type="text" id="special_law" name="special_law" value="<?= escape_html($record['special_law'] ?? '') ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 2: Petitioner                          -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="user"></i> Petitioner</h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="petitioner_names">Complete Name of Petitioner/s <span class="required">*</span></label>
                                        <input type="text" id="petitioner_names" name="petitioner_names" value="<?= escape_html($record['petitioner_names'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="petitioner_nationality">Nationality / Citizenship</label>
                                        <input type="text" id="petitioner_nationality" name="petitioner_nationality" value="<?= escape_html($record['petitioner_nationality'] ?? 'FILIPINO') ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="petitioner_address">Complete Address</label>
                                    <input type="text" id="petitioner_address" name="petitioner_address" value="<?= escape_html($record['petitioner_address'] ?? '') ?>" placeholder="e.g. Mocag, Baggao, Cagayan">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="petitioner_id_type">ID Presented (Type)</label>
                                        <input type="text" id="petitioner_id_type" name="petitioner_id_type" value="<?= escape_html($record['petitioner_id_type'] ?? '') ?>" placeholder="e.g. National ID, TIN, Passport">
                                    </div>
                                    <div class="form-group">
                                        <label for="petitioner_id_number">ID Number</label>
                                        <input type="text" id="petitioner_id_number" name="petitioner_id_number" value="<?= escape_html($record['petitioner_id_number'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" id="is_self_petition" name="is_self_petition" value="1" <?= !empty($record['is_self_petition']) ? 'checked' : '' ?>>
                                            Petitioner is the document owner (filing for own record)
                                        </label>
                                    </div>
                                    <div class="form-group" id="relationField">
                                        <label for="relation_to_owner">Relation to Document Owner</label>
                                        <input type="text" id="relation_to_owner" name="relation_to_owner" value="<?= escape_html($record['relation_to_owner'] ?? '') ?>" placeholder="e.g. DAUGHTER, FATHER, MOTHER">
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 3: Document Owner (with COLB lookup)   -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="user-check"></i> Document Owner</h2>
                                    <button type="button" class="section-action" id="lookupOwnerBtn" title="Search existing scanned records">
                                        <i data-lucide="search"></i> Lookup from Records
                                    </button>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="document_owner_names">Complete Name of Document Owner <span class="required">*</span></label>
                                        <input type="text" id="document_owner_names" name="document_owner_names" value="<?= escape_html($record['document_owner_names'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="document_type">Type of Document <span class="required">*</span></label>
                                        <select id="document_type" name="document_type" required>
                                            <option value="">— Select —</option>
                                            <option value="COLB" <?= ($record['document_type'] ?? '') === 'COLB' ? 'selected' : '' ?>>COLB — Certificate of Live Birth</option>
                                            <option value="COM"  <?= ($record['document_type'] ?? '') === 'COM'  ? 'selected' : '' ?>>COM — Certificate of Marriage</option>
                                            <option value="COD"  <?= ($record['document_type'] ?? '') === 'COD'  ? 'selected' : '' ?>>COD — Certificate of Death</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="owner_dob">Date of Birth (Owner)</label>
                                        <input type="date" id="owner_dob" name="owner_dob" value="<?= escape_html($record['owner_dob'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="registry_number">Registry Number</label>
                                        <input type="text" id="registry_number" name="registry_number" value="<?= escape_html($record['registry_number'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="owner_birthplace_city">City / Municipality of Birth</label>
                                        <input type="text" id="owner_birthplace_city" name="owner_birthplace_city" value="<?= escape_html($record['owner_birthplace_city'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="owner_birthplace_province">Province of Birth</label>
                                        <input type="text" id="owner_birthplace_province" name="owner_birthplace_province" value="<?= escape_html($record['owner_birthplace_province'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="owner_birthplace_country">Country</label>
                                        <input type="text" id="owner_birthplace_country" name="owner_birthplace_country" value="<?= escape_html($record['owner_birthplace_country'] ?? 'PHILIPPINES') ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="father_full_name">Father's Full Name (on COLB)</label>
                                        <input type="text" id="father_full_name" name="father_full_name" value="<?= escape_html($record['father_full_name'] ?? '') ?>" placeholder="Used in publication notice">
                                    </div>
                                    <div class="form-group">
                                        <label for="mother_full_name">Mother's Full Name (on COLB)</label>
                                        <input type="text" id="mother_full_name" name="mother_full_name" value="<?= escape_html($record['mother_full_name'] ?? '') ?>" placeholder="Used in publication notice">
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 4: Corrections (FROM → TO grid)         -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="edit-3"></i> Corrections (FROM → TO)</h2>
                                    <button type="button" class="section-action" id="addCorrectionBtn">
                                        <i data-lucide="plus"></i> Add Row
                                    </button>
                                </div>
                                <div class="grid-table" id="correctionsGrid">
                                    <div class="grid-head">
                                        <div class="grid-col grid-col-num">#</div>
                                        <div class="grid-col grid-col-nature">Nature</div>
                                        <div class="grid-col grid-col-desc">Description</div>
                                        <div class="grid-col grid-col-val">From</div>
                                        <div class="grid-col grid-col-val">To</div>
                                        <div class="grid-col grid-col-action"></div>
                                    </div>
                                    <div class="grid-body" id="correctionsBody">
                                        <?php if (!empty($corrections)): ?>
                                            <?php foreach ($corrections as $i => $c): ?>
                                                <div class="grid-row" data-row-index="<?= $i ?>">
                                                    <div class="grid-col grid-col-num"><?= $i + 1 ?></div>
                                                    <div class="grid-col grid-col-nature">
                                                        <select name="corrections[<?= $i ?>][nature]">
                                                            <option value="CCE" <?= $c['nature'] === 'CCE' ? 'selected' : '' ?>>CCE</option>
                                                            <option value="CFN" <?= $c['nature'] === 'CFN' ? 'selected' : '' ?>>CFN</option>
                                                        </select>
                                                    </div>
                                                    <div class="grid-col grid-col-desc">
                                                        <input type="text" name="corrections[<?= $i ?>][description]" value="<?= escape_html($c['description']) ?>" placeholder="e.g. FATHER'S FIRST NAME">
                                                    </div>
                                                    <div class="grid-col grid-col-val">
                                                        <input type="text" name="corrections[<?= $i ?>][value_from]" value="<?= escape_html($c['value_from']) ?>">
                                                    </div>
                                                    <div class="grid-col grid-col-val">
                                                        <input type="text" name="corrections[<?= $i ?>][value_to]" value="<?= escape_html($c['value_to']) ?>">
                                                    </div>
                                                    <div class="grid-col grid-col-action">
                                                        <button type="button" class="row-delete" title="Remove row"><i data-lucide="trash-2"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="help-text">Add one row per error to be corrected. Nature drives publication notice grouping.</span>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 5: CFN Ground (CFN only)               -->
                            <!-- ============================================== -->
                            <div class="form-section conditional-section" id="cfnGroundSection" style="display:none;">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="help-circle"></i> Ground for Change of First Name</h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cfn_ground">Ground <span class="required">*</span></label>
                                        <select id="cfn_ground" name="cfn_ground">
                                            <option value="">— Select —</option>
                                            <option value="difficult"  <?= ($record['cfn_ground'] ?? '') === 'difficult'  ? 'selected' : '' ?>>(a) First name is extremely difficult to write or pronounce</option>
                                            <option value="habitual"   <?= ($record['cfn_ground'] ?? '') === 'habitual'   ? 'selected' : '' ?>>(b) Habitually and continuously used another name</option>
                                            <option value="ridicule"   <?= ($record['cfn_ground'] ?? '') === 'ridicule'   ? 'selected' : '' ?>>(c) Subject of ridicule</option>
                                            <option value="confusion"  <?= ($record['cfn_ground'] ?? '') === 'confusion'  ? 'selected' : '' ?>>(d) Causes confusion</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cfn_ground_detail">Explanation / Habitually-used Name</label>
                                    <textarea id="cfn_ground_detail" name="cfn_ground_detail" rows="2" placeholder="If habitually used, enter that name; otherwise explain the ground."><?= escape_html($record['cfn_ground_detail'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 6: Supporting Documents                -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="paperclip"></i> Supporting Documents</h2>
                                    <button type="button" class="section-action" id="addSupportingBtn">
                                        <i data-lucide="plus"></i> Add Document
                                    </button>
                                </div>
                                <div class="grid-table" id="supportingGrid">
                                    <div class="grid-head">
                                        <div class="grid-col grid-col-num">#</div>
                                        <div class="grid-col grid-col-doc">Document Label</div>
                                        <div class="grid-col grid-col-action"></div>
                                    </div>
                                    <div class="grid-body" id="supportingBody">
                                        <?php if (!empty($supporting_docs)): ?>
                                            <?php foreach ($supporting_docs as $i => $d): ?>
                                                <div class="grid-row" data-row-index="<?= $i ?>">
                                                    <div class="grid-col grid-col-num"><?= $i + 1 ?></div>
                                                    <div class="grid-col grid-col-doc">
                                                        <input type="text" name="supporting_docs[<?= $i ?>][doc_label]" value="<?= escape_html($d['doc_label']) ?>">
                                                    </div>
                                                    <div class="grid-col grid-col-action">
                                                        <button type="button" class="row-delete" title="Remove row"><i data-lucide="trash-2"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="help-text" id="medCertHint" style="display:none; color:#b45309;">
                                    <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
                                    Sex correction requires a "Medical Certification (Government Physician)" entry.
                                </span>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 7: Posting & Publication               -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="megaphone"></i> Posting</h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="posting_start_date">Posting Start Date</label>
                                        <input type="date" id="posting_start_date" name="posting_start_date" value="<?= escape_html($record['posting_start_date'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="posting_end_date">Posting End Date</label>
                                        <input type="date" id="posting_end_date" name="posting_end_date" value="<?= escape_html($record['posting_end_date'] ?? '') ?>">
                                        <span class="help-text">Suggested: start + 10 working days</span>
                                    </div>
                                    <div class="form-group">
                                        <label for="posting_location">Place of Posting</label>
                                        <input type="text" id="posting_location" name="posting_location" value="<?= escape_html($record['posting_location'] ?? 'MUNICIPAL HALL BULLETIN BOARD') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section conditional-section" id="publicationSection" style="display:none;">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="newspaper"></i> Publication</h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="publication_date_1">Publication Date 1</label>
                                        <input type="date" id="publication_date_1" name="publication_date_1" value="<?= escape_html($record['publication_date_1'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="publication_date_2">Publication Date 2</label>
                                        <input type="date" id="publication_date_2" name="publication_date_2" value="<?= escape_html($record['publication_date_2'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="opposition_deadline">Opposition Deadline (auto)</label>
                                        <input type="date" id="opposition_deadline" name="opposition_deadline" value="<?= escape_html($record['opposition_deadline'] ?? '') ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="publication_newspaper">Newspaper</label>
                                        <input type="text" id="publication_newspaper" name="publication_newspaper" value="<?= escape_html($record['publication_newspaper'] ?? '') ?>" placeholder="e.g. The Northern Forum">
                                    </div>
                                    <div class="form-group">
                                        <label for="publication_place">Place of Publication</label>
                                        <input type="text" id="publication_place" name="publication_place" value="<?= escape_html($record['publication_place'] ?? '') ?>" placeholder="e.g. Tuguegarao City, Cagayan">
                                    </div>
                                    <div class="form-group">
                                        <label for="order_date">Order for Publication Date</label>
                                        <input type="date" id="order_date" name="order_date" value="<?= escape_html($record['order_date'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 8: Payment & Notarization              -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="receipt"></i> Payment & Notarization</h2>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="receipt_number">Receipt Number</label>
                                        <input type="text" id="receipt_number" name="receipt_number" value="<?= escape_html($record['receipt_number'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="payment_date">Date of Payment</label>
                                        <input type="date" id="payment_date" name="payment_date" value="<?= escape_html($record['payment_date'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="notarized_at">Date Notarized</label>
                                        <input type="date" id="notarized_at" name="notarized_at" value="<?= escape_html($record['notarized_at'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================== -->
                            <!-- SECTION 9: Remarks                             -->
                            <!-- ============================================== -->
                            <div class="form-section">
                                <div class="section-header">
                                    <h2 class="section-title"><i data-lucide="message-square"></i> Remarks</h2>
                                </div>
                                <div class="form-group">
                                    <label for="petition_of">Petition (of what correction)</label>
                                    <input type="text" id="petition_of" name="petition_of" value="<?= escape_html($record['petition_of'] ?? '') ?>" placeholder="Brief one-line description of the correction">
                                </div>
                                <div class="form-group">
                                    <label for="remarks">Remarks</label>
                                    <textarea id="remarks" name="remarks" rows="3"><?= escape_html($record['remarks'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="sticky-buttons">
                                <?php $ra9048_records_url = 'records.php?type=petition'; ?>
                                <?php if ($edit_mode): ?>
                                <div class="form-toolbar" id="formToolbar" data-form-toolbar>
                                    <div class="toolbar-group toolbar-primary">
                                        <a href="<?= $ra9048_records_url ?>" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" title="Back to Records">
                                            <i data-lucide="arrow-left"></i> <span>Records</span>
                                        </a>
                                        <button type="button" class="toolbar-btn toolbar-btn-ghost-danger" data-action="cancel-edit" onclick="window.location.href='<?= $ra9048_records_url ?>'">
                                            <i data-lucide="x"></i> <span>Cancel</span>
                                        </button>
                                    </div>
                                    <div class="toolbar-divider"></div>
                                    <div class="toolbar-group toolbar-secondary">
                                        <button type="button" class="toolbar-btn toolbar-btn-outline" id="downloadDocsBtn" data-petition-id="<?= (int) $record['id'] ?>" title="Download generated documents">
                                            <i data-lucide="download"></i> <span>Download</span>
                                        </button>
                                        <button type="button" class="toolbar-btn toolbar-btn-outline" id="regenerateDocsBtn" data-petition-id="<?= (int) $record['id'] ?>" title="Regenerate generated documents">
                                            <i data-lucide="file-cog"></i> <span>Regenerate</span>
                                        </button>
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
                                        <button type="submit" class="toolbar-btn toolbar-btn-primary" data-action="save" title="Verify & File (Ctrl+S)">
                                            <span class="toolbar-spinner"></span>
                                            <i data-lucide="check-circle-2"></i> <span>Verify &amp; File</span> <kbd>Ctrl+S</kbd>
                                        </button>
                                        <button type="button" class="toolbar-btn toolbar-btn-outline" data-action="save-and-new" title="Save and start a new record (Ctrl+Shift+S)">
                                            <i data-lucide="plus"></i> <span>Save &amp; New</span>
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
                                <h3 class="pdf-preview-title"><i data-lucide="file-text"></i> PDF Upload</h3>
                                <button type="button" id="togglePdfBtn" class="toggle-pdf-btn" title="Hide PDF Upload">
                                    <i data-lucide="eye-off"></i>
                                </button>
                            </div>
                            <div class="form-group">
                                <label for="pdf_file">Upload PDF Document</label>
                                <div class="upload-scanner-container">
                                    <input type="file" id="pdf_file" name="pdf_file" accept=".pdf">
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

                <button type="button" id="floatingToggleBtn" class="floating-toggle-btn" title="Open PDF Upload">
                    <i data-lucide="file-text"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Owner Lookup Modal (Phase 3 wires this up) -->
    <div id="ownerLookupModal" class="lookup-modal" style="display:none;">
        <div class="lookup-modal-backdrop"></div>
        <div class="lookup-modal-panel">
            <div class="lookup-modal-header">
                <h3>Lookup Document Owner</h3>
                <button type="button" class="lookup-modal-close" id="lookupCloseBtn">&times;</button>
            </div>
            <div class="lookup-modal-body">
                <input type="text" id="lookupQuery" placeholder="Search by registry number or name…" autocomplete="off">
                <div id="lookupResults" class="lookup-results"></div>
            </div>
        </div>
    </div>

    <script>
    // Pre-populate child rows when in edit mode (used by JS to set counters)
    window.RA9048_INIT = {
        editMode: <?= $edit_mode ? 'true' : 'false' ?>,
        correctionRows: <?= count($corrections) ?>,
        supportingRows: <?= count($supporting_docs) ?>,
        existingPetitionNumber: <?= json_encode($record['petition_number'] ?? '') ?>
    };
    </script>
    <script src="../../assets/js/ra9048-petition-form.js?v=1.0"></script>

    <!-- Verify & File post-save pipeline (must load BEFORE the form handler so it can wrap fetch) -->
    <script src="../../assets/js/ra9048-verify-and-file.js?v=1.0"></script>

    <script src="../../assets/js/certificate-form-handler.js"></script>
    <script>
        const formHandler = new CertificateFormHandler({
            formType: 'petition',
            apiEndpoint: '../../api/ra9048/petition_save.php',
            updateEndpoint: '../../api/ra9048/petition_update.php'
        });
    </script>

    <?php if ($edit_mode): ?>
    <script>
    // Edit-mode toolbar — Download and Regenerate buttons.
    (function() {
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const dlBtn  = document.getElementById('downloadDocsBtn');
        const regBtn = document.getElementById('regenerateDocsBtn');

        function listAndDownload(id) {
            return fetch('../../api/ra9048/list_documents.php?petition_id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(json => {
                    if (!json || !json.success || !json.data) {
                        Notiflix.Notify.failure('Failed to load documents.');
                        return null;
                    }
                    const docs = json.data.documents || [];
                    const petitionDoc = docs.find(d => d.doc_type === 'petition');
                    return petitionDoc || null;
                });
        }

        function generate(id, andDownload) {
            Notiflix.Loading.standard('Generating documents…');
            const fd = new FormData();
            fd.append('petition_id', id);
            fd.append('doc_type', 'all');
            fd.append('csrf_token', CSRF);
            return fetch('../../api/ra9048/generate_document.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    Notiflix.Loading.remove();
                    if (!data.success) {
                        Notiflix.Notify.failure(data.message || 'Generation failed.');
                        return;
                    }
                    const gen = (data.data && data.data.generated) || [];
                    const skipped = (data.data && data.data.skipped) || [];
                    Notiflix.Notify.success('Generated ' + gen.length + ' file(s).' + (skipped.length ? ' Skipped ' + skipped.length + '.' : ''));
                    if (andDownload) {
                        const petitionDoc = gen.find(g => g.doc_type === 'petition');
                        if (petitionDoc) window.location.href = petitionDoc.url;
                    }
                })
                .catch(() => {
                    Notiflix.Loading.remove();
                    Notiflix.Notify.failure('Network error.');
                });
        }

        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                const id = dlBtn.dataset.petitionId;
                if (!id) return;
                dlBtn.disabled = true;
                listAndDownload(id).then(petitionDoc => {
                    dlBtn.disabled = false;
                    if (petitionDoc) {
                        window.location.href = petitionDoc.url;
                    } else {
                        Notiflix.Confirm.show(
                            'No petition document yet',
                            'Generate the petition document now?',
                            'Generate', 'Cancel',
                            function() { generate(id, true); }
                        );
                    }
                }).catch(() => {
                    dlBtn.disabled = false;
                    Notiflix.Notify.failure('Network error.');
                });
            });
        }

        if (regBtn) {
            regBtn.addEventListener('click', function() {
                const id = regBtn.dataset.petitionId;
                if (!id) return;
                Notiflix.Confirm.show(
                    'Regenerate Documents',
                    'Replace any previously generated DOCX files for this petition?',
                    'Regenerate', 'Cancel',
                    function() { generate(id, false); }
                );
            });
        }
    })();
    </script>
    <?php endif; ?>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
