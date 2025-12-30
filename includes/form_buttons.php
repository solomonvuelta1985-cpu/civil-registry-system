<?php
/**
 * Form Buttons Component
 * Standard button group for certificate forms
 *
 * Usage: Include this file after your form closing tag
 */
?>
<div class="button-group">
    <button type="submit" class="btn btn-primary" aria-label="Save certificate record">
        <i data-lucide="save" aria-hidden="true"></i>
        <span>Save Record</span>
    </button>

    <button type="button" class="btn btn-success" data-action="save-and-new" aria-label="Save this record and create another">
        <i data-lucide="plus-circle" aria-hidden="true"></i>
        <span>Save & Add New</span>
    </button>

    <button type="reset" class="btn btn-danger" aria-label="Reset form to empty state">
        <i data-lucide="rotate-ccw" aria-hidden="true"></i>
        <span>Reset Form</span>
    </button>

    <a href="../admin/dashboard.php" class="btn btn-secondary" data-action="back" aria-label="Return to dashboard">
        <i data-lucide="arrow-left" aria-hidden="true"></i>
        <span>Back to Dashboard</span>
    </a>
</div>
