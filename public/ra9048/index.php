<?php
/**
 * RA 9048/10172 — Transaction Selection Page
 * Landing page with 3 cards: Petition, Legal Instrument, Court Decree
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

// Require authentication
requireAuth();

// Fetch record counts from RA 9048 database
$counts = ['petitions' => 0, 'legal_instruments' => 0, 'court_decrees' => 0];
try {
    $stmt = $pdo_ra->query("
        SELECT 'petitions' AS tbl, COUNT(*) AS cnt FROM petitions WHERE status = 'Active'
        UNION ALL
        SELECT 'legal_instruments', COUNT(*) FROM legal_instruments WHERE status = 'Active'
        UNION ALL
        SELECT 'court_decrees', COUNT(*) FROM court_decrees WHERE status = 'Active'
    ");
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['tbl']] = (int) $row['cnt'];
    }
} catch (PDOException $e) {
    // Tables may not exist yet — silently continue with 0 counts
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfTokenMeta() ?>
    <title>RA 9048/10172 Transactions - <?= APP_SHORT_NAME ?></title>

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

    <!-- Main Content Area -->
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

                <!-- Page Header -->
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; text-align: left;">
                    <div>
                        <h1 class="page-title" style="justify-content: flex-start;">
                            <i data-lucide="file-stack"></i>
                            RA 9048 / 10172 Transactions
                        </h1>
                        <p class="page-subtitle">Select a transaction type to begin encoding</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="records.php" class="ra9048-btn-action">
                            <i data-lucide="list"></i> All Records
                        </a>
                        <a href="export.php" class="ra9048-btn-action">
                            <i data-lucide="download"></i> Export
                        </a>
                    </div>
                </div>

                <!-- 3 Transaction Cards -->
                <div class="ra9048-card-grid" style="padding: 28px 20px;">
                    <!-- Petition Card -->
                    <a href="petition.php" class="ra9048-card ra9048-card--petition">
                        <div class="ra9048-card-icon">
                            <i data-lucide="file-pen"></i>
                        </div>
                        <div class="ra9048-card-title">Petition</div>
                        <div class="ra9048-card-desc">
                            CCE (Correction of Clerical Error) and CFN (Change of First Name) under RA 9048 / RA 10172
                        </div>
                        <div class="ra9048-card-footer">
                            <div class="ra9048-card-count">
                                <strong><?= number_format($counts['petitions']) ?></strong> record<?= $counts['petitions'] !== 1 ? 's' : '' ?>
                            </div>
                            <div class="ra9048-card-arrow">
                                <i data-lucide="arrow-right"></i>
                            </div>
                        </div>
                    </a>

                    <!-- Legal Instrument Card -->
                    <a href="legal_instrument.php" class="ra9048-card ra9048-card--legal">
                        <div class="ra9048-card-icon">
                            <i data-lucide="scale"></i>
                        </div>
                        <div class="ra9048-card-title">Legal Instrument</div>
                        <div class="ra9048-card-desc">
                            AUSF (Affidavit to Use Surname of Father), Supplemental Report, and Legitimation
                        </div>
                        <div class="ra9048-card-footer">
                            <div class="ra9048-card-count">
                                <strong><?= number_format($counts['legal_instruments']) ?></strong> record<?= $counts['legal_instruments'] !== 1 ? 's' : '' ?>
                            </div>
                            <div class="ra9048-card-arrow">
                                <i data-lucide="arrow-right"></i>
                            </div>
                        </div>
                    </a>

                    <!-- Court Decree Card -->
                    <a href="court_decree.php" class="ra9048-card ra9048-card--decree">
                        <div class="ra9048-card-icon">
                            <i data-lucide="gavel"></i>
                        </div>
                        <div class="ra9048-card-title">Court Decree</div>
                        <div class="ra9048-card-desc">
                            Registration of court orders and decrees — Adoption, Annulment, Legal Separation, and more
                        </div>
                        <div class="ra9048-card-footer">
                            <div class="ra9048-card-count">
                                <strong><?= number_format($counts['court_decrees']) ?></strong> record<?= $counts['court_decrees'] !== 1 ? 's' : '' ?>
                            </div>
                            <div class="ra9048-card-arrow">
                                <i data-lucide="arrow-right"></i>
                            </div>
                        </div>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
