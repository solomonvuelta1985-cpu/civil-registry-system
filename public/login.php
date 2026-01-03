<?php
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

// Handle login submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Authenticate user from database
        $user = authenticateUser($username, $password);

        if ($user) {
            // Set user session
            setUserSession($user);

            // Log the login activity
            logActivity('login', 'auth', $user['id'], 'User logged in');

            header('Location: ../admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Civil Registry Records Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            background-color: #ffffff;
            padding: 0;
            margin: 0;
        }

        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            overflow: hidden;
        }

        /* Left Panel - Brand Side */
        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #1d4ed8 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
            position: relative;
        }

        .particles-container {
            position: absolute;
            width: 220px;
            height: 220px;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particleFloat 3s ease-in-out infinite;
        }

        .particle:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 90%; top: 30%; animation-delay: 0.5s; }
        .particle:nth-child(3) { left: 20%; top: 80%; animation-delay: 1s; }
        .particle:nth-child(4) { left: 80%; top: 70%; animation-delay: 1.5s; }
        .particle:nth-child(5) { left: 50%; top: 5%; animation-delay: 2s; }
        .particle:nth-child(6) { left: 5%; top: 50%; animation-delay: 2.5s; }
        .particle:nth-child(7) { left: 95%; top: 50%; animation-delay: 0.3s; }
        .particle:nth-child(8) { left: 30%; top: 95%; animation-delay: 0.8s; }

        @keyframes particleFloat {
            0%, 100% {
                transform: translateY(0) scale(1);
                opacity: 0.5;
            }
            50% {
                transform: translateY(-10px) scale(1.5);
                opacity: 1;
            }
        }

        .logo-circle {
            width: 160px;
            height: 160px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            animation: logoPulse 3s ease-in-out infinite;
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            transition: transform 0.3s ease;
        }

        @keyframes logoPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            50% {
                transform: scale(1.03);
                box-shadow: 0 0 30px 10px rgba(255, 255, 255, 0.2);
            }
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-name {
            font-size: 28px;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            animation: fadeInUp 1s ease-out;
            text-align: center;
            line-height: 1.3;
        }

        .tagline {
            font-size: 14px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 0.3px;
            animation: fadeInUp 1s ease-out 0.2s backwards;
            text-align: center;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Right Panel - Login Form */
        .right-panel {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 80px;
            position: relative;
            overflow: hidden;
        }

        .hex-pattern {
            position: absolute;
            top: -20px;
            right: -40px;
            width: 350px;
            height: 350px;
            pointer-events: none;
            transition: transform 0.3s ease;
        }

        .hex-pattern svg {
            width: 100%;
            height: 100%;
        }

        .hex {
            stroke: #e5e7eb;
            stroke-width: 1;
            fill: none;
            transform-origin: center;
            animation: hexFloat 4s ease-in-out infinite;
        }

        .hex-1 { animation-delay: 0s; opacity: 0.3; }
        .hex-2 { animation-delay: 0.2s; opacity: 0.35; }
        .hex-3 { animation-delay: 0.4s; opacity: 0.4; }
        .hex-4 { animation-delay: 0.1s; opacity: 0.32; }
        .hex-5 { animation-delay: 0.3s; opacity: 0.38; }
        .hex-6 { animation-delay: 0.5s; opacity: 0.42; }
        .hex-7 { animation-delay: 0.6s; opacity: 0.45; }
        .hex-8 { animation-delay: 0.15s; opacity: 0.33; }
        .hex-9 { animation-delay: 0.35s; opacity: 0.37; }
        .hex-10 { animation-delay: 0.55s; opacity: 0.43; }
        .hex-11 { animation-delay: 0.7s; opacity: 0.48; }
        .hex-12 { animation-delay: 0.25s; opacity: 0.36; }
        .hex-13 { animation-delay: 0.45s; opacity: 0.41; }
        .hex-14 { animation-delay: 0.65s; opacity: 0.46; }
        .hex-15 { animation-delay: 0.5s; opacity: 0.4; }
        .hex-16 { animation-delay: 0.75s; opacity: 0.5; }

        @keyframes hexFloat {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-5px) scale(1.02);
            }
        }

        .hex-glow {
            animation: hexGlow 3s ease-in-out infinite alternate;
        }

        @keyframes hexGlow {
            0% {
                stroke: #e5e7eb;
                filter: drop-shadow(0 0 0 transparent);
            }
            100% {
                stroke: #c7d2fe;
                filter: drop-shadow(0 0 3px rgba(99, 102, 241, 0.3));
            }
        }

        .form-container {
            width: 100%;
            max-width: 340px;
            z-index: 1;
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            font-size: 15px;
            font-weight: 400;
            color: #9ca3af;
            text-align: center;
            margin-bottom: 40px;
        }

        .alert {
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #c62828;
            background: #ffebee;
            border-left: 3px solid #c62828;
            border-radius: 6px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group {
            position: relative;
            margin-bottom: 30px;
        }

        .input-icon {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-icon svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: #6b7280;
            stroke-width: 1.5;
            transition: stroke 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 12px 0 12px 30px;
            border: none;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #374151;
            background: transparent;
            transition: border-color 0.3s ease;
        }

        .form-input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .form-input:focus {
            outline: none;
            border-bottom-color: #3b82f6;
        }

        .form-input:focus + .input-icon svg {
            stroke: #3b82f6;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            background: #1a1a1a;
            color: #ffffff;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary:hover {
            background: #000000;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: #bbb;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
            }

            .left-panel {
                padding: 60px 40px;
                min-height: 300px;
            }

            .right-panel {
                padding: 50px 40px;
            }

            .logo-circle {
                width: 120px;
                height: 120px;
            }

            .brand-name {
                font-size: 24px;
            }

            .tagline {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .container {
                min-height: 100vh;
            }

            .right-panel {
                padding: 40px 30px;
            }

            .form-container {
                max-width: 100%;
            }

            .logo-circle {
                width: 100px;
                height: 100px;
            }

            .brand-name {
                font-size: 20px;
            }
        }

        /* Loading Animation */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Panel - Brand Section -->
        <div class="left-panel" id="leftPanel">
            <div class="logo-container">
                <div class="particles-container">
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                    <div class="particle"></div>
                </div>

                <div class="logo-circle" id="logoCircle">
                    <img src="../assets/img/LOGO1.png" alt="Baggao Logo">
                </div>
                <h1 class="brand-name">Civil Registry Records<br>Management System</h1>
                <p class="tagline">Lalawigan ng Cagayan - Bayan ng Baggao</p>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="right-panel" id="rightPanel">
            <div class="hex-pattern" id="hexPattern">
                <svg viewBox="0 0 350 350" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path class="hex hex-1" d="M180 30 L220 50 L220 90 L180 110 L140 90 L140 50 Z"/>
                    <path class="hex hex-2 hex-glow" d="M260 30 L300 50 L300 90 L260 110 L220 90 L220 50 Z"/>
                    <path class="hex hex-3" d="M340 30 L380 50 L380 90 L340 110 L300 90 L300 50 Z"/>
                    <path class="hex hex-4" d="M140 90 L180 110 L180 150 L140 170 L100 150 L100 110 Z"/>
                    <path class="hex hex-5 hex-glow" d="M220 90 L260 110 L260 150 L220 170 L180 150 L180 110 Z"/>
                    <path class="hex hex-6" d="M300 90 L340 110 L340 150 L300 170 L260 150 L260 110 Z"/>
                    <path class="hex hex-7" d="M380 90 L420 110 L420 150 L380 170 L340 150 L340 110 Z"/>
                    <path class="hex hex-8" d="M100 150 L140 170 L140 210 L100 230 L60 210 L60 170 Z"/>
                    <path class="hex hex-9" d="M180 150 L220 170 L220 210 L180 230 L140 210 L140 170 Z"/>
                    <path class="hex hex-10 hex-glow" d="M260 150 L300 170 L300 210 L260 230 L220 210 L220 170 Z"/>
                    <path class="hex hex-11" d="M340 150 L380 170 L380 210 L340 230 L300 210 L300 170 Z"/>
                    <path class="hex hex-12" d="M140 210 L180 230 L180 270 L140 290 L100 270 L100 230 Z"/>
                    <path class="hex hex-13 hex-glow" d="M220 210 L260 230 L260 270 L220 290 L180 270 L180 230 Z"/>
                    <path class="hex hex-14" d="M300 210 L340 230 L340 270 L300 290 L260 270 L260 230 Z"/>
                    <path class="hex hex-15" d="M180 270 L220 290 L220 330 L180 350 L140 330 L140 290 Z"/>
                    <path class="hex hex-16" d="M260 270 L300 290 L300 330 L260 350 L220 330 L220 290 Z"/>
                </svg>
            </div>

            <div class="form-container">
                <h2 class="welcome-title">Welcome Back</h2>
                <p class="welcome-subtitle">Please login to continue</p>

                <?php if ($error): ?>
                    <div class="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="input-group">
                        <input type="text" name="username" class="form-input" placeholder="Username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="7" r="4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>

                    <div class="input-group">
                        <input type="password" name="password" class="form-input" placeholder="Password" required>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="11" width="18" height="11" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>

                    <button type="submit" class="btn-primary" id="loginBtn">Sign In</button>
                </form>

                <div class="form-footer">
                    Civil Registry Records Management System
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function() {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner"></span> Signing In...';
        });

        // Mouse parallax effect on hexagons
        document.getElementById('rightPanel').addEventListener('mousemove', function(e) {
            const hexPattern = document.getElementById('hexPattern');
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            hexPattern.style.transform = `translate(${x * 15}px, ${y * 15}px)`;
        });

        document.getElementById('rightPanel').addEventListener('mouseleave', function() {
            const hexPattern = document.getElementById('hexPattern');
            hexPattern.style.transform = 'translate(0, 0)';
        });

        // Mouse parallax effect on logo
        document.getElementById('leftPanel').addEventListener('mousemove', function(e) {
            const logoCircle = document.getElementById('logoCircle');
            const rect = this.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            logoCircle.style.transform = `translate(${x * 10}px, ${y * 10}px)`;
        });

        document.getElementById('leftPanel').addEventListener('mouseleave', function() {
            const logoCircle = document.getElementById('logoCircle');
            logoCircle.style.transform = 'translate(0, 0)';
        });
    </script>
</body>
</html>
