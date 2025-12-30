<?php
/**
 * Error Log Viewer
 * View and manage PHP error logs
 * Admin access only
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin access (you can enhance this with proper role checking)
$current_user_role = $_SESSION['user_role'] ?? 'Admin';

$log_file = __DIR__ . '/../logs/php_errors.log';
$lines_to_show = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Get log content
$log_content = '';
$file_size = 0;
$line_count = 0;

if (file_exists($log_file)) {
    $file_size = filesize($log_file);
    $line_count = count(file($log_file));

    // Read file
    $all_lines = file($log_file);

    // Filter by search term if provided
    if ($search_term) {
        $all_lines = array_filter($all_lines, function($line) use ($search_term) {
            return stripos($line, $search_term) !== false;
        });
    }

    // Get last N lines
    $display_lines = array_slice($all_lines, -$lines_to_show);
    $log_content = implode('', array_reverse($display_lines));
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear':
                file_put_contents($log_file, '');
                header('Location: error_log_viewer.php?cleared=1');
                exit;

            case 'download':
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="php_errors_' . date('Y-m-d_His') . '.log"');
                readfile($log_file);
                exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Log Viewer - iScan Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);
        }

        header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header-info {
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0a3622;
            border-left: 4px solid #198754;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .control-group label {
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
        }

        .control-group input,
        .control-group select {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: #0d6efd;
            color: white;
        }

        .btn-success {
            background: #198754;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            max-height: 800px;
            overflow-y: auto;
        }

        .log-line {
            margin-bottom: 10px;
            padding: 5px;
            border-left: 3px solid transparent;
        }

        .log-line.error {
            border-left-color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .log-line.warning {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.1);
        }

        .log-line.notice {
            border-left-color: #0dcaf0;
            background: rgba(13, 202, 240, 0.1);
        }

        .log-timestamp {
            color: #6c757d;
        }

        .log-type {
            font-weight: bold;
            text-transform: uppercase;
        }

        .log-type.error { color: #dc3545; }
        .log-type.warning { color: #ffc107; }
        .log-type.notice { color: #0dcaf0; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            color: #6c757d;
        }

        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .controls-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Error Log Viewer</h1>
            <div class="header-info">
                Monitoring system errors and exceptions
            </div>
        </header>

        <?php if (isset($_GET['cleared'])): ?>
        <div class="alert alert-success">
            ✅ Log file has been cleared successfully
        </div>
        <?php endif; ?>

        <?php if (!file_exists($log_file)): ?>
        <div class="alert alert-warning">
            ⚠️ Log file not found. It will be created automatically when the first error occurs.
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($line_count) ?></div>
                <div class="stat-label">Total Log Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= formatFileSize($file_size) ?></div>
                <div class="stat-label">Log File Size</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $lines_to_show ?></div>
                <div class="stat-label">Displaying Lines</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= file_exists($log_file) ? date('M d, H:i', filemtime($log_file)) : 'N/A' ?></div>
                <div class="stat-label">Last Modified</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <form method="GET" action="">
                <div class="controls-grid">
                    <div class="control-group">
                        <label for="search">Search Logs:</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Enter search term...">
                    </div>

                    <div class="control-group">
                        <label for="lines">Lines to Show:</label>
                        <select id="lines" name="lines" onchange="this.form.submit()">
                            <option value="50" <?= $lines_to_show === 50 ? 'selected' : '' ?>>50 lines</option>
                            <option value="100" <?= $lines_to_show === 100 ? 'selected' : '' ?>>100 lines</option>
                            <option value="200" <?= $lines_to_show === 200 ? 'selected' : '' ?>>200 lines</option>
                            <option value="500" <?= $lines_to_show === 500 ? 'selected' : '' ?>>500 lines</option>
                            <option value="1000" <?= $lines_to_show === 1000 ? 'selected' : '' ?>>1000 lines</option>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="error_log_viewer.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>

            <div class="button-group" style="margin-top: 15px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="download">
                    <button type="submit" class="btn btn-success">📥 Download</button>
                </form>

                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear the log file? This cannot be undone.')">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-danger">🗑️ Clear Log</button>
                </form>

                <a href="../admin/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Log Viewer -->
        <?php if (file_exists($log_file) && $log_content): ?>
        <div class="log-viewer">
            <pre><?= highlightLogContent($log_content) ?></pre>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <h3>No Errors Found</h3>
            <p><?= $search_term ? 'No results match your search criteria' : 'The error log is currently empty' ?></p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh every 30 seconds if no search term
        <?php if (!$search_term): ?>
        setTimeout(() => {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

function highlightLogContent($content) {
    // Escape HTML
    $content = htmlspecialchars($content);

    // Highlight timestamps
    $content = preg_replace('/\[([\d\-: ]+)\]/', '<span class="log-timestamp">[$1]</span>', $content);

    // Highlight error types
    $content = preg_replace('/(Fatal Error|Error|Warning|Notice|Parse Error|Exception)/i', '<span class="log-type $1">$1</span>', $content);

    // Highlight file paths
    $content = preg_replace('/(in )(\/[^\s:]+)(:\d+)?/', '$1<span style="color: #4ec9b0;">$2</span>$3', $content);

    return $content;
}
?>
