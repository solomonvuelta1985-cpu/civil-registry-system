-- Migration: 021_ra9048_database
-- RA 9048/10172 Civil Registry Transactions Module
-- Creates separate database iscan_ra9048_db with 3 tables:
--   petitions, legal_instruments, court_decrees
--
-- NOTE: This migration creates a SEPARATE database.
--       Run this directly in MySQL/phpMyAdmin, not via run_migrations.php.

CREATE DATABASE IF NOT EXISTS `iscan_ra9048_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `iscan_ra9048_db`;

-- =====================================================
-- Table 1: petitions (CCE / CFN under RA 9048 / 10172)
-- =====================================================
CREATE TABLE IF NOT EXISTS `petitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `petition_type` ENUM('CCE','CFN') NOT NULL COMMENT 'CCE=Correction of Clerical Error, CFN=Change of First Name',
  `date_of_filing` DATE NOT NULL,
  `document_owner_names` VARCHAR(500) NOT NULL COMMENT 'Document Owner/s',
  `petitioner_names` VARCHAR(500) NOT NULL COMMENT 'Name of Petitioner/s',
  `document_type` ENUM('COLB','COM','COD') NOT NULL COMMENT 'COLB=Certificate of Live Birth, COM=Certificate of Marriage, COD=Certificate of Death',
  `petition_of` VARCHAR(500) NULL COMMENT 'Petition of what correction',
  `special_law` VARCHAR(255) NULL COMMENT 'Applicable law (e.g. RA 9048, RA 10172)',
  `fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '1000 for CCE, 3000 for CFN',
  `remarks` TEXT NULL,
  `pdf_filename` VARCHAR(255) NULL,
  `pdf_filepath` VARCHAR(500) NULL,
  `pdf_hash` CHAR(64) NULL,
  `status` ENUM('Active','Deleted') NOT NULL DEFAULT 'Active',
  `created_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `updated_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_petition_type` (`petition_type`),
  INDEX `idx_date_of_filing` (`date_of_filing`),
  INDEX `idx_document_type` (`document_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_document_owner` (`document_owner_names`(100)),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 2: legal_instruments (AUSF / Supplemental / Legitimation)
-- =====================================================
CREATE TABLE IF NOT EXISTS `legal_instruments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `instrument_type` ENUM('AUSF','Supplemental','Legitimation') NOT NULL COMMENT 'AUSF=Affidavit to Use Surname of Father',
  `date_of_filing` DATE NOT NULL,
  `document_owner_names` VARCHAR(500) NOT NULL COMMENT 'Document Owner/s (child/person)',
  `father_name` VARCHAR(500) NULL COMMENT 'For AUSF and Legitimation',
  `mother_name` VARCHAR(500) NULL,
  `affiant_names` VARCHAR(500) NULL COMMENT 'Affiant/s (person executing affidavit)',
  `document_type` ENUM('COLB','COM','COD') NULL,
  `registry_number` VARCHAR(100) NULL,
  `supplemental_info` TEXT NULL COMMENT 'What was omitted (Supplemental only)',
  `legitimation_date` DATE NULL COMMENT 'Date parents married (Legitimation only)',
  `applicable_law` VARCHAR(255) NULL COMMENT 'e.g. RA 9255 for AUSF',
  `remarks` TEXT NULL,
  `pdf_filename` VARCHAR(255) NULL,
  `pdf_filepath` VARCHAR(500) NULL,
  `pdf_hash` CHAR(64) NULL,
  `status` ENUM('Active','Deleted') NOT NULL DEFAULT 'Active',
  `created_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `updated_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_instrument_type` (`instrument_type`),
  INDEX `idx_date_of_filing` (`date_of_filing`),
  INDEX `idx_status` (`status`),
  INDEX `idx_document_owner` (`document_owner_names`(100)),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table 3: court_decrees
-- =====================================================
CREATE TABLE IF NOT EXISTS `court_decrees` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `decree_type` ENUM('Adoption','Annulment','Legal Separation','Correction of Entry','Naturalization','Recognition','Other') NOT NULL,
  `decree_type_other` VARCHAR(255) NULL COMMENT 'When decree_type = Other',
  `court_region` VARCHAR(255) NULL COMMENT 'Region of the court',
  `court_branch` VARCHAR(255) NULL COMMENT 'Branch number/name',
  `court_city_municipality` VARCHAR(255) NULL COMMENT 'City/Municipality',
  `court_province` VARCHAR(255) NULL COMMENT 'Province',
  `case_number` VARCHAR(255) NULL COMMENT 'Court case number',
  `date_of_decree` DATE NULL COMMENT 'Date of court order',
  `date_of_filing` DATE NULL COMMENT 'Date filed at civil registry (optional)',
  `document_owner_names` VARCHAR(500) NOT NULL COMMENT 'Document Owner/s',
  `petitioner_names` VARCHAR(500) NULL,
  `document_type` ENUM('COLB','COM','COD') NULL,
  `registry_number` VARCHAR(100) NULL,
  `decree_details` TEXT NULL COMMENT 'Summary of the decree',
  `remarks` TEXT NULL,
  `pdf_filename` VARCHAR(255) NULL,
  `pdf_filepath` VARCHAR(500) NULL,
  `pdf_hash` CHAR(64) NULL,
  `status` ENUM('Active','Deleted') NOT NULL DEFAULT 'Active',
  `created_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `updated_by` INT UNSIGNED NULL COMMENT 'User ID from iscan_db.users',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_decree_type` (`decree_type`),
  INDEX `idx_date_of_filing` (`date_of_filing`),
  INDEX `idx_date_of_decree` (`date_of_decree`),
  INDEX `idx_status` (`status`),
  INDEX `idx_document_owner` (`document_owner_names`(100)),
  INDEX `idx_case_number` (`case_number`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
