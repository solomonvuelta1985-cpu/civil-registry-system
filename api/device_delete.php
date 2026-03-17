<?php
/**
 * API: Revoke or Reactivate a Device
 * Admin access required.
 *
 * POST params:
 *   csrf_token  (string) CSRF token
 *   device_id   (int)    ID of device to revoke/reactivate
 *   action      (string) 'revoke' or 'reactivate'
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/device_auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (getUserRole() !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// CSRF check (requireCSRFToken exits internally on failure)
requireCSRFToken();

$deviceId = (int)($_POST['device_id'] ?? 0);
$action   = trim($_POST['action'] ?? 'revoke');

if ($deviceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid device ID']);
    exit;
}

$userId = getUserId();

if ($action === 'reactivate') {
    $success = reactivateDevice($deviceId);
    $eventMsg = 'DEVICE_REACTIVATED';
    $label = 'reactivated';
} else {
    $success = revokeDevice($deviceId);
    $eventMsg = 'DEVICE_REVOKED';
    $label = 'revoked';
}

if ($success) {
    logActivity($userId, $eventMsg, 'Device ID ' . $deviceId . ' ' . $label);
    logSecurityEvent($eventMsg, 'MEDIUM', $userId, ['device_id' => $deviceId]);
    echo json_encode(['success' => true, 'message' => 'Device ' . $label . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update device. Device may not exist.']);
}
