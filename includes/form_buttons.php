<?php
/**
 * Form Buttons Component
 * Corporate toolbar style for certificate forms
 *
 * Usage: Include this file after your form closing tag
 * Note: $edit_mode variable must be set in the parent file
 */

// Determine the records page URL based on the current script
$records_url_map = [
    'certificate_of_live_birth.php' => 'birth_records.php',
    'certificate_of_marriage.php' => 'marriage_records.php',
    'certificate_of_death.php' => 'death_records.php',
    'application_for_marriage_license.php' => 'marriage_license_records.php',
];
$current_script = basename($_SERVER['SCRIPT_NAME']);
$records_url = $records_url_map[$current_script] ?? '../admin/dashboard.php';
?>
<?php if ($edit_mode): ?>
<div class="form-toolbar">
    <div class="toolbar-group toolbar-primary">
        <a href="<?php echo $records_url; ?>" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" aria-label="Return to records">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Records</span>
        </a>
        <button type="button" class="toolbar-btn toolbar-btn-ghost-danger" data-action="cancel-edit" aria-label="Cancel editing">
            <i data-lucide="x" aria-hidden="true"></i>
            <span>Cancel</span>
        </button>
    </div>
    <div class="toolbar-divider"></div>
    <div class="toolbar-group toolbar-secondary">
        <button type="submit" class="toolbar-btn toolbar-btn-primary" aria-label="Update certificate record">
            <i data-lucide="refresh-cw" aria-hidden="true"></i>
            <span>Update</span>
        </button>
    </div>
</div>
<?php else: ?>
<div class="form-toolbar">
    <div class="toolbar-group toolbar-primary">
        <button type="submit" class="toolbar-btn toolbar-btn-primary" aria-label="Save certificate record">
            <i data-lucide="save" aria-hidden="true"></i>
            <span>Save</span>
        </button>
        <button type="button" class="toolbar-btn toolbar-btn-outline" data-action="save-and-new" aria-label="Save this record and create another">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Save & New</span>
        </button>
    </div>
    <div class="toolbar-divider"></div>
    <div class="toolbar-group toolbar-secondary">
        <button type="reset" class="toolbar-btn toolbar-btn-ghost-danger" aria-label="Reset form to empty state">
            <i data-lucide="rotate-ccw" aria-hidden="true"></i>
            <span>Reset</span>
        </button>
        <a href="../admin/dashboard.php" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" aria-label="Return to dashboard">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
    </div>
</div>
<?php endif; ?>
