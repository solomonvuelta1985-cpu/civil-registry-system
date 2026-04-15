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
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Set cookie params atomically before session_start so Secure/SameSite/HttpOnly
// are applied consistently. SameSite=Lax (not Strict) is required behind
// Cloudflare Tunnel / reverse proxies so the cookie survives top-level
// redirects. Secure flag is auto-enabled when isHTTPS() (honors X-Forwarded-Proto).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isHTTPS(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Session timeout check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    // Last request was more than SESSION_TIMEOUT seconds ago
    session_unset();
    session_destroy();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isHTTPS(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Redirect to login if not already there
    if (!isset($_GET['timeout']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: ' . BASE_URL . 'public/login.php?timeout=1');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();

// Session regeneration happens at privilege boundaries only (login/logout),
// not on a timer. Timer-based regenerate_id behind a tunnel can reissue the
// cookie without the Secure flag if a single request misses X-Forwarded-Proto,
// causing silent session loss on the next navigation.
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
}
