/**
 * OCR Page Selector
 * Allows users to select specific pages or ranges for OCR processing
 */

class OCRPageSelector {
    constructor(options = {}) {
        this.onPageSelectionChange = options.onPageSelectionChange || (() => {});
        this.totalPages = 0;
        this.selectedPages = [];
        this.selectionMode = 'all'; // 'all', 'custom', 'range'
        this.pdfFile = null;
        this.pagePreviewsLoaded = false;
    }

    /**
     * Initialize page selector UI
     */
    createUI() {
        const html = `
            <div id="page-selector-panel" class="page-selector-panel" style="display: none;">
                <div class="page-selector-header">
                    <h4>📄 Select Pages to Scan</h4>
                    <span class="page-count-badge">0 pages</span>
                </div>

                <div class="page-selector-modes">
                    <label class="mode-option">
                        <input type="radio" name="page_mode" value="all" checked>
                        <span class="mode-label">
                            <strong>All Pages</strong>
                            <small>Scan entire document</small>
                        </span>
                    </label>

                    <label class="mode-option">
                        <input type="radio" name="page_mode" value="range">
                        <span class="mode-label">
                            <strong>Page Range</strong>
                            <small>e.g., 1-5, 10-15</small>
                        </span>
                    </label>

                    <label class="mode-option">
                        <input type="radio" name="page_mode" value="custom">
                        <span class="mode-label">
                            <strong>Specific Pages</strong>
                            <small>e.g., 1, 6, 15</small>
                        </span>
                    </label>
                </div>

                <div id="range-input-container" class="range-input-container" style="display: none;">
                    <label>
                        <strong>Enter Page Range:</strong>
                        <input type="text" id="page-range-input" placeholder="e.g., 1-5 or 1-3, 8-10" class="page-range-input">
                        <small class="input-hint">Format: 1-5 (pages 1 to 5) or 1-3, 8-10 (multiple ranges)</small>
                    </label>
                </div>

                <div id="custom-input-container" class="custom-input-container" style="display: none;">
                    <label>
                        <strong>Enter Page Numbers:</strong>
                        <input type="text" id="page-custom-input" placeholder="e.g., 1, 6, 15" class="page-range-input">
                        <small class="input-hint">Format: 1, 6, 15 (comma-separated page numbers)</small>
                    </label>
                </div>

                <div id="page-preview-container" class="page-preview-container" style="display: none;">
                    <div class="preview-header">
                        <strong>Page Preview:</strong>
                        <button type="button" class="btn-toggle-preview" onclick="window.pageSelector.togglePreviews()">
                            Show Thumbnails
                        </button>
                    </div>
                    <div id="page-thumbnails" class="page-thumbnails"></div>
                </div>

                <div class="page-selector-summary">
                    <strong>Selected Pages:</strong>
                    <span id="selected-pages-display">All pages</span>
                </div>
            </div>
        `;

        return html;
    }

    /**
     * Load PDF and get total pages
     */
    async loadPDF(file) {
        this.pdfFile = file;

        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            this.totalPages = pdf.numPages;

            console.log(`📄 PDF loaded: ${this.totalPages} pages`);

            // Update UI
            this.showSelector();
            this.updatePageCount();

            // Store PDF for preview generation
            this.pdf = pdf;

            return this.totalPages;
        } catch (error) {
            console.error('❌ Error loading PDF:', error);
            throw error;
        }
    }

    /**
     * Show the page selector panel
     */
    showSelector() {
        const panel = document.getElementById('page-selector-panel');
        if (panel) {
            panel.style.display = 'block';
            this.attachEventListeners();
        }
    }

    /**
     * Hide the page selector panel
     */
    hideSelector() {
        const panel = document.getElementById('page-selector-panel');
        if (panel) {
            panel.style.display = 'none';
        }
    }

    /**
     * Update page count display
     */
    updatePageCount() {
        const badge = document.querySelector('.page-count-badge');
        if (badge) {
            badge.textContent = `${this.totalPages} pages`;
        }

        // Update input placeholders with valid range
        const rangeInput = document.getElementById('page-range-input');
        if (rangeInput) {
            rangeInput.placeholder = `e.g., 1-${this.totalPages} or 1-3, ${Math.max(1, this.totalPages - 2)}-${this.totalPages}`;
        }

        const customInput = document.getElementById('page-custom-input');
        if (customInput) {
            customInput.placeholder = `e.g., 1, ${Math.ceil(this.totalPages / 2)}, ${this.totalPages}`;
        }
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Mode change listeners
        const modeInputs = document.querySelectorAll('input[name="page_mode"]');
        modeInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.selectionMode = e.target.value;
                this.handleModeChange();
            });
        });

        // Range/custom input listeners
        const rangeInput = document.getElementById('page-range-input');
        if (rangeInput) {
            rangeInput.addEventListener('input', () => this.handleRangeInput());
        }

        const customInput = document.getElementById('page-custom-input');
        if (customInput) {
            customInput.addEventListener('input', () => this.handleCustomInput());
        }
    }

    /**
     * Handle mode change
     */
    handleModeChange() {
        const rangeContainer = document.getElementById('range-input-container');
        const customContainer = document.getElementById('custom-input-container');

        // Hide all input containers
        rangeContainer.style.display = 'none';
        customContainer.style.display = 'none';

        // Show relevant container
        if (this.selectionMode === 'range') {
            rangeContainer.style.display = 'block';
        } else if (this.selectionMode === 'custom') {
            customContainer.style.display = 'block';
        }

        this.updateSelection();
    }

    /**
     * Handle range input (e.g., "1-5, 10-15")
     */
    handleRangeInput() {
        const input = document.getElementById('page-range-input');
        const value = input.value.trim();

        try {
            this.selectedPages = this.parsePageRange(value);
            this.updateSelectionDisplay();
            input.style.borderColor = '#198754'; // Green
        } catch (error) {
            console.warn('Invalid range:', error.message);
            input.style.borderColor = '#dc3545'; // Red
        }
    }

    /**
     * Handle custom input (e.g., "1, 6, 15")
     */
    handleCustomInput() {
        const input = document.getElementById('page-custom-input');
        const value = input.value.trim();

        try {
            this.selectedPages = this.parseCustomPages(value);
            this.updateSelectionDisplay();
            input.style.borderColor = '#198754'; // Green
        } catch (error) {
            console.warn('Invalid page numbers:', error.message);
            input.style.borderColor = '#dc3545'; // Red
        }
    }

    /**
     * Parse page range string (e.g., "1-5, 10-15")
     */
    parsePageRange(rangeStr) {
        if (!rangeStr) return [];

        const pages = new Set();
        const ranges = rangeStr.split(',').map(s => s.trim());

        for (const range of ranges) {
            if (range.includes('-')) {
                const [start, end] = range.split('-').map(n => parseInt(n.trim()));
                if (isNaN(start) || isNaN(end)) throw new Error('Invalid range format');
                if (start < 1 || end > this.totalPages) throw new Error('Page out of range');
                if (start > end) throw new Error('Start page must be <= end page');

                for (let i = start; i <= end; i++) {
                    pages.add(i);
                }
            } else {
                const page = parseInt(range);
                if (isNaN(page)) throw new Error('Invalid page number');
                if (page < 1 || page > this.totalPages) throw new Error('Page out of range');
                pages.add(page);
            }
        }

        return Array.from(pages).sort((a, b) => a - b);
    }

    /**
     * Parse custom pages (e.g., "1, 6, 15")
     */
    parseCustomPages(pagesStr) {
        if (!pagesStr) return [];

        const pages = pagesStr
            .split(',')
            .map(s => parseInt(s.trim()))
            .filter(n => !isNaN(n) && n >= 1 && n <= this.totalPages);

        if (pages.length === 0) {
            throw new Error('No valid page numbers');
        }

        return Array.from(new Set(pages)).sort((a, b) => a - b);
    }

    /**
     * Update selection based on mode
     */
    updateSelection() {
        if (this.selectionMode === 'all') {
            this.selectedPages = Array.from({ length: this.totalPages }, (_, i) => i + 1);
        } else if (this.selectionMode === 'range') {
            this.handleRangeInput();
        } else if (this.selectionMode === 'custom') {
            this.handleCustomInput();
        }

        this.updateSelectionDisplay();
    }

    /**
     * Update selection display
     */
    updateSelectionDisplay() {
        const display = document.getElementById('selected-pages-display');
        if (!display) return;

        if (this.selectionMode === 'all') {
            display.textContent = `All pages (1-${this.totalPages})`;
            display.style.color = '#0d6efd';
        } else if (this.selectedPages.length === 0) {
            display.textContent = 'No pages selected';
            display.style.color = '#dc3545';
        } else {
            const summary = this.formatPageSummary(this.selectedPages);
            display.textContent = `${this.selectedPages.length} pages: ${summary}`;
            display.style.color = '#198754';
        }

        // Notify parent
        this.onPageSelectionChange({
            mode: this.selectionMode,
            pages: this.selectedPages,
            totalPages: this.totalPages
        });
    }

    /**
     * Format page summary for display
     */
    formatPageSummary(pages) {
        if (pages.length === 0) return 'None';
        if (pages.length <= 10) return pages.join(', ');

        // Show first 5 and last 5
        const first5 = pages.slice(0, 5).join(', ');
        const last5 = pages.slice(-5).join(', ');
        return `${first5} ... ${last5}`;
    }

    /**
     * Get selected pages
     */
    getSelectedPages() {
        if (this.selectionMode === 'all') {
            return Array.from({ length: this.totalPages }, (_, i) => i + 1);
        }
        return this.selectedPages;
    }

    /**
     * Toggle page preview thumbnails
     */
    async togglePreviews() {
        const container = document.getElementById('page-thumbnails');
        const btn = document.querySelector('.btn-toggle-preview');

        if (container.style.display === 'none' || !container.style.display) {
            btn.textContent = 'Hide Thumbnails';
            container.style.display = 'grid';

            if (!this.pagePreviewsLoaded) {
                await this.generatePreviews();
            }
        } else {
            btn.textContent = 'Show Thumbnails';
            container.style.display = 'none';
        }
    }

    /**
     * Generate page preview thumbnails
     */
    async generatePreviews() {
        const container = document.getElementById('page-thumbnails');
        container.innerHTML = '<div class="loading">Generating previews...</div>';

        try {
            const previews = [];
            const maxPreviews = Math.min(this.totalPages, 20); // Limit to 20 previews

            for (let i = 1; i <= maxPreviews; i++) {
                const page = await this.pdf.getPage(i);
                const scale = 0.3;
                const viewport = page.getViewport({ scale });

                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                const context = canvas.getContext('2d');

                await page.render({ canvasContext: context, viewport }).promise;

                previews.push(`
                    <div class="page-thumbnail" data-page="${i}">
                        <img src="${canvas.toDataURL()}" alt="Page ${i}">
                        <div class="page-number">Page ${i}</div>
                    </div>
                `);
            }

            container.innerHTML = previews.join('');
            this.pagePreviewsLoaded = true;

            if (this.totalPages > maxPreviews) {
                container.innerHTML += `<div class="preview-note">Showing first ${maxPreviews} of ${this.totalPages} pages</div>`;
            }
        } catch (error) {
            console.error('Error generating previews:', error);
            container.innerHTML = '<div class="error">Failed to generate previews</div>';
        }
    }
}

// Make globally available
window.OCRPageSelector = OCRPageSelector;
console.log('✓ OCR Page Selector loaded');
