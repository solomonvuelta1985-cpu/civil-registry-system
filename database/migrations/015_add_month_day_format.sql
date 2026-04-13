-- Migration 015: Add 'month_day' option to date_of_registration_format ENUM
-- and add partial_day column to store the day value for month_day format.
-- Also adds partial_day for future completeness of month_only if needed.

-- ─── Certificate of Live Birth ───────────────────────────────────────────────
ALTER TABLE `certificate_of_live_birth`
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full',
    ADD COLUMN `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;

-- ─── Certificate of Marriage ──────────────────────────────────────────────────
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full',
    ADD COLUMN `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;

-- ─── Certificate of Death ─────────────────────────────────────────────────────
ALTER TABLE `certificate_of_death`
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full',
    ADD COLUMN `date_of_registration_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_registration_partial_year`;
