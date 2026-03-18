-- Migration 008: Add UNIQUE constraint on registry_no columns
-- Prevents duplicate registry numbers across certificate tables
-- NOTE: registry_no is nullable, MySQL UNIQUE allows multiple NULLs

ALTER TABLE certificate_of_live_birth
    DROP INDEX IF EXISTS idx_registry_no,
    ADD UNIQUE KEY uniq_registry_no (registry_no);

ALTER TABLE certificate_of_death
    DROP INDEX IF EXISTS idx_registry_no,
    ADD UNIQUE KEY uniq_registry_no (registry_no);

ALTER TABLE certificate_of_marriage
    DROP INDEX IF EXISTS idx_registry_no,
    ADD UNIQUE KEY uniq_registry_no (registry_no);

ALTER TABLE application_for_marriage_license
    DROP INDEX IF EXISTS idx_registry_no,
    ADD UNIQUE KEY uniq_registry_no (registry_no);
