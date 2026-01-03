<?php
/**
 * Logout Handler
 * Civil Registry Records Management System
 */

require_once '../includes/session_config.php';
require_once '../includes/auth.php';

// Log the logout activity before destroying session
if (isLoggedIn()) {
    logActivity('logout', 'auth', getUserId(), 'User logged out');
}

// Logout and destroy session
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
