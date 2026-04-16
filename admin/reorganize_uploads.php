<?php
/**
 * Reorganize Uploads — Admin Tool
 * Moves existing PDFs into the correct year + last-name folder structure.
 * Admin-only. Requires typed confirmation for the Apply action.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/reorganize_uploads.php';

requireAuth();
requireAdmin();
setSecurityHeaders();

$csrfField = csrfTokenField();
$csrfMeta  = csrfTokenMeta();

$output   = null;
$stats    = null;
$ranMode  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    $action = $_POST['action'] ?? '';

    if ($action === 'dry-run') {
        $ranMode = 'DRY-RUN';
        $lines   = [];
        $log     = function (string $line) use (&$lines) { $lines[] = $line; };
        $stats   = reorganize_uploads($pdo, UPLOAD_DIR, false, $log);
        $output  = implode("\n", $lines);

    } elseif ($action === 'apply') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm !== 'I UNDERSTAND') {
            $output = 'ERROR: You must type "I UNDERSTAND" to confirm the apply action.';
        } else {
            $ranMode = 'APPLY';
            $lines   = [];
            $logDir  = BASE_PATH . '/scripts/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/reorganize_' . date('Ymd_His') . '.log';
            $fh      = fopen($logFile, 'w');

            $log = function (string $line) use (&$lines, $fh) {
                $ts = date('Y-m-d H:i:s');
                $formatted = "[{$ts}] {$line}";
                $lines[] = $formatted;
                if ($fh) fwrite($fh, $formatted . "\n");
            };

            $stats  = reorganize_uploads($pdo, UPLOAD_DIR, true, $log);
            $output = implode("\n", $lines);
            if ($fh) fclose($fh);
            $output .= "\n\nLog saved to: {$logFile}";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reorganize Uploads - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>

    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; background: #f4f6f9; color: #1a202c; }
        .content { padding: 32px 36px; max-width: 1600px; }

        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; color: #1a202c; }
        .page-header p  { color: #718096; font-size: 0.95rem; margin-top: 4px; }

        .card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06); overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px; border-bottom: 1px solid #e2e8f0;
            font-weight: 600; font-size: 1rem;
        }
        .card-body { padding: 22px; }

        .info-box {
            background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px;
            padding: 14px 18px; margin-bottom: 18px; font-size: 0.9rem; color: #2a4365;
        }
        .warning-box {
            background: #fffbeb; border: 1px solid #f6ad55; border-radius: 8px;
            padding: 14px 18px; margin-bottom: 18px; font-size: 0.9rem; color: #744210;
        }

        .form-row { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border: none; border-radius: 8px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary { background: #3182ce; color: #fff; }
        .btn-primary:hover { background: #2b6cb0; }
        .btn-danger { background: #e53e3e; color: #fff; }
        .btn-danger:hover { background: #c53030; }

        .confirm-input {
            padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 0.9rem; width: 200px;
        }

        .output-box {
            background: #1a202c; color: #a0aec0; border-radius: 8px;
            padding: 18px; font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.82rem; line-height: 1.6;
            max-height: 500px; overflow-y: auto; white-space: pre-wrap;
            word-break: break-all;
        }
        .output-box .line-move { color: #68d391; }
        .output-box .line-skip { color: #a0aec0; }
        .output-box .line-err  { color: #fc8181; }
        .output-box .line-head { color: #90cdf4; font-weight: 600; }

        .stats-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .stat-pill {
            background: #edf2f7; border-radius: 20px; padding: 6px 16px;
            font-size: 0.85rem; font-weight: 600;
        }
        .stat-pill.moved { background: #c6f6d5; color: #22543d; }
        .stat-pill.error { background: #fed7d7; color: #742a2a; }

        .scheme-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .scheme-table th,
        .scheme-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .scheme-table th { background: #f7fafc; font-weight: 600; }
        code { background: #edf2f7; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">
    <div class="page-header">
        <h1><i data-lucide="folder-sync" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;"></i> Reorganize Uploads</h1>
        <p>Move existing PDFs into the correct year + last-name folder structure.</p>
    </div>

    <!-- Scheme reference -->
    <div class="card">
        <div class="card-header">Folder Scheme</div>
        <div class="card-body">
            <table class="scheme-table">
                <thead>
                    <tr><th>Scenario</th><th>Folder Path</th><th>Example</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Year derivable (DOB or registry)</td>
                        <td><code>{type}/{YEAR}/{LAST_NAME}/</code></td>
                        <td><code>birth/2014/DELOS_SANTOS/cert_xxx.pdf</code></td>
                    </tr>
                    <tr>
                        <td>No year anywhere</td>
                        <td><code>{type}/{LAST_NAME}/</code></td>
                        <td><code>birth/DELOS_SANTOS/cert_xxx.pdf</code></td>
                    </tr>
                </tbody>
            </table>
            <div class="info-box" style="margin-top:14px;">
                <strong>Year priority:</strong> Subject event date (DOB / date of death / date of marriage) wins over registry number prefix. Registry is only used if the event date is missing.
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="card">
        <div class="card-header">Run Reorganization</div>
        <div class="card-body">
            <div class="info-box">
                <strong>Dry Run</strong> scans all records and shows what would be moved without changing anything.
            </div>

            <form method="POST" style="margin-bottom: 24px;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="dry-run">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" style="width:16px;height:16px;"></i> Dry Run
                </button>
            </form>

            <div class="warning-box">
                <strong>Apply</strong> actually moves files and updates the database. This cannot be easily undone. Type <strong>I UNDERSTAND</strong> to confirm.
            </div>

            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="apply">
                <div class="form-row">
                    <input type="text" name="confirm_text" class="confirm-input" placeholder='Type "I UNDERSTAND"' autocomplete="off">
                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="play" style="width:16px;height:16px;"></i> Apply Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($output !== null): ?>
    <!-- Results -->
    <div class="card">
        <div class="card-header">Results (<?= htmlspecialchars($ranMode) ?>)</div>
        <div class="card-body">
            <?php if ($stats): ?>
            <div class="stats-row">
                <span class="stat-pill">Total: <?= $stats['total'] ?></span>
                <span class="stat-pill">Skipped: <?= $stats['skipped'] ?></span>
                <span class="stat-pill moved">Moved: <?= $stats['moved'] ?></span>
                <span class="stat-pill">Missing: <?= $stats['missing'] ?></span>
                <span class="stat-pill">Collision: <?= $stats['collision'] ?></span>
                <?php if ($stats['error'] > 0): ?>
                <span class="stat-pill error">Errors: <?= $stats['error'] ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="output-box"><?= htmlspecialchars($output) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
