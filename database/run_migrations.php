<?php
/**
 * iScan Database Migration Runner (Web-Based)
 * Runs SQL migrations via browser for shared hosting without SSH
 *
 * SECURITY: Delete this file after running migrations
 * Or require authentication before use
 */

// Load environment configuration
require_once __DIR__ . '/../includes/env_loader.php';

// Simple authentication (optional - uncomment to require login)
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     die('Access denied. Admin login required.');
// }

// Database configuration
$dbHost = env('DB_HOST', 'localhost');
$dbName = env('DB_NAME', 'iscan_db');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');

// Migration tracking table
$migrationTable = 'migrations';

// Available migrations in order
$migrations = [
    '002_workflow_versioning_ocr_tables.sql',
    '003_calendar_notes_system.sql',
    '004_add_citizenship_to_birth_certificates.sql',
    '005_add_barangay_and_time_of_birth.sql',
    '006_registered_devices.sql',
    '007_add_pdf_hash.sql',
    '020_double_registration_linking.sql'
];

$results = [];
$pdo = null;

// Connect to database
try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Create migrations tracking table if doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS $migrationTable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    die('Failed to create migrations table: ' . htmlspecialchars($e->getMessage()));
}

// Get already executed migrations
$executedMigrations = [];
try {
    $stmt = $pdo->query("SELECT migration_name FROM $migrationTable");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Table might not exist yet
}

// Run migrations if requested
if (isset($_POST['run_migrations'])) {
    foreach ($migrations as $migration) {
        // Skip if already executed
        if (in_array($migration, $executedMigrations)) {
            $results[] = [
                'migration' => $migration,
                'status' => 'skipped',
                'message' => 'Already executed'
            ];
            continue;
        }

        $migrationPath = __DIR__ . '/migrations/' . $migration;

        if (!file_exists($migrationPath)) {
            $results[] = [
                'migration' => $migration,
                'status' => 'error',
                'message' => 'Migration file not found'
            ];
            continue;
        }

        try {
            // Read and execute SQL file
            $sql = file_get_contents($migrationPath);

            // Execute SQL (may contain multiple statements)
            $pdo->exec($sql);

            // Mark as executed
            $stmt = $pdo->prepare("INSERT INTO $migrationTable (migration_name) VALUES (?)");
            $stmt->execute([$migration]);

            $results[] = [
                'migration' => $migration,
                'status' => 'success',
                'message' => 'Executed successfully'
            ];

        } catch (PDOException $e) {
            $results[] = [
                'migration' => $migration,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    // Refresh executed migrations list
    $stmt = $pdo->query("SELECT migration_name FROM $migrationTable");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iScan Migration Runner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-executed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-skipped {
            background: #e5e7eb;
            color: #6b7280;
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .error-details {
            background: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 5px;
            color: #dc2626;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Database Migration Runner</h1>
            <p>Run SQL migrations for iScan</p>
        </div>

        <div class="content">
            <?php if (empty($results)): ?>
                <div class="alert alert-info">
                    <strong>ℹ️ Migration Status:</strong> Review pending migrations below and click "Run Migrations" to execute.
                </div>

                <h2 style="margin-bottom: 20px; color: #1f2937;">Available Migrations</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Migration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($migrations as $migration): ?>
                            <tr>
                                <td><?= htmlspecialchars($migration) ?></td>
                                <td>
                                    <?php if (in_array($migration, $executedMigrations)): ?>
                                        <span class="status-badge status-executed">✓ Executed</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $pendingCount = count(array_diff($migrations, $executedMigrations));
                ?>

                <?php if ($pendingCount > 0): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Pending Migrations:</strong> <?= $pendingCount ?> migration(s) need to be executed.
                    </div>

                    <form method="POST">
                        <div class="actions">
                            <button type="submit" name="run_migrations" value="1" class="btn btn-primary">
                                Run Migrations (<?= $pendingCount ?>)
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong>✓ All Migrations Executed!</strong> Your database is up to date.
                    </div>

                    <div class="actions">
                        <a href="../public/login.php" class="btn btn-primary" style="text-decoration: none;">
                            Go to Application
                        </a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-success">
                    <strong>✓ Migration Execution Complete!</strong> Review results below.
                </div>

                <h2 style="margin-bottom: 20px; color: #1f2937;">Execution Results</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Migration</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['migration']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $result['status'] ?>">
                                        <?php
                                        $icons = [
                                            'success' => '✓',
                                            'error' => '✗',
                                            'skipped' => '⏭'
                                        ];
                                        echo $icons[$result['status']] ?? '';
                                        ?>
                                        <?= ucfirst($result['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($result['message']) ?>
                                    <?php if ($result['status'] === 'error' && strlen($result['message']) > 50): ?>
                                        <div class="error-details"><?= htmlspecialchars($result['message']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $successCount = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
                $errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
                ?>

                <?php if ($errorCount > 0): ?>
                    <div class="alert alert-error">
                        <strong>⚠️ Errors Encountered:</strong> <?= $errorCount ?> migration(s) failed. Please review error messages and fix issues before retrying.
                    </div>
                <?php endif; ?>

                <?php if ($errorCount === 0 && $successCount > 0): ?>
                    <div class="alert alert-success">
                        <strong>✓ Success!</strong> All migrations executed successfully.
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <a href="?reload=1" class="btn btn-primary" style="text-decoration: none;">
                        Check Status Again
                    </a>
                    <a href="../public/login.php" class="btn btn-primary" style="text-decoration: none;">
                        Go to Application
                    </a>
                </div>
            <?php endif; ?>

            <div class="alert alert-warning" style="margin-top: 30px;">
                <strong>🔒 Security Notice:</strong> Delete this file (database/run_migrations.php) after completing migrations, or protect it with authentication.
            </div>
        </div>
    </div>
</body>
</html>
