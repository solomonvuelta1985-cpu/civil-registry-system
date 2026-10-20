<?php
/** Mandatory TOTP and recovery-code helpers for Super Admin accounts. */

const MFA_MAX_AUTHENTICATORS = 5;

function mfa_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function mfa_encryption_key(): string
{
    $decoded = base64_decode((string)MFA_ENCRYPTION_KEY, true);
    if (!is_string($decoded) || strlen($decoded) !== 32) {
        throw new RuntimeException('MFA_ENCRYPTION_KEY must be base64 for exactly 32 random bytes.');
    }
    return $decoded;
}

function mfa_base32_encode(string $binary): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($binary) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function mfa_base32_decode(string $encoded): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = strtoupper(rtrim(preg_replace('/[^A-Z2-7]/i', '', $encoded), '='));
    $bits = '';
    foreach (str_split($encoded) as $char) {
        $position = strpos($alphabet, $char);
        if ($position === false) throw new InvalidArgumentException('Invalid Base32 authenticator secret.');
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $binary .= chr(bindec($chunk));
    return $binary;
}

function mfa_generate_secret(): string
{
    return mfa_base32_encode(random_bytes(20));
}

function mfa_encrypt_secret(string $secret): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', mfa_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($ciphertext) || strlen($tag) !== 16) throw new RuntimeException('Unable to encrypt the authenticator secret.');
    return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
}

function mfa_decrypt_secret(string $encrypted): string
{
    $parts = explode(':', $encrypted);
    if (count($parts) !== 4 || $parts[0] !== 'v1') throw new RuntimeException('Stored authenticator secret has an unsupported format.');
    $iv = base64_decode($parts[1], true);
    $tag = base64_decode($parts[2], true);
    $ciphertext = base64_decode($parts[3], true);
    if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($ciphertext)) {
        throw new RuntimeException('Stored authenticator secret is invalid.');
    }
    $secret = openssl_decrypt($ciphertext, 'aes-256-gcm', mfa_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($secret)) throw new RuntimeException('Unable to decrypt the authenticator secret. Check MFA_ENCRYPTION_KEY.');
    return $secret;
}

function mfa_totp_counter(string $secret, string $code, ?int $now = null, int $window = 1): ?int
{
    if (!preg_match('/^\d{6}$/', $code)) return null;
    $key = mfa_base32_decode($secret);
    $counter = (int)floor(($now ?? time()) / 30);
    for ($offset = -max(0, $window); $offset <= max(0, $window); $offset++) {
        $candidateCounter = $counter + $offset;
        if ($candidateCounter < 0) continue;
        $counterBytes = pack('N2', ($candidateCounter >> 32) & 0xffffffff, $candidateCounter & 0xffffffff);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offsetByte = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offsetByte]) & 0x7f) << 24)
            | ((ord($hash[$offsetByte + 1]) & 0xff) << 16)
            | ((ord($hash[$offsetByte + 2]) & 0xff) << 8)
            | (ord($hash[$offsetByte + 3]) & 0xff);
        if (hash_equals(str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT), $code)) return $candidateCounter;
    }
    return null;
}

function mfa_generate_recovery_codes(int $count = 10): array
{
    $codes = [];
    for ($i = 0; $i < max(1, min(20, $count)); $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $codes;
}

function mfa_normalize_recovery_code(string $code): string
{
    return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $code));
}

function mfa_count_authenticators(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_mfa_authenticators WHERE user_id=:id');
    $stmt->execute([':id' => $userId]);
    return (int)$stmt->fetchColumn();
}

function mfa_list_authenticators(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id,label,created_at,last_used_at FROM user_mfa_authenticators WHERE user_id=:id ORDER BY created_at,id');
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mfa_begin_pending_session(array $user): void
{
    $deviceFingerprint = (string)($_SESSION['device_fingerprint'] ?? ($_POST['device_fingerprint'] ?? ''));
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['pending_mfa_user_id'] = (int)$user['id'];
    $_SESSION['pending_mfa_role'] = 'Super Admin';
    $_SESSION['pending_mfa_username'] = (string)$user['username'];
    $_SESSION['pending_mfa_password_fingerprint'] = hash('sha256', (string)$user['password']);
    $_SESSION['pending_mfa_session_version'] = (int)($user['mfa_session_version'] ?? 1);
    $_SESSION['pending_mfa_started_at'] = time();
    $_SESSION['pending_mfa_device_fingerprint'] = $deviceFingerprint;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function mfa_pending_user(PDO $pdo): ?array
{
    $userId = (int)($_SESSION['pending_mfa_user_id'] ?? 0);
    $startedAt = (int)($_SESSION['pending_mfa_started_at'] ?? 0);
    if ($userId <= 0 || $startedAt <= 0 || time() - $startedAt > 600
        || (string)($_SESSION['pending_mfa_role'] ?? '') !== 'Super Admin') return null;
    $stmt = $pdo->prepare('SELECT id,username,full_name,email,role,status,password,require_2fa,mfa_enabled_at,mfa_session_version FROM users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['status'] !== 'Active' || $user['role'] !== 'Super Admin'
        || empty($user['require_2fa'])
        || !hash_equals((string)($_SESSION['pending_mfa_password_fingerprint'] ?? ''), hash('sha256', (string)$user['password']))
        || (int)($_SESSION['pending_mfa_session_version'] ?? 0) !== (int)$user['mfa_session_version']) return null;
    if (defined('ENABLE_DEVICE_LOCK') && ENABLE_DEVICE_LOCK) {
        $fingerprint = (string)($_SESSION['pending_mfa_device_fingerprint'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/i', $fingerprint)) return null;
        $device = $pdo->prepare("SELECT id FROM registered_devices WHERE fingerprint_hash=:fingerprint AND status='Active' LIMIT 1");
        $device->execute([':fingerprint' => $fingerprint]);
        if (!$device->fetchColumn()) return null;
    }
    return $user;
}

function mfa_clear_pending_session(): void
{
    foreach (array_keys($_SESSION) as $key) {
        if (strpos((string)$key, 'pending_mfa_') === 0 || strpos((string)$key, 'mfa_setup_') === 0
            || strpos((string)$key, 'mfa_management_') === 0 || $key === 'mfa_recovery_codes') unset($_SESSION[$key]);
    }
}

function mfa_finish_login(array $user): void
{
    $deviceFingerprint = (string)($_SESSION['pending_mfa_device_fingerprint'] ?? '');
    setUserSession($user);
    if (preg_match('/^[a-f0-9]{64}$/i', $deviceFingerprint)) $_SESSION['device_fingerprint'] = strtolower($deviceFingerprint);
    mfa_clear_pending_session();
    if (function_exists('log_activity')) log_activity($GLOBALS['pdo'], 'login', 'Super Admin completed MFA login', (int)$user['id']);
    if (function_exists('logSecurityEvent')) logSecurityEvent('MFA_LOGIN_SUCCESS', 'LOW', 'Super Admin completed authenticator verification', (int)$user['id']);
    if (function_exists('logSecurityEvent')) logSecurityEvent('LOGIN_SUCCESS', 'LOW', 'Super Admin completed sign-in', (int)$user['id']);
}

function mfa_hash_recovery_codes(array $codes): array
{
    return array_map(static fn($code) => password_hash(mfa_normalize_recovery_code((string)$code), PASSWORD_DEFAULT), $codes);
}

function mfa_store_enrollment(PDO $pdo, array $user, string $secret, int $counter, array $recoveryCodes, string $label = 'Primary authenticator'): void
{
    $encrypted = mfa_encrypt_secret($secret);
    $hashes = mfa_hash_recovery_codes($recoveryCodes);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT role,status,mfa_enabled_at FROM users WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => (int)$user['id']]);
        $current = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$current || $current['role'] !== 'Super Admin' || $current['status'] !== 'Active'
            || !empty($current['mfa_enabled_at']) || mfa_count_authenticators($pdo, (int)$user['id']) !== 0) {
            throw new RuntimeException('This Super Admin account changed during MFA enrollment. Sign in again.');
        }
        $insertDevice = $pdo->prepare('INSERT INTO user_mfa_authenticators (user_id,label,secret_enc,last_totp_step,last_used_at) VALUES (:id,:label,:secret,:step,NOW())');
        $insertDevice->execute([':id' => (int)$user['id'], ':label' => $label, ':secret' => $encrypted, ':step' => $counter]);
        $update = $pdo->prepare('UPDATE users SET require_2fa=1,mfa_enabled_at=NOW(),mfa_secret_enc=NULL,mfa_last_totp_step=NULL WHERE id=:id');
        $update->execute([':id' => (int)$user['id']]);
        $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=:id')->execute([':id' => (int)$user['id']]);
        $insertCode = $pdo->prepare('INSERT INTO user_mfa_recovery_codes (user_id,code_hash) VALUES (:id,:hash)');
        foreach ($hashes as $hash) $insertCode->execute([':id' => (int)$user['id'], ':hash' => $hash]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Consume one new TOTP step for any enrolled device. Caller holds a transaction and user lock. */
function mfa_consume_totp_locked(PDO $pdo, int $userId, string $code): bool
{
    $devices = $pdo->prepare('SELECT id,secret_enc,last_totp_step FROM user_mfa_authenticators WHERE user_id=:id ORDER BY id FOR UPDATE');
    $devices->execute([':id' => $userId]);
    foreach ($devices->fetchAll(PDO::FETCH_ASSOC) as $device) {
        $counter = mfa_totp_counter(mfa_decrypt_secret((string)$device['secret_enc']), trim($code));
        if ($counter === null || ($device['last_totp_step'] !== null && $counter <= (int)$device['last_totp_step'])) continue;
        // Native PDO prepares require distinct names for each placeholder occurrence.
        $update = $pdo->prepare('UPDATE user_mfa_authenticators SET last_totp_step=:new_step,last_used_at=NOW() WHERE id=:id AND (last_totp_step IS NULL OR last_totp_step<:previous_step)');
        $update->execute([
            ':new_step' => $counter,
            ':id' => (int)$device['id'],
            ':previous_step' => $counter,
        ]);
        if ($update->rowCount() === 1) return true;
    }
    return false;
}

/** Consume one fresh TOTP step or one unused recovery code atomically. */
function mfa_consume_challenge(PDO $pdo, array $user, string $code): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT role,status,require_2fa,mfa_enabled_at,mfa_session_version FROM users WHERE id=:id FOR UPDATE');
        $stmt->execute([':id' => (int)$user['id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current || $current['role'] !== 'Super Admin' || $current['status'] !== 'Active'
            || empty($current['require_2fa']) || empty($current['mfa_enabled_at'])
            || (int)$current['mfa_session_version'] !== (int)($_SESSION['pending_mfa_session_version'] ?? 0)) {
            $pdo->rollBack();
            return false;
        }
        if (mfa_consume_totp_locked($pdo, (int)$user['id'], $code)) {
            $pdo->commit();
            return true;
        }
        $normalized = mfa_normalize_recovery_code($code);
        if (preg_match('/^[A-F0-9]{10}$/', $normalized)) {
            $codes = $pdo->prepare('SELECT id,code_hash FROM user_mfa_recovery_codes WHERE user_id=:id AND used_at IS NULL FOR UPDATE');
            $codes->execute([':id' => (int)$user['id']]);
            foreach ($codes->fetchAll(PDO::FETCH_ASSOC) as $recovery) {
                if (!password_verify($normalized, (string)$recovery['code_hash'])) continue;
                $consume = $pdo->prepare('UPDATE user_mfa_recovery_codes SET used_at=NOW() WHERE id=:id AND used_at IS NULL');
                $consume->execute([':id' => (int)$recovery['id']]);
                if ($consume->rowCount() === 1) {
                    $pdo->commit();
                    return true;
                }
            }
        }
        $pdo->commit();
        return false;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Verify a Super Admin's password and consume a fresh enrolled TOTP code. */
function mfa_verify_owner_credentials(PDO $pdo, int $userId, string $password, string $code): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,role,status,password,require_2fa,mfa_enabled_at FROM users WHERE id=:id FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['role'] !== 'Super Admin' || $user['status'] !== 'Active'
            || empty($user['require_2fa']) || empty($user['mfa_enabled_at'])
            || !password_verify($password, (string)$user['password'])) {
            $pdo->commit();
            return false;
        }
        $valid = mfa_consume_totp_locked($pdo, $userId, $code);
        $pdo->commit();
        return $valid;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mfa_add_authenticator(PDO $pdo, int $userId, string $label, string $secret, int $counter): int
{
    $label = trim($label);
    $labelLength = mfa_text_length($label);
    if ($label === '' || $labelLength > 60) throw new InvalidArgumentException('Authenticator label must be between 1 and 60 characters.');
    $encrypted = mfa_encrypt_secret($secret);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT role,status,require_2fa FROM users WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => $userId]);
        $user = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['role'] !== 'Super Admin' || $user['status'] !== 'Active' || empty($user['require_2fa'])) {
            throw new RuntimeException('Only an active Super Admin can add an authenticator.');
        }
        if (mfa_count_authenticators($pdo, $userId) >= MFA_MAX_AUTHENTICATORS) throw new RuntimeException('You have reached the five-authenticator limit.');
        $insert = $pdo->prepare('INSERT INTO user_mfa_authenticators (user_id,label,secret_enc,last_totp_step,last_used_at) VALUES (:id,:label,:secret,:step,NOW())');
        $insert->execute([':id' => $userId, ':label' => $label, ':secret' => $encrypted, ':step' => $counter]);
        $pdo->prepare('UPDATE users SET mfa_enabled_at=COALESCE(mfa_enabled_at,NOW()),mfa_secret_enc=NULL,mfa_last_totp_step=NULL WHERE id=:id')->execute([':id' => $userId]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mfa_revoke_authenticator(PDO $pdo, int $userId, int $deviceId): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT role,status FROM users WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => $userId]);
        $user = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['role'] !== 'Super Admin' || $user['status'] !== 'Active') throw new RuntimeException('Only an active Super Admin can manage authenticators.');
        $count = mfa_count_authenticators($pdo, $userId);
        if ($count <= 1) throw new RuntimeException('You cannot remove the last authenticator. Ask another Super Admin to reset MFA if all devices are lost.');
        $delete = $pdo->prepare('DELETE FROM user_mfa_authenticators WHERE id=:device_id AND user_id=:user_id');
        $delete->execute([':device_id' => $deviceId, ':user_id' => $userId]);
        if ($delete->rowCount() !== 1) throw new RuntimeException('Authenticator not found. Refresh the page and try again.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mfa_replace_recovery_codes(PDO $pdo, int $userId, array $codes): void
{
    $hashes = mfa_hash_recovery_codes($codes);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT role,status,mfa_enabled_at FROM users WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => $userId]);
        $user = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['role'] !== 'Super Admin' || $user['status'] !== 'Active' || empty($user['mfa_enabled_at'])
            || mfa_count_authenticators($pdo, $userId) === 0) throw new RuntimeException('Authenticator enrollment is required before recovery codes can be issued.');
        $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=:id')->execute([':id' => $userId]);
        $insert = $pdo->prepare('INSERT INTO user_mfa_recovery_codes (user_id,code_hash) VALUES (:id,:hash)');
        foreach ($hashes as $hash) $insert->execute([':id' => $userId, ':hash' => $hash]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Peer recovery reset; actor credentials and TOTP are consumed in the same transaction as the reset. */
function mfa_peer_reset(PDO $pdo, int $actorId, int $targetId, string $password, string $code, string $reason): string
{
    if ($actorId <= 0 || $targetId <= 0 || $actorId === $targetId) throw new InvalidArgumentException('A different Super Admin must perform the MFA reset.');
    if (mfa_text_length(trim($reason)) < 10 || mfa_text_length($reason) > 500) throw new InvalidArgumentException('Provide a reset reason between 10 and 500 characters.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,username,password,role,status,require_2fa,mfa_enabled_at FROM users WHERE id IN (:actor_id,:target_id) ORDER BY id FOR UPDATE');
        $stmt->execute([':actor_id' => $actorId, ':target_id' => $targetId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($rows as $row) $users[(int)$row['id']] = $row;
        $actor = $users[$actorId] ?? null;
        $target = $users[$targetId] ?? null;
        if (!$actor || !$target || $actor['role'] !== 'Super Admin' || $actor['status'] !== 'Active'
            || empty($actor['require_2fa']) || empty($actor['mfa_enabled_at'])) {
            throw new RuntimeException('An active, MFA-enrolled Super Admin must perform this reset.');
        }
        if ($target['role'] !== 'Super Admin' || $target['status'] !== 'Active') throw new RuntimeException('The target must be an active Super Admin.');
        if (!password_verify($password, (string)$actor['password']) || !mfa_consume_totp_locked($pdo, $actorId, $code)) {
            throw new DomainException('Current password or authenticator code is incorrect or already used.');
        }
        $pdo->prepare('DELETE FROM user_mfa_authenticators WHERE user_id=:id')->execute([':id' => $targetId]);
        $pdo->prepare('DELETE FROM user_mfa_recovery_codes WHERE user_id=:id')->execute([':id' => $targetId]);
        $pdo->prepare('UPDATE users SET require_2fa=1,mfa_secret_enc=NULL,mfa_enabled_at=NULL,mfa_last_totp_step=NULL,mfa_session_version=mfa_session_version+1 WHERE id=:id')
            ->execute([':id' => $targetId]);
        $details = json_encode([
            'target_user_id' => $targetId,
            'target_username' => (string)$target['username'],
            'reason' => trim($reason),
            'action' => 'all authenticators and recovery codes revoked; MFA enrollment required',
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $log = $pdo->prepare("INSERT INTO security_logs (event_type,severity,user_id,ip_address,user_agent,details,created_at) VALUES ('SUPER_ADMIN_MFA_RESET','HIGH',:actor_id,:ip,:agent,:details,NOW())");
        $log->execute([
            ':actor_id' => $actorId,
            ':ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ':agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
            ':details' => $details ?: '{}',
        ]);
        $pdo->commit();
        return (string)$target['username'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
