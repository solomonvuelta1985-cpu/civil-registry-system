<?php
/**
 * Device Blocked Page
 * Shown when a device's fingerprint is not in the registered_devices table.
 * No login form is displayed — this is a hard block.
 */
require_once '../includes/session_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Not Authorized - Civil Registry System</title>
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

        .block-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            padding: 48px 40px;
            max-width: 540px;
            width: 100%;
            text-align: center;
        }

        .icon-wrap {
            width: 88px;
            height: 88px;
            background: #fff0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            border: 3px solid #ffcccc;
        }

        .icon-wrap svg {
            width: 44px;
            height: 44px;
            color: #e53e3e;
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 1rem;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .device-id-section {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 28px;
        }

        .device-id-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .device-id-value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.8rem;
            color: #2d3748;
            word-break: break-all;
            background: #edf2f7;
            padding: 10px 14px;
            border-radius: 6px;
            min-height: 36px;
            border: 1px solid #cbd5e0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .device-id-value:hover { background: #e2e8f0; }

        .copy-btn {
            margin-top: 10px;
            padding: 8px 20px;
            background: #4a5568;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover { background: #2d3748; }
        .copy-btn.copied { background: #38a169; }

        .steps {
            text-align: left;
            background: #fffbeb;
            border: 1px solid #f6e05e;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }

        .steps-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #744210;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .steps ol {
            padding-left: 18px;
            color: #744210;
            font-size: 0.88rem;
            line-height: 1.8;
        }

        .back-link {
            display: inline-block;
            color: #3182ce;
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #2b6cb0; text-decoration: underline; }

        .loading-text {
            color: #a0aec0;
            font-size: 0.8rem;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="block-card">

        <!-- Red shield icon -->
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>

        <h1>Device Not Authorized</h1>
        <p class="subtitle">
            This device is <strong>not registered</strong> to access the
            Civil Registry Records Management System.<br>
            Contact your system administrator for access.
        </p>

        <!-- Device ID display -->
        <div class="device-id-section">
            <div class="device-id-label">Your Device ID (show this to the administrator)</div>
            <div class="device-id-value" id="deviceIdDisplay" title="Click to copy">
                <span class="loading-text">Generating device ID...</span>
            </div>
            <button class="copy-btn" id="copyBtn" onclick="copyDeviceId()">Copy Device ID</button>
        </div>

        <!-- Instructions -->
        <div class="steps">
            <div class="steps-title">How to get access:</div>
            <ol>
                <li>Copy your Device ID above</li>
                <li>Show or send it to your System Administrator</li>
                <li>Ask them to register it in <strong>Admin &gt; Devices</strong></li>
                <li>Reload this page and try logging in again</li>
            </ol>
        </div>

        <a href="login.php" class="back-link">← Back to Login Page</a>
    </div>

    <script src="../assets/js/device-fingerprint.js"></script>
    <script>
        async function initPage() {
            const display = document.getElementById('deviceIdDisplay');
            try {
                const hash = await window.DeviceFingerprint.get();
                display.textContent = hash;
            } catch (e) {
                display.textContent = 'Unable to generate device ID. Please enable JavaScript.';
            }
        }

        function copyDeviceId() {
            const text = document.getElementById('deviceIdDisplay').textContent;
            if (!text || text.includes('Generating') || text.includes('Unable')) return;

            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copy Device ID';
                    btn.classList.remove('copied');
                }, 2500);
            }).catch(() => {
                // Fallback for older browsers
                const el = document.createElement('textarea');
                el.value = text;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
            });
        }

        initPage();
    </script>
</body>
</html>
