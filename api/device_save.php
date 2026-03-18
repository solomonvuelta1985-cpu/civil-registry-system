<?php
/**
 * API: Register a Device
 * Saves a browser fingerprint hash as a trusted device.
 * Admin access required.
 *
 * POST params:
 *   csrf_token         (string) CSRF token
 *   device_fingerprint (string) SHA-256 hex hash from device-fingerprint.js
 *   device_name        (string) Human-readable name (e.g. "Front Desk PC")
 *   notes              (string) Optional notes
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/device_auth.php';

header('Content-Type: application/json');

// Must be logged in and admin
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

// Validate inputs
$fingerprint = trim($_POST['device_fingerprint'] ?? '');
$deviceName  = trim($_POST['device_name'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if (empty($fingerprint) || strlen($fingerprint) < 8) {
    echo json_encode(['success' => false, 'message' => 'Invalid device fingerprint']);
    exit;
}
if (empty($deviceName) || strlen($deviceName) > 100) {
    echo json_encode(['success' => false, 'message' => 'Device name is required (max 100 characters)']);
    exit;
}

$userId = getUserId();

// Check if already registered
$existing = checkDeviceRegistered($fingerprint);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'This device is already registered as: ' . htmlspecialchars($existing['device_name'])]);
    exit;
}

// Register the device
$success = registerDevice($fingerprint, $deviceName, $userId, $notes);

if ($success) {
    log_activity($pdo, 'DEVICE_REGISTERED', 'Device "' . $deviceName . '" registered (fp: ' . substr($fingerprint, 0, 16) . '...)', $userId);
    logSecurityEvent('DEVICE_REGISTERED', 'LOW', $userId, [
        'device_name'    => $deviceName,
        'fp_prefix'      => substr($fingerprint, 0, 16),
    ]);
    echo json_encode(['success' => true, 'message' => 'Device "' . htmlspecialchars($deviceName) . '" registered successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to register device. It may already exist.']);
}
