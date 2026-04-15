<?php
/**
 * Form Buttons Component
 * Corporate toolbar — sticky, with unsaved indicator, keyboard shortcuts,
 * loading state, and reset confirmation.
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
$records_label = ucfirst(str_replace(['_', '.php'], [' ', ''], $records_url_map[$current_script] ?? 'records'));
?>
<?php if ($edit_mode): ?>
<div class="form-toolbar" id="formToolbar" data-form-toolbar>
    <div class="toolbar-group toolbar-primary">
        <a href="<?php echo $records_url; ?>" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" title="Back to <?php echo htmlspecialchars($records_label); ?>" aria-label="Return to <?php echo htmlspecialchars($records_label); ?>">
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
        <button type="submit" class="toolbar-btn toolbar-btn-primary" data-action="save" aria-label="Update certificate record (Ctrl+S)" title="Save changes (Ctrl+S)">
            <span class="toolbar-spinner" aria-hidden="true"></span>
            <i data-lucide="refresh-cw" aria-hidden="true"></i>
            <span>Update</span>
            <kbd>Ctrl+S</kbd>
        </button>
    </div>
    <div class="toolbar-status">
        <span class="toolbar-unsaved" aria-live="polite">
            <span class="toolbar-unsaved-text">Unsaved changes</span>
        </span>
    </div>
</div>
<?php else: ?>
<div class="form-toolbar" id="formToolbar" data-form-toolbar>
    <div class="toolbar-group toolbar-primary">
        <button type="submit" class="toolbar-btn toolbar-btn-primary" data-action="save" aria-label="Save certificate record (Ctrl+S)" title="Save and return to records (Ctrl+S)">
            <span class="toolbar-spinner" aria-hidden="true"></span>
            <i data-lucide="save" aria-hidden="true"></i>
            <span>Save</span>
            <kbd>Ctrl+S</kbd>
        </button>
        <button type="button" class="toolbar-btn toolbar-btn-outline" data-action="save-and-new" title="Save and start a new record (Ctrl+Shift+S)" aria-label="Save this record and create another">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Save & New</span>
        </button>
    </div>
    <div class="toolbar-divider"></div>
    <div class="toolbar-group toolbar-secondary">
        <button type="button" class="toolbar-btn toolbar-btn-ghost-danger" data-action="reset-form" aria-label="Reset form to empty state">
            <i data-lucide="rotate-ccw" aria-hidden="true"></i>
            <span>Reset</span>
        </button>
        <a href="../admin/dashboard.php" class="toolbar-btn toolbar-btn-ghost-dark" data-action="back" title="Back to Dashboard" aria-label="Return to dashboard">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
    </div>
    <div class="toolbar-status">
        <span class="toolbar-unsaved" aria-live="polite">
            <span class="toolbar-unsaved-text">Unsaved changes</span>
        </span>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    const toolbar = document.getElementById('formToolbar');
    if (!toolbar) return;

    const form = toolbar.closest('form') || document.querySelector('form');
    if (!form) return;

    // ----- Sticky shadow state -----
    const stickyObserver = () => {
        const rect = toolbar.getBoundingClientRect();
        const stuck = rect.bottom >= window.innerHeight - 2;
        toolbar.classList.toggle('is-stuck', stuck);
    };
    window.addEventListener('scroll', stickyObserver, { passive: true });
    window.addEventListener('resize', stickyObserver);
    stickyObserver();

    // ----- Hide toolbar while a <select> is focused -----
    // Prevents the sticky bar from overlapping the native options popup.
    const hideForSelect = (e) => {
        if (e.target.tagName === 'SELECT') {
            toolbar.classList.add('is-hidden');
        }
    };
    const restoreToolbar = (e) => {
        if (e.target.tagName === 'SELECT') {
            toolbar.classList.remove('is-hidden');
        }
    };
    form.addEventListener('focusin', hideForSelect);
    form.addEventListener('focusout', restoreToolbar);
    form.addEventListener('change', (e) => {
        if (e.target.tagName === 'SELECT') {
            toolbar.classList.remove('is-hidden');
        }
    });

    // ----- Dirty state tracking -----
    let isDirty = false;
    const initialSnapshot = new FormData(form);
    const markDirty = () => {
        if (!isDirty) {
            isDirty = true;
            toolbar.classList.add('is-dirty');
        }
    };
    const clearDirty = () => {
        isDirty = false;
        toolbar.classList.remove('is-dirty');
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);

    window.addEventListener('beforeunload', (e) => {
        if (isDirty && !form.dataset.submitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ----- Reset confirmation (two-click arm) -----
    const resetBtn = toolbar.querySelector('[data-action="reset-form"]');
    let resetArmed = false;
    let resetTimer = null;
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!resetArmed) {
                resetArmed = true;
                resetBtn.classList.add('is-armed');
                resetTimer = setTimeout(() => {
                    resetArmed = false;
                    resetBtn.classList.remove('is-armed');
                }, 3000);
                return;
            }
            clearTimeout(resetTimer);
            resetArmed = false;
            resetBtn.classList.remove('is-armed');
            form.reset();
            clearDirty();
        });
    }

    // ----- Save & New button reference (click handled by certificate-form-handler.js) -----
    const saveAndNewBtn = toolbar.querySelector('[data-action="save-and-new"]');

    // ----- Submit: spinner + disable + clear dirty -----
    form.addEventListener('submit', () => {
        form.dataset.submitting = '1';
        clearDirty();
        toolbar.querySelectorAll('.toolbar-btn').forEach(btn => {
            if (btn.dataset.action === 'save' || btn.type === 'submit') {
                btn.setAttribute('aria-busy', 'true');
            } else {
                btn.disabled = true;
            }
        });
    });

    // ----- Keyboard shortcuts -----
    document.addEventListener('keydown', (e) => {
        // Ctrl+S / Cmd+S -> Save
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 's') {
            const saveBtn = toolbar.querySelector('[data-action="save"]');
            if (saveBtn && !saveBtn.hasAttribute('aria-busy')) {
                e.preventDefault();
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(saveBtn);
                } else {
                    form.submit();
                }
            }
        }
        // Ctrl+Shift+S -> Save & New
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 's') {
            if (saveAndNewBtn) {
                e.preventDefault();
                saveAndNewBtn.click();
            }
        }
    });
})();
</script>
