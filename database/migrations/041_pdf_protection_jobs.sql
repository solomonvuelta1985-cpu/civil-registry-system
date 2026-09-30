-- ============================================================
-- Migration 041: PDF Recovery and Incremental Backup Jobs
-- iSCAN Civil Registry Records Management System
-- ============================================================

CREATE TABLE IF NOT EXISTS pdf_protection_jobs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type            ENUM('recovery','backup') NOT NULL,
    status              ENUM('queued','scanning','preview_ready','approved',
                             'running','paused','completed','completed_with_errors','failed','cancelled')
                        NOT NULL DEFAULT 'queued',
    source_root         VARCHAR(500) NOT NULL,
    destination_root    VARCHAR(500) NULL,
    created_by          INT UNSIGNED NULL,
    approved_by         INT UNSIGNED NULL,
    total_items         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    scanned_items       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    copied_items        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    skipped_items       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    failed_items        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    review_items        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes_scanned       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes_copied        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_error          TEXT NULL,
    manifest_hash       CHAR(64) NULL,
    started_at          DATETIME NULL,
    last_activity_at    DATETIME NULL,
    completed_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_pdf_job_status (status),
    KEY idx_pdf_job_type_status (job_type, status),
    KEY idx_pdf_job_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Batch jobs for PDF recovery and incremental backup operations';

CREATE TABLE IF NOT EXISTS pdf_protection_job_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id              BIGINT UNSIGNED NOT NULL,
    source_path         VARCHAR(1000) NOT NULL,
    destination_path    VARCHAR(1000) NULL,
    source_filename     VARCHAR(255) NULL,
    source_size         BIGINT UNSIGNED NULL,
    source_mtime        DATETIME NULL,
    source_hash         CHAR(64) NULL,
    destination_hash    CHAR(64) NULL,
    expected_hash       CHAR(64) NULL,
    cert_type           VARCHAR(40) NULL,
    record_id           BIGINT UNSIGNED NULL,
    registry_no         VARCHAR(100) NULL,
    match_method        ENUM('exact_hash','backup_hash','path_metadata','manual','none')
                        NOT NULL DEFAULT 'none',
    status              ENUM('queued','scanned','already_present','matched','imported',
                             'skipped','needs_review','unmatched','invalid_pdf','corrupt_source',
                             'duplicate_source','failed')
                        NOT NULL DEFAULT 'queued',
    attempts            INT UNSIGNED NOT NULL DEFAULT 0,
    last_error          TEXT NULL,
    reviewed_by         INT UNSIGNED NULL,
    reviewed_at         DATETIME NULL,
    imported_at         DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_pdf_job_source (job_id, source_path(255)),
    KEY idx_pdf_job_item_status (job_id, status),
    KEY idx_pdf_job_item_hash (source_hash),
    KEY idx_pdf_job_item_record (cert_type, record_id),
    CONSTRAINT fk_pdf_job_item_job
        FOREIGN KEY (job_id) REFERENCES pdf_protection_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-file progress, matching, and audit state for PDF jobs';

CREATE TABLE IF NOT EXISTS pdf_backup_manifest_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manifest_id         BIGINT UNSIGNED NOT NULL,
    relative_path       VARCHAR(1000) NOT NULL,
    file_size            BIGINT UNSIGNED NOT NULL,
    file_mtime           DATETIME NULL,
    file_hash            CHAR(64) NOT NULL,
    verified_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_pdf_manifest_path (manifest_id, relative_path(255)),
    KEY idx_pdf_manifest_hash (file_hash),
    CONSTRAINT fk_pdf_manifest_item_manifest
        FOREIGN KEY (manifest_id) REFERENCES pdf_protection_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Verified file manifest used to make incremental PDF backups efficient';

