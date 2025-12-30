<?php
/**
 * Error Handler and Logger
 * Centralized error handling and logging system for iScan
 *
 * Features:
 * - Custom error handler
 * - Exception handler
 * - Shutdown handler (fatal errors)
 * - Detailed error logging
 * - User-friendly error pages
 * - Email notifications for critical errors
 */

// Define log file path
define('ERROR_LOG_FILE', __DIR__ . '/../logs/php_errors.log');
define('ERROR_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ERROR_EMAIL', 'admin@iscan.local'); // Change to actual admin email
define('ENABLE_ERROR_EMAIL', false); // Set to true to enable email notifications

/**
 * Initialize error handling system
 */
function initializeErrorHandling() {
    // Create logs directory if it doesn't exist
    $logDir = dirname(ERROR_LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Set custom error handler
    set_error_handler('customErrorHandler');

    // Set custom exception handler
    set_exception_handler('customExceptionHandler');

    // Set shutdown handler for fatal errors
    register_shutdown_function('shutdownHandler');

    // Configure PHP error reporting
    if (isProduction()) {
        // Production: Log errors, don't display
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', ERROR_LOG_FILE);
    } else {
        // Development: Show all errors
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        ini_set('log_errors', 1);
        ini_set('error_log', ERROR_LOG_FILE);
    }
}

/**
 * Check if running in production environment
 */
function isProduction() {
    return !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
}

/**
 * Custom error handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't log errors suppressed with @
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorType = getErrorType($errno);

    // Build error message
    $message = sprintf(
        "[%s] %s: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $errorType,
        $errstr,
        $errfile,
        $errline
    );

    // Add context information
    $context = [
        'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'user_id' => $_SESSION['user_id'] ?? 'Guest',
        'post_data' => !empty($_POST) ? json_encode(sanitizePostData($_POST)) : 'None',
        'get_data' => !empty($_GET) ? json_encode($_GET) : 'None'
    ];

    // Log the error
    logError($message, $context, $errno);

    // Send email for critical errors
    if (shouldEmailError($errno)) {
        sendErrorEmail($message, $context, $errno);
    }

    // Don't execute PHP internal error handler
    return true;
}

/**
 * Custom exception handler
 */
function customExceptionHandler($exception) {
    $message = sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    $context = [
        'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_id' => $_SESSION['user_id'] ?? 'Guest',
        'exception_class' => get_class($exception)
    ];

    logError($message, $context, E_ERROR);

    // Send email for exceptions
    if (ENABLE_ERROR_EMAIL) {
        sendErrorEmail($message, $context, E_ERROR);
    }

    // Show user-friendly error page
    if (!isProduction()) {
        // Development: Show detailed error
        echo "<h1>Exception Occurred</h1>";
        echo "<pre>" . htmlspecialchars($message) . "</pre>";
    } else {
        // Production: Show generic error
        showErrorPage(500, "An unexpected error occurred. Please try again later.");
    }
}

/**
 * Shutdown handler for fatal errors
 */
function shutdownHandler() {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );

        $context = [
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'memory_usage' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit')
        ];

        logError($message, $context, $error['type']);

        if (ENABLE_ERROR_EMAIL) {
            sendErrorEmail($message, $context, $error['type']);
        }

        // Show error page
        if (!isProduction()) {
            echo "<h1>Fatal Error</h1>";
            echo "<pre>" . htmlspecialchars($message) . "</pre>";
        } else {
            showErrorPage(500, "A fatal error occurred. The issue has been logged.");
        }
    }
}

/**
 * Log error to file
 */
function logError($message, $context, $errno) {
    // Rotate log if too large
    if (file_exists(ERROR_LOG_FILE) && filesize(ERROR_LOG_FILE) > ERROR_LOG_MAX_SIZE) {
        rotateLogFile();
    }

    // Format log entry
    $logEntry = $message . "\n";
    $logEntry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
    $logEntry .= str_repeat('-', 80) . "\n";

    // Write to log file
    file_put_contents(ERROR_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);

    // Also log to syslog for critical errors
    if ($errno >= E_WARNING) {
        error_log($message, 0);
    }
}

/**
 * Rotate log file when it gets too large
 */
function rotateLogFile() {
    $archiveFile = str_replace('.log', '_' . date('Y-m-d_His') . '.log', ERROR_LOG_FILE);
    rename(ERROR_LOG_FILE, $archiveFile);

    // Keep only last 10 archive files
    $logDir = dirname(ERROR_LOG_FILE);
    $archives = glob($logDir . '/php_errors_*.log');

    if (count($archives) > 10) {
        // Sort by modification time
        usort($archives, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Delete oldest archives
        $toDelete = array_slice($archives, 0, count($archives) - 10);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }
}

/**
 * Send error email notification
 */
function sendErrorEmail($message, $context, $errno) {
    if (!ENABLE_ERROR_EMAIL || !filter_var(ERROR_EMAIL, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $subject = sprintf(
        '[iScan Alert] %s on %s',
        getErrorType($errno),
        $_SERVER['SERVER_NAME'] ?? 'Unknown Server'
    );

    $body = "An error has occurred in the iScan system:\n\n";
    $body .= $message . "\n\n";
    $body .= "Additional Context:\n";
    $body .= json_encode($context, JSON_PRETTY_PRINT) . "\n\n";
    $body .= "Please check the error logs for more details.\n";
    $body .= "Log file: " . ERROR_LOG_FILE;

    $headers = [
        'From: iScan Error Handler <noreply@iscan.local>',
        'Content-Type: text/plain; charset=UTF-8'
    ];

    mail(ERROR_EMAIL, $subject, $body, implode("\r\n", $headers));
}

/**
 * Get human-readable error type
 */
function getErrorType($errno) {
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    return $errorTypes[$errno] ?? 'Unknown Error';
}

/**
 * Determine if error should trigger email
 */
function shouldEmailError($errno) {
    // Only email critical errors
    return ENABLE_ERROR_EMAIL && in_array($errno, [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR
    ]);
}

/**
 * Sanitize POST data for logging (remove sensitive info)
 */
function sanitizePostData($data) {
    $sensitive = ['password', 'passwd', 'pwd', 'token', 'secret', 'api_key', 'access_token'];

    foreach ($data as $key => $value) {
        if (in_array(strtolower($key), $sensitive)) {
            $data[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $data[$key] = sanitizePostData($value);
        }
    }

    return $data;
}

/**
 * Show user-friendly error page
 */
function showErrorPage($code, $message) {
    http_response_code($code);

    $title = $code === 404 ? 'Page Not Found' : 'Error Occurred';

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . ' - iScan</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                color: #333;
            }
            .error-container {
                background: white;
                padding: 60px 40px;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 600px;
                margin: 20px;
            }
            .error-code {
                font-size: 6rem;
                font-weight: 700;
                color: #667eea;
                margin-bottom: 20px;
            }
            .error-title {
                font-size: 2rem;
                margin-bottom: 20px;
                color: #333;
            }
            .error-message {
                font-size: 1.1rem;
                color: #6c757d;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .error-actions {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .btn {
                padding: 12px 30px;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                transition: all 0.2s;
            }
            .btn-primary {
                background: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background: #5568d3;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            .btn-secondary:hover {
                background: #545b62;
                transform: translateY(-2px);
            }
            .error-ref {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
                font-size: 0.85rem;
                color: #adb5bd;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-code">' . $code . '</div>
            <h1 class="error-title">' . $title . '</h1>
            <p class="error-message">' . htmlspecialchars($message) . '</p>
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
                <a href="/iscan/public/" class="btn btn-primary">🏠 Home</a>
            </div>
            <div class="error-ref">
                Error Reference: ' . date('Y-m-d H:i:s') . ' | ' . uniqid('ERR-') . '
            </div>
        </div>
    </body>
    </html>';

    exit;
}

/**
 * Public function to log custom messages
 */
function logCustomError($message, $level = 'INFO') {
    $logEntry = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );

    file_put_contents(ERROR_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Public function to log database errors
 */
function logDatabaseError($query, $error, $params = []) {
    $message = sprintf(
        "[%s] DATABASE ERROR: %s\nQuery: %s\nParams: %s",
        date('Y-m-d H:i:s'),
        $error,
        $query,
        json_encode($params)
    );

    $context = [
        'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
        'user_id' => $_SESSION['user_id'] ?? 'Guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ];

    logError($message, $context, E_WARNING);
}

// Initialize error handling when this file is included
initializeErrorHandling();
