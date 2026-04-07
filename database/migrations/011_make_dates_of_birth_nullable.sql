-- Migration 011: Make date_of_birth columns nullable across all registration tables
-- This allows saving records where birthdate information is unknown or not provided.
-- Run this against your iScan database.

-- Certificate of Death
ALTER TABLE `certificate_of_death`
    MODIFY COLUMN `date_of_birth` DATE NULL;

-- Certificate of Marriage (husband + wife)
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `husband_date_of_birth` DATE NULL,
    MODIFY COLUMN `wife_date_of_birth` DATE NULL;

-- Application for Marriage License (groom + bride)
ALTER TABLE `application_for_marriage_license`
    MODIFY COLUMN `groom_date_of_birth` DATE NULL,
    MODIFY COLUMN `bride_date_of_birth` DATE NULL;

-- Certificate of Live Birth: child_date_of_birth is already NULL in current schema (database_schema.sql line 24)
-- No change needed for birth certificate table.
