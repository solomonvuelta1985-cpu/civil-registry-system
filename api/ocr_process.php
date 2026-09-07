<?php
/**
 * OCR Processing API Endpoint
 * Processes PDFs using server-side Tesseract OCR
 * MUCH faster than browser-based processing
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/session_config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/TesseractOCR.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        throw new Exception('Only POST is allowed');
    }
    requireAuth();
    if (!hasAnyPermission(['birth_create', 'birth_edit', 'marriage_create', 'marriage_edit', 'death_create', 'death_edit'])) {
        http_response_code(403);
        throw new Exception('You do not have permission to use OCR');
    }
    requireCSRFToken();
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

    // Keep OCR within the application upload limit and reject oversized values
    // before invoking external tools.
    if ($file['size'] > MAX_FILE_SIZE || $file['size'] > 25 * 1024 * 1024) {
        throw new Exception('File too large for OCR.');
    }

    // Get selected pages if provided
    $selectedPages = null;
    if (isset($_POST['selected_pages'])) {
        $selectedPages = json_decode($_POST['selected_pages'], true);
        if (!is_array($selectedPages)) {
            throw new Exception('Invalid page selection');
        }
        if (count($selectedPages) > 20) throw new Exception('Too many pages selected');
        foreach ($selectedPages as $page) {
            if (filter_var($page, FILTER_VALIDATE_INT) === false || (int)$page < 1 || (int)$page > 500) {
                throw new Exception('Invalid page selection');
            }
        }
    }

    // Initialize OCR processor
    $ocr = new TesseractOCR($pdo);

    // Process the PDF with optional page selection
    $result = $ocr->processPDF($file['tmp_name'], $selectedPages);

    // Clean up temp file
    @unlink($file['tmp_name']);

    // Return result
    echo json_encode($result, JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    $status = http_response_code();
    if ($status < 400) {
        $status = 500;
    }
    http_response_code($status);
    error_log('OCR request failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'OCR request could not be completed.'
    ]);
}
