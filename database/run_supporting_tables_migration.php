<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run Migration - Supporting Tables</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
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
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #664d03;
        }
        button {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #0b5ed7;
        }
        button.danger {
            background: #dc3545;
        }
        button.danger:hover {
            background: #bb2d3b;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #6c757d;
        }
        .output {
            margin-top: 20px;
        }
        ul {
            line-height: 1.8;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Migration Runner - Supporting Tables</h1>

        <div class="alert alert-info">
            <strong>📋 What This Migration Does:</strong>
            <ul>
                <li>✅ <strong>NO CHANGES</strong> to existing <code>certificate_of_live_birth</code> table</li>
                <li>✅ <strong>NO CHANGES</strong> to existing <code>certificate_of_marriage</code> table</li>
                <li>✅ Creates NEW supporting tables for enhanced features</li>
                <li>✅ Adds OCR processing capabilities</li>
                <li>✅ Adds workflow management system</li>
                <li>✅ Adds version tracking</li>
                <li>✅ Adds quality assurance features</li>
                <li>✅ Adds batch upload support</li>
            </ul>
        </div>

        <div class="alert alert-warning">
            <strong>⚠️ Before Running:</strong>
            <ul>
                <li>Backup your database first!</li>
                <li>Make sure you're running this on the correct database</li>
                <li>This is a <strong>one-way migration</strong> - plan for rollback if needed</li>
            </ul>
        </div>

        <form method="POST" onsubmit="return confirm('Are you sure you want to run this migration? Make sure you have backed up your database!');">
            <button type="submit" name="action" value="run">▶️ Run Migration</button>
            <button type="button" onclick="window.location.href='../admin/dashboard.php'">⬅️ Back to Dashboard</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            require_once '../includes/config.php';

            echo '<div class="output">';
            echo '<h2>Migration Output:</h2>';

            try {
                // Read the migration file
                $migrationFile = __DIR__ . '/migrations/001_add_supporting_tables_only.sql';

                if (!file_exists($migrationFile)) {
                    throw new Exception("Migration file not found: " . $migrationFile);
                }

                $sql = file_get_contents($migrationFile);

                echo '<div class="alert alert-info">📄 Migration file loaded: ' . basename($migrationFile) . '</div>';

                // Start transaction
                $pdo->beginTransaction();

                echo '<div class="alert alert-info">🔄 Starting migration...</div>';

                // Split SQL into individual statements
                $split = preg_split('/;(?=(?:[^\'"]|\'[^\']*\'|"[^"]*")*$)/', $sql);

                if ($split === false) {
                    throw new Exception("Failed to parse SQL statements");
                }

                $statements = array_filter(
                    array_map('trim', $split),
                    function($stmt) {
                        // Filter out comments and empty statements
                        return !empty($stmt) &&
                               !preg_match('/^\s*--/', $stmt) &&
                               !preg_match('/^\s*\/\*/', $stmt);
                    }
                );

                $successCount = 0;
                $errorCount = 0;
                $tableCount = 0;

                foreach ($statements as $statement) {
                    // Skip if it's just whitespace or comments
                    if (trim($statement) === '') continue;

                    try {
                        $pdo->exec($statement);
                        $successCount++;

                        // Count table creations
                        if (preg_match('/CREATE\s+TABLE/i', $statement)) {
                            $tableCount++;
                            // Extract table name
                            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                                echo '<div class="alert alert-success">✅ Created table: <code>' . $matches[1] . '</code></div>';
                            }
                        }
                        // Count view creations
                        else if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW/i', $statement)) {
                            if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+`?(\w+)`?/i', $statement, $matches)) {
                                echo '<div class="alert alert-success">✅ Created view: <code>' . $matches[1] . '</code></div>';
                            }
                        }
                        // Count inserts
                        else if (preg_match('/INSERT\s+INTO/i', $statement)) {
                            if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $statement, $matches)) {
                                echo '<div class="alert alert-success">✅ Inserted data into: <code>' . $matches[1] . '</code></div>';
                            }
                        }

                    } catch (PDOException $e) {
                        // Only count as error if it's not "table already exists"
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $errorCount++;
                            echo '<div class="alert alert-danger">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';

                            // Show problematic SQL (first 200 chars)
                            echo '<pre>' . htmlspecialchars(substr($statement, 0, 200)) . '...</pre>';
                        } else {
                            echo '<div class="alert alert-warning">⚠️ Table already exists, skipping...</div>';
                        }
                    }
                }

                // Commit transaction
                $pdo->commit();

                echo '<div class="alert alert-success">';
                echo '<h3>🎉 Migration Completed Successfully!</h3>';
                echo '<ul>';
                echo '<li><strong>Total Statements Executed:</strong> ' . $successCount . '</li>';
                echo '<li><strong>Tables Created:</strong> ' . $tableCount . '</li>';
                echo '<li><strong>Errors:</strong> ' . $errorCount . '</li>';
                echo '</ul>';
                echo '</div>';

                // List created tables
                echo '<h3>📊 Verifying Created Tables:</h3>';
                $checkTables = [
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

                foreach ($checkTables as $tableName) {
                    try {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
                        if ($stmt->rowCount() > 0) {
                            // Get row count
                            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$tableName`");
                            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
                            echo '<div class="alert alert-success">✅ <code>' . $tableName . '</code> exists (' . $count . ' rows)</div>';
                        } else {
                            echo '<div class="alert alert-danger">❌ <code>' . $tableName . '</code> NOT found!</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">❌ Error checking <code>' . $tableName . '</code>: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }

                echo '<div class="alert alert-info">';
                echo '<h3>✅ Next Steps:</h3>';
                echo '<ul>';
                echo '<li>Your existing birth and marriage certificate data is <strong>completely untouched</strong></li>';
                echo '<li>New features are now available through the supporting tables</li>';
                echo '<li>OCR processing can now be enabled</li>';
                echo '<li>Workflow management is ready to use</li>';
                echo '<li>Check the system settings table for configuration options</li>';
                echo '</ul>';
                echo '</div>';

            } catch (Exception $e) {
                // Rollback on error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                echo '<div class="alert alert-danger">';
                echo '<h3>❌ Migration Failed!</h3>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p>The database has been rolled back to its previous state.</p>';
                echo '</div>';
            }

            echo '</div>';
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 14px;">
            <strong>💡 Note:</strong> This migration only adds new tables. Your existing forms and data continue to work exactly as before.
            The new features are optional enhancements that can be enabled gradually.
        </div>
    </div>
</body>
</html>
