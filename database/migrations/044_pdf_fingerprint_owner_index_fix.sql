-- ============================================================
-- Migration 044: Allow Atomic Hash Replacement
-- ============================================================
-- The global hash primary key is the concurrency boundary. The old owner
-- unique key prevented an AFTER UPDATE trigger from inserting the new hash
-- before releasing the old hash. Replacement must be safe regardless of
-- trigger execution order, so ownership is validated by the hash key and
-- the lifecycle triggers.

USE iscan_db;

ALTER TABLE pdf_fingerprints
    DROP INDEX uq_pdf_fingerprint_owner;

