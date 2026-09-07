<?php
if (php_sapi_name() !== 'cli') { http_response_code(404); exit('Migration scripts are CLI-only.'); }
/**
 * Run Marriage Table Migration
 * Execute this file once to create the certificate_of_marriage table
 */

require_once '../includes/config.php';

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/create_marriage_table.sql');

    // Execute SQL
    $pdo->exec($sql);

    echo "✓ Success! The certificate_of_marriage table has been created successfully.\n";
    echo "You can now use the marriage certificate system.\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
