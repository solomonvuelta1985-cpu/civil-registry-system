<?php
/**
 * Environment Configuration Loader
 * Loads configuration from .env file if it exists
 * Falls back to default values for localhost development
 */

/**
 * Load environment variables from .env file
 */
function loadEnv($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }

    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            // Set as environment variable and make it available via getenv()
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }

    return true;
}

/**
 * Get environment variable with fallback
 */
function env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    // Convert string booleans to actual booleans
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'null':
        case '(null)':
            return null;
        case 'empty':
        case '(empty)':
            return '';
    }

    return $value;
}

/**
 * Check if running in production environment
 */
function isProduction() {
    return env('APP_ENV', 'development') === 'production';
}

/**
 * Check if running in development environment
 */
function isDevelopment() {
    return env('APP_ENV', 'development') === 'development';
}

// Load .env file from project root
$env_file = __DIR__ . '/../.env';
loadEnv($env_file);
