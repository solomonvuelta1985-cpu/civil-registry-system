-- =====================================================
-- Application for Marriage License (AML) Table
-- Civil Registry Document Management System (CRDMS)
-- =====================================================

CREATE TABLE IF NOT EXISTS `application_for_marriage_license` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `registry_no` VARCHAR(100) DEFAULT NULL,
    `date_of_application` DATE NOT NULL,

    -- Groom's Information
    `groom_first_name` VARCHAR(100) NOT NULL,
    `groom_middle_name` VARCHAR(100) DEFAULT NULL,
    `groom_last_name` VARCHAR(100) NOT NULL,
    `groom_date_of_birth` DATE NOT NULL,
    `groom_place_of_birth` VARCHAR(255) NOT NULL,
    `groom_citizenship` VARCHAR(100) NOT NULL,
    `groom_residence` TEXT NOT NULL,

    -- Groom's Father Information
    `groom_father_first_name` VARCHAR(100) DEFAULT NULL,
    `groom_father_middle_name` VARCHAR(100) DEFAULT NULL,
    `groom_father_last_name` VARCHAR(100) DEFAULT NULL,
    `groom_father_citizenship` VARCHAR(100) DEFAULT NULL,
    `groom_father_residence` TEXT DEFAULT NULL,

    -- Groom's Mother Information
    `groom_mother_first_name` VARCHAR(100) DEFAULT NULL,
    `groom_mother_middle_name` VARCHAR(100) DEFAULT NULL,
    `groom_mother_last_name` VARCHAR(100) DEFAULT NULL,
    `groom_mother_citizenship` VARCHAR(100) DEFAULT NULL,
    `groom_mother_residence` TEXT DEFAULT NULL,

    -- Bride's Information
    `bride_first_name` VARCHAR(100) NOT NULL,
    `bride_middle_name` VARCHAR(100) DEFAULT NULL,
    `bride_last_name` VARCHAR(100) NOT NULL,
    `bride_date_of_birth` DATE NOT NULL,
    `bride_place_of_birth` VARCHAR(255) NOT NULL,
    `bride_citizenship` VARCHAR(100) NOT NULL,
    `bride_residence` TEXT NOT NULL,

    -- Bride's Father Information
    `bride_father_first_name` VARCHAR(100) DEFAULT NULL,
    `bride_father_middle_name` VARCHAR(100) DEFAULT NULL,
    `bride_father_last_name` VARCHAR(100) DEFAULT NULL,
    `bride_father_citizenship` VARCHAR(100) DEFAULT NULL,
    `bride_father_residence` TEXT DEFAULT NULL,

    -- Bride's Mother Information
    `bride_mother_first_name` VARCHAR(100) DEFAULT NULL,
    `bride_mother_middle_name` VARCHAR(100) DEFAULT NULL,
    `bride_mother_last_name` VARCHAR(100) DEFAULT NULL,
    `bride_mother_citizenship` VARCHAR(100) DEFAULT NULL,
    `bride_mother_residence` TEXT DEFAULT NULL,

    -- PDF Document
    `pdf_filename` VARCHAR(255) DEFAULT NULL,
    `pdf_filepath` VARCHAR(500) DEFAULT NULL,

    -- Status and Audit Fields
    `status` ENUM('Active', 'Archived', 'Deleted') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_registry_no` (`registry_no`),
    INDEX `idx_date_of_application` (`date_of_application`),
    INDEX `idx_groom_name` (`groom_first_name`, `groom_last_name`),
    INDEX `idx_bride_name` (`bride_first_name`, `bride_last_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Note: This system uses role-based access control via
-- the users.role ENUM field (Admin, Encoder, Viewer).
-- No separate permissions table is required.
--
-- Access Control:
-- - Admin: Full access (view, create, edit, delete)
-- - Encoder: Can view, create, and edit records
-- - Viewer: Can only view records
-- =====================================================
