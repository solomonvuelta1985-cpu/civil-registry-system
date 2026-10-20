<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if ($argc !== 3 || !preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', (string)($argv[1] ?? '')) || ($argv[2] ?? '') !== '--confirm-bootstrap') {
    fwrite(STDERR, "Usage: php scripts/bootstrap_super_admin.php USERNAME --confirm-bootstrap\n");
    exit(2);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mfa.php';

$username = (string)$argv[1];
try {
    mfa_encryption_key();
} catch (Throwable $error) {
    fwrite(STDERR, 'STOP: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

try {
    $pdo->beginTransaction();
    $existing = $pdo->query("SELECT id FROM users WHERE role='Super Admin' LIMIT 1 FOR UPDATE")->fetchColumn();
    if ($existing !== false) throw new RuntimeException('A Super Admin already exists. Bootstrap can only be used once.');

    $find = $pdo->prepare("SELECT id,username,full_name FROM users WHERE username=:username AND role='Admin' AND status='Active' LIMIT 1 FOR UPDATE");
    $find->execute([':username' => $username]);
    $admin = $find->fetch(PDO::FETCH_ASSOC);
    if (!$admin) throw new RuntimeException('The named account was not found as an active Admin. No account was changed.');

    $update = $pdo->prepare("UPDATE users SET role='Super Admin',require_2fa=1,mfa_secret_enc=NULL,mfa_enabled_at=NULL,mfa_last_totp_step=NULL,mfa_session_version=mfa_session_version+1 WHERE id=:id AND role='Admin' AND status='Active'");
    $update->execute([':id' => (int)$admin['id']]);
    if ($update->rowCount() !== 1) throw new RuntimeException('The named account changed during bootstrap.');
    $pdo->prepare('DELETE FROM user_mfa_authenticators WHERE user_id=:id')->execute([':id' => (int)$admin['id']]);
    $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=:id')->execute([':id' => (int)$admin['id']]);

    $event = $pdo->prepare("INSERT INTO security_logs (event_type,severity,user_id,ip_address,user_agent,details,created_at) VALUES ('SUPER_ADMIN_BOOTSTRAP','CRITICAL',:user_id,'CLI','CLI',:details,NOW())");
    $event->execute([
        ':user_id' => (int)$admin['id'],
        ':details' => json_encode(['username' => $username, 'action' => 'promoted active Admin; MFA enrollment required'], JSON_UNESCAPED_SLASHES),
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Super Admin bootstrap completed for {$username}. MFA enrollment is required on the next sign-in.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'STOP: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
