<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/mfa.php';

setSecurityHeaders();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isLoggedIn()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$user = mfa_pending_user($pdo);
if (!$user) {
    mfa_clear_pending_session();
    header('Location: login.php?timeout=1');
    exit;
}

$error = '';
$message = '';
$enrollmentComplete = !empty($_SESSION['mfa_recovery_codes']);
$isEnrolled = !empty($user['mfa_enabled_at']) && mfa_count_authenticators($pdo, (int)$user['id']) > 0;
if (!$isEnrolled && empty($_SESSION['mfa_setup_secret'])) {
    try {
        // Validate the deployment key before presenting enrollment instructions.
        mfa_encryption_key();
        $_SESSION['mfa_setup_secret'] = mfa_generate_secret();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Refresh this page and try again.';
    } elseif ($enrollmentComplete && ($_POST['action'] ?? '') === 'finish_enrollment') {
        mfa_finish_login($user);
        header('Location: ../admin/dashboard.php');
        exit;
    } elseif (!$isEnrolled && ($_POST['action'] ?? '') === 'enroll') {
        $limitKey = 'mfa_enroll_' . (int)$user['id'] . '_' . (function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $limit = checkRateLimit($limitKey, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
        if (empty($limit['allowed'])) {
            $error = $limit['message'] ?? 'Too many attempts. Try again later.';
        } else {
            try {
                $secret = (string)($_SESSION['mfa_setup_secret'] ?? '');
                $counter = $secret !== '' ? mfa_totp_counter($secret, trim((string)($_POST['code'] ?? ''))) : null;
                if ($counter === null) {
                    $error = 'That authenticator code is not valid. Check the device clock and try again.';
                    logSecurityEvent('MFA_ENROLLMENT_FAILED', 'MEDIUM', 'Invalid TOTP during Super Admin enrollment', (int)$user['id']);
                } else {
                    $codes = mfa_generate_recovery_codes();
                    mfa_store_enrollment($pdo, $user, $secret, $counter, $codes);
                    unset($_SESSION['mfa_setup_secret']);
                    $_SESSION['mfa_recovery_codes'] = $codes;
                    $enrollmentComplete = true;
                    $isEnrolled = true;
                    clearRateLimit($limitKey);
                    logSecurityEvent('MFA_ENROLLMENT_COMPLETED', 'HIGH', 'Super Admin authenticator enrollment completed', (int)$user['id']);
                }
            } catch (Throwable $e) {
                error_log('Super Admin MFA enrollment failed: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        }
    } elseif ($isEnrolled && !$enrollmentComplete && ($_POST['action'] ?? '') === 'verify') {
        $limitKey = 'mfa_login_' . (int)$user['id'] . '_' . (function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $limit = checkRateLimit($limitKey, MAX_LOGIN_ATTEMPTS, RATE_LIMIT_WINDOW);
        if (empty($limit['allowed'])) {
            $error = $limit['message'] ?? 'Too many attempts. Try again later.';
        } else {
            try {
                if (mfa_consume_challenge($pdo, $user, trim((string)($_POST['code'] ?? '')))) {
                    clearRateLimit($limitKey);
                    mfa_finish_login($user);
                    header('Location: ../admin/dashboard.php');
                    exit;
                }
                $error = 'The code is incorrect, expired, or has already been used.';
                logSecurityEvent('MFA_CHALLENGE_FAILED', 'MEDIUM', 'Invalid or replayed Super Admin MFA code', (int)$user['id']);
            } catch (Throwable $e) {
                error_log('Super Admin MFA challenge failed: ' . $e->getMessage());
                $error = 'MFA verification is unavailable. Check the server encryption key and try again.';
            }
        }
    } else {
        $error = 'This MFA step has expired. Sign in again.';
    }
}

$setupSecret = (string)($_SESSION['mfa_setup_secret'] ?? '');
$otpauth = $setupSecret !== '' ? 'otpauth://totp/' . rawurlencode(APP_SHORT_NAME . ':' . (string)$user['username'])
    . '?secret=' . rawurlencode($setupSecret) . '&issuer=' . rawurlencode(APP_SHORT_NAME) . '&algorithm=SHA1&digits=6&period=30' : '';
$recoveryCodes = $_SESSION['mfa_recovery_codes'] ?? [];
$displayName = trim((string)($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'Super Admin account';
}
$stageLabel = $enrollmentComplete ? 'SETUP COMPLETE' : (!$isEnrolled ? 'STEP 1 OF 2' : 'STEP 2 OF 2');
$pageHeading = $enrollmentComplete ? 'Authenticator is ready' : (!$isEnrolled ? 'Set up your authenticator' : 'Verify your identity');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Verification - <?= htmlspecialchars(APP_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root{color-scheme:light;--ink:#142b4d;--muted:#61728a;--blue:#2458dc;--blue-hover:#1948c1;--line:#dce5f1;--soft:#f4f7fc}
        *{box-sizing:border-box}
        body{min-height:100vh;margin:0;padding:18px;display:grid;place-items:center;background:radial-gradient(ellipse at 50% 0%,#eaf1fc 0%,#f4f7fc 62%,#eef3f9 100%);color:var(--ink);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
        .card{position:relative;overflow:hidden;width:min(100%,460px);padding:27px 30px 22px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 18px 48px rgba(27,54,92,.1)}
        .card::before{position:absolute;inset:0 0 auto;height:3px;background:linear-gradient(90deg,#6ea5ff,#2458dc);content:""}
        .security-mark{display:block;width:78px;height:78px;margin:0 auto 9px;object-fit:contain}
        .eyebrow{margin:0 0 4px;text-align:center;color:#54709b;font-size:.68rem;font-weight:700;letter-spacing:.11em}
        h1{margin:0;text-align:center;color:var(--ink);font-size:clamp(1.4rem,3.4vw,1.6rem);line-height:1.2;letter-spacing:-.02em}
        .muted{max-width:350px;margin:7px auto 12px;text-align:center;color:var(--muted);font-size:.9rem;line-height:1.4}
        .identity{display:flex;align-items:center;justify-content:center;gap:7px;width:max-content;max-width:100%;margin:0 auto 18px;padding:5px 10px;border:1px solid #e4eaf3;border-radius:999px;background:#f8faff;color:#6b7a90;font-size:.8rem}
        .identity strong{overflow:hidden;max-width:240px;color:#29466f;font-size:.82rem;font-weight:650;text-overflow:ellipsis;white-space:nowrap}
        .identity-dot{width:7px;height:7px;flex:none;border-radius:50%;background:#4383e9}
        .intro{max-width:360px;margin:0 auto 12px;text-align:center;color:#50627c;font-size:.92rem;line-height:1.4}
        .notice{margin:14px 0;padding:11px 13px;border:1px solid transparent;border-radius:10px}
        .error{border-color:#f2d2d0;background:#fff3f2;color:#9c2922}.success{border-color:#cde8d7;background:#f0faf4;color:#17643a}
        .block{margin:14px 0;padding:14px 16px;border:1px solid #e0e8f2;border-radius:12px;background:var(--soft)}
        .secret{color:var(--ink);font:700 1rem/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;letter-spacing:.1em;overflow-wrap:anywhere}
        .codes{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:15px 0}
        .codes code{padding:9px 7px;border:1px solid #e1e8f2;border-radius:8px;background:#f8faff;color:#29466f;text-align:center;font-size:.92rem}
        details{margin:4px 0 5px}summary{width:max-content;max-width:100%;color:var(--blue);cursor:pointer;text-underline-offset:3px}
        details .block{font-size:.84rem;line-height:1.45}
        .actions{width:min(100%,390px);margin:16px auto 0}
        .actions label{display:block;margin:0 0 8px;color:#40536e;text-align:center;font-size:.87rem;font-weight:650}
        .field{display:block;width:100%;margin:8px 0 14px;padding:12px;border:1px solid #b9c8dc;border-radius:10px;background:#fff;color:var(--ink);font:inherit}
        .field:focus{outline:0;border-color:#3474ed;box-shadow:0 0 0 3px #dceaff}
        .otp-widget{position:relative;width:100%;margin:0 auto}
        .otp-slots{display:none}
        .otp-widget.is-enhanced .otp-slots{display:grid;width:100%;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin:0 0 12px}
        .otp-slot{display:grid;width:100%;height:auto;aspect-ratio:1/1;place-items:center;border:1.5px solid #b7c8df;border-radius:10px;background:#fff;color:#183b70;font-size:1.35rem;font-weight:700;transition:border-color .16s ease,box-shadow .16s ease,background .16s ease}
        .otp-slot.active{border-color:#3474ed;background:#f2f7ff;box-shadow:0 0 0 3px #dceaff}
        .otp-widget.is-enhanced .otp-capture{position:absolute;z-index:2;inset:0 0 auto;width:100%;height:58px;margin:0;padding:0;border:0;opacity:.01;color:transparent;caret-color:transparent;cursor:text}
        .otp-widget.is-enhanced:focus-within .otp-slot.active{border-color:#2865e8;box-shadow:0 0 0 3px #d5e5ff}
        .otp-status{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        button{display:block;width:100%;min-height:47px;padding:12px 15px;border:0;border-radius:10px;background:var(--blue);box-shadow:0 4px 10px rgba(36,88,220,.14);color:#fff;font:600 .95rem/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;cursor:pointer;transition:background .15s ease,box-shadow .15s ease,transform .15s ease}
        button:hover{background:var(--blue-hover);box-shadow:0 6px 14px rgba(36,88,220,.2)}
        button:active{transform:translateY(1px)}
        button:focus-visible,a:focus-visible,summary:focus-visible{outline:3px solid #93baff;outline-offset:3px}
        .recovery-toggle{display:block;width:max-content;min-height:0;margin:0 auto 12px;padding:2px 0;background:transparent;box-shadow:none;color:var(--blue);font:500 .88rem/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;text-decoration:underline;text-underline-offset:3px}
        .recovery-toggle:hover{background:transparent;box-shadow:none;color:var(--blue-hover)}
        .otp-widget.recovery-mode .otp-slots{display:none!important}
        .otp-widget.recovery-mode .otp-capture{position:static;width:100%;height:auto;margin:0 0 14px;padding:12px;border:1px solid #b9c8dc;border-radius:10px;opacity:1;color:var(--ink);caret-color:auto;cursor:text}
        .small{color:var(--muted);font-size:.82rem}
        .footer{max-width:390px;margin:16px auto 0;padding-top:12px;border-top:1px solid #edf1f6;text-align:center}
        a{color:var(--blue);text-underline-offset:3px}
        a:hover{color:var(--blue-hover)}
        .sign-out-link{color:#b42318}
        .sign-out-link:hover{color:#8f1d16}
        @keyframes mfa-card-enter{from{opacity:0;transform:translateY(12px) scale(.992)}to{opacity:1;transform:translateY(0) scale(1)}}
        @keyframes mfa-item-enter{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
        .card{animation:mfa-card-enter .46s cubic-bezier(.2,.75,.25,1) both}
        .security-mark{animation:mfa-item-enter .34s ease-out .08s both}
        .eyebrow{animation:mfa-item-enter .32s ease-out .12s both}
        h1{animation:mfa-item-enter .34s ease-out .16s both}
        .muted,.identity,.intro,.actions label{animation:mfa-item-enter .34s ease-out .2s both}
        .otp-widget.is-enhanced .otp-slot{animation:mfa-item-enter .28s ease-out both}
        .otp-widget.is-enhanced .otp-slot:nth-child(1){animation-delay:.22s}
        .otp-widget.is-enhanced .otp-slot:nth-child(2){animation-delay:.26s}
        .otp-widget.is-enhanced .otp-slot:nth-child(3){animation-delay:.3s}
        .otp-widget.is-enhanced .otp-slot:nth-child(4){animation-delay:.34s}
        .otp-widget.is-enhanced .otp-slot:nth-child(5){animation-delay:.38s}
        .otp-widget.is-enhanced .otp-slot:nth-child(6){animation-delay:.42s}
        .actions>button{animation:mfa-item-enter .32s ease-out .34s both}
        @media(max-width:480px){body{padding:12px}.card{padding:23px 22px 19px;border-radius:18px}.identity{margin-bottom:15px}.otp-widget.is-enhanced .otp-slots{gap:7px}.otp-widget.is-enhanced .otp-capture{height:clamp(35px,calc((100vw - 103px)/6),59px)}.footer{margin-top:14px}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
    </style>
    <link rel="stylesheet" href="../assets/css/mfa-qr.css?v=20261020-1">
    <?php if ($setupSecret !== '' && !$isEnrolled && !$enrollmentComplete): ?>
        <script src="../assets/js/qrcode-generator-2.0.4.js" defer></script>
        <script src="../assets/js/mfa-qr.js?v=20261020-1" defer></script>
    <?php endif; ?>
</head>
<body>
<main class="card">
    <img class="security-mark" src="../assets/img/mfa_account_security.gif" alt="" aria-hidden="true" width="78" height="78">
    <p class="eyebrow"><?= htmlspecialchars($stageLabel, ENT_QUOTES, 'UTF-8') ?></p>
    <h1><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="muted"><?= $enrollmentComplete ? 'Authenticator setup is complete. Save your recovery codes before continuing.' : (!$isEnrolled ? 'Secure your account with an authenticator app.' : 'Enter a code to finish signing in.') ?></p>
    <div class="identity"><span class="identity-dot" aria-hidden="true"></span><span>Account</span><strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong></div>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($enrollmentComplete): ?>
        <div class="notice success">Authenticator setup is complete. Store these recovery codes somewhere secure. Each code can be used once.</div>
        <div class="codes"><?php foreach ($recoveryCodes as $code): ?><code><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></code><?php endforeach; ?></div>
        <form method="post" class="actions">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="finish_enrollment">
            <button type="submit">I saved these codes, continue</button>
        </form>
    <?php elseif (!$isEnrolled): ?>
        <?php if ($setupSecret !== ''): ?>
            <p class="intro">Scan this code in your authenticator app, or enter the setup key manually.</p>
            <div class="mfa-qr-panel">
                <p class="mfa-qr-heading">Scan with your authenticator app</p>
                <div class="mfa-qr" data-mfa-qr role="img" aria-label="Authenticator setup QR code" data-otpauth-uri="<?= htmlspecialchars($otpauth, ENT_QUOTES, 'UTF-8') ?>">QR code unavailable. Use the setup key below.</div>
                <p class="mfa-qr-help">This QR contains your private setup key. Keep it private.</p>
            </div>
            <div class="block"><div class="small">AUTHENTICATOR SETUP KEY</div><div class="secret"><?= htmlspecialchars($setupSecret, ENT_QUOTES, 'UTF-8') ?></div></div>
            <details><summary>Show authenticator setup URI</summary><div class="block small" style="word-break:break-all"><?= htmlspecialchars($otpauth, ENT_QUOTES, 'UTF-8') ?></div></details>
            <form method="post" class="actions">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="enroll">
                <label for="code">Enter the 6-digit code from your app</label>
                <div class="otp-widget" data-otp-widget>
                    <div class="otp-slots" role="group" aria-label="Six-digit authenticator code" aria-hidden="true"><?php for ($i = 0; $i < 6; $i++): ?><span class="otp-slot" data-otp-slot></span><?php endfor; ?></div>
                    <input class="field otp-capture" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
                </div>
                <p class="otp-status" data-otp-status aria-live="polite"></p>
                <button type="submit">Enable authenticator MFA</button>
            </form>
        <?php else: ?>
            <p>Authenticator enrollment is not ready. Ask the deployment operator to configure <code>MFA_ENCRYPTION_KEY</code>, then sign in again.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="intro">Enter the 6-digit code from your authenticator app.</p>
        <form method="post" class="actions">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="verify">
            <label for="code" data-code-label>Authenticator code</label>
            <div class="otp-widget" data-otp-widget>
                <div class="otp-slots" role="group" aria-label="Six-digit authenticator code" aria-hidden="true"><?php for ($i = 0; $i < 6; $i++): ?><span class="otp-slot" data-otp-slot></span><?php endfor; ?></div>
                <input class="field otp-capture" id="code" name="code" autocomplete="one-time-code" inputmode="numeric" maxlength="32" required autofocus>
            </div>
            <button class="recovery-toggle" type="button" data-recovery-toggle>Use a recovery code instead</button>
            <p class="otp-status" data-otp-status aria-live="polite"></p>
            <button type="submit">Verify and continue</button>
        </form>
    <?php endif; ?>
    <p class="small footer">This restricted session expires after 10 minutes.<br><a class="sign-out-link" href="logout.php">Cancel and sign out</a></p>
</main>
<script>
(function () {
    document.querySelectorAll('[data-otp-widget]').forEach(function (widget) {
        const input = widget.querySelector('[name="code"]');
        const slots = Array.from(widget.querySelectorAll('[data-otp-slot]'));
        const form = widget.closest('form');
        const toggle = form ? form.querySelector('[data-recovery-toggle]') : null;
        const label = form ? form.querySelector('[data-code-label]') : null;
        const status = form ? form.querySelector('[data-otp-status]') : null;
        if (!input || !form) return;

        widget.classList.add('is-enhanced');
        let recoveryMode = false;
        let submitPending = false;
        let submitTimer = null;

        function paintSlots(digits) {
            slots.forEach(function (slot, index) {
                slot.textContent = digits[index] || '';
                slot.classList.toggle('active', !recoveryMode && index === Math.min(digits.length, 5));
            });
        }

        function normalizeAuthenticatorInput() {
            if (recoveryMode) return;
            const digits = input.value.replace(/\D/g, '').slice(0, 6);
            if (input.value !== digits) input.value = digits;
            paintSlots(digits);
            if (digits.length === 6 && !submitPending) {
                submitPending = true;
                if (status) status.textContent = 'Verifying code…';
                submitTimer = window.setTimeout(function () {
                    if (input.value.length === 6 && !recoveryMode) form.requestSubmit();
                    else submitPending = false;
                }, 120);
            }
        }

        input.addEventListener('input', normalizeAuthenticatorInput);
        input.addEventListener('focus', function () { paintSlots(input.value.replace(/\D/g, '').slice(0, 6)); });
        input.addEventListener('blur', function () { slots.forEach(function (slot) { slot.classList.remove('active'); }); });
        widget.addEventListener('click', function () { input.focus(); });

        if (toggle) {
            toggle.addEventListener('click', function () {
                recoveryMode = !recoveryMode;
                submitPending = false;
                if (submitTimer) window.clearTimeout(submitTimer);
                input.value = '';
                widget.classList.toggle('recovery-mode', recoveryMode);
                input.inputMode = recoveryMode ? 'text' : 'numeric';
                input.maxLength = recoveryMode ? 32 : 6;
                input.removeAttribute('pattern');
                if (label) label.textContent = recoveryMode ? 'Recovery code' : 'Authenticator code';
                toggle.textContent = recoveryMode ? 'Use an authenticator code instead' : 'Use a recovery code instead';
                if (status) status.textContent = '';
                paintSlots('');
                input.focus();
            });
        }

        paintSlots('');
    });
}());
</script>
</body>
</html>
