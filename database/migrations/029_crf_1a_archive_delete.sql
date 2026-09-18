-- Migration 029: allow CRF No. 1A issuances to be archived or moved to Trash.

ALTER TABLE `crf_1a_issuances`
    MODIFY COLUMN `status` ENUM('Active', 'Archived', 'Deleted', 'Voided') NOT NULL DEFAULT 'Active';
