<?php
/**
 * RA 9048 Petition — Update API
 * Updates an existing petition record (CCE/CFN)
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
    $record_id = sanitize_input($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Check if record exists
    $stmt = $pdo_ra->prepare("SELECT * FROM petitions WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => (int) $record_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        json_response(false, 'Record not found.', null, 404);
    }

    // Sanitize inputs
    $petition_type        = sanitize_input($_POST['petition_type'] ?? '');
    $date_of_filing       = sanitize_input($_POST['date_of_filing'] ?? '');
    $document_owner_names = sanitize_input($_POST['document_owner_names'] ?? '');
    $petitioner_names     = sanitize_input($_POST['petitioner_names'] ?? '');
    $document_type        = sanitize_input($_POST['document_type'] ?? '');
    $petition_of          = sanitize_input($_POST['petition_of'] ?? '') ?: null;
    $special_law          = sanitize_input($_POST['special_law'] ?? '') ?: null;
    $fee_amount           = floatval($_POST['fee_amount'] ?? 0);
    $remarks              = sanitize_input($_POST['remarks'] ?? '') ?: null;

    // Validate required fields
    $errors = [];
    if (!in_array($petition_type, ['CCE', 'CFN'])) {
        $errors[] = 'Petition type must be CCE or CFN.';
    }
    if (empty($date_of_filing)) {
        $errors[] = 'Date of filing is required.';
    }
    if (empty($document_owner_names)) {
        $errors[] = 'Document Owner/s is required.';
    }
    if (empty($petitioner_names)) {
        $errors[] = 'Name of Petitioner/s is required.';
    }
    if (!in_array($document_type, ['COLB', 'COM', 'COD'])) {
        $errors[] = 'Type of Document is required.';
    }

    // Handle PDF upload (optional for update)
    $pdf_filename = $existing['pdf_filename'];
    $pdf_filepath = $existing['pdf_filepath'];
    $pdf_hash     = $existing['pdf_hash'];

    if (!empty($_FILES['pdf_file']['name'])) {
        $upload_result = upload_file($_FILES['pdf_file'], 'ra9048_petition');
        if (!$upload_result['success']) {
            $errors[] = 'PDF upload failed: ' . implode(', ', $upload_result['errors']);
        } else {
            $pdf_filename = $upload_result['filename'];
            $pdf_filepath = $upload_result['path'] ?? null;
            $pdf_hash     = $upload_result['hash'] ?? null;
        }
    }

    if (!empty($errors)) {
        json_response(false, implode(' ', $errors));
    }

    // Update record
    $pdo_ra->beginTransaction();

    $sql = "UPDATE petitions SET
                petition_type        = :petition_type,
                date_of_filing       = :date_of_filing,
                document_owner_names = :document_owner_names,
                petitioner_names     = :petitioner_names,
                document_type        = :document_type,
                petition_of          = :petition_of,
                special_law          = :special_law,
                fee_amount           = :fee_amount,
                remarks              = :remarks,
                pdf_filename         = :pdf_filename,
                pdf_filepath         = :pdf_filepath,
                pdf_hash             = :pdf_hash,
                updated_by           = :updated_by
            WHERE id = :id";

    $stmt = $pdo_ra->prepare($sql);
    $stmt->execute([
        ':petition_type'        => $petition_type,
        ':date_of_filing'       => $date_of_filing,
        ':document_owner_names' => $document_owner_names,
        ':petitioner_names'     => $petitioner_names,
        ':document_type'        => $document_type,
        ':petition_of'          => $petition_of,
        ':special_law'          => $special_law,
        ':fee_amount'           => $fee_amount,
        ':remarks'              => $remarks,
        ':pdf_filename'         => $pdf_filename,
        ':pdf_filepath'         => $pdf_filepath,
        ':pdf_hash'             => $pdf_hash,
        ':updated_by'           => $_SESSION['user_id'] ?? null,
        ':id'                   => $record_id,
    ]);

    // Log activity in main database
    log_activity($pdo, 'RA9048 Petition Updated', "Petition #{$record_id} ({$petition_type}) for {$document_owner_names}", $_SESSION['user_id'] ?? null);

    $pdo_ra->commit();

    json_response(true, 'Petition record updated successfully.', ['id' => $record_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Petition update error: " . $e->getMessage());
    json_response(false, 'Database error. Please try again.', null, 500);
}
