<?php
/**
 * Verification Script for Marriage Certificate Nature of Solemnization Field
 * Checks if all components are properly configured
 */

require_once '../includes/config.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Marriage Certificate Nature Field Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #ec4899; border-bottom: 2px solid #ec4899; padding-bottom: 10px; }
        h2 { color: #ec4899; margin-top: 30px; }
        .check { color: #198754; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .info { background: #fff3cd; padding: 10px; border-left: 3px solid #ffc107; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>💍 Marriage Certificate - Nature of Solemnization Field Verification</h1>
        <p>This script verifies that the Nature of Solemnization field has been properly added to the marriage certificate system.</p>";

// 1. Check Database Structure
echo "<h2>1. Database Structure</h2>";

try {
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'iscan_db'
            AND TABLE_NAME = 'certificate_of_marriage'
            AND COLUMN_NAME = 'nature_of_solemnization'
    ");

    $column = $stmt->fetch();

    if ($column) {
        echo "<p class='check'>✅ Column exists in database</p>";
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Nullable</th><th>Default</th></tr>";
        echo "<tr>";
        echo "<td><code>{$column['COLUMN_NAME']}</code></td>";
        echo "<td><code>{$column['COLUMN_TYPE']}</code></td>";
        echo "<td>{$column['IS_NULLABLE']}</td>";
        echo "<td>" . ($column['COLUMN_DEFAULT'] ?: 'NULL') . "</td>";
        echo "</tr>";
        echo "</table>";

        // Verify ENUM values
        if (strpos($column['COLUMN_TYPE'], 'Church') !== false &&
            strpos($column['COLUMN_TYPE'], 'Civil') !== false &&
            strpos($column['COLUMN_TYPE'], 'Other Religious Sect') !== false) {
            echo "<p class='check'>✅ All expected ENUM values present (Church, Civil, Other Religious Sect)</p>";
        } else {
            echo "<p class='fail'>❌ ENUM values incorrect or missing</p>";
        }
    } else {
        echo "<p class='fail'>❌ Column not found in database!</p>";
    }
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 2. Check Column Position
echo "<h2>2. Column Position</h2>";

try {
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, ORDINAL_POSITION
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'iscan_db'
            AND TABLE_NAME = 'certificate_of_marriage'
            AND COLUMN_NAME IN ('place_of_marriage', 'nature_of_solemnization', 'pdf_filename')
        ORDER BY ORDINAL_POSITION
    ");

    $columns = $stmt->fetchAll();

    echo "<table>";
    echo "<tr><th>Position</th><th>Column Name</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['ORDINAL_POSITION']}</td><td><code>{$col['COLUMN_NAME']}</code></td></tr>";
    }
    echo "</table>";

    // Check if nature_of_solemnization is between place_of_marriage and pdf_filename
    $positions = array_column($columns, 'ORDINAL_POSITION', 'COLUMN_NAME');
    if (isset($positions['place_of_marriage']) &&
        isset($positions['nature_of_solemnization']) &&
        isset($positions['pdf_filename'])) {

        if ($positions['nature_of_solemnization'] > $positions['place_of_marriage'] &&
            $positions['nature_of_solemnization'] < $positions['pdf_filename']) {
            echo "<p class='check'>✅ Column is positioned correctly (after place_of_marriage)</p>";
        } else {
            echo "<p class='fail'>❌ Column position is incorrect</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Check Files Exist
echo "<h2>3. File Verification</h2>";

$files = [
    'Form Page' => '../public/certificate_of_marriage.php',
    'Save API' => '../api/certificate_of_marriage_save.php',
    'Update API' => '../api/certificate_of_marriage_update.php',
    'Migration SQL' => 'add_marriage_nature_field_migration.sql',
];

echo "<table>";
echo "<tr><th>Component</th><th>File Path</th><th>Status</th></tr>";
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $status = $exists ? "<span class='check'>✅ Exists</span>" : "<span class='fail'>❌ Missing</span>";
    echo "<tr><td>{$name}</td><td><code>{$path}</code></td><td>{$status}</td></tr>";
}
echo "</table>";

// 4. Check Code Integration
echo "<h2>4. Code Integration Check</h2>";

$codeChecks = [
    'Form has nature_of_solemnization field' => [
        'file' => '../public/certificate_of_marriage.php',
        'pattern' => 'id="nature_of_solemnization"'
    ],
    'Form has dropdown options' => [
        'file' => '../public/certificate_of_marriage.php',
        'pattern' => "['Church', 'Civil', 'Other Religious Sect']"
    ],
    'Save API captures nature_of_solemnization' => [
        'file' => '../api/certificate_of_marriage_save.php',
        'pattern' => '$nature_of_solemnization'
    ],
    'Save API validates field' => [
        'file' => '../api/certificate_of_marriage_save.php',
        'pattern' => 'empty($nature_of_solemnization)'
    ],
    'Save API includes in SQL INSERT' => [
        'file' => '../api/certificate_of_marriage_save.php',
        'pattern' => 'nature_of_solemnization,'
    ],
    'Update API handles nature_of_solemnization' => [
        'file' => '../api/certificate_of_marriage_update.php',
        'pattern' => '$nature_of_solemnization'
    ],
    'Update API validates field' => [
        'file' => '../api/certificate_of_marriage_update.php',
        'pattern' => 'empty($nature_of_solemnization)'
    ],
    'Update API includes in SQL UPDATE' => [
        'file' => '../api/certificate_of_marriage_update.php',
        'pattern' => 'nature_of_solemnization = :nature_of_solemnization'
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

// 5. Sample Data Check
echo "<h2>5. Sample Data Query</h2>";

try {
    $stmt = $pdo->query("
        SELECT
            id,
            CONCAT(husband_first_name, ' ', husband_last_name, ' & ', wife_first_name, ' ', wife_last_name) as couple,
            nature_of_solemnization,
            date_of_marriage,
            place_of_marriage
        FROM certificate_of_marriage
        WHERE status = 'Active'
        ORDER BY id DESC
        LIMIT 5
    ");

    $records = $stmt->fetchAll();

    if (count($records) > 0) {
        echo "<p class='check'>✅ Successfully queried " . count($records) . " sample records</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Couple</th><th>Nature</th><th>Date</th><th>Place</th></tr>";
        foreach ($records as $record) {
            $nature_display = $record['nature_of_solemnization'] ?: '<em style="color: #999;">NULL</em>';
            echo "<tr>";
            echo "<td>{$record['id']}</td>";
            echo "<td>" . htmlspecialchars($record['couple']) . "</td>";
            echo "<td>{$nature_display}</td>";
            echo "<td>{$record['date_of_marriage']}</td>";
            echo "<td>" . htmlspecialchars($record['place_of_marriage']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='info'><strong>Note:</strong> NULL values are expected for existing records. New records will require this field.</p>";
    } else {
        echo "<p class='info'>ℹ️ No records found in database</p>";
    }
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Query error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 6. Test Insert Query (Dry Run)
echo "<h2>6. Test Insert Query (Dry Run)</h2>";

try {
    // Test if the SQL syntax is correct
    $test_sql = "INSERT INTO certificate_of_marriage (
        registry_no, date_of_registration,
        husband_first_name, husband_last_name,
        husband_date_of_birth, husband_place_of_birth, husband_residence,
        wife_first_name, wife_last_name,
        wife_date_of_birth, wife_place_of_birth, wife_residence,
        date_of_marriage, place_of_marriage, nature_of_solemnization,
        status
    ) VALUES (
        'TEST-123', '2026-01-18',
        'John', 'Doe',
        '1990-01-01', 'Manila', 'Manila',
        'Jane', 'Smith',
        '1992-01-01', 'Manila', 'Manila',
        '2025-12-25', 'Manila Cathedral', 'Church',
        'Active'
    )";

    $stmt = $pdo->prepare($test_sql);
    echo "<p class='check'>✅ Insert query syntax is valid</p>";
    echo "<p class='info'>SQL query prepared successfully (not executed)</p>";
} catch (PDOException $e) {
    echo "<p class='fail'>❌ Insert query syntax error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 7. Summary
echo "<h2>7. Summary</h2>";
echo "<div class='info'>";
echo "<p><strong>✅ Verification Complete!</strong></p>";
echo "<p>All components have been checked. If all checks show ✅, the implementation is complete and ready for testing.</p>";
echo "<p><strong>ENUM Options Available:</strong></p>";
echo "<ul>";
echo "<li><strong>Church</strong> - Religious ceremony in a church</li>";
echo "<li><strong>Civil</strong> - Civil ceremony by government official</li>";
echo "<li><strong>Other Religious Sect</strong> - Other religious ceremonies</li>";
echo "</ul>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Test creating a new marriage certificate with the nature of solemnization field</li>";
echo "<li>Test editing an existing marriage certificate</li>";
echo "<li>Verify validation works (field cannot be empty)</li>";
echo "<li>Test all three dropdown options</li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px; color: #6c757d; font-size: 12px;'>
    Generated on " . date('F d, Y h:i:s A') . "
</p>";

echo "</div></body></html>";
?>
