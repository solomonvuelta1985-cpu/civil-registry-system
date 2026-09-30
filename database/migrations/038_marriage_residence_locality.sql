-- Migration 038: Store municipality and province for marriage residences.

ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `husband_residence_municipality` VARCHAR(100) NULL AFTER `husband_residence`,
    ADD COLUMN IF NOT EXISTS `husband_residence_province` VARCHAR(100) NULL AFTER `husband_residence_municipality`,
    ADD COLUMN IF NOT EXISTS `husband_father_residence_municipality` VARCHAR(100) NULL AFTER `husband_father_residence`,
    ADD COLUMN IF NOT EXISTS `husband_father_residence_province` VARCHAR(100) NULL AFTER `husband_father_residence_municipality`,
    ADD COLUMN IF NOT EXISTS `husband_mother_residence_municipality` VARCHAR(100) NULL AFTER `husband_mother_residence`,
    ADD COLUMN IF NOT EXISTS `husband_mother_residence_province` VARCHAR(100) NULL AFTER `husband_mother_residence_municipality`,
    ADD COLUMN IF NOT EXISTS `wife_residence_municipality` VARCHAR(100) NULL AFTER `wife_residence`,
    ADD COLUMN IF NOT EXISTS `wife_residence_province` VARCHAR(100) NULL AFTER `wife_residence_municipality`,
    ADD COLUMN IF NOT EXISTS `wife_father_residence_municipality` VARCHAR(100) NULL AFTER `wife_father_residence`,
    ADD COLUMN IF NOT EXISTS `wife_father_residence_province` VARCHAR(100) NULL AFTER `wife_father_residence_municipality`,
    ADD COLUMN IF NOT EXISTS `wife_mother_residence_municipality` VARCHAR(100) NULL AFTER `wife_mother_residence`,
    ADD COLUMN IF NOT EXISTS `wife_mother_residence_province` VARCHAR(100) NULL AFTER `wife_mother_residence_municipality`;
