-- Migration 025: PDF Backup Manager extensions
-- Adds similarity-hash, verification timestamp, and cached file size to pdf_backups.
-- Powers near-duplicate detection (chunked-hash + Hamming distance) and faster stats.

ALTER TABLE pdf_backups
    ADD COLUMN sim_hash    CHAR(16)         NULL AFTER file_hash,
    ADD COLUMN verified_at TIMESTAMP        NULL AFTER restored_by,
    ADD COLUMN file_size   BIGINT UNSIGNED  NULL AFTER backup_path;

CREATE INDEX idx_sim_hash    ON pdf_backups (sim_hash);
CREATE INDEX idx_verified_at ON pdf_backups (verified_at);
