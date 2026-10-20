<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/mfa.php';

requireSuperAdmin();
setSecurityHeaders();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$userId = (int)getUserId();
$username = getUsername();
$flash = $_SESSION['mfa_management_flash'] ?? null;
unset($_SESSION['mfa_management_flash']);
$recoveryCodes = $_SESSION['mfa_management_recovery_codes'] ?? [];
$setupSecret = (string)($_SESSION['mfa_management_setup_secret'] ?? '');
$setupLabel = (string)($_SESSION['mfa_management_setup_label'] ?? '');
$setupStarted = (int)($_SESSION['mfa_management_setup_started_at'] ?? 0);
if ($setupStarted > 0 && time() - $setupStarted > 600) {
    unset($_SESSION['mfa_management_setup_secret'], $_SESSION['mfa_management_setup_label'], $_SESSION['mfa_management_setup_started_at']);
    $setupSecret = '';
    $setupLabel = '';
    $flash = ['type' => 'error', 'message' => 'Authenticator setup expired. Start again to get a fresh setup key.'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $message = '';
    $messageType = 'error';
    $attemptKey = 'mfa_manage_' . $userId . '_' . (function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) throw new RuntimeException('Security token expired. Refresh the page and try again.');
        if ($action === 'cancel_add') {
            unset($_SESSION['mfa_management_setup_secret'], $_SESSION['mfa_management_setup_label'], $_SESSION['mfa_management_setup_started_at']);
            $message = 'Authenticator setup canceled.';
            $messageType = 'success';
        } elseif ($action === 'dismiss_recovery_codes') {
            unset($_SESSION['mfa_management_recovery_codes']);
            $message = 'Recovery codes closed. They cannot be shown again.';
            $messageType = 'success';
        } elseif ($action === 'start_add' || $action === 'revoke' || $action === 'replace_recovery_codes') {
            $limit = checkRateLimit($attemptKey, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
            if (empty($limit['allowed'])) throw new RuntimeException($limit['message'] ?? 'Too many attempts. Try again later.');
            $password = (string)($_POST['current_password'] ?? '');
            $currentCode = trim((string)($_POST['current_code'] ?? ''));
            if ($password === '' || !preg_match('/^\d{6}$/', $currentCode)) {
                throw new InvalidArgumentException('Enter your password and a current six-digit authenticator code.');
            }
            if (!mfa_verify_owner_credentials($pdo, $userId, $password, $currentCode)) {
                logSecurityEvent('MFA_MANAGEMENT_PROOF_FAILED', 'MEDIUM', 'Invalid password or TOTP for Super Admin MFA management.', $userId);
                throw new DomainException('Your password or authenticator code is incorrect, expired, or already used.');
            }
            clearRateLimit($attemptKey);

            if ($action === 'start_add') {
                if (mfa_count_authenticators($pdo, $userId) >= MFA_MAX_AUTHENTICATORS) throw new RuntimeException('You have reached the five-authenticator limit.');
                $label = trim((string)($_POST['label'] ?? ''));
                $labelLength = function_exists('mb_strlen') ? mb_strlen($label) : strlen($label);
                if ($labelLength < 1 || $labelLength > 60) throw new InvalidArgumentException('Enter a device label between 1 and 60 characters.');
                $_SESSION['mfa_management_setup_secret'] = mfa_generate_secret();
                $_SESSION['mfa_management_setup_label'] = $label;
                $_SESSION['mfa_management_setup_started_at'] = time();
                $message = 'Add the setup key to your new authenticator and confirm with its six-digit code.';
                $messageType = 'success';
            } elseif ($action === 'revoke') {
                $deviceId = filter_var($_POST['device_id'] ?? null, FILTER_VALIDATE_INT);
                if (!$deviceId || $deviceId <= 0) throw new InvalidArgumentException('Choose a valid authenticator.');
                mfa_revoke_authenticator($pdo, $userId, (int)$deviceId);
                logSecurityEvent('MFA_DEVICE_REVOKED', 'HIGH', 'Super Admin revoked one of their authenticator devices.', $userId);
                $message = 'Authenticator removed.';
                $messageType = 'success';
            } else {
                $codes = mfa_generate_recovery_codes();
                mfa_replace_recovery_codes($pdo, $userId, $codes);
                $_SESSION['mfa_management_recovery_codes'] = $codes;
                logSecurityEvent('MFA_RECOVERY_CODES_REPLACED', 'HIGH', 'Super Admin replaced their recovery codes.', $userId);
                $message = 'New recovery codes created. Previous recovery codes are no longer valid.';
                $messageType = 'success';
            }
        } elseif ($action === 'confirm_add') {
            $limit = checkRateLimit($attemptKey, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
            if (empty($limit['allowed'])) throw new RuntimeException($limit['message'] ?? 'Too many attempts. Try again later.');
            $secret = (string)($_SESSION['mfa_management_setup_secret'] ?? '');
            $label = (string)($_SESSION['mfa_management_setup_label'] ?? '');
            $started = (int)($_SESSION['mfa_management_setup_started_at'] ?? 0);
            if ($secret === '' || $started <= 0 || time() - $started > 600) throw new RuntimeException('Authenticator setup expired. Start again.');
            if (mfa_count_authenticators($pdo, $userId) >= MFA_MAX_AUTHENTICATORS) throw new RuntimeException('You have reached the five-authenticator limit.');
            $counter = mfa_totp_counter($secret, trim((string)($_POST['new_code'] ?? '')));
            if ($counter === null) {
                logSecurityEvent('MFA_DEVICE_ENROLLMENT_FAILED', 'MEDIUM', 'Invalid code while adding a Super Admin authenticator.', $userId);
                throw new DomainException('The new authenticator code is invalid. Check the device clock and try again.');
            }
            mfa_add_authenticator($pdo, $userId, $label, $secret, $counter);
            clearRateLimit($attemptKey);
            unset($_SESSION['mfa_management_setup_secret'], $_SESSION['mfa_management_setup_label'], $_SESSION['mfa_management_setup_started_at']);
            logSecurityEvent('MFA_DEVICE_ADDED', 'HIGH', 'Super Admin added an authenticator device.', $userId);
            $message = 'Authenticator added and verified.';
            $messageType = 'success';
        } else {
            throw new InvalidArgumentException('Unsupported MFA management action.');
        }
    } catch (PDOException $error) {
        error_log('MFA management database operation failed: ' . $error->getMessage());
        $message = 'The MFA change could not be completed. Refresh the page and try again.';
    } catch (DomainException | InvalidArgumentException | RuntimeException $error) {
        $message = $error->getMessage();
    } catch (Throwable $error) {
        error_log('MFA management action failed: ' . $error->getMessage());
        $message = 'The MFA change could not be completed. Refresh the page and try again.';
    }
    $_SESSION['mfa_management_flash'] = ['type' => $messageType, 'message' => $message];
    header('Location: mfa_management.php');
    exit;
}

$devices = mfa_list_authenticators($pdo, $userId);
$deviceCount = count($devices);
$recoveryCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_mfa_recovery_codes WHERE user_id=:id AND used_at IS NULL');
$recoveryCountStmt->execute([':id' => $userId]);
$unusedRecoveryCount = (int)$recoveryCountStmt->fetchColumn();
$otpauth = $setupSecret !== '' ? 'otpauth://totp/' . rawurlencode(APP_SHORT_NAME . ':' . $username)
    . '?secret=' . rawurlencode($setupSecret) . '&issuer=' . rawurlencode(APP_SHORT_NAME) . '&algorithm=SHA1&digits=6&period=30' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfTokenMeta() ?>
    <title>My MFA - <?= htmlspecialchars(APP_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20261019-active-icon-2">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f6fa;color:#172033;font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}.mfa-page{max-width:1050px;margin:0 auto;padding:30px 24px 48px}.mfa-hero,.mfa-card{background:#fff;border:1px solid #e1e7f0;border-radius:14px;padding:24px 28px;margin-bottom:20px;box-shadow:0 8px 24px #1e293b08}.mfa-hero h1{margin:0 0 6px;font-size:1.65rem}.muted,.hint{color:#64748b}.mfa-card h2{margin:0 0 12px;font-size:1.15rem}.alert{padding:12px 15px;border-radius:9px;margin:0 0 18px}.alert.success{background:#ecfdf3;color:#166534;border:1px solid #bbf7d0}.alert.error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}.device-list{width:100%;border-collapse:collapse}.device-list th,.device-list td{text-align:left;padding:12px;border-bottom:1px solid #e5eaf1;vertical-align:middle}.device-list th{font-size:.82rem;color:#64748b}.device-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.field{display:block;width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;margin:5px 0 13px}.field:focus{outline:3px solid #bfdbfe;border-color:#2563eb}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.btn{border:0;border-radius:8px;padding:10px 15px;font:600 .92rem inherit;cursor:pointer}.btn-primary{background:#1d4ed8;color:#fff}.btn-danger{background:#b91c1c;color:#fff}.btn-secondary{background:#e8edf5;color:#1e293b}.btn:disabled{opacity:.55;cursor:not-allowed}.secret-block,.codes{padding:16px;background:#f7f9fc;border:1px solid #dce4ef;border-radius:9px;overflow-wrap:anywhere}.secret{font:700 1.05rem ui-monospace,monospace;letter-spacing:.12em}.codes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:14px 0}.codes code{text-align:center;padding:9px;background:#fff;border:1px solid #e2e8f0;border-radius:6px}.inline-form{display:inline}.page-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.setup-uri{overflow-wrap:anywhere}.small{font-size:.9rem}.device-action{white-space:nowrap}@media(max-width:700px){.mfa-page{padding:18px 12px 32px}.mfa-hero,.mfa-card{padding:20px 16px}.form-grid{grid-template-columns:1fr}.device-list th:nth-child(3),.device-list td:nth-child(3){display:none}.codes{grid-template-columns:1fr 1fr}}
    </style>
    <link rel="stylesheet" href="../assets/css/mfa-qr.css?v=20261020-1">
    <?php if ($setupSecret !== ''): ?>
        <script src="../assets/js/qrcode-generator-2.0.4.js" defer></script>
        <script src="../assets/js/mfa-qr.js?v=20261020-1" defer></script>
    <?php endif; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/preloader.php'; ?>
<div class="mobile-header">
    <div class="mobile-header-content">
        <h4><?= htmlspecialchars(APP_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></h4>
        <button id="mobileSidebarToggle" type="button" aria-label="Open navigation"><i data-lucide="menu"></i></button>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include __DIR__ . '/../includes/sidebar_nav.php'; ?>
<?php include __DIR__ . '/../includes/top_navbar.php'; ?>
<div class="content">
<main class="mfa-page">
    <section class="mfa-hero">
        <h1>My MFA</h1>
        <p class="muted">Manage the authenticator devices and one-time recovery codes for <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>. MFA remains required for every Super Admin.</p>
    </section>

    <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'success' : 'error' ?>" role="status"><?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($recoveryCodes): ?>
        <section class="mfa-card">
            <h2>Save your new recovery codes</h2>
            <p class="hint">These codes replace all previous recovery codes. Each code works once. This list is shown only for this session.</p>
            <div class="codes"><?php foreach ($recoveryCodes as $code): ?><code><?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?></code><?php endforeach; ?></div>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="dismiss_recovery_codes"><button class="btn btn-primary" type="submit">I saved these codes</button></form>
        </section>
    <?php endif; ?>

    <section class="mfa-card">
        <h2>Registered authenticators <span class="hint small">(<?= $deviceCount ?>/<?= MFA_MAX_AUTHENTICATORS ?>)</span></h2>
        <?php if (!$devices): ?><p class="hint">No authenticator is enrolled. Sign out and sign in again to complete required MFA enrollment.</p>
        <?php else: ?>
            <div style="overflow-x:auto"><table class="device-list"><thead><tr><th>Device</th><th>Added</th><th>Last used</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$device['label'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('M d, Y H:i', strtotime((string)$device['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($device['last_used_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime((string)$device['last_used_at'])), ENT_QUOTES, 'UTF-8') : 'Never' ?></td>
                        <td><?php if ($deviceCount > 1): ?>
                            <form class="device-form" method="post" onsubmit="return confirm('Remove this authenticator? You will need your password and a current code from one of your enrolled devices.');">
                                <?= csrfTokenField() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>">
                                <input class="field" style="width:150px;margin:0" type="password" name="current_password" autocomplete="current-password" placeholder="Password" required>
                                <input class="field" style="width:115px;margin:0" type="text" name="current_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" required>
                                <button class="btn btn-danger device-action" type="submit">Remove</button>
                            </form>
                        <?php else: ?><span class="hint small">Last device</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

    <?php if ($setupSecret !== ''): ?>
        <section class="mfa-card">
            <h2>Confirm new authenticator</h2>
            <p class="hint">Setup expires in 10 minutes. Scan the QR code or enter the setup key manually, then confirm with a code from the new authenticator.</p>
            <div class="mfa-qr-panel">
                <p class="mfa-qr-heading">Scan with your authenticator app</p>
                <div class="mfa-qr" data-mfa-qr role="img" aria-label="New authenticator setup QR code" data-otpauth-uri="<?= htmlspecialchars($otpauth, ENT_QUOTES, 'UTF-8') ?>">QR code unavailable. Use the setup key below.</div>
                <p class="mfa-qr-help">This QR contains your private setup key. Keep it private.</p>
            </div>
            <div class="secret-block"><div class="hint small">Device: <?= htmlspecialchars($setupLabel, ENT_QUOTES, 'UTF-8') ?></div><div class="secret"><?= htmlspecialchars($setupSecret, ENT_QUOTES, 'UTF-8') ?></div></div>
            <details><summary>Show authenticator setup URI</summary><div class="secret-block small setup-uri"><?= htmlspecialchars($otpauth, ENT_QUOTES, 'UTF-8') ?></div></details>
            <form method="post" class="mfa-card" style="padding:0;border:0;box-shadow:none;margin:18px 0 0">
                <?= csrfTokenField() ?><input type="hidden" name="action" value="confirm_add">
                <label for="new_code">Code from the new authenticator</label><input class="field" id="new_code" name="new_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                <div class="page-actions"><button class="btn btn-primary" type="submit">Confirm and add device</button></div>
            </form>
            <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="cancel_add"><button class="btn btn-secondary" type="submit">Cancel setup</button></form>
        </section>
    <?php elseif ($deviceCount < MFA_MAX_AUTHENTICATORS): ?>
        <section class="mfa-card">
            <h2>Add an authenticator</h2>
            <p class="hint">Verify your current authenticator before starting. The new device must confirm its own code before it is registered.</p>
            <form method="post">
                <?= csrfTokenField() ?><input type="hidden" name="action" value="start_add">
                <div class="form-grid">
                    <div class="form-group"><label for="label">Device label</label><input class="field" id="label" name="label" maxlength="60" placeholder="For example, office phone" required></div>
                    <div class="form-group"><label for="current_password">Your password</label><input class="field" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                </div>
                <label for="current_code">Current code from an enrolled authenticator</label><input class="field" id="current_code" name="current_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                <button class="btn btn-primary" type="submit">Start device enrollment</button>
            </form>
        </section>
    <?php else: ?>
        <section class="mfa-card"><h2>Add an authenticator</h2><p class="hint">You have reached the five-device limit. Remove an existing device before adding another.</p></section>
    <?php endif; ?>

    <section class="mfa-card">
        <h2>Recovery codes</h2>
        <p class="hint">You have <?= $unusedRecoveryCount ?> unused recovery code(s). Replacing them immediately invalidates every previous code.</p>
        <form method="post" onsubmit="return confirm('Replace all recovery codes? Any previously saved codes will stop working.');">
            <?= csrfTokenField() ?><input type="hidden" name="action" value="replace_recovery_codes">
            <div class="form-grid">
                <div class="form-group"><label for="recovery_password">Your password</label><input class="field" id="recovery_password" name="current_password" type="password" autocomplete="current-password" required></div>
                <div class="form-group"><label for="recovery_code">Current authenticator code</label><input class="field" id="recovery_code" name="current_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
            </div>
            <button class="btn btn-secondary" type="submit">Replace recovery codes</button>
        </form>
    </section>
</main>
</div>
<?php include __DIR__ . '/../includes/sidebar_scripts.php'; ?>
<script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
