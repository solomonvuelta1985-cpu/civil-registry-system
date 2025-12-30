-- ============================================================================
-- MIGRATION 001: Enhanced Schema with Complete Metadata
-- Purpose: Add all missing fields for complete civil registry data capture
-- Date: 2025-12-27
-- ============================================================================

-- ============================================================================
-- 1. ENHANCE CERTIFICATE_OF_LIVE_BIRTH TABLE
-- ============================================================================

-- Add complete child information
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `child_sex` ENUM('Male', 'Female') NULL AFTER `child_last_name`,
ADD COLUMN `child_birth_time` TIME NULL COMMENT 'Time of birth' AFTER `child_date_of_birth`,
ADD COLUMN `child_weight` DECIMAL(5,2) NULL COMMENT 'Weight in kilograms' AFTER `child_birth_time`,
ADD COLUMN `child_multiple_birth` ENUM('Single', 'Twin', 'Triplet', 'Quadruplet', 'Quintuplet', 'Other') DEFAULT 'Single' AFTER `child_weight`,
ADD COLUMN `child_multiple_birth_order` TINYINT NULL COMMENT '1st, 2nd, etc. in multiple birth' AFTER `child_multiple_birth`;

-- Add complete mother information
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `mother_maiden_name` VARCHAR(100) NULL COMMENT 'Mother maiden surname' AFTER `mother_last_name`,
ADD COLUMN `mother_citizenship` VARCHAR(50) NULL DEFAULT 'Filipino' AFTER `mother_maiden_name`,
ADD COLUMN `mother_religion` VARCHAR(50) NULL AFTER `mother_citizenship`,
ADD COLUMN `mother_occupation` VARCHAR(100) NULL AFTER `mother_religion`,
ADD COLUMN `mother_age_at_birth` TINYINT NULL AFTER `mother_occupation`,
ADD COLUMN `mother_residence_country` VARCHAR(100) NULL DEFAULT 'Philippines' AFTER `mother_age_at_birth`,
ADD COLUMN `mother_residence_province` VARCHAR(100) NULL AFTER `mother_residence_country`,
ADD COLUMN `mother_residence_municipality` VARCHAR(100) NULL AFTER `mother_residence_province`,
ADD COLUMN `mother_residence_barangay` VARCHAR(100) NULL AFTER `mother_residence_municipality`,
ADD COLUMN `mother_residence_street` VARCHAR(200) NULL AFTER `mother_residence_barangay`;

-- Add complete father information
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `father_citizenship` VARCHAR(50) NULL DEFAULT 'Filipino' AFTER `father_last_name`,
ADD COLUMN `father_religion` VARCHAR(50) NULL AFTER `father_citizenship`,
ADD COLUMN `father_occupation` VARCHAR(100) NULL AFTER `father_religion`,
ADD COLUMN `father_age_at_birth` TINYINT NULL AFTER `father_occupation`,
ADD COLUMN `father_residence_country` VARCHAR(100) NULL DEFAULT 'Philippines' AFTER `father_age_at_birth`,
ADD COLUMN `father_residence_province` VARCHAR(100) NULL AFTER `father_residence_country`,
ADD COLUMN `father_residence_municipality` VARCHAR(100) NULL AFTER `father_residence_province`,
ADD COLUMN `father_residence_barangay` VARCHAR(100) NULL AFTER `father_residence_municipality`,
ADD COLUMN `father_residence_street` VARCHAR(200) NULL AFTER `father_residence_barangay`;

-- Add marriage information
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `parents_married` ENUM('Yes', 'No', 'Unknown') DEFAULT 'Unknown' AFTER `place_of_marriage`,
ADD COLUMN `marriage_country` VARCHAR(100) NULL AFTER `parents_married`,
ADD COLUMN `marriage_province` VARCHAR(100) NULL AFTER `marriage_country`,
ADD COLUMN `marriage_municipality` VARCHAR(100) NULL AFTER `marriage_province`;

-- Add birth attendant information
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `attendant_role` ENUM('Physician', 'Nurse', 'Midwife', 'Hilot', 'Other', 'None') NULL AFTER `marriage_municipality`,
ADD COLUMN `attendant_name` VARCHAR(200) NULL AFTER `attendant_role`,
ADD COLUMN `attendant_title` VARCHAR(100) NULL COMMENT 'MD, RN, RM, etc.' AFTER `attendant_name`,
ADD COLUMN `attendant_credentials` VARCHAR(200) NULL COMMENT 'License number, certification' AFTER `attendant_title`;

-- Add place of delivery details
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `delivery_type` ENUM('Hospital', 'Clinic', 'Home', 'Other') NULL AFTER `attendant_credentials`,
ADD COLUMN `delivery_institution_name` VARCHAR(200) NULL AFTER `delivery_type`,
ADD COLUMN `delivery_institution_address` VARCHAR(300) NULL AFTER `delivery_institution_name`;

-- Add certificate issuance details
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `psa_reference_number` VARCHAR(50) NULL COMMENT 'PSA/NSO reference number' AFTER `delivery_institution_address`,
ADD COLUMN `local_civil_registrar` VARCHAR(200) NULL AFTER `psa_reference_number`,
ADD COLUMN `issued_date` DATE NULL AFTER `local_civil_registrar`,
ADD COLUMN `issued_at` VARCHAR(200) NULL COMMENT 'Place of issuance' AFTER `issued_date`;

-- Add annotation/remarks tracking
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `has_annotations` BOOLEAN DEFAULT FALSE AFTER `issued_at`,
ADD COLUMN `annotation_details` TEXT NULL COMMENT 'Summary of annotations/corrections' AFTER `has_annotations`,
ADD COLUMN `is_delayed_registration` BOOLEAN DEFAULT FALSE AFTER `annotation_details`,
ADD COLUMN `delayed_registration_reason` TEXT NULL AFTER `is_delayed_registration`;

-- Add informant details
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `informant_name` VARCHAR(200) NULL AFTER `delayed_registration_reason`,
ADD COLUMN `informant_relationship` VARCHAR(100) NULL COMMENT 'Relationship to child' AFTER `informant_name`,
ADD COLUMN `informant_address` VARCHAR(300) NULL AFTER `informant_relationship`;

-- ============================================================================
-- 2. ENHANCE CERTIFICATE_OF_MARRIAGE TABLE
-- ============================================================================

-- Add complete husband information
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `husband_age_at_marriage` TINYINT NULL AFTER `husband_date_of_birth`,
ADD COLUMN `husband_citizenship` VARCHAR(50) NULL DEFAULT 'Filipino' AFTER `husband_residence`,
ADD COLUMN `husband_religion` VARCHAR(50) NULL AFTER `husband_citizenship`,
ADD COLUMN `husband_civil_status` ENUM('Single', 'Widow', 'Widower', 'Divorced', 'Annulled') DEFAULT 'Single' AFTER `husband_religion`,
ADD COLUMN `husband_occupation` VARCHAR(100) NULL AFTER `husband_civil_status`,
ADD COLUMN `husband_residence_street` VARCHAR(200) NULL AFTER `husband_residence`,
ADD COLUMN `husband_residence_barangay` VARCHAR(100) NULL AFTER `husband_residence_street`,
ADD COLUMN `husband_residence_municipality` VARCHAR(100) NULL AFTER `husband_residence_barangay`,
ADD COLUMN `husband_residence_province` VARCHAR(100) NULL AFTER `husband_residence_municipality`;

-- Add complete wife information
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `wife_age_at_marriage` TINYINT NULL AFTER `wife_date_of_birth`,
ADD COLUMN `wife_maiden_surname` VARCHAR(100) NULL AFTER `wife_last_name`,
ADD COLUMN `wife_citizenship` VARCHAR(50) NULL DEFAULT 'Filipino' AFTER `wife_residence`,
ADD COLUMN `wife_religion` VARCHAR(50) NULL AFTER `wife_citizenship`,
ADD COLUMN `wife_civil_status` ENUM('Single', 'Widow', 'Widower', 'Divorced', 'Annulled') DEFAULT 'Single' AFTER `wife_religion`,
ADD COLUMN `wife_occupation` VARCHAR(100) NULL AFTER `wife_civil_status`,
ADD COLUMN `wife_residence_street` VARCHAR(200) NULL AFTER `wife_residence`,
ADD COLUMN `wife_residence_barangay` VARCHAR(100) NULL AFTER `wife_residence_street`,
ADD COLUMN `wife_residence_municipality` VARCHAR(100) NULL AFTER `wife_residence_barangay`,
ADD COLUMN `wife_residence_province` VARCHAR(100) NULL AFTER `wife_residence_municipality`;

-- Add marriage ceremony details
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `marriage_time` TIME NULL AFTER `date_of_marriage`,
ADD COLUMN `marriage_place_type` ENUM('Church', 'City Hall', 'Court', 'Garden', 'Beach', 'Home', 'Other') NULL AFTER `place_of_marriage`,
ADD COLUMN `marriage_barangay` VARCHAR(100) NULL AFTER `marriage_place_type`,
ADD COLUMN `marriage_municipality` VARCHAR(100) NULL AFTER `marriage_barangay`,
ADD COLUMN `marriage_province` VARCHAR(100) NULL AFTER `marriage_municipality`,
ADD COLUMN `marriage_country` VARCHAR(100) NULL DEFAULT 'Philippines' AFTER `marriage_province`;

-- Add solemnizing officer details
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `solemnizing_officer_name` VARCHAR(200) NULL AFTER `marriage_country`,
ADD COLUMN `solemnizing_officer_title` VARCHAR(100) NULL COMMENT 'Priest, Judge, Mayor, etc.' AFTER `solemnizing_officer_name`,
ADD COLUMN `solemnizing_officer_religion` VARCHAR(100) NULL AFTER `solemnizing_officer_title`,
ADD COLUMN `solemnizing_officer_credentials` VARCHAR(200) NULL AFTER `solemnizing_officer_religion`;

-- Add witnesses information
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `witness_1_name` VARCHAR(200) NULL AFTER `solemnizing_officer_credentials`,
ADD COLUMN `witness_1_age` TINYINT NULL AFTER `witness_1_name`,
ADD COLUMN `witness_1_residence` VARCHAR(300) NULL AFTER `witness_1_age`,
ADD COLUMN `witness_2_name` VARCHAR(200) NULL AFTER `witness_1_residence`,
ADD COLUMN `witness_2_age` TINYINT NULL AFTER `witness_2_name`,
ADD COLUMN `witness_2_residence` VARCHAR(300) NULL AFTER `witness_2_age`;

-- Add marriage license details
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `marriage_license_number` VARCHAR(50) NULL AFTER `witness_2_residence`,
ADD COLUMN `marriage_license_date_issued` DATE NULL AFTER `marriage_license_number`,
ADD COLUMN `marriage_license_place_issued` VARCHAR(200) NULL AFTER `marriage_license_date_issued`;

-- Add certificate issuance details
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `psa_reference_number` VARCHAR(50) NULL COMMENT 'PSA/NSO reference number' AFTER `marriage_license_place_issued`,
ADD COLUMN `local_civil_registrar` VARCHAR(200) NULL AFTER `psa_reference_number`,
ADD COLUMN `issued_date` DATE NULL AFTER `local_civil_registrar`,
ADD COLUMN `issued_at` VARCHAR(200) NULL AFTER `issued_date`;

-- Add annotation/remarks tracking
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `has_annotations` BOOLEAN DEFAULT FALSE AFTER `issued_at`,
ADD COLUMN `annotation_details` TEXT NULL AFTER `has_annotations`;

-- ============================================================================
-- 3. ADD INDEXES FOR PERFORMANCE
-- ============================================================================

-- Birth certificate indexes
ALTER TABLE `certificate_of_live_birth`
ADD INDEX `idx_child_sex` (`child_sex`),
ADD INDEX `idx_child_dob` (`child_date_of_birth`),
ADD INDEX `idx_mother_citizenship` (`mother_citizenship`),
ADD INDEX `idx_psa_reference` (`psa_reference_number`),
ADD INDEX `idx_delayed_registration` (`is_delayed_registration`);

-- Marriage certificate indexes
ALTER TABLE `certificate_of_marriage`
ADD INDEX `idx_marriage_date` (`date_of_marriage`),
ADD INDEX `idx_husband_citizenship` (`husband_citizenship`),
ADD INDEX `idx_wife_citizenship` (`wife_citizenship`),
ADD INDEX `idx_psa_reference` (`psa_reference_number`),
ADD INDEX `idx_license_number` (`marriage_license_number`);

-- ============================================================================
-- ROLLBACK SCRIPT (Keep for reference)
-- ============================================================================
/*
-- To rollback this migration, execute the following:

-- Birth certificate rollback
ALTER TABLE `certificate_of_live_birth`
DROP COLUMN `child_sex`,
DROP COLUMN `child_birth_time`,
DROP COLUMN `child_weight`,
DROP COLUMN `child_multiple_birth`,
DROP COLUMN `child_multiple_birth_order`,
DROP COLUMN `mother_maiden_name`,
DROP COLUMN `mother_citizenship`,
DROP COLUMN `mother_religion`,
DROP COLUMN `mother_occupation`,
DROP COLUMN `mother_age_at_birth`,
DROP COLUMN `mother_residence_country`,
DROP COLUMN `mother_residence_province`,
DROP COLUMN `mother_residence_municipality`,
DROP COLUMN `mother_residence_barangay`,
DROP COLUMN `mother_residence_street`,
DROP COLUMN `father_citizenship`,
DROP COLUMN `father_religion`,
DROP COLUMN `father_occupation`,
DROP COLUMN `father_age_at_birth`,
DROP COLUMN `father_residence_country`,
DROP COLUMN `father_residence_province`,
DROP COLUMN `father_residence_municipality`,
DROP COLUMN `father_residence_barangay`,
DROP COLUMN `father_residence_street`,
DROP COLUMN `parents_married`,
DROP COLUMN `marriage_country`,
DROP COLUMN `marriage_province`,
DROP COLUMN `marriage_municipality`,
DROP COLUMN `attendant_role`,
DROP COLUMN `attendant_name`,
DROP COLUMN `attendant_title`,
DROP COLUMN `attendant_credentials`,
DROP COLUMN `delivery_type`,
DROP COLUMN `delivery_institution_name`,
DROP COLUMN `delivery_institution_address`,
DROP COLUMN `psa_reference_number`,
DROP COLUMN `local_civil_registrar`,
DROP COLUMN `issued_date`,
DROP COLUMN `issued_at`,
DROP COLUMN `has_annotations`,
DROP COLUMN `annotation_details`,
DROP COLUMN `is_delayed_registration`,
DROP COLUMN `delayed_registration_reason`,
DROP COLUMN `informant_name`,
DROP COLUMN `informant_relationship`,
DROP COLUMN `informant_address`;

-- Marriage certificate rollback
ALTER TABLE `certificate_of_marriage`
DROP COLUMN `husband_age_at_marriage`,
DROP COLUMN `husband_citizenship`,
DROP COLUMN `husband_religion`,
DROP COLUMN `husband_civil_status`,
DROP COLUMN `husband_occupation`,
DROP COLUMN `husband_residence_street`,
DROP COLUMN `husband_residence_barangay`,
DROP COLUMN `husband_residence_municipality`,
DROP COLUMN `husband_residence_province`,
DROP COLUMN `wife_age_at_marriage`,
DROP COLUMN `wife_maiden_surname`,
DROP COLUMN `wife_citizenship`,
DROP COLUMN `wife_religion`,
DROP COLUMN `wife_civil_status`,
DROP COLUMN `wife_occupation`,
DROP COLUMN `wife_residence_street`,
DROP COLUMN `wife_residence_barangay`,
DROP COLUMN `wife_residence_municipality`,
DROP COLUMN `wife_residence_province`,
DROP COLUMN `marriage_time`,
DROP COLUMN `marriage_place_type`,
DROP COLUMN `marriage_barangay`,
DROP COLUMN `marriage_municipality`,
DROP COLUMN `marriage_province`,
DROP COLUMN `marriage_country`,
DROP COLUMN `solemnizing_officer_name`,
DROP COLUMN `solemnizing_officer_title`,
DROP COLUMN `solemnizing_officer_religion`,
DROP COLUMN `solemnizing_officer_credentials`,
DROP COLUMN `witness_1_name`,
DROP COLUMN `witness_1_age`,
DROP COLUMN `witness_1_residence`,
DROP COLUMN `witness_2_name`,
DROP COLUMN `witness_2_age`,
DROP COLUMN `witness_2_residence`,
DROP COLUMN `marriage_license_number`,
DROP COLUMN `marriage_license_date_issued`,
DROP COLUMN `marriage_license_place_issued`,
DROP COLUMN `psa_reference_number`,
DROP COLUMN `local_civil_registrar`,
DROP COLUMN `issued_date`,
DROP COLUMN `issued_at`,
DROP COLUMN `has_annotations`,
DROP COLUMN `annotation_details`;
*/
