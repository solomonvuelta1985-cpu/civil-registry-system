/**
 * Certificate Form Handler
 * Shared JavaScript for all certificate forms (Birth, Marriage, Death)
 * Version: 1.0
 */

class CertificateFormHandler {
    constructor(config) {
        this.formType = config.formType; // 'birth', 'marriage', 'death'
        this.apiEndpoint = config.apiEndpoint;
        this.updateEndpoint = config.updateEndpoint || config.apiEndpoint;
        this.form = document.getElementById('certificateForm');
        this.submitButtons = {
            save: null,
            saveAndNew: null,
            reset: null,
            back: null
        };
        this.isSubmitting = false;

        this.init();
    }

    init() {
        if (!this.form) {
            return;
        }

        // Check Notiflix availability
        this.checkNotiflixAvailability();

        this.cacheElements();
        this.setupFormValidation();
        this.setupFormSubmission();
        this.setupPDFUpload();
        this.setupPDFToggle();
        this.setupPDFModal();
        this.setupResetConfirmation();
        this.setupCancelEdit();
        this.setupAutoSave();
        this.setupBeforeUnload();
    }

    /**
     * Check if Notiflix is available
     */
    checkNotiflixAvailability() {
        // Silently check - no console output in production
    }

    cacheElements() {
        // Cache form buttons
        this.submitButtons.save = this.form.querySelector('[type="submit"]');
        this.submitButtons.saveAndNew = this.form.querySelector('[data-action="save-and-new"]');
        this.submitButtons.reset = this.form.querySelector('[type="reset"]');
        this.submitButtons.back = this.form.querySelector('[data-action="back"]');
        this.submitButtons.cancelEdit = this.form.closest('.content')?.querySelector('[data-action="cancel-edit"]') || document.querySelector('[data-action="cancel-edit"]');

        // Cache other elements
        this.alertContainer = document.getElementById('alertContainer');
        this.pdfColumn = document.getElementById('pdfColumn');
        this.pdfFileInput = document.getElementById('pdf_file');
        this.pdfPreview = document.getElementById('pdfPreview');
    }

    /**
     * Setup real-time form validation
     */
    setupFormValidation() {
        // Validate required fields on blur
        const requiredInputs = this.form.querySelectorAll('[required]');

        requiredInputs.forEach(input => {
            // Add aria-required
            input.setAttribute('aria-required', 'true');

            // Validate on blur
            input.addEventListener('blur', () => this.validateField(input));

            // Clear validation on input
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });

        // Date validation
        const dateInputs = this.form.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            input.addEventListener('change', () => this.validateDate(input));
        });
    }

    /**
     * Validate a single field
     */
    validateField(field) {
        const isValid = field.checkValidity();

        // Set ARIA attribute
        field.setAttribute('aria-invalid', !isValid);

        // Add/remove visual classes
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }

        // Show/hide error message
        this.updateFieldError(field, !isValid);

        return isValid;
    }

    /**
     * Update field error message
     */
    updateFieldError(field, hasError) {
        let errorDiv = field.parentElement.querySelector('.invalid-feedback');

        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.setAttribute('role', 'alert');
            field.parentElement.appendChild(errorDiv);
        }

        if (hasError) {
            const label = field.closest('.form-group')?.querySelector('label')?.textContent || 'This field';
            errorDiv.textContent = field.validationMessage || `${label} is required`;
            errorDiv.style.display = 'block';
        } else {
            errorDiv.style.display = 'none';
        }
    }

    /**
     * Validate date fields
     */
    validateDate(input) {
        const value = input.value;
        if (!value) return true;

        const date = new Date(value);
        const today = new Date();

        // Check for invalid dates (e.g., Feb 30)
        if (isNaN(date.getTime())) {
            input.setCustomValidity('Invalid date');
            this.validateField(input);
            return false;
        }

        // Check for future dates if needed
        if (date > today && input.hasAttribute('data-no-future')) {
            input.setCustomValidity('Date cannot be in the future');
            this.validateField(input);
            return false;
        }

        input.setCustomValidity('');
        this.validateField(input);
        return true;
    }

    /**
     * Setup form submission handlers
     */
    setupFormSubmission() {
        // Standard submit
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm(false);
        });

        // Save and add new
        if (this.submitButtons.saveAndNew) {
            this.submitButtons.saveAndNew.addEventListener('click', (e) => {
                e.preventDefault();
                this.submitForm(true);
            });
        }
    }

    /**
     * Submit the form
     */
    async submitForm(addNew = false) {
        // Prevent double submission
        if (this.isSubmitting) {
            return;
        }

        // Show confirmation dialog based on form type
        const formTypeNames = {
            'birth': 'Birth',
            'marriage': 'Marriage',
            'death': 'Death',
            'marriage_license': 'Marriage License',
            'petition': 'Petition',
            'legal_instrument': 'Legal Instrument',
            'court_decree': 'Court Decree'
        };

        const certificateType = formTypeNames[this.formType] || 'Certificate';
        const isEditMode = this.form.querySelector('input[name="record_id"]')?.value;
        const action = isEditMode ? 'update' : 'submit';
        const confirmMessage = `Are you sure you want to ${action} this ${certificateType} record?`;

        // Function to actually submit the form
        const doSubmit = async () => {
            // Validate all fields
            const isValid = this.form.checkValidity();

            if (!isValid) {
                this.form.reportValidity();

                // Validate all fields to show error messages
                const requiredInputs = this.form.querySelectorAll('[required]');
                requiredInputs.forEach(input => this.validateField(input));

                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.warning('Please fill in all required fields correctly.');
                } else {
                    this.showAlert('danger', 'Please fill in all required fields correctly.');
                }
                return;
            }

            this.isSubmitting = true;

            // Show loading state
            this.setButtonLoading(this.submitButtons.save, true);
            this.showLoadingOverlay(true);
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle('Submitting certificate...');
            }

            // Enable any disabled fields that have values (for cascading dropdowns)
            // Skip fields marked data-skip-reenable (e.g. Not Stated / Not Married selects)
            const disabledFields = this.form.querySelectorAll('input:disabled, select:disabled, textarea:disabled');
            const fieldsToReEnable = [];
            disabledFields.forEach(field => {
                if (field.dataset.skipReenable) return;
                if (field.value && field.value.trim() !== '') {
                    field.disabled = false;
                    fieldsToReEnable.push(field);
                }
            });

            // Prepare form data
            const formData = new FormData(this.form);

            // Append CSRF token from meta tag (if not already in form)
            if (!formData.has('csrf_token')) {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) {
                    formData.append('csrf_token', csrfMeta.content);
                }
            }

            // Re-disable fields after FormData is created
            fieldsToReEnable.forEach(field => {
                field.disabled = true;
            });

            // Determine which endpoint to use based on edit mode
            const isEditMode = this.form.querySelector('input[name="record_id"]')?.value;
            const endpoint = isEditMode ? this.updateEndpoint : this.apiEndpoint;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                if (response.redirected || response.url.includes('/public/login.php')) {
                    this.handleError('Your session has expired. Please log in again.');
                    return;
                }

                const data = await response.json();

                if (data.success) {
                    // Check for potential duplicate registrations
                    if (data.data && data.data.potential_duplicates && data.data.potential_duplicates.length > 0) {
                        this.showDuplicateNotification(data.data.potential_duplicates, data.data.id, data.message, addNew);
                    } else {
                        this.handleSuccess(data.message, addNew);
                    }
            } else {
                this.handleError(data.message || 'An error occurred while saving the record.');
            }
            } catch (error) {
                this.handleError('Network connection failed. Please check your connection and try again.');
            } finally {
                this.isSubmitting = false;
                this.setButtonLoading(this.submitButtons.save, false);
                this.showLoadingOverlay(false);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Loading.remove();
                }
            }
        };

        // Show Notiflix confirmation
        if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
            Notiflix.Confirm.show(
                'Confirm Submission',
                confirmMessage,
                isEditMode ? 'Update' : 'Submit',
                'Cancel',
                () => {
                    doSubmit();
                },
                () => {
                    // User cancelled - do nothing
                },
                {
                    width: '360px',
                    borderRadius: '12px',
                    okButtonBackground: isEditMode ? '#3B82F6' : '#10B981',
                    titleColor: '#111827',
                }
            );
        } else {
            // Fallback to native confirm
            if (!confirm(confirmMessage)) {
                return;
            }
            doSubmit();
        }
    }

    /**
     * Reset form for a new entry, preserving context fields (municipality, province)
     */
    _resetFormForNewEntry() {
        // Save context fields before reset
        const contextFields = {};
        const contextFieldNames = ['municipality', 'province'];
        contextFieldNames.forEach(name => {
            const field = this.form.elements[name];
            if (field) contextFields[name] = field.value;
        });

        // Reset form
        this.form.reset();

        // Restore context fields after reset
        Object.keys(contextFields).forEach(name => {
            const field = this.form.elements[name];
            if (field) field.value = contextFields[name];
        });

        // Clear validation states
        this.form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
            el.classList.remove('is-valid', 'is-invalid');
            el.removeAttribute('aria-invalid');
        });

        // Notify listeners (e.g. progress bar) that form values have changed
        this.form.dispatchEvent(new Event('change', { bubbles: true }));
        if (typeof updateFormProgress === 'function') {
            updateFormProgress();
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Handle successful form submission
     */
    handleSuccess(message, addNew) {
        // Clear autosave
        this.clearAutoSave();

        if (addNew) {
            if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                Notiflix.Notify.success(`${message} Ready to create another record.`, {
                    timeout: 3000,
                    position: 'right-top',
                });
            } else {
                this.showAlert('success', `${message} Ready to create another record.`);
            }

            this._resetFormForNewEntry();
        } else {
            // Show success with redirect countdown
            const isUpdate = this.form.querySelector('[name="record_id"]')?.value;
            const action = isUpdate ? 'updated' : 'created';

            // Determine the correct redirect URL based on record type
            const base = window.APP_BASE || '';
            const redirectUrls = {
                'birth': base + '/public/birth_records.php',
                'marriage': base + '/public/marriage_records.php',
                'death': base + '/public/death_records.php',
                'marriage_license': base + '/public/marriage_license_records.php',
                'petition': base + '/public/ra9048/records.php?type=petition',
                'legal_instrument': base + '/public/ra9048/records.php?type=legal_instrument',
                'court_decree': base + '/public/ra9048/records.php?type=court_decree'
            };

            const redirectUrl = redirectUrls[this.formType] || base + '/admin/dashboard.php';

            if (typeof Notiflix !== 'undefined' && Notiflix.Report) {
                Notiflix.Report.success(
                    'Success!',
                    `Certificate ${action} successfully! Redirecting to records page...`,
                    'Okay',
                    () => {
                        window.location.href = redirectUrl;
                    },
                    {
                        width: '360px',
                        borderRadius: '12px',
                        svgSize: '80px',
                        messageMaxLength: 500,
                        plainText: false,
                        backOverlay: true,
                    }
                );

                // Auto-redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            } else {
                this.showAlert('success', `Certificate ${action} successfully! Redirecting to records page in 3 seconds...`);

                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            }
        }
    }

    /**
     * Handle form submission error
     */
    handleError(message) {
        this.showAlert('danger', message);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Set button loading state
     */
    setButtonLoading(button, isLoading) {
        if (!button) return;

        if (isLoading) {
            button.disabled = true;
            button.classList.add('btn-loading');
            button.setAttribute('aria-busy', 'true');
        } else {
            button.disabled = false;
            button.classList.remove('btn-loading');
            button.setAttribute('aria-busy', 'false');
        }
    }

    /**
     * Show/hide loading overlay
     */
    showLoadingOverlay(show) {
        let overlay = document.getElementById('loadingOverlay');

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.setAttribute('role', 'status');
            overlay.setAttribute('aria-live', 'polite');
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }

        if (show) {
            overlay.classList.add('active');
        } else {
            overlay.classList.remove('active');
        }
    }

    /**
     * Show alert message
     */
    showAlert(type, message) {
        if (!this.alertContainer) return;

        // Remove existing alerts
        this.alertContainer.innerHTML = '';

        // Create alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.setAttribute('role', 'alert');

        const iconMap = {
            success: 'check-circle',
            danger: 'alert-circle',
            warning: 'alert-triangle',
            info: 'info'
        };

        alert.innerHTML = `
            <i data-lucide="${iconMap[type] || 'info'}" aria-hidden="true"></i>
            <span>${message}</span>
        `;

        this.alertContainer.appendChild(alert);

        // Initialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 10000);
    }

    /**
     * Setup PDF upload handling
     */
    setupPDFUpload() {
        if (!this.pdfFileInput) return;

        // Make the upload area clickable to trigger file input
        const uploadArea = document.getElementById('pdfUploadArea');
        if (uploadArea) {
            uploadArea.addEventListener('click', () => {
                this.pdfFileInput.click();
            });
        }

        this.pdfFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];

            if (!file) {
                this.clearPDFPreview();
                return;
            }

            // Validate file type
            if (file.type !== 'application/pdf') {
                this.showAlert('danger', 'Please upload a valid PDF file.');
                this.pdfFileInput.value = '';
                this.clearPDFPreview();
                return;
            }

            // Validate file size (10MB)
            if (file.size > 10485760) {
                this.showAlert('danger', 'File size exceeds 10MB. Please upload a smaller file.');
                this.pdfFileInput.value = '';
                this.clearPDFPreview();
                return;
            }

            // Show preview
            this.showPDFPreview(file);
        });
    }

    /**
     * Show PDF preview
     */
    showPDFPreview(file) {
        if (!this.pdfPreview) return;

        const fileURL = URL.createObjectURL(file);
        this.pdfPreview.src = fileURL;

        // Hide upload area and show preview area
        const uploadArea = document.getElementById('pdfUploadArea');
        const previewArea = document.getElementById('pdfPreviewArea');
        const pdfFileName = document.getElementById('pdfFileName');

        if (uploadArea) {
            uploadArea.classList.add('hidden');
        }

        if (previewArea) {
            previewArea.classList.remove('hidden');
        }

        if (pdfFileName) {
            pdfFileName.textContent = file.name;
        }

        // Make the new preview container clickable for modal
        if (previewArea) {
            const container = previewArea.querySelector('.pdf-preview-container');
            if (container && !container.dataset.modalBound) {
                container.style.cursor = 'pointer';
                container.title = 'Click to view PDF in full screen';
                container.addEventListener('click', (e) => {
                    e.preventDefault();
                    const iframe = container.querySelector('iframe');
                    if (iframe && iframe.src) {
                        const modalFrame = document.getElementById('pdfViewerModalFrame');
                        if (modalFrame && this.pdfModal) {
                            modalFrame.src = iframe.src;
                            this.pdfModal.classList.add('open');
                            document.body.style.overflow = 'hidden';
                        }
                    }
                });
                container.dataset.modalBound = 'true';
            }
        }

        if (this.openDrawerFn) {
            this.openDrawerFn();
        }
    }

    /**
     * Clear PDF preview
     */
    clearPDFPreview() {
        if (this.pdfPreview) {
            this.pdfPreview.src = '';
        }

        // Show upload area and hide preview area
        const uploadArea = document.getElementById('pdfUploadArea');
        const previewArea = document.getElementById('pdfPreviewArea');
        const pdfFileName = document.getElementById('pdfFileName');

        if (uploadArea) {
            uploadArea.classList.remove('hidden');
        }

        if (previewArea) {
            previewArea.classList.add('hidden');
        }

        if (pdfFileName) {
            pdfFileName.textContent = '';
        }
    }

    /**
     * Setup PDF drawer toggle
     */
    setupPDFToggle() {
        const toggleBtn = document.getElementById('togglePdfBtn');
        const floatingBtn = document.getElementById('floatingToggleBtn');

        if (!this.pdfColumn) return;

        // Remove legacy classes
        this.pdfColumn.classList.remove('hidden');

        // Move floating button to body to avoid stacking context issues
        if (floatingBtn) {
            document.body.appendChild(floatingBtn);
        }

        let inlineBtn = null;

        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'pdf-drawer-backdrop';
        document.body.appendChild(backdrop);

        let drawerOpen = false;

        const openDrawer = () => {
            if (drawerOpen) return;
            drawerOpen = true;
            this.openPdfDrawer(toggleBtn, floatingBtn, backdrop);
            localStorage.setItem('pdfColumnVisible', 'true');
        };

        const closeDrawer = () => {
            if (!drawerOpen) return;
            drawerOpen = false;
            this.closePdfDrawer(toggleBtn, floatingBtn, backdrop);
            localStorage.setItem('pdfColumnVisible', 'false');
        };

        // Load saved state (default to closed)
        const savedState = localStorage.getItem('pdfColumnVisible');
        if (savedState === 'true') {
            this.pdfColumn.style.transition = 'none';
            backdrop.style.transition = 'none';
            drawerOpen = true;
            this.openPdfDrawer(toggleBtn, floatingBtn, backdrop);
            this.pdfColumn.offsetHeight;
            this.pdfColumn.style.transition = '';
            backdrop.style.transition = '';
        }

        // ARIA attributes
        if (floatingBtn) {
            floatingBtn.setAttribute('aria-controls', 'pdfColumn');
            floatingBtn.setAttribute('aria-expanded', drawerOpen ? 'true' : 'false');
        }
        this.pdfColumn.setAttribute('role', 'dialog');
        this.pdfColumn.setAttribute('aria-label', 'PDF Upload Panel');

        // Floating button opens
        if (floatingBtn) {
            floatingBtn.addEventListener('click', openDrawer);
        }

        // Inline button opens
        if (inlineBtn) {
            inlineBtn.addEventListener('click', openDrawer);
        }

        // Header toggle closes
        if (toggleBtn) {
            toggleBtn.addEventListener('click', closeDrawer);
        }

        // Backdrop click closes
        backdrop.addEventListener('click', closeDrawer);

        // Escape key closes
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawerOpen) {
                closeDrawer();
            }
        });

        this.openDrawerFn = openDrawer;
        this.closeDrawerFn = closeDrawer;
        this._floatingBtn = floatingBtn;
    }

    openPdfDrawer(toggleBtn, floatingBtn, backdrop) {
        this.pdfColumn.classList.add('drawer-open');
        if (backdrop) backdrop.classList.add('active');
        if (floatingBtn) {
            floatingBtn.classList.add('drawer-active');
            floatingBtn.setAttribute('aria-expanded', 'true');
        }
        document.body.classList.add('pdf-drawer-open');

        if (toggleBtn) {
            toggleBtn.innerHTML = '<i data-lucide="x"></i>';
            toggleBtn.title = 'Close PDF Upload';
        }

        this.pdfColumn.setAttribute('aria-hidden', 'false');

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    closePdfDrawer(toggleBtn, floatingBtn, backdrop) {
        this.pdfColumn.classList.remove('drawer-open');
        if (backdrop) backdrop.classList.remove('active');
        if (floatingBtn) {
            floatingBtn.classList.remove('drawer-active');
            floatingBtn.setAttribute('aria-expanded', 'false');
            floatingBtn.focus();
        }
        document.body.classList.remove('pdf-drawer-open');

        if (toggleBtn) {
            toggleBtn.innerHTML = '<i data-lucide="eye-off"></i>';
            toggleBtn.title = 'Hide PDF Upload';
        }

        this.pdfColumn.setAttribute('aria-hidden', 'true');

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /**
     * Setup PDF modal viewer
     */
    setupPDFModal() {
        // Create modal dynamically
        const modal = document.createElement('div');
        modal.id = 'pdfViewerModal';
        modal.className = 'pdf-viewer-modal';
        modal.innerHTML = `
            <div class="pdf-viewer-modal-content">
                <div class="pdf-viewer-modal-header">
                    <span class="pdf-viewer-modal-title">PDF Viewer</span>
                    <button type="button" class="pdf-viewer-modal-close" title="Close">&times;</button>
                </div>
                <div class="pdf-viewer-modal-body">
                    <iframe id="pdfViewerModalFrame" src=""></iframe>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const modalFrame = document.getElementById('pdfViewerModalFrame');
        const closeBtn = modal.querySelector('.pdf-viewer-modal-close');

        // Close handlers
        closeBtn.addEventListener('click', () => this.closePDFModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closePDFModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('open')) {
                this.closePDFModal();
            }
        });

        // Click on PDF preview containers to open modal
        const previewContainers = document.querySelectorAll('.pdf-preview-container');
        previewContainers.forEach(container => {
            container.style.cursor = 'pointer';
            container.title = 'Click to view PDF in full screen';
            container.addEventListener('click', (e) => {
                e.preventDefault();
                const iframe = container.querySelector('iframe');
                if (iframe && iframe.src) {
                    modalFrame.src = iframe.src;
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                }
            });
        });

        this.pdfModal = modal;
    }

    /**
     * Close PDF modal
     */
    closePDFModal() {
        if (this.pdfModal) {
            this.pdfModal.classList.remove('open');
            if (!document.body.classList.contains('pdf-drawer-open')) {
                document.body.style.overflow = '';
            }
            const frame = document.getElementById('pdfViewerModalFrame');
            if (frame) frame.src = '';
        }
    }

    /**
     * Setup reset confirmation
     */
    setupResetConfirmation() {
        if (!this.submitButtons.reset) return;

        this.submitButtons.reset.addEventListener('click', (e) => {
            e.preventDefault();

            const resetForm = () => {
                this.form.reset();

                // Clear validation states
                this.form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                    el.classList.remove('is-valid', 'is-invalid');
                    el.removeAttribute('aria-invalid');
                });

                // Notify listeners (e.g. progress bar) that form values have changed
                this.form.dispatchEvent(new Event('change', { bubbles: true }));
                if (typeof updateFormProgress === 'function') {
                    updateFormProgress();
                }

                // Clear autosave
                this.clearAutoSave();

                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.info('Form has been reset.');
                } else {
                    this.showAlert('info', 'Form has been reset.');
                }
            };

            if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                Notiflix.Confirm.show(
                    'Reset Form',
                    'Are you sure you want to reset the form? All unsaved data will be lost.',
                    'Reset',
                    'Cancel',
                    () => {
                        resetForm();
                    },
                    () => {
                        // User cancelled - do nothing
                    },
                    {
                        width: '360px',
                        borderRadius: '12px',
                        titleColor: '#F59E0B',
                        okButtonBackground: '#F59E0B',
                    }
                );
            } else {
                // Fallback to native confirm
                if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                    resetForm();
                }
            }
        });
    }

    /**
     * Setup cancel edit button
     */
    setupCancelEdit() {
        if (!this.submitButtons.cancelEdit) return;

        this.submitButtons.cancelEdit.addEventListener('click', (e) => {
            e.preventDefault();
            const backBtn = document.querySelector('[data-action="back"]');
            const backUrl = backBtn ? backBtn.href : '../admin/dashboard.php';

            if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                Notiflix.Confirm.show(
                    'Cancel Editing',
                    'Are you sure you want to cancel? Any unsaved changes will be lost.',
                    'Yes, Cancel',
                    'Continue Editing',
                    () => {
                        this.form.dataset.submitting = '1';
                        window.location.href = backUrl;
                    },
                    () => {},
                    {
                        width: '360px',
                        borderRadius: '12px',
                        titleColor: '#F59E0B',
                        okButtonBackground: '#F59E0B',
                    }
                );
            } else {
                if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                    window.location.href = backUrl;
                }
            }
        });
    }

    /**
     * Setup auto-save functionality
     */
    setupAutoSave() {
        const autoSaveKey = `cert_form_autosave_${this.formType}`;
        let autoSaveTimeout;

        // Check if we're in edit mode
        const isEditMode = this.form.querySelector('input[name="record_id"]')?.value;

        // Load autosaved data ONLY if NOT in edit mode
        const savedData = localStorage.getItem(autoSaveKey);
        if (savedData && !isEditMode) {
            try {
                const data = JSON.parse(savedData);

                if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                    Notiflix.Confirm.show(
                        'Restore Autosaved Data',
                        'Found autosaved data. Would you like to restore it?',
                        'Restore',
                        'Discard',
                        () => {
                            setTimeout(() => {
                                this.restoreFormData(data);
                                if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                                    Notiflix.Notify.info('Autosaved data has been restored.');
                                } else {
                                    this.showAlert('info', 'Autosaved data has been restored.');
                                }
                            }, 0);
                        },
                        () => {
                            localStorage.removeItem(autoSaveKey);
                            if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                                Notiflix.Notify.info('Autosaved data discarded.');
                            } else {
                                this.showAlert('info', 'Autosaved data discarded.');
                            }
                        },
                        {
                            width: '360px',
                            borderRadius: '12px',
                            okButtonBackground: '#3B82F6',
                            cancelButtonBackground: '#6B7280',
                        }
                    );
                } else {
                    // Fallback to native confirm
                    const shouldRestore = confirm('Found autosaved data. Would you like to restore it?');
                    if (shouldRestore) {
                        this.restoreFormData(data);
                        this.showAlert('info', 'Autosaved data has been restored.');
                    } else {
                        localStorage.removeItem(autoSaveKey);
                    }
                }
            } catch (e) {
                // Silently handle autosave restore errors
            }
        } else if (savedData && isEditMode) {
            // In edit mode, silently clear any autosaved data to prevent confusion
            localStorage.removeItem(autoSaveKey);
        }

        // Autosave on input ONLY if NOT in edit mode
        if (!isEditMode) {
            this.form.addEventListener('input', () => {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    this.saveFormData(autoSaveKey);
                }, 2000); // Save after 2 seconds of inactivity
            });
        }
    }

    /**
     * Save form data to localStorage
     */
    saveFormData(key) {
        const formData = new FormData(this.form);
        const data = {};

        for (let [name, value] of formData.entries()) {
            if (name !== 'pdf_file') { // Don't save file inputs
                data[name] = value;
            }
        }

        localStorage.setItem(key, JSON.stringify(data));
    }

    /**
     * Restore form data from object
     */
    restoreFormData(data) {
        Object.keys(data).forEach(name => {
            const field = this.form.elements[name];
            if (!field) return;
            const value = data[name];
            const inputs = field instanceof RadioNodeList || field instanceof HTMLCollection
                ? Array.from(field)
                : [field];
            inputs.forEach(input => {
                if (!input || typeof input.dispatchEvent !== 'function') return;
                if (input.type === 'radio' || input.type === 'checkbox') {
                    input.checked = Array.isArray(value)
                        ? value.includes(input.value)
                        : String(input.value) === String(value);
                } else {
                    input.value = value;
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    /**
     * Clear autosaved data
     */
    clearAutoSave() {
        const autoSaveKey = `cert_form_autosave_${this.formType}`;
        localStorage.removeItem(autoSaveKey);
    }

    /**
     * Setup before unload warning
     */
    setupBeforeUnload() {
        let formChanged = false;

        this.form.addEventListener('input', () => {
            formChanged = true;
        });

        // Intercept all link clicks - use capture phase to catch early
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');

            // Check if it's a link and there are unsaved changes
            if (link && link.href && formChanged && !this.isSubmitting) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const targetUrl = link.href;

                if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
                    Notiflix.Confirm.show(
                        'Unsaved Changes',
                        'You have unsaved changes that will be lost. Are you sure you want to leave this page?',
                        'Leave Page',
                        'Stay',
                        () => {
                            // User confirmed - clear flag and navigate
                            formChanged = false;
                            this.form.dataset.submitting = '1';
                            // Use a small delay to ensure flag is cleared before navigation
                            setTimeout(() => {
                                window.location.href = targetUrl;
                            }, 50);
                        },
                        () => {
                            // User chose to stay - do nothing
                        },
                        {
                            width: '380px',
                            borderRadius: '12px',
                            titleColor: '#F59E0B',
                            okButtonBackground: '#EF4444',
                            okButtonColor: '#FFFFFF',
                            cancelButtonBackground: '#6B7280',
                            cancelButtonColor: '#FFFFFF',
                        }
                    );
                } else {
                    // Fallback to native confirm
                    if (confirm('You have unsaved changes. Are you sure you want to leave this page?')) {
                        formChanged = false;
                        this.form.dataset.submitting = '1';
                        setTimeout(() => {
                            window.location.href = targetUrl;
                        }, 50);
                    }
                }
            }
        }, true); // Use capture phase

        // Clear flag on successful submission
        this.form.addEventListener('submit', () => {
            formChanged = false;
        });
    }

    /**
     * Show duplicate registration notification after save/update.
     * Triggered when the API returns potential_duplicates in the response.
     */
    showDuplicateNotification(duplicates, savedRecordId, successMessage, addNew) {
        if (!duplicates || duplicates.length === 0) return;

        // Clear autosave since save was successful
        this.clearAutoSave();

        const topMatch = duplicates[0];
        const scoreLabel = topMatch.match_score >= 80 ? 'High confidence'
                         : topMatch.match_score >= 50 ? 'Moderate match'
                         : 'Weak match';

        const matchInfo = `Possible double registration with Registry No. ${topMatch.registry_no || 'N/A'} ` +
            `(${topMatch.child_name || 'Unknown'}) — ${topMatch.match_score}% match (${scoreLabel}).` +
            (duplicates.length > 1 ? ` +${duplicates.length - 1} more potential match(es).` : '');

        const message = `${successMessage}\n\n⚠️ ${matchInfo}`;

        // Determine the secondary action based on addNew
        const secondaryLabel = addNew ? 'Save Another' : 'Go to Records';

        if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
            Notiflix.Confirm.show(
                'Saved — Possible Double Registration',
                message,
                'Compare Now',
                secondaryLabel,
                () => {
                    // Compare Now — open the comparison modal
                    if (typeof DoubleRegComparisonModal !== 'undefined') {
                        const modal = new DoubleRegComparisonModal();
                        // When modal closes, handle form reset or stay on page
                        if (addNew) {
                            modal.onClose = () => this._resetFormForNewEntry();
                        }
                        modal.open(savedRecordId, topMatch.id, 'birth');
                    } else {
                        if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                            Notiflix.Notify.info('Comparison modal not available. You can review from the Records page.', { timeout: 5000 });
                        }
                    }
                },
                () => {
                    // Secondary action: Save Another or Go to Records
                    if (addNew) {
                        this._resetFormForNewEntry();
                        if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                            Notiflix.Notify.success('Ready to create another record.', { timeout: 3000, position: 'right-top' });
                        }
                    } else {
                        const base = window.APP_BASE || '';
                        const redirectUrls = {
                            'birth': base + '/public/birth_records.php',
                            'marriage': base + '/public/marriage_records.php',
                            'death': base + '/public/death_records.php',
                            'marriage_license': base + '/public/marriage_license_records.php',
                            'petition': base + '/public/ra9048/records.php?type=petition',
                            'legal_instrument': base + '/public/ra9048/records.php?type=legal_instrument',
                            'court_decree': base + '/public/ra9048/records.php?type=court_decree'
                        };
                        window.location.href = redirectUrls[this.formType] || base + '/admin/dashboard.php';
                    }
                },
                {
                    width: '500px',
                    borderRadius: '12px',
                    titleColor: '#DC2626',
                    titleMaxLength: 50,
                    okButtonBackground: '#DC2626',
                    cancelButtonBackground: '#2563EB',
                    titleFontSize: '16px',
                    messageFontSize: '14px',
                    plainText: false,
                }
            );
        } else {
            // Fallback: native confirm
            if (confirm('SAVED — POSSIBLE DOUBLE REGISTRATION\n\n' + matchInfo + '\n\nClick OK to compare records.')) {
                if (typeof DoubleRegComparisonModal !== 'undefined') {
                    const modal = new DoubleRegComparisonModal();
                    if (addNew) {
                        modal.onClose = () => this._resetFormForNewEntry();
                    }
                    modal.open(savedRecordId, topMatch.id, 'birth');
                }
            } else if (addNew) {
                this._resetFormForNewEntry();
            } else {
                const base = window.APP_BASE || '';
                const redirectUrls = {
                    'birth': base + '/public/birth_records.php',
                    'marriage': base + '/public/marriage_records.php',
                    'death': base + '/public/death_records.php',
                    'marriage_license': base + '/public/marriage_license_records.php',
                    'petition': base + '/public/ra9048/records.php?type=petition',
                    'legal_instrument': base + '/public/ra9048/records.php?type=legal_instrument',
                    'court_decree': base + '/public/ra9048/records.php?type=court_decree'
                };
                window.location.href = redirectUrls[this.formType] || base + '/admin/dashboard.php';
            }
        }
    }

}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CertificateFormHandler;
}
