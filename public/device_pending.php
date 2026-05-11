<?php
/**
 * Device Pending Approval Page
 * Shown to a user who logged in with valid credentials from an unregistered
 * device while ENABLE_DEVICE_LOCK is on. The session is NOT established yet —
 * the user must wait for the admin to approve the device.
 *
 * The page polls api/device_status_check.php every 15 seconds. When status
 * flips to 'Active' it auto-redirects to login.php (so the user can submit
 * credentials again and complete login).
 */
require_once '../includes/session_config.php';
require_once '../includes/config.php';

$pendingId = $_SESSION['pending_device_id'] ?? 0;
$pendingFp = $_SESSION['pending_device_fp'] ?? '';

// If someone hits this page directly without a pending row in their session,
// bounce them to login.
if (!$pendingId) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awaiting Admin Approval - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', 'Inter', Arial, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pending-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            padding: 48px 40px;
            max-width: 560px;
            width: 100%;
            text-align: center;
        }

        .icon-wrap {
            width: 96px;
            height: 96px;
            background: #fff8e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            border: 3px solid #f6e05e;
            position: relative;
        }

        .icon-wrap svg {
            width: 48px;
            height: 48px;
            color: #d69e2e;
            animation: pulse 2.4s ease-in-out infinite;
        }

        .icon-wrap.approved {
            background: #f0fff4;
            border-color: #68d391;
        }
        .icon-wrap.approved svg {
            color: #38a169;
            animation: none;
        }

        .icon-wrap.rejected {
            background: #fff5f5;
            border-color: #fc8181;
        }
        .icon-wrap.rejected svg {
            color: #e53e3e;
            animation: none;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1);   opacity: 1;   }
            50%      { transform: scale(1.1); opacity: 0.7; }
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 1rem;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .status-line {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fffbeb;
            border: 1px solid #f6e05e;
            border-radius: 999px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #744210;
            margin-bottom: 28px;
        }
        .status-line .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #d69e2e;
            animation: blink 1.6s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

        .info-box {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 0;
        }
        .info-row:not(:last-child) { border-bottom: 1px solid #edf2f7; }
        .info-row .lbl { color: #718096; font-weight: 600; }
        .info-row .val {
            font-family: 'Courier New', monospace;
            color: #2d3748;
            font-size: 0.78rem;
            max-width: 60%;
            text-align: right;
            word-break: break-all;
        }

        .steps {
            text-align: left;
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-size: 0.88rem;
            color: #2c5282;
            line-height: 1.65;
        }
        .steps strong { color: #2a4365; }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 22px;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-primary {
            background: #3182ce;
            color: #fff;
        }
        .btn-primary:hover { background: #2b6cb0; }

        .countdown {
            font-size: 0.78rem;
            color: #a0aec0;
            margin-top: 14px;
        }
    </style>
</head>
<body>
    <div class="pending-card">

        <div class="icon-wrap" id="iconWrap">
            <svg id="statusIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>

        <h1 id="pageTitle">Awaiting Admin Approval</h1>
        <p class="subtitle" id="pageSubtitle">
            Your credentials are correct, but this device is not yet registered.<br>
            The administrator has been notified — please wait while they review your request.
        </p>

        <div class="status-line" id="statusLine">
            <span class="dot"></span>
            <span id="statusText">Waiting for approval&hellip;</span>
        </div>

        <div class="info-box">
            <div class="info-row">
                <span class="lbl">Request ID</span>
                <span class="val">#<?= (int) $pendingId ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Device fingerprint</span>
                <span class="val"><?= htmlspecialchars(substr($pendingFp, 0, 24)) ?>&hellip;</span>
            </div>
            <div class="info-row">
                <span class="lbl">Submitted</span>
                <span class="val"><?= date('M d, Y g:i A') ?></span>
            </div>
        </div>

        <div class="steps">
            <strong>What's happening:</strong><br>
            1. Your login was accepted.<br>
            2. We sent your device to the administrator for approval.<br>
            3. As soon as they approve it, this page will redirect you to the login screen.<br>
            4. If they reject it, you will see an error message.
        </div>

        <div class="actions">
            <a href="login.php" class="btn btn-secondary">← Back to Login</a>
            <button class="btn btn-primary" onclick="checkNow()" id="checkBtn">Check Status Now</button>
        </div>

        <div class="countdown" id="countdown">Next automatic check in 15s</div>
    </div>

    <script>
        const REQUEST_ID  = <?= (int) $pendingId ?>;
        const POLL_MS     = 15000;
        let secondsLeft   = 15;
        let pollHandle    = null;
        let tickerHandle  = null;

        async function checkNow() {
            const btn = document.getElementById('checkBtn');
            btn.disabled = true;
            btn.textContent = 'Checking…';

            try {
                const res = await fetch('../api/device_status_check.php?id=' + REQUEST_ID, {
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.status === 'Active') {
                    showApproved();
                } else if (data.status === 'Revoked') {
                    showRejected();
                } else {
                    // Still pending
                    document.getElementById('statusText').textContent = 'Still waiting for approval…';
                    btn.disabled = false;
                    btn.textContent = 'Check Status Now';
                    secondsLeft = 15;
                }
            } catch (e) {
                btn.disabled = false;
                btn.textContent = 'Check Status Now';
                document.getElementById('statusText').textContent = 'Could not reach server. Retrying…';
            }
        }

        function showApproved() {
            clearInterval(pollHandle);
            clearInterval(tickerHandle);

            const wrap = document.getElementById('iconWrap');
            wrap.classList.add('approved');
            document.getElementById('statusIcon').innerHTML =
                '<polyline points="20 6 9 17 4 12"/>';
            document.getElementById('pageTitle').textContent = 'Device Approved ✓';
            document.getElementById('pageSubtitle').innerHTML =
                'Your device has been approved by the administrator.<br>'
              + 'Redirecting you to the login page&hellip;';
            const statusLine = document.getElementById('statusLine');
            statusLine.style.background = '#f0fff4';
            statusLine.style.borderColor = '#68d391';
            statusLine.style.color = '#22543d';
            statusLine.querySelector('.dot').style.background = '#38a169';
            document.getElementById('statusText').textContent = 'Approved — redirecting…';
            document.getElementById('countdown').textContent = '';
            document.getElementById('checkBtn').style.display = 'none';

            setTimeout(() => { window.location.href = 'login.php'; }, 2500);
        }

        function showRejected() {
            clearInterval(pollHandle);
            clearInterval(tickerHandle);

            const wrap = document.getElementById('iconWrap');
            wrap.classList.add('rejected');
            document.getElementById('statusIcon').innerHTML =
                '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
            document.getElementById('pageTitle').textContent = 'Request Rejected';
            document.getElementById('pageSubtitle').innerHTML =
                'The administrator did not approve this device.<br>'
              + 'Please contact your administrator if you believe this is a mistake.';
            const statusLine = document.getElementById('statusLine');
            statusLine.style.background = '#fff5f5';
            statusLine.style.borderColor = '#fc8181';
            statusLine.style.color = '#742a2a';
            statusLine.querySelector('.dot').style.background = '#e53e3e';
            document.getElementById('statusText').textContent = 'Rejected';
            document.getElementById('countdown').textContent = '';
            document.getElementById('checkBtn').style.display = 'none';
        }

        function tick() {
            secondsLeft--;
            if (secondsLeft <= 0) {
                document.getElementById('countdown').textContent = 'Checking…';
                checkNow();
                secondsLeft = 15;
            } else {
                document.getElementById('countdown').textContent =
                    'Next automatic check in ' + secondsLeft + 's';
            }
        }

        // Start polling
        tickerHandle = setInterval(tick, 1000);
        // Do an immediate check on page load so admin-approved devices clear fast
        setTimeout(checkNow, 500);
    </script>
</body>
</html>
