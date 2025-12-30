<?php
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: certificate_of_live_birth.php');
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
        // Simple authentication (replace with your actual authentication logic)
        // For now, using default admin credentials
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = 'admin';
            $_SESSION['full_name'] = 'Admin User';

            header('Location: certificate_of_live_birth.php');
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

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0d6efd;
            --primary-hover: #0b5ed7;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--gray-100);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--gray-900);
        }

        .login-container {
            width: 100%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07), 0 10px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            min-height: 650px;
        }

        /* Left Panel - Information */
        .info-panel {
            background: #051f3a;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--white);
            position: relative;
        }

        .info-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)" /></svg>');
            opacity: 0.5;
        }

        .logo-section {
            position: relative;
            z-index: 1;
            margin-bottom: 40px;
        }

        .logo-container {
            width: 90px;
            height: 90px;
            background: var(--white);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            margin-bottom: 24px;
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .system-name {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 12px;
            color: var(--white);
        }

        .system-subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 400;
            line-height: 1.5;
        }

        .features-list {
            position: relative;
            z-index: 1;
            margin-top: 50px;
            list-style: none;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon i {
            color: rgba(255, 255, 255, 0.95);
        }

        .feature-content h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--white);
        }

        .feature-content p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.4;
        }

        /* Right Panel - Login Form */
        .form-panel {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .form-header p {
            font-size: 0.9375rem;
            color: var(--gray-600);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.5;
            border: 1px solid;
        }

        .alert-danger {
            background-color: #fff5f5;
            color: #c53030;
            border-color: #feb2b2;
        }

        .alert-danger i {
            color: #fc8181;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert-info {
            background-color: #f0f9ff;
            color: #075985;
            border-color: #bae6fd;
        }

        .alert-info i {
            color: #0ea5e9;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .login-form {
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.9375rem;
            border: 1.5px solid var(--gray-300);
            border-radius: 10px;
            background-color: var(--white);
            color: var(--gray-900);
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control:hover {
            border-color: var(--gray-400);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .form-control::placeholder {
            color: var(--gray-500);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .password-wrapper .form-control {
            padding-right: 48px;
        }

        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background-color: var(--gray-400);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
        }

        .default-creds {
            padding: 16px;
            background-color: var(--gray-50);
            border-radius: 10px;
            border: 1px solid var(--gray-200);
        }

        .default-creds p {
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        .default-creds strong {
            color: var(--danger);
            font-weight: 600;
        }

        .default-creds small {
            font-size: 0.8125rem;
            color: var(--gray-600);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--gray-300);
        }

        .divider::before {
            margin-right: 12px;
        }

        .divider::after {
            margin-left: 12px;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .info-panel {
                display: none;
            }

            .form-panel {
                padding: 40px 30px;
            }

            .form-header h1 {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .form-panel {
                padding: 32px 24px;
            }

            .form-header h1 {
                font-size: 1.5rem;
            }

            .form-control {
                padding: 11px 14px;
                font-size: 0.875rem;
            }

            .btn {
                padding: 12px 20px;
                font-size: 0.9375rem;
            }
        }

        /* Loading Spinner */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Information -->
        <div class="info-panel">
            <div class="logo-section">
                <div class="logo-container">
                    <img src="../assets/img/LOGO1.png" alt="Baggao Logo">
                </div>
                <h2 class="system-name">Civil Registry Records Management System</h2>
                <p class="system-subtitle">Lalawigan ng Cagayan - Bayan ng Baggao</p>
            </div>

            <ul class="features-list">
                <li class="feature-item">
                    <div class="feature-icon">
                        <i data-lucide="file-check" width="20" height="20"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Digital Records</h4>
                        <p>Manage birth, marriage, and death certificates digitally</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">
                        <i data-lucide="shield-check" width="20" height="20"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Secure & Reliable</h4>
                        <p>Protected with industry-standard security measures</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">
                        <i data-lucide="zap" width="20" height="20"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Fast Processing</h4>
                        <p>Quick certificate issuance and record retrieval</p>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="form-panel">
            <div class="form-header">
                <h1>Welcome Back</h1>
                <p>Please sign in to access the system</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" width="20" height="20"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="Enter your username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i data-lucide="eye" width="20" height="20"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <i data-lucide="log-in" width="20" height="20"></i>
                    Sign In
                </button>
            </form>

            <div class="divider">OR</div>

            <div class="form-footer">
                <div class="default-creds">
                    <p>Default Credentials: <strong>admin / admin123</strong></p>
                    <small>Please change your password after first login</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;

            const icon = this.querySelector('i');
            icon.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
            lucide.createIcons();
        });

        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function() {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i data-lucide="loader-2" width="20" height="20" class="spinner"></i> Signing In...';
            lucide.createIcons();
        });
    </script>
</body>
</html>
