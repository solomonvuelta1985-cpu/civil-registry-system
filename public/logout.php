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

// Clear browser-side certificate drafts as part of the logout boundary.  The
// drafts can contain civil-registry data and must not survive into another
// account on a shared workstation.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Signing out</title></head>
<body>
<p>Signing out…</p>
<script>
(() => {
    try {
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && (key.startsWith('crdms_draft_') || key.startsWith('cert_form_autosave_'))) {
                localStorage.removeItem(key);
            }
        }
    } catch (_) {}
    window.location.replace('login.php');
})();
</script>
<noscript><a href="login.php">Continue to login</a></noscript>
</body>
</html>
