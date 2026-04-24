<?php
/**
 * RA 9048/10172 Database Configuration
 * Civil Registry Transactions Module
 *
 * Provides $pdo_ra for the separate RA 9048 database.
 * Also loads the main config.php which provides $pdo for auth/sessions.
 */

// Load main config (gives $pdo, constants, auth helpers, etc.)
require_once __DIR__ . '/config.php';

// RA 9048 database name from .env (default: iscan_ra9048_db)
define('RA9048_DB_NAME', env('RA9048_DB_NAME', 'iscan_ra9048_db'));

// Upload path for RA 9048 documents
define('RA9048_UPLOAD_PATH', UPLOAD_PATH . 'ra9048/');

// RA 9048 database connection using PDO
try {
    $dsn_ra = "mysql:host=" . DB_HOST . ";dbname=" . RA9048_DB_NAME . ";charset=utf8mb4";
    $pdo_ra = new PDO($dsn_ra, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("RA9048 DB connection error: " . $e->getMessage());
    die("RA9048 database connection error. Please contact the administrator.");
}
