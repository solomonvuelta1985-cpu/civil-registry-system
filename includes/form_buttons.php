<?php
/**
 * Form Buttons Component
 * Corporate toolbar style for certificate forms
 *
 * Usage: Include this file after your form closing tag
 * Note: $edit_mode variable must be set in the parent file
 */
?>
<div class="form-toolbar">
    <div class="toolbar-group toolbar-primary">
        <button type="submit" class="toolbar-btn toolbar-btn-primary" aria-label="<?php echo $edit_mode ? 'Update certificate record' : 'Save certificate record'; ?>">
            <i data-lucide="<?php echo $edit_mode ? 'refresh-cw' : 'save'; ?>" aria-hidden="true"></i>
            <span><?php echo $edit_mode ? 'Update' : 'Save'; ?></span>
        </button>
        <?php if (!$edit_mode): ?>
        <button type="button" class="toolbar-btn toolbar-btn-outline" data-action="save-and-new" aria-label="Save this record and create another">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Save & New</span>
        </button>
        <?php endif; ?>
    </div>
    <div class="toolbar-divider"></div>
    <div class="toolbar-group toolbar-secondary">
        <button type="reset" class="toolbar-btn toolbar-btn-ghost-danger" aria-label="Reset form to <?php echo $edit_mode ? 'original values' : 'empty state'; ?>">
            <i data-lucide="rotate-ccw" aria-hidden="true"></i>
            <span>Reset</span>
        </button>
        <a href="../admin/dashboard.php" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" aria-label="Return to dashboard">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
    </div>
</div>
