-- OCR Cache Table
-- Stores OCR results for fast retrieval

CREATE TABLE IF NOT EXISTS `ocr_cache` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of PDF file',
  `file_name` VARCHAR(255) DEFAULT NULL,
  `file_size` INT(11) DEFAULT NULL,
  `ocr_text` LONGTEXT NOT NULL COMMENT 'Raw OCR extracted text',
  `structured_data` JSON DEFAULT NULL COMMENT 'Parsed field data',
  `processing_time` DECIMAL(6,2) DEFAULT NULL COMMENT 'Processing time in seconds',
  `tesseract_version` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` TIMESTAMP NULL DEFAULT NULL,
  `access_count` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_hash` (`file_hash`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cache for OCR processing results';
