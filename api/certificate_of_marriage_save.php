<?php
/**
 * Certificate of Marriage - Save API
 * Handles form submission and PDF upload
 */

require_once '../includes/session_config.php';
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Authentication & CSRF
requireAuth();
requireCSRFToken();

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    // Sanitize and validate input
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    $date_of_registration = sanitize_input($_POST['date_of_registration'] ?? '');

    // Husband's Information
    $husband_first_name = sanitize_input($_POST['husband_first_name'] ?? '');
    $husband_middle_name = sanitize_input($_POST['husband_middle_name'] ?? '');
    $husband_last_name = sanitize_input($_POST['husband_last_name'] ?? '');
    $husband_date_of_birth = sanitize_input($_POST['husband_date_of_birth'] ?? '');
    $husband_place_of_birth = sanitize_input($_POST['husband_place_of_birth'] ?? '');
    $husband_residence = sanitize_input($_POST['husband_residence'] ?? '');
    $husband_citizenship = sanitize_input($_POST['husband_citizenship'] ?? null);
    if ($husband_citizenship === 'Other') {
        $husband_citizenship = sanitize_input($_POST['husband_citizenship_other'] ?? null);
    }
    $husband_father_name = sanitize_input($_POST['husband_father_name'] ?? '');
    $husband_father_residence = sanitize_input($_POST['husband_father_residence'] ?? '');
    $husband_mother_name = sanitize_input($_POST['husband_mother_name'] ?? '');
    $husband_mother_residence = sanitize_input($_POST['husband_mother_residence'] ?? '');

    // Wife's Information
    $wife_first_name = sanitize_input($_POST['wife_first_name'] ?? '');
    $wife_middle_name = sanitize_input($_POST['wife_middle_name'] ?? '');
    $wife_last_name = sanitize_input($_POST['wife_last_name'] ?? '');
    $wife_date_of_birth = sanitize_input($_POST['wife_date_of_birth'] ?? '');
    $wife_place_of_birth = sanitize_input($_POST['wife_place_of_birth'] ?? '');
    $wife_residence = sanitize_input($_POST['wife_residence'] ?? '');
    $wife_citizenship = sanitize_input($_POST['wife_citizenship'] ?? null);
    if ($wife_citizenship === 'Other') {
        $wife_citizenship = sanitize_input($_POST['wife_citizenship_other'] ?? null);
    }
    $wife_father_name = sanitize_input($_POST['wife_father_name'] ?? '');
    $wife_father_residence = sanitize_input($_POST['wife_father_residence'] ?? '');
    $wife_mother_name = sanitize_input($_POST['wife_mother_name'] ?? '');
    $wife_mother_residence = sanitize_input($_POST['wife_mother_residence'] ?? '');

    // Marriage Information
    $date_of_marriage = sanitize_input($_POST['date_of_marriage'] ?? '');
    $place_of_marriage = sanitize_input($_POST['place_of_marriage'] ?? '');
    $nature_of_solemnization = sanitize_input($_POST['nature_of_solemnization'] ?? '');

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

    // Validation: Required fields
    if (empty($date_of_registration) || empty($husband_first_name) || empty($husband_last_name) ||
        empty($husband_date_of_birth) || empty($husband_place_of_birth) || empty($husband_residence) ||
        empty($wife_first_name) || empty($wife_last_name) ||
        empty($wife_date_of_birth) || empty($wife_place_of_birth) || empty($wife_residence) ||
        empty($date_of_marriage) || empty($place_of_marriage) || empty($nature_of_solemnization)) {
        json_response(false, 'Please fill in all required fields.', null, 400);
    }

    // Validate field lengths against database column limits
    $length_errors = validate_field_lengths([
        'Registry number'          => [$registry_no, 100],
        'Husband first name'       => [$husband_first_name, 100],
        'Husband middle name'      => [$husband_middle_name, 100],
        'Husband last name'        => [$husband_last_name, 100],
        'Husband place of birth'   => [$husband_place_of_birth, 255],
        'Husband citizenship'      => [$husband_citizenship, 100],
        'Husband father name'      => [$husband_father_name, 255],
        'Husband mother name'      => [$husband_mother_name, 255],
        'Wife first name'          => [$wife_first_name, 100],
        'Wife middle name'         => [$wife_middle_name, 100],
        'Wife last name'           => [$wife_last_name, 100],
        'Wife place of birth'      => [$wife_place_of_birth, 255],
        'Wife citizenship'         => [$wife_citizenship, 100],
        'Wife father name'         => [$wife_father_name, 255],
        'Wife mother name'         => [$wife_mother_name, 255],
        'Place of marriage'        => [$place_of_marriage, 255],
    ]);
    if (!empty($length_errors)) {
        json_response(false, implode(' ', $length_errors), null, 400);
    }

    // Validate PDF file upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(false, 'PDF file is required.', null, 400);
    }

    // Convert date formats safely (returns null on invalid dates)
    $date_of_registration = safe_date_convert($date_of_registration);
    if ($date_of_registration === null) {
        json_response(false, 'Invalid date of registration.', null, 400);
    }
    $husband_date_of_birth = safe_date_convert($husband_date_of_birth);
    $wife_date_of_birth = safe_date_convert($wife_date_of_birth);
    $date_of_marriage = safe_date_convert($date_of_marriage);

    // Upload PDF file into organized folder: marriage/{year}/
    $reg_year = date('Y', strtotime($date_of_registration));
    $upload_result = upload_file($_FILES['pdf_file'], 'marriage', $reg_year);

    if (!$upload_result['success']) {
        json_response(false, implode(' ', $upload_result['errors']), null, 400);
    }

    $unique_filename = $upload_result['filename'];
    $upload_path = $upload_result['path'];
    $pdf_hash = $upload_result['hash'] ?? null;

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert into database
        $sql = "INSERT INTO certificate_of_marriage (
            registry_no, date_of_registration,
            husband_first_name, husband_middle_name, husband_last_name,
            husband_date_of_birth, husband_place_of_birth, husband_residence, husband_citizenship,
            husband_father_name, husband_father_residence,
            husband_mother_name, husband_mother_residence,
            wife_first_name, wife_middle_name, wife_last_name,
            wife_date_of_birth, wife_place_of_birth, wife_residence, wife_citizenship,
            wife_father_name, wife_father_residence,
            wife_mother_name, wife_mother_residence,
            date_of_marriage, place_of_marriage, nature_of_solemnization,
            pdf_filename, pdf_filepath, pdf_hash,
            status, created_by
        ) VALUES (
            :registry_no, :date_of_registration,
            :husband_first_name, :husband_middle_name, :husband_last_name,
            :husband_date_of_birth, :husband_place_of_birth, :husband_residence, :husband_citizenship,
            :husband_father_name, :husband_father_residence,
            :husband_mother_name, :husband_mother_residence,
            :wife_first_name, :wife_middle_name, :wife_last_name,
            :wife_date_of_birth, :wife_place_of_birth, :wife_residence, :wife_citizenship,
            :wife_father_name, :wife_father_residence,
            :wife_mother_name, :wife_mother_residence,
            :date_of_marriage, :place_of_marriage, :nature_of_solemnization,
            :pdf_filename, :pdf_filepath, :pdf_hash,
            'Active', :created_by
        )";

        $stmt = $pdo->prepare($sql);

        $created_by = $_SESSION['user_id'] ?? 1;

        $params = [
            ':registry_no' => $registry_no ?: null,
            ':date_of_registration' => $date_of_registration,
            ':husband_first_name' => $husband_first_name,
            ':husband_middle_name' => $husband_middle_name ?: null,
            ':husband_last_name' => $husband_last_name,
            ':husband_date_of_birth' => $husband_date_of_birth,
            ':husband_place_of_birth' => $husband_place_of_birth,
            ':husband_residence' => $husband_residence,
            ':husband_citizenship' => $husband_citizenship ?: null,
            ':husband_father_name' => $husband_father_name ?: null,
            ':husband_father_residence' => $husband_father_residence ?: null,
            ':husband_mother_name' => $husband_mother_name ?: null,
            ':husband_mother_residence' => $husband_mother_residence ?: null,
            ':wife_first_name' => $wife_first_name,
            ':wife_middle_name' => $wife_middle_name ?: null,
            ':wife_last_name' => $wife_last_name,
            ':wife_date_of_birth' => $wife_date_of_birth,
            ':wife_place_of_birth' => $wife_place_of_birth,
            ':wife_residence' => $wife_residence,
            ':wife_citizenship' => $wife_citizenship ?: null,
            ':wife_father_name' => $wife_father_name ?: null,
            ':wife_father_residence' => $wife_father_residence ?: null,
            ':wife_mother_name' => $wife_mother_name ?: null,
            ':wife_mother_residence' => $wife_mother_residence ?: null,
            ':date_of_marriage' => $date_of_marriage,
            ':place_of_marriage' => $place_of_marriage,
            ':nature_of_solemnization' => $nature_of_solemnization,
            ':pdf_filename' => $unique_filename,
            ':pdf_filepath' => $upload_path,
            ':pdf_hash'     => $pdf_hash,
            ':created_by'   => $created_by
        ];

        $stmt->execute($params);

        $inserted_id = $pdo->lastInsertId();

        // Commit transaction
        $pdo->commit();

        $message = $add_new
            ? 'Marriage certificate saved successfully! You can add another record.'
            : 'Marriage certificate saved successfully!';

        json_response(true, $message, ['id' => $inserted_id], 201);

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();

        // Delete uploaded file
        delete_file($unique_filename);

        error_log("Database Insert Error: " . $e->getMessage());

        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_registry_no') !== false) {
            json_response(false, 'Registry number already exists. Please use a unique registry number.', null, 409);
        } else {
            json_response(false, 'Database error occurred. Please try again.', null, 500);
        }
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());

    // Delete uploaded file if there was an error
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }

    json_response(false, 'An error occurred while processing your request.', null, 500);
}
