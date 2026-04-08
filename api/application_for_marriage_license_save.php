<?php
/**
 * Application for Marriage License - Save API
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
    $date_of_application = sanitize_input($_POST['date_of_application'] ?? '');

    // Groom's Information
    $groom_first_name = sanitize_input($_POST['groom_first_name'] ?? '');
    $groom_middle_name = sanitize_input($_POST['groom_middle_name'] ?? '');
    $groom_last_name = sanitize_input($_POST['groom_last_name'] ?? '');
    $groom_date_of_birth = sanitize_input($_POST['groom_date_of_birth'] ?? '');
    $groom_place_of_birth = sanitize_input($_POST['groom_place_of_birth'] ?? '');
    $groom_citizenship = sanitize_input($_POST['groom_citizenship'] ?? '');
    if ($groom_citizenship === 'Other') {
        $groom_citizenship = sanitize_input($_POST['groom_citizenship_other'] ?? '');
    }
    $groom_residence = sanitize_input($_POST['groom_residence'] ?? '');

    // Groom's Father Information
    $groom_father_first_name = sanitize_input($_POST['groom_father_first_name'] ?? '');
    $groom_father_middle_name = sanitize_input($_POST['groom_father_middle_name'] ?? '');
    $groom_father_last_name = sanitize_input($_POST['groom_father_last_name'] ?? '');
    $groom_father_citizenship = sanitize_input($_POST['groom_father_citizenship'] ?? '');
    if ($groom_father_citizenship === 'Other') {
        $groom_father_citizenship = sanitize_input($_POST['groom_father_citizenship_other'] ?? '');
    }
    $groom_father_residence = sanitize_input($_POST['groom_father_residence'] ?? '');

    // Groom's Mother Information
    $groom_mother_first_name = sanitize_input($_POST['groom_mother_first_name'] ?? '');
    $groom_mother_middle_name = sanitize_input($_POST['groom_mother_middle_name'] ?? '');
    $groom_mother_last_name = sanitize_input($_POST['groom_mother_last_name'] ?? '');
    $groom_mother_citizenship = sanitize_input($_POST['groom_mother_citizenship'] ?? '');
    if ($groom_mother_citizenship === 'Other') {
        $groom_mother_citizenship = sanitize_input($_POST['groom_mother_citizenship_other'] ?? '');
    }
    $groom_mother_residence = sanitize_input($_POST['groom_mother_residence'] ?? '');

    // Bride's Information
    $bride_first_name = sanitize_input($_POST['bride_first_name'] ?? '');
    $bride_middle_name = sanitize_input($_POST['bride_middle_name'] ?? '');
    $bride_last_name = sanitize_input($_POST['bride_last_name'] ?? '');
    $bride_date_of_birth = sanitize_input($_POST['bride_date_of_birth'] ?? '');
    $bride_place_of_birth = sanitize_input($_POST['bride_place_of_birth'] ?? '');
    $bride_citizenship = sanitize_input($_POST['bride_citizenship'] ?? '');
    if ($bride_citizenship === 'Other') {
        $bride_citizenship = sanitize_input($_POST['bride_citizenship_other'] ?? '');
    }
    $bride_residence = sanitize_input($_POST['bride_residence'] ?? '');

    // Bride's Father Information
    $bride_father_first_name = sanitize_input($_POST['bride_father_first_name'] ?? '');
    $bride_father_middle_name = sanitize_input($_POST['bride_father_middle_name'] ?? '');
    $bride_father_last_name = sanitize_input($_POST['bride_father_last_name'] ?? '');
    $bride_father_citizenship = sanitize_input($_POST['bride_father_citizenship'] ?? '');
    if ($bride_father_citizenship === 'Other') {
        $bride_father_citizenship = sanitize_input($_POST['bride_father_citizenship_other'] ?? '');
    }
    $bride_father_residence = sanitize_input($_POST['bride_father_residence'] ?? '');

    // Bride's Mother Information
    $bride_mother_first_name = sanitize_input($_POST['bride_mother_first_name'] ?? '');
    $bride_mother_middle_name = sanitize_input($_POST['bride_mother_middle_name'] ?? '');
    $bride_mother_last_name = sanitize_input($_POST['bride_mother_last_name'] ?? '');
    $bride_mother_citizenship = sanitize_input($_POST['bride_mother_citizenship'] ?? '');
    if ($bride_mother_citizenship === 'Other') {
        $bride_mother_citizenship = sanitize_input($_POST['bride_mother_citizenship_other'] ?? '');
    }
    $bride_mother_residence = sanitize_input($_POST['bride_mother_residence'] ?? '');

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

    // Validation: Required fields
    if (empty($date_of_application) ||
        empty($groom_first_name) || empty($groom_last_name) ||
        empty($groom_place_of_birth) ||
        empty($groom_citizenship) || empty($groom_residence) ||
        empty($bride_first_name) || empty($bride_last_name) ||
        empty($bride_place_of_birth) ||
        empty($bride_citizenship) || empty($bride_residence)) {
        json_response(false, 'Please fill in all required fields.', null, 400);
    }

    // Validate field lengths against database column limits
    $length_errors = validate_field_lengths([
        'Registry number'              => [$registry_no, 100],
        'Groom first name'             => [$groom_first_name, 100],
        'Groom middle name'            => [$groom_middle_name, 100],
        'Groom last name'              => [$groom_last_name, 100],
        'Groom place of birth'         => [$groom_place_of_birth, 255],
        'Groom citizenship'            => [$groom_citizenship, 100],
        'Groom father first name'      => [$groom_father_first_name, 100],
        'Groom father middle name'     => [$groom_father_middle_name, 100],
        'Groom father last name'       => [$groom_father_last_name, 100],
        'Groom father citizenship'     => [$groom_father_citizenship, 100],
        'Groom mother first name'      => [$groom_mother_first_name, 100],
        'Groom mother middle name'     => [$groom_mother_middle_name, 100],
        'Groom mother last name'       => [$groom_mother_last_name, 100],
        'Groom mother citizenship'     => [$groom_mother_citizenship, 100],
        'Bride first name'             => [$bride_first_name, 100],
        'Bride middle name'            => [$bride_middle_name, 100],
        'Bride last name'              => [$bride_last_name, 100],
        'Bride place of birth'         => [$bride_place_of_birth, 255],
        'Bride citizenship'            => [$bride_citizenship, 100],
        'Bride father first name'      => [$bride_father_first_name, 100],
        'Bride father middle name'     => [$bride_father_middle_name, 100],
        'Bride father last name'       => [$bride_father_last_name, 100],
        'Bride father citizenship'     => [$bride_father_citizenship, 100],
        'Bride mother first name'      => [$bride_mother_first_name, 100],
        'Bride mother middle name'     => [$bride_mother_middle_name, 100],
        'Bride mother last name'       => [$bride_mother_last_name, 100],
        'Bride mother citizenship'     => [$bride_mother_citizenship, 100],
    ]);
    if (!empty($length_errors)) {
        json_response(false, implode(' ', $length_errors), null, 400);
    }

    // Validate PDF file upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(false, 'PDF file is required.', null, 400);
    }

    // Convert date formats safely (returns null on invalid dates)
    $date_of_application = safe_date_convert($date_of_application);
    if ($date_of_application === null) {
        json_response(false, 'Invalid date of application.', null, 400);
    }
    $groom_date_of_birth = !empty($groom_date_of_birth) ? safe_date_convert($groom_date_of_birth) : null;
    $bride_date_of_birth = !empty($bride_date_of_birth) ? safe_date_convert($bride_date_of_birth) : null;

    // Upload PDF file into organized folder: marriage_license/{year}/
    $reg_year = date('Y', strtotime($date_of_application));
    $upload_result = upload_file($_FILES['pdf_file'], 'marriage_license', $reg_year);

    if (!$upload_result['success']) {
        json_response(false, implode(' ', $upload_result['errors']), null, 400);
    }

    $unique_filename = $upload_result['filename'];
    $upload_path = $upload_result['path'];
    $pdf_hash = $upload_result['hash'] ?? null;

    // Duplicate-PDF guard: reject if this exact file is already attached
    // to another record (any certificate type).
    if ($pdf_hash) {
        $dup = check_pdf_duplicate($pdo, $pdf_hash);
        if ($dup) {
            delete_file($unique_filename);
            json_response(
                false,
                "This PDF is already attached to {$dup['label']} Registry No. {$dup['registry_no']}. "
              . "Please verify you selected the correct file. If this is the same document, open the existing record instead.",
                null,
                409
            );
        }
    }

    // Insert into database
    $sql = "INSERT INTO application_for_marriage_license (
        registry_no, date_of_application,
        groom_first_name, groom_middle_name, groom_last_name,
        groom_date_of_birth, groom_place_of_birth, groom_citizenship, groom_residence,
        groom_father_first_name, groom_father_middle_name, groom_father_last_name,
        groom_father_citizenship, groom_father_residence,
        groom_mother_first_name, groom_mother_middle_name, groom_mother_last_name,
        groom_mother_citizenship, groom_mother_residence,
        bride_first_name, bride_middle_name, bride_last_name,
        bride_date_of_birth, bride_place_of_birth, bride_citizenship, bride_residence,
        bride_father_first_name, bride_father_middle_name, bride_father_last_name,
        bride_father_citizenship, bride_father_residence,
        bride_mother_first_name, bride_mother_middle_name, bride_mother_last_name,
        bride_mother_citizenship, bride_mother_residence,
        pdf_filename, pdf_filepath, pdf_hash,
        status, created_by
    ) VALUES (
        :registry_no, :date_of_application,
        :groom_first_name, :groom_middle_name, :groom_last_name,
        :groom_date_of_birth, :groom_place_of_birth, :groom_citizenship, :groom_residence,
        :groom_father_first_name, :groom_father_middle_name, :groom_father_last_name,
        :groom_father_citizenship, :groom_father_residence,
        :groom_mother_first_name, :groom_mother_middle_name, :groom_mother_last_name,
        :groom_mother_citizenship, :groom_mother_residence,
        :bride_first_name, :bride_middle_name, :bride_last_name,
        :bride_date_of_birth, :bride_place_of_birth, :bride_citizenship, :bride_residence,
        :bride_father_first_name, :bride_father_middle_name, :bride_father_last_name,
        :bride_father_citizenship, :bride_father_residence,
        :bride_mother_first_name, :bride_mother_middle_name, :bride_mother_last_name,
        :bride_mother_citizenship, :bride_mother_residence,
        :pdf_filename, :pdf_filepath, :pdf_hash,
        'Active', :created_by
    )";

    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);

    $created_by = $_SESSION['user_id'] ?? 1;

    $params = [
        ':registry_no' => $registry_no ?: null,
        ':date_of_application' => $date_of_application,
        ':groom_first_name' => $groom_first_name,
        ':groom_middle_name' => $groom_middle_name ?: null,
        ':groom_last_name' => $groom_last_name,
        ':groom_date_of_birth' => $groom_date_of_birth,
        ':groom_place_of_birth' => $groom_place_of_birth,
        ':groom_citizenship' => $groom_citizenship,
        ':groom_residence' => $groom_residence,
        ':groom_father_first_name' => $groom_father_first_name ?: null,
        ':groom_father_middle_name' => $groom_father_middle_name ?: null,
        ':groom_father_last_name' => $groom_father_last_name ?: null,
        ':groom_father_citizenship' => $groom_father_citizenship ?: null,
        ':groom_father_residence' => $groom_father_residence ?: null,
        ':groom_mother_first_name' => $groom_mother_first_name ?: null,
        ':groom_mother_middle_name' => $groom_mother_middle_name ?: null,
        ':groom_mother_last_name' => $groom_mother_last_name ?: null,
        ':groom_mother_citizenship' => $groom_mother_citizenship ?: null,
        ':groom_mother_residence' => $groom_mother_residence ?: null,
        ':bride_first_name' => $bride_first_name,
        ':bride_middle_name' => $bride_middle_name ?: null,
        ':bride_last_name' => $bride_last_name,
        ':bride_date_of_birth' => $bride_date_of_birth,
        ':bride_place_of_birth' => $bride_place_of_birth,
        ':bride_citizenship' => $bride_citizenship,
        ':bride_residence' => $bride_residence,
        ':bride_father_first_name' => $bride_father_first_name ?: null,
        ':bride_father_middle_name' => $bride_father_middle_name ?: null,
        ':bride_father_last_name' => $bride_father_last_name ?: null,
        ':bride_father_citizenship' => $bride_father_citizenship ?: null,
        ':bride_father_residence' => $bride_father_residence ?: null,
        ':bride_mother_first_name' => $bride_mother_first_name ?: null,
        ':bride_mother_middle_name' => $bride_mother_middle_name ?: null,
        ':bride_mother_last_name' => $bride_mother_last_name ?: null,
        ':bride_mother_citizenship' => $bride_mother_citizenship ?: null,
        ':bride_mother_residence' => $bride_mother_residence ?: null,
        ':pdf_filename' => $unique_filename,
        ':pdf_filepath' => $upload_path,
        ':pdf_hash'     => $pdf_hash,
        ':created_by'   => $created_by
    ];

    $stmt->execute($params);

    $new_id = $pdo->lastInsertId();
    $pdo->commit();

    $message = $add_new
        ? 'Marriage license application saved successfully! You can add another record.'
        : 'Marriage license application saved successfully!';

    json_response(true, $message, ['record_id' => $new_id], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Error: " . $e->getMessage());

    // Delete uploaded file if there was an error
    if (isset($unique_filename)) {
        delete_file($unique_filename);
    }

    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_registry_no') !== false) {
        json_response(false, 'Registry number already exists. Please use a unique registry number.', null, 409);
    } else {
        json_response(false, 'Database error occurred.', null, 500);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error: " . $e->getMessage());

    // Delete uploaded file if there was an error
    if (isset($unique_filename)) {
        delete_file($unique_filename);
    }

    json_response(false, 'An error occurred while processing your request.', null, 500);
}
