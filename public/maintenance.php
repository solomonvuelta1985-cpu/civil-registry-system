<?php
/**
 * Maintenance Page
 * Civil Registry Document Management System (CRDMS)
 *
 * Standalone page shown to non-admin users when Maintenance Mode is ON.
 * Does NOT include session_config.php / auth.php to avoid the maintenance
 * guard recursing on itself.
 */

require_once '../includes/config.php';
require_once '../includes/settings.php';

http_response_code(503);
header('Retry-After: 600');

$maintenance_active = (bool) get_setting('maintenance_mode', false);
$message = trim((string) get_setting('maintenance_message', 'The system is undergoing scheduled maintenance. Please try again shortly.'));
if ($message === '') {
    $message = 'The system is undergoing scheduled maintenance. Please try again shortly.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>System Maintenance - Civil Registry Document Management System (CRDMS)</title>
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 20px;
        }

        .container {
            text-align: center;
            max-width: 520px;
            background: #ffffff;
            padding: 56px 44px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid #fde68a;
        }

        .icon {
            width: 96px;
            height: 96px;
            margin: 0 auto 28px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: gentlePulse 2.4s ease-in-out infinite;
        }

        @keyframes gentlePulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.05); }
        }

        .icon svg {
            width: 48px;
            height: 48px;
            stroke: #b45309;
            stroke-width: 1.6;
            fill: none;
        }

        .title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 14px;
            font-weight: 500;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 22px;
        }

        .message {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.65;
            margin-bottom: 32px;
            white-space: pre-wrap;
        }

        .meta {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-family: inherit;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #1f2937;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #111827;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
        }

        .btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        @media (max-width: 480px) {
            .container { padding: 40px 28px; }
            .title { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <div class="subtitle">System Maintenance</div>
        <h1 class="title">We&rsquo;ll be right back</h1>

        <p class="message"><?= htmlspecialchars($message) ?></p>

        <p class="meta">This page checks for updates every 30 seconds.</p>

        <a href="login.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke-linecap="round" stroke-linejoin="round"/>
                <polyline points="10 17 15 12 10 7" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="15" y1="12" x2="3" y2="12" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Administrator Sign In
        </a>
    </div>

    <?php if (!$maintenance_active): ?>
        <script>
            // Maintenance was turned off — redirect immediately instead of waiting
            // for the 30-second meta refresh.
            window.location.replace('login.php');
        </script>
    <?php endif; ?>
</body>
</html>
