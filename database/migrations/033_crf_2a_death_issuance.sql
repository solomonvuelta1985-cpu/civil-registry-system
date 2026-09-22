-- Migration 033: CRF No. 2A death certificate fields and issuance history.

ALTER TABLE `certificate_of_death`
    ADD COLUMN `civil_status` VARCHAR(50) NULL AFTER `sex`,
    ADD COLUMN `citizenship` VARCHAR(100) NULL AFTER `civil_status`,
    ADD COLUMN `cause_of_death` VARCHAR(500) NULL AFTER `place_of_death`;

CREATE TABLE IF NOT EXISTS `crf_2a_sequences` (
    `issue_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crf_2a_issuances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `crf_year` SMALLINT UNSIGNED NOT NULL,
    `sequence_no` INT UNSIGNED NOT NULL,
    `crf_number` VARCHAR(32) NOT NULL,
    `death_record_id` INT(11) UNSIGNED NOT NULL,
    `replaces_issuance_id` BIGINT UNSIGNED NULL,

    `registry_no_snapshot` VARCHAR(100) NULL,
    `deceased_name_snapshot` VARCHAR(305) NULL,
    `deceased_last_name_snapshot` VARCHAR(100) NULL,
    `record_snapshot_json` LONGTEXT NOT NULL,

    `issue_date` DATE NOT NULL,
    `page_number` VARCHAR(50) NOT NULL,
    `book_number` VARCHAR(50) NOT NULL,
    `requester_name` VARCHAR(150) NULL,
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `or_number` VARCHAR(100) NOT NULL,
    `date_paid` DATE NOT NULL,
    `mcr_full_name` VARCHAR(150) NOT NULL,
    `mcr_title` VARCHAR(100) NOT NULL,
    `certified_by_name` VARCHAR(150) NOT NULL,
    `certified_by_position` VARCHAR(100) NOT NULL,

    `pdf_filename` VARCHAR(255) NOT NULL,
    `pdf_filepath` VARCHAR(500) NOT NULL,
    `pdf_hash` CHAR(64) NULL,

    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) UNSIGNED NULL,
    `status` ENUM('Active', 'Archived', 'Deleted', 'Voided') NOT NULL DEFAULT 'Active',
    `archived_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,

    UNIQUE KEY `uniq_crf_2a_number` (`crf_number`),
    UNIQUE KEY `uniq_crf_2a_year_sequence` (`crf_year`, `sequence_no`),
    KEY `idx_crf_2a_death_record` (`death_record_id`),
    KEY `idx_crf_2a_registry` (`registry_no_snapshot`),
    KEY `idx_crf_2a_deceased_last` (`deceased_last_name_snapshot`),
    KEY `idx_crf_2a_issue_date` (`issue_date`),
    KEY `idx_crf_2a_date_paid` (`date_paid`),
    KEY `idx_crf_2a_status` (`status`),
    KEY `idx_crf_2a_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('death_crf_2a_view', 'View CRF No. 2A issuance records', 'death'),
    ('death_crf_2a_generate', 'Generate CRF No. 2A forms for death records', 'death');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions`
WHERE `name` IN ('death_crf_2a_view', 'death_crf_2a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', `id` FROM `permissions`
WHERE `name` IN ('death_crf_2a_view', 'death_crf_2a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Viewer', `id` FROM `permissions`
WHERE `name` = 'death_crf_2a_view';
