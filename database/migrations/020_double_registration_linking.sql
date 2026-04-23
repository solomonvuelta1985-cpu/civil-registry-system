-- Migration: 020_double_registration_linking
-- Double Registration Detection & Blocking (PSA MC 2019-23)
-- Creates record_links table and link permissions

CREATE TABLE IF NOT EXISTS `record_links` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- 1st Registration (earlier — the one to be issued)
  `primary_certificate_type` ENUM('birth','marriage','death') NOT NULL,
  `primary_certificate_id` INT UNSIGNED NOT NULL,

  -- 2nd Registration (later — blocked from issuance)
  `duplicate_certificate_type` ENUM('birth','marriage','death') NOT NULL,
  `duplicate_certificate_id` INT UNSIGNED NOT NULL,

  -- Link metadata
  `link_type` ENUM('double_registration') NOT NULL DEFAULT 'double_registration',
  `link_reason` TEXT NULL,
  `match_fields` JSON NULL COMMENT 'Fields that matched during detection',
  `match_score` DECIMAL(5,2) NULL COMMENT 'Similarity score 0-100',

  -- Discrepancy tracking (for cases where 1st registration has errors)
  `has_discrepancies` TINYINT(1) NOT NULL DEFAULT 0,
  `discrepancies` JSON NULL COMMENT 'Array of {field, primary_value, duplicate_value}',
  `needs_correction` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1st Reg has errors needing RA 9048',
  `correction_status` ENUM('none','pending','filed','completed') NOT NULL DEFAULT 'none',
  `correction_notes` TEXT NULL COMMENT 'RA 9048 petition tracking notes',

  -- Link Status
  `status` ENUM('active','unlinked') NOT NULL DEFAULT 'active',
  `unlinked_reason` TEXT NULL,
  `unlinked_by` INT UNSIGNED NULL,
  `unlinked_at` DATETIME NULL,

  -- Audit
  `linked_by` INT UNSIGNED NOT NULL,
  `linked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `uniq_active_link` (`primary_certificate_type`, `primary_certificate_id`,
    `duplicate_certificate_type`, `duplicate_certificate_id`, `status`),
  INDEX `idx_primary` (`primary_certificate_type`, `primary_certificate_id`),
  INDEX `idx_duplicate` (`duplicate_certificate_type`, `duplicate_certificate_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_needs_correction` (`needs_correction`),
  INDEX `idx_correction_status` (`correction_status`),
  FOREIGN KEY (`linked_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`unlinked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks double registration identification per PSA MC 2019-23';

-- Add link permissions for each certificate type
INSERT IGNORE INTO `permissions` (`name`, `description`) VALUES
  ('birth_link', 'Can link/unlink double-registered birth certificates'),
  ('marriage_link', 'Can link/unlink double-registered marriage certificates'),
  ('death_link', 'Can link/unlink double-registered death certificates');

-- Grant link permissions to Admin and Encoder roles
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions` WHERE `name` IN ('birth_link', 'marriage_link', 'death_link');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', `id` FROM `permissions` WHERE `name` IN ('birth_link', 'marriage_link', 'death_link');
