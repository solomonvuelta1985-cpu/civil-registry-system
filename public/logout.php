<?php
/**
 * Logout Handler
 * Civil Registry Document Management System (CRDMS)
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Log the logout activity before destroying session
if (isLoggedIn()) {
    log_activity($pdo, 'logout', 'User logged out', getUserId());
}

// Logout and destroy session
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
