-- ============================================================
-- Migration 046: Isolated database restore preview jobs
-- ============================================================

CREATE TABLE IF NOT EXISTS database_restore_preview_jobs (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backup_job_id         BIGINT UNSIGNED NOT NULL,
    status                ENUM('queued','running','completed','completed_with_errors','failed','cancelled','cleaned') NOT NULL DEFAULT 'queued',
    staging_database_name VARCHAR(255) NULL,
    staging_database_host VARCHAR(255) NOT NULL,
    dump_path             VARCHAR(1500) NOT NULL,
    manifest_path         VARCHAR(1500) NOT NULL,
    expected_file_hash    CHAR(64) NULL,
    actual_file_hash      CHAR(64) NULL,
    expected_manifest_hash CHAR(64) NULL,
    actual_manifest_hash  CHAR(64) NULL,
    dump_size             BIGINT UNSIGNED NULL,
    table_statements      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    insert_statements     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    staging_table_count   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_reference_count   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_present_count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_missing_count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    pdf_invalid_count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    report_path           VARCHAR(1500) NULL,
    last_error            TEXT NULL,
    created_by            INT UNSIGNED NULL,
    started_at            DATETIME NULL,
    last_activity_at      DATETIME NULL,
    completed_at          DATETIME NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_restore_preview_status (status),
    KEY idx_restore_preview_backup (backup_job_id),
    KEY idx_restore_preview_created (created_at),
    CONSTRAINT fk_restore_preview_backup
        FOREIGN KEY (backup_job_id) REFERENCES database_backup_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Isolated database restore previews and PDF reference validation';

CREATE TABLE IF NOT EXISTS database_restore_preview_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    level       ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message     VARCHAR(1000) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_restore_preview_log_job (job_id, created_at),
    CONSTRAINT fk_restore_preview_log_job FOREIGN KEY (job_id)
        REFERENCES database_restore_preview_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log for isolated database restore previews';
