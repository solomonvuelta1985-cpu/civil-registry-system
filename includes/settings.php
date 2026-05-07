<?php
/**
 * System Settings helper.
 *
 * Lazily ensures the system_settings table exists and reads/writes typed
 * setting values. Safe to require_once on any page; bootstrap runs at most
 * once per request and falls back to defaults if the DB is unavailable.
 */

require_once __DIR__ . '/config.php';

/**
 * Idempotent table + seed bootstrap. Runs at most once per request.
 * Returns true if the table is usable, false on any DB error.
 */
function __settings_bootstrap(PDO $pdo): bool {
    static $initialized = null;
    if ($initialized !== null) {
        return $initialized;
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `system_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                `setting_value` TEXT NULL,
                `setting_type` ENUM('string','number','boolean','json') DEFAULT 'string',
                `category` VARCHAR(50) NULL,
                `description` TEXT NULL,
                `is_public` BOOLEAN DEFAULT FALSE,
                `updated_by` INT UNSIGNED NULL,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("
            INSERT IGNORE INTO `system_settings`
                (`setting_key`,`setting_value`,`setting_type`,`category`,`description`,`is_public`)
            VALUES
                ('ocr_enabled','true','boolean','OCR','Show the floating Scan Now badge and load OCR engine on certificate forms.',0)
        ");
        $initialized = true;
    } catch (PDOException $e) {
        error_log('Settings bootstrap failed: ' . $e->getMessage());
        $initialized = false;
    }
    return $initialized;
}

/**
 * Cast a stored string value into the PHP type matching $type.
 */
function __settings_cast(?string $value, string $type) {
    if ($value === null) {
        return null;
    }
    switch ($type) {
        case 'boolean':
            return $value === 'true' || $value === '1';
        case 'number':
            return is_numeric($value) ? $value + 0 : 0;
        case 'json':
            $decoded = json_decode($value, true);
            return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? null : $decoded;
        default:
            return $value;
    }
}

/**
 * Read a setting value. Returns $default if the key, table, or DB is missing.
 * Caches results per request.
 */
function get_setting(string $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $default;
    }
    if (!__settings_bootstrap($pdo)) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value, setting_type FROM system_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $cache[$key] = $default;
        }
        return $cache[$key] = __settings_cast($row['setting_value'], $row['setting_type'] ?: 'string');
    } catch (PDOException $e) {
        error_log("get_setting('$key') failed: " . $e->getMessage());
        return $default;
    }
}

/**
 * Write a setting value. The row must already exist (seeded by bootstrap or
 * an explicit INSERT). Returns true on success.
 *
 * Caller is responsible for auth and CSRF — this helper does not check either.
 */
function set_setting(PDO $pdo, string $key, $value, ?int $user_id): bool {
    if (!__settings_bootstrap($pdo)) {
        return false;
    }
    if ($value === true)  { $stored = 'true'; }
    elseif ($value === false) { $stored = 'false'; }
    elseif (is_array($value)) { $stored = json_encode($value); }
    else { $stored = (string)$value; }

    try {
        $stmt = $pdo->prepare('
            UPDATE system_settings
               SET setting_value = :v,
                   updated_by = :uid
             WHERE setting_key = :k
        ');
        $stmt->execute([
            ':v' => $stored,
            ':uid' => $user_id,
            ':k' => $key,
        ]);
        return $stmt->rowCount() >= 0;
    } catch (PDOException $e) {
        error_log("set_setting('$key') failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Read all settings grouped by category. Used by the admin settings page.
 */
function list_settings_by_category(): array {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return [];
    }
    if (!__settings_bootstrap($pdo)) {
        return [];
    }
    try {
        $stmt = $pdo->query('
            SELECT setting_key, setting_value, setting_type, category, description
              FROM system_settings
          ORDER BY category, setting_key
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $cat = $r['category'] ?: 'General';
            $out[$cat][] = $r;
        }
        return $out;
    } catch (PDOException $e) {
        error_log('list_settings_by_category failed: ' . $e->getMessage());
        return [];
    }
}
