<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if ($argc !== 3 || !preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', (string)($argv[1] ?? '')) || ($argv[2] ?? '') !== '--confirm-mfa-reset') {
    fwrite(STDERR, "Usage: php scripts/reset_super_admin_mfa.php USERNAME --confirm-mfa-reset\n");
    exit(2);
}

require_once __DIR__ . '/../includes/config.php';
$username = (string)$argv[1];

try {
    $pdo->beginTransaction();
    $find = $pdo->prepare("SELECT id FROM users WHERE username=:username AND role='Super Admin' AND status='Active' LIMIT 1 FOR UPDATE");
    $find->execute([':username' => $username]);
    $userId = (int)$find->fetchColumn();
    if ($userId <= 0) throw new RuntimeException('The named account was not found as an active Super Admin.');

    $update = $pdo->prepare('UPDATE users SET require_2fa=1,mfa_secret_enc=NULL,mfa_enabled_at=NULL,mfa_last_totp_step=NULL,mfa_session_version=mfa_session_version+1 WHERE id=:id');
    $update->execute([':id' => $userId]);
    $pdo->prepare('DELETE FROM user_mfa_authenticators WHERE user_id=:id')->execute([':id' => $userId]);
    $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=:id')->execute([':id' => $userId]);

    $event = $pdo->prepare("INSERT INTO security_logs (event_type,severity,user_id,ip_address,user_agent,details,created_at) VALUES ('SUPER_ADMIN_MFA_RESET','HIGH',:user_id,'CLI','CLI',:details,NOW())");
    $event->execute([
        ':user_id' => $userId,
        ':details' => json_encode(['username' => $username, 'action' => 'MFA secret and recovery codes cleared; enrollment required'], JSON_UNESCAPED_SLASHES),
    ]);
    $pdo->commit();
    fwrite(STDOUT, "MFA reset for {$username}. The next sign-in will require authenticator enrollment.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'STOP: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
