<?php
/**
 * Form Buttons Component
 * Standard button group for certificate forms
 *
 * Usage: Include this file after your form closing tag
 * Note: $edit_mode variable must be set in the parent file
 */
?>
<div class="button-group">
    <button type="submit" class="btn btn-primary" aria-label="<?php echo $edit_mode ? 'Update certificate record' : 'Save certificate record'; ?>">
        <i data-lucide="<?php echo $edit_mode ? 'refresh-cw' : 'save'; ?>" aria-hidden="true"></i>
        <span><?php echo $edit_mode ? 'Update Record' : 'Save Record'; ?></span>
    </button>

    <?php if (!$edit_mode): ?>
    <button type="button" class="btn btn-success" data-action="save-and-new" aria-label="Save this record and create another">
        <i data-lucide="plus-circle" aria-hidden="true"></i>
        <span>Save & Add New</span>
    </button>
    <?php endif; ?>

    <button type="reset" class="btn btn-danger" aria-label="Reset form to <?php echo $edit_mode ? 'original values' : 'empty state'; ?>">
        <i data-lucide="rotate-ccw" aria-hidden="true"></i>
        <span>Reset Form</span>
    </button>

    <a href="../admin/dashboard.php" class="btn btn-secondary" data-action="back" aria-label="Return to dashboard">
        <i data-lucide="arrow-left" aria-hidden="true"></i>
        <span>Back to Dashboard</span>
    </a>
</div>
