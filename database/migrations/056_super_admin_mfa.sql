-- Migration 056: Super Admin role and mandatory authenticator MFA.
-- Safe for installations where require_2fa was added by the older security migration.

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `identifier` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_identifier` (`identifier`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    `user_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_severity` (`severity`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('Super Admin', 'Admin', 'Encoder', 'Viewer') DEFAULT 'Encoder',
    ADD COLUMN IF NOT EXISTS `require_2fa` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Require two-factor authentication',
    ADD COLUMN IF NOT EXISTS `mfa_secret_enc` TEXT NULL,
    ADD COLUMN IF NOT EXISTS `mfa_enabled_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `mfa_last_totp_step` BIGINT UNSIGNED NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `user_mfa_recovery_codes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_mfa_recovery_user_unused` (`user_id`, `used_at`),
    CONSTRAINT `fk_user_mfa_recovery_codes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
