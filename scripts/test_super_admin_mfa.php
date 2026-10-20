<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

define('MFA_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
require_once __DIR__ . '/../includes/mfa.php';

function assert_mfa_test(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

try {
    $raw = '12345678901234567890';
    assert_mfa_test(MFA_MAX_AUTHENTICATORS === 5, 'Authenticator device limit changed unexpectedly.');
    $secret = mfa_base32_encode($raw);
    assert_mfa_test(mfa_base32_decode($secret) === $raw, 'Base32 round trip failed.');
    assert_mfa_test(mfa_totp_counter($secret, '287082', 59, 0) === 1, 'RFC TOTP vector did not match.');
    assert_mfa_test(mfa_totp_counter($secret, '000000', 59, 0) === null, 'Invalid TOTP code was accepted.');

    $generatedSecret = mfa_generate_secret();
    $encrypted = mfa_encrypt_secret($generatedSecret);
    assert_mfa_test($encrypted !== $generatedSecret && mfa_decrypt_secret($encrypted) === $generatedSecret, 'Encrypted secret round trip failed.');

    $recoveryCodes = mfa_generate_recovery_codes(10);
    assert_mfa_test(count($recoveryCodes) === 10, 'Expected ten recovery codes.');
    $normalized = mfa_normalize_recovery_code($recoveryCodes[0]);
    $hash = password_hash($normalized, PASSWORD_DEFAULT);
    assert_mfa_test(password_verify(mfa_normalize_recovery_code($recoveryCodes[0]), $hash), 'Recovery code hash verification failed.');
    assert_mfa_test(!password_verify(mfa_normalize_recovery_code($recoveryCodes[1]), $hash), 'A different recovery code matched the hash.');

    echo "Super Admin MFA helper checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
