-- =====================================================================
-- Migration 019: NAS Production Catch-Up
-- =====================================================================
-- Brings the NAS database (frozen at ~Jan 5, 2026 schema) up to date
-- with all columns and permissions the current codebase expects.
--
-- Covers migrations: 004, 005 (fixed), 007, 008, 009, 010, 011, 012,
--                    013, 014, 015, 016 (partial DOB), 016 (backfill),
--                    017, 018
--
-- Safe to re-run: uses ADD COLUMN IF NOT EXISTS, INSERT IGNORE, etc.
--
-- Run on NAS via SSH:
--   mysql -u root -p iscan_db < /volume1/iscan/database/migrations/019_nas_catchup_all_missing_columns.sql
-- =====================================================================

-- ─────────────────────────────────────────────────────────────────────
-- CERTIFICATE OF LIVE BIRTH — missing columns
-- ─────────────────────────────────────────────────────────────────────

-- From migration 004: citizenship columns
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `mother_citizenship` VARCHAR(100) NULL AFTER `mother_last_name`,
    ADD COLUMN IF NOT EXISTS `father_citizenship` VARCHAR(100) NULL AFTER `father_last_name`;

-- place_type was never properly ADD'd in any migration (005 only did MODIFY)
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `place_type` VARCHAR(100) NULL AFTER `child_place_of_birth`;

-- From migration 005: barangay and time_of_birth
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `barangay` VARCHAR(255) NULL AFTER `place_type`,
    ADD COLUMN IF NOT EXISTS `time_of_birth` TIME NULL AFTER `child_date_of_birth`;

-- From add_birth_fields_migration: legitimacy_status
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `legitimacy_status` ENUM('Legitimate', 'Illegitimate') NULL AFTER `child_sex`;

-- From migration 007: pdf_hash
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `pdf_hash` CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF'
    AFTER `pdf_filepath`;

-- From migration 008: unique registry_no
-- Drop old index if exists, then add unique
ALTER TABLE `certificate_of_live_birth`
    DROP INDEX IF EXISTS `idx_registry_no`;
ALTER TABLE `certificate_of_live_birth`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_registry_no` (`registry_no`);

-- From migration 013: pdf_hash index
ALTER TABLE `certificate_of_live_birth`
    ADD INDEX IF NOT EXISTS `idx_pdf_hash` (`pdf_hash`);

-- From migration 014: partial date of registration
ALTER TABLE `certificate_of_live_birth`
    MODIFY COLUMN `date_of_registration` DATE NULL;
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;

-- From migration 016: partial child date of birth
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN IF NOT EXISTS `child_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `child_date_of_birth`,
    ADD COLUMN IF NOT EXISTS `child_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `child_date_of_birth_format`,
    ADD COLUMN IF NOT EXISTS `child_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `child_date_of_birth_partial_month`,
    ADD COLUMN IF NOT EXISTS `child_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `child_date_of_birth_partial_year`;


-- ─────────────────────────────────────────────────────────────────────
-- CERTIFICATE OF DEATH — missing columns
-- ─────────────────────────────────────────────────────────────────────

-- From migration 007: pdf_hash
ALTER TABLE `certificate_of_death`
    ADD COLUMN IF NOT EXISTS `pdf_hash` CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF'
    AFTER `pdf_filepath`;

-- From migration 008: unique registry_no
ALTER TABLE `certificate_of_death`
    DROP INDEX IF EXISTS `idx_registry_no`;
ALTER TABLE `certificate_of_death`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_registry_no` (`registry_no`);

-- From migration 011: make date_of_birth nullable
ALTER TABLE `certificate_of_death`
    MODIFY COLUMN `date_of_birth` DATE NULL;

-- From migration 012: add sex column
ALTER TABLE `certificate_of_death`
    ADD COLUMN IF NOT EXISTS `sex` ENUM('Male', 'Female') DEFAULT NULL AFTER `deceased_last_name`;

-- From migration 013: pdf_hash index
ALTER TABLE `certificate_of_death`
    ADD INDEX IF NOT EXISTS `idx_pdf_hash` (`pdf_hash`);

-- From migration 014: partial date of registration
ALTER TABLE `certificate_of_death`
    MODIFY COLUMN `date_of_registration` DATE NULL;
ALTER TABLE `certificate_of_death`
    ADD COLUMN IF NOT EXISTS `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;

-- From migration 016: partial date_of_birth + age_unit
ALTER TABLE `certificate_of_death`
    ADD COLUMN IF NOT EXISTS `date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_birth`,
    ADD COLUMN IF NOT EXISTS `date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_birth_format`,
    ADD COLUMN IF NOT EXISTS `date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_birth_partial_month`,
    ADD COLUMN IF NOT EXISTS `date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_birth_partial_year`,
    ADD COLUMN IF NOT EXISTS `age_unit`
        ENUM('years','months','days')
        NOT NULL DEFAULT 'years'
        AFTER `age`;


-- ─────────────────────────────────────────────────────────────────────
-- CERTIFICATE OF MARRIAGE — missing columns
-- ─────────────────────────────────────────────────────────────────────

-- From migration 007: pdf_hash
ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `pdf_hash` CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF'
    AFTER `pdf_filepath`;

-- From migration 008: unique registry_no
ALTER TABLE `certificate_of_marriage`
    DROP INDEX IF EXISTS `idx_registry_no`;
ALTER TABLE `certificate_of_marriage`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_registry_no` (`registry_no`);

-- From migration 011: make dates nullable
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `husband_date_of_birth` DATE NULL,
    MODIFY COLUMN `wife_date_of_birth` DATE NULL;

-- From migration 013: pdf_hash index
ALTER TABLE `certificate_of_marriage`
    ADD INDEX IF NOT EXISTS `idx_pdf_hash` (`pdf_hash`);

-- From migration 014: partial date of registration
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `date_of_registration` DATE NULL;
ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`,
    ADD COLUMN IF NOT EXISTS `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;

-- From migration 016: partial husband + wife DOB
ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `husband_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `husband_date_of_birth`,
    ADD COLUMN IF NOT EXISTS `husband_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `husband_date_of_birth_format`,
    ADD COLUMN IF NOT EXISTS `husband_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `husband_date_of_birth_partial_month`,
    ADD COLUMN IF NOT EXISTS `husband_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `husband_date_of_birth_partial_year`;

ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `wife_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `wife_date_of_birth`,
    ADD COLUMN IF NOT EXISTS `wife_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `wife_date_of_birth_format`,
    ADD COLUMN IF NOT EXISTS `wife_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `wife_date_of_birth_partial_month`,
    ADD COLUMN IF NOT EXISTS `wife_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `wife_date_of_birth_partial_year`;


-- ─────────────────────────────────────────────────────────────────────
-- APPLICATION FOR MARRIAGE LICENSE — missing columns
-- ─────────────────────────────────────────────────────────────────────

-- From migration 007: pdf_hash
ALTER TABLE `application_for_marriage_license`
    ADD COLUMN IF NOT EXISTS `pdf_hash` CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF'
    AFTER `pdf_filepath`;

-- From migration 008: unique registry_no
ALTER TABLE `application_for_marriage_license`
    DROP INDEX IF EXISTS `idx_registry_no`;
ALTER TABLE `application_for_marriage_license`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_registry_no` (`registry_no`);

-- From migration 013: pdf_hash index
ALTER TABLE `application_for_marriage_license`
    ADD INDEX IF NOT EXISTS `idx_pdf_hash` (`pdf_hash`);


-- ─────────────────────────────────────────────────────────────────────
-- REGISTERED DEVICES TABLE (from migration 006)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `registered_devices` (
    `id`               INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_name`      VARCHAR(100) NOT NULL,
    `fingerprint_hash` CHAR(64) NOT NULL,
    `registered_by`    INT(11) UNSIGNED NOT NULL,
    `registered_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`     TIMESTAMP NULL,
    `last_seen_ip`     VARCHAR(45) NULL,
    `status`           ENUM('Active', 'Revoked') DEFAULT 'Active',
    `notes`            TEXT NULL,
    UNIQUE KEY `uniq_fingerprint` (`fingerprint_hash`),
    INDEX `idx_status` (`status`),
    INDEX `idx_registered_by` (`registered_by`),
    INDEX `idx_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- PDF BACKUPS TABLE (from migration 007)
-- ─────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pdf_backups` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cert_type`     ENUM('birth','death','marriage','marriage_license') NOT NULL,
    `record_id`     INT UNSIGNED NOT NULL,
    `original_path` VARCHAR(255) NOT NULL,
    `backup_path`   VARCHAR(255) NOT NULL,
    `file_hash`     CHAR(64) NULL,
    `backed_up_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `backed_up_by`  INT UNSIGNED NULL,
    `restored_at`   TIMESTAMP NULL,
    `restored_by`   INT UNSIGNED NULL,
    INDEX `idx_record`    (`cert_type`, `record_id`),
    INDEX `idx_backed_up` (`backed_up_at`),
    INDEX `idx_restored`  (`restored_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
-- PERMISSIONS (from migrations 009, 010, 017, 018)
-- ─────────────────────────────────────────────────────────────────────

-- 009: Add archive permissions
INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('birth_archive',            'Archive birth records',                 'birth'),
    ('marriage_archive',         'Archive marriage records',              'marriage'),
    ('death_archive',            'Archive death records',                 'death'),
    ('marriage_license_archive', 'Archive marriage license applications', 'marriage_license');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions`
WHERE `name` IN ('birth_archive', 'marriage_archive', 'death_archive', 'marriage_license_archive');

-- 010: Remove dead *_delete permissions
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE p.name IN ('birth_delete', 'death_delete', 'marriage_delete', 'marriage_license_delete');

DELETE FROM `permissions`
WHERE `name` IN ('birth_delete', 'death_delete', 'marriage_delete', 'marriage_license_delete');

-- 017: Add marriage license view/create/edit permissions
INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('marriage_license_view',   'View marriage license applications',   'marriage_license'),
    ('marriage_license_create', 'Create marriage license applications', 'marriage_license'),
    ('marriage_license_edit',   'Edit marriage license applications',   'marriage_license');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', id FROM `permissions`
WHERE `name` IN ('marriage_license_view', 'marriage_license_create', 'marriage_license_edit');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', id FROM `permissions`
WHERE `name` IN ('marriage_license_view', 'marriage_license_create', 'marriage_license_edit');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Viewer', id FROM `permissions`
WHERE `name` = 'marriage_license_view';

-- 018: Restrict users module to Admin only
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE rp.role = 'Encoder' AND p.module = 'users';

DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE rp.role = 'Viewer' AND p.module = 'users';


-- ─────────────────────────────────────────────────────────────────────
-- BACKFILL created_by (from migration 016_backfill)
-- ─────────────────────────────────────────────────────────────────────

UPDATE certificate_of_live_birth c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Live Birth:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;

UPDATE certificate_of_marriage c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Marriage:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;

UPDATE certificate_of_death c
JOIN (
    SELECT
        SUBSTRING_INDEX(details, 'Registry No. ', -1) AS registry_no,
        user_id
    FROM activity_logs
    WHERE action = 'CREATE_CERTIFICATE'
      AND details LIKE 'Created Certificate of Death:%'
      AND user_id IS NOT NULL
) al ON al.registry_no = c.registry_no
SET c.created_by = al.user_id
WHERE c.created_by IS NULL;


-- ─────────────────────────────────────────────────────────────────────
-- DONE
-- ─────────────────────────────────────────────────────────────────────
SELECT 'Migration 019 completed successfully — NAS database is now up to date.' AS status;
