-- ============================================================
-- Migration 045: Verified full database backup jobs
-- ============================================================

CREATE TABLE IF NOT EXISTS database_backup_jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status          ENUM('queued','running','completed','completed_with_errors','failed','cancelled') NOT NULL DEFAULT 'queued',
    destination_root VARCHAR(1000) NOT NULL,
    backup_folder   VARCHAR(1000) NOT NULL,
    dump_path       VARCHAR(1500) NULL,
    manifest_path   VARCHAR(1500) NULL,
    database_name   VARCHAR(255) NOT NULL,
    database_host   VARCHAR(255) NOT NULL,
    file_size       BIGINT UNSIGNED NULL,
    file_hash       CHAR(64) NULL,
    manifest_hash   CHAR(64) NULL,
    last_error      TEXT NULL,
    created_by      INT UNSIGNED NULL,
    started_at      DATETIME NULL,
    last_activity_at DATETIME NULL,
    completed_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_database_backup_status (status),
    KEY idx_database_backup_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Verified full database backup jobs and manifests';

CREATE TABLE IF NOT EXISTS database_backup_job_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    level       ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message     VARCHAR(1000) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_database_backup_log_job (job_id, created_at),
    CONSTRAINT fk_database_backup_log_job FOREIGN KEY (job_id)
        REFERENCES database_backup_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log for database backup jobs';
