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
} elseif ($action === 'delete') {
    // Hard delete — fetch first so we can log a useful audit trail (the
    // device row is gone after this, so we won't be able to look it up).
    try {
        $stmt = $pdo->prepare(
            "SELECT device_name, fingerprint_hash, status
               FROM registered_devices WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $deviceId]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        $row = null;
    }
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Device not found']);
        exit;
    }

    try {
        $del = $pdo->prepare("DELETE FROM registered_devices WHERE id = :id");
        $del->execute([':id' => $deviceId]);
        $success = $del->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Device delete error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during delete']);
        exit;
    }

    if ($success) {
        log_activity($pdo, 'DEVICE_DELETED',
            'Permanently deleted device "' . $row['device_name'] . '" (fp: '
                . substr($row['fingerprint_hash'], 0, 16) . '..., was: ' . $row['status'] . ')',
            $userId);
        logSecurityEvent('DEVICE_DELETED', 'HIGH', [
            'device_id'    => $deviceId,
            'device_name'  => $row['device_name'],
            'previous_status' => $row['status'],
            'fp_prefix'    => substr($row['fingerprint_hash'], 0, 16),
        ], $userId);
        echo json_encode(['success' => true, 'message' => 'Device permanently deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete device']);
    }
    exit;
} else {
    $success = revokeDevice($deviceId);
    $eventMsg = 'DEVICE_REVOKED';
    $label = 'revoked';
}

if ($success) {
    log_activity($pdo, $eventMsg, 'Device ID ' . $deviceId . ' ' . $label, $userId);
    logSecurityEvent($eventMsg, 'MEDIUM', ['device_id' => $deviceId], $userId);
    echo json_encode(['success' => true, 'message' => 'Device ' . $label . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update device. Device may not exist.']);
}
