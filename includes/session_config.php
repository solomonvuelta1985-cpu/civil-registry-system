<?php
/**
 * Session Configuration
 * This file should be included before session_start() is called
 */

// Load configuration if not already loaded
if (!defined('SESSION_TIMEOUT')) {
    require_once __DIR__ . '/config.php';
}

// Load security headers helper
require_once __DIR__ . '/security_headers.php';

// Configure session settings before starting the session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Set secure flag only if on HTTPS (auto-detect)
ini_set('session.cookie_secure', isHTTPS() ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');

// Set session garbage collection
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    // Last request was more than SESSION_TIMEOUT seconds ago
    session_unset();
    session_destroy();
    session_start();

    // Redirect to login if not already there
    if (!isset($_GET['timeout']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: ' . BASE_URL . 'public/login.php?timeout=1');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();

// Session regeneration for security (regenerate every 30 minutes)
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > 1800) {
    // Session started more than 30 minutes ago
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}
