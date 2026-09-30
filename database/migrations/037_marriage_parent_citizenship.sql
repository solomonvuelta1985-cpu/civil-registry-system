-- Migration 037: Add parent citizenship/nationality fields used by CRF No. 3A.

ALTER TABLE `certificate_of_marriage`
    ADD COLUMN IF NOT EXISTS `husband_father_citizenship` VARCHAR(100) NULL AFTER `husband_father_residence`,
    ADD COLUMN IF NOT EXISTS `husband_mother_citizenship` VARCHAR(100) NULL AFTER `husband_mother_residence`,
    ADD COLUMN IF NOT EXISTS `wife_father_citizenship` VARCHAR(100) NULL AFTER `wife_father_residence`,
    ADD COLUMN IF NOT EXISTS `wife_mother_citizenship` VARCHAR(100) NULL AFTER `wife_mother_residence`;