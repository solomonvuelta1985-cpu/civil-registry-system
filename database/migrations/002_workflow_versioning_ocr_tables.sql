-- ============================================================================
-- MIGRATION 002: Workflow States, Versioning, OCR Support Tables
-- Purpose: Add workflow management, document versioning, and OCR capabilities
-- Date: 2025-12-27
-- ============================================================================

-- ============================================================================
-- 1. ADD WORKFLOW STATE COLUMNS TO EXISTING TABLES
-- ============================================================================

-- Add workflow to birth certificates
ALTER TABLE `certificate_of_live_birth`
ADD COLUMN `workflow_state` ENUM('draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived') DEFAULT 'draft' AFTER `status`,
ADD COLUMN `verified_by` INT NULL COMMENT 'User ID who verified' AFTER `workflow_state`,
ADD COLUMN `verified_at` DATETIME NULL AFTER `verified_by`,
ADD COLUMN `approved_by` INT NULL COMMENT 'User ID who approved' AFTER `verified_at`,
ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by`,
ADD COLUMN `rejected_by` INT NULL COMMENT 'User ID who rejected' AFTER `approved_at`,
ADD COLUMN `rejected_at` DATETIME NULL AFTER `rejected_by`,
ADD COLUMN `rejection_reason` TEXT NULL AFTER `rejected_at`,
ADD COLUMN `data_quality_score` DECIMAL(5,2) NULL COMMENT 'Confidence score 0-100' AFTER `rejection_reason`,
ADD INDEX `idx_workflow_state` (`workflow_state`),
ADD INDEX `idx_verified_by` (`verified_by`),
ADD INDEX `idx_approved_by` (`approved_by`);

-- Add workflow to marriage certificates
ALTER TABLE `certificate_of_marriage`
ADD COLUMN `workflow_state` ENUM('draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived') DEFAULT 'draft' AFTER `status`,
ADD COLUMN `verified_by` INT NULL AFTER `workflow_state`,
ADD COLUMN `verified_at` DATETIME NULL AFTER `verified_by`,
ADD COLUMN `approved_by` INT NULL AFTER `verified_at`,
ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by`,
ADD COLUMN `rejected_by` INT NULL AFTER `approved_at`,
ADD COLUMN `rejected_at` DATETIME NULL AFTER `rejected_by`,
ADD COLUMN `rejection_reason` TEXT NULL AFTER `rejected_at`,
ADD COLUMN `data_quality_score` DECIMAL(5,2) NULL COMMENT 'Confidence score 0-100' AFTER `rejection_reason`,
ADD INDEX `idx_workflow_state` (`workflow_state`),
ADD INDEX `idx_verified_by` (`verified_by`),
ADD INDEX `idx_approved_by` (`approved_by`);

-- ============================================================================
-- 2. CREATE PDF ATTACHMENTS TABLE WITH OCR SUPPORT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `pdf_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL COMMENT 'Type of certificate',
  `certificate_id` INT UNSIGNED NOT NULL COMMENT 'ID in respective certificate table',

  -- File information
  `file_name` VARCHAR(255) NOT NULL COMMENT 'Original filename',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Storage path',
  `file_size` INT UNSIGNED NOT NULL COMMENT 'File size in bytes',
  `file_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for integrity',
  `mime_type` VARCHAR(100) DEFAULT 'application/pdf',

  -- Version tracking
  `version` TINYINT UNSIGNED DEFAULT 1 COMMENT 'Version number for amendments',
  `is_current_version` BOOLEAN DEFAULT TRUE COMMENT 'Is this the active version?',
  `replaced_by_id` INT UNSIGNED NULL COMMENT 'ID of newer version if replaced',
  `version_notes` TEXT NULL COMMENT 'Reason for new version',

  -- OCR data
  `ocr_text` LONGTEXT NULL COMMENT 'Extracted text from PDF',
  `ocr_confidence_score` DECIMAL(5,2) NULL COMMENT 'OCR confidence 0-100',
  `ocr_processed_at` DATETIME NULL,
  `ocr_engine` VARCHAR(50) NULL COMMENT 'tesseract, google-vision, etc.',
  `ocr_language` VARCHAR(10) DEFAULT 'eng' COMMENT 'Language code',

  -- Page information
  `page_count` TINYINT UNSIGNED DEFAULT 1,
  `is_multipage` BOOLEAN DEFAULT FALSE,

  -- Processing status
  `processing_status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  `processing_error` TEXT NULL,

  -- Metadata
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL COMMENT 'Soft delete timestamp',

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_current_version` (`is_current_version`),
  INDEX `idx_file_hash` (`file_hash`),
  INDEX `idx_processing_status` (`processing_status`),
  INDEX `idx_uploaded_by` (`uploaded_by`),
  FULLTEXT INDEX `idx_ocr_text` (`ocr_text`),

  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manages PDF attachments with versioning and OCR support';

-- ============================================================================
-- 3. CREATE CERTIFICATE VERSIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `certificate_versions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NOT NULL,

  -- Version tracking
  `version_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_current` BOOLEAN DEFAULT TRUE,

  -- Snapshot of data at this version
  `data_snapshot` JSON NOT NULL COMMENT 'Complete certificate data at this version',

  -- Change tracking
  `change_type` ENUM('created', 'updated', 'corrected', 'annotated', 'amended') NOT NULL,
  `change_summary` TEXT NULL COMMENT 'Human-readable summary of changes',
  `fields_changed` JSON NULL COMMENT 'Array of field names that changed',

  -- Amendment documentation
  `amendment_type` ENUM('clerical_error', 'legal_correction', 'court_order', 'legitimation', 'adoption', 'other') NULL,
  `supporting_document_path` VARCHAR(500) NULL COMMENT 'Path to court order, affidavit, etc.',
  `amendment_notes` TEXT NULL,

  -- Audit trail
  `changed_by` INT UNSIGNED NOT NULL,
  `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_version_number` (`version_number`),
  INDEX `idx_is_current` (`is_current`),
  INDEX `idx_change_type` (`change_type`),
  INDEX `idx_changed_by` (`changed_by`),

  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,

  UNIQUE KEY `unique_version` (`certificate_type`, `certificate_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks all versions and amendments of certificates';

-- ============================================================================
-- 4. CREATE WORKFLOW TRANSITIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `workflow_transitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NOT NULL,

  -- State transition
  `from_state` ENUM('draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived') NULL,
  `to_state` ENUM('draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived') NOT NULL,

  -- Transition details
  `transition_type` ENUM('submit', 'verify', 'approve', 'reject', 'archive', 'reopen') NOT NULL,
  `notes` TEXT NULL COMMENT 'Reason for transition',
  `automated` BOOLEAN DEFAULT FALSE COMMENT 'Was this an automated transition?',

  -- Audit
  `performed_by` INT UNSIGNED NOT NULL,
  `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_transition_type` (`transition_type`),
  INDEX `idx_performed_by` (`performed_by`),
  INDEX `idx_performed_at` (`performed_at`),

  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks all workflow state transitions for audit trail';

-- ============================================================================
-- 5. CREATE VALIDATION DISCREPANCIES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `validation_discrepancies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NOT NULL,
  `pdf_attachment_id` INT UNSIGNED NULL,

  -- Discrepancy details
  `field_name` VARCHAR(100) NOT NULL COMMENT 'Field with discrepancy',
  `form_value` TEXT NULL COMMENT 'Value from manual entry',
  `pdf_value` TEXT NULL COMMENT 'Value extracted from PDF',
  `discrepancy_type` ENUM('missing', 'mismatch', 'format_error', 'unclear', 'confidence_low') NOT NULL,
  `confidence_score` DECIMAL(5,2) NULL COMMENT 'OCR confidence for this field',

  -- Resolution
  `status` ENUM('open', 'resolved', 'ignored', 'escalated') DEFAULT 'open',
  `resolution_notes` TEXT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,

  -- Audit
  `detected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `detected_by` INT UNSIGNED NULL COMMENT 'User or system (NULL = automated)',

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_discrepancy_type` (`discrepancy_type`),
  INDEX `idx_detected_at` (`detected_at`),

  FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks discrepancies between manual entry and PDF content';

-- ============================================================================
-- 6. CREATE OCR PROCESSING QUEUE TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ocr_processing_queue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pdf_attachment_id` INT UNSIGNED NOT NULL,

  -- Queue management
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `status` ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'queued',
  `attempts` TINYINT UNSIGNED DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED DEFAULT 3,

  -- Processing details
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `processing_time_seconds` INT UNSIGNED NULL,
  `error_message` TEXT NULL,

  -- Configuration
  `ocr_engine` VARCHAR(50) DEFAULT 'tesseract' COMMENT 'Engine to use',
  `language` VARCHAR(10) DEFAULT 'eng',
  `dpi` SMALLINT UNSIGNED DEFAULT 300,

  -- Audit
  `queued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `queued_by` INT UNSIGNED NOT NULL,

  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_pdf_attachment` (`pdf_attachment_id`),
  INDEX `idx_queued_at` (`queued_at`),

  FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`queued_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Queue for processing PDFs through OCR engines';

-- ============================================================================
-- 7. CREATE BATCH UPLOAD TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `batch_uploads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `batch_name` VARCHAR(200) NOT NULL,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,

  -- Batch statistics
  `total_files` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `processed_files` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `successful_files` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `failed_files` SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- Status
  `status` ENUM('uploading', 'queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'uploading',
  `progress_percentage` DECIMAL(5,2) DEFAULT 0.00,

  -- Timing
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `estimated_completion` DATETIME NULL,

  -- Configuration
  `auto_ocr` BOOLEAN DEFAULT TRUE COMMENT 'Automatically process OCR',
  `auto_validate` BOOLEAN DEFAULT TRUE COMMENT 'Automatically validate data',

  -- Audit
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_created_at` (`created_at`),

  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks batch upload operations';

-- ============================================================================
-- 8. CREATE BATCH UPLOAD ITEMS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `batch_upload_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `batch_id` INT UNSIGNED NOT NULL,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NULL COMMENT 'Set after successful processing',
  `pdf_attachment_id` INT UNSIGNED NULL,

  -- File info
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,

  -- Processing
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
  `error_message` TEXT NULL,
  `processing_order` SMALLINT UNSIGNED NOT NULL,

  -- Timestamps
  `processed_at` DATETIME NULL,

  INDEX `idx_batch_id` (`batch_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_processing_order` (`processing_order`),

  FOREIGN KEY (`batch_id`) REFERENCES `batch_uploads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual items in a batch upload';

-- ============================================================================
-- 9. UPDATE ACTIVITY LOGS TABLE
-- ============================================================================

-- Add more detailed action types
ALTER TABLE `activity_logs`
MODIFY COLUMN `action` ENUM(
  'CREATE', 'UPDATE', 'DELETE',
  'SUBMIT', 'VERIFY', 'APPROVE', 'REJECT', 'ARCHIVE',
  'UPLOAD_PDF', 'DELETE_PDF', 'OCR_PROCESS',
  'BATCH_UPLOAD', 'EXPORT', 'PRINT',
  'LOGIN', 'LOGOUT', 'FAILED_LOGIN'
) NOT NULL,
ADD COLUMN `certificate_type` ENUM('birth', 'marriage', 'death') NULL AFTER `action`,
ADD COLUMN `certificate_id` INT UNSIGNED NULL AFTER `certificate_type`,
ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `details`,
ADD COLUMN `user_agent` VARCHAR(500) NULL AFTER `ip_address`,
ADD INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
ADD INDEX `idx_ip_address` (`ip_address`);

-- ============================================================================
-- ROLLBACK SCRIPT (Keep for reference)
-- ============================================================================
/*
-- To rollback this migration:

-- Drop new tables
DROP TABLE IF EXISTS `batch_upload_items`;
DROP TABLE IF EXISTS `batch_uploads`;
DROP TABLE IF EXISTS `ocr_processing_queue`;
DROP TABLE IF EXISTS `validation_discrepancies`;
DROP TABLE IF EXISTS `workflow_transitions`;
DROP TABLE IF EXISTS `certificate_versions`;
DROP TABLE IF EXISTS `pdf_attachments`;

-- Revert birth certificate changes
ALTER TABLE `certificate_of_live_birth`
DROP COLUMN `workflow_state`,
DROP COLUMN `verified_by`,
DROP COLUMN `verified_at`,
DROP COLUMN `approved_by`,
DROP COLUMN `approved_at`,
DROP COLUMN `rejected_by`,
DROP COLUMN `rejected_at`,
DROP COLUMN `rejection_reason`,
DROP COLUMN `data_quality_score`;

-- Revert marriage certificate changes
ALTER TABLE `certificate_of_marriage`
DROP COLUMN `workflow_state`,
DROP COLUMN `verified_by`,
DROP COLUMN `verified_at`,
DROP COLUMN `approved_by`,
DROP COLUMN `approved_at`,
DROP COLUMN `rejected_by`,
DROP COLUMN `rejected_at`,
DROP COLUMN `rejection_reason`,
DROP COLUMN `data_quality_score`;

-- Revert activity logs changes
ALTER TABLE `activity_logs`
MODIFY COLUMN `action` VARCHAR(50) NOT NULL,
DROP COLUMN `certificate_type`,
DROP COLUMN `certificate_id`,
DROP COLUMN `ip_address`,
DROP COLUMN `user_agent`;
*/
