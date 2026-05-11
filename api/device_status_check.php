<?php
/**
 * API: Device Pending Status Check
 * Called every 15 seconds by public/device_pending.php to find out whether
 * the admin has Approved (status='Active'), Rejected (status='Revoked'), or
 * not yet acted (status='Pending') on the user's request.
 *
 * SECURITY: This endpoint is reachable WITHOUT being logged in (the waiting
 * page sits between credential auth and session establishment). To prevent
 * arbitrary enumeration of the registered_devices table, we only return the
 * status for the device id that was stored in $_SESSION when the user hit
 * the waiting page. The id in the query string MUST match the session value.
 *
 * GET params:
 *   id (int) The pending device row id
 *
 * Response:
 *   { status: 'Active'|'Pending'|'Revoked'|'Unknown' }
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/device_auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$requestedId = (int) ($_GET['id'] ?? 0);
$sessionId   = (int) ($_SESSION['pending_device_id'] ?? 0);

// The user must have come from the login flow (which sets the session id).
// Mismatch = either a stale session or an enumeration attempt. Return Unknown.
if ($requestedId <= 0 || $sessionId <= 0 || $requestedId !== $sessionId) {
    echo json_encode(['status' => 'Unknown']);
    exit;
}

global $pdo;
try {
    $stmt = $pdo->prepare(
        "SELECT status FROM registered_devices WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $requestedId]);
    $status = (string) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('device_status_check API error: ' . $e->getMessage());
    echo json_encode(['status' => 'Unknown']);
    exit;
}

if (!$status) {
    echo json_encode(['status' => 'Unknown']);
    exit;
}

// If the device was approved or rejected, clear the pending markers from the
// session so the user goes back through the normal login flow next.
if ($status === 'Active' || $status === 'Revoked') {
    unset($_SESSION['pending_device_id'], $_SESSION['pending_device_fp']);
}

echo json_encode(['status' => $status]);
