-- Migration 057: support multiple encrypted Super Admin authenticators.
-- Existing enrolled secrets are copied into a primary device row; legacy user
-- columns remain populated so a rollback to the single-device release works.

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `mfa_session_version` BIGINT UNSIGNED NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `user_mfa_authenticators` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `label` VARCHAR(60) NOT NULL,
    `secret_enc` TEXT NOT NULL,
    `last_totp_step` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_mfa_authenticators_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_user_mfa_authenticators_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_mfa_authenticators`
    (`user_id`, `label`, `secret_enc`, `last_totp_step`, `created_at`)
SELECT u.`id`, 'Primary authenticator', u.`mfa_secret_enc`, u.`mfa_last_totp_step`, COALESCE(u.`mfa_enabled_at`, NOW())
FROM `users` u
WHERE u.`role` = 'Super Admin'
  AND u.`require_2fa` = 1
  AND u.`mfa_enabled_at` IS NOT NULL
  AND u.`mfa_secret_enc` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `user_mfa_authenticators` d WHERE d.`user_id` = u.`id`
  );
