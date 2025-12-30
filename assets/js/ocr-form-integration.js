/**
 * OCR Form Integration
 * Integrates OCR functionality into existing certificate forms
 * WITHOUT modifying the existing form HTML structure
 *
 * Features:
 * - Auto-detect file upload
 * - Extract text from PDF
 * - Suggest values for form fields
 * - Show confidence scores
 * - Allow user to accept/reject suggestions
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

        // Use ServerOCR if available (FAST!), fallback to browser OCR
        this.processor = typeof ServerOCR !== 'undefined' ? new ServerOCR() : new OCRProcessor();
        this.fieldMapper = typeof OCRFieldMapper !== 'undefined' ? new OCRFieldMapper() : null;
        this.pageSelector = null; // Will be initialized when PDF is loaded
        this.ocrResult = null;
        this.suggestions = {};
        this.currentFile = null;
        this.selectedPages = null;

        console.log('📱 OCR Mode:', this.processor.constructor.name === 'ServerOCR' ? 'Server (FAST)' : 'Browser (Slow)');

        this.init();
    }

    /**
     * Initialize OCR integration
     */
    init() {
        this.createOCRPanel();
        this.attachFileListener();
        this.createSuggestionButtons();
        this.initializePageSelector();
    }

    /**
     * Initialize page selector
     */
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
     * Create OCR control panel (injected into page)
     */
    createOCRPanel() {
        // Check if panel already exists
        if (document.getElementById('ocr-panel')) return;

        const panel = document.createElement('div');
        panel.id = 'ocr-panel';
        panel.className = 'ocr-panel';
        panel.innerHTML = `
            <div class="ocr-panel-header">
                <h3>🤖 OCR Assistant</h3>
                <button type="button" class="ocr-toggle-btn" onclick="ocrForm.togglePanel()">
                    <span class="toggle-icon">−</span>
                </button>
            </div>
            <div class="ocr-panel-content">
                <div class="ocr-status">
                    <div class="status-message">Ready to process PDF</div>
                    <div class="progress-container" style="display:none;">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>

                <!-- Page selector will be inserted here -->
                <div id="page-selector-container"></div>

                <div class="ocr-actions">
                    <button type="button" class="btn btn-ocr" onclick="ocrForm.processCurrentPDF()" disabled id="ocr-process-btn">
                        📄 Process PDF
                    </button>
                    <button type="button" class="btn btn-apply" onclick="ocrForm.applyAllSuggestions()" disabled id="ocr-apply-btn">
                        ✅ Apply All
                    </button>
                    <button type="button" class="btn btn-clear" onclick="ocrForm.clearSuggestions()" disabled id="ocr-clear-btn">
                        ❌ Clear
                    </button>
                </div>

                <div class="ocr-results" id="ocr-results" style="display:none;">
                    <h4>Extracted Data:</h4>
                    <div class="suggestions-list" id="suggestions-list">
                        <!-- Suggestions will be inserted here -->
                    </div>

                    <div class="ocr-stats">
                        <div class="stat">
                            <span class="stat-label">Confidence:</span>
                            <span class="stat-value" id="ocr-confidence">--</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Pages:</span>
                            <span class="stat-value" id="ocr-pages">--</span>
                        </div>
                    </div>
                </div>

                <div class="ocr-settings">
                    <label>
                        <input type="checkbox" id="auto-process-checkbox" checked>
                        Auto-process on file select
                    </label>
                    <label>
                        <input type="checkbox" id="auto-fill-checkbox">
                        Auto-fill high confidence fields (>90%)
                    </label>
                </div>
            </div>
        `;

        // Add styles
        this.injectStyles();

        // Insert panel into the PDF upload section
        const pdfSection = document.querySelector('.pdf-upload-section') ||
                          document.querySelector('.certificate-pdf-upload') ||
                          document.querySelector('form');

        if (pdfSection) {
            pdfSection.insertAdjacentElement('afterend', panel);
        } else {
            document.body.appendChild(panel);
        }

        // Add event listeners for settings
        document.getElementById('auto-process-checkbox').addEventListener('change', (e) => {
            this.options.autoProcess = e.target.checked;
        });

        document.getElementById('auto-fill-checkbox').addEventListener('change', (e) => {
            this.options.autoFill = e.target.checked;
        });
    }

    /**
     * Inject CSS styles
     */
    injectStyles() {
        if (document.getElementById('ocr-styles')) return;

        const style = document.createElement('style');
        style.id = 'ocr-styles';
        style.textContent = `
            .ocr-panel {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                padding: 0;
                margin: 20px 0;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
                overflow: hidden;
            }

            .ocr-panel-header {
                background: rgba(255,255,255,0.95);
                padding: 15px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 2px solid #667eea;
            }

            .ocr-panel-header h3 {
                margin: 0;
                color: #667eea;
                font-size: 1.1rem;
                font-weight: 600;
            }

            .ocr-toggle-btn {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #667eea;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .ocr-toggle-btn:hover {
                background: rgba(102, 126, 234, 0.1);
                border-radius: 4px;
            }

            .ocr-panel-content {
                padding: 20px;
                background: white;
            }

            .ocr-panel-content.collapsed {
                display: none;
            }

            .ocr-status {
                margin-bottom: 15px;
            }

            .status-message {
                font-size: 0.9rem;
                color: #555;
                margin-bottom: 10px;
            }

            .progress-container {
                margin-top: 10px;
            }

            .progress-bar {
                height: 8px;
                background: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
                margin-bottom: 5px;
            }

            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #667eea, #764ba2);
                transition: width 0.3s ease;
            }

            .progress-text {
                font-size: 0.8rem;
                color: #666;
                text-align: right;
            }

            .ocr-actions {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }

            .btn-ocr, .btn-apply, .btn-clear {
                padding: 8px 16px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.9rem;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .btn-ocr {
                background: #667eea;
                color: white;
            }

            .btn-ocr:hover:not(:disabled) {
                background: #5568d3;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }

            .btn-apply {
                background: #10b981;
                color: white;
            }

            .btn-apply:hover:not(:disabled) {
                background: #059669;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            }

            .btn-clear {
                background: #ef4444;
                color: white;
            }

            .btn-clear:hover:not(:disabled) {
                background: #dc2626;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            }

            .btn-ocr:disabled, .btn-apply:disabled, .btn-clear:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .ocr-results {
                margin-top: 15px;
            }

            .ocr-results h4 {
                margin: 0 0 10px 0;
                font-size: 0.95rem;
                color: #333;
            }

            .suggestions-list {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 10px;
            }

            .suggestion-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px;
                margin-bottom: 8px;
                background: #f9fafb;
                border-radius: 6px;
                border-left: 3px solid #667eea;
            }

            .suggestion-item:hover {
                background: #f3f4f6;
            }

            .suggestion-field {
                flex: 1;
            }

            .suggestion-label {
                font-size: 0.8rem;
                color: #6b7280;
                font-weight: 500;
                text-transform: uppercase;
            }

            .suggestion-value {
                font-size: 0.95rem;
                color: #111827;
                font-weight: 600;
                margin-top: 2px;
            }

            .suggestion-confidence {
                font-size: 0.75rem;
                padding: 2px 8px;
                border-radius: 12px;
                font-weight: 600;
                margin-left: 10px;
            }

            .confidence-high {
                background: #d1fae5;
                color: #065f46;
            }

            .confidence-medium {
                background: #fef3c7;
                color: #92400e;
            }

            .confidence-low {
                background: #fee2e2;
                color: #991b1b;
            }

            .suggestion-actions {
                display: flex;
                gap: 5px;
                margin-left: 10px;
            }

            .btn-apply-single {
                padding: 4px 12px;
                background: #10b981;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.8rem;
            }

            .btn-apply-single:hover {
                background: #059669;
            }

            .ocr-stats {
                display: flex;
                gap: 20px;
                margin-top: 15px;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
            }

            .stat {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .stat-label {
                font-size: 0.85rem;
                color: #6b7280;
                font-weight: 500;
            }

            .stat-value {
                font-size: 0.95rem;
                color: #111827;
                font-weight: 700;
            }

            .ocr-settings {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #e5e7eb;
            }

            .ocr-settings label {
                display: block;
                margin-bottom: 8px;
                font-size: 0.85rem;
                color: #374151;
                cursor: pointer;
            }

            .ocr-settings input[type="checkbox"] {
                margin-right: 8px;
            }

            @media (max-width: 768px) {
                .ocr-panel {
                    margin: 15px 10px;
                }

                .ocr-actions {
                    flex-direction: column;
                }

                .btn-ocr, .btn-apply, .btn-clear {
                    width: 100%;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Attach listener to file input
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

                    // Auto-process if enabled
                    if (this.options.autoProcess) {
                        setTimeout(() => this.processCurrentPDF(), 500);
                    }
                }
            });
        }
    }

    /**
     * Load PDF and show page selector
     */
    async loadPDFForPageSelection(file) {
        if (!this.pageSelector) return;

        // Load PDF to get page count
        const totalPages = await this.pageSelector.loadPDF(file);

        // Insert page selector UI into container
        const container = document.getElementById('page-selector-container');
        if (container && totalPages > 1) {
            container.innerHTML = this.pageSelector.createUI();
            this.pageSelector.showSelector();
            console.log(`📄 PDF loaded: ${totalPages} pages - Select which pages to scan`);
        } else if (container && totalPages === 1) {
            container.innerHTML = '<div style="padding: 10px; font-size: 13px; color: #6c757d;">Single page PDF - All pages will be processed</div>';
        }
    }

    /**
     * Process current PDF file
     */
    async processCurrentPDF() {
        if (!this.currentFile) {
            alert('Please select a PDF file first');
            return;
        }

        this.showProgress(true);
        this.updateStatus('Initializing OCR engine...');

        try {
            // Get selected pages from page selector
            const selectedPages = this.pageSelector ? this.pageSelector.getSelectedPages() : null;

            // Process PDF with selected pages
            this.ocrResult = await this.processor.processPDF(this.currentFile, {
                selectedPages: selectedPages
            });

            if (this.ocrResult.success) {
                this.updateStatus(`Successfully processed ${this.ocrResult.pages} page(s)`);
                this.displayResults(this.ocrResult);

                // Auto-fill if enabled and confidence is high
                if (this.options.autoFill) {
                    this.autoFillHighConfidence();
                }
            } else {
                this.updateStatus('OCR processing failed: ' + this.ocrResult.error);
            }
        } catch (error) {
            console.error('OCR error:', error);
            this.updateStatus('Error: ' + error.message);
        } finally {
            this.showProgress(false);
        }
    }

    /**
     * Display OCR results
     */
    displayResults(result) {
        const resultsDiv = document.getElementById('ocr-results');
        const suggestionsList = document.getElementById('suggestions-list');

        // Update stats
        document.getElementById('ocr-confidence').textContent = result.confidence.toFixed(1) + '%';
        document.getElementById('ocr-pages').textContent = result.pages;

        // Clear previous suggestions
        suggestionsList.innerHTML = '';

        // Build suggestions
        const data = result.structuredData;
        this.suggestions = {};

        console.log('🎨 Building suggestions from extracted data...');
        let displayedCount = 0;
        let skippedCount = 0;

        for (const [field, value] of Object.entries(data)) {
            if (field === 'confidence_scores') {
                continue; // Skip confidence_scores
            }

            if (value && value !== null) {
                // Get confidence for this field
                const confidence = this.estimateFieldConfidence(value, result);

                this.suggestions[field] = {
                    value: value,
                    confidence: confidence
                };

                // Create suggestion item
                const item = this.createSuggestionItem(field, value, confidence);
                suggestionsList.appendChild(item);
                displayedCount++;
                console.log(`  ✅ Displayed: ${field} = "${value}"`);
            } else {
                skippedCount++;
                console.log(`  ⏭️  Skipped (null/empty): ${field}`);
            }
        }

        console.log(`📊 Display Summary: ${displayedCount} shown, ${skippedCount} skipped (null/empty)`);

        // Show results
        resultsDiv.style.display = 'block';

        // Enable buttons
        document.getElementById('ocr-apply-btn').disabled = false;
        document.getElementById('ocr-clear-btn').disabled = false;
    }

    /**
     * Create suggestion item element
     */
    createSuggestionItem(field, value, confidence) {
        const item = document.createElement('div');
        item.className = 'suggestion-item';

        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'suggestion-field';

        const label = document.createElement('div');
        label.className = 'suggestion-label';
        label.textContent = this.formatFieldName(field);

        const valueDiv = document.createElement('div');
        valueDiv.className = 'suggestion-value';
        valueDiv.textContent = value;

        fieldDiv.appendChild(label);
        fieldDiv.appendChild(valueDiv);

        const confidenceDiv = document.createElement('div');
        confidenceDiv.className = 'suggestion-confidence ' + this.getConfidenceClass(confidence);
        confidenceDiv.textContent = confidence.toFixed(0) + '%';

        const actions = document.createElement('div');
        actions.className = 'suggestion-actions';

        const applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = 'btn-apply-single';
        applyBtn.textContent = 'Use This';
        applyBtn.onclick = () => this.applySingleField(field, value);

        actions.appendChild(applyBtn);

        item.appendChild(fieldDiv);
        item.appendChild(confidenceDiv);
        item.appendChild(actions);

        return item;
    }

    /**
     * Apply single field using field mapper
     */
    applySingleField(field, value) {
        if (this.fieldMapper) {
            console.log(`🎯 Applying single field: ${field} = ${value}`);

            const formField = document.getElementById(field);
            if (formField) {
                const convertedValue = this.fieldMapper.convertValue(value, formField);
                formField.value = convertedValue;
                formField.dispatchEvent(new Event('change', { bubbles: true }));
                formField.dispatchEvent(new Event('input', { bubbles: true }));

                // Visual feedback
                formField.style.backgroundColor = '#d1fae5';
                setTimeout(() => {
                    formField.style.backgroundColor = '';
                }, 1500);

                console.log(`✅ Applied: ${field} = ${convertedValue}`);
            }
        } else {
            this.applySuggestion(field, value);
        }
    }

    /**
     * Apply single suggestion (fallback)
     */
    applySuggestion(field, value) {
        const input = document.getElementById(field) ||
                     document.querySelector(`[name="${field}"]`);

        if (input) {
            input.value = value;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            // Visual feedback
            input.style.backgroundColor = '#d1fae5';
            setTimeout(() => {
                input.style.backgroundColor = '';
            }, 1000);
        }
    }

    /**
     * Apply all suggestions
     */
    applyAllSuggestions() {
        if (this.fieldMapper && this.ocrResult && this.ocrResult.structuredData) {
            console.log('🚀 Using Field Mapper to apply suggestions...');
            const results = this.fieldMapper.applyToForm(this.ocrResult.structuredData);

            const totalFilled = results.filled.length;
            this.updateStatus(`✅ Auto-filled ${totalFilled} field(s)`);

            // Show notification
            if (totalFilled > 0) {
                alert(`Successfully auto-filled ${totalFilled} fields!`);
            }
        } else {
            // Fallback to old method
            let applied = 0;

            for (const [field, data] of Object.entries(this.suggestions)) {
                if (data.confidence >= this.options.confidenceThreshold) {
                    this.applySuggestion(field, data.value);
                    applied++;
                }
            }

            this.updateStatus(`Applied ${applied} suggestion(s)`);
        }
    }

    /**
     * Auto-fill high confidence fields
     */
    autoFillHighConfidence() {
        let filled = 0;

        for (const [field, data] of Object.entries(this.suggestions)) {
            if (data.confidence >= 90) {
                this.applySuggestion(field, data.value);
                filled++;
            }
        }

        if (filled > 0) {
            this.updateStatus(`Auto-filled ${filled} high-confidence field(s)`);
        }
    }

    /**
     * Clear all suggestions
     */
    clearSuggestions() {
        document.getElementById('suggestions-list').innerHTML = '';
        document.getElementById('ocr-results').style.display = 'none';
        document.getElementById('ocr-apply-btn').disabled = true;
        document.getElementById('ocr-clear-btn').disabled = true;
        this.suggestions = {};
        this.ocrResult = null;
    }

    /**
     * Toggle panel visibility
     */
    togglePanel() {
        const content = document.querySelector('.ocr-panel-content');
        const icon = document.querySelector('.toggle-icon');

        content.classList.toggle('collapsed');
        icon.textContent = content.classList.contains('collapsed') ? '+' : '−';
    }

    /**
     * Show/hide progress indicator
     */
    showProgress(show) {
        const container = document.querySelector('.progress-container');
        if (container) {
            container.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * Update status message
     */
    updateStatus(message) {
        const statusDiv = document.querySelector('.status-message');
        if (statusDiv) {
            statusDiv.textContent = message;
        }
    }

    /**
     * Helper functions
     */
    formatFieldName(field) {
        return field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    getConfidenceClass(confidence) {
        if (confidence >= 80) return 'confidence-high';
        if (confidence >= 50) return 'confidence-medium';
        return 'confidence-low';
    }

    estimateFieldConfidence(value, result) {
        // Simple estimation - in production, use actual OCR confidence per field
        return result.confidence * (0.8 + Math.random() * 0.2);
    }

    /**
     * Create suggestion buttons next to form fields
     */
    createSuggestionButtons() {
        // This adds "OCR" indicators next to fields that can be auto-filled
        const fields = [
            'registry_no', 'date_of_registration',
            'child_first_name', 'child_middle_name', 'child_last_name',
            'child_date_of_birth', 'child_place_of_birth',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name'
        ];

        fields.forEach(fieldId => {
            const input = document.getElementById(fieldId);
            if (input && !input.nextElementSibling?.classList.contains('ocr-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'ocr-indicator';
                indicator.textContent = '🤖';
                indicator.title = 'Can be auto-filled by OCR';
                indicator.style.cssText = 'margin-left: 5px; opacity: 0.3; font-size: 14px;';
                input.parentNode.appendChild(indicator);
            }
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if OCRProcessor is available and we're on a certificate form
    if (typeof OCRProcessor !== 'undefined' &&
        (document.getElementById('certificateForm') || document.querySelector('form'))) {

        window.ocrForm = new OCRFormIntegration({
            autoProcess: true,
            autoFill: false,
            confidenceThreshold: 75
        });

        console.log('OCR Form Integration initialized');
    }
});

// Listen for progress updates
window.addEventListener('ocr-progress', (e) => {
    const progressFill = document.querySelector('.progress-fill');
    const progressText = document.querySelector('.progress-text');

    if (progressFill && progressText) {
        const percentage = e.detail.progress;
        progressFill.style.width = percentage + '%';
        progressText.textContent = percentage.toFixed(0) + '%';
    }
});

window.addEventListener('ocr-status', (e) => {
    const statusDiv = document.querySelector('.status-message');
    if (statusDiv) {
        statusDiv.textContent = e.detail.message;
    }
});
