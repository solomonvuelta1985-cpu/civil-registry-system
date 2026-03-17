-- ============================================================
-- Migration 007: PDF Hash + Backup Table
-- iScan Civil Registry Records Management System
-- ============================================================
-- Purpose: Adds SHA-256 hash column to all 4 certificate tables
--          for integrity verification, and creates the pdf_backups
--          table to track old PDF versions moved on update.
--
-- Run:
--   mysql -u root iscan_db < database/migrations/007_add_pdf_hash.sql
-- ============================================================

USE iscan_db;

-- Add pdf_hash to Birth Certificates
ALTER TABLE certificate_of_live_birth
    ADD COLUMN IF NOT EXISTS pdf_hash CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF for integrity verification'
    AFTER pdf_filepath;

-- Add pdf_hash to Death Certificates
ALTER TABLE certificate_of_death
    ADD COLUMN IF NOT EXISTS pdf_hash CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF for integrity verification'
    AFTER pdf_filepath;

-- Add pdf_hash to Marriage Certificates
ALTER TABLE certificate_of_marriage
    ADD COLUMN IF NOT EXISTS pdf_hash CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF for integrity verification'
    AFTER pdf_filepath;

-- Add pdf_hash to Marriage License Applications
ALTER TABLE application_for_marriage_license
    ADD COLUMN IF NOT EXISTS pdf_hash CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF for integrity verification'
    AFTER pdf_filepath;

-- Backup tracking table
-- Stores old PDF versions that were replaced on record update.
-- Files are moved to uploads/backup/ instead of deleted.
CREATE TABLE IF NOT EXISTS pdf_backups (
    id            INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    cert_type     ENUM('birth','death','marriage','marriage_license') NOT NULL
                  COMMENT 'Certificate type this backup belongs to',
    record_id     INT UNSIGNED      NOT NULL
                  COMMENT 'ID of the certificate record that owned this PDF',
    original_path VARCHAR(255)      NOT NULL
                  COMMENT 'Relative path the PDF had when it was current (under uploads/)',
    backup_path   VARCHAR(255)      NOT NULL
                  COMMENT 'Relative path where backup now lives (under uploads/backup/)',
    file_hash     CHAR(64)          NULL
                  COMMENT 'SHA-256 of the backup file at time of backup',
    backed_up_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    backed_up_by  INT UNSIGNED      NULL
                  COMMENT 'User ID who triggered the update (causing backup)',
    restored_at   TIMESTAMP         NULL
                  COMMENT 'Timestamp if/when this backup was restored as the current file',
    restored_by   INT UNSIGNED      NULL
                  COMMENT 'User ID who performed the restore',

    INDEX idx_record     (cert_type, record_id),
    INDEX idx_backed_up  (backed_up_at),
    INDEX idx_restored   (restored_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks old PDF versions preserved when records are updated';
