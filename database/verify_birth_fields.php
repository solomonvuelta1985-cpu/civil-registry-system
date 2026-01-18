<?php
/**
 * Verification Script for Birth Certificate Fields Update
 * Checks if all components are properly configured
 */

require_once '../includes/config.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Birth Certificate Fields Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 10px; }
        h2 { color: #3b82f6; margin-top: 30px; }
        .check { color: #198754; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .info { background: #e7f1ff; padding: 10px; border-left: 3px solid #0d6efd; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Birth Certificate Fields Verification</h1>
        <p>This script verifies that the Sex and Legitimacy Status fields have been properly added to the birth certificate system.</p>";

// 1. Check Database Structure
echo "<h2>1. Database Structure</h2>";

try {
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'iscan_db'
            AND TABLE_NAME = 'certificate_of_live_birth'
            AND COLUMN_NAME IN ('child_sex', 'legitimacy_status')
        ORDER BY ORDINAL_POSITION
    ");

    $columns = $stmt->fetchAll();

    if (count($columns) === 2) {
        echo "<p class='check'>✅ Both columns exist in database</p>";
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Nullable</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><code>{$col['COLUMN_NAME']}</code></td>";
            echo "<td><code>{$col['COLUMN_TYPE']}</code></td>";
            echo "<td>{$col['IS_NULLABLE']}</td>";
            echo "<td>" . ($col['COLUMN_DEFAULT'] ?: 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='fail'>❌ Missing columns in database!</p>";
    }
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 2. Check Files Exist
echo "<h2>2. File Verification</h2>";

$files = [
    'Form Page' => '../public/certificate_of_live_birth.php',
    'Save API' => '../api/certificate_of_live_birth_save.php',
    'Update API' => '../api/certificate_of_live_birth_update.php',
    'OCR Mapper' => '../assets/js/ocr-field-mapper.js',
    'Migration SQL' => 'add_birth_fields_migration.sql',
];

echo "<table>";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th></tr>";
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $status = $exists ? "<span class='check'>✅ Exists</span>" : "<span class='fail'>❌ Missing</span>";
    echo "<tr><td>{$name}</td><td><code>{$path}</code></td><td>{$status}</td></tr>";
}
echo "</table>";

// 3. Check Code Integration
echo "<h2>3. Code Integration Check</h2>";

$codeChecks = [
    'Form has child_sex field' => [
        'file' => '../public/certificate_of_live_birth.php',
        'pattern' => 'id="child_sex"'
    ],
    'Form has legitimacy_status field' => [
        'file' => '../public/certificate_of_live_birth.php',
        'pattern' => 'id="legitimacy_status"'
    ],
    'Save API captures child_sex' => [
        'file' => '../api/certificate_of_live_birth_save.php',
        'pattern' => "child_sex"
    ],
    'Save API captures legitimacy_status' => [
        'file' => '../api/certificate_of_live_birth_save.php',
        'pattern' => "legitimacy_status"
    ],
    'Update API handles child_sex' => [
        'file' => '../api/certificate_of_live_birth_update.php',
        'pattern' => "child_sex"
    ],
    'Update API handles legitimacy_status' => [
        'file' => '../api/certificate_of_live_birth_update.php',
        'pattern' => "legitimacy_status"
    ],
    'OCR mapper includes legitimacy_status' => [
        'file' => '../assets/js/ocr-field-mapper.js',
        'pattern' => "'legitimacy_status'"
    ],
];

echo "<table>";
echo "<tr><th>Check</th><th>File</th><th>Status</th></tr>";
foreach ($codeChecks as $check => $config) {
    if (file_exists($config['file'])) {
        $content = file_get_contents($config['file']);
        $found = strpos($content, $config['pattern']) !== false;
        $status = $found ? "<span class='check'>✅ Found</span>" : "<span class='fail'>❌ Not Found</span>";
    } else {
        $status = "<span class='fail'>❌ File Missing</span>";
    }
    echo "<tr><td>{$check}</td><td><code>" . basename($config['file']) . "</code></td><td>{$status}</td></tr>";
}
echo "</table>";

// 4. Sample Data Check
echo "<h2>4. Sample Data Query</h2>";

try {
    $stmt = $pdo->query("
        SELECT
            id,
            CONCAT(child_first_name, ' ', child_last_name) as child_name,
            child_sex,
            legitimacy_status,
            date_of_registration
        FROM certificate_of_live_birth
        WHERE status = 'Active'
        LIMIT 5
    ");

    $records = $stmt->fetchAll();

    if (count($records) > 0) {
        echo "<p class='check'>✅ Successfully queried {count($records)} sample records</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Child Name</th><th>Sex</th><th>Status</th><th>Date</th></tr>";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>{$record['id']}</td>";
            echo "<td>" . htmlspecialchars($record['child_name']) . "</td>";
            echo "<td>" . ($record['child_sex'] ?: '<em>NULL</em>') . "</td>";
            echo "<td>" . ($record['legitimacy_status'] ?: '<em>NULL</em>') . "</td>";
            echo "<td>{$record['date_of_registration']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='info'><strong>Note:</strong> NULL values are expected for existing records. New records will require these fields.</p>";
    } else {
        echo "<p class='info'>ℹ️ No records found in database</p>";
    }
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Query error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 5. Summary
echo "<h2>5. Summary</h2>";
echo "<div class='info'>";
echo "<p><strong>✅ Verification Complete!</strong></p>";
echo "<p>All components have been checked. If all checks show ✅, the implementation is complete and ready for testing.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Test creating a new birth certificate with the new fields</li>";
echo "<li>Test editing an existing birth certificate</li>";
echo "<li>Test OCR detection of these fields from PDF documents</li>";
echo "<li>Verify validation works (required fields cannot be empty)</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px; color: #6c757d; font-size: 12px;'>
    Generated on " . date('F d, Y h:i:s A') . "
</p>";

echo "</div></body></html>";
?>
