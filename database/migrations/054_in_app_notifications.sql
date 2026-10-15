-- Migration 054: durable in-app notifications and per-user read receipts.

CREATE TABLE IF NOT EXISTS `app_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_key` VARCHAR(191) NOT NULL,
    `category` VARCHAR(24) NOT NULL,
    `event_type` VARCHAR(48) NOT NULL,
    `source_id` VARCHAR(64) NULL,
    `actor_user_id` INT(11) UNSIGNED NULL,
    `recipient_user_id` INT(11) UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NULL,
    `target_url` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_app_notifications_key` (`notification_key`),
    KEY `idx_app_notifications_recipient_created` (`recipient_user_id`, `created_at`),
    KEY `idx_app_notifications_actor_created` (`actor_user_id`, `created_at`),
    KEY `idx_app_notifications_created` (`created_at`),
    CONSTRAINT `fk_app_notifications_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_app_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_notification_reads` (
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`, `user_id`),
    KEY `idx_app_notification_reads_user_read` (`user_id`, `read_at`),
    CONSTRAINT `fk_app_notification_reads_notification` FOREIGN KEY (`notification_id`) REFERENCES `app_notifications` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_app_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
