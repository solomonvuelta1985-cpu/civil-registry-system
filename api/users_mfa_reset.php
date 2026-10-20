<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mfa.php';

if (!isLoggedIn() || !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Super Admin access required.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
requireCSRFToken();

$actorId = (int)getUserId();
$targetId = filter_var($_POST['target_user_id'] ?? null, FILTER_VALIDATE_INT);
$password = (string)($_POST['password'] ?? '');
$code = trim((string)($_POST['code'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));
if (!$targetId || $password === '' || !preg_match('/^\d{6}$/', $code)
    || mfa_text_length($reason) < 10 || mfa_text_length($reason) > 500
    || (string)($_POST['confirm_reset'] ?? '') !== '1') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Confirm the reset and provide the target account, password, six-digit code, and a reason of 10 to 500 characters.']);
    exit;
}

$limitKey = 'mfa_peer_reset_' . $actorId . '_' . (function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$limit = checkRateLimit($limitKey, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
if (empty($limit['allowed'])) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => $limit['message'] ?? 'Too many attempts. Try again later.']);
    exit;
}

try {
    $targetUsername = mfa_peer_reset($pdo, $actorId, (int)$targetId, $password, $code, $reason);
    clearRateLimit($limitKey);
    log_activity($pdo, 'SUPER_ADMIN_MFA_RESET', "Reset MFA for Super Admin {$targetUsername}", $actorId);
    echo json_encode(['success' => true, 'message' => "MFA reset for {$targetUsername}. They must enroll again at their next sign-in."]);
} catch (PDOException $error) {
    error_log('Super Admin peer MFA reset database failure: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MFA reset could not be completed.']);
} catch (DomainException $error) {
    logSecurityEvent('SUPER_ADMIN_MFA_RESET_FAILED', 'MEDIUM', 'Peer MFA reset rejected because actor proof was invalid.', $actorId);
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Super Admin peer MFA reset failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'MFA reset could not be completed.']);
}
