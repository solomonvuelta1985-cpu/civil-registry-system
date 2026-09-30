-- ============================================================
-- Migration 048: Persist PDF protection preview mode
-- ============================================================

ALTER TABLE pdf_protection_jobs
    ADD COLUMN dry_run TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
