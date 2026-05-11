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

/**
 * Get a single device row by fingerprint hash (any status).
 * Used by login.php to decide between Active/Pending/Revoked/new.
 *
 * @param  string      $hash
 * @return array|false
 */
function getDeviceByFingerprint(string $hash): array|false {
    if (empty($hash) || strlen($hash) < 8) return false;

    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT id, device_name, status, requested_by, requested_at
               FROM registered_devices
              WHERE fingerprint_hash = :hash
              LIMIT 1"
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch();
        return $row ?: false;
    } catch (PDOException $e) {
        error_log('getDeviceByFingerprint error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a Pending device row when a user logs in (with valid credentials)
 * from an unregistered device while Device Lock is enabled.
 *
 * @param  string $hash    SHA-256 fingerprint hash
 * @param  int    $userId  ID of the user requesting access (the encoder)
 * @param  string $ip      Client IP
 * @param  string $userAgent Optional UA string for naming the row
 * @return int|false       Row id on success, false on failure / duplicate
 */
function requestDeviceApproval(string $hash, int $userId, string $ip, string $userAgent = ''): int|false {
    if (empty($hash) || strlen($hash) < 8) return false;

    global $pdo;
    try {
        // Auto-generate a placeholder device name so admin can identify it.
        // Format: "Pending: <username> <short_ua> <date>"
        $username = '';
        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $username = (string) $stmt->fetchColumn();
        } catch (PDOException $e) { /* ignore */ }

        $uaShort = '';
        if (!empty($userAgent)) {
            if (stripos($userAgent, 'Edg/') !== false)         $uaShort = 'Edge';
            elseif (stripos($userAgent, 'Chrome/') !== false)  $uaShort = 'Chrome';
            elseif (stripos($userAgent, 'Firefox/') !== false) $uaShort = 'Firefox';
            elseif (stripos($userAgent, 'Safari/') !== false)  $uaShort = 'Safari';
            else                                                $uaShort = 'Browser';
        }
        // Placeholder name shown in the Pending Approval section. Kept short
        // and prefix-free so it reads sensibly once the device is approved
        // (admin can still rename in the approval input).
        $deviceName = trim($username . ' (' . $uaShort . ') ' . date('M d'));
        if (strlen($deviceName) > 100) $deviceName = substr($deviceName, 0, 100);

        $stmt = $pdo->prepare(
            "INSERT INTO registered_devices
                (fingerprint_hash, device_name, registered_by, requested_by, requested_at, request_ip, status)
             VALUES
                (:hash, :name, NULL, :uid, NOW(), :ip, 'Pending')"
        );
        $stmt->execute([
            ':hash' => $hash,
            ':name' => $deviceName,
            ':uid'  => $userId,
            ':ip'   => $ip,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        // Duplicate fingerprint (SQLSTATE 23000) means a row already exists —
        // the row is either already Pending (request again is a no-op) or
        // Active/Revoked (shouldn't reach this path). Caller decides.
        if (str_starts_with($e->getCode(), '23')) return false;
        error_log('requestDeviceApproval error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Approve a Pending device — flips its status to Active and records the
 * approving admin in registered_by.
 *
 * @param  int $deviceId
 * @param  int $adminUserId
 * @return bool
 */
function approveDevice(int $deviceId, int $adminUserId): bool {
    global $pdo;
    try {
        // Flip to Active + record approving admin. Also strip any legacy
        // "Pending:" prefix from the device_name so the row reads cleanly
        // in the registry after approval. Admin's own rename (via the
        // approval API's new_name field) takes precedence and runs separately.
        $stmt = $pdo->prepare(
            "UPDATE registered_devices
                SET status = 'Active',
                    registered_by = :uid,
                    device_name = TRIM(REGEXP_REPLACE(device_name, '^Pending:\\\\s*', ''))
              WHERE id = :id AND status = 'Pending'"
        );
        $stmt->execute([':id' => $deviceId, ':uid' => $adminUserId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('approveDevice error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Reject a Pending device — flips its status to Revoked.
 *
 * @param  int $deviceId
 * @param  int $adminUserId
 * @return bool
 */
function rejectDevice(int $deviceId, int $adminUserId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "UPDATE registered_devices
                SET status = 'Revoked', registered_by = :uid
              WHERE id = :id AND status = 'Pending'"
        );
        $stmt->execute([':id' => $deviceId, ':uid' => $adminUserId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('rejectDevice error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Count devices that are awaiting admin approval.
 *
 * @return int
 */
function countPendingDevices(): int {
    global $pdo;
    try {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM registered_devices WHERE status = 'Pending'"
        )->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
