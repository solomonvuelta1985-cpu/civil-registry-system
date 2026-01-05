-- ============================================================================
-- MIGRATION 003: Calendar & Notes System for Operational Management
-- Purpose: Add calendar events and contextual notes for LGU operations
-- Date: 2026-01-04
-- ============================================================================

-- ============================================================================
-- 1. CALENDAR EVENTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Event Information
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `event_type` ENUM('registration', 'deadline', 'maintenance', 'digitization', 'meeting', 'other') NOT NULL,
  `certificate_type` ENUM('birth', 'marriage', 'death', 'license', 'all') DEFAULT 'all',

  -- Date & Time
  `event_date` DATE NOT NULL,
  `event_time` TIME NULL,
  `end_date` DATE NULL COMMENT 'For multi-day events',
  `all_day` BOOLEAN DEFAULT FALSE,

  -- Categorization & Linking
  `barangay` VARCHAR(100) NULL COMMENT 'Associated barangay if applicable',
  `registry_number` VARCHAR(100) NULL COMMENT 'Link to specific record',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',

  -- Status & Tracking
  `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
  `reminder_sent` BOOLEAN DEFAULT FALSE,
  `reminder_days_before` TINYINT DEFAULT 1 COMMENT 'Days before to send reminder',

  -- Visual & Display
  `color_code` VARCHAR(7) NULL COMMENT 'Hex color for calendar display',
  `icon` VARCHAR(50) NULL COMMENT 'Icon name for display',

  -- Metadata
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL COMMENT 'Soft delete',

  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_certificate_type` (`certificate_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_barangay` (`barangay`),

  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar events for operational planning and tracking';

-- ============================================================================
-- 2. SYSTEM NOTES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `system_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Note Content
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `note_type` ENUM('operational', 'administrative', 'technical', 'audit', 'compliance', 'other') NOT NULL,

  -- Categorization & Context
  `certificate_type` ENUM('birth', 'marriage', 'death', 'license', 'all', 'none') DEFAULT 'none',
  `registry_number` VARCHAR(100) NULL COMMENT 'Link to specific record',
  `barangay` VARCHAR(100) NULL,
  `event_date` DATE NULL COMMENT 'Date this note refers to',

  -- Linking to Records
  `linked_certificate_id` INT UNSIGNED NULL,
  `linked_event_id` INT UNSIGNED NULL,

  -- Priority & Visibility
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `is_pinned` BOOLEAN DEFAULT FALSE COMMENT 'Pin to top of notes list',
  `visibility` ENUM('private', 'team', 'public') DEFAULT 'team' COMMENT 'Who can see this note',

  -- Status & Protection
  `status` ENUM('draft', 'active', 'archived') DEFAULT 'active',
  `is_locked` BOOLEAN DEFAULT FALSE COMMENT 'Prevent editing (audit protection)',
  `locked_by` INT UNSIGNED NULL,
  `locked_at` DATETIME NULL,

  -- Attachments
  `has_attachment` BOOLEAN DEFAULT FALSE,
  `attachment_path` VARCHAR(500) NULL,

  -- Metadata
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL COMMENT 'Soft delete',

  INDEX `idx_note_type` (`note_type`),
  INDEX `idx_certificate_type` (`certificate_type`),
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_is_pinned` (`is_pinned`),
  INDEX `idx_status` (`status`),
  INDEX `idx_registry_number` (`registry_number`),
  INDEX `idx_barangay` (`barangay`),
  FULLTEXT INDEX `idx_content_search` (`title`, `content`),

  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`locked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`linked_event_id`) REFERENCES `calendar_events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contextual notes for institutional memory and operational tracking';

-- ============================================================================
-- 3. NOTE TAGS TABLE (For Better Organization)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `note_tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tag_name` VARCHAR(50) NOT NULL UNIQUE,
  `tag_color` VARCHAR(7) NULL COMMENT 'Hex color code',
  `usage_count` INT UNSIGNED DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_tag_name` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tags for organizing notes';

-- ============================================================================
-- 4. NOTE-TAG RELATIONSHIP TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `note_tag_relations` (
  `note_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`note_id`, `tag_id`),
  FOREIGN KEY (`note_id`) REFERENCES `system_notes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `note_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Many-to-many relationship between notes and tags';

-- ============================================================================
-- 5. CALENDAR EVENT REMINDERS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `event_reminders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reminder_date` DATE NOT NULL,
  `reminder_time` TIME NOT NULL,
  `sent` BOOLEAN DEFAULT FALSE,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_reminder_date` (`reminder_date`),
  INDEX `idx_sent` (`sent`),

  FOREIGN KEY (`event_id`) REFERENCES `calendar_events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Reminder notifications for calendar events';

-- ============================================================================
-- 6. INSERT SAMPLE DATA (Optional - for demonstration)
-- ============================================================================

-- Sample Calendar Events
INSERT INTO `calendar_events` (`title`, `description`, `event_type`, `certificate_type`, `event_date`, `priority`, `color_code`, `created_by`)
VALUES
('PSA Monthly Report Deadline', 'Submit monthly statistics to PSA', 'deadline', 'all', '2026-02-05', 'high', '#ef4444', 1),
('Barangay San Isidro Bulk Submission', 'Expected delayed birth certificates from flood-affected area', 'registration', 'birth', '2026-01-15', 'medium', '#3b82f6', 1),
('System Maintenance Window', 'Database optimization and backup', 'maintenance', 'all', '2026-01-20', 'low', '#64748b', 1);

-- Sample System Notes
INSERT INTO `system_notes` (`title`, `content`, `note_type`, `certificate_type`, `barangay`, `event_date`, `priority`, `is_pinned`, `created_by`)
VALUES
('Delayed Registrations Explanation', 'Barangay San Isidro submitted delayed birth records (Dec 12-18) due to severe flooding. Registry operations were suspended during this period. All documents verified and compliant.', 'operational', 'birth', 'San Isidro', '2025-12-18', 'high', TRUE, 1),
('New PSA Guidelines', 'PSA issued new guidelines for marriage certificate annotation procedures. All staff trained on Jan 10, 2026.', 'compliance', 'marriage', NULL, '2026-01-10', 'medium', TRUE, 1),
('Digitization Milestone', 'Completed digitization of all 2020 marriage certificates (total: 543 records). Scans uploaded and verified.', 'administrative', 'marriage', NULL, '2026-01-04', 'medium', FALSE, 1);

-- Sample Tags
INSERT INTO `note_tags` (`tag_name`, `tag_color`, `usage_count`)
VALUES
('flood-impact', '#fbbf24', 1),
('psa-guidelines', '#3b82f6', 1),
('milestone', '#22c55e', 1),
('training', '#8b5cf6', 1),
('emergency', '#ef4444', 0);

-- ============================================================================
-- 7. VIEWS FOR COMMON QUERIES
-- ============================================================================

-- View for upcoming events (next 30 days)
CREATE OR REPLACE VIEW `vw_upcoming_events` AS
SELECT
    e.*,
    u.full_name as created_by_name,
    u.role as created_by_role,
    DATEDIFF(e.event_date, CURDATE()) as days_until_event
FROM calendar_events e
LEFT JOIN users u ON e.created_by = u.id
WHERE e.event_date >= CURDATE()
  AND e.event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND e.deleted_at IS NULL
  AND e.status != 'cancelled'
ORDER BY e.event_date ASC, e.event_time ASC;

-- View for today's events
CREATE OR REPLACE VIEW `vw_today_events` AS
SELECT
    e.*,
    u.full_name as created_by_name,
    u.role as created_by_role
FROM calendar_events e
LEFT JOIN users u ON e.created_by = u.id
WHERE DATE(e.event_date) = CURDATE()
  AND e.deleted_at IS NULL
  AND e.status != 'cancelled'
ORDER BY e.event_time ASC;

-- View for pinned notes
CREATE OR REPLACE VIEW `vw_pinned_notes` AS
SELECT
    n.*,
    u.full_name as created_by_name,
    u.role as created_by_role
FROM system_notes n
LEFT JOIN users u ON n.created_by = u.id
WHERE n.is_pinned = TRUE
  AND n.deleted_at IS NULL
  AND n.status = 'active'
ORDER BY n.created_at DESC;

-- ============================================================================
-- ROLLBACK SCRIPT (Keep for reference)
-- ============================================================================
/*
-- To rollback this migration:

DROP VIEW IF EXISTS `vw_pinned_notes`;
DROP VIEW IF EXISTS `vw_today_events`;
DROP VIEW IF EXISTS `vw_upcoming_events`;

DROP TABLE IF EXISTS `event_reminders`;
DROP TABLE IF EXISTS `note_tag_relations`;
DROP TABLE IF EXISTS `note_tags`;
DROP TABLE IF EXISTS `system_notes`;
DROP TABLE IF EXISTS `calendar_events`;
*/
