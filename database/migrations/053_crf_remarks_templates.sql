-- Migration 053: user-owned reusable remarks templates for CRF 1A, 2A, and 3A.

CREATE TABLE IF NOT EXISTS `crf_remarks_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `template_name` VARCHAR(120) NOT NULL,
    `remarks_html` MEDIUMTEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_crf_remarks_template_user_name` (`user_id`, `template_name`),
    KEY `idx_crf_remarks_templates_updated` (`user_id`, `updated_at`),
    CONSTRAINT `fk_crf_remarks_templates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
