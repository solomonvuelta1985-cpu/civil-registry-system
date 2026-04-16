-- ============================================
-- Activity Logs Migration
-- ============================================
-- Purpose:
--   1. Ensure the `activity_logs` table exists (for fresh installs).
--   2. Add the `ip_address` column if the table already exists without it
--      (the original database_schema.sql shipped without this column, but
--      includes/functions.php log_activity() writes to it).
--
-- Safe to run multiple times.
-- Requires MariaDB 10.0.2+ or MySQL 8.0.1+ for IF NOT EXISTS on ADD COLUMN.
-- Run in phpMyAdmin: select the database, go to SQL tab, paste and Go.
-- ============================================

-- Step 1: Create the table if it doesn't exist (fresh install path)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Patch existing installs that predate the ip_address column
ALTER TABLE activity_logs
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER details;

ALTER TABLE activity_logs
    ADD INDEX IF NOT EXISTS idx_ip_address (ip_address);

-- Step 3: Verification — run this manually to confirm the schema is correct:
--   DESCRIBE activity_logs;
--   Expected columns: id, user_id, action, details, ip_address, created_at
