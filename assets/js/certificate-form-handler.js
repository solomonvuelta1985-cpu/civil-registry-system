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
            console.error('Certificate form not found');
            return;
        }

        // Check Notiflix availability
        this.checkNotiflixAvailability();

        this.cacheElements();
        this.setupFormValidation();
        this.setupFormSubmission();
        this.setupPDFUpload();
        this.setupPDFToggle();
        this.setupResetConfirmation();
        this.setupAutoSave();
        this.setupBeforeUnload();
    }

    /**
     * Check if Notiflix is available
     */
    checkNotiflixAvailability() {
        if (typeof Notiflix === 'undefined') {
            console.warn('⚠️ Notiflix library not loaded. Falling back to native dialogs.');
        } else if (!Notiflix.Confirm || !Notiflix.Notify) {
            console.warn('⚠️ Notiflix modules incomplete. Some features may not work.');
        } else {
            console.log('✅ Notiflix library loaded successfully');
        }
    }

    cacheElements() {
        // Cache form buttons
        this.submitButtons.save = this.form.querySelector('[type="submit"]');
        this.submitButtons.saveAndNew = this.form.querySelector('[data-action="save-and-new"]');
        this.submitButtons.reset = this.form.querySelector('[type="reset"]');
        this.submitButtons.back = this.form.querySelector('[data-action="back"]');

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
        console.log('=== submitForm called ===');
        console.log('addNew:', addNew);
        console.log('isSubmitting:', this.isSubmitting);

        // Prevent double submission
        if (this.isSubmitting) {
            console.log('Form already submitting, returning');
            return;
        }

        // Show confirmation dialog based on form type
        const formTypeNames = {
            'birth': 'Birth',
            'marriage': 'Marriage',
            'death': 'Death',
            'marriage_license': 'Marriage License'
        };

        const certificateType = formTypeNames[this.formType] || 'Certificate';
        const isEditMode = this.form.querySelector('input[name="record_id"]')?.value;
        const action = isEditMode ? 'update' : 'submit';
        const confirmMessage = `Are you sure you want to ${action} this ${certificateType} record?`;

        console.log('certificateType:', certificateType);
        console.log('isEditMode:', isEditMode);
        console.log('confirmMessage:', confirmMessage);

        // Function to actually submit the form
        const doSubmit = async () => {
            console.log('=== doSubmit() started ===');

            // Validate all fields
            console.log('Checking form validity...');
            const isValid = this.form.checkValidity();
            console.log('Form is valid:', isValid);

            if (!isValid) {
                console.log('❌ Form validation failed!');
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

            console.log('✅ Form validation passed');
            console.log('✅ Proceeding with form submission...');
            this.isSubmitting = true;

            // Show loading state
            console.log('Setting loading state...');
            this.setButtonLoading(this.submitButtons.save, true);
            this.showLoadingOverlay(true);
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle('Submitting certificate...');
            }

            // Enable any disabled fields that have values (for cascading dropdowns)
            console.log('Enabling disabled fields with values...');
            const disabledFields = this.form.querySelectorAll('input:disabled, select:disabled, textarea:disabled');
            const fieldsToReEnable = [];
            disabledFields.forEach(field => {
                if (field.value && field.value.trim() !== '') {
                    console.log('Enabling field:', field.name, 'with value:', field.value);
                    field.disabled = false;
                    fieldsToReEnable.push(field);
                }
            });

            // Prepare form data
            console.log('Creating FormData...');
            const formData = new FormData(this.form);
            console.log('FormData entries:', Array.from(formData.entries()));

            // CRITICAL DEBUG: Check if place_type and child_place_of_birth are in FormData
            console.log('🔍 CRITICAL CHECK:');
            console.log('place_type in FormData:', formData.get('place_type'));
            console.log('child_place_of_birth in FormData:', formData.get('child_place_of_birth'));

            // Re-disable fields after FormData is created
            fieldsToReEnable.forEach(field => {
                field.disabled = true;
            });

            // Determine which endpoint to use based on edit mode
            const isEditMode = this.form.querySelector('input[name="record_id"]')?.value;
            const endpoint = isEditMode ? this.updateEndpoint : this.apiEndpoint;
            console.log('Using endpoint:', endpoint);
            console.log('Is edit mode:', !!isEditMode);

            try {
                console.log('Sending POST request to:', endpoint);
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });

                console.log('Response received, status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    this.handleSuccess(data.message, addNew);
            } else {
                this.handleError(data.message || 'An error occurred while saving the record.');
            }
            } catch (error) {
                console.error('Form submission error:', error);
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
        console.log('Checking Notiflix availability...');
        console.log('typeof Notiflix:', typeof Notiflix);
        console.log('Notiflix.Confirm:', Notiflix?.Confirm);

        if (typeof Notiflix !== 'undefined' && Notiflix.Confirm) {
            console.log('Showing Notiflix confirmation dialog...');
            Notiflix.Confirm.show(
                'Confirm Submission',
                confirmMessage,
                isEditMode ? 'Update' : 'Submit',
                'Cancel',
                () => {
                    console.log('User clicked Submit - calling doSubmit()');
                    doSubmit();
                },
                () => {
                    // User cancelled - do nothing
                    console.log('Form submission cancelled by user');
                },
                {
                    width: '360px',
                    borderRadius: '12px',
                    okButtonBackground: isEditMode ? '#3B82F6' : '#10B981',
                    titleColor: '#111827',
                }
            );
            console.log('Notiflix.Confirm.show() called');
        } else {
            // Fallback to native confirm
            console.warn('Notiflix not loaded, using native confirm dialog');
            if (!confirm(confirmMessage)) {
                return;
            }
            doSubmit();
        }
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

            // Save context fields before reset (municipality, province, etc.)
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

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            // Show success with redirect countdown
            const isUpdate = this.form.querySelector('[name="record_id"]')?.value;
            const action = isUpdate ? 'updated' : 'created';

            // Determine the correct redirect URL based on record type
            const redirectUrls = {
                'birth': '/iscan/public/birth_records.php',
                'marriage': '/iscan/public/marriage_records.php',
                'death': '/iscan/public/death_records.php',
                'marriage_license': '/iscan/public/marriage_license_records.php'
            };

            const redirectUrl = redirectUrls[this.formType] || '/iscan/admin/dashboard.php';

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
     * Setup PDF column toggle
     */
    setupPDFToggle() {
        const toggleBtn = document.getElementById('togglePdfBtn');
        const floatingBtn = document.getElementById('floatingToggleBtn');
        const formLayout = document.querySelector('.form-layout');

        if (!this.pdfColumn || !formLayout) return;

        let pdfVisible = true;

        // Load saved state
        const savedState = localStorage.getItem('pdfColumnVisible');
        if (savedState !== null) {
            pdfVisible = savedState === 'true';
            if (!pdfVisible) {
                this.pdfColumn.classList.add('hidden');
                formLayout.classList.add('pdf-hidden');
                if (floatingBtn) floatingBtn.classList.add('show');
            }
        }

        // Toggle handlers
        const toggle = () => {
            pdfVisible = !pdfVisible;

            if (pdfVisible) {
                this.pdfColumn.classList.remove('hidden');
                formLayout.classList.remove('pdf-hidden');
                if (floatingBtn) floatingBtn.classList.remove('show');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i data-lucide="eye-off"></i>';
                    toggleBtn.title = 'Hide PDF Upload';
                }
            } else {
                this.pdfColumn.classList.add('hidden');
                formLayout.classList.add('pdf-hidden');
                if (floatingBtn) floatingBtn.classList.add('show');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i data-lucide="eye"></i>';
                    toggleBtn.title = 'Show PDF Upload';
                }
            }

            // Save state
            localStorage.setItem('pdfColumnVisible', pdfVisible);

            // Reinitialize icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }

        if (floatingBtn) {
            floatingBtn.addEventListener('click', toggle);
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
                        console.log('Form reset cancelled by user');
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
                console.warn('Notiflix not loaded for reset confirmation, using native confirm dialog');
                if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                    resetForm();
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
                            this.restoreFormData(data);
                            if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                                Notiflix.Notify.info('Autosaved data has been restored.');
                            } else {
                                this.showAlert('info', 'Autosaved data has been restored.');
                            }
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
                console.error('Error restoring autosaved data:', e);
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
            const input = this.form.elements[name];
            if (input) {
                input.value = data[name];
            }
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

}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CertificateFormHandler;
}
