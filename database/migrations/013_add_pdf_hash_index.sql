-- ============================================================
-- Migration 013: Index pdf_hash for Fast Duplicate Detection
-- iScan Civil Registry Records Management System
-- ============================================================
-- Purpose: Adds a non-unique index on pdf_hash in all 4 certificate
--          tables so that the duplicate-PDF check performed on upload
--          (check_pdf_duplicate() in includes/functions.php) runs in
--          O(log n) instead of scanning the whole table.
--
--          The index is intentionally NON-UNIQUE — the uniqueness
--          constraint is enforced in application code so we can return
--          a friendly, actionable error message pointing to the
--          existing record (which table, which registry no).
--
-- Run:
--   mysql -u root iscan_db < database/migrations/013_add_pdf_hash_index.sql
-- ============================================================

USE iscan_db;

-- Birth Certificates
ALTER TABLE certificate_of_live_birth
    ADD INDEX IF NOT EXISTS idx_pdf_hash (pdf_hash);

-- Death Certificates
ALTER TABLE certificate_of_death
    ADD INDEX IF NOT EXISTS idx_pdf_hash (pdf_hash);

-- Marriage Certificates
ALTER TABLE certificate_of_marriage
    ADD INDEX IF NOT EXISTS idx_pdf_hash (pdf_hash);

-- Marriage License Applications
ALTER TABLE application_for_marriage_license
    ADD INDEX IF NOT EXISTS idx_pdf_hash (pdf_hash);
