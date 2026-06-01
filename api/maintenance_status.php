<?php
/**
 * Maintenance Status Heartbeat
 *
 * Lightweight JSON endpoint polled by client-side JS on every protected page.
 * Returns whether maintenance mode is active and whether the *current viewer*
 * should be kicked out (i.e. logged-in non-admin).
 *
 * IMPORTANT: This endpoint deliberately does NOT include auth.php — including
 * auth.php triggers enforceMaintenanceMode() which would log the user out as
 * a side effect of the poll. We read session state directly instead.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/settings.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$is_on = (bool) get_setting('maintenance_mode', false);

$role = $_SESSION['user_role'] ?? null;
$logged_in = !empty($_SESSION['user_id']);
$should_logout = $is_on && $logged_in && $role !== 'Admin';

echo json_encode([
    'maintenance' => $is_on,
    'logout'      => $should_logout,
    'message'     => $is_on ? (string) get_setting('maintenance_message', '') : '',
]);
