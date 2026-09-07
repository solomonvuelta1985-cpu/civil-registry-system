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
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return is_string($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
 * Read a request body once and make it available to CSRF validation and the
 * endpoint parser. This preserves JSON bodies when the token is included in
 * the JSON payload instead of the header.
 */
function requestBody() {
    if (!array_key_exists('_iscan_raw_request_body', $GLOBALS)) {
        $GLOBALS['_iscan_raw_request_body'] = file_get_contents('php://input');
    }
    return $GLOBALS['_iscan_raw_request_body'];
}

/**
 * Require CSRF Token (call at the start of POST handlers)
 */
function requireCSRFToken() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if ($token === null && in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            $raw = requestBody();
            $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
            if (strpos($contentType, 'application/json') !== false && is_string($raw) && $raw !== '') {
                $json = json_decode($raw, true);
                $token = is_array($json) ? ($json['csrf_token'] ?? null) : null;
            }
        }

        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF token validation failed'], JSON_UNESCAPED_SLASHES);
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
        $identifier = substr(hash('sha256', (string)$identifier), 0, 64);
        $max_attempts = max(1, min((int)$max_attempts, 100));
        $time_window = max(1, min((int)$time_window, 86400));
        // Clean old entries (older than time window)
        $cleanup_sql = "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL {$time_window} SECOND)";
        $cleanup_stmt = $pdo->prepare($cleanup_sql);
        $cleanup_stmt->execute();

        // Count attempts in time window
        $count_sql = "SELECT COUNT(*) as attempts FROM rate_limits
                      WHERE identifier = :identifier
                      AND created_at >= DATE_SUB(NOW(), INTERVAL {$time_window} SECOND)";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([':identifier' => $identifier]);
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
        // A database failure must not disable brute-force protection.
        error_log("Rate Limiting Error: " . $e->getMessage());
        return ['allowed' => false, 'remaining_time' => 1,
            'message' => 'Login protection is temporarily unavailable. Please try again shortly.'];
    }
}

/**
 * Clear rate limit for identifier (e.g., after successful login)
 */
function clearRateLimit($identifier) {
    global $pdo;

    try {
        $identifier = substr(hash('sha256', (string)$identifier), 0, 64);
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

    // Older callers in this codebase passed (event, severity, user_id, details).
    // Normalize that legacy order while keeping the public signature safe.
    if ((is_int($details) || (is_string($details) && ctype_digit($details))) && (is_array($user_id) || is_object($user_id))) {
        $legacyUser = (int)$details;
        $details = $user_id;
        $user_id = $legacyUser;
    }
    if ((is_int($details) || (is_string($details) && ctype_digit($details))) && is_string($user_id) && !ctype_digit($user_id)) {
        $legacyUser = (int)$details;
        $details = $user_id;
        $user_id = $legacyUser;
    }
    if ($details === null && is_string($user_id) && !ctype_digit($user_id)) {
        $details = $user_id;
        $user_id = null;
    }
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
            ':details' => is_array($details) ? json_encode($details) : $details
        ]);

        // Also log to error log for critical events
        if ($severity === 'CRITICAL' || $severity === 'HIGH') {
            $details_str = is_array($details) ? json_encode($details) : $details;
            error_log("SECURITY [{$severity}] {$event_type}: {$details_str}");
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
