/**
 * Server-Side OCR Client
 * Uses fast server-side Tesseract, falls back to browser if needed
 */

class ServerOCR {
    constructor() {
        this.apiEndpoint = '../api/ocr_process.php';
        this.browserOCR = null; // Fallback to browser OCR
    }

    /**
     * Process PDF using server-side OCR (FAST!)
     * Falls back to browser OCR if server fails
     * @param {File} file - PDF file to process
     * @param {Object} options - Processing options
     * @param {Array} options.selectedPages - Array of page numbers to process
     */
    async processPDF(file, options = {}) {
        console.log('🚀 Attempting server-side OCR (FAST mode)...');

        try {
            const result = await this.processServerSide(file, options.selectedPages);

            if (result.success) {
                console.log(`✅ Server OCR completed in ${result.processing_time}s${result.cached ? ' (CACHED!)' : ''}`);
                return this.formatResult(result);
            } else {
                throw new Error(result.error || 'Server OCR failed');
            }

        } catch (error) {
            console.warn('⚠️ Server OCR failed:', error.message);
            console.log('🔄 Falling back to browser OCR...');

            // Fallback to browser OCR
            if (typeof OCRProcessor !== 'undefined') {
                this.browserOCR = this.browserOCR || new OCRProcessor();
                return await this.browserOCR.processPDF(file, options);
            } else {
                throw new Error('Both server and browser OCR unavailable');
            }
        }
    }

    /**
     * Send PDF to server for processing
     * @param {File} file - PDF file
     * @param {Array} selectedPages - Page numbers to process (optional)
     */
    async processServerSide(file, selectedPages = null) {
        const formData = new FormData();
        formData.append('pdf_file', file);

        if (selectedPages && selectedPages.length > 0) {
            formData.append('selected_pages', JSON.stringify(selectedPages));
            console.log(`📋 Requesting pages: ${selectedPages.join(', ')}`);
        }

        const response = await fetch(this.apiEndpoint, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Format server result to match browser OCR format
     */
    formatResult(serverResult) {
        return {
            success: true,
            text: serverResult.text,
            confidence: 85, // Server OCR typically high confidence
            pages: 1, // Server processes all pages as one
            structuredData: serverResult.structured_data || {},
            cached: serverResult.cached || false,
            processing_time: serverResult.processing_time,
            source: 'server'
        };
    }
}

// Make globally available
window.ServerOCR = ServerOCR;
console.log('✓ Server OCR Client loaded');
