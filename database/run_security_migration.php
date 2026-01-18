<?php
/**
 * Security Tables Migration Runner
 * Run this file once to create security tables
 */

// Load configuration
require_once __DIR__ . '/../includes/config.php';

echo "===========================================\n";
echo "Security Tables Migration\n";
echo "===========================================\n\n";

try {
    // Read SQL file
    $sql_file = __DIR__ . '/security_tables_migration.sql';

    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: {$sql_file}");
    }

    $sql = file_get_contents($sql_file);

    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }

    echo "Reading migration file... ✓\n";

    // Split by semicolons to get individual queries
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strpos($stmt, '--') !== 0;
        }
    );

    echo "Found " . count($statements) . " SQL statements\n\n";

    // Execute each statement
    $success_count = 0;
    $skip_count = 0;

    foreach ($statements as $index => $statement) {
        try {
            // Extract table/action info for better output
            preg_match('/CREATE TABLE IF NOT EXISTS `?(\w+)`?|ALTER TABLE `?(\w+)`?/', $statement, $matches);
            $table_name = $matches[1] ?? $matches[2] ?? "statement " . ($index + 1);

            echo "Executing: {$table_name}... ";

            $pdo->exec($statement);
            echo "✓\n";
            $success_count++;

        } catch (PDOException $e) {
            // Check if it's just a "column already exists" error
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⊘ (already exists)\n";
                $skip_count++;
            } else {
                echo "✗\n";
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n===========================================\n";
    echo "Migration Summary\n";
    echo "===========================================\n";
    echo "Successful: {$success_count}\n";
    echo "Skipped: {$skip_count}\n";
    echo "\n";

    // Verify tables exist
    echo "Verifying tables...\n";
    $tables = ['rate_limits', 'security_logs'];

    foreach ($tables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if ($check) {
            echo "  ✓ {$table}\n";
        } else {
            echo "  ✗ {$table} NOT FOUND!\n";
        }
    }

    echo "\n===========================================\n";
    echo "Migration completed successfully!\n";
    echo "===========================================\n\n";

    echo "Next steps:\n";
    echo "1. Test your application\n";
    echo "2. Verify CSRF protection is working\n";
    echo "3. Test rate limiting (try 6 failed logins)\n";
    echo "4. Check security_logs table for events\n";
    echo "\nFor production deployment, see DEPLOYMENT_GUIDE.md\n\n";

} catch (Exception $e) {
    echo "\n===========================================\n";
    echo "Migration FAILED!\n";
    echo "===========================================\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
