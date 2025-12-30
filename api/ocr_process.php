<?php
/**
 * OCR Processing API Endpoint
 * Processes PDFs using server-side Tesseract OCR
 * MUCH faster than browser-based processing
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/TesseractOCR.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log

try {
    // Check if file was uploaded
    if (!isset($_FILES['pdf_file'])) {
        throw new Exception('No PDF file uploaded');
    }

    $file = $_FILES['pdf_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        throw new Exception('Invalid file type. Only PDF files are allowed.');
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 10MB.');
    }

    // Get selected pages if provided
    $selectedPages = null;
    if (isset($_POST['selected_pages'])) {
        $selectedPages = json_decode($_POST['selected_pages'], true);
        if (!is_array($selectedPages)) {
            $selectedPages = null;
        }
    }

    // Initialize OCR processor
    $ocr = new TesseractOCR($pdo);

    // Process the PDF with optional page selection
    $result = $ocr->processPDF($file['tmp_name'], $selectedPages);

    // Clean up temp file
    @unlink($file['tmp_name']);

    // Return result
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
