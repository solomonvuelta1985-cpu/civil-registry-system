-- ============================================================
-- Migration 049: Full database restore and rollback controls
-- ============================================================

USE iscan_db;

CREATE TABLE IF NOT EXISTS database_live_restore_jobs (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    preview_job_id        BIGINT UNSIGNED NOT NULL,
    pre_restore_backup_job_id BIGINT UNSIGNED NULL,
    status                ENUM('backup_running','awaiting_approval','approved','running','completed','completed_with_errors','failed','cancelled','rollback_awaiting_approval','rolling_back','rolled_back') NOT NULL DEFAULT 'backup_running',
    restore_mode          ENUM('full_database_replace') NOT NULL DEFAULT 'full_database_replace',
    source_database_name  VARCHAR(255) NOT NULL,
    target_database_name  VARCHAR(255) NOT NULL,
    target_database_host  VARCHAR(255) NOT NULL,
    created_by            INT UNSIGNED NULL,
    started_at            DATETIME NULL,
    last_activity_at      DATETIME NULL,
    completed_at          DATETIME NULL,
    last_error            TEXT NULL,
    target_table_count    BIGINT UNSIGNED NULL,
    expected_table_count  BIGINT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_database_live_restore_preview (preview_job_id),
    KEY idx_database_live_restore_status (status),
    KEY idx_database_live_restore_backup (pre_restore_backup_job_id),
    KEY idx_database_live_restore_created (created_at),
    CONSTRAINT fk_database_live_restore_preview
        FOREIGN KEY (preview_job_id) REFERENCES database_restore_preview_jobs(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_database_live_restore_backup
        FOREIGN KEY (pre_restore_backup_job_id) REFERENCES database_backup_jobs(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Two-person controlled full database restore and rollback jobs';

CREATE TABLE IF NOT EXISTS database_live_restore_approvals (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    action      ENUM('restore','rollback') NOT NULL,
    stage       TINYINT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_database_live_restore_approval_stage (job_id, action, stage),
    UNIQUE KEY uq_database_live_restore_approval_user (job_id, action, user_id),
    KEY idx_database_live_restore_approval_user (user_id),
    CONSTRAINT fk_database_live_restore_approval_job
        FOREIGN KEY (job_id) REFERENCES database_live_restore_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Separate administrator approvals for database restore and rollback';

CREATE TABLE IF NOT EXISTS database_live_restore_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NOT NULL,
    level       ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message     VARCHAR(1000) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_database_live_restore_log_job (job_id, created_at),
    CONSTRAINT fk_database_live_restore_log_job
        FOREIGN KEY (job_id) REFERENCES database_live_restore_jobs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log for full database restore and rollback operations';
