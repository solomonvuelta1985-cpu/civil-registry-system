-- Migration: Add nature_of_solemnization field to certificate_of_marriage
-- Date: 2026-01-18
-- Description: Adds nature of solemnization field to marriage certificate records

-- Add nature_of_solemnization column
ALTER TABLE certificate_of_marriage
ADD COLUMN IF NOT EXISTS nature_of_solemnization ENUM('Church', 'Civil', 'Other Religious Sect') NULL
AFTER place_of_marriage;

-- Verify the column exists
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'iscan_db'
    AND TABLE_NAME = 'certificate_of_marriage'
    AND COLUMN_NAME = 'nature_of_solemnization';
