<?php
/**
 * API: Check Current Device Registration
 * Returns whether a given fingerprint matches an Active registered device.
 * Used by admin/devices.php to show a pre-flight safety indicator BEFORE
 * the user enables ENABLE_DEVICE_LOCK, so they cannot accidentally lock
 * themselves out.
 *
 * GET params:
 *   fp (string) SHA-256 hex hash from device-fingerprint.js
 *
 * Response:
 *   { registered: bool, device_name: string|null, status: string|null }
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/device_auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['registered' => false, 'error' => 'Not authenticated']);
    exit;
}
if (getUserRole() !== 'Admin') {
    http_response_code(403);
    echo json_encode(['registered' => false, 'error' => 'Admin access required']);
    exit;
}

$fingerprint = trim($_GET['fp'] ?? '');
if (empty($fingerprint) || strlen($fingerprint) < 8) {
    echo json_encode(['registered' => false, 'error' => 'Invalid fingerprint']);
    exit;
}

// Look up ANY status (Active or Revoked) so we can show the right message
global $pdo;
try {
    $stmt = $pdo->prepare(
        "SELECT device_name, status
           FROM registered_devices
          WHERE fingerprint_hash = :hash
          LIMIT 1"
    );
    $stmt->execute([':hash' => $fingerprint]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Device check API error: ' . $e->getMessage());
    echo json_encode(['registered' => false, 'error' => 'Database error']);
    exit;
}

if (!$row) {
    echo json_encode([
        'registered'  => false,
        'device_name' => null,
        'status'      => null,
    ]);
    exit;
}

echo json_encode([
    'registered'  => $row['status'] === 'Active',
    'device_name' => $row['device_name'],
    'status'      => $row['status'],
]);
