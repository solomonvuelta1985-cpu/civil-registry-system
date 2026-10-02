-- Persist staff decisions that a suggested birth-record pair is not the same person.
-- Fingerprints let the matcher reconsider the pair after identifying fields change.
CREATE TABLE IF NOT EXISTS `duplicate_match_dismissals` (
  `certificate_type` VARCHAR(32) NOT NULL DEFAULT 'birth',
  `record_id_low` INT UNSIGNED NOT NULL,
  `record_id_high` INT UNSIGNED NOT NULL,
  `fingerprint_low` CHAR(64) NOT NULL,
  `fingerprint_high` CHAR(64) NOT NULL,
  `dismissed_by` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) NULL,
  `dismissed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`certificate_type`, `record_id_low`, `record_id_high`),
  KEY `idx_duplicate_dismissal_high` (`certificate_type`, `record_id_high`),
  KEY `idx_duplicate_dismissal_user` (`dismissed_by`),
  CONSTRAINT `fk_duplicate_dismissal_user`
    FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Staff-dismissed duplicate suggestions, reconsidered when identity data changes';
