-- Migration 012: Add sex column to certificate_of_death
-- Stores the sex of the deceased (Male/Female).
-- Run this against your iScan database.

ALTER TABLE `certificate_of_death`
    ADD COLUMN `sex` ENUM('Male', 'Female') DEFAULT NULL AFTER `deceased_last_name`;
