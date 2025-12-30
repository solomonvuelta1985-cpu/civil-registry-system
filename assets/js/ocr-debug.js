/**
 * OCR Debug Script
 * Helps diagnose OCR integration issues
 */

console.log('=== OCR Debug Script Loaded ===');

window.addEventListener('load', function() {
    console.log('=== Page Fully Loaded ===');

    // Check if required libraries are loaded
    console.log('PDF.js loaded:', typeof pdfjsLib !== 'undefined');
    console.log('Tesseract.js loaded:', typeof Tesseract !== 'undefined');
    console.log('OCRProcessor loaded:', typeof OCRProcessor !== 'undefined');
    console.log('OCRFormIntegration loaded:', typeof OCRFormIntegration !== 'undefined');

    // Check if form exists
    const form = document.getElementById('certificateForm');
    console.log('Form found:', form !== null);

    // Check if file input exists
    const fileInput = document.getElementById('pdf_file');
    console.log('File input found:', fileInput !== null);

    // Check if OCR panel exists
    const ocrPanel = document.getElementById('ocr-panel');
    console.log('OCR panel found:', ocrPanel !== null);

    // Check if window.ocrForm exists
    console.log('window.ocrForm initialized:', window.ocrForm !== undefined);

    // If OCR form is initialized, log its state
    if (window.ocrForm) {
        console.log('OCR Form options:', window.ocrForm.options);
        console.log('OCR Processor:', window.ocrForm.processor);
    }

    // Manually trigger initialization if needed
    if (typeof OCRProcessor !== 'undefined' && !window.ocrForm) {
        console.log('Attempting manual OCR initialization...');
        try {
            window.ocrForm = new OCRFormIntegration({
                autoProcess: true,
                autoFill: false,
                confidenceThreshold: 75
            });
            console.log('✓ OCR manually initialized successfully');
        } catch (error) {
            console.error('✗ OCR manual initialization failed:', error);
        }
    }

    // Monitor file input changes
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            console.log('📁 File selected:', e.target.files[0]?.name);
        });
    }

    // Intercept OCR results
    const originalDisplayResults = window.ocrForm?.displayResults;
    if (originalDisplayResults && window.ocrForm) {
        window.ocrForm.displayResults = function(result) {
            console.log('📊 OCR Results:', result);
            console.log('📋 Structured Data:', result.structuredData);
            console.log('📝 Full Text:', result.text);
            return originalDisplayResults.call(this, result);
        };
    }
});
