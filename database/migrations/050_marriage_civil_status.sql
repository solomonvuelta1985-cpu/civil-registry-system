-- Add optional source-record civil status fields used by the marriage form and CRF No. 3A.
-- Existing marriage records remain valid; staff can fill these values during their next edit.

ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `husband_civil_status` VARCHAR(50) NULL AFTER `husband_citizenship`,
    ADD COLUMN IF NOT EXISTS `wife_civil_status` VARCHAR(50) NULL AFTER `wife_citizenship`;
