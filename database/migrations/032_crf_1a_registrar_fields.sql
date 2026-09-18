-- Migration 032: store the manually entered registrar name and position on each CRF issuance.

ALTER TABLE `crf_1a_issuances`
    ADD COLUMN `mcr_full_name` VARCHAR(150) NULL AFTER `date_paid`,
    ADD COLUMN `mcr_title` VARCHAR(100) NULL AFTER `mcr_full_name`;
