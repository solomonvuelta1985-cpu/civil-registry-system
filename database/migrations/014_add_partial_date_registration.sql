-- Migration 014: Add partial date support for date_of_registration
-- Converts date_of_registration from DATE NOT NULL to DATE NULL across all
-- three certificate tables, and adds companion columns to track how the date
-- was entered (full, month_only, year_only, month_year, na) plus raw month/year
-- integer values for month_only and year_only cases.
--
-- All existing rows default to format='full' — zero data loss.

-- ─── Certificate of Live Birth ───────────────────────────────────────────────
ALTER TABLE `certificate_of_live_birth`
    MODIFY COLUMN `date_of_registration` DATE NULL,
    ADD COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`;

-- ─── Certificate of Marriage ──────────────────────────────────────────────────
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `date_of_registration` DATE NULL,
    ADD COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`;

-- ─── Certificate of Death ─────────────────────────────────────────────────────
ALTER TABLE `certificate_of_death`
    MODIFY COLUMN `date_of_registration` DATE NULL,
    ADD COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_registration`,
    ADD COLUMN `date_of_registration_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_format`,
    ADD COLUMN `date_of_registration_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_registration_partial_month`;
