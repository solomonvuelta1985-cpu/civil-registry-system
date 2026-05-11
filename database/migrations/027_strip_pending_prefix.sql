-- ============================================================
-- Migration 027: Strip legacy "Pending:" prefix from device names
-- iScan Civil Registry Records Management System
-- ============================================================
-- Purpose: Cleans up rows that were created by an earlier version of
--          requestDeviceApproval() which prefixed device_name with
--          "Pending: " for the admin's benefit. Once the device is
--          approved (status='Active') the prefix is misleading.
--
--          From this point onward, requestDeviceApproval() no longer
--          adds the prefix, and approveDevice() will strip it if it
--          slipped through. This migration just back-fills existing
--          rows so they read cleanly.
--
-- Run this:
--   mysql -u root -p iscan_db < 027_strip_pending_prefix.sql
-- ============================================================

USE iscan_db;

UPDATE registered_devices
   SET device_name = TRIM(REGEXP_REPLACE(device_name, '^Pending:\\s*', ''))
 WHERE device_name LIKE 'Pending:%';
