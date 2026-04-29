<?php
/**
 * RA 9048 Petition — Save API
 * Creates a new petition record (CCE_minor / CCE_10172 / CFN), with
 * petition_corrections and petition_supporting_docs child rows.
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
    $payload = ra9048_extract_petition_payload();
    $errors  = ra9048_validate_petition_payload($payload);

    if (!empty($errors)) {
        json_response(false, implode(' ', $errors));
    }

    // Pre-check: petition_number must be unique
    $stmt = $pdo_ra->prepare("SELECT id FROM petitions WHERE petition_number = :n LIMIT 1");
    $stmt->execute([':n' => $payload['petition_number']]);
    if ($stmt->fetch()) {
        json_response(false, "Petition number {$payload['petition_number']} already exists.");
    }

    // Handle PDF upload (optional)
    $pdf_filename = null;
    $pdf_filepath = null;
    $pdf_hash     = null;
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

    $sql = "INSERT INTO petitions (
                petition_type, petition_subtype, petition_number,
                date_of_filing, special_law, fee_amount,
                document_type, petition_of, remarks,
                petitioner_names, petitioner_nationality, petitioner_address,
                petitioner_id_type, petitioner_id_number, is_self_petition, relation_to_owner,
                document_owner_names, owner_dob,
                owner_birthplace_city, owner_birthplace_province, owner_birthplace_country,
                registry_number, father_full_name, mother_full_name,
                cfn_ground, cfn_ground_detail,
                notarized_at, receipt_number, payment_date,
                posting_start_date, posting_end_date, posting_location,
                order_date, publication_date_1, publication_date_2,
                publication_newspaper, publication_place, opposition_deadline,
                status_workflow,
                pdf_filename, pdf_filepath, pdf_hash,
                created_by
            ) VALUES (
                :petition_type, :petition_subtype, :petition_number,
                :date_of_filing, :special_law, :fee_amount,
                :document_type, :petition_of, :remarks,
                :petitioner_names, :petitioner_nationality, :petitioner_address,
                :petitioner_id_type, :petitioner_id_number, :is_self_petition, :relation_to_owner,
                :document_owner_names, :owner_dob,
                :owner_birthplace_city, :owner_birthplace_province, :owner_birthplace_country,
                :registry_number, :father_full_name, :mother_full_name,
                :cfn_ground, :cfn_ground_detail,
                :notarized_at, :receipt_number, :payment_date,
                :posting_start_date, :posting_end_date, :posting_location,
                :order_date, :publication_date_1, :publication_date_2,
                :publication_newspaper, :publication_place, :opposition_deadline,
                'Filed',
                :pdf_filename, :pdf_filepath, :pdf_hash,
                :created_by
            )";

    $stmt = $pdo_ra->prepare($sql);
    $stmt->execute(array_merge(
        [
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
            ':created_by'                => $_SESSION['user_id'] ?? null,
        ]
    ));

    $new_id = (int) $pdo_ra->lastInsertId();

    // Persist child rows
    $corrections     = $_POST['corrections']     ?? [];
    $supporting_docs = $_POST['supporting_docs'] ?? [];
    if (!is_array($corrections))     $corrections     = [];
    if (!is_array($supporting_docs)) $supporting_docs = [];
    ra9048_replace_child_rows($pdo_ra, $new_id, $corrections, $supporting_docs);

    log_activity(
        $pdo,
        'RA9048 Petition Created',
        "Petition {$payload['petition_number']} ({$payload['petition_subtype']}) for {$payload['document_owner_names']}",
        $_SESSION['user_id'] ?? null
    );

    $pdo_ra->commit();

    json_response(true, 'Petition record saved successfully.', ['id' => $new_id]);

} catch (PDOException $e) {
    if ($pdo_ra->inTransaction()) $pdo_ra->rollBack();
    error_log("RA9048 Petition save error: " . $e->getMessage());
    // Surface unique-constraint message clearly
    if ($e->getCode() === '23000') {
        json_response(false, 'A petition with that number already exists.', null, 409);
    }
    json_response(false, 'Database error. Please try again.', null, 500);
}
