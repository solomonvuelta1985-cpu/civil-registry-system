/**
 * OCR Form Integration V2 - Clean & Professional Design
 * Collapsible accordion interface with smart visibility
 */

class OCRFormIntegration {
    constructor(options = {}) {
        this.options = {
            formId: options.formId || 'certificateForm',
            fileInputId: options.fileInputId || 'pdf_file',
            autoProcess: options.autoProcess !== undefined ? options.autoProcess : true,
            autoFill: options.autoFill !== undefined ? options.autoFill : false,
            confidenceThreshold: options.confidenceThreshold || 75,
            ...options
        };

        this.processor = typeof ServerOCR !== 'undefined' ? new ServerOCR() : new OCRProcessor();
        this.fieldMapper = typeof OCRFieldMapper !== 'undefined' ? new OCRFieldMapper() : null;
        this.pageSelector = null;
        this.ocrResult = null;
        this.suggestions = {};
        this.currentFile = null;
        this.selectedPages = null;
        this.accordionState = {
            pageSelector: false,
            results: true,
            settings: false
        };

        console.log('📱 OCR Mode:', this.processor.constructor.name === 'ServerOCR' ? 'Server (FAST)' : 'Browser (Slow)');

        this.init();
    }

    init() {
        this.createOCRPanel();
        this.attachFileListener();
        this.createSuggestionButtons();
        this.initializePageSelector();
    }

    initializePageSelector() {
        if (typeof OCRPageSelector !== 'undefined') {
            this.pageSelector = new OCRPageSelector({
                onPageSelectionChange: (selection) => {
                    this.selectedPages = selection.pages;
                    console.log(`📄 Page selection updated: ${selection.pages.length} page(s)`);
                }
            });
        }
    }

    /**
     * Create clean, collapsible OCR panel
     */
    createOCRPanel() {
        if (document.getElementById('ocr-panel')) return;

        const panel = document.createElement('div');
        panel.id = 'ocr-panel';
        panel.className = 'ocr-panel-v2';
        panel.innerHTML = `
            <!-- Minimized Header (Always Visible) -->
            <div class="ocr-header" id="ocr-header" onclick="ocrForm.togglePanel()">
                <div class="ocr-header-left">
                    <span class="ocr-icon">🤖</span>
                    <span class="ocr-title">OCR Assistant</span>
                    <span class="ocr-status-badge" id="ocr-status-badge">Ready</span>
                </div>
                <div class="ocr-header-right">
                    <span class="ocr-stats-mini" id="ocr-stats-mini">0 fields extracted</span>
                    <button type="button" class="ocr-collapse-btn" id="ocr-collapse-btn">
                        <span class="collapse-icon">▼</span>
                    </button>
                </div>
            </div>

            <!-- Expandable Content -->
            <div class="ocr-content" id="ocr-content" style="display: none;">

                <!-- Progress Bar -->
                <div class="ocr-progress-section" id="ocr-progress-section" style="display: none;">
                    <div class="ocr-progress-bar">
                        <div class="ocr-progress-fill" id="ocr-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="ocr-progress-text" id="ocr-progress-text">Processing...</div>
                </div>

                <!-- Accordion Section 1: Page Selection -->
                <div class="ocr-accordion-section" id="page-selector-section" style="display: none;">
                    <div class="ocr-accordion-header" onclick="ocrForm.toggleAccordion('pageSelector')">
                        <span class="accordion-icon" id="accordion-icon-pageSelector">▶</span>
                        <span class="accordion-title">Page Selection</span>
                        <span class="accordion-badge" id="page-count-badge">0 pages</span>
                    </div>
                    <div class="ocr-accordion-content" id="accordion-content-pageSelector" style="display: none;">
                        <div id="page-selector-container-v2"></div>
                    </div>
                </div>

                <!-- Processing Button -->
                <div class="ocr-process-section">
                    <button type="button" class="btn-process" id="ocr-process-btn" disabled onclick="ocrForm.processCurrentPDF()">
                        <span class="btn-icon">📄</span>
                        <span class="btn-text">Process PDF</span>
                    </button>
                </div>

                <!-- Accordion Section 2: Extracted Data (Compact Format) -->
                <div class="ocr-accordion-section" id="results-section" style="display: none;">
                    <div class="ocr-accordion-header" onclick="ocrForm.toggleAccordion('results')">
                        <span class="accordion-icon" id="accordion-icon-results">▼</span>
                        <span class="accordion-title">Extracted Data</span>
                        <span class="accordion-badge" id="fields-count-badge">0 fields</span>
                    </div>
                    <div class="ocr-accordion-content" id="accordion-content-results">
                        <!-- Compact Data Table -->
                        <div class="ocr-data-table" id="ocr-data-table">
                            <!-- Data rows will be inserted here -->
                        </div>

                        <!-- Action Buttons -->
                        <div class="ocr-actions-compact">
                            <button type="button" class="btn-apply-all" id="ocr-apply-btn" disabled onclick="ocrForm.applyAllSuggestions()">
                                ✅ Apply All
                            </button>
                            <button type="button" class="btn-clear" id="ocr-clear-btn" disabled onclick="ocrForm.clearSuggestions()">
                                ❌ Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Accordion Section 3: Settings -->
                <div class="ocr-accordion-section">
                    <div class="ocr-accordion-header" onclick="ocrForm.toggleAccordion('settings')">
                        <span class="accordion-icon" id="accordion-icon-settings">▶</span>
                        <span class="accordion-title">Settings & Options</span>
                    </div>
                    <div class="ocr-accordion-content" id="accordion-content-settings" style="display: none;">
                        <div class="ocr-settings-compact">
                            <label class="setting-item">
                                <input type="checkbox" id="auto-process-checkbox" checked>
                                <span>Auto-process on file upload</span>
                            </label>
                            <label class="setting-item">
                                <input type="checkbox" id="auto-fill-checkbox">
                                <span>Auto-fill high confidence fields (>90%)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer Stats -->
                <div class="ocr-footer-stats" id="ocr-footer-stats" style="display: none;">
                    <span class="stat-item">
                        <span class="stat-label">Confidence:</span>
                        <span class="stat-value" id="footer-confidence">--</span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-label">Pages:</span>
                        <span class="stat-value" id="footer-pages">--</span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-label">Time:</span>
                        <span class="stat-value" id="footer-time">--</span>
                    </span>
                </div>
            </div>
        `;

        this.injectStyles();

        const pdfSection = document.querySelector('.pdf-upload-section') ||
                          document.querySelector('.certificate-pdf-upload') ||
                          document.querySelector('form');

        if (pdfSection) {
            pdfSection.insertAdjacentElement('afterend', panel);
        } else {
            document.body.appendChild(panel);
        }

        // Attach event listeners
        document.getElementById('auto-process-checkbox')?.addEventListener('change', (e) => {
            this.options.autoProcess = e.target.checked;
        });

        document.getElementById('auto-fill-checkbox')?.addEventListener('change', (e) => {
            this.options.autoFill = e.target.checked;
        });
    }

    /**
     * Toggle main panel collapse/expand
     */
    togglePanel() {
        const content = document.getElementById('ocr-content');
        const btn = document.getElementById('ocr-collapse-btn');
        const icon = btn.querySelector('.collapse-icon');

        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.textContent = '▲';
        } else {
            content.style.display = 'none';
            icon.textContent = '▼';
        }
    }

    /**
     * Toggle accordion sections
     */
    toggleAccordion(section) {
        const content = document.getElementById(`accordion-content-${section}`);
        const icon = document.getElementById(`accordion-icon-${section}`);

        this.accordionState[section] = !this.accordionState[section];

        if (this.accordionState[section]) {
            content.style.display = 'block';
            icon.textContent = '▼';
        } else {
            content.style.display = 'none';
            icon.textContent = '▶';
        }
    }

    /**
     * Update status badge
     */
    updateStatusBadge(status, type = 'info') {
        const badge = document.getElementById('ocr-status-badge');
        if (badge) {
            badge.textContent = status;
            badge.className = `ocr-status-badge badge-${type}`;
        }
    }

    /**
     * Attach file listener
     */
    attachFileListener() {
        const fileInput = document.getElementById(this.options.fileInputId);

        if (fileInput) {
            fileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];

                if (file && file.type === 'application/pdf') {
                    this.currentFile = file;

                    // Load PDF for page selection
                    if (this.pageSelector) {
                        try {
                            await this.loadPDFForPageSelection(file);
                        } catch (error) {
                            console.warn('Could not load PDF for page selection:', error);
                        }
                    }

                    // Enable process button
                    document.getElementById('ocr-process-btn').disabled = false;
                    this.updateStatusBadge('PDF Loaded', 'success');

                    // Auto-process if enabled
                    if (this.options.autoProcess) {
                        setTimeout(() => this.processCurrentPDF(), 500);
                    }
                }
            });
        }
    }

    /**
     * Load PDF and conditionally show page selector
     */
    async loadPDFForPageSelection(file) {
        if (!this.pageSelector) return;

        const totalPages = await this.pageSelector.loadPDF(file);

        // ONLY show page selector if multi-page PDF
        const selectorSection = document.getElementById('page-selector-section');
        const container = document.getElementById('page-selector-container-v2');
        const badge = document.getElementById('page-count-badge');

        if (totalPages > 1) {
            selectorSection.style.display = 'block';
            container.innerHTML = this.pageSelector.createUI();
            this.pageSelector.showSelector();
            badge.textContent = `${totalPages} pages`;
            console.log(`📄 Multi-page PDF detected - Page selector enabled`);
        } else {
            selectorSection.style.display = 'none';
            console.log(`📄 Single page PDF - Page selector hidden`);
        }
    }

    /**
     * Process current PDF
     */
    async processCurrentPDF() {
        if (!this.currentFile) {
            alert('Please select a PDF file first');
            return;
        }

        this.showProgress(true);
        this.updateStatus('Initializing OCR...');
        this.updateStatusBadge('Processing...', 'processing');

        try {
            const selectedPages = this.pageSelector ? this.pageSelector.getSelectedPages() : null;

            this.ocrResult = await this.processor.processPDF(this.currentFile, {
                selectedPages: selectedPages
            });

            if (this.ocrResult.success) {
                this.updateStatus(`Successfully processed`);
                this.updateStatusBadge('Complete', 'success');
                this.displayResults(this.ocrResult);

                // Enable action buttons
                document.getElementById('ocr-apply-btn').disabled = false;
                document.getElementById('ocr-clear-btn').disabled = false;
            } else {
                throw new Error(this.ocrResult.error || 'OCR processing failed');
            }
        } catch (error) {
            console.error('OCR Error:', error);
            this.updateStatus(`Error: ${error.message}`);
            this.updateStatusBadge('Error', 'error');
            this.showProgress(false);
        }
    }

    /**
     * Display results in compact format
     */
    displayResults(result) {
        this.showProgress(false);

        const resultsSection = document.getElementById('results-section');
        const dataTable = document.getElementById('ocr-data-table');
        const fieldsBadge = document.getElementById('fields-count-badge');
        const statsSection = document.getElementById('ocr-footer-stats');
        const statsMini = document.getElementById('ocr-stats-mini');

        resultsSection.style.display = 'block';
        statsSection.style.display = 'flex';

        // Auto-expand results
        this.accordionState.results = true;
        document.getElementById('accordion-content-results').style.display = 'block';
        document.getElementById('accordion-icon-results').textContent = '▼';

        const data = result.structuredData || {};
        let html = '';
        let count = 0;

        // Build compact data rows
        for (const [field, value] of Object.entries(data)) {
            if (value && value !== null && field !== 'confidence_scores') {
                const confidence = result.structuredData?.confidence_scores?.[field] || 75;
                const confidenceClass = confidence > 90 ? 'high' : confidence > 70 ? 'medium' : 'low';

                html += `
                    <div class="ocr-data-row">
                        <div class="data-field-name">${this.formatFieldName(field)}</div>
                        <div class="data-field-value">${value}</div>
                        <div class="data-confidence ${confidenceClass}">${Math.round(confidence)}%</div>
                        <button class="btn-use-compact" onclick="ocrForm.useSuggestion('${field}', '${this.escapeHtml(value)}')">
                            Use
                        </button>
                    </div>
                `;
                count++;
            }
        }

        if (count === 0) {
            html = '<div class="no-data">No data extracted</div>';
        }

        dataTable.innerHTML = html;
        fieldsBadge.textContent = `${count} fields`;
        statsMini.textContent = `${count} fields extracted`;

        // Update footer stats
        document.getElementById('footer-confidence').textContent = `${Math.round(result.confidence || 0)}%`;
        document.getElementById('footer-pages').textContent = result.pages || 1;
        document.getElementById('footer-time').textContent = result.processing_time ? `${result.processing_time}s` : '--';

        this.suggestions = data;
    }

    formatFieldName(field) {
        return field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/'/g, '&#39;');
    }

    showProgress(show) {
        const section = document.getElementById('ocr-progress-section');
        if (section) {
            section.style.display = show ? 'block' : 'none';
        }
    }

    updateStatus(message) {
        const text = document.getElementById('ocr-progress-text');
        if (text) {
            text.textContent = message;
        }
    }

    updateProgress(percent) {
        const fill = document.getElementById('ocr-progress-fill');
        if (fill) {
            fill.style.width = `${percent}%`;
        }
    }

    /**
     * Apply single suggestion
     */
    useSuggestion(fieldName, value) {
        const formFieldId = this.fieldMapper?.fieldMapping?.[fieldName] || fieldName;
        const formField = document.getElementById(formFieldId);

        if (formField) {
            const convertedValue = this.fieldMapper ? this.fieldMapper.convertValue(value, formField) : value;
            formField.value = convertedValue;
            formField.dispatchEvent(new Event('change', { bubbles: true }));

            // Flash green
            formField.style.transition = 'background-color 0.3s';
            formField.style.backgroundColor = '#d1e7dd';
            setTimeout(() => {
                formField.style.backgroundColor = '';
            }, 1000);

            console.log(`✅ Applied: ${fieldName} = "${convertedValue}"`);
        }
    }

    /**
     * Apply all suggestions
     */
    applyAllSuggestions() {
        if (!this.fieldMapper || !this.suggestions) return;

        const results = this.fieldMapper.applyToForm(this.suggestions);

        if (results.filled.length > 0) {
            this.updateStatusBadge(`${results.filled.length} Fields Applied`, 'success');
            console.log(`✅ Applied ${results.filled.length} fields`);
        }
    }

    /**
     * Clear suggestions
     */
    clearSuggestions() {
        this.suggestions = {};
        document.getElementById('ocr-data-table').innerHTML = '<div class="no-data">No data</div>';
        document.getElementById('fields-count-badge').textContent = '0 fields';
        document.getElementById('ocr-stats-mini').textContent = '0 fields extracted';
        this.updateStatusBadge('Cleared', 'info');

        document.getElementById('ocr-apply-btn').disabled = true;
        document.getElementById('ocr-clear-btn').disabled = true;
    }

    createSuggestionButtons() {
        // Kept for compatibility
    }

    /**
     * Inject clean, compact styles
     */
    injectStyles() {
        if (document.getElementById('ocr-styles-v2')) return;

        const style = document.createElement('style');
        style.id = 'ocr-styles-v2';
        style.textContent = `
            /* Clean OCR Panel V2 */
            .ocr-panel-v2 {
                background: #ffffff;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                margin: 15px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                overflow: hidden;
            }

            /* Header (Always Visible) */
            .ocr-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 16px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                cursor: pointer;
                user-select: none;
            }

            .ocr-header-left {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .ocr-icon {
                font-size: 18px;
            }

            .ocr-title {
                font-weight: 600;
                font-size: 14px;
                color: #ffffff;
            }

            .ocr-status-badge {
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 600;
                background: rgba(255,255,255,0.3);
                color: #ffffff;
            }

            .ocr-status-badge.badge-success { background: #10b981; }
            .ocr-status-badge.badge-processing { background: #f59e0b; animation: pulse 1.5s infinite; }
            .ocr-status-badge.badge-error { background: #ef4444; }
            .ocr-status-badge.badge-info { background: rgba(255,255,255,0.3); }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }

            .ocr-header-right {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .ocr-stats-mini {
                font-size: 12px;
                color: rgba(255,255,255,0.9);
            }

            .ocr-collapse-btn {
                background: rgba(255,255,255,0.2);
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                color: #ffffff;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }

            .ocr-collapse-btn:hover {
                background: rgba(255,255,255,0.3);
            }

            /* Content Area */
            .ocr-content {
                padding: 16px;
                background: #f9fafb;
            }

            /* Progress Section */
            .ocr-progress-section {
                margin-bottom: 16px;
            }

            .ocr-progress-bar {
                height: 6px;
                background: #e5e7eb;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 8px;
            }

            .ocr-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                transition: width 0.3s ease;
            }

            .ocr-progress-text {
                font-size: 12px;
                color: #6b7280;
                text-align: center;
            }

            /* Accordion Sections */
            .ocr-accordion-section {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                margin-bottom: 10px;
                overflow: hidden;
            }

            .ocr-accordion-header {
                padding: 10px 14px;
                background: #f9fafb;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 10px;
                user-select: none;
                transition: background 0.2s;
            }

            .ocr-accordion-header:hover {
                background: #f3f4f6;
            }

            .accordion-icon {
                font-size: 12px;
                color: #6b7280;
                width: 12px;
            }

            .accordion-title {
                font-weight: 600;
                font-size: 13px;
                color: #374151;
                flex: 1;
            }

            .accordion-badge {
                font-size: 11px;
                color: #6b7280;
                background: #e5e7eb;
                padding: 2px 8px;
                border-radius: 10px;
            }

            .ocr-accordion-content {
                padding: 14px;
            }

            /* Process Button */
            .ocr-process-section {
                margin-bottom: 16px;
            }

            .btn-process {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .btn-process:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            .btn-process:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Compact Data Table */
            .ocr-data-table {
                margin-bottom: 12px;
            }

            .ocr-data-row {
                display: grid;
                grid-template-columns: 1.5fr 2fr auto auto;
                gap: 10px;
                padding: 10px;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 4px;
                margin-bottom: 6px;
                align-items: center;
                font-size: 13px;
            }

            .data-field-name {
                font-weight: 600;
                color: #374151;
                font-size: 12px;
            }

            .data-field-value {
                color: #1f2937;
                font-family: 'Courier New', monospace;
            }

            .data-confidence {
                font-size: 11px;
                font-weight: 600;
                padding: 2px 6px;
                border-radius: 10px;
                text-align: center;
            }

            .data-confidence.high { background: #d1fae5; color: #065f46; }
            .data-confidence.medium { background: #fef3c7; color: #92400e; }
            .data-confidence.low { background: #fee2e2; color: #991b1b; }

            .btn-use-compact {
                padding: 4px 12px;
                background: #10b981;
                color: #ffffff;
                border: none;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }

            .btn-use-compact:hover {
                background: #059669;
            }

            .no-data {
                text-align: center;
                padding: 20px;
                color: #9ca3af;
                font-size: 13px;
            }

            /* Actions */
            .ocr-actions-compact {
                display: flex;
                gap: 8px;
                margin-top: 12px;
            }

            .btn-apply-all,
            .btn-clear {
                flex: 1;
                padding: 8px;
                border: none;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }

            .btn-apply-all {
                background: #10b981;
                color: #ffffff;
            }

            .btn-apply-all:hover:not(:disabled) {
                background: #059669;
                transform: translateY(-1px);
            }

            .btn-clear {
                background: #ef4444;
                color: #ffffff;
            }

            .btn-clear:hover:not(:disabled) {
                background: #dc2626;
                transform: translateY(-1px);
            }

            .btn-apply-all:disabled,
            .btn-clear:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Settings */
            .ocr-settings-compact {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .setting-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: #374151;
                cursor: pointer;
            }

            .setting-item input[type="checkbox"] {
                cursor: pointer;
            }

            /* Footer Stats */
            .ocr-footer-stats {
                display: flex;
                justify-content: space-around;
                padding: 10px;
                background: #f9fafb;
                border-top: 1px solid #e5e7eb;
                font-size: 12px;
            }

            .stat-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
            }

            .stat-label {
                color: #6b7280;
                font-size: 11px;
            }

            .stat-value {
                font-weight: 600;
                color: #1f2937;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .ocr-data-row {
                    grid-template-columns: 1fr;
                    gap: 6px;
                }

                .data-field-name {
                    font-weight: 700;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Make globally available
window.OCRFormIntegration = OCRFormIntegration;
console.log('✓ OCR Form Integration V2 loaded (Clean Design)');
