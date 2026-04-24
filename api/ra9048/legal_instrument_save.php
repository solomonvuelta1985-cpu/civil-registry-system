<?php
/**
 * RA 9048 Legal Instrument — Save API
 * Creates a new legal instrument record (AUSF / Supplemental / Legitimation)
 */

require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

header('Content-Type: application/json');

requireAuth();
requireCSRFToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    // Sanitize inputs
    $instrument_type      = sanitize_input($_POST['instrument_type'] ?? '');
    $date_of_filing       = sanitize_input($_POST['date_of_filing'] ?? '');
    $document_owner_names = sanitize_input($_POST['document_owner_names'] ?? '');
    $father_name          = sanitize_input($_POST['father_name'] ?? '') ?: null;
    $mother_name          = sanitize_input($_POST['mother_name'] ?? '') ?: null;
    $affiant_names        = sanitize_input($_POST['affiant_names'] ?? '') ?: null;
    $document_type        = sanitize_input($_POST['document_type'] ?? '') ?: null;
    $registry_number      = sanitize_input($_POST['registry_number'] ?? '') ?: null;
    $supplemental_info    = sanitize_input($_POST['supplemental_info'] ?? '') ?: null;
    $legitimation_date    = sanitize_input($_POST['legitimation_date'] ?? '') ?: null;
    $applicable_law       = sanitize_input($_POST['applicable_law'] ?? '') ?: null;
    $remarks              = sanitize_input($_POST['remarks'] ?? '') ?: null;

    // Validate required fields
    $errors = [];
    if (!in_array($instrument_type, ['AUSF', 'Supplemental', 'Legitimation'])) {
        $errors[] = 'Instrument type must be AUSF, Supplemental, or Legitimation.';
    }
    if (empty($date_of_filing)) {
        $errors[] = 'Date of filing is required.';
    }
    if (empty($document_owner_names)) {
        $errors[] = 'Document Owner/s is required.';
    }

    // Validate document_type if provided
    if (!empty($document_type) && !in_array($document_type, ['COLB', 'COM', 'COD'])) {
        $errors[] = 'Invalid document type.';
    }

    if (!empty($errors)) {
        json_response(false, implode(' ', $errors));
    }

    // Handle PDF upload
    $pdf_filename = null;
    $pdf_filepath = null;
    $pdf_hash = null;
    if (!empty($_FILES['pdf_file']['name'])) {
        $upload_result = upload_file($_FILES['pdf_file'], 'ra9048_legal_instrument');
        if (!$upload_result['success']) {
            json_response(false, 'PDF upload failed: ' . implode(', ', $upload_result['errors']));
        }
        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'] ?? null;
        $pdf_hash = $upload_result['hash'] ?? null;
    }

    // Insert into database
    $pdo_ra->beginTransaction();

    $sql = "INSERT INTO legal_instruments (
                instrument_type, date_of_filing, document_owner_names, father_name, mother_name,
                affiant_names, document_type, registry_number, supplemental_info, legitimation_date,
                applicable_law, remarks, pdf_filename, pdf_filepath, pdf_hash, created_by
            ) VALUES (
                :instrument_type, :date_of_filing, :document_owner_names, :father_name, :mother_name,
                :affiant_names, :document_type, :registry_number, :supplemental_info, :legitimation_date,
                :applicable_law, :remarks, :pdf_filename, :pdf_filepath, :pdf_hash, :created_by
            )";

    $stmt = $pdo_ra->prepare($sql);
    $stmt->execute([
        ':instrument_type'      => $instrument_type,
        ':date_of_filing'       => $date_of_filing,
        ':document_owner_names' => $document_owner_names,
        ':father_name'          => $father_name,
        ':mother_name'          => $mother_name,
        ':affiant_names'        => $affiant_names,
        ':document_type'        => $document_type,
        ':registry_number'      => $registry_number,
        ':supplemental_info'    => $supplemental_info,
        ':legitimation_date'    => $legitimation_date,
        ':applicable_law'       => $applicable_law,
        ':remarks'              => $remarks,
        ':pdf_filename'         => $pdf_filename,
        ':pdf_filepath'         => $pdf_filepath,
        ':pdf_hash'             => $pdf_hash,
        ':created_by'           => $_SESSION['user_id'] ?? null,
    ]);

    $new_id = $pdo_ra->lastInsertId();

    log_activity($pdo, 'RA9048 Legal Instrument Created', "Legal Instrument #{$new_id} ({$instrument_type}) for {$document_owner_names}", $_SESSION['user_id'] ?? null);

    $pdo_ra->commit();

    json_response(true, 'Legal Instrument record saved successfully.', ['id' => $new_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Legal Instrument save error: " . $e->getMessage());
    json_response(false, 'Database error. Please try again.', null, 500);
}
