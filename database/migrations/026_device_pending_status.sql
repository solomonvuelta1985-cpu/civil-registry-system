-- ============================================================
-- Migration 026: Add 'Pending' status to registered_devices
-- iScan Civil Registry Records Management System
-- ============================================================
-- Purpose: Enables device-approval workflow. When ENABLE_DEVICE_LOCK
--          is true and a user logs in from an unregistered device,
--          the system creates a row with status='Pending' and the
--          admin must approve it before the user can enter.
--
-- Backwards-compatible: existing 'Active' and 'Revoked' values
-- remain unchanged.
--
-- Run this:
--   mysql -u root -p iscan_db < 026_device_pending_status.sql
-- ============================================================

USE iscan_db;

ALTER TABLE registered_devices
  MODIFY COLUMN status ENUM('Active', 'Pending', 'Revoked') NOT NULL DEFAULT 'Active';

-- Track WHO requested approval (encoder/staff who hit the login screen on the
-- new device). Nullable because existing rows were registered by an admin and
-- this column does not apply to them.
ALTER TABLE registered_devices
  ADD COLUMN IF NOT EXISTS requested_by INT(11) UNSIGNED NULL AFTER registered_by,
  ADD COLUMN IF NOT EXISTS requested_at TIMESTAMP NULL AFTER requested_by,
  ADD COLUMN IF NOT EXISTS request_ip   VARCHAR(45)     NULL AFTER requested_at,
  ADD INDEX IF NOT EXISTS idx_requested_by (requested_by);

-- Allow registered_by to be NULL for self-requested devices (admin has not
-- approved yet, so no admin owns this row).
ALTER TABLE registered_devices
  MODIFY COLUMN registered_by INT(11) UNSIGNED NULL;
