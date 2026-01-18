-- Security Tables Migration
-- Add rate limiting and security logging capabilities

-- Rate Limits Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_identifier` (`identifier`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security Logs Table
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(100) NOT NULL,
  `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
  `user_id` INT NULL,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `details` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_severity` (`severity`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Timeout Configuration (add to existing users table)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `session_timeout` INT DEFAULT 3600 COMMENT 'Session timeout in seconds (default 1 hour)',
  ADD COLUMN IF NOT EXISTS `require_2fa` BOOLEAN DEFAULT FALSE COMMENT 'Require two-factor authentication',
  ADD COLUMN IF NOT EXISTS `last_password_change` TIMESTAMP NULL COMMENT 'When password was last changed',
  ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT DEFAULT 0 COMMENT 'Track failed login attempts',
  ADD COLUMN IF NOT EXISTS `account_locked_until` TIMESTAMP NULL COMMENT 'Account lockout timestamp';

-- Update existing users to set last password change to created_at
UPDATE `users` SET `last_password_change` = `created_at` WHERE `last_password_change` IS NULL;
