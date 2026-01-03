-- Create certificate_of_death table
-- Based on actual form fields in certificate_of_death.php
CREATE TABLE IF NOT EXISTS `certificate_of_death` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Registry Information
    `registry_no` VARCHAR(100) DEFAULT NULL,
    `date_of_registration` DATE NOT NULL,

    -- Deceased Information
    `deceased_first_name` VARCHAR(100) NOT NULL,
    `deceased_middle_name` VARCHAR(100) DEFAULT NULL,
    `deceased_last_name` VARCHAR(100) NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `date_of_death` DATE NOT NULL,
    `age` INT DEFAULT NULL,
    `occupation` VARCHAR(100) DEFAULT NULL,

    -- Place of Death
    `place_of_death` VARCHAR(255) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `municipality` VARCHAR(100) DEFAULT NULL,

    -- Father's Information
    `father_first_name` VARCHAR(100) DEFAULT NULL,
    `father_middle_name` VARCHAR(100) DEFAULT NULL,
    `father_last_name` VARCHAR(100) DEFAULT NULL,

    -- Mother's Information
    `mother_first_name` VARCHAR(100) DEFAULT NULL,
    `mother_middle_name` VARCHAR(100) DEFAULT NULL,
    `mother_last_name` VARCHAR(100) DEFAULT NULL,

    -- PDF File
    `pdf_filename` VARCHAR(255) DEFAULT NULL,
    `pdf_filepath` VARCHAR(500) DEFAULT NULL,

    -- Metadata
    `status` ENUM('Active', 'Archived', 'Deleted') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_registry_no` (`registry_no`),
    INDEX `idx_deceased_name` (`deceased_last_name`, `deceased_first_name`),
    INDEX `idx_father_name` (`father_last_name`, `father_first_name`),
    INDEX `idx_mother_name` (`mother_last_name`, `mother_first_name`),
    INDEX `idx_date_of_death` (`date_of_death`),
    INDEX `idx_date_of_birth` (`date_of_birth`),
    INDEX `idx_date_of_registration` (`date_of_registration`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
