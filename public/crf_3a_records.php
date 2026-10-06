<?php
/** CRF No. 3A Records - immutable marriage issuance history and reprints. */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_3a.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }
if (!hasPermission(crf_3a_view_permission())) { http_response_code(403); include __DIR__ . '/403.php'; exit; }

setSecurityHeaders();
$csrfMeta = csrfTokenMeta();
$crfDefaults = branding_crf_preview_config(crf_3a_config());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRF No. 3A Records - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css?v=13">
    <link rel="stylesheet" href="../assets/css/crf-1a.css?v=31">
    <script src="<?= asset_url('pdfjs') ?>"></script>
    <script>if (typeof pdfjsLib !== 'undefined') pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= asset_url("pdfjs_worker") ?>';</script>
    <style>
        body { background:#f8fafc; color:#1e293b; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
        .crf1a-records-page { margin-left:var(--sidebar-width); padding:88px 32px 24px; max-width:1700px; transition:margin-left .25s ease; }
        .sidebar-collapsed .crf1a-records-page { margin-left:var(--sidebar-collapsed-width); }
        .crf1a-page-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; padding-bottom:14px; border-bottom:2px solid #f1f5f9; }
        .crf1a-page-header h1 { display:flex; align-items:center; gap:10px; margin:0; font-size:24px; color:#0f172a; }
        .crf1a-page-header h1 [data-lucide] { width:25px; height:25px; color:#2563eb; }
        .crf1a-page-header p { margin:5px 0 0 35px; color:#64748b; font-size:13px; }
        .crf1a-records-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(15,23,42,.04); }
        .crf1a-records-message { min-height:22px; color:#64748b; font-size:13px; margin:8px 0; }
        .crf1a-records-message.error { color:#b91c1c; }
        .crf1a-records-empty { padding:54px 20px; text-align:center; color:#94a3b8; }
        .crf1a-records-empty [data-lucide] { width:46px; height:46px; margin-bottom:10px; }
        @media (max-width:1100px) { .crf1a-records-page { margin-left:0; padding:24px 18px; } .sidebar-collapsed .crf1a-records-page { margin-left:0; } }
        @media (max-width:700px) { .crf1a-records-page { padding:18px 14px; } .crf1a-page-header { align-items:flex-start; } .crf1a-page-header h1 { font-size:20px; } .crf1a-page-header p { margin-left:0; } .crf1a-records-card { padding:12px; } }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<main class="crf1a-records-page">
    <div class="crf1a-page-header">
        <div><h1><i data-lucide="heart-handshake"></i> CRF No. 3A Records</h1><p>Immutable Marriage-Available certifications, issuance history, and reprints</p></div>
    </div>
    <section class="crf1a-records-card">
        <div class="crf1a-records-toolbar">
            <div class="crf1a-form-group"><label for="crf3aRecordsSearch">Search</label><input id="crf3aRecordsSearch" class="crf1a-form-control" placeholder="CRF ID, registry no., husband, wife, page..." autocomplete="off"></div>
            <div class="crf1a-form-group narrow"><label for="crf3aIssueYearFilter">Issue Year</label><input id="crf3aIssueYearFilter" class="crf1a-form-control" inputmode="numeric" maxlength="4" placeholder="YYYY"></div>
            <div class="crf1a-form-group narrow"><label for="crf3aDateFrom">Date Paid From</label><input id="crf3aDateFrom" type="date" class="crf1a-form-control"></div>
            <div class="crf1a-form-group narrow"><label for="crf3aDateTo">Date Paid To</label><input id="crf3aDateTo" type="date" class="crf1a-form-control"></div>
            <div class="crf1a-form-group narrow crf1a-sort-control"><label for="crf3aSortByFilter">Sort By</label><select id="crf3aSortByFilter" class="crf1a-form-control"><option value="crf_id">CRF ID</option><option value="registry">Registry No.</option><option value="husband">Husband</option><option value="wife">Wife</option><option value="page_book">Page / Book</option><option value="amount">Payment Amount</option><option value="date_paid">Date Paid</option><option value="issue_date">Issue Date</option></select></div>
            <div class="crf1a-form-group narrow crf1a-sort-control"><label for="crf3aSortDirFilter">Order</label><select id="crf3aSortDirFilter" class="crf1a-form-control"><option value="desc">Descending</option><option value="asc">Ascending</option></select></div>
            <button type="button" id="crf3aClearFilters" class="crf1a-btn crf1a-btn-secondary"><i data-lucide="rotate-ccw"></i> Clear</button>
        </div>
        <div id="crf3aRecordsMessage" class="crf1a-records-message" role="status"></div>
        <div id="crf3aRecordsTableHost"></div>
        <div id="crf3aRecordsPagination" class="crf1a-records-pagination"></div>
    </section>
</main>

<script>
    window.CRF3A_OFFICE_CONFIG = <?= json_encode($crfDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.CRF3A_CAN_GENERATE = <?= hasPermission(crf_3a_generate_permission()) ? 'true' : 'false' ?>;
    window.CRF3A_CAN_ARCHIVE = <?= canArchive('marriage') ? 'true' : 'false' ?>;
    window.CRF3A_CAN_DELETE = <?= isAdmin() ? 'true' : 'false' ?>;
    window.CRF3A_DEFAULT_ISSUE_DATE = <?= json_encode(date('Y-m-d')) ?>;
</script>
<script src="../assets/js/crf-remarks-editor.js?v=20261006-full-month-dates-1"></script>
<script src="../assets/js/crf-3a-generator.js?v=20261006-full-month-dates-1"></script>
<script src="../assets/js/crf-3a-records.js?v=3"></script>
<?php include '../includes/sidebar_scripts.php'; ?>
</body>
</html>
