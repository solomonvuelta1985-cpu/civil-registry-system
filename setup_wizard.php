<?php
/**
 * iScan Setup Wizard for Shared Hosting
 * Browser-based setup tool for cPanel/Plesk environments
 *
 * SECURITY: This file auto-deletes after successful setup.
 * If setup fails, delete this file manually after configuration.
 */

// Start session for wizard state
session_start();

// Initialize wizard state
if (!isset($_SESSION['wizard_step'])) {
    $_SESSION['wizard_step'] = 1;
    $_SESSION['wizard_token'] = bin2hex(random_bytes(32));
}

// Security: Verify token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['wizard_token']) || $_POST['wizard_token'] !== $_SESSION['wizard_token']) {
        die('Security token mismatch. Please refresh and try again.');
    }
}

// Base path detection
define('BASE_PATH', dirname(__FILE__));

// Handle wizard navigation
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'next':
            $_SESSION['wizard_step']++;
            break;
        case 'back':
            $_SESSION['wizard_step']--;
            break;
        case 'goto':
            if (isset($_POST['step'])) {
                $_SESSION['wizard_step'] = (int)$_POST['step'];
            }
            break;
    }
}

$step = $_SESSION['wizard_step'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iScan Setup Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .wizard-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .wizard-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .wizard-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.3);
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: #4ade80;
            transition: width 0.3s ease;
        }

        .wizard-body {
            padding: 40px;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 0 20px;
        }

        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: -1;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .step-item.active .step-number {
            background: #667eea;
            color: white;
        }

        .step-item.completed .step-number {
            background: #4ade80;
            color: white;
        }

        .step-label {
            font-size: 12px;
            color: #6b7280;
        }

        .step-item.active .step-label {
            color: #667eea;
            font-weight: 600;
        }

        .step-content h2 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .step-content p {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .check-list {
            list-style: none;
            margin: 20px 0;
        }

        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .check-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
        }

        .check-icon.success {
            background: #4ade80;
            color: white;
        }

        .check-icon.error {
            background: #ef4444;
            color: white;
        }

        .check-icon.warning {
            background: #f59e0b;
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .wizard-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .code-block {
            background: #1f2937;
            color: #e5e7eb;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 20px 0;
        }

        .spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-log {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 20px 0;
        }

        .log-line {
            margin-bottom: 5px;
            color: #374151;
        }

        .log-line.success {
            color: #059669;
        }

        .log-line.error {
            color: #dc2626;
        }

        .log-line.info {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>🚀 iScan Setup Wizard</h1>
            <p>Civil Registry Records Management System</p>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= ($step / 6) * 100 ?>%"></div>
        </div>

        <div class="wizard-body">
            <div class="step-indicator">
                <div class="step-item <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Check</div>
                </div>
                <div class="step-item <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step-item <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">Import</div>
                </div>
                <div class="step-item <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">
                    <div class="step-number">4</div>
                    <div class="step-label">Assets</div>
                </div>
                <div class="step-item <?= $step >= 5 ? 'active' : '' ?> <?= $step > 5 ? 'completed' : '' ?>">
                    <div class="step-number">5</div>
                    <div class="step-label">Security</div>
                </div>
                <div class="step-item <?= $step >= 6 ? 'active' : '' ?> <?= $step > 6 ? 'completed' : '' ?>">
                    <div class="step-number">6</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>

            <div class="step-content">
                <?php
                // Step 1: System Requirements Check
                if ($step === 1):
                    $checks = [];

                    // PHP Version
                    $phpVersion = phpversion();
                    $checks[] = [
                        'name' => 'PHP Version ' . $phpVersion,
                        'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'success' : 'error',
                        'message' => version_compare($phpVersion, '7.4.0', '>=') ? 'PHP 7.4+ detected' : 'PHP 7.4+ required'
                    ];

                    // Required extensions
                    $requiredExtensions = ['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'json'];
                    foreach ($requiredExtensions as $ext) {
                        $checks[] = [
                            'name' => "PHP Extension: $ext",
                            'status' => extension_loaded($ext) ? 'success' : 'error',
                            'message' => extension_loaded($ext) ? 'Installed' : 'Required'
                        ];
                    }

                    // Memory limit
                    $memoryLimit = ini_get('memory_limit');
                    $memoryBytes = return_bytes($memoryLimit);
                    $checks[] = [
                        'name' => 'Memory Limit: ' . $memoryLimit,
                        'status' => $memoryBytes >= 268435456 ? 'success' : 'warning',
                        'message' => $memoryBytes >= 268435456 ? 'Sufficient' : '256MB+ recommended'
                    ];

                    // File permissions
                    $writableDirs = ['uploads', 'logs'];
                    foreach ($writableDirs as $dir) {
                        $path = BASE_PATH . '/' . $dir;
                        $writable = is_writable($path);
                        $checks[] = [
                            'name' => "$dir/ writable",
                            'status' => $writable ? 'success' : 'error',
                            'message' => $writable ? 'OK' : 'Set permissions to 755 or 775'
                        ];
                    }

                    // Check if .env exists
                    $envExists = file_exists(BASE_PATH . '/.env');
                    $checks[] = [
                        'name' => '.env file',
                        'status' => $envExists ? 'warning' : 'success',
                        'message' => $envExists ? 'Already exists (will not overwrite)' : 'Ready to create'
                    ];

                    $allPassed = true;
                    foreach ($checks as $check) {
                        if ($check['status'] === 'error') {
                            $allPassed = false;
                            break;
                        }
                    }
                ?>
                    <h2>System Requirements Check</h2>
                    <p>Verifying your hosting environment meets the minimum requirements...</p>

                    <ul class="check-list">
                        <?php foreach ($checks as $check): ?>
                            <li class="check-item">
                                <div class="check-icon <?= $check['status'] ?>">
                                    <?php if ($check['status'] === 'success'): ?>✓<?php endif; ?>
                                    <?php if ($check['status'] === 'error'): ?>✗<?php endif; ?>
                                    <?php if ($check['status'] === 'warning'): ?>!</php endif; ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($check['name']) ?></strong><br>
                                    <span class="form-hint"><?= htmlspecialchars($check['message']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!$allPassed): ?>
                        <div class="alert alert-error">
                            <strong>⚠️ Action Required:</strong> Please fix the errors above before continuing. Contact your hosting provider if you need help enabling PHP extensions or adjusting settings.
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                        <div class="wizard-actions">
                            <div></div>
                            <button type="submit" name="action" value="next" class="btn btn-primary" <?= !$allPassed ? 'disabled' : '' ?>>
                                Next: Database Setup →
                            </button>
                        </div>
                    </form>

                <?php
                // Step 2: Database Configuration
                elseif ($step === 2):
                    $dbError = '';
                    $dbSuccess = false;

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
                        $dbHost = $_POST['db_host'] ?? '';
                        $dbName = $_POST['db_name'] ?? '';
                        $dbUser = $_POST['db_user'] ?? '';
                        $dbPass = $_POST['db_pass'] ?? '';

                        try {
                            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
                            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                            $dbSuccess = true;

                            // Save credentials to session
                            $_SESSION['db_host'] = $dbHost;
                            $_SESSION['db_name'] = $dbName;
                            $_SESSION['db_user'] = $dbUser;
                            $_SESSION['db_pass'] = $dbPass;

                            // Create .env file
                            if (!file_exists(BASE_PATH . '/.env')) {
                                $envTemplate = file_get_contents(BASE_PATH . '/.env.production');
                                $envTemplate = str_replace('DB_HOST=localhost', "DB_HOST=$dbHost", $envTemplate);
                                $envTemplate = str_replace('DB_NAME=username_iscan_db', "DB_NAME=$dbName", $envTemplate);
                                $envTemplate = str_replace('DB_USER=username_iscan_user', "DB_USER=$dbUser", $envTemplate);
                                $envTemplate = str_replace('DB_PASS=CHANGE_THIS_PASSWORD', "DB_PASS=$dbPass", $envTemplate);
                                file_put_contents(BASE_PATH . '/.env', $envTemplate);
                                chmod(BASE_PATH . '/.env', 0600);
                            }

                        } catch (PDOException $e) {
                            $dbError = $e->getMessage();
                        }
                    }

                    $dbHost = $_SESSION['db_host'] ?? 'localhost';
                    $dbName = $_SESSION['db_name'] ?? '';
                    $dbUser = $_SESSION['db_user'] ?? '';
                    $dbPass = $_SESSION['db_pass'] ?? '';
                ?>
                    <h2>Database Configuration</h2>
                    <p>Enter your MySQL/MariaDB database credentials from cPanel.</p>

                    <?php if ($dbSuccess): ?>
                        <div class="alert alert-success">
                            <strong>✓ Connection Successful!</strong> .env file has been created with your database credentials.
                        </div>
                    <?php endif; ?>

                    <?php if ($dbError): ?>
                        <div class="alert alert-error">
                            <strong>✗ Connection Failed:</strong> <?= htmlspecialchars($dbError) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">

                        <div class="form-group">
                            <label>Database Host</label>
                            <input type="text" name="db_host" value="<?= htmlspecialchars($dbHost) ?>" required>
                            <div class="form-hint">Usually "localhost" for cPanel hosting</div>
                        </div>

                        <div class="form-group">
                            <label>Database Name</label>
                            <input type="text" name="db_name" value="<?= htmlspecialchars($dbName) ?>" required placeholder="cpanel_username_iscan_db">
                            <div class="form-hint">Include the cPanel username prefix (e.g., username_iscan_db)</div>
                        </div>

                        <div class="form-group">
                            <label>Database User</label>
                            <input type="text" name="db_user" value="<?= htmlspecialchars($dbUser) ?>" required placeholder="cpanel_username_iscan_user">
                            <div class="form-hint">Include the cPanel username prefix (e.g., username_iscan_user)</div>
                        </div>

                        <div class="form-group">
                            <label>Database Password</label>
                            <input type="password" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>" required>
                            <div class="form-hint">The password you created in cPanel MySQL Databases</div>
                        </div>

                        <div class="wizard-actions">
                            <button type="submit" name="action" value="back" class="btn btn-secondary">
                                ← Back
                            </button>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" name="test_connection" value="1" class="btn btn-secondary">
                                    Test Connection
                                </button>
                                <button type="submit" name="action" value="next" class="btn btn-primary" <?= !$dbSuccess ? 'disabled' : '' ?>>
                                    Next: Import Database →
                                </button>
                            </div>
                        </div>
                    </form>

                <?php
                // Step 3: Import Database
                elseif ($step === 3):
                    $importStarted = isset($_POST['start_import']);
                    $importComplete = isset($_SESSION['import_complete']) && $_SESSION['import_complete'];
                ?>
                    <h2>Import Database Schema</h2>
                    <p>Import the database schema and migrations to set up the tables.</p>

                    <?php if (!$importStarted && !$importComplete): ?>
                        <div class="alert alert-info">
                            <strong>ℹ️ Ready to Import:</strong> This will import the base schema and all migrations. This may take 30-60 seconds.
                        </div>

                        <form method="POST">
                            <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                            <div class="wizard-actions">
                                <button type="submit" name="action" value="back" class="btn btn-secondary">
                                    ← Back
                                </button>
                                <button type="submit" name="start_import" value="1" class="btn btn-primary">
                                    Start Import
                                </button>
                            </div>
                        </form>
                    <?php elseif ($importStarted && !$importComplete): ?>
                        <?php
                        // Perform import
                        $logs = [];
                        $success = true;

                        try {
                            $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4";
                            $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass'], [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                            ]);

                            // Import base schema
                            $logs[] = ['type' => 'info', 'message' => 'Importing database_schema.sql...'];
                            $sql = file_get_contents(BASE_PATH . '/database_schema.sql');
                            $pdo->exec($sql);
                            $logs[] = ['type' => 'success', 'message' => '✓ Base schema imported'];

                            // Import migrations
                            $migrations = [
                                '002_workflow_versioning_ocr_tables.sql',
                                '003_calendar_notes_system.sql',
                                '004_add_citizenship_to_birth_certificates.sql',
                                '005_add_barangay_and_time_of_birth.sql',
                                '006_registered_devices.sql',
                                '007_add_pdf_hash.sql'
                            ];

                            foreach ($migrations as $migration) {
                                $path = BASE_PATH . '/database/migrations/' . $migration;
                                if (file_exists($path)) {
                                    $logs[] = ['type' => 'info', 'message' => "Importing $migration..."];
                                    $sql = file_get_contents($path);
                                    $pdo->exec($sql);
                                    $logs[] = ['type' => 'success', 'message' => "✓ $migration imported"];
                                }
                            }

                            $logs[] = ['type' => 'success', 'message' => '✓ All migrations completed successfully!'];
                            $_SESSION['import_complete'] = true;

                        } catch (PDOException $e) {
                            $logs[] = ['type' => 'error', 'message' => '✗ Error: ' . $e->getMessage()];
                            $success = false;
                        }
                        ?>

                        <div class="progress-log">
                            <?php foreach ($logs as $log): ?>
                                <div class="log-line <?= $log['type'] ?>"><?= htmlspecialchars($log['message']) ?></div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>✓ Import Complete!</strong> Database schema and migrations imported successfully.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-error">
                                <strong>✗ Import Failed:</strong> Please check the log above. You may need to import manually via phpMyAdmin.
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                            <div class="wizard-actions">
                                <button type="submit" name="action" value="back" class="btn btn-secondary">
                                    ← Back
                                </button>
                                <button type="submit" name="action" value="next" class="btn btn-primary" <?= !$success ? 'disabled' : '' ?>>
                                    Next: Download Assets →
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <strong>✓ Already Imported!</strong> Database setup is complete.
                        </div>

                        <form method="POST">
                            <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                            <div class="wizard-actions">
                                <button type="submit" name="action" value="back" class="btn btn-secondary">
                                    ← Back
                                </button>
                                <button type="submit" name="action" value="next" class="btn btn-primary">
                                    Next: Download Assets →
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php
                // Step 4: Download Assets (Optional)
                elseif ($step === 4):
                ?>
                    <h2>Download Vendor Assets (Optional)</h2>
                    <p>Download CSS/JS libraries for offline mode. Skip if your hosting has reliable internet (most do).</p>

                    <div class="alert alert-info">
                        <strong>ℹ️ Recommendation:</strong> Most shared hosting has good internet. You can skip this step and use CDN mode (default). Enable offline mode later if needed.
                    </div>

                    <div class="form-group">
                        <label>What would you like to do?</label>
                        <select id="asset_choice" class="form-control">
                            <option value="skip">Skip - Use CDN (Recommended)</option>
                            <option value="download">Download Assets for Offline Mode</option>
                        </select>
                        <div class="form-hint">CDN mode requires internet but loads faster. Offline mode works without internet.</div>
                    </div>

                    <div id="download_section" style="display: none; margin-top: 20px;">
                        <div class="alert alert-warning">
                            <strong>⚠️ Note:</strong> Downloading assets may take 2-5 minutes depending on your server speed.
                        </div>
                        <div class="progress-log" id="download_log" style="display: none;"></div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                        <div class="wizard-actions">
                            <button type="submit" name="action" value="back" class="btn btn-secondary">
                                ← Back
                            </button>
                            <button type="submit" name="action" value="next" class="btn btn-primary">
                                Next: Security Check →
                            </button>
                        </div>
                    </form>

                    <script>
                        document.getElementById('asset_choice').addEventListener('change', function() {
                            const section = document.getElementById('download_section');
                            section.style.display = this.value === 'download' ? 'block' : 'none';
                        });
                    </script>

                <?php
                // Step 5: Security Check
                elseif ($step === 5):
                    $securityChecks = [];

                    // Check .env permissions
                    $envPath = BASE_PATH . '/.env';
                    $envPerms = fileperms($envPath);
                    $securityChecks[] = [
                        'name' => '.env file permissions',
                        'status' => ($envPerms & 0077) === 0 ? 'success' : 'warning',
                        'message' => ($envPerms & 0077) === 0 ? 'Secure (600)' : 'Set to 600 for better security'
                    ];

                    // Check uploads/ writable
                    $uploadsPath = BASE_PATH . '/uploads';
                    $securityChecks[] = [
                        'name' => 'uploads/ directory',
                        'status' => is_writable($uploadsPath) ? 'success' : 'error',
                        'message' => is_writable($uploadsPath) ? 'Writable' : 'Set to 755 or 775'
                    ];

                    // Check logs/ writable
                    $logsPath = BASE_PATH . '/logs';
                    $securityChecks[] = [
                        'name' => 'logs/ directory',
                        'status' => is_writable($logsPath) ? 'success' : 'error',
                        'message' => is_writable($logsPath) ? 'Writable' : 'Set to 755 or 775'
                    ];

                    // Check .htaccess exists
                    $htaccessExists = file_exists(BASE_PATH . '/.htaccess');
                    $securityChecks[] = [
                        'name' => '.htaccess file',
                        'status' => $htaccessExists ? 'success' : 'warning',
                        'message' => $htaccessExists ? 'Exists' : 'Missing - security rules not applied'
                    ];
                ?>
                    <h2>Security Check</h2>
                    <p>Verifying file permissions and security configuration...</p>

                    <ul class="check-list">
                        <?php foreach ($securityChecks as $check): ?>
                            <li class="check-item">
                                <div class="check-icon <?= $check['status'] ?>">
                                    <?php if ($check['status'] === 'success'): ?>✓<?php endif; ?>
                                    <?php if ($check['status'] === 'error'): ?>✗<?php endif; ?>
                                    <?php if ($check['status'] === 'warning'): ?>!<?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($check['name']) ?></strong><br>
                                    <span class="form-hint"><?= htmlspecialchars($check['message']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="alert alert-warning">
                        <strong>⚠️ Important Security Steps:</strong>
                        <ul style="margin-top: 10px; margin-left: 20px;">
                            <li>Change the default admin password (admin/admin123) immediately after login</li>
                            <li>Install SSL certificate via cPanel for HTTPS</li>
                            <li>Set ENABLE_HSTS=true in .env after SSL is working</li>
                            <li>Delete setup_wizard.php after setup completes</li>
                        </ul>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                        <div class="wizard-actions">
                            <button type="submit" name="action" value="back" class="btn btn-secondary">
                                ← Back
                            </button>
                            <button type="submit" name="action" value="next" class="btn btn-primary">
                                Next: Complete Setup →
                            </button>
                        </div>
                    </form>

                <?php
                // Step 6: Complete
                elseif ($step === 6):
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $scriptName = $_SERVER['SCRIPT_NAME'];
                    $appUrl = $protocol . '://' . $host . dirname($scriptName) . '/public/login.php';
                ?>
                    <h2>🎉 Setup Complete!</h2>
                    <p>Your iScan installation is ready. Follow the steps below to access your application.</p>

                    <div class="alert alert-success">
                        <strong>✓ Installation Successful!</strong> All configuration steps completed.
                    </div>

                    <h3 style="margin-top: 30px; margin-bottom: 15px;">Next Steps:</h3>

                    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <p style="margin-bottom: 10px;"><strong>1. Access Your Application:</strong></p>
                        <div class="code-block"><?= htmlspecialchars($appUrl) ?></div>

                        <p style="margin-bottom: 10px; margin-top: 20px;"><strong>2. Default Login Credentials:</strong></p>
                        <div class="code-block">Username: admin
Password: admin123</div>

                        <p style="margin-bottom: 10px; margin-top: 20px;"><strong>3. Change Admin Password:</strong></p>
                        <p style="color: #6b7280; font-size: 14px;">After logging in: Admin Panel → Users → Edit admin → Change Password</p>

                        <p style="margin-bottom: 10px; margin-top: 20px;"><strong>4. Security Recommendations:</strong></p>
                        <ul style="margin-left: 20px; color: #6b7280; font-size: 14px;">
                            <li>Install SSL certificate (cPanel → SSL/TLS)</li>
                            <li>Set ENABLE_HSTS=true in .env after SSL is working</li>
                            <li>Register authorized devices (Admin → Devices)</li>
                            <li>Set up regular backups (cPanel → Backup Wizard)</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <strong>🔒 Security:</strong> This setup wizard will be automatically deleted when you click "Finish" below. If deletion fails, manually delete setup_wizard.php via cPanel File Manager.
                    </div>

                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="wizard_token" value="<?= $_SESSION['wizard_token'] ?>">
                        <input type="hidden" name="delete_wizard" value="1">
                        <div class="wizard-actions">
                            <button type="submit" name="action" value="back" class="btn btn-secondary">
                                ← Back
                            </button>
                            <button type="submit" class="btn btn-danger">
                                Finish & Delete Wizard
                            </button>
                        </div>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Handle wizard completion and self-delete
if (isset($_POST['delete_wizard'])) {
    // Clear session
    session_destroy();

    // Attempt to delete this file
    $deleted = @unlink(__FILE__);

    if ($deleted) {
        // Redirect to login
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $loginUrl = $protocol . '://' . $host . dirname($scriptName) . '/public/login.php';
        header("Location: $loginUrl");
        exit;
    } else {
        echo '<script>alert("Setup complete but wizard file could not be auto-deleted. Please delete setup_wizard.php manually via cPanel File Manager for security."); window.location.href = "public/login.php";</script>';
        exit;
    }
}

// Helper function to convert memory limit to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
?>
