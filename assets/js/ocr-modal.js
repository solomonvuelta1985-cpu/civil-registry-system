/**
 * OCR Modal System - Professional Implementation
 * Floating badge + Clean modal dialog
 * Following industry best practices (Google Drive, Dropbox pattern)
 */

class OCRModal {
    constructor(options = {}) {
        this.options = {
            formId: options.formId || 'certificateForm',
            fileInputId: options.fileInputId || 'pdf_file',
            autoProcess: options.autoProcess !== undefined ? options.autoProcess : true,
            autoFill: options.autoFill !== undefined ? options.autoFill : false,
            confidenceThreshold: options.confidenceThreshold || 75,
            showBadgeDelay: options.showBadgeDelay || 500,
            ...options
        };

        this.processor = typeof ServerOCR !== 'undefined' ? new ServerOCR() : new OCRProcessor();
        this.fieldMapper = typeof OCRFieldMapper !== 'undefined' ? new OCRFieldMapper() : null;
        this.pageSelector = null;

        this.currentFile = null;
        this.selectedPages = null;
        this.ocrResult = null;
        this.suggestions = {};
        this.isProcessing = false;
        this.modalOpen = false;

        console.log('🎨 OCR Modal System initialized');
        console.log('📱 OCR Mode:', this.processor.constructor.name === 'ServerOCR' ? 'Server (FAST)' : 'Browser (Slow)');

        this.init();
    }

    init() {
        this.createFloatingBadge();
        this.createModal();
        this.attachFileListener();
        this.initializePageSelector();
        this.attachKeyboardShortcuts();
    }

    initializePageSelector() {
        if (typeof OCRPageSelector !== 'undefined') {
            this.pageSelector = new OCRPageSelector({
                onPageSelectionChange: (selection) => {
                    this.selectedPages = selection.pages;
                }
            });
        }
    }

    /**
     * Create floating badge (bottom-right)
     */
    createFloatingBadge() {
        const badge = document.createElement('div');
        badge.id = 'ocr-floating-badge';
        badge.className = 'ocr-floating-badge';
        badge.style.display = 'none';
        badge.innerHTML = `
            <div class="badge-content" id="badge-content">
                <div class="badge-icon" id="badge-icon">📄</div>
                <div class="badge-text">
                    <div class="badge-title" id="badge-title">OCR Ready</div>
                    <div class="badge-subtitle" id="badge-subtitle">Click to review</div>
                </div>
                <div class="badge-count" id="badge-count" style="display: none;">0</div>
            </div>
            <div class="badge-actions" id="badge-actions" style="display: none;">
                <button type="button" class="badge-scan-btn" id="badge-scan-btn" title="Scan document now">
                    <span class="scan-icon">🔍</span>
                    <span class="scan-text">Scan Now</span>
                </button>
            </div>
            <div class="badge-progress" id="badge-progress" style="display: none;">
                <div class="badge-progress-bar" id="badge-progress-bar"></div>
            </div>
        `;

        // Main badge click opens modal
        const badgeContent = badge.querySelector('#badge-content');
        badgeContent.addEventListener('click', () => this.openModal());

        // Manual scan button
        const scanBtn = badge.querySelector('#badge-scan-btn');
        scanBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent modal opening
            this.manualScanTrigger();
        });

        document.body.appendChild(badge);
    }

    /**
     * Create professional modal dialog
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'ocr-modal';
        modal.className = 'ocr-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <!-- Backdrop -->
            <div class="ocr-modal-backdrop" id="ocr-modal-backdrop"></div>

            <!-- Modal Dialog -->
            <div class="ocr-modal-dialog" id="ocr-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ocr-modal-title">
                <!-- Header -->
                <div class="ocr-modal-header">
                    <h3 class="ocr-modal-title" id="ocr-modal-title">
                        <span class="modal-icon">🤖</span>
                        OCR Assistant
                    </h3>
                    <button type="button" class="ocr-modal-close" id="ocr-modal-close" aria-label="Close">
                        <span>×</span>
                    </button>
                </div>

                <!-- Body -->
                <div class="ocr-modal-body" id="ocr-modal-body">
                    <!-- Processing State -->
                    <div class="modal-processing-state" id="modal-processing-state" style="display: none;">
                        <div class="processing-spinner"></div>
                        <div class="processing-text" id="processing-text">Processing PDF...</div>
                        <div class="processing-progress">
                            <div class="processing-progress-bar" id="processing-progress-bar"></div>
                        </div>
                        <div class="processing-details" id="processing-details">Initializing OCR engine...</div>
                    </div>

                    <!-- Page Selector (if multi-page) -->
                    <div class="modal-page-selector" id="modal-page-selector" style="display: none;">
                        <div class="section-header">
                            <span class="section-icon">📄</span>
                            <span class="section-title">Select Pages to Scan</span>
                        </div>
                        <div id="modal-page-selector-content"></div>
                    </div>

                    <!-- Results State -->
                    <div class="modal-results-state" id="modal-results-state" style="display: none;">
                        <!-- Stats Summary -->
                        <div class="results-summary">
                            <div class="summary-item">
                                <div class="summary-label">Fields Extracted</div>
                                <div class="summary-value" id="summary-fields">0</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Confidence</div>
                                <div class="summary-value" id="summary-confidence">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Processing Time</div>
                                <div class="summary-value" id="summary-time">--</div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="results-table-container">
                            <table class="results-table" id="results-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Value</th>
                                        <th>Confidence</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="results-table-body">
                                    <!-- Data rows inserted here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div class="results-empty" id="results-empty" style="display: none;">
                            <div class="empty-icon">📭</div>
                            <div class="empty-title">No data extracted</div>
                            <div class="empty-subtitle">Try uploading a different PDF or adjusting settings</div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="ocr-modal-footer" id="ocr-modal-footer">
                    <button type="button" class="btn-modal btn-secondary" id="btn-modal-cancel">
                        Cancel
                    </button>
                    <button type="button" class="btn-modal btn-primary" id="btn-modal-apply" disabled>
                        <span class="btn-icon">✓</span>
                        <span class="btn-text">Apply All Fields</span>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.attachModalListeners();
        this.injectStyles();
    }

    /**
     * Attach modal event listeners
     */
    attachModalListeners() {
        // Close button
        document.getElementById('ocr-modal-close').addEventListener('click', () => this.closeModal());

        // Cancel button
        document.getElementById('btn-modal-cancel').addEventListener('click', () => this.closeModal());

        // Apply button
        document.getElementById('btn-modal-apply').addEventListener('click', () => this.applyAllFields());

        // Backdrop click
        document.getElementById('ocr-modal-backdrop').addEventListener('click', () => this.closeModal());

        // Prevent dialog click from closing
        document.getElementById('ocr-modal-dialog').addEventListener('click', (e) => e.stopPropagation());
    }

    /**
     * Attach keyboard shortcuts
     */
    attachKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // ESC to close modal
            if (e.key === 'Escape' && this.modalOpen) {
                this.closeModal();
            }

            // Ctrl/Cmd + Enter to apply all (when modal open)
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && this.modalOpen) {
                const applyBtn = document.getElementById('btn-modal-apply');
                if (!applyBtn.disabled) {
                    this.applyAllFields();
                }
            }
        });
    }

    /**
     * Attach file input listener
     */
    attachFileListener() {
        const fileInput = document.getElementById(this.options.fileInputId);

        if (fileInput) {
            fileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];

                if (file && file.type === 'application/pdf') {
                    this.currentFile = file;
                    await this.handlePDFUpload(file);
                }
            });
        }
    }

    /**
     * Handle PDF upload
     */
    async handlePDFUpload(file) {
        console.log('📄 PDF uploaded:', file.name);

        // Show badge with processing state
        this.showBadge('processing');
        this.updateBadge('Processing...', 'Analyzing PDF', '⏳');

        // Load PDF metadata for page selection
        if (this.pageSelector) {
            try {
                const totalPages = await this.pageSelector.loadPDF(file);
                console.log(`📄 PDF has ${totalPages} pages`);

                // If multi-page, we'll show selector in modal
                if (totalPages > 1) {
                    this.hasMultiplePages = true;
                }
            } catch (error) {
                console.warn('Could not load PDF metadata:', error);
            }
        }

        // Decide whether to auto-process or wait for user
        if (this.hasMultiplePages) {
            // Multi-page PDF - show page selector first
            this.updateBadge('Select Pages', `${this.pageSelector.totalPages} pages available`, '📄');
            this.showScanButton(true); // Show scan button or click badge to select pages
            console.log('📄 Multi-page PDF detected - page selection required');
        } else if (this.options.autoProcess) {
            // Single page - auto-process
            await this.processOCR();
        } else {
            // Auto-process disabled - show manual scan button
            this.updateBadge('Ready to scan', 'Click Scan Now button', '📄');
            this.showScanButton(true);
        }
    }

    /**
     * Manual scan trigger (fallback button)
     */
    async manualScanTrigger() {
        if (!this.currentFile) {
            this.showToast('No PDF file uploaded', 'error');
            return;
        }

        if (this.isProcessing) {
            this.showToast('Processing already in progress', 'info');
            return;
        }

        console.log('🔍 Manual scan triggered');

        // If multi-page PDF, open modal to select pages first
        if (this.hasMultiplePages && !this.ocrResult) {
            this.openModal(); // Will show page selector
        } else {
            // Single page or already processed - scan directly
            this.showScanButton(false);
            await this.processOCR();
        }
    }

    /**
     * Show/hide manual scan button
     */
    showScanButton(show) {
        const actionsEl = document.getElementById('badge-actions');
        if (actionsEl) {
            actionsEl.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * Process OCR
     */
    async processOCR() {
        this.isProcessing = true;
        this.updateBadge('Processing...', 'Please wait', '⏳');
        this.showBadgeProgress(true);

        try {
            // Get selected pages
            const selectedPages = this.pageSelector ? this.pageSelector.getSelectedPages() : null;

            // Process with progress updates
            this.ocrResult = await this.processor.processPDF(this.currentFile, {
                selectedPages: selectedPages
            });

            if (this.ocrResult.success) {
                const data = this.ocrResult.structuredData || {};
                const fieldCount = Object.keys(data).filter(k => data[k] && k !== 'confidence_scores').length;

                this.suggestions = data;
                this.showBadgeProgress(false);

                if (fieldCount > 0) {
                    this.updateBadge('Scan Complete', `${fieldCount} fields ready`, '✅', fieldCount);
                    console.log(`✅ OCR complete: ${fieldCount} fields extracted`);
                } else {
                    // No data found - show retry button
                    this.updateBadge('No data found', 'Click Scan Now to retry', '📭');
                    this.showScanButton(true);
                }
            } else {
                throw new Error(this.ocrResult.error || 'OCR processing failed');
            }
        } catch (error) {
            console.error('❌ OCR Error:', error);
            this.updateBadge('Processing failed', 'Click Scan Now to retry', '❌');
            this.showBadgeProgress(false);
            this.showScanButton(true); // Show retry button on error
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Show floating badge
     */
    showBadge(state = 'ready') {
        const badge = document.getElementById('ocr-floating-badge');

        setTimeout(() => {
            badge.style.display = 'block';
            setTimeout(() => badge.classList.add('show'), 10);
        }, this.options.showBadgeDelay);

        badge.className = `ocr-floating-badge show state-${state}`;
    }

    /**
     * Hide floating badge
     */
    hideBadge() {
        const badge = document.getElementById('ocr-floating-badge');
        badge.classList.remove('show');
        setTimeout(() => badge.style.display = 'none', 300);
    }

    /**
     * Update badge content
     */
    updateBadge(title, subtitle, icon = '📄', count = null) {
        document.getElementById('badge-title').textContent = title;
        document.getElementById('badge-subtitle').textContent = subtitle;
        document.getElementById('badge-icon').textContent = icon;

        const countEl = document.getElementById('badge-count');
        if (count !== null && count > 0) {
            countEl.textContent = count;
            countEl.style.display = 'flex';
        } else {
            countEl.style.display = 'none';
        }
    }

    /**
     * Show/hide badge progress bar
     */
    showBadgeProgress(show) {
        const progress = document.getElementById('badge-progress');
        progress.style.display = show ? 'block' : 'none';
    }

    /**
     * Open modal
     */
    openModal() {
        const modal = document.getElementById('ocr-modal');
        const dialog = document.getElementById('ocr-modal-dialog');

        this.modalOpen = true;
        modal.style.display = 'block';

        // Trigger animation
        setTimeout(() => {
            modal.classList.add('show');
            dialog.classList.add('show');
        }, 10);

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Load content based on state
        if (this.isProcessing) {
            this.showProcessingState();
        } else if (this.ocrResult && this.ocrResult.success) {
            this.showResultsState();
        } else if (this.hasMultiplePages && !this.ocrResult) {
            // Show page selector FIRST for multi-page PDFs
            this.showPageSelectorState();
        } else {
            this.showProcessingState();
            this.processOCR();
        }

        // Focus trap
        document.getElementById('ocr-modal-dialog').focus();
    }

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.getElementById('ocr-modal');
        const dialog = document.getElementById('ocr-modal-dialog');

        modal.classList.remove('show');
        dialog.classList.remove('show');

        setTimeout(() => {
            modal.style.display = 'none';
            this.modalOpen = false;
        }, 300);

        // Restore body scroll
        document.body.style.overflow = '';
    }

    /**
     * Show page selector state in modal
     */
    showPageSelectorState() {
        document.getElementById('modal-processing-state').style.display = 'none';
        document.getElementById('modal-page-selector').style.display = 'block';
        document.getElementById('modal-results-state').style.display = 'none';
        document.getElementById('btn-modal-apply').disabled = true;

        // Render page selector UI
        const selectorContent = document.getElementById('modal-page-selector-content');
        if (this.pageSelector && selectorContent) {
            selectorContent.innerHTML = this.pageSelector.createUI();

            // Show the page selector panel and update badge
            const panel = document.getElementById('page-selector-panel');
            if (panel) {
                panel.style.display = 'block';

                // Update page count badge
                const badge = panel.querySelector('.page-count-badge');
                if (badge) {
                    badge.textContent = `${this.pageSelector.totalPages} pages`;
                }

                // Attach event listeners for mode switching
                this.attachPageSelectorListeners();
            }

            // Change "Apply All" button to "Start Scan"
            const applyBtn = document.getElementById('btn-modal-apply');
            const btnText = applyBtn.querySelector('.btn-text');
            if (btnText) {
                btnText.textContent = 'Start Scan';
            } else {
                applyBtn.innerHTML = `
                    <span class="btn-icon">🔍</span>
                    <span class="btn-text">Start Scan</span>
                `;
            }
            applyBtn.disabled = false;

            // Update button handler for page selector mode
            const oldHandler = applyBtn.onclick;
            applyBtn.onclick = () => {
                // Get selected pages from page selector
                this.selectedPages = this.pageSelector.getSelectedPages();
                console.log('📋 User selected pages:', this.selectedPages);

                // Restore original button structure
                applyBtn.innerHTML = `
                    <span class="btn-icon">✓</span>
                    <span class="btn-text">Apply All</span>
                `;
                applyBtn.onclick = oldHandler;

                // Start processing with selected pages
                this.showProcessingState();
                this.processOCR();
            };
        }
    }

    /**
     * Attach event listeners to page selector UI
     */
    attachPageSelectorListeners() {
        const modeRadios = document.querySelectorAll('input[name="page_mode"]');
        const rangeContainer = document.getElementById('range-input-container');
        const customContainer = document.getElementById('custom-input-container');
        const rangeInput = document.getElementById('page-range-input');
        const customInput = document.getElementById('page-custom-input');
        const summaryDisplay = document.getElementById('selected-pages-display');

        // Mode switching
        modeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                const mode = e.target.value;
                this.pageSelector.selectionMode = mode;

                // Show/hide input containers
                rangeContainer.style.display = mode === 'range' ? 'block' : 'none';
                customContainer.style.display = mode === 'custom' ? 'block' : 'none';

                // Update summary
                this.updatePageSummary();
            });
        });

        // Range input validation
        if (rangeInput) {
            rangeInput.addEventListener('input', () => this.updatePageSummary());
        }

        // Custom input validation
        if (customInput) {
            customInput.addEventListener('input', () => this.updatePageSummary());
        }

        // Initial summary update
        this.updatePageSummary();
    }

    /**
     * Update selected pages summary
     */
    updatePageSummary() {
        const summaryDisplay = document.getElementById('selected-pages-display');
        if (!summaryDisplay) return;

        const mode = this.pageSelector.selectionMode;
        const totalPages = this.pageSelector.totalPages;

        if (mode === 'all') {
            summaryDisplay.textContent = `All pages (1-${totalPages})`;
            summaryDisplay.style.color = '#059669';
        } else if (mode === 'range') {
            const input = document.getElementById('page-range-input');
            const rangeStr = input?.value.trim();

            if (rangeStr) {
                try {
                    const pages = this.pageSelector.parsePageRange(rangeStr);
                    if (pages.length > 0) {
                        summaryDisplay.textContent = `${pages.length} pages: ${pages.join(', ')}`;
                        summaryDisplay.style.color = '#059669';
                        input.style.borderColor = '#10b981';
                    } else {
                        summaryDisplay.textContent = 'No pages selected';
                        summaryDisplay.style.color = '#dc2626';
                        input.style.borderColor = '#ef4444';
                    }
                } catch (error) {
                    summaryDisplay.textContent = error.message;
                    summaryDisplay.style.color = '#dc2626';
                    input.style.borderColor = '#ef4444';
                }
            } else {
                summaryDisplay.textContent = 'Enter page range';
                summaryDisplay.style.color = '#6b7280';
                input.style.borderColor = '';
            }
        } else if (mode === 'custom') {
            const input = document.getElementById('page-custom-input');
            const customStr = input?.value.trim();

            if (customStr) {
                try {
                    const pages = this.pageSelector.parseCustomPages(customStr);
                    if (pages.length > 0) {
                        summaryDisplay.textContent = `${pages.length} pages: ${pages.join(', ')}`;
                        summaryDisplay.style.color = '#059669';
                        input.style.borderColor = '#10b981';
                    } else {
                        summaryDisplay.textContent = 'No pages selected';
                        summaryDisplay.style.color = '#dc2626';
                        input.style.borderColor = '#ef4444';
                    }
                } catch (error) {
                    summaryDisplay.textContent = error.message;
                    summaryDisplay.style.color = '#dc2626';
                    input.style.borderColor = '#ef4444';
                }
            } else {
                summaryDisplay.textContent = 'Enter page numbers';
                summaryDisplay.style.color = '#6b7280';
                input.style.borderColor = '';
            }
        }
    }

    /**
     * Show processing state in modal
     */
    showProcessingState() {
        document.getElementById('modal-processing-state').style.display = 'block';
        document.getElementById('modal-page-selector').style.display = 'none';
        document.getElementById('modal-results-state').style.display = 'none';
        document.getElementById('btn-modal-apply').disabled = true;
    }

    /**
     * Show results state in modal
     */
    showResultsState() {
        document.getElementById('modal-processing-state').style.display = 'none';
        document.getElementById('modal-results-state').style.display = 'block';

        const data = this.ocrResult.structuredData || {};
        const tbody = document.getElementById('results-table-body');
        const empty = document.getElementById('results-empty');

        tbody.innerHTML = '';
        let count = 0;

        // Build table rows
        for (const [field, value] of Object.entries(data)) {
            if (value && value !== null && field !== 'confidence_scores') {
                const confidence = data.confidence_scores?.[field] || 75;
                const confidenceClass = confidence > 90 ? 'high' : confidence > 70 ? 'medium' : 'low';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="field-name">${this.formatFieldName(field)}</td>
                    <td class="field-value">${this.escapeHtml(value)}</td>
                    <td class="field-confidence">
                        <span class="confidence-badge ${confidenceClass}">${Math.round(confidence)}%</span>
                    </td>
                    <td class="field-action">
                        <button class="btn-use-field" data-field="${field}">
                            Use
                        </button>
                    </td>
                `;

                // Attach click event properly (not inline)
                const useBtn = row.querySelector('.btn-use-field');
                useBtn.addEventListener('click', () => {
                    this.useField(field, value);
                });

                tbody.appendChild(row);
                count++;
            }
        }

        // Update summary
        document.getElementById('summary-fields').textContent = count;
        document.getElementById('summary-confidence').textContent = `${Math.round(this.ocrResult.confidence || 0)}%`;
        document.getElementById('summary-time').textContent = this.ocrResult.processing_time ? `${this.ocrResult.processing_time}s` : '--';

        // Show empty state or enable apply button
        if (count === 0) {
            empty.style.display = 'block';
            document.getElementById('btn-modal-apply').disabled = true;
        } else {
            empty.style.display = 'none';
            document.getElementById('btn-modal-apply').disabled = false;

            // Update button text
            const btnText = document.querySelector('#btn-modal-apply .btn-text');
            if (btnText) {
                btnText.textContent = `Apply All ${count} Fields`;
            } else {
                // Button structure was changed (e.g., by page selector), recreate it
                const applyBtn = document.getElementById('btn-modal-apply');
                applyBtn.innerHTML = `
                    <span class="btn-icon">✓</span>
                    <span class="btn-text">Apply All ${count} Fields</span>
                `;
            }
        }
    }

    /**
     * Use single field
     */
    useField(fieldName, value) {
        console.log(`🎯 Applying field: ${fieldName} = "${value}"`);

        // Get mapped form field ID
        const formFieldId = this.fieldMapper?.fieldMapping?.[fieldName] || fieldName;
        console.log(`📝 Form field ID: ${formFieldId}`);

        const formField = document.getElementById(formFieldId);

        if (!formField) {
            console.error(`❌ Form field not found: ${formFieldId}`);
            this.showToast(`Field not found: ${this.formatFieldName(fieldName)}`, 'error');
            return;
        }

        try {
            // Convert value based on field type
            const convertedValue = this.fieldMapper ? this.fieldMapper.convertValue(value, formField) : value;
            console.log(`🔄 Converted value: "${convertedValue}" (field type: ${formField.type || formField.tagName})`);

            // Set the value
            formField.value = convertedValue;

            // Trigger change and input events
            formField.dispatchEvent(new Event('change', { bubbles: true }));
            formField.dispatchEvent(new Event('input', { bubbles: true }));

            // Visual feedback
            formField.style.transition = 'all 0.3s ease';
            formField.style.backgroundColor = '#d1fae5';
            formField.style.borderColor = '#10b981';

            setTimeout(() => {
                formField.style.backgroundColor = '';
                formField.style.borderColor = '';
            }, 1000);

            console.log(`✅ Successfully applied: ${fieldName} → ${formFieldId} = "${convertedValue}"`);

            // Show success toast
            this.showToast(`Applied: ${this.formatFieldName(fieldName)}`, 'success');

        } catch (error) {
            console.error(`❌ Error applying field:`, error);
            this.showToast(`Error: ${error.message}`, 'error');
        }
    }

    /**
     * Apply all fields
     */
    applyAllFields() {
        if (!this.fieldMapper || !this.suggestions) return;

        const results = this.fieldMapper.applyToForm(this.suggestions);

        if (results.filled.length > 0) {
            console.log(`✅ Applied ${results.filled.length} fields`);
            this.showToast(`${results.filled.length} fields applied successfully`, 'success');

            // Close modal after short delay
            setTimeout(() => {
                this.closeModal();
                this.hideBadge();
            }, 800);
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Simple toast - can be enhanced
        const toast = document.createElement('div');
        toast.className = `ocr-toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    formatFieldName(field) {
        return field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, '&#39;');
    }

    /**
     * Inject professional styles
     */
    injectStyles() {
        if (document.getElementById('ocr-modal-styles')) return;

        const style = document.createElement('style');
        style.id = 'ocr-modal-styles';
        style.textContent = `
            /* Floating Badge */
            .ocr-floating-badge {
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                cursor: pointer;
                z-index: 9999;
                opacity: 0;
                transform: translateY(20px) scale(0.9);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                min-width: 280px;
                max-width: 320px;
            }

            .ocr-floating-badge.show {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            .ocr-floating-badge:hover {
                transform: translateY(-4px) scale(1.02);
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            }

            .badge-content {
                display: flex;
                align-items: center;
                padding: 16px;
                gap: 12px;
            }

            .badge-icon {
                font-size: 32px;
                flex-shrink: 0;
            }

            .badge-text {
                flex: 1;
                min-width: 0;
            }

            .badge-title {
                font-weight: 600;
                font-size: 14px;
                color: #1f2937;
                margin-bottom: 2px;
            }

            .badge-subtitle {
                font-size: 12px;
                color: #6b7280;
            }

            .badge-count {
                background: #667eea;
                color: #ffffff;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                font-weight: 700;
                flex-shrink: 0;
            }

            .badge-actions {
                padding: 0 16px 12px 16px;
                border-top: 1px solid #f3f4f6;
            }

            .badge-scan-btn {
                width: 100%;
                padding: 10px 16px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            }

            .badge-scan-btn:hover {
                background: linear-gradient(135deg, #5a67d8 0%, #6b46a1 100%);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                transform: translateY(-1px);
            }

            .badge-scan-btn:active {
                transform: translateY(0);
                box-shadow: 0 1px 4px rgba(102, 126, 234, 0.3);
            }

            .badge-scan-btn .scan-icon {
                font-size: 16px;
            }

            .badge-scan-btn .scan-text {
                font-size: 13px;
            }

            .badge-progress {
                height: 3px;
                background: #e5e7eb;
                overflow: hidden;
            }

            .badge-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                animation: indeterminate 1.5s infinite;
            }

            @keyframes indeterminate {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .ocr-floating-badge.state-processing .badge-icon {
                animation: pulse 1.5s infinite;
            }

            /* Modal */
            .ocr-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .ocr-modal.show {
                opacity: 1;
            }

            .ocr-modal-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
            }

            .ocr-modal-dialog {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.9);
                background: #ffffff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                width: 90%;
                max-width: 600px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .ocr-modal-dialog.show {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }

            .ocr-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 20px 24px;
                border-bottom: 1px solid #e5e7eb;
            }

            .ocr-modal-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #1f2937;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .modal-icon {
                font-size: 24px;
            }

            .ocr-modal-close {
                background: none;
                border: none;
                font-size: 32px;
                color: #6b7280;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                transition: all 0.2s;
                line-height: 1;
            }

            .ocr-modal-close:hover {
                background: #f3f4f6;
                color: #1f2937;
            }

            .ocr-modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 24px;
            }

            /* Processing State */
            .modal-processing-state {
                text-align: center;
                padding: 40px 20px;
            }

            .processing-spinner {
                width: 48px;
                height: 48px;
                border: 4px solid #e5e7eb;
                border-top-color: #667eea;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                margin: 0 auto 20px;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .processing-text {
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 8px;
            }

            .processing-details {
                font-size: 13px;
                color: #6b7280;
                margin-top: 16px;
            }

            .processing-progress {
                width: 100%;
                max-width: 300px;
                height: 6px;
                background: #e5e7eb;
                border-radius: 3px;
                margin: 16px auto 0;
                overflow: hidden;
            }

            .processing-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                width: 0%;
                transition: width 0.3s ease;
            }

            /* Results Summary */
            .results-summary {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .summary-item {
                text-align: center;
                padding: 16px;
                background: #f9fafb;
                border-radius: 8px;
            }

            .summary-label {
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 6px;
            }

            .summary-value {
                font-size: 24px;
                font-weight: 700;
                color: #1f2937;
            }

            /* Results Table */
            .results-table-container {
                overflow-x: auto;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
            }

            .results-table {
                width: 100%;
                border-collapse: collapse;
            }

            .results-table th {
                background: #f9fafb;
                padding: 12px;
                text-align: left;
                font-size: 12px;
                font-weight: 600;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                border-bottom: 1px solid #e5e7eb;
            }

            .results-table td {
                padding: 12px;
                border-bottom: 1px solid #f3f4f6;
                font-size: 14px;
            }

            .results-table tr:last-child td {
                border-bottom: none;
            }

            .results-table tr:hover {
                background: #f9fafb;
            }

            .field-name {
                font-weight: 600;
                color: #374151;
            }

            .field-value {
                font-family: 'Courier New', monospace;
                color: #1f2937;
            }

            .confidence-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }

            .confidence-badge.high {
                background: #d1fae5;
                color: #065f46;
            }

            .confidence-badge.medium {
                background: #fef3c7;
                color: #92400e;
            }

            .confidence-badge.low {
                background: #fee2e2;
                color: #991b1b;
            }

            .btn-use-field {
                padding: 6px 16px;
                background: #667eea;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-use-field:hover {
                background: #5568d3;
                transform: translateY(-1px);
            }

            /* Empty State */
            .results-empty {
                text-align: center;
                padding: 60px 20px;
            }

            .empty-icon {
                font-size: 64px;
                margin-bottom: 16px;
                opacity: 0.5;
            }

            .empty-title {
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 8px;
            }

            .empty-subtitle {
                font-size: 14px;
                color: #6b7280;
            }

            /* Modal Footer */
            .ocr-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                padding: 20px 24px;
                border-top: 1px solid #e5e7eb;
            }

            .btn-modal {
                padding: 10px 20px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .btn-modal.btn-secondary {
                background: #f3f4f6;
                color: #374151;
            }

            .btn-modal.btn-secondary:hover {
                background: #e5e7eb;
            }

            .btn-modal.btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
            }

            .btn-modal.btn-primary:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            }

            .btn-modal:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Toast */
            .ocr-toast {
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(100px);
                background: #1f2937;
                color: #ffffff;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
                z-index: 10001;
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .ocr-toast.show {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }

            .ocr-toast.toast-success {
                background: #10b981;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .ocr-modal-dialog {
                    width: 95%;
                    max-height: 90vh;
                }

                .results-summary {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }

                .ocr-floating-badge {
                    bottom: 16px;
                    right: 16px;
                    min-width: 260px;
                }
            }

            /* Accessibility */
            .ocr-modal-dialog:focus {
                outline: none;
            }

            @media (prefers-reduced-motion: reduce) {
                .ocr-floating-badge,
                .ocr-modal,
                .ocr-modal-dialog,
                .btn-modal {
                    transition: none;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Make globally available
window.OCRModal = OCRModal;
console.log('✓ OCR Modal System loaded');
