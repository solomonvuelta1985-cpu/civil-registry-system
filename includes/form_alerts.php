<?php
/**
 * Form Alerts Component
 * Displays success/error messages with proper accessibility attributes
 */
?>
<div id="alertContainer" role="region" aria-live="polite" aria-label="Form notifications">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" role="alert">
            <i data-lucide="check-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" role="alert">
            <i data-lucide="alert-circle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>
</div>
