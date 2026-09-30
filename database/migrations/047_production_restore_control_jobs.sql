-- ============================================================
-- Migration 047: Controlled production PDF restore approvals
-- ============================================================

CREATE TABLE IF NOT EXISTS production_restore_jobs (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recovery_job_id       BIGINT UNSIGNED NOT NULL,
    pre_restore_backup_job_id BIGINT UNSIGNED NULL,
    status                ENUM('backup_running','awaiting_approval','approved','running','completed','completed_with_errors','failed','cancelled') NOT NULL DEFAULT 'backup_running',
    restore_scope         ENUM('verified_pdf_recovery') NOT NULL DEFAULT 'verified_pdf_recovery',
    strategy              ENUM('missing_and_corrupt') NOT NULL DEFAULT 'missing_and_corrupt',
    source_root           VARCHAR(1000) NOT NULL,
    destination_root      VARCHAR(1000) NOT NULL,
    created_by            INT UNSIGNED NULL,
    first_approved_by     INT UNSIGNED NULL,
    second_approved_by    INT UNSIGNED NULL,
    first_approved_at     DATETIME NULL,
    second_approved_at    DATETIME NULL,
    last_error            TEXT NULL,
    started_at            DATETIME NULL,
    last_activity_at      DATETIME NULL,
    completed_at          DATETIME NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_production_restore_recovery (recovery_job_id),
    KEY idx_production_restore_status (status),
    KEY idx_production_restore_backup (pre_restore_backup_job_id),
    KEY idx_production_restore_created (created_at),
    CONSTRAINT fk_production_restore_recovery
        FOREIGN KEY (recovery_job_id) REFERENCES pdf_protection_jobs(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_production_restore_backup
        FOREIGN KEY (pre_restore_backup_job_id) REFERENCES database_backup_jobs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Two-person approval gates for verified production PDF recovery';

CREATE TABLE IF NOT EXISTS production_restore_approvals (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    stage       TINYINT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_production_restore_stage (job_id, stage),
    UNIQUE KEY uq_production_restore_user (job_id, user_id),
    KEY idx_production_restore_approval_user (user_id),
    CONSTRAINT fk_production_restore_approval_job
        FOREIGN KEY (job_id) REFERENCES production_restore_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Separate administrator approvals for production PDF recovery';

CREATE TABLE IF NOT EXISTS production_restore_job_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    level       ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message     VARCHAR(1000) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_production_restore_log_job (job_id, created_at),
    CONSTRAINT fk_production_restore_log_job
        FOREIGN KEY (job_id) REFERENCES production_restore_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log for controlled production restore gates';
