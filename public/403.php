<?php
/**
 * 403 Forbidden Error Page
 * Civil Registry Document Management System (CRDMS)
 */

require_once '../includes/session_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Civil Registry Document Management System (CRDMS)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            padding: 20px;
        }

        .error-container {
            text-align: center;
            max-width: 500px;
            background: #ffffff;
            padding: 60px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .error-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .error-icon svg {
            width: 50px;
            height: 50px;
            stroke: #dc2626;
            stroke-width: 1.5;
            fill: none;
        }

        .error-code {
            font-size: 72px;
            font-weight: 700;
            color: #dc2626;
            line-height: 1;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
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
            background: #3b82f6;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        @media (max-width: 480px) {
            .error-container {
                padding: 40px 25px;
            }

            .error-code {
                font-size: 56px;
            }

            .error-title {
                font-size: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke-linecap="round" stroke-linejoin="round"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <div class="error-code">403</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            You don't have permission to access this page. Please contact your administrator if you believe this is an error.
        </p>

        <div class="btn-group">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 19L5 12L12 5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Go Back
            </a>
            <a href="../admin/dashboard.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="9 22 9 12 15 12 15 22" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
