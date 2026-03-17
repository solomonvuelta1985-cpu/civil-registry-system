<?php
/**
 * Device Authentication Helpers
 * iScan Civil Registry Records Management System
 *
 * Provides server-side functions for the Device Registration Security system.
 * Only devices whose browser fingerprint hash is in the registered_devices
 * table (with status = 'Active') are allowed to log in.
 *
 * Usage in login.php:
 *   require_once __DIR__ . '/device_auth.php';
 *   if (isDeviceLockEnabled()) {
 *       $fp = trim($_POST['device_fingerprint'] ?? '');
 *       if (!checkDeviceRegistered($fp)) { redirect to device_blocked.php; }
 *       updateDeviceLastSeen($fp, $_SERVER['REMOTE_ADDR']);
 *   }
 */

/**
 * Check if device lock is enabled via ENABLE_DEVICE_LOCK constant (.env).
 */
function isDeviceLockEnabled(): bool {
    if (!defined('ENABLE_DEVICE_LOCK')) return false;
    $val = ENABLE_DEVICE_LOCK;
    return $val === true || $val === 'true' || $val === '1' || $val === 1;
}

/**
 * Check if a device fingerprint hash is registered and active.
 *
 * @param  string      $hash  SHA-256 hex string from browser
 * @return array|false        Device row on success, false if not found/revoked
 */
function checkDeviceRegistered(string $hash): array|false {
    if (empty($hash) || strlen($hash) < 8) return false;

    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT id, device_name, status
               FROM registered_devices
              WHERE fingerprint_hash = :hash
                AND status = 'Active'
              LIMIT 1"
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch();
        return $row ?: false;
    } catch (PDOException $e) {
        error_log('Device check error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update last_seen_at and last_seen_ip for a registered device.
 *
 * @param string $hash SHA-256 fingerprint hash
 * @param string $ip   Client IP address
 */
function updateDeviceLastSeen(string $hash, string $ip): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "UPDATE registered_devices
                SET last_seen_at = NOW(),
                    last_seen_ip = :ip
              WHERE fingerprint_hash = :hash"
        );
        $stmt->execute([':hash' => $hash, ':ip' => $ip]);
    } catch (PDOException $e) {
        error_log('Device last_seen update error: ' . $e->getMessage());
    }
}

/**
 * Register a new device in the database.
 *
 * @param  string $hash    SHA-256 fingerprint hash from browser
 * @param  string $name    Human-readable device name (e.g. "Front Desk PC")
 * @param  int    $userId  ID of the admin registering the device
 * @param  string $notes   Optional notes
 * @return bool            True on success, false on failure (e.g. duplicate)
 */
function registerDevice(string $hash, string $name, int $userId, string $notes = ''): bool {
    if (empty($hash) || empty($name)) return false;

    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO registered_devices
                (fingerprint_hash, device_name, registered_by, notes, status)
             VALUES
                (:hash, :name, :uid, :notes, 'Active')"
        );
        $stmt->execute([
            ':hash'  => $hash,
            ':name'  => $name,
            ':uid'   => $userId,
            ':notes' => $notes,
        ]);
        return true;
    } catch (PDOException $e) {
        // Duplicate fingerprint (SQLSTATE 23000) is treated as already registered
        if (str_starts_with($e->getCode(), '23')) return false;
        error_log('Device register error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all registered devices (for admin listing).
 *
 * @return array
 */
function getAllDevices(): array {
    global $pdo;
    try {
        $stmt = $pdo->query(
            "SELECT d.*, u.full_name AS registered_by_name
               FROM registered_devices d
          LEFT JOIN users u ON u.id = d.registered_by
              ORDER BY d.registered_at DESC"
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get devices error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Revoke (soft-delete) a device by its ID.
 *
 * @param  int  $deviceId
 * @return bool
 */
function revokeDevice(int $deviceId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "UPDATE registered_devices SET status = 'Revoked' WHERE id = :id"
        );
        $stmt->execute([':id' => $deviceId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Device revoke error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Re-activate a previously revoked device.
 *
 * @param  int  $deviceId
 * @return bool
 */
function reactivateDevice(int $deviceId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "UPDATE registered_devices SET status = 'Active' WHERE id = :id"
        );
        $stmt->execute([':id' => $deviceId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Device reactivate error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Count total active registered devices.
 *
 * @return int
 */
function countActiveDevices(): int {
    global $pdo;
    try {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM registered_devices WHERE status = 'Active'"
        )->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
