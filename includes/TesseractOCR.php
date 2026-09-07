<?php
/**
 * Server-Side Tesseract OCR Processor
 * Uses installed Tesseract binary for FAST OCR processing
 * 10-20x faster than browser-based Tesseract.js
 */

class TesseractOCR {

    private $tesseractPath;
    private $pdftocairoPath;
    private $imageMagickPath;
    private $pdo;
    private $tempDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->tempDir = sys_get_temp_dir();

        // Auto-detect Tesseract installation path
        $this->tesseractPath = $this->detectTesseractPath();
        $this->pdftocairoPath = $this->detectToolPath('PDFTOCAIRO_PATH', [
            '/usr/bin/pdftocairo', '/usr/local/bin/pdftocairo',
            'C:\\Program Files\\poppler\\Library\\bin\\pdftocairo.exe',
            'C:\\Program Files (x86)\\poppler\\Library\\bin\\pdftocairo.exe',
        ]);
        $this->imageMagickPath = $this->detectToolPath('IMAGEMAGICK_PATH', [
            '/usr/bin/convert', '/usr/local/bin/convert',
            'C:\\Program Files\\ImageMagick-7.0.0-Q16-HDRI\\magick.exe',
            'C:\\Program Files\\ImageMagick-7.1.0-Q16-HDRI\\magick.exe',
        ]);
    }

    /**
     * Detect Tesseract installation path (cross-platform: Linux/Synology NAS + Windows/XAMPP)
     * Returns null if not found (graceful degradation for shared hosting)
     */
    private function detectTesseractPath() {
        $configured = getenv('TESSERACT_PATH');
        if (is_string($configured) && $configured !== '' && file_exists($configured)) {
            return $configured;
        }
        $possiblePaths = [
            // Linux / Synology NAS paths (checked first on Linux)
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract',
            '/opt/entware/bin/tesseract',
            // Windows / XAMPP paths
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }

        // Return null instead of throwing - allows graceful degradation
        return null;
    }

    private function detectToolPath($envName, array $candidates) {
        $configured = getenv($envName);
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }
        foreach ($candidates as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }

    /**
     * Check if Tesseract OCR is available on this server
     * @return bool
     */
    public function isAvailable() {
        return $this->tesseractPath !== null;
    }

    /**
     * Process PDF and extract text using Tesseract
     * @param string $pdfPath - Path to PDF file
     * @param array $selectedPages - Array of page numbers to process (optional)
     * @return array - OCR results
     */
    public function processPDF($pdfPath, $selectedPages = null) {
        if (!is_string($pdfPath) || !is_file($pdfPath) || !is_readable($pdfPath)) {
            throw new InvalidArgumentException('Invalid OCR input file');
        }
        if ($selectedPages !== null) {
            if (!is_array($selectedPages) || count($selectedPages) > 20) {
                throw new InvalidArgumentException('At most 20 pages may be selected');
            }
            $normalized = [];
            foreach ($selectedPages as $page) {
                if (filter_var($page, FILTER_VALIDATE_INT) === false || (int)$page < 1 || (int)$page > 500) {
                    throw new InvalidArgumentException('Invalid page selection');
                }
                $normalized[(int)$page] = (int)$page;
            }
            $selectedPages = array_values($normalized);
        }
        // Check if Tesseract is available
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'OCR service unavailable on this server',
                'error_code' => 'TESSERACT_NOT_INSTALLED',
                'suggestions' => [
                    'Use browser-based OCR (slower but works without server installation)',
                    'Process documents on local XAMPP with Tesseract installed, then upload',
                    'Enter data manually',
                    'Contact your hosting provider about Tesseract installation',
                    'Consider upgrading to VPS for full OCR support'
                ]
            ];
        }

        $startTime = microtime(true);

        // Calculate file hash for caching (include page selection in hash)
        $fileHash = hash_file('sha256', $pdfPath);
        if ($selectedPages) {
            $fileHash .= '_pages_' . implode('_', $selectedPages);
            $fileHash = hash('sha256', $fileHash);
        }

        // Check cache first
        $cached = $this->getCachedResult($fileHash);
        if ($cached) {
            return [
                'success' => true,
                'text' => $cached['ocr_text'],
                'structured_data' => json_decode($cached['structured_data'], true),
                'cached' => true,
                'processing_time' => 0,
                'pages_processed' => $selectedPages ? count($selectedPages) : 'all'
            ];
        }

        try {
            // Extract text using Tesseract
            $text = $this->extractText($pdfPath, $selectedPages);

            // Parse structured data
            $structuredData = $this->parseStructuredData($text);

            $processingTime = microtime(true) - $startTime;

            // Cache the result
            $this->cacheResult($fileHash, basename($pdfPath), filesize($pdfPath), $text, $structuredData, $processingTime);

            return [
                'success' => true,
                'text' => $text,
                'structured_data' => $structuredData,
                'cached' => false,
                'processing_time' => round($processingTime, 2),
                'pages_processed' => $selectedPages ? count($selectedPages) : 'all'
            ];

        } catch (Exception $e) {
            error_log('OCR processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'OCR processing failed. Please try again.'
            ];
        }
    }

    /**
     * Extract text from PDF using Tesseract
     * @param string $pdfPath - Path to PDF file
     * @param array $selectedPages - Array of page numbers to process (optional)
     */
    private function extractText($pdfPath, $selectedPages = null) {
        // If specific pages selected, we need to extract them first
        if ($selectedPages && count($selectedPages) > 0) {
            return $this->extractTextFromPages($pdfPath, $selectedPages);
        }

        // Process entire PDF
        $outputBase = $this->tempDir . DIRECTORY_SEPARATOR . 'crdms_ocr_' . bin2hex(random_bytes(16));

        // Build Tesseract command
        // PSM 6 = Assume a single uniform block of text
        $command = implode(' ', array_map([$this, 'quoteArg'], [
            $this->tesseractPath, $pdfPath, $outputBase, '-l', 'eng', '--psm', '6'
        ])) . ' 2>&1';
        $output = [];
        $returnCode = $this->runCommand($command, $output);

        // Read output file
        $textFile = $outputBase . '.txt';

        if (!file_exists($textFile)) {
            throw new Exception('Tesseract processing failed: ' . implode("\n", $output));
        }

        $text = file_get_contents($textFile);

        // Cleanup
        @unlink($textFile);

        return $text;
    }

    /**
     * Extract text from specific pages of PDF
     * Uses pdftk or similar to extract pages first, then OCR
     */
    private function extractTextFromPages($pdfPath, $selectedPages) {
        // For now, we'll use ImageMagick to extract specific pages as images
        // Then run Tesseract on each image

        $combinedText = '';

        foreach ($selectedPages as $pageNum) {
            $imageFile = $this->tempDir . DIRECTORY_SEPARATOR . 'crdms_page_' . bin2hex(random_bytes(16)) . '.png';
            $outputBase = $this->tempDir . DIRECTORY_SEPARATOR . 'crdms_ocr_page_' . bin2hex(random_bytes(16));

            // Convert PDF page to image using ImageMagick (if available)
            // Or use pdftocairo (comes with poppler-utils)
            $convertCmd = $this->pdftocairoPath ? implode(' ', array_map([$this, 'quoteArg'], [
                $this->pdftocairoPath, '-png', '-f', (string)$pageNum, '-l', (string)$pageNum,
                '-singlefile', $pdfPath, str_replace('.png', '', $imageFile)
            ])) . ' 2>&1' : '';
            $convertOutput = [];
            $convertCode = $convertCmd !== '' ? $this->runCommand($convertCmd, $convertOutput) : 1;

            // If pdftocairo not available, try ImageMagick convert
            if (($convertCode !== 0 || !file_exists($imageFile)) && $this->imageMagickPath) {
                $convertCmd = implode(' ', array_map([$this, 'quoteArg'], [
                    $this->imageMagickPath, '-density', '300', $pdfPath . '[' . ($pageNum - 1) . ']', $imageFile
                ])) . ' 2>&1';
                $convertOutput = [];
                $convertCode = $this->runCommand($convertCmd, $convertOutput);
            }

            if (!file_exists($imageFile)) {
                // Fallback: If we can't extract individual pages, process whole PDF
                continue;
            }

            // Run Tesseract on the image
            $command = implode(' ', array_map([$this, 'quoteArg'], [
                $this->tesseractPath, $imageFile, $outputBase, '-l', 'eng', '--psm', '6'
            ])) . ' 2>&1';
            $output = [];
            $returnCode = $this->runCommand($command, $output);

            $textFile = $outputBase . '.txt';

            if (file_exists($textFile)) {
                $combinedText .= "\n\n=== Page $pageNum ===\n\n";
                $combinedText .= file_get_contents($textFile);
                @unlink($textFile);
            }

            // Cleanup image
            @unlink($imageFile);
        }

        if (empty($combinedText)) {
            throw new Exception('Could not extract text from selected pages');
        }

        return $combinedText;
    }

    /**
     * Parse structured data from OCR text
     * Same logic as browser OCR but server-side
     */
    private function parseStructuredData($text) {
        $data = [];

        // Extract child's name
        if (preg_match('/1\.\s*NAME[^\n]*\n([^\n]*\n)?[^\n]*\n\s*([A-Z]{2,}(?:\s+[A-Z]{2,}){0,2})\b/i', $text, $match)) {
            $nameParts = array_filter(explode(' ', $match[2]));
            $data['child_first_name'] = $nameParts[0] ?? null;
            $data['child_last_name'] = $nameParts[1] ?? ($nameParts[2] ?? null);
            $data['child_middle_name'] = isset($nameParts[2]) ? $nameParts[1] : null;
        }

        // Extract sex
        if (preg_match('/2\.\s*SEX[^\n]*Male[^\n]*Female[^\n]*\n\s*(MALE|FEMALE)/i', $text, $match)) {
            $data['child_sex'] = strtoupper($match[1]);
        } elseif (preg_match('/2\.\s*SEX[^\n]{0,100}(MALE|FEMALE)/i', $text, $match)) {
            $data['child_sex'] = strtoupper($match[1]);
        }

        // Extract date of birth
        if (preg_match('/BIRTH[\s\S]{0,100}?(\d{1,2})\s+(JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)\s+(\d{4})/i', $text, $match)) {
            $data['child_date_of_birth'] = $match[2] . ' ' . $match[1] . ', ' . $match[3];
        }

        // Extract place of birth
        if (preg_match('/PLACE\s*OF\s*BIRTH[^\n]*\n[^\n]*\n\s*([A-Z][^\n]{3,40})/i', $text, $match)) {
            $data['child_place_of_birth'] = trim($match[1]);
        }

        // Extract type of birth
        if (preg_match('/(SINGLE|TWIN|TRIPLET|QUADRUPLET)/i', $text, $match)) {
            $data['type_of_birth'] = strtoupper($match[1]);
        }

        // Extract registry number
        if (preg_match('/Registry\s*No\.?\s*[:\s]*\n?\s*([A-Z0-9-]{3,20})/i', $text, $match)) {
            $regNo = trim($match[1]);
            if (!in_array(strtoupper($regNo), ['PROVINCE', 'CITY', 'MUNICIPALITY'])) {
                $data['registry_no'] = $regNo;
            }
        }

        return $data;
    }

    /**
     * Get cached OCR result
     */
    private function getCachedResult($fileHash) {
        $stmt = $this->pdo->prepare("
            SELECT ocr_text, structured_data
            FROM ocr_cache
            WHERE file_hash = ?
        ");
        $stmt->execute([$fileHash]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Update access stats
            $this->pdo->prepare("
                UPDATE ocr_cache
                SET last_accessed = NOW(), access_count = access_count + 1
                WHERE file_hash = ?
            ")->execute([$fileHash]);
        }

        return $result;
    }

    /**
     * Cache OCR result
     */
    private function cacheResult($fileHash, $fileName, $fileSize, $text, $structuredData, $processingTime) {
        $stmt = $this->pdo->prepare("
            INSERT INTO ocr_cache
            (file_hash, file_name, file_size, ocr_text, structured_data, processing_time, tesseract_version)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            ocr_text = VALUES(ocr_text),
            structured_data = VALUES(structured_data),
            processing_time = VALUES(processing_time),
            last_accessed = NOW()
        ");

        $tesseractVersion = $this->getTesseractVersion();

        $stmt->execute([
            $fileHash,
            $fileName,
            $fileSize,
            $text,
            json_encode($structuredData),
            $processingTime,
            $tesseractVersion
        ]);
    }

    /**
     * Get Tesseract version
     */
    private function getTesseractVersion() {
        $command = $this->quoteArg($this->tesseractPath) . ' --version 2>&1';
        $output = [];
        $this->runCommand($command, $output);
        return isset($output[0]) ? $output[0] : 'Unknown';
    }

    /** Quote every executable argument and run with a bounded output buffer. */
    private function quoteArg($value) {
        return escapeshellarg((string)$value);
    }

    private function runCommand($command, &$output) {
        $output = [];
        $returnCode = 1;
        $lines = [];
        @exec($command, $lines, $returnCode);
        $output = array_slice($lines, 0, 200);
        return $returnCode;
    }
}
