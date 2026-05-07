<?php
/**
 * System Settings - Admin only
 * Civil Registry Document Management System (CRDMS)
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/settings.php';

requireAuth();
if (!isAdmin()) {
    http_response_code(403);
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

/**
 * Settings rendered on this page. Add an entry here to expose a new toggle.
 * Type is currently 'boolean' only; extend the renderer if other types are needed.
 */
$TOGGLE_DEFINITIONS = [
    [
        'key'         => 'ocr_enabled',
        'label'       => 'OCR / Scan Now Badge',
        'description' => 'When ON, the floating "Scan Now" badge appears on certificate forms and OCR engines are loaded. Turn OFF to hide the badge and skip loading OCR scripts.',
        'category'    => 'OCR',
    ],
];

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    $allowed_keys = array_column($TOGGLE_DEFINITIONS, 'key');
    $changes = [];

    foreach ($allowed_keys as $key) {
        $submitted = isset($_POST[$key]) && $_POST[$key] === '1';
        $current = (bool) get_setting($key, false);
        if ($submitted !== $current) {
            if (set_setting($pdo, $key, $submitted ? 'true' : 'false', getUserId())) {
                $changes[] = $key . '=' . ($submitted ? 'true' : 'false');
            }
        }
    }

    if (!empty($changes)) {
        log_activity($pdo, 'settings_updated', implode('; ', $changes), getUserId());
        $flash = ['type' => 'success', 'message' => 'Settings saved.'];
    } else {
        $flash = ['type' => 'info', 'message' => 'No changes.'];
    }
}

$current_page = 'settings.php';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Civil Registry</title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #1a1a1a;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .page-container {
            padding: 20px;
            max-width: 960px;
            margin: 0 auto;
        }
        .page-header {
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }
        .page-title [data-lucide] { color: #3b82f6; }
        .page-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .flash {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.info    { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        .flash.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .category-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .category-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .category-title {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding: 20px 24px;
            border-bottom: 1px solid #f3f4f6;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-info { flex: 1; }
        .toggle-label {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }
        .toggle-desc {
            font-size: 0.8125rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #d1d5db;
            transition: 0.2s;
            border-radius: 28px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: #ffffff;
            transition: 0.2s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: #3b82f6; }
        input:checked + .slider:before { transform: translateX(24px); }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 0;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.15s;
            font-family: inherit;
        }
        .btn-primary { background-color: #3b82f6; color: #ffffff; }
        .btn-primary:hover { background-color: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .content { margin-left: 0; padding-top: 120px; }
            .top-navbar { left: 0; }
            .mobile-header { display: block; }
            .sidebar-overlay.active { display: block; }
        }
        @media (max-width: 768px) {
            .toggle-row { flex-direction: column; gap: 14px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h4><i data-lucide="file-badge"></i> Civil Registry</h4>
            <button id="mobileSidebarToggle">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i data-lucide="settings"></i>
                        System Settings
                    </h1>
                    <p class="page-subtitle">Turn features on or off across the system.</p>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= htmlspecialchars($flash['type']) ?>">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                <?php
                $by_category = [];
                foreach ($TOGGLE_DEFINITIONS as $def) {
                    $by_category[$def['category']][] = $def;
                }
                foreach ($by_category as $category => $defs):
                ?>
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-title"><?= htmlspecialchars($category) ?></div>
                        </div>
                        <?php foreach ($defs as $def):
                            $checked = (bool) get_setting($def['key'], true);
                        ?>
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-label"><?= htmlspecialchars($def['label']) ?></div>
                                    <div class="toggle-desc"><?= htmlspecialchars($def['description']) ?></div>
                                </div>
                                <label class="switch" title="<?= htmlspecialchars($def['label']) ?>">
                                    <input type="checkbox" name="<?= htmlspecialchars($def['key']) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/sidebar_scripts.php'; ?>
    <script>
        if (window.lucide) { lucide.createIcons(); }
    </script>
</body>
</html>
