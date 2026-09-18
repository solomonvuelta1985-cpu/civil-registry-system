-- Migration 030: retain the time a CRF No. 1A issuance was archived.

ALTER TABLE `crf_1a_issuances`
    ADD COLUMN `archived_at` DATETIME NULL AFTER `status`,
    ADD KEY `idx_crf_1a_archived_at` (`archived_at`);
