<?php
/**
 * API: Approve or Reject a Pending Device
 * Admin-only. Called from admin/devices.php for each row in the
 * "Pending Approval" section.
 *
 * POST params:
 *   csrf_token (string)  CSRF token
 *   device_id  (int)     Row id in registered_devices
 *   action     (string)  'approve' | 'reject'
 *   new_name   (string)  Optional — admin can rename on approve
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

requireCSRFToken();

$deviceId = (int) ($_POST['device_id'] ?? 0);
$action   = trim($_POST['action'] ?? '');
$newName  = trim($_POST['new_name'] ?? '');

if ($deviceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid device id']);
    exit;
}
if (!in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$adminId = getUserId();

// Fetch the row so we can sanity-check it's actually Pending and report a
// useful audit-log entry.
global $pdo;
try {
    $stmt = $pdo->prepare(
        "SELECT id, device_name, status, requested_by, fingerprint_hash
           FROM registered_devices WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $deviceId]);
    $device = $stmt->fetch();
} catch (PDOException $e) {
    error_log('device_approve fetch error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

if (!$device) {
    echo json_encode(['success' => false, 'message' => 'Device not found']);
    exit;
}
if ($device['status'] !== 'Pending') {
    echo json_encode([
        'success' => false,
        'message' => 'This device is no longer pending (current status: ' . $device['status'] . ')',
    ]);
    exit;
}

if ($action === 'approve') {
    // Optional rename
    if ($newName !== '' && strlen($newName) <= 100) {
        try {
            $rn = $pdo->prepare(
                "UPDATE registered_devices SET device_name = :n WHERE id = :id"
            );
            $rn->execute([':n' => $newName, ':id' => $deviceId]);
        } catch (PDOException $e) {
            error_log('device_approve rename error: ' . $e->getMessage());
        }
    }

    if (approveDevice($deviceId, $adminId)) {
        log_activity($pdo, 'DEVICE_APPROVED',
            'Approved device #' . $deviceId . ' (' . substr($device['fingerprint_hash'], 0, 16) . '...)',
            $adminId);
        logSecurityEvent('DEVICE_APPROVED', 'LOW', $adminId, [
            'device_id'    => $deviceId,
            'requested_by' => $device['requested_by'],
            'fp_prefix'    => substr($device['fingerprint_hash'], 0, 16),
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Device approved. The user can now log in.',
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Failed to approve device']);
    exit;
}

// Reject
if (rejectDevice($deviceId, $adminId)) {
    log_activity($pdo, 'DEVICE_REJECTED',
        'Rejected device #' . $deviceId . ' (' . substr($device['fingerprint_hash'], 0, 16) . '...)',
        $adminId);
    logSecurityEvent('DEVICE_REJECTED', 'MEDIUM', $adminId, [
        'device_id'    => $deviceId,
        'requested_by' => $device['requested_by'],
        'fp_prefix'    => substr($device['fingerprint_hash'], 0, 16),
    ]);
    echo json_encode([
        'success' => true,
        'message' => 'Device rejected. The user has been notified.',
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Failed to reject device']);
