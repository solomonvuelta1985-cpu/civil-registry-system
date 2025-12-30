<?php
/**
 * Tesseract Installation Verification Script
 * Checks if Tesseract is accessible from PHP
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tesseract Verification - iScan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d1e7dd;
            border-color: #198754;
            color: #0f5132;
        }
        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #842029;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .alert-info {
            background: #cfe2ff;
            border-color: #0d6efd;
            color: #084298;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
            border: 1px solid #dee2e6;
        }
        .check-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .check-item strong {
            display: inline-block;
            width: 200px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Tesseract Installation Verification</h1>

        <?php
        $tesseractPaths = [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'tesseract'  // Try from PATH
        ];

        $tesseractFound = false;
        $tesseractPath = '';
        $tesseractVersion = '';
        $execEnabled = false;
        $errors = [];

        // 1. Check if exec() is enabled
        echo '<h2>Step 1: PHP Configuration</h2>';

        if (function_exists('exec')) {
            $execEnabled = true;
            echo '<div class="alert alert-success">✅ <strong>exec() function:</strong> Enabled</div>';
        } else {
            echo '<div class="alert alert-danger">❌ <strong>exec() function:</strong> Disabled - You need to enable it in php.ini</div>';
            $errors[] = 'exec() function is disabled';
        }

        // 2. Try to find Tesseract
        echo '<h2>Step 2: Tesseract Detection</h2>';

        foreach ($tesseractPaths as $path) {
            if (file_exists($path) || $path === 'tesseract') {
                // Try to execute
                $command = sprintf('"%s" --version 2>&1', $path);
                exec($command, $output, $returnCode);

                if ($returnCode === 0 && !empty($output)) {
                    $tesseractFound = true;
                    $tesseractPath = $path;
                    $tesseractVersion = implode("\n", $output);
                    break;
                }
            }
        }

        if ($tesseractFound) {
            echo '<div class="alert alert-success">';
            echo '<strong>✅ Tesseract Found!</strong><br>';
            echo '<div class="check-item"><strong>Path:</strong> ' . htmlspecialchars($tesseractPath) . '</div>';
            echo '<div class="check-item"><strong>Version Info:</strong><pre>' . htmlspecialchars($tesseractVersion) . '</pre></div>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '<strong>❌ Tesseract Not Found!</strong><br>';
            echo '<p style="margin-top: 10px;">Searched in:</p>';
            echo '<ul style="margin-left: 20px;">';
            foreach ($tesseractPaths as $path) {
                echo '<li>' . htmlspecialchars($path) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            $errors[] = 'Tesseract executable not found';
        }

        // 3. Check temp directory
        echo '<h2>Step 3: Temporary Directory</h2>';

        $tempDir = sys_get_temp_dir();
        if (is_writable($tempDir)) {
            echo '<div class="alert alert-success">';
            echo '✅ <strong>Temp directory:</strong> Writable<br>';
            echo '<div class="check-item"><strong>Path:</strong> ' . htmlspecialchars($tempDir) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '❌ <strong>Temp directory:</strong> Not writable<br>';
            echo '<div class="check-item"><strong>Path:</strong> ' . htmlspecialchars($tempDir) . '</div>';
            echo '</div>';
            $errors[] = 'Temp directory not writable';
        }

        // 4. Check uploads directory
        echo '<h2>Step 4: Uploads Directory</h2>';

        $uploadsDir = dirname(__DIR__) . '/uploads';
        if (!file_exists($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        if (is_writable($uploadsDir)) {
            echo '<div class="alert alert-success">';
            echo '✅ <strong>Uploads directory:</strong> Writable<br>';
            echo '<div class="check-item"><strong>Path:</strong> ' . htmlspecialchars($uploadsDir) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-warning">';
            echo '⚠️ <strong>Uploads directory:</strong> Not writable<br>';
            echo '<div class="check-item"><strong>Path:</strong> ' . htmlspecialchars($uploadsDir) . '</div>';
            echo '</div>';
        }

        // 5. Final Summary
        echo '<h2>Summary</h2>';

        if (empty($errors)) {
            echo '<div class="alert alert-success">';
            echo '<h3>🎉 All Checks Passed!</h3>';
            echo '<p>Your system is ready for fast server-side OCR processing.</p>';
            echo '<ul style="margin-top: 10px; margin-left: 20px;">';
            echo '<li>✅ PHP exec() is enabled</li>';
            echo '<li>✅ Tesseract is installed and accessible</li>';
            echo '<li>✅ Temporary directory is writable</li>';
            echo '<li>✅ Uploads directory is ready</li>';
            echo '</ul>';
            echo '<p style="margin-top: 20px;"><strong>Next Step:</strong> Run the database migration to create the OCR cache table.</p>';
            echo '<button onclick="window.location.href=\'run_migration_simple.php\'">▶️ Run Database Migration</button>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '<h3>❌ Issues Found</h3>';
            echo '<p>The following issues need to be fixed:</p>';
            echo '<ul style="margin-top: 10px; margin-left: 20px;">';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';

            // Provide solutions
            echo '<div class="alert alert-info">';
            echo '<h3>💡 Solutions:</h3>';

            if (!$execEnabled) {
                echo '<h4>Enable exec() function:</h4>';
                echo '<ol style="margin-left: 20px;">';
                echo '<li>Open php.ini (in XAMPP: C:\\xampp\\php\\php.ini)</li>';
                echo '<li>Find the line: <code>disable_functions =</code></li>';
                echo '<li>Remove "exec" from the list</li>';
                echo '<li>Save and restart Apache</li>';
                echo '</ol>';
            }

            if (!$tesseractFound) {
                echo '<h4>Install/Configure Tesseract:</h4>';
                echo '<ol style="margin-left: 20px;">';
                echo '<li>If not installed, download from: <a href="https://github.com/UB-Mannheim/tesseract/wiki" target="_blank">https://github.com/UB-Mannheim/tesseract/wiki</a></li>';
                echo '<li>Install to: C:\\Program Files\\Tesseract-OCR</li>';
                echo '<li>Add to Windows PATH environment variable</li>';
                echo '<li>Restart XAMPP after installation</li>';
                echo '</ol>';
            }
            echo '</div>';
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #dee2e6;">
            <button onclick="location.reload()">🔄 Re-check</button>
            <button onclick="window.location.href='../admin/dashboard.php'" style="background: #6c757d;">← Back to Dashboard</button>
        </div>
    </div>
</body>
</html>
