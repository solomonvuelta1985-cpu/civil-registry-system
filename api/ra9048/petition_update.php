<?php
/**
 * RA 9048 Petition — Update API
 * Updates an existing petition record (all fields + replaces child rows).
 */

require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';
require_once __DIR__ . '/_petition_helpers.php';

header('Content-Type: application/json');

requireAuth();
requireCSRFToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    $record_id = (int) ($_POST['record_id'] ?? 0);
    if ($record_id <= 0) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    $stmt = $pdo_ra->prepare("SELECT * FROM petitions WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        json_response(false, 'Record not found.', null, 404);
    }

    $payload = ra9048_extract_petition_payload();
    $errors  = ra9048_validate_petition_payload($payload);

    if (!empty($errors)) {
        json_response(false, implode(' ', $errors));
    }

    // Petition number uniqueness (allow keeping the same number on this record)
    $stmt = $pdo_ra->prepare("SELECT id FROM petitions WHERE petition_number = :n AND id <> :id LIMIT 1");
    $stmt->execute([':n' => $payload['petition_number'], ':id' => $record_id]);
    if ($stmt->fetch()) {
        json_response(false, "Petition number {$payload['petition_number']} is already used by another record.");
    }

    // PDF: keep existing unless new file uploaded
    $pdf_filename = $existing['pdf_filename'];
    $pdf_filepath = $existing['pdf_filepath'];
    $pdf_hash     = $existing['pdf_hash'];
    if (!empty($_FILES['pdf_file']['name'])) {
        $upload_result = upload_file($_FILES['pdf_file'], 'ra9048_petition');
        if (!$upload_result['success']) {
            json_response(false, 'PDF upload failed: ' . implode(', ', $upload_result['errors']));
        }
        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'] ?? null;
        $pdf_hash     = $upload_result['hash'] ?? null;
    }

    $pdo_ra->beginTransaction();

    $sql = "UPDATE petitions SET
                petition_type             = :petition_type,
                petition_subtype          = :petition_subtype,
                petition_number           = :petition_number,
                date_of_filing            = :date_of_filing,
                special_law               = :special_law,
                fee_amount                = :fee_amount,
                document_type             = :document_type,
                petition_of               = :petition_of,
                remarks                   = :remarks,
                petitioner_names          = :petitioner_names,
                petitioner_nationality    = :petitioner_nationality,
                petitioner_address        = :petitioner_address,
                petitioner_id_type        = :petitioner_id_type,
                petitioner_id_number      = :petitioner_id_number,
                is_self_petition          = :is_self_petition,
                relation_to_owner         = :relation_to_owner,
                document_owner_names      = :document_owner_names,
                owner_dob                 = :owner_dob,
                owner_birthplace_city     = :owner_birthplace_city,
                owner_birthplace_province = :owner_birthplace_province,
                owner_birthplace_country  = :owner_birthplace_country,
                registry_number           = :registry_number,
                father_full_name          = :father_full_name,
                mother_full_name          = :mother_full_name,
                cfn_ground                = :cfn_ground,
                cfn_ground_detail         = :cfn_ground_detail,
                notarized_at              = :notarized_at,
                receipt_number            = :receipt_number,
                payment_date              = :payment_date,
                posting_start_date        = :posting_start_date,
                posting_end_date          = :posting_end_date,
                posting_location          = :posting_location,
                order_date                = :order_date,
                publication_date_1        = :publication_date_1,
                publication_date_2        = :publication_date_2,
                publication_newspaper     = :publication_newspaper,
                publication_place         = :publication_place,
                opposition_deadline       = :opposition_deadline,
                pdf_filename              = :pdf_filename,
                pdf_filepath              = :pdf_filepath,
                pdf_hash                  = :pdf_hash,
                updated_by                = :updated_by
            WHERE id = :id";

    $stmt = $pdo_ra->prepare($sql);
    $stmt->execute([
        ':petition_type'             => $payload['petition_type'],
        ':petition_subtype'          => $payload['petition_subtype'],
        ':petition_number'           => $payload['petition_number'],
        ':date_of_filing'            => $payload['date_of_filing'],
        ':special_law'               => $payload['special_law'],
        ':fee_amount'                => $payload['fee_amount'],
        ':document_type'             => $payload['document_type'],
        ':petition_of'               => $payload['petition_of'],
        ':remarks'                   => $payload['remarks'],
        ':petitioner_names'          => $payload['petitioner_names'],
        ':petitioner_nationality'    => $payload['petitioner_nationality'],
        ':petitioner_address'        => $payload['petitioner_address'],
        ':petitioner_id_type'        => $payload['petitioner_id_type'],
        ':petitioner_id_number'      => $payload['petitioner_id_number'],
        ':is_self_petition'          => $payload['is_self_petition'],
        ':relation_to_owner'         => $payload['relation_to_owner'],
        ':document_owner_names'      => $payload['document_owner_names'],
        ':owner_dob'                 => $payload['owner_dob'],
        ':owner_birthplace_city'     => $payload['owner_birthplace_city'],
        ':owner_birthplace_province' => $payload['owner_birthplace_province'],
        ':owner_birthplace_country'  => $payload['owner_birthplace_country'],
        ':registry_number'           => $payload['registry_number'],
        ':father_full_name'          => $payload['father_full_name'],
        ':mother_full_name'          => $payload['mother_full_name'],
        ':cfn_ground'                => $payload['cfn_ground'],
        ':cfn_ground_detail'         => $payload['cfn_ground_detail'],
        ':notarized_at'              => $payload['notarized_at'],
        ':receipt_number'            => $payload['receipt_number'],
        ':payment_date'              => $payload['payment_date'],
        ':posting_start_date'        => $payload['posting_start_date'],
        ':posting_end_date'          => $payload['posting_end_date'],
        ':posting_location'          => $payload['posting_location'],
        ':order_date'                => $payload['order_date'],
        ':publication_date_1'        => $payload['publication_date_1'],
        ':publication_date_2'        => $payload['publication_date_2'],
        ':publication_newspaper'     => $payload['publication_newspaper'],
        ':publication_place'         => $payload['publication_place'],
        ':opposition_deadline'       => $payload['opposition_deadline'],
        ':pdf_filename'              => $pdf_filename,
        ':pdf_filepath'              => $pdf_filepath,
        ':pdf_hash'                  => $pdf_hash,
        ':updated_by'                => $_SESSION['user_id'] ?? null,
        ':id'                        => $record_id,
    ]);

    // Replace child rows
    $corrections     = $_POST['corrections']     ?? [];
    $supporting_docs = $_POST['supporting_docs'] ?? [];
    if (!is_array($corrections))     $corrections     = [];
    if (!is_array($supporting_docs)) $supporting_docs = [];
    ra9048_replace_child_rows($pdo_ra, $record_id, $corrections, $supporting_docs);

    log_activity(
        $pdo,
        'RA9048 Petition Updated',
        "Petition {$payload['petition_number']} ({$payload['petition_subtype']}) for {$payload['document_owner_names']}",
        $_SESSION['user_id'] ?? null
    );

    $pdo_ra->commit();

    json_response(true, 'Petition record updated successfully.', ['id' => $record_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Petition update error: " . $e->getMessage());
    if ($e->getCode() === '23000') {
        json_response(false, 'A petition with that number already exists.', null, 409);
    }
    json_response(false, 'Database error. Please try again.', null, 500);
}
