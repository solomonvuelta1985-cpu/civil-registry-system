-- ============================================
-- Migration: Add Child Columns to Birth Certificates
-- Date: 2025-12-27
-- Description: Adds missing child-related columns to certificate_of_live_birth table
-- ============================================

USE iscan_db;

-- Add child columns to certificate_of_live_birth table
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `child_first_name` VARCHAR(100) NULL AFTER `birth_order_other`,
ADD COLUMN `child_middle_name` VARCHAR(100) NULL AFTER `child_first_name`,
ADD COLUMN `child_last_name` VARCHAR(100) NULL AFTER `child_middle_name`,
ADD COLUMN `child_date_of_birth` DATE NULL AFTER `child_last_name`,
ADD COLUMN `child_place_of_birth` VARCHAR(255) NULL AFTER `child_date_of_birth`,
ADD COLUMN `child_sex` ENUM('Male', 'Female') NULL AFTER `child_place_of_birth`;

-- Add index for child name (for faster searches)
ALTER TABLE `certificate_of_live_birth`
ADD KEY `idx_child_name` (`child_last_name`, `child_first_name`),
ADD KEY `idx_child_date_of_birth` (`child_date_of_birth`);

-- Display success message
SELECT 'Migration completed successfully! Child columns added to certificate_of_live_birth table.' AS status;
