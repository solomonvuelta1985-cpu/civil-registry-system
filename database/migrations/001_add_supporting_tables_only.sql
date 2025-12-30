-- ============================================================================
-- MIGRATION 001: Add Supporting Tables (NO CHANGES TO EXISTING TABLES)
-- Purpose: Add new features through separate tables, keeping existing schema intact
-- Date: 2025-12-27
-- ============================================================================
-- IMPORTANT: This migration does NOT alter certificate_of_live_birth or
--            certificate_of_marriage tables. It only adds NEW tables.
-- ============================================================================

-- ============================================================================
-- 1. PDF ATTACHMENTS TABLE WITH OCR SUPPORT
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
  `ocr_data_json` JSON NULL COMMENT 'Structured OCR data with field mappings',

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
COMMENT='Tracks PDF versions and OCR data separately from main tables';

-- ============================================================================
-- 2. WORKFLOW STATES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `workflow_states` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NOT NULL,

  -- Current state
  `current_state` ENUM('draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived') DEFAULT 'draft',
  `data_quality_score` DECIMAL(5,2) NULL COMMENT 'Overall confidence score 0-100',

  -- Verification
  `verified_by` INT UNSIGNED NULL,
  `verified_at` DATETIME NULL,
  `verification_notes` TEXT NULL,

  -- Approval
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `approval_notes` TEXT NULL,

  -- Rejection
  `rejected_by` INT UNSIGNED NULL,
  `rejected_at` DATETIME NULL,
  `rejection_reason` TEXT NULL,

  -- Timestamps
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `unique_certificate` (`certificate_type`, `certificate_id`),
  INDEX `idx_current_state` (`current_state`),
  INDEX `idx_verified_by` (`verified_by`),
  INDEX `idx_approved_by` (`approved_by`),

  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`rejected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manages workflow states separately from certificate tables';

-- ============================================================================
-- 3. WORKFLOW TRANSITIONS TABLE
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
COMMENT='Audit trail of all workflow state changes';

-- ============================================================================
-- 4. CERTIFICATE VERSIONS TABLE
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
-- 5. VALIDATION DISCREPANCIES TABLE
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
  `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',

  -- Resolution
  `status` ENUM('open', 'resolved', 'ignored', 'escalated') DEFAULT 'open',
  `resolution_value` TEXT NULL COMMENT 'Final corrected value',
  `resolution_notes` TEXT NULL,
  `resolved_by` INT UNSIGNED NULL,
  `resolved_at` DATETIME NULL,

  -- Audit
  `detected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `detected_by` INT UNSIGNED NULL COMMENT 'User or system (NULL = automated)',

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_severity` (`severity`),
  INDEX `idx_discrepancy_type` (`discrepancy_type`),
  INDEX `idx_detected_at` (`detected_at`),

  FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks discrepancies between manual entry and PDF/OCR data';

-- ============================================================================
-- 6. OCR PROCESSING QUEUE TABLE
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
-- 7. BATCH UPLOADS TABLE
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
-- 8. BATCH UPLOAD ITEMS TABLE
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
-- 9. QA SAMPLING TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `qa_samples` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `certificate_type` ENUM('birth', 'marriage', 'death') NOT NULL,
  `certificate_id` INT UNSIGNED NOT NULL,

  -- Sampling info
  `sample_date` DATE NOT NULL,
  `sample_batch` VARCHAR(100) NULL COMMENT 'Batch identifier',
  `sampling_method` ENUM('random', 'targeted', 'problematic', 'high_risk') DEFAULT 'random',

  -- Review
  `reviewer_id` INT UNSIGNED NULL,
  `review_date` DATE NULL,
  `review_status` ENUM('pending', 'in_progress', 'passed', 'failed') DEFAULT 'pending',

  -- QA Results
  `errors_found` TINYINT UNSIGNED DEFAULT 0,
  `error_details` JSON NULL COMMENT 'Array of errors found',
  `overall_rating` ENUM('excellent', 'good', 'fair', 'poor') NULL,
  `reviewer_notes` TEXT NULL,

  -- Original encoder tracking
  `original_encoder_id` INT UNSIGNED NULL,

  -- Timestamps
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_certificate_lookup` (`certificate_type`, `certificate_id`),
  INDEX `idx_review_status` (`review_status`),
  INDEX `idx_reviewer` (`reviewer_id`),
  INDEX `idx_sample_date` (`sample_date`),
  INDEX `idx_original_encoder` (`original_encoder_id`),

  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`original_encoder_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Quality assurance sampling and review tracking';

-- ============================================================================
-- 10. USER PERFORMANCE METRICS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_performance_metrics` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `metric_date` DATE NOT NULL,

  -- Productivity
  `records_created` SMALLINT UNSIGNED DEFAULT 0,
  `records_updated` SMALLINT UNSIGNED DEFAULT 0,
  `records_verified` SMALLINT UNSIGNED DEFAULT 0,
  `records_approved` SMALLINT UNSIGNED DEFAULT 0,

  -- Quality
  `qa_samples_reviewed` SMALLINT UNSIGNED DEFAULT 0,
  `qa_samples_passed` SMALLINT UNSIGNED DEFAULT 0,
  `qa_samples_failed` SMALLINT UNSIGNED DEFAULT 0,
  `error_rate_percentage` DECIMAL(5,2) DEFAULT 0.00,

  -- Accuracy
  `average_quality_score` DECIMAL(5,2) NULL,

  -- Time tracking
  `total_time_minutes` INT UNSIGNED DEFAULT 0,
  `average_time_per_record` DECIMAL(8,2) NULL COMMENT 'Minutes per record',

  UNIQUE KEY `unique_user_date` (`user_id`, `metric_date`),
  INDEX `idx_metric_date` (`metric_date`),
  INDEX `idx_user_id` (`user_id`),

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily performance metrics per user';

-- ============================================================================
-- 11. SYSTEM SETTINGS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
  `category` VARCHAR(50) NULL COMMENT 'Group related settings',
  `description` TEXT NULL,
  `is_public` BOOLEAN DEFAULT FALSE COMMENT 'Can non-admins see this?',
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_category` (`category`),
  INDEX `idx_is_public` (`is_public`),

  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='System-wide configuration settings';

-- ============================================================================
-- 12. INSERT DEFAULT SYSTEM SETTINGS
-- ============================================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`) VALUES
('ocr_enabled', 'true', 'boolean', 'OCR', 'Enable OCR processing for uploaded PDFs', FALSE),
('ocr_default_engine', 'tesseract', 'string', 'OCR', 'Default OCR engine (tesseract, google-vision, aws-textract)', FALSE),
('ocr_auto_process', 'true', 'boolean', 'OCR', 'Automatically process OCR on PDF upload', FALSE),
('ocr_confidence_threshold', '75.00', 'number', 'OCR', 'Minimum confidence score to auto-fill fields (0-100)', FALSE),
('workflow_require_verification', 'true', 'boolean', 'Workflow', 'Require verification before approval', FALSE),
('workflow_auto_approve_high_quality', 'false', 'boolean', 'Workflow', 'Auto-approve records with quality score > 95', FALSE),
('qa_sample_percentage', '10.00', 'number', 'QA', 'Percentage of records to sample for QA (0-100)', FALSE),
('qa_enabled', 'true', 'boolean', 'QA', 'Enable quality assurance sampling', FALSE),
('batch_upload_enabled', 'true', 'boolean', 'Batch', 'Enable batch upload feature', FALSE),
('batch_max_files', '100', 'number', 'Batch', 'Maximum files per batch upload', FALSE),
('versioning_enabled', 'true', 'boolean', 'Versioning', 'Track all versions of certificates', FALSE),
('max_file_size_mb', '10', 'number', 'Upload', 'Maximum PDF file size in MB', TRUE),
('allowed_file_types', 'pdf', 'string', 'Upload', 'Allowed file extensions (comma-separated)', TRUE);

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================

SELECT 'Migration 001 completed successfully! Supporting tables created.' AS status;
