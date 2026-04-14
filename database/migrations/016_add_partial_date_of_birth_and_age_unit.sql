-- =====================================================================
-- Migration 016: Partial Date of Birth Support + Age Unit Selector
-- =====================================================================
-- Adds partial-date columns to each date_of_birth column (3 tables,
-- 4 DOB columns total) using the same pattern as migrations 014/015 for
-- date_of_registration. Also adds age_unit ENUM to certificate_of_death
-- so ages < 1 year can be expressed in months or days.
--
-- Columns added per DOB field:
--   <dob>_format ENUM('full','month_only','year_only','month_year','month_day','na') NOT NULL DEFAULT 'full'
--   <dob>_partial_month TINYINT(2) UNSIGNED NULL
--   <dob>_partial_year  SMALLINT(4) UNSIGNED NULL
--   <dob>_partial_day   TINYINT(2) UNSIGNED NULL
--
-- All existing rows default to format='full' — zero data loss.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. certificate_of_live_birth.child_date_of_birth
-- ---------------------------------------------------------------------
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN `child_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `child_date_of_birth`,
    ADD COLUMN `child_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `child_date_of_birth_format`,
    ADD COLUMN `child_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `child_date_of_birth_partial_month`,
    ADD COLUMN `child_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `child_date_of_birth_partial_year`;

-- ---------------------------------------------------------------------
-- 2. certificate_of_marriage.husband_date_of_birth
-- ---------------------------------------------------------------------
ALTER TABLE `certificate_of_marriage`
    MODIFY COLUMN `husband_date_of_birth` DATE NULL,
    MODIFY COLUMN `wife_date_of_birth` DATE NULL,
    ADD COLUMN `husband_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `husband_date_of_birth`,
    ADD COLUMN `husband_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `husband_date_of_birth_format`,
    ADD COLUMN `husband_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `husband_date_of_birth_partial_month`,
    ADD COLUMN `husband_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `husband_date_of_birth_partial_year`;

-- ---------------------------------------------------------------------
-- 3. certificate_of_marriage.wife_date_of_birth
-- ---------------------------------------------------------------------
ALTER TABLE `certificate_of_marriage`
    ADD COLUMN `wife_date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `wife_date_of_birth`,
    ADD COLUMN `wife_date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `wife_date_of_birth_format`,
    ADD COLUMN `wife_date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `wife_date_of_birth_partial_month`,
    ADD COLUMN `wife_date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `wife_date_of_birth_partial_year`;

-- ---------------------------------------------------------------------
-- 4. certificate_of_death.date_of_birth + age_unit
-- ---------------------------------------------------------------------
ALTER TABLE `certificate_of_death`
    ADD COLUMN `date_of_birth_format`
        ENUM('full','month_only','year_only','month_year','month_day','na')
        NOT NULL DEFAULT 'full'
        AFTER `date_of_birth`,
    ADD COLUMN `date_of_birth_partial_month`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_birth_format`,
    ADD COLUMN `date_of_birth_partial_year`
        SMALLINT(4) UNSIGNED NULL
        AFTER `date_of_birth_partial_month`,
    ADD COLUMN `date_of_birth_partial_day`
        TINYINT(2) UNSIGNED NULL
        AFTER `date_of_birth_partial_year`,
    ADD COLUMN `age_unit`
        ENUM('years','months','days')
        NOT NULL DEFAULT 'years'
        AFTER `age`;
