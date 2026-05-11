<?php
/**
 * API: Toggle ENABLE_DEVICE_LOCK in .env
 * Admin-only. Used by:
 *   1) "Enable Device Lock" button (after admin confirms safety).
 *   2) "Emergency Disable" button (when admin is in danger of being locked out
 *      or wants to undo activation).
 *
 * The change takes effect on the NEXT HTTP request — env_loader.php reads .env
 * at request start, and config.php defines the constant once per request.
 *
 * POST params:
 *   csrf_token (string)
 *   enable     (string)  '1' to set true, '0' to set false
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

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

$enable = ($_POST['enable'] ?? '') === '1';
$envFile = __DIR__ . '/../.env';

if (!is_readable($envFile)) {
    echo json_encode(['success' => false, 'message' => '.env file not readable on server']);
    exit;
}
if (!is_writable($envFile)) {
    echo json_encode([
        'success' => false,
        'message' => '.env is not writable by the web server. '
                   . 'Edit ' . basename($envFile) . ' manually and set ENABLE_DEVICE_LOCK='
                   . ($enable ? 'true' : 'false') . '.',
    ]);
    exit;
}

$contents = file_get_contents($envFile);
if ($contents === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to read .env']);
    exit;
}

$newValue = $enable ? 'true' : 'false';

// Replace the first non-commented ENABLE_DEVICE_LOCK= line.
$pattern = '/^(\s*)ENABLE_DEVICE_LOCK\s*=.*$/m';
if (preg_match($pattern, $contents)) {
    $updated = preg_replace($pattern, '${1}ENABLE_DEVICE_LOCK=' . $newValue, $contents, 1);
} else {
    // Line missing entirely — append it.
    $updated = rtrim($contents) . "\n\n# Device Lock Security\nENABLE_DEVICE_LOCK=" . $newValue . "\n";
}

// Atomic-ish write: write to a temp file, then rename. This avoids leaving
// .env half-written if the process is killed mid-write.
$tmpFile = $envFile . '.tmp.' . bin2hex(random_bytes(4));
if (file_put_contents($tmpFile, $updated) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write temp .env file']);
    exit;
}
if (!@rename($tmpFile, $envFile)) {
    @unlink($tmpFile);
    echo json_encode(['success' => false, 'message' => 'Failed to replace .env file']);
    exit;
}

$adminId = getUserId();
log_activity($pdo, 'DEVICE_LOCK_TOGGLED',
    'Device Lock ' . ($enable ? 'ENABLED' : 'DISABLED') . ' via admin UI',
    $adminId);
logSecurityEvent('DEVICE_LOCK_TOGGLED', 'HIGH', $adminId, [
    'new_value' => $newValue,
]);

echo json_encode([
    'success' => true,
    'message' => $enable
        ? 'Device Lock ENABLED. New devices will require your approval.'
        : 'Device Lock DISABLED. All devices can now log in (use this only for emergency recovery).',
    'new_value' => $newValue,
]);
