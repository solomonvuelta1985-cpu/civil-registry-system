<?php
/**
 * Security Helper Functions
 * CSRF Protection, Rate Limiting, Security Logging
 */

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF Token Input Field (for forms)
 */
function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Get CSRF Token Meta Tag (for AJAX requests)
 */
function csrfTokenMeta() {
    $token = generateCSRFToken();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Require CSRF Token (call at the start of POST handlers)
 */
function requireCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
            } else {
                die('CSRF token validation failed. Please refresh the page and try again.');
            }
            exit;
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Rate Limiting
 * Prevents brute-force attacks by limiting request frequency
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
    global $pdo;

    try {
        // Clean old entries (older than time window)
        $cleanup_sql = "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL :time_window SECOND)";
        $cleanup_stmt = $pdo->prepare($cleanup_sql);
        $cleanup_stmt->execute([':time_window' => $time_window]);

        // Count attempts in time window
        $count_sql = "SELECT COUNT(*) as attempts FROM rate_limits
                      WHERE identifier = :identifier
                      AND created_at >= DATE_SUB(NOW(), INTERVAL :time_window SECOND)";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([
            ':identifier' => $identifier,
            ':time_window' => $time_window
        ]);
        $result = $count_stmt->fetch();

        if ($result['attempts'] >= $max_attempts) {
            // Get the oldest attempt time to calculate lockout remaining
            $oldest_sql = "SELECT created_at FROM rate_limits
                          WHERE identifier = :identifier
                          ORDER BY created_at ASC LIMIT 1";
            $oldest_stmt = $pdo->prepare($oldest_sql);
            $oldest_stmt->execute([':identifier' => $identifier]);
            $oldest = $oldest_stmt->fetch();

            if ($oldest) {
                $lockout_expires = strtotime($oldest['created_at']) + $time_window;
                $remaining_seconds = $lockout_expires - time();
                $remaining_minutes = ceil($remaining_seconds / 60);

                return [
                    'allowed' => false,
                    'remaining_time' => $remaining_minutes,
                    'message' => "Too many attempts. Please try again in {$remaining_minutes} minute(s)."
                ];
            }
        }

        // Record this attempt
        $insert_sql = "INSERT INTO rate_limits (identifier, ip_address, created_at) VALUES (:identifier, :ip_address, NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            ':identifier' => $identifier,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return ['allowed' => true];

    } catch (PDOException $e) {
        // If rate limiting fails, allow the request (fail open)
        error_log("Rate Limiting Error: " . $e->getMessage());
        return ['allowed' => true];
    }
}

/**
 * Clear rate limit for identifier (e.g., after successful login)
 */
function clearRateLimit($identifier) {
    global $pdo;

    try {
        $sql = "DELETE FROM rate_limits WHERE identifier = :identifier";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':identifier' => $identifier]);
    } catch (PDOException $e) {
        error_log("Clear Rate Limit Error: " . $e->getMessage());
    }
}

/**
 * Security Event Logging
 * Logs security-related events separate from activity logs
 */
function logSecurityEvent($event_type, $severity, $details, $user_id = null) {
    global $pdo;

    try {
        $sql = "INSERT INTO security_logs (event_type, severity, user_id, ip_address, user_agent, details, created_at)
                VALUES (:event_type, :severity, :user_id, :ip_address, :user_agent, :details, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':event_type' => $event_type,
            ':severity' => $severity,
            ':user_id' => $user_id,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ':details' => $details
        ]);

        // Also log to error log for critical events
        if ($severity === 'CRITICAL' || $severity === 'HIGH') {
            error_log("SECURITY [{$severity}] {$event_type}: {$details}");
        }

    } catch (PDOException $e) {
        error_log("Security Logging Error: " . $e->getMessage());
    }
}

/**
 * Enhanced Input Validation
 */
function validateInput($data, $type, $options = []) {
    $data = trim($data);

    switch ($type) {
        case 'username':
            // Alphanumeric, underscore, 3-50 chars
            if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data)) {
                return ['valid' => false, 'error' => 'Username must be 3-50 alphanumeric characters'];
            }
            return ['valid' => true, 'value' => $data];

        case 'email':
            if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'error' => 'Invalid email format'];
            }
            return ['valid' => true, 'value' => strtolower($data)];

        case 'password':
            $min_length = $options['min_length'] ?? 8;
            if (strlen($data) < $min_length) {
                return ['valid' => false, 'error' => "Password must be at least {$min_length} characters"];
            }
            // Check for common weak passwords
            $weak_passwords = ['password', '12345678', 'admin123', 'qwerty123'];
            if (in_array(strtolower($data), $weak_passwords)) {
                return ['valid' => false, 'error' => 'Password is too common. Please choose a stronger password'];
            }
            return ['valid' => true, 'value' => $data];

        case 'integer':
            if (!filter_var($data, FILTER_VALIDATE_INT)) {
                return ['valid' => false, 'error' => 'Must be a valid integer'];
            }
            $value = (int)$data;
            if (isset($options['min']) && $value < $options['min']) {
                return ['valid' => false, 'error' => "Value must be at least {$options['min']}"];
            }
            if (isset($options['max']) && $value > $options['max']) {
                return ['valid' => false, 'error' => "Value must not exceed {$options['max']}"];
            }
            return ['valid' => true, 'value' => $value];

        case 'date':
            $format = $options['format'] ?? 'Y-m-d';
            $d = DateTime::createFromFormat($format, $data);
            if (!$d || $d->format($format) !== $data) {
                return ['valid' => false, 'error' => 'Invalid date format'];
            }
            return ['valid' => true, 'value' => $data];

        case 'enum':
            if (!isset($options['allowed']) || !in_array($data, $options['allowed'], true)) {
                return ['valid' => false, 'error' => 'Invalid value'];
            }
            return ['valid' => true, 'value' => $data];

        default:
            return ['valid' => true, 'value' => sanitize_input($data)];
    }
}

/**
 * Detect suspicious activity patterns
 */
function detectSuspiciousActivity() {
    global $pdo;

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    try {
        // Check for multiple failed login attempts
        $sql = "SELECT COUNT(*) as failed_attempts FROM security_logs
                WHERE event_type = 'LOGIN_FAILED'
                AND ip_address = :ip_address
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ip_address' => $ip_address]);
        $result = $stmt->fetch();

        if ($result['failed_attempts'] >= 10) {
            logSecurityEvent('SUSPICIOUS_ACTIVITY', 'HIGH', "Multiple failed login attempts from IP: {$ip_address}");
            return true;
        }

        return false;

    } catch (PDOException $e) {
        error_log("Suspicious Activity Detection Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check password strength
 */
function checkPasswordStrength($password) {
    $strength = 0;
    $feedback = [];

    // Length check
    if (strlen($password) >= 8) {
        $strength += 1;
    } else {
        $feedback[] = 'Use at least 8 characters';
    }

    if (strlen($password) >= 12) {
        $strength += 1;
    }

    // Complexity checks
    if (preg_match('/[a-z]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Include lowercase letters';
    }

    if (preg_match('/[A-Z]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Include uppercase letters';
    }

    if (preg_match('/[0-9]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Include numbers';
    }

    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $strength += 1;
    } else {
        $feedback[] = 'Include special characters';
    }

    // Determine strength level
    if ($strength <= 2) {
        $level = 'weak';
    } elseif ($strength <= 4) {
        $level = 'medium';
    } else {
        $level = 'strong';
    }

    return [
        'score' => $strength,
        'level' => $level,
        'feedback' => $feedback
    ];
}
