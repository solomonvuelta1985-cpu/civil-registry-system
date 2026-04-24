<?php
/**
 * RA 9048 Court Decree — Save API
 * Creates a new court decree record
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
    $decree_type             = sanitize_input($_POST['decree_type'] ?? '');
    $decree_type_other       = sanitize_input($_POST['decree_type_other'] ?? '') ?: null;
    $court_region            = sanitize_input($_POST['court_region'] ?? '') ?: null;
    $court_branch            = sanitize_input($_POST['court_branch'] ?? '') ?: null;
    $court_city_municipality = sanitize_input($_POST['court_city_municipality'] ?? '') ?: null;
    $court_province          = sanitize_input($_POST['court_province'] ?? '') ?: null;
    $case_number             = sanitize_input($_POST['case_number'] ?? '') ?: null;
    $date_of_decree          = sanitize_input($_POST['date_of_decree'] ?? '') ?: null;
    $date_of_filing          = sanitize_input($_POST['date_of_filing'] ?? '') ?: null;
    $document_owner_names    = sanitize_input($_POST['document_owner_names'] ?? '');
    $petitioner_names        = sanitize_input($_POST['petitioner_names'] ?? '') ?: null;
    $document_type           = sanitize_input($_POST['document_type'] ?? '') ?: null;
    $registry_number         = sanitize_input($_POST['registry_number'] ?? '') ?: null;
    $decree_details          = sanitize_input($_POST['decree_details'] ?? '') ?: null;
    $remarks                 = sanitize_input($_POST['remarks'] ?? '') ?: null;

    // Validate required fields
    $valid_types = ['Adoption', 'Annulment', 'Legal Separation', 'Correction of Entry', 'Naturalization', 'Recognition', 'Other'];
    $errors = [];

    if (!in_array($decree_type, $valid_types)) {
        $errors[] = 'Type of Court Decree is required.';
    }
    if ($decree_type === 'Other' && empty($decree_type_other)) {
        $errors[] = 'Please specify the decree type.';
    }
    if (empty($document_owner_names)) {
        $errors[] = 'Document Owner/s is required.';
    }
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
        $upload_result = upload_file($_FILES['pdf_file'], 'ra9048_court_decree');
        if (!$upload_result['success']) {
            json_response(false, 'PDF upload failed: ' . implode(', ', $upload_result['errors']));
        }
        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'] ?? null;
        $pdf_hash = $upload_result['hash'] ?? null;
    }

    // Insert into database
    $pdo_ra->beginTransaction();

    $sql = "INSERT INTO court_decrees (
                decree_type, decree_type_other, court_region, court_branch, court_city_municipality,
                court_province, case_number, date_of_decree, date_of_filing, document_owner_names,
                petitioner_names, document_type, registry_number, decree_details, remarks,
                pdf_filename, pdf_filepath, pdf_hash, created_by
            ) VALUES (
                :decree_type, :decree_type_other, :court_region, :court_branch, :court_city_municipality,
                :court_province, :case_number, :date_of_decree, :date_of_filing, :document_owner_names,
                :petitioner_names, :document_type, :registry_number, :decree_details, :remarks,
                :pdf_filename, :pdf_filepath, :pdf_hash, :created_by
            )";

    $stmt = $pdo_ra->prepare($sql);
    $stmt->execute([
        ':decree_type'             => $decree_type,
        ':decree_type_other'       => $decree_type_other,
        ':court_region'            => $court_region,
        ':court_branch'            => $court_branch,
        ':court_city_municipality' => $court_city_municipality,
        ':court_province'          => $court_province,
        ':case_number'             => $case_number,
        ':date_of_decree'          => $date_of_decree,
        ':date_of_filing'          => $date_of_filing,
        ':document_owner_names'    => $document_owner_names,
        ':petitioner_names'        => $petitioner_names,
        ':document_type'           => $document_type,
        ':registry_number'         => $registry_number,
        ':decree_details'          => $decree_details,
        ':remarks'                 => $remarks,
        ':pdf_filename'            => $pdf_filename,
        ':pdf_filepath'            => $pdf_filepath,
        ':pdf_hash'                => $pdf_hash,
        ':created_by'              => $_SESSION['user_id'] ?? null,
    ]);

    $new_id = $pdo_ra->lastInsertId();

    $type_label = $decree_type === 'Other' ? $decree_type_other : $decree_type;
    log_activity($pdo, 'RA9048 Court Decree Created', "Court Decree #{$new_id} ({$type_label}) for {$document_owner_names}", $_SESSION['user_id'] ?? null);

    $pdo_ra->commit();

    json_response(true, 'Court Decree record saved successfully.', ['id' => $new_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Court Decree save error: " . $e->getMessage());
    json_response(false, 'Database error. Please try again.', null, 500);
}
