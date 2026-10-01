-- Preserve missing/illegible registry numbers separately from the unique registry_no value.
ALTER TABLE `certificate_of_live_birth`
    ADD COLUMN `registry_no_status` VARCHAR(20) NULL AFTER `registry_no`,
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na','not_readable','no_entry')
        NOT NULL DEFAULT 'full';

ALTER TABLE `certificate_of_death`
    ADD COLUMN `registry_no_status` VARCHAR(20) NULL AFTER `registry_no`,
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na','not_readable','no_entry')
        NOT NULL DEFAULT 'full';

ALTER TABLE `certificate_of_marriage`
    ADD COLUMN `registry_no_status` VARCHAR(20) NULL AFTER `registry_no`,
    MODIFY COLUMN `date_of_registration_format`
        ENUM('full','month_only','year_only','month_year','month_day','na','not_readable','no_entry')
        NOT NULL DEFAULT 'full';

ALTER TABLE `application_for_marriage_license`
    ADD COLUMN `registry_no_status` VARCHAR(20) NULL AFTER `registry_no`;
