/**
 * OCR Processor - PDF Text Extraction with Tesseract.js
 * Automatically extracts text from uploaded PDFs to assist with form filling
 *
 * Usage:
 *   const processor = new OCRProcessor();
 *   processor.processPDF(file).then(result => {
 *       console.log(result.text);
 *       console.log(result.confidence);
 *   });
 */

class OCRProcessor {
    constructor(options = {}) {
        this.options = {
            language: options.language || 'eng',
            workerPath: options.workerPath || 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/worker.min.js',
            corePath: options.corePath || 'https://cdn.jsdelivr.net/npm/tesseract.js-core@4/tesseract-core.wasm.js',
            langPath: options.langPath || 'https://tessdata.projectnaptha.com/4.0.0',
            logger: options.logger || null,
            dpi: options.dpi || 300,
            ...options
        };

        this.worker = null;
        this.initialized = false;
    }

    /**
     * Initialize Tesseract worker
     */
    async initialize() {
        if (this.initialized) return;

        try {
            this.worker = await Tesseract.createWorker({
                logger: this.options.logger || (m => {
                    if (m.status === 'recognizing text') {
                        this.updateProgress(m.progress * 100);
                    }
                }),
                workerPath: this.options.workerPath,
                corePath: this.options.corePath,
                langPath: this.options.langPath
            });

            await this.worker.loadLanguage(this.options.language);
            await this.worker.initialize(this.options.language);

            // Configure for better accuracy
            await this.worker.setParameters({
                tessedit_pageseg_mode: Tesseract.PSM.AUTO,
                tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,-/',
            });

            this.initialized = true;
            console.log('OCR Worker initialized successfully');
        } catch (error) {
            console.error('Failed to initialize OCR worker:', error);
            throw error;
        }
    }

    /**
     * Process PDF file and extract text
     * @param {File} file - PDF file to process
     * @param {Object} options - Processing options
     * @param {Array} options.selectedPages - Array of page numbers to process (optional)
     * @returns {Promise<Object>} Result with text and confidence
     */
    async processPDF(file, options = {}) {
        if (!file || file.type !== 'application/pdf') {
            throw new Error('Invalid file type. Please provide a PDF file.');
        }

        try {
            // Initialize worker if not already done
            await this.initialize();

            // Convert PDF to images
            const images = await this.pdfToImages(file, options.selectedPages);

            if (!images || images.length === 0) {
                throw new Error('Could not extract images from PDF');
            }

            console.log(`📄 Processing ${images.length} page(s)...`);
            if (options.selectedPages && options.selectedPages.length > 0) {
                console.log(`📋 Selected pages: ${options.selectedPages.join(', ')}`);
            }

            // Process all pages
            const results = [];
            let totalConfidence = 0;

            for (let i = 0; i < images.length; i++) {
                const actualPageNum = images[i].pageNumber || (i + 1);
                this.updateStatus(`Processing page ${actualPageNum} (${i + 1} of ${images.length})...`);

                const result = await this.worker.recognize(images[i].canvas || images[i]);

                results.push({
                    page: actualPageNum,
                    text: result.data.text,
                    confidence: result.data.confidence,
                    words: result.data.words,
                    lines: result.data.lines,
                    blocks: result.data.blocks
                });

                totalConfidence += result.data.confidence;
            }

            const averageConfidence = totalConfidence / results.length;

            // Combine all text
            const fullText = results.map(r => r.text).join('\n\n');

            // Extract structured data from text
            const structuredData = this.extractStructuredData(fullText, results);

            return {
                success: true,
                text: fullText,
                confidence: averageConfidence,
                pages: results.length,
                structuredData: structuredData,
                rawResults: results
            };

        } catch (error) {
            console.error('OCR processing error:', error);
            return {
                success: false,
                error: error.message,
                text: '',
                confidence: 0
            };
        }
    }

    /**
     * Convert PDF to images using PDF.js
     * @param {File} file - PDF file
     * @param {Array} selectedPages - Array of page numbers to convert (optional, defaults to all)
     * @returns {Promise<Array>} Array of canvas elements with pageNumber property
     */
    async pdfToImages(file, selectedPages = null) {
        return new Promise((resolve, reject) => {
            const fileReader = new FileReader();

            fileReader.onload = async function() {
                try {
                    const typedarray = new Uint8Array(this.result);

                    // Load PDF.js
                    if (typeof pdfjsLib === 'undefined') {
                        throw new Error('PDF.js library not loaded');
                    }

                    const loadingTask = pdfjsLib.getDocument(typedarray);
                    const pdf = await loadingTask.promise;

                    const images = [];

                    // Determine which pages to process
                    const pagesToProcess = selectedPages && selectedPages.length > 0
                        ? selectedPages
                        : Array.from({ length: pdf.numPages }, (_, i) => i + 1);

                    console.log(`🔄 Converting ${pagesToProcess.length} page(s) to images...`);

                    for (const pageNum of pagesToProcess) {
                        // Skip invalid page numbers
                        if (pageNum < 1 || pageNum > pdf.numPages) {
                            console.warn(`⚠️ Skipping invalid page number: ${pageNum}`);
                            continue;
                        }

                        const page = await pdf.getPage(pageNum);

                        // Set scale for better quality
                        const scale = 2.0;
                        const viewport = page.getViewport({ scale: scale });

                        // Prepare canvas
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        // Render PDF page to canvas
                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;

                        // Store page number with canvas
                        canvas.pageNumber = pageNum;
                        images.push(canvas);
                    }

                    resolve(images);
                } catch (error) {
                    reject(error);
                }
            };

            fileReader.onerror = () => reject(new Error('Failed to read PDF file'));
            fileReader.readAsArrayBuffer(file);
        });
    }

    /**
     * Extract structured data from OCR text
     * @param {string} fullText - Complete extracted text
     * @param {Array} results - Page-by-page results
     * @returns {Object} Structured data
     */
    extractStructuredData(fullText, results) {
        console.log('🔍 Extracting structured data from text...');

        // Extract child's full name
        // The format is: "1. NAME" -> "(First) (Middle) (Last)" -> "RICHMOND ROSETE"
        // We need to skip the labels and get the actual name (the line with all caps, 2-3 words)
        const nameLines = fullText.match(/1\.\s*NAME[^\n]*\n([^\n]*\n)?[^\n]*\n\s*([A-Z]{2,}(?:\s+[A-Z]{2,}){0,2})\b/i);
        let childNames = { first: null, middle: null, last: null };

        if (nameLines && nameLines[2]) {
            const nameText = nameLines[2].trim();
            // Filter out label words
            const nameParts = nameText.split(/\s+/).filter(part =>
                part.length > 1 &&
                !['FIRST', 'MIDDLE', 'LAST', 'NAME'].includes(part.toUpperCase())
            );

            if (nameParts.length >= 2) {
                childNames.first = nameParts[0];
                if (nameParts.length === 3) {
                    childNames.middle = nameParts[1];
                    childNames.last = nameParts[2];
                } else {
                    childNames.last = nameParts[1];
                }
            } else if (nameParts.length === 1) {
                childNames.first = nameParts[0];
            }
            console.log('✓ Child name extracted:', childNames);
        }

        // Extract sex (specifically look for MALE or FEMALE)
        let child_sex = null;
        const sexMatch = fullText.match(/2\.\s*SEX[^\n]*Male[^\n]*Female[^\n]*\n\s*(MALE|FEMALE)/i);
        if (sexMatch) {
            child_sex = sexMatch[1].toUpperCase().trim();
        } else {
            // Fallback: Look for MALE or FEMALE anywhere near "2. SEX"
            const sexSection = fullText.match(/2\.\s*SEX[^\n]{0,100}(MALE|FEMALE)/i);
            if (sexSection) {
                child_sex = sexSection[1].toUpperCase().trim();
            }
        }
        console.log('✓ Sex extracted:', child_sex);

        // Extract date of birth (format: "17 OCTOBER 1999")
        // More flexible pattern to handle OCR variations
        const dobMatch = fullText.match(/BIRTH[\s\S]{0,100}?(\d{1,2})\s+(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{4})/i);
        let child_date_of_birth = null;
        if (dobMatch) {
            child_date_of_birth = `${dobMatch[2]} ${dobMatch[1]}, ${dobMatch[3]}`;
            console.log('✓ Date of birth extracted:', child_date_of_birth);
        } else {
            console.log('✗ Date of birth NOT extracted');
        }

        // Extract place of birth - look for text after "PLACE OF BIRTH"
        let child_place_of_birth = null;
        const pobMatch = fullText.match(/PLACE\s*OF\s*BIRTH[^\n]*\n[^\n]*\n\s*([A-Z][^\n]{3,40})/i);
        if (pobMatch) {
            child_place_of_birth = pobMatch[1].trim();
        }
        console.log('✓ Place of birth extracted:', child_place_of_birth);

        // Extract Type of Birth - look for SINGLE, TWIN, TRIPLET, etc.
        let type_of_birth = null;
        const tobMatch = fullText.match(/(SINGLE|TWIN|TRIPLET|QUADRUPLET)/i);
        if (tobMatch) {
            type_of_birth = tobMatch[1].toUpperCase();
        }
        console.log('✓ Type of birth extracted:', type_of_birth);

        // Extract Birth Order (if multiple birth)
        const birthOrderMatch = fullText.match(/5c\.\s*BIRTH\s*ORDER[^\n]*\n\s*([A-Za-z0-9]+)/i);
        const birth_order = birthOrderMatch ? birthOrderMatch[1].trim() : null;
        console.log('✓ Birth order extracted:', birth_order);

        // Extract Registry Number (at top of form)
        const registryNo = this.extractRegistryNumber(fullText);
        console.log('✓ Registry number extracted:', registryNo);

        // Extract Mother's Name (Section 7. MAIDEN NAME)
        const motherNames = this.extractParentName(fullText, 'MAIDEN', '7');
        console.log('✓ Mother name extracted:', motherNames);

        // Extract Father's Name (Section 14. NAME - for father)
        const fatherNames = this.extractParentName(fullText, 'FATHER', '14');
        console.log('✓ Father name extracted:', fatherNames);

        const data = {
            registry_no: registryNo,
            child_first_name: childNames.first,
            child_middle_name: childNames.middle,
            child_last_name: childNames.last,
            child_sex: child_sex,
            child_date_of_birth: child_date_of_birth,
            child_place_of_birth: child_place_of_birth,
            type_of_birth: type_of_birth,
            birth_order: birth_order,
            mother_first_name: motherNames.first,
            mother_middle_name: motherNames.middle,
            mother_last_name: motherNames.last,
            father_first_name: fatherNames.first,
            father_middle_name: fatherNames.middle,
            father_last_name: fatherNames.last,
            confidence_scores: this.calculateFieldConfidence(results)
        };

        console.log('📦 Final structured data:', data);
        return data;
    }

    /**
     * Extract registry number from text
     */
    extractRegistryNumber(text) {
        const patterns = [
            /Registry\s*No\.?\s*[:\s]*\n?\s*([A-Z0-9-]{3,20})/i,
            /registry\s*(?:no|number)[:\s]*([A-Z0-9-]{3,20})/i,
            /reg\s*no[:\s]*([A-Z0-9-]{3,20})/i,
            /certificate\s*no[:\s]*([A-Z0-9-]{3,20})/i
        ];

        for (const pattern of patterns) {
            const match = text.match(pattern);
            if (match && match[1]) {
                const regNo = match[1].trim();
                // Filter out common false matches
                if (regNo.length >= 3 && !['PROVINCE', 'CITY', 'MUNICIPALITY'].includes(regNo.toUpperCase())) {
                    return regNo;
                }
            }
        }

        return null;
    }

    /**
     * Extract date from text
     */
    extractDate(text, keywords) {
        // Common date patterns
        const datePatterns = [
            /(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/,  // MM/DD/YYYY or DD/MM/YYYY
            /(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/,  // YYYY/MM/DD
            /([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})/,    // Month DD, YYYY
            /(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/       // DD Month YYYY
        ];

        for (const keyword of keywords) {
            const regex = new RegExp(keyword + '[:\\s]+([^\\n]{1,50})', 'i');
            const match = text.match(regex);

            if (match && match[1]) {
                const dateText = match[1].trim();

                for (const pattern of datePatterns) {
                    const dateMatch = dateText.match(pattern);
                    if (dateMatch) {
                        return this.formatDate(dateMatch);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract parent name (Mother or Father)
     * Handles Philippine birth certificate format:
     * "7. MAIDEN NAME" followed by labels "(First) (Middle) (Last)" then actual name
     * "14. NAME" followed by labels "(First) (Middle) (Last)" then actual name
     */
    extractParentName(text, parentType, sectionNumber) {
        // Pattern: Section number -> labels -> actual name (2-3 uppercase words)
        const pattern = new RegExp(
            sectionNumber + '\\.\\s*(?:MAIDEN\\s+)?NAME[^\\n]*\\n' +
            '[^\\n]*\\n' +  // Skip label line with (First) (Middle) (Last)
            '\\s*([A-Z]{2,}(?:\\s+[A-Z]{2,}){0,2})\\b',
            'i'
        );

        const match = text.match(pattern);
        const names = { first: null, middle: null, last: null };

        if (match && match[1]) {
            const nameText = match[1].trim();
            const nameParts = nameText.split(/\s+/).filter(part =>
                part.length > 1 &&
                !['FIRST', 'MIDDLE', 'LAST', 'NAME', 'MAIDEN'].includes(part.toUpperCase())
            );

            if (nameParts.length >= 2) {
                names.first = nameParts[0];
                if (nameParts.length === 3) {
                    names.middle = nameParts[1];
                    names.last = nameParts[2];
                } else {
                    names.last = nameParts[1];
                }
            } else if (nameParts.length === 1) {
                names.first = nameParts[0];
            }
        }

        return names;
    }

    /**
     * Extract name from text (legacy fallback)
     */
    extractName(text, nameType, personType) {
        const regex = new RegExp(personType + '[\'s]*\\s+' + nameType + '[:\\s]+([A-Za-z\\s]+)', 'i');
        const match = text.match(regex);

        if (match && match[1]) {
            return match[1].trim().split(/\s+/)[0]; // Get first word
        }

        return null;
    }

    /**
     * Extract place from text
     */
    extractPlace(text, keywords) {
        for (const keyword of keywords) {
            const regex = new RegExp(keyword + '[:\\s]+([^\\n]{1,100})', 'i');
            const match = text.match(regex);

            if (match && match[1]) {
                return match[1].trim();
            }
        }

        return null;
    }

    /**
     * Format date match to YYYY-MM-DD
     */
    formatDate(match) {
        // This is a simplified version - you may need to enhance based on your date formats
        if (match.length === 4) {
            // MM/DD/YYYY format
            const month = match[1].padStart(2, '0');
            const day = match[2].padStart(2, '0');
            const year = match[3];
            return `${year}-${month}-${day}`;
        }
        return null;
    }

    /**
     * Calculate confidence scores for individual fields
     */
    calculateFieldConfidence(results) {
        const scores = {};

        results.forEach((result, index) => {
            result.words.forEach(word => {
                const key = word.text.toLowerCase();
                if (!scores[key] || scores[key] < word.confidence) {
                    scores[key] = word.confidence;
                }
            });
        });

        return scores;
    }

    /**
     * Update progress callback
     */
    updateProgress(percentage) {
        if (typeof this.onProgress === 'function') {
            this.onProgress(percentage);
        }

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('ocr-progress', {
            detail: { progress: percentage }
        }));
    }

    /**
     * Update status callback
     */
    updateStatus(message) {
        if (typeof this.onStatus === 'function') {
            this.onStatus(message);
        }

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('ocr-status', {
            detail: { message: message }
        }));
    }

    /**
     * Terminate worker and cleanup
     */
    async terminate() {
        if (this.worker) {
            await this.worker.terminate();
            this.worker = null;
            this.initialized = false;
        }
    }
}

// Make available globally
window.OCRProcessor = OCRProcessor;
