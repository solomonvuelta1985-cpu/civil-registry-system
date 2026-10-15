<?php
require_once __DIR__ . '/security.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/assets/css/profile-password.css?v=20261015-mobile-account-compact', ENT_QUOTES, 'UTF-8') ?>">
<div class="change-password-modal" id="changePasswordModal" role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle" aria-describedby="changePasswordDescription" hidden>
    <div class="change-password-dialog">
        <div class="change-password-heading">
            <div>
                <p class="change-password-eyebrow">Account security</p>
                <h2 id="changePasswordTitle">Change password</h2>
            </div>
            <button type="button" class="change-password-close" data-change-password-close aria-label="Close change password dialog">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
        <p class="change-password-description" id="changePasswordDescription">Enter your current password, then choose a new password for your account.</p>

        <form id="changePasswordForm" class="change-password-form" action="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/api/change_password.php', ENT_QUOTES, 'UTF-8') ?>" method="post" novalidate>
            <?= csrfTokenField() ?>
            <label for="currentPassword">Current password</label>
            <input id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>

            <label for="newPassword">New password</label>
            <input id="newPassword" name="new_password" type="password" autocomplete="new-password" minlength="<?= (int)MIN_PASSWORD_LENGTH ?>" required>
            <p class="change-password-hint">Use at least <?= (int)MIN_PASSWORD_LENGTH ?> characters and meet your configured password requirements.</p>

            <label for="confirmPassword">Confirm new password</label>
            <input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" minlength="<?= (int)MIN_PASSWORD_LENGTH ?>" required>

            <p class="change-password-message" id="changePasswordMessage" role="status" aria-live="polite" aria-atomic="true"></p>
            <div class="change-password-actions">
                <button type="button" class="change-password-cancel" data-change-password-close>Cancel</button>
                <button type="submit" class="change-password-submit">
                    <i data-lucide="shield-check" aria-hidden="true"></i>
                    <span>Update password</span>
                </button>
            </div>
        </form>
    </div>
</div>
<script src="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/assets/js/profile-password.js?v=20261015-password-click-fix', ENT_QUOTES, 'UTF-8') ?>" defer></script>
