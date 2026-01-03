<?php
/**
 * Create Certificate of Death Table
 * Run this script once to create the missing table
 * Access: http://localhost/iscan/setup_death_table.php
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html><html><head><title>Create Death Table</title></head><body>";
echo "<h1>Creating certificate_of_death table...</h1>";

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/database/create_death_table.sql');

    if ($sql === false) {
        throw new Exception("Could not read SQL file");
    }

    // Execute the SQL
    $pdo->exec($sql);

    echo "<p style='color: green; font-weight: bold;'>✓ SUCCESS: certificate_of_death table created successfully!</p>";
    echo "<p>You can now access death records pages.</p>";
    echo "<p><a href='public/death_records.php'>Go to Death Records</a></p>";
    echo "<p><a href='admin/dashboard.php'>Go to Dashboard</a></p>";

    // Optional: Delete this file after successful execution
    echo "<hr>";
    echo "<p><strong>IMPORTANT:</strong> For security, consider deleting this setup file after running it.</p>";

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ DATABASE ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
