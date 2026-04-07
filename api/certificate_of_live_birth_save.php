<?php
/**
 * Certificate of Live Birth - Save API
 * Handles form submission and saves data to database
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Set JSON response header
header('Content-Type: application/json');

// Authentication & CSRF
requireAuth();
requireCSRFToken();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    // Sanitize input data
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    // Convert empty registry_no to NULL to avoid unique constraint issues
    if (empty($registry_no)) {
        $registry_no = null;
    }
    $date_of_registration = sanitize_input($_POST['date_of_registration'] ?? '');

    // Child information
    $child_first_name = sanitize_input($_POST['child_first_name'] ?? '');
    $child_middle_name = sanitize_input($_POST['child_middle_name'] ?? null);
    $child_last_name = sanitize_input($_POST['child_last_name'] ?? '');
    $child_date_of_birth = sanitize_input($_POST['child_date_of_birth'] ?? '');
    $time_of_birth = sanitize_input($_POST['time_of_birth'] ?? null);
    $place_type = sanitize_input($_POST['place_type'] ?? '');
    $child_place_of_birth = sanitize_input($_POST['child_place_of_birth'] ?? '');
    $barangay = sanitize_input($_POST['barangay'] ?? '');

    $child_sex = sanitize_input($_POST['child_sex'] ?? '');
    $legitimacy_status = sanitize_input($_POST['legitimacy_status'] ?? '');

    $type_of_birth = sanitize_input($_POST['type_of_birth'] ?? '');
    $type_of_birth_other = sanitize_input($_POST['type_of_birth_other'] ?? null);
    $birth_order = sanitize_input($_POST['birth_order'] ?? null);
    $birth_order_other = sanitize_input($_POST['birth_order_other'] ?? null);

    // Mother's information
    $mother_first_name = sanitize_input($_POST['mother_first_name'] ?? '');
    $mother_middle_name = sanitize_input($_POST['mother_middle_name'] ?? null);
    $mother_last_name = sanitize_input($_POST['mother_last_name'] ?? '');
    $mother_citizenship = sanitize_input($_POST['mother_citizenship'] ?? null);
    if ($mother_citizenship === 'Other') {
        $mother_citizenship = sanitize_input($_POST['mother_citizenship_other'] ?? null);
    }

    // Father's information
    $father_first_name = sanitize_input($_POST['father_first_name'] ?? null);
    $father_middle_name = sanitize_input($_POST['father_middle_name'] ?? null);
    $father_last_name = sanitize_input($_POST['father_last_name'] ?? null);
    $father_citizenship = sanitize_input($_POST['father_citizenship'] ?? null);
    if ($father_citizenship === 'Other') {
        $father_citizenship = sanitize_input($_POST['father_citizenship_other'] ?? null);
    }

    // Marriage information
    $date_of_marriage = sanitize_input($_POST['date_of_marriage'] ?? null);
    $place_of_marriage = sanitize_input($_POST['place_of_marriage'] ?? null);

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

    // Validation
    $errors = [];

    // Validate registry number format if provided (optional but must match format if provided)
    if (!empty($registry_no) && $registry_no !== null) {
        // Validate format: XXXX-XXXX or XX-XXXXXX
        if (!preg_match('/^\d{2,4}-\d{4,6}$/', $registry_no)) {
            $errors[] = "Registry number must be in format XXXX-XXXX or XX-XXXXXX (e.g., 2014-1423 or 99-123456).";
            error_log("Registry number format validation failed: " . $registry_no);
        }
    }

    if (empty($date_of_registration)) {
        $errors[] = "Date of registration is required.";
    }

    if (empty($type_of_birth)) {
        $errors[] = "Type of birth is required.";
    } elseif ($type_of_birth === 'Other' && empty($type_of_birth_other)) {
        $errors[] = "Please specify other type of birth.";
    }

    if (empty($mother_first_name)) {
        $errors[] = "Mother's first name is required.";
    }

    if (empty($mother_last_name)) {
        $errors[] = "Mother's last name is required.";
    }

    // Validate child information
    if (empty($child_first_name)) {
        $errors[] = "Child's first name is required.";
    }

    if (empty($child_last_name)) {
        $errors[] = "Child's last name is required.";
    }

    if (empty($place_type)) {
        $errors[] = "Place type is required.";
    }

    // child_place_of_birth is required only for Hospital/Clinic and Barangay Health Center
    if (in_array($place_type, ['Hospital/Clinic', 'Barangay Health Center']) && empty($child_place_of_birth)) {
        $errors[] = "Child's place of birth (location) is required for the selected place type.";
    }

    if (empty($barangay)) {
        $errors[] = "Barangay is required.";
    }

    if (empty($child_sex)) {
        $errors[] = "Child's sex is required.";
    }

    if (empty($legitimacy_status)) {
        $errors[] = "Legitimacy status is required.";
    }

    // Validate field lengths against database column limits
    $length_errors = validate_field_lengths([
        'Registry number'      => [$registry_no, 100],
        'Place type'           => [$place_type, 100],
        'Child first name'     => [$child_first_name, 100],
        'Child middle name'    => [$child_middle_name, 100],
        'Child last name'      => [$child_last_name, 100],
        'Place of birth'       => [$child_place_of_birth, 255],
        'Barangay'             => [$barangay, 255],
        'Mother first name'    => [$mother_first_name, 100],
        'Mother middle name'   => [$mother_middle_name, 100],
        'Mother last name'     => [$mother_last_name, 100],
        'Mother citizenship'   => [$mother_citizenship, 100],
        'Father first name'    => [$father_first_name, 100],
        'Father middle name'   => [$father_middle_name, 100],
        'Father last name'     => [$father_last_name, 100],
        'Father citizenship'   => [$father_citizenship, 100],
        'Place of marriage'    => [$place_of_marriage, 255],
    ]);
    $errors = array_merge($errors, $length_errors);

    // Validate PDF file upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "PDF certificate is required.";
    } else {
        $file_errors = validate_file_upload($_FILES['pdf_file']);
        if (!empty($file_errors)) {
            $errors = array_merge($errors, $file_errors);
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        json_response(false, implode(' ', $errors), null, 400);
    }

    // Convert date format to MySQL date format (safe — returns null on invalid)
    $date_of_registration = safe_date_convert($date_of_registration);
    if ($date_of_registration === null) {
        json_response(false, 'Invalid date of registration.', null, 400);
    }

    // Upload PDF file into organized folder: birth/{year}/
    $reg_year = date('Y', strtotime($date_of_registration));
    $upload_result = upload_file($_FILES['pdf_file'], 'birth', $reg_year);

    if (!$upload_result['success']) {
        json_response(false, implode(' ', $upload_result['errors']), null, 400);
    }

    $pdf_filename = $upload_result['filename'];
    $pdf_filepath = $upload_result['path'];
    $pdf_hash     = $upload_result['hash'] ?? null;

    // Convert child date of birth format (safe) - optional
    if (!empty($child_date_of_birth)) {
        $child_date_of_birth = safe_date_convert($child_date_of_birth);
    } else {
        $child_date_of_birth = null;
    }

    // Convert marriage date if provided (safe)
    $date_of_marriage = safe_date_convert($date_of_marriage);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert into database
        $sql = "INSERT INTO certificate_of_live_birth (
                    registry_no,
                    date_of_registration,
                    child_first_name,
                    child_middle_name,
                    child_last_name,
                    child_date_of_birth,
                    time_of_birth,
                    place_type,
                    child_place_of_birth,
                    barangay,
                    child_sex,
                    legitimacy_status,
                    type_of_birth,
                    type_of_birth_other,
                    birth_order,
                    birth_order_other,
                    mother_first_name,
                    mother_middle_name,
                    mother_last_name,
                    mother_citizenship,
                    father_first_name,
                    father_middle_name,
                    father_last_name,
                    father_citizenship,
                    date_of_marriage,
                    place_of_marriage,
                    pdf_filename,
                    pdf_filepath,
                    pdf_hash,
                    created_at,
                    status
                ) VALUES (
                    :registry_no,
                    :date_of_registration,
                    :child_first_name,
                    :child_middle_name,
                    :child_last_name,
                    :child_date_of_birth,
                    :time_of_birth,
                    :place_type,
                    :child_place_of_birth,
                    :barangay,
                    :child_sex,
                    :legitimacy_status,
                    :type_of_birth,
                    :type_of_birth_other,
                    :birth_order,
                    :birth_order_other,
                    :mother_first_name,
                    :mother_middle_name,
                    :mother_last_name,
                    :mother_citizenship,
                    :father_first_name,
                    :father_middle_name,
                    :father_last_name,
                    :father_citizenship,
                    :date_of_marriage,
                    :place_of_marriage,
                    :pdf_filename,
                    :pdf_filepath,
                    :pdf_hash,
                    NOW(),
                    'Active'
                )";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':registry_no' => $registry_no,
            ':date_of_registration' => $date_of_registration,
            ':child_first_name' => $child_first_name,
            ':child_middle_name' => $child_middle_name,
            ':child_last_name' => $child_last_name,
            ':child_date_of_birth' => $child_date_of_birth,
            ':time_of_birth' => !empty($time_of_birth) ? $time_of_birth : null,
            ':place_type' => $place_type,
            ':child_place_of_birth' => !empty($child_place_of_birth) ? $child_place_of_birth : null,
            ':barangay' => $barangay,
            ':child_sex' => $child_sex,
            ':legitimacy_status' => $legitimacy_status,
            ':type_of_birth' => $type_of_birth,
            ':type_of_birth_other' => $type_of_birth_other,
            ':birth_order' => $birth_order,
            ':birth_order_other' => $birth_order_other,
            ':mother_first_name' => $mother_first_name,
            ':mother_middle_name' => $mother_middle_name,
            ':mother_last_name' => $mother_last_name,
            ':mother_citizenship' => $mother_citizenship,
            ':father_first_name' => $father_first_name,
            ':father_middle_name' => $father_middle_name,
            ':father_last_name' => $father_last_name,
            ':father_citizenship' => $father_citizenship,
            ':date_of_marriage' => $date_of_marriage,
            ':place_of_marriage' => $place_of_marriage,
            ':pdf_filename' => $pdf_filename,
            ':pdf_filepath' => $pdf_filepath,
            ':pdf_hash'     => $pdf_hash
        ]);

        $inserted_id = $pdo->lastInsertId();

        // Log activity
        log_activity(
            $pdo,
            'CREATE_CERTIFICATE',
            "Created Certificate of Live Birth: Registry No. {$registry_no}",
            $_SESSION['user_id'] ?? null
        );

        // Commit transaction
        $pdo->commit();

        // Prepare success response
        $response_data = [
            'id' => $inserted_id,
            'registry_no' => $registry_no
        ];

        json_response(
            true,
            "Certificate of Live Birth saved successfully! Registry No: {$registry_no}",
            $response_data,
            201
        );

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();

        // Delete uploaded file
        delete_file($pdf_filename);

        // Log error
        error_log("Database Insert Error: " . $e->getMessage());

        // Friendly message for duplicate registry number
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_registry_no') !== false) {
            json_response(false, 'Registry number already exists. Please use a unique registry number.', null, 409);
        } else {
            json_response(false, 'Database error occurred. Please try again.', null, 500);
        }
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected Error: " . $e->getMessage());

    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
?>
