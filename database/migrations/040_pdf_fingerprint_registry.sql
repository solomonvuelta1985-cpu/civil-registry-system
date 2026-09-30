-- ============================================================
-- Migration 040: Global PDF Fingerprint Registry
-- iSCAN Civil Registry Records Management System
-- ============================================================
-- Purpose:
--   Provides one database-enforced ownership row per active PDF hash.
--   This closes the race condition where two simultaneous requests can
--   both pass an application-level duplicate check before either record
--   is inserted.
--
-- Important:
--   This table is intended to contain hashes currently reserved by active
--   certificate records. Create/update/delete/archive/restore workflows
--   must maintain this registry in the same database transaction as the
--   certificate record. Historical ownership belongs in the audit log or
--   a separate history table, not in this unique reservation table.
-- ============================================================

CREATE TABLE IF NOT EXISTS pdf_fingerprints (
    pdf_hash       CHAR(64)     NOT NULL,
    cert_type      VARCHAR(40)  NOT NULL,
    record_id      BIGINT UNSIGNED NOT NULL,
    registry_no    VARCHAR(100) NULL,
    reserved_by    INT UNSIGNED NULL,
    reserved_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (pdf_hash),
    UNIQUE KEY uq_pdf_fingerprint_owner (cert_type, record_id),
    KEY idx_pdf_fingerprint_record (cert_type, record_id),
    KEY idx_pdf_fingerprint_registry (registry_no),

    CONSTRAINT chk_pdf_fingerprint_hash
        CHECK (pdf_hash REGEXP '^[0-9A-Fa-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Database-enforced reservations for active PDF SHA-256 fingerprints';

