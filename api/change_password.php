<?php
/**
 * Change the signed-in user's password.
 * This endpoint intentionally accepts no user ID, so it cannot change another account.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

function changePasswordResponse($status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    changePasswordResponse(405, ['success' => false, 'message' => 'Use the password form to submit this request.']);
}

if (!isLoggedIn()) {
    changePasswordResponse(401, ['success' => false, 'message' => 'Your session has expired. Sign in again and retry.']);
}

$userId = (int)getUserId();
$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verifyCSRFToken($csrfToken)) {
    logSecurityEvent('CSRF_VALIDATION_FAILED', 'MEDIUM', 'CSRF token validation failed during password change', $userId);
    changePasswordResponse(403, ['success' => false, 'message' => 'Security check failed. Refresh the page and try again.']);
}

$rateLimitIdentifier = 'password_change_' . $userId . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (ENABLE_RATE_LIMITING) {
    $rateLimit = checkRateLimit($rateLimitIdentifier, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
    if (empty($rateLimit['allowed'])) {
        logSecurityEvent('RATE_LIMIT_EXCEEDED', 'MEDIUM', 'Password change rate limit exceeded', $userId);
        changePasswordResponse(429, [
            'success' => false,
            'message' => $rateLimit['message'] ?? 'Too many attempts. Please try again later.'
        ]);
    }
}

$currentPassword = $_POST['current_password'] ?? null;
$newPassword = $_POST['new_password'] ?? null;
$confirmPassword = $_POST['confirm_password'] ?? null;
if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)
    || $currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    changePasswordResponse(400, ['success' => false, 'message' => 'Complete all password fields.']);
}

if (!hash_equals($newPassword, $confirmPassword)) {
    changePasswordResponse(400, ['success' => false, 'message' => 'The new password and confirmation do not match.']);
}

if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
    changePasswordResponse(400, [
        'success' => false,
        'message' => 'Your new password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.'
    ]);
}

if (REQUIRE_PASSWORD_COMPLEXITY && checkPasswordStrength($newPassword)['score'] < 5) {
    changePasswordResponse(400, [
        'success' => false,
        'message' => 'Your new password does not meet the configured security requirements.'
    ]);
}

try {
    $userStmt = $pdo->prepare("SELECT id, username, password FROM users WHERE id = :id AND status = 'Active' LIMIT 1");
    $userStmt->execute([':id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        changePasswordResponse(401, ['success' => false, 'message' => 'Your account is unavailable. Sign in again or contact an administrator.']);
    }

    if (!password_verify($currentPassword, $user['password'])) {
        logSecurityEvent('PASSWORD_CHANGE_FAILED', 'MEDIUM', [
            'reason' => 'invalid_current_password',
            'username' => $user['username']
        ], $userId);
        changePasswordResponse(400, ['success' => false, 'message' => 'Your current password is incorrect.']);
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET password = :new_password WHERE id = :id AND password = :current_hash');
    $updateStmt->execute([
        ':new_password' => $newPasswordHash,
        ':id' => $userId,
        ':current_hash' => $user['password']
    ]);

    if ($updateStmt->rowCount() !== 1) {
        logSecurityEvent('PASSWORD_CHANGE_FAILED', 'MEDIUM', 'Password changed concurrently during self-service update', $userId);
        changePasswordResponse(409, ['success' => false, 'message' => 'Your password changed while this form was open. Refresh the page and sign in again.']);
    }

    // Keep this session valid; other sessions retain the old fingerprint and will be rejected.
    $_SESSION['password_fingerprint'] = hash('sha256', $newPasswordHash);
    $_SESSION['LAST_ACTIVITY'] = time();
    clearRateLimit($rateLimitIdentifier);

    log_activity($pdo, 'password_change', 'User changed their own password', $userId);
    logSecurityEvent('PASSWORD_CHANGED', 'LOW', 'User changed their own password', $userId);

    changePasswordResponse(200, [
        'success' => true,
        'message' => 'Password updated. You are still signed in on this browser.'
    ]);
} catch (Throwable $e) {
    error_log('Self-service password change failed: ' . $e->getMessage());
    logSecurityEvent('PASSWORD_CHANGE_FAILED', 'HIGH', 'Password change failed because of a server error', $userId);
    changePasswordResponse(500, ['success' => false, 'message' => 'We could not update your password. Please try again.']);
}
