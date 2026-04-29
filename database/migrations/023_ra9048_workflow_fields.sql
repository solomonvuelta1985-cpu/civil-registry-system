-- Migration: 023_ra9048_workflow_fields
-- Adds petition workflow fields + 2 child tables for RA 9048 / RA 10172 document automation.
--
-- NOTE: Runs against iscan_db (RA 9048 tables share the main database now;
--       see migration 024). Originally targeted the separate iscan_ra9048_db.

USE `iscan_db`;

-- =====================================================
-- A) ALTER petitions: workflow + identity + posting/publication fields
-- =====================================================
ALTER TABLE `petitions`
  ADD COLUMN `petition_number` VARCHAR(30) NULL COMMENT 'Manually entered, e.g. CCE-0130-2025; prefix locked to petition_type' AFTER `id`,
  ADD COLUMN `petition_subtype` ENUM('CCE_minor','CCE_10172','CFN') NULL COMMENT 'CCE_minor=RA9048 only, CCE_10172=RA9048 as amended by RA10172, CFN=Change of First Name' AFTER `petition_type`,
  ADD COLUMN `petitioner_nationality` VARCHAR(100) NULL DEFAULT 'FILIPINO',
  ADD COLUMN `petitioner_address` VARCHAR(500) NULL,
  ADD COLUMN `petitioner_id_type` VARCHAR(100) NULL COMMENT 'e.g. NATIONAL ID, TAX IDENTIFICATION CARD',
  ADD COLUMN `petitioner_id_number` VARCHAR(100) NULL,
  ADD COLUMN `is_self_petition` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if petitioner is the document owner',
  ADD COLUMN `relation_to_owner` VARCHAR(100) NULL COMMENT 'e.g. DAUGHTER, FATHER, MOTHER',
  ADD COLUMN `owner_dob` DATE NULL,
  ADD COLUMN `owner_birthplace_city` VARCHAR(255) NULL,
  ADD COLUMN `owner_birthplace_province` VARCHAR(255) NULL,
  ADD COLUMN `owner_birthplace_country` VARCHAR(100) NULL DEFAULT 'PHILIPPINES',
  ADD COLUMN `registry_number` VARCHAR(100) NULL,
  ADD COLUMN `father_full_name` VARCHAR(255) NULL COMMENT 'On COLB; used in publication notice',
  ADD COLUMN `mother_full_name` VARCHAR(255) NULL COMMENT 'On COLB; used in publication notice',
  ADD COLUMN `cfn_ground` ENUM('difficult','habitual','ridicule','confusion') NULL COMMENT 'Ground for CFN petition',
  ADD COLUMN `cfn_ground_detail` TEXT NULL,
  ADD COLUMN `notarized_at` DATE NULL,
  ADD COLUMN `order_date` DATE NULL COMMENT 'Date of Order for Publication',
  ADD COLUMN `posting_start_date` DATE NULL,
  ADD COLUMN `posting_end_date` DATE NULL,
  ADD COLUMN `posting_location` VARCHAR(255) NULL DEFAULT 'MUNICIPAL HALL BULLETIN BOARD',
  ADD COLUMN `posting_cert_issued_at` DATE NULL,
  ADD COLUMN `publication_date_1` DATE NULL,
  ADD COLUMN `publication_date_2` DATE NULL,
  ADD COLUMN `publication_newspaper` VARCHAR(255) NULL,
  ADD COLUMN `publication_place` VARCHAR(255) NULL COMMENT 'e.g. Tuguegarao City, Cagayan',
  ADD COLUMN `opposition_deadline` DATE NULL COMMENT 'Auto: publication_date_2 + 1 day',
  ADD COLUMN `receipt_number` VARCHAR(100) NULL,
  ADD COLUMN `payment_date` DATE NULL,
  ADD COLUMN `certification_issued_at` DATE NULL,
  ADD COLUMN `decision_date` DATE NULL,
  ADD COLUMN `status_workflow` ENUM('Filed','Posted','Published','Decided','Endorsed') NULL DEFAULT 'Filed',
  ADD UNIQUE INDEX `idx_petition_number` (`petition_number`),
  ADD INDEX `idx_petition_subtype` (`petition_subtype`),
  ADD INDEX `idx_status_workflow` (`status_workflow`),
  ADD INDEX `idx_registry_number` (`registry_number`);

-- =====================================================
-- B) Child table: petition_corrections (FROM/TO grid rows)
-- =====================================================
CREATE TABLE IF NOT EXISTS `petition_corrections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `petition_id` INT UNSIGNED NOT NULL,
  `item_no` SMALLINT NOT NULL,
  `nature` ENUM('CFN','CCE') NOT NULL COMMENT 'CHANGE OF FIRST NAME or CORRECTION OF CLERICAL ERROR — drives publication slide grouping',
  `description` VARCHAR(255) NOT NULL COMMENT 'e.g. FATHER\'S FULL NAME, FIRST NAME, SEX, DATE OF BIRTH',
  `value_from` VARCHAR(500) NOT NULL,
  `value_to` VARCHAR(500) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_petition_id` (`petition_id`),
  CONSTRAINT `fk_corrections_petition`
    FOREIGN KEY (`petition_id`) REFERENCES `petitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- C) Child table: petition_supporting_docs (list of supporting documents)
-- =====================================================
CREATE TABLE IF NOT EXISTS `petition_supporting_docs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `petition_id` INT UNSIGNED NOT NULL,
  `item_no` SMALLINT NOT NULL,
  `doc_label` VARCHAR(255) NOT NULL COMMENT 'e.g. Police Clearance, NBI Clearance, Medical Certification',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_petition_id` (`petition_id`),
  CONSTRAINT `fk_supporting_docs_petition`
    FOREIGN KEY (`petition_id`) REFERENCES `petitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- D) Backfill petition_subtype for existing rows
--    Existing data only has petition_type (CCE / CFN). We map:
--      CFN → CFN
--      CCE → CCE_minor (assume legacy CCE rows are clerical-only;
--             admin can re-classify to CCE_10172 manually if needed)
-- =====================================================
UPDATE `petitions`
   SET `petition_subtype` = CASE
         WHEN `petition_type` = 'CFN' THEN 'CFN'
         WHEN `petition_type` = 'CCE' THEN 'CCE_minor'
         ELSE NULL
       END
 WHERE `petition_subtype` IS NULL;

-- =====================================================
-- E) Backfill status_workflow for existing rows
-- =====================================================
UPDATE `petitions`
   SET `status_workflow` = 'Filed'
 WHERE `status_workflow` IS NULL
   AND `status` = 'Active';
