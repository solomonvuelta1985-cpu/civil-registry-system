<?php
/**
 * Security Headers
 * Implements security headers to protect against common web vulnerabilities
 */

/**
 * Set security headers
 * Call this at the start of every page
 */
function setSecurityHeaders($options = []) {
    // Prevent clickjacking
    if (!isset($options['disable_frame_protection'])) {
        header('X-Frame-Options: SAMEORIGIN');
    }

    // XSS Protection (for older browsers)
    header('X-XSS-Protection: 1; mode=block');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy
    if (!isset($options['disable_csp'])) {
        $csp = buildContentSecurityPolicy($options['csp'] ?? []);
        header("Content-Security-Policy: {$csp}");
    }

    // HTTPS Strict Transport Security (only if on HTTPS)
    if (isHTTPS() && !isset($options['disable_hsts'])) {
        // 1 year HSTS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Permissions Policy (formerly Feature-Policy)
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/**
 * Build Content Security Policy header
 */
function buildContentSecurityPolicy($custom = []) {
    $defaults = [
        'default-src' => ["'self'"],
        'script-src' => [
            "'self'", "'unsafe-inline'",
            "https://fonts.googleapis.com",
            "https://cdnjs.cloudflare.com",
            "https://cdn.jsdelivr.net",
            "https://unpkg.com",
        ],
        'style-src' => [
            "'self'", "'unsafe-inline'",
            "https://fonts.googleapis.com",
            "https://cdnjs.cloudflare.com",
            "https://cdn.jsdelivr.net",
        ],
        'font-src' => [
            "'self'",
            "https://fonts.gstatic.com",
            "https://cdnjs.cloudflare.com",
        ],
        'img-src' => ["'self'", "data:", "https:"],
        'connect-src' => ["'self'", "https://unpkg.com"],
        'worker-src' => ["'self'", "blob:", "https://cdnjs.cloudflare.com"],
        'frame-ancestors' => ["'self'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"]
    ];

    // Merge custom policies with defaults
    $policies = array_merge($defaults, $custom);

    // Build CSP string
    $csp_parts = [];
    foreach ($policies as $directive => $sources) {
        $csp_parts[] = $directive . ' ' . implode(' ', $sources);
    }

    return implode('; ', $csp_parts);
}

/**
 * Check if connection is HTTPS
 */
function isHTTPS() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $_SERVER['SERVER_PORT'] == 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Enforce HTTPS (redirect if not on HTTPS)
 */
function enforceHTTPS() {
    if (!isHTTPS() && php_sapi_name() !== 'cli') {
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: {$redirect_url}", true, 301);
        exit;
    }
}

/**
 * Set secure cookie
 */
function setSecureCookie($name, $value, $expire = 0, $path = '/', $domain = '', $httponly = true) {
    $secure = isHTTPS(); // Only set secure flag if on HTTPS
    $samesite = 'Strict'; // Or 'Lax' for less strict

    if (PHP_VERSION_ID >= 70300) {
        // PHP 7.3+ supports samesite in options array
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    } else {
        // Older PHP versions - use path workaround for samesite
        setcookie($name, $value, $expire, "{$path}; SameSite={$samesite}", $domain, $secure, $httponly);
    }
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Sanitize filename for upload
 */
function sanitizeFilename($filename) {
    // Remove any path information
    $filename = basename($filename);

    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

    // Prevent double extensions
    $filename = preg_replace('/\.+/', '.', $filename);

    return $filename;
}

/**
 * Check if IP is in private range (for localhost detection)
 */
function isPrivateIP($ip) {
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/**
 * Check if running on localhost
 */
function isLocalhost() {
    $server_addr = $_SERVER['SERVER_ADDR'] ?? 'unknown';
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    return in_array($server_addr, ['127.0.0.1', '::1'])
        || in_array($remote_addr, ['127.0.0.1', '::1'])
        || $server_addr === 'localhost'
        || isPrivateIP($remote_addr);
}
