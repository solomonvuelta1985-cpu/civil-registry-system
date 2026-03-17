-- ============================================================
-- Migration 006: Registered Devices (Device Lock Security)
-- iScan Civil Registry Records Management System
-- ============================================================
-- Purpose: Stores browser/device fingerprints that are allowed
--          to access the system. Unauthorized devices are hard-
--          blocked at login, even with valid credentials.
--
-- Run this:
--   mysql -u root -p iscan_db < 006_registered_devices.sql
-- ============================================================

USE iscan_db;

CREATE TABLE IF NOT EXISTS registered_devices (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Human-readable label (e.g. "Front Desk PC", "Encoder Station 2")
    device_name VARCHAR(100) NOT NULL,

    -- SHA-256 hex hash of browser fingerprint (64 chars)
    fingerprint_hash CHAR(64) NOT NULL,

    -- Admin who registered this device
    registered_by INT(11) UNSIGNED NOT NULL,

    -- Timestamps
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NULL,
    last_seen_ip VARCHAR(45) NULL,

    -- Active = allowed, Revoked = blocked
    status ENUM('Active', 'Revoked') DEFAULT 'Active',

    -- Optional notes (e.g. "Room 3 desktop, Windows 11")
    notes TEXT NULL,

    -- Constraints
    UNIQUE KEY uniq_fingerprint (fingerprint_hash),
    INDEX idx_status (status),
    INDEX idx_registered_by (registered_by),
    INDEX idx_last_seen (last_seen_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registered browser/device fingerprints for device-lock security';
