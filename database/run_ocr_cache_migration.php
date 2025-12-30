<?php
/**
 * OCR Cache Table Migration
 * Creates ONLY the ocr_cache table
 */

require_once '../includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Cache Migration - iScan</title>
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
        .alert-info {
            background: #cfe2ff;
            border-color: #0d6efd;
            color: #084298;
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
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚡ OCR Cache Table Migration</h1>

        <div class="alert alert-info">
            <strong>📋 This will create:</strong>
            <ul style="margin-top: 10px;">
                <li><code class="code">ocr_cache</code> table for fast OCR results caching</li>
                <li>Enables instant subsequent PDF loads (0 seconds!)</li>
                <li>No changes to existing tables</li>
            </ul>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
            try {
                // First check if table already exists
                $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                if ($mysqli->connect_error) {
                    throw new Exception("Connection failed: " . $mysqli->connect_error);
                }

                echo '<div class="alert alert-info">🔍 Checking if table exists...</div>';

                $result = $mysqli->query("SHOW TABLES LIKE 'ocr_cache'");
                if ($result && $result->num_rows > 0) {
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Table Already Exists!</h3>';
                    echo '<p>The <code class="code">ocr_cache</code> table is already created. No migration needed.</p>';
                    echo '<p style="margin-top: 15px;">You can proceed to test the fast OCR feature!</p>';
                    echo '<button onclick="window.location.href=\'../public/certificate_of_live_birth.php\'">🧪 Test OCR Now</button>';
                    echo '</div>';
                    $mysqli->close();
                    exit;
                }

                echo '<div class="alert alert-info">📄 Creating ocr_cache table...</div>';

                // SQL to create ONLY the ocr_cache table
                $sql = "
                CREATE TABLE IF NOT EXISTS `ocr_cache` (
                  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `file_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of PDF file',
                  `file_name` VARCHAR(255) DEFAULT NULL,
                  `file_size` INT(11) DEFAULT NULL,
                  `ocr_text` LONGTEXT NOT NULL COMMENT 'Raw OCR extracted text',
                  `structured_data` JSON DEFAULT NULL COMMENT 'Parsed field data',
                  `processing_time` DECIMAL(6,2) DEFAULT NULL COMMENT 'Processing time in seconds',
                  `tesseract_version` VARCHAR(50) DEFAULT NULL,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `last_accessed` TIMESTAMP NULL DEFAULT NULL,
                  `access_count` INT(11) DEFAULT 0,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `file_hash` (`file_hash`),
                  KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cache for OCR processing results';
                ";

                if ($mysqli->query($sql)) {
                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Migration Completed Successfully!</h3>';
                    echo '<p>The <code class="code">ocr_cache</code> table has been created.</p>';
                    echo '</div>';

                    // Verify table structure
                    echo '<h3>📊 Table Structure:</h3>';
                    $result = $mysqli->query("DESCRIBE ocr_cache");
                    if ($result) {
                        echo '<pre>';
                        echo sprintf("%-20s %-30s %-10s\n", 'Field', 'Type', 'Key');
                        echo str_repeat('-', 65) . "\n";
                        while ($row = $result->fetch_assoc()) {
                            echo sprintf("%-20s %-30s %-10s\n",
                                $row['Field'],
                                $row['Type'],
                                $row['Key']
                            );
                        }
                        echo '</pre>';
                    }

                    echo '<div class="alert alert-success">';
                    echo '<h3>🚀 Next Steps:</h3>';
                    echo '<ol style="margin-left: 20px;">';
                    echo '<li>Verify Tesseract installation: <button onclick="window.location.href=\'verify_tesseract.php\'">🔍 Verify Tesseract</button></li>';
                    echo '<li>Test fast OCR: <button onclick="window.location.href=\'../public/certificate_of_live_birth.php\'">🧪 Test OCR</button></li>';
                    echo '</ol>';
                    echo '</div>';

                } else {
                    throw new Exception("Failed to create table: " . $mysqli->error);
                }

                $mysqli->close();

            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '<h3>❌ Migration Failed!</h3>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        } else {
            ?>
            <form method="POST">
                <p style="margin-bottom: 20px;">Ready to create the OCR cache table?</p>
                <button type="submit" name="run_migration" value="1">▶️ Create Table Now</button>
                <button type="button" onclick="window.location.href='verify_tesseract.php'" style="background: #6c757d;">🔍 Verify Tesseract First</button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>
