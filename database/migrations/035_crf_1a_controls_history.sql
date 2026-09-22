-- Migration 035: CRF No. 1A issuance type, duplicate fingerprint, and audit history.

ALTER TABLE `crf_1a_issuances`
    ADD COLUMN `replaces_issuance_id` BIGINT UNSIGNED NULL AFTER `birth_record_id`,
    ADD COLUMN `issuance_kind` VARCHAR(20) NOT NULL DEFAULT 'Original' AFTER `birth_record_id`,
    ADD COLUMN `duplicate_key` CHAR(64) NULL AFTER `pdf_hash`,
    ADD KEY `idx_crf_1a_replaces_issuance` (`replaces_issuance_id`),
    ADD KEY `idx_crf_1a_issuance_kind` (`issuance_kind`),
    ADD KEY `idx_crf_1a_duplicate_key` (`duplicate_key`);

CREATE TABLE IF NOT EXISTS `crf_1a_issuance_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `issuance_id` BIGINT UNSIGNED NULL,
    `crf_number` VARCHAR(32) NULL,
    `action` VARCHAR(40) NOT NULL,
    `details` VARCHAR(500) NULL,
    `actor_id` INT(11) UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_crf_1a_history_issuance` (`issuance_id`),
    KEY `idx_crf_1a_history_created` (`created_at`),
    KEY `idx_crf_1a_history_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `crf_1a_issuance_history` (`issuance_id`, `crf_number`, `action`, `details`, `actor_id`, `created_at`)
SELECT c.`id`, c.`crf_number`, 'generated', 'Historical issuance recorded when CRF 1A audit history was enabled.', c.`created_by`, c.`created_at`
FROM `crf_1a_issuances` c
LEFT JOIN `crf_1a_issuance_history` h ON h.`issuance_id` = c.`id`
WHERE h.`id` IS NULL;
