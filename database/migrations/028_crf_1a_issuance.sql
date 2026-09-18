-- Migration 028: CRF No. 1A issuance records
-- Stores immutable Civil Registry Form No. 1A generations and their files.

CREATE TABLE IF NOT EXISTS `crf_1a_sequences` (
    `issue_year` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crf_1a_issuances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `crf_year` SMALLINT UNSIGNED NOT NULL,
    `sequence_no` INT UNSIGNED NOT NULL,
    `crf_number` VARCHAR(32) NOT NULL,
    `birth_record_id` INT(11) UNSIGNED NOT NULL,

    -- Snapshot fields keep an issued form stable even if the source record changes.
    `registry_no_snapshot` VARCHAR(100) NULL,
    `child_name_snapshot` VARCHAR(305) NULL,
    `child_last_name_snapshot` VARCHAR(100) NULL,
    `record_snapshot_json` LONGTEXT NOT NULL,

    -- Issuance inputs
    `issue_date` DATE NOT NULL,
    `page_number` VARCHAR(50) NOT NULL,
    `book_number` VARCHAR(50) NOT NULL,
    `population_reference_no` VARCHAR(100) NULL,
    `requester_name` VARCHAR(150) NULL,
    `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `or_number` VARCHAR(100) NOT NULL,
    `date_paid` DATE NOT NULL,
    `certified_by_name` VARCHAR(150) NULL,
    `certified_by_position` VARCHAR(100) NULL,

    -- Generated file metadata
    `pdf_filename` VARCHAR(255) NOT NULL,
    `pdf_filepath` VARCHAR(500) NOT NULL,
    `pdf_hash` CHAR(64) NULL,

    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) UNSIGNED NULL,
    `status` ENUM('Active', 'Voided') NOT NULL DEFAULT 'Active',

    UNIQUE KEY `uniq_crf_1a_number` (`crf_number`),
    UNIQUE KEY `uniq_crf_1a_year_sequence` (`crf_year`, `sequence_no`),
    KEY `idx_crf_1a_birth_record` (`birth_record_id`),
    KEY `idx_crf_1a_registry` (`registry_no_snapshot`),
    KEY `idx_crf_1a_child_last` (`child_last_name_snapshot`),
    KEY `idx_crf_1a_issue_date` (`issue_date`),
    KEY `idx_crf_1a_date_paid` (`date_paid`),
    KEY `idx_crf_1a_status` (`status`),
    KEY `idx_crf_1a_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('birth_crf_1a_view', 'View CRF No. 1A issuance records', 'birth'),
    ('birth_crf_1a_generate', 'Generate CRF No. 1A forms for birth records', 'birth');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions`
WHERE `name` IN ('birth_crf_1a_view', 'birth_crf_1a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', `id` FROM `permissions`
WHERE `name` IN ('birth_crf_1a_view', 'birth_crf_1a_generate');

INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Viewer', `id` FROM `permissions`
WHERE `name` = 'birth_crf_1a_view';
