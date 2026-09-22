-- Migration 036: CRF No. 3A marriage issuance records and history.

CREATE TABLE IF NOT EXISTS `crf_3a_sequences` (
    `issue_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crf_3a_issuances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `crf_year` SMALLINT UNSIGNED NOT NULL,
    `sequence_no` INT UNSIGNED NOT NULL,
    `crf_number` VARCHAR(32) NOT NULL,
    `marriage_record_id` INT(11) UNSIGNED NOT NULL,
    `replaces_issuance_id` BIGINT UNSIGNED NULL,
    `issuance_kind` VARCHAR(20) NOT NULL DEFAULT 'Original',

    `registry_no_snapshot` VARCHAR(100) NULL,
    `husband_name_snapshot` VARCHAR(305) NULL,
    `husband_last_name_snapshot` VARCHAR(100) NULL,
    `wife_name_snapshot` VARCHAR(305) NULL,
    `wife_last_name_snapshot` VARCHAR(100) NULL,
    `record_snapshot_json` LONGTEXT NOT NULL,

    `issue_date` DATE NOT NULL,
    `page_number` VARCHAR(50) NOT NULL,
    `book_number` VARCHAR(50) NOT NULL,
    `requester_name` VARCHAR(150) NULL,
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `or_number` VARCHAR(100) NOT NULL,
    `date_paid` DATE NOT NULL,
    `husband_nationality` VARCHAR(100) NOT NULL,
    `husband_civil_status` VARCHAR(50) NOT NULL,
    `husband_mother_nationality` VARCHAR(100) NOT NULL,
    `husband_father_nationality` VARCHAR(100) NOT NULL,
    `wife_nationality` VARCHAR(100) NOT NULL,
    `wife_civil_status` VARCHAR(50) NOT NULL,
    `wife_mother_nationality` VARCHAR(100) NOT NULL,
    `wife_father_nationality` VARCHAR(100) NOT NULL,
    `mcr_full_name` VARCHAR(150) NOT NULL,
    `mcr_title` VARCHAR(100) NOT NULL,
    `verified_by_name` VARCHAR(150) NOT NULL,
    `verified_by_position` VARCHAR(100) NOT NULL,

    `pdf_filename` VARCHAR(255) NOT NULL,
    `pdf_filepath` VARCHAR(500) NOT NULL,
    `pdf_hash` CHAR(64) NULL,
    `duplicate_key` CHAR(64) NULL,

    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) UNSIGNED NULL,
    `status` ENUM('Active', 'Archived', 'Deleted', 'Voided') NOT NULL DEFAULT 'Active',
    `archived_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,

    UNIQUE KEY `uniq_crf_3a_number` (`crf_number`),
    UNIQUE KEY `uniq_crf_3a_year_sequence` (`crf_year`, `sequence_no`),
    KEY `idx_crf_3a_marriage_record` (`marriage_record_id`),
    KEY `idx_crf_3a_registry` (`registry_no_snapshot`),
    KEY `idx_crf_3a_husband_last` (`husband_last_name_snapshot`),
    KEY `idx_crf_3a_wife_last` (`wife_last_name_snapshot`),
    KEY `idx_crf_3a_issue_date` (`issue_date`),
    KEY `idx_crf_3a_date_paid` (`date_paid`),
    KEY `idx_crf_3a_status` (`status`),
    KEY `idx_crf_3a_issuance_kind` (`issuance_kind`),
    KEY `idx_crf_3a_duplicate_key` (`duplicate_key`),
    KEY `idx_crf_3a_replaces_issuance` (`replaces_issuance_id`),
    KEY `idx_crf_3a_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crf_3a_issuance_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `issuance_id` BIGINT UNSIGNED NULL,
    `crf_number` VARCHAR(32) NULL,
    `action` VARCHAR(40) NOT NULL,
    `details` VARCHAR(500) NULL,
    `actor_id` INT(11) UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_crf_3a_history_issuance` (`issuance_id`),
    KEY `idx_crf_3a_history_created` (`created_at`),
    KEY `idx_crf_3a_history_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('marriage_crf_3a_view', 'View CRF No. 3A issuance records', 'marriage'),
    ('marriage_crf_3a_generate', 'Generate CRF No. 3A forms for marriage records', 'marriage');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions`
WHERE `name` IN ('marriage_crf_3a_view', 'marriage_crf_3a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', `id` FROM `permissions`
WHERE `name` IN ('marriage_crf_3a_view', 'marriage_crf_3a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Viewer', `id` FROM `permissions`
WHERE `name` = 'marriage_crf_3a_view';
