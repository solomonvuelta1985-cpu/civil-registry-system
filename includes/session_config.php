<?php
/**
 * Session Configuration
 * This file should be included before session_start() is called
 */

// Configure session settings before starting the session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
