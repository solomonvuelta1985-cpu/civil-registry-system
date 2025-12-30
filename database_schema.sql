-- ============================================
-- Certificate of Live Birth Database Schema
-- iScan - Civil Registry System
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS iscan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iscan_db;

-- ============================================
-- Certificate of Live Birth Table
-- ============================================
CREATE TABLE IF NOT EXISTS certificate_of_live_birth (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Registry Information
    registry_no VARCHAR(100) NULL,
    date_of_registration DATE NOT NULL,

    -- Child Information
    child_first_name VARCHAR(100) NULL,
    child_middle_name VARCHAR(100) NULL,
    child_last_name VARCHAR(100) NULL,
    child_date_of_birth DATE NULL,
    child_place_of_birth VARCHAR(255) NULL,

    -- Birth Information
    type_of_birth ENUM('Single', 'Twin', 'Triplets', 'Quadruplets', 'Other') NOT NULL DEFAULT 'Single',
    type_of_birth_other VARCHAR(100) NULL,
    birth_order ENUM('1st', '2nd', '3rd', '4th', '5th', '6th', '7th', 'Other') NULL,
    birth_order_other VARCHAR(50) NULL,

    -- Mother's Information
    mother_first_name VARCHAR(100) NOT NULL,
    mother_middle_name VARCHAR(100) NULL,
    mother_last_name VARCHAR(100) NOT NULL,

    -- Father's Information
    father_first_name VARCHAR(100) NULL,
    father_middle_name VARCHAR(100) NULL,
    father_last_name VARCHAR(100) NULL,

    -- Marriage Information
    date_of_marriage DATE NULL,
    place_of_marriage VARCHAR(255) NULL,

    -- File Information
    pdf_filename VARCHAR(255) NULL,
    pdf_filepath VARCHAR(500) NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT(11) UNSIGNED NULL,
    updated_by INT(11) UNSIGNED NULL,
    status ENUM('Active', 'Archived', 'Deleted') DEFAULT 'Active',

    -- Indexes
    INDEX idx_registry_no (registry_no),
    INDEX idx_mother_name (mother_last_name, mother_first_name),
    INDEX idx_father_name (father_last_name, father_first_name),
    INDEX idx_date_registration (date_of_registration),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Activity Logs Table
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Users Table (Simple Authentication)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(100) NULL,
    role ENUM('Admin', 'Encoder', 'Viewer') DEFAULT 'Encoder',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,

    INDEX idx_username (username),
    INDEX idx_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default Admin User
-- Password: admin123 (hashed with password_hash)
-- ============================================
INSERT INTO users (username, password, full_name, email, role, status)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@iscan.local', 'Admin', 'Active')
ON DUPLICATE KEY UPDATE username=username;

-- ============================================
-- Sample Data (Optional - for testing)
-- ============================================
-- INSERT INTO certificate_of_live_birth (
--     registry_no, date_of_registration, type_of_birth, birth_order,
--     mother_first_name, mother_middle_name, mother_last_name,
--     father_first_name, father_middle_name, father_last_name,
--     date_of_marriage, place_of_marriage
-- ) VALUES (
--     'REG-2025-00001', '2025-01-15 10:30:00', 'Single', '1st',
--     'Maria', 'Santos', 'Cruz',
--     'Juan', 'Reyes', 'Dela Cruz',
--     '2024-06-15', 'Manila City Hall'
-- );

-- ============================================
-- Views for Reporting
-- ============================================

-- View for Active Records
CREATE OR REPLACE VIEW vw_active_certificates AS
SELECT
    id,
    registry_no,
    date_of_registration,
    type_of_birth,
    birth_order,
    CONCAT(mother_last_name, ', ', mother_first_name, ' ', IFNULL(mother_middle_name, '')) AS mother_full_name,
    CONCAT(father_last_name, ', ', father_first_name, ' ', IFNULL(father_middle_name, '')) AS father_full_name,
    date_of_marriage,
    place_of_marriage,
    pdf_filename,
    created_at,
    updated_at
FROM certificate_of_live_birth
WHERE status = 'Active'
ORDER BY date_of_registration DESC;

-- View for Statistics
CREATE OR REPLACE VIEW vw_certificate_statistics AS
SELECT
    COUNT(*) AS total_records,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_records,
    SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) AS archived_records,
    SUM(CASE WHEN type_of_birth = 'Single' THEN 1 ELSE 0 END) AS single_births,
    SUM(CASE WHEN type_of_birth = 'Twin' THEN 1 ELSE 0 END) AS twin_births,
    SUM(CASE WHEN type_of_birth = 'Triplets' THEN 1 ELSE 0 END) AS triplet_births,
    SUM(CASE WHEN DATE(date_of_registration) = CURDATE() THEN 1 ELSE 0 END) AS today_registrations,
    SUM(CASE WHEN MONTH(date_of_registration) = MONTH(CURDATE()) AND YEAR(date_of_registration) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS this_month_registrations
FROM certificate_of_live_birth;

-- ============================================
-- End of Schema
-- ============================================
