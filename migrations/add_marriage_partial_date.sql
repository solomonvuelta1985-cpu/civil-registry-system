-- ============================================
-- Migration: Add partial-date columns for date_of_marriage
-- Table: certificate_of_live_birth
-- ============================================
-- Mirrors the partial-date pattern already used for
-- date_of_registration and child_date_of_birth.
--
-- Format values: 'full', 'month_only', 'year_only',
-- 'month_year', 'month_day', 'na'
-- ============================================

ALTER TABLE certificate_of_live_birth
    ADD COLUMN date_of_marriage_format VARCHAR(20) NULL DEFAULT 'full' AFTER date_of_marriage_others,
    ADD COLUMN date_of_marriage_partial_month TINYINT NULL AFTER date_of_marriage_format,
    ADD COLUMN date_of_marriage_partial_year SMALLINT NULL AFTER date_of_marriage_partial_month,
    ADD COLUMN date_of_marriage_partial_day TINYINT NULL AFTER date_of_marriage_partial_year;
