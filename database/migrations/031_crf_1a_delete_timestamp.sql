-- Migration 031: retain the time a CRF No. 1A issuance was moved to Trash.

ALTER TABLE `crf_1a_issuances`
    ADD COLUMN `deleted_at` DATETIME NULL AFTER `archived_at`,
    ADD KEY `idx_crf_1a_deleted_at` (`deleted_at`);
