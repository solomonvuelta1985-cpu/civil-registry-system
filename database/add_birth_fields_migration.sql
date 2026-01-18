-- Migration: Add child_sex and legitimacy_status fields to certificate_of_live_birth
-- Date: 2026-01-18
-- Description: Adds sex and legitimacy status fields to birth certificate records

-- Add legitimacy_status column (child_sex already exists)
ALTER TABLE certificate_of_live_birth
ADD COLUMN IF NOT EXISTS legitimacy_status ENUM('Legitimate', 'Illegitimate') NULL
AFTER child_sex;

-- Verify the columns exist
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'iscan_db'
    AND TABLE_NAME = 'certificate_of_live_birth'
    AND COLUMN_NAME IN ('child_sex', 'legitimacy_status')
ORDER BY ORDINAL_POSITION;
