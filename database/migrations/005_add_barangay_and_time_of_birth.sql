-- Migration: Add barangay and time_of_birth columns, update place_type
-- Date: 2026-02-17
-- Description: Adds barangay field for specific place of birth location,
--              time_of_birth field, and updates place_type from ENUM to VARCHAR
--              to support new values (Hospital/Clinic, Home, Barangay Health Center, Other)

ALTER TABLE certificate_of_live_birth
  ADD COLUMN barangay VARCHAR(255) NULL AFTER child_place_of_birth,
  ADD COLUMN time_of_birth TIME NULL AFTER child_date_of_birth;

-- Change place_type from ENUM to VARCHAR to support new place types
ALTER TABLE certificate_of_live_birth
  MODIFY COLUMN place_type VARCHAR(100) NULL;
