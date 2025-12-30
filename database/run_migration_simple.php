<?php
/**
 * Simple Migration Runner
 * Runs the SQL migration file directly
 */

require_once '../includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Migration - iScan</title>
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
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Database Migration</h1>

        <div class="alert alert-info">
            <strong>📋 This will create:</strong>
            <ul style="margin-top: 10px;">
                <li>11 new supporting tables</li>
                <li>System settings with defaults</li>
                <li>No changes to existing tables</li>
            </ul>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
            try {
                $sqlFile = __DIR__ . '/migrations/001_add_supporting_tables_only.sql';

                if (!file_exists($sqlFile)) {
                    throw new Exception("Migration file not found!");
                }

                echo '<div class="alert alert-info">📄 Reading migration file...</div>';

                // Read the entire SQL file
                $sql = file_get_contents($sqlFile);

                // Remove comments
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Execute using mysqli for multi_query support
                $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                if ($mysqli->connect_error) {
                    throw new Exception("Connection failed: " . $mysqli->connect_error);
                }

                echo '<div class="alert alert-info">🔄 Executing migration...</div>';

                // Execute all statements at once
                if ($mysqli->multi_query($sql)) {
                    $count = 0;
                    do {
                        $count++;
                        if ($result = $mysqli->store_result()) {
                            $result->free();
                        }
                    } while ($mysqli->next_result());

                    if ($mysqli->errno) {
                        throw new Exception("Error: " . $mysqli->error);
                    }

                    echo '<div class="alert alert-success">';
                    echo '<h3>✅ Migration Completed Successfully!</h3>';
                    echo '<p>Executed ' . $count . ' SQL statements</p>';
                    echo '</div>';

                    // Verify tables
                    echo '<h3>📊 Verifying Tables:</h3>';
                    $tables = [
                        'pdf_attachments',
                        'workflow_states',
                        'workflow_transitions',
                        'certificate_versions',
                        'validation_discrepancies',
                        'ocr_processing_queue',
                        'batch_uploads',
                        'batch_upload_items',
                        'qa_samples',
                        'user_performance_metrics',
                        'system_settings'
                    ];

                    echo '<pre>';
                    foreach ($tables as $table) {
                        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
                        if ($result && $result->num_rows > 0) {
                            echo "✅ $table - Created\n";
                        } else {
                            echo "❌ $table - NOT FOUND\n";
                        }
                    }
                    echo '</pre>';

                } else {
                    throw new Exception("Multi-query failed: " . $mysqli->error);
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
                <p style="margin-bottom: 20px;">Ready to run the migration?</p>
                <button type="submit" name="run_migration" value="1">▶️ Run Migration Now</button>
                <button type="button" onclick="window.location.href='../admin/dashboard.php'" style="background: #6c757d;">← Cancel</button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>
