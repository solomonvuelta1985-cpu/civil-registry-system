<?php
/**
 * Public maintenance status and notification feed.
 *
 * This endpoint deliberately avoids auth.php: that guard would log out a
 * non-admin before this heartbeat can report the change to the browser.
 */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/settings.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$isOn = maintenance_is_active();
$notificationPdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
if ($notificationPdo) app_notification_sync_maintenance($notificationPdo);
$role = $_SESSION['user_role'] ?? null;
$loggedIn = !empty($_SESSION['user_id']);
$shouldLogout = $isOn && $loggedIn && !in_array($role, ['Admin', 'Super Admin'], true);

echo json_encode([
    'maintenance' => $isOn,
    'logout' => $shouldLogout,
    'message' => $isOn ? maintenance_get_message() : '',
    'schedule' => maintenance_get_public_schedule(),
    'notifications' => maintenance_get_notification_events(),
    'timezone' => date_default_timezone_get(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
