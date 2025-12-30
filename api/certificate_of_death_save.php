<?php
/**
 * Certificate of Death - Save API
 * Handles form submission and saves data to database
 */

// Include configuration and functions
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

try {
    // Sanitize input data
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    $date_of_registration = sanitize_input($_POST['date_of_registration'] ?? '');

    // Deceased information
    $deceased_first_name = sanitize_input($_POST['deceased_first_name'] ?? '');
    $deceased_middle_name = sanitize_input($_POST['deceased_middle_name'] ?? null);
    $deceased_last_name = sanitize_input($_POST['deceased_last_name'] ?? '');
    $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
    $date_of_death = sanitize_input($_POST['date_of_death'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    $occupation = sanitize_input($_POST['occupation'] ?? null);

    // Place of death
    $place_of_death = sanitize_input($_POST['place_of_death'] ?? '');

    // Father's information
    $father_first_name = sanitize_input($_POST['father_first_name'] ?? null);
    $father_middle_name = sanitize_input($_POST['father_middle_name'] ?? null);
    $father_last_name = sanitize_input($_POST['father_last_name'] ?? null);

    // Mother's information
    $mother_first_name = sanitize_input($_POST['mother_first_name'] ?? null);
    $mother_middle_name = sanitize_input($_POST['mother_middle_name'] ?? null);
    $mother_last_name = sanitize_input($_POST['mother_last_name'] ?? null);

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

    // Validation
    $errors = [];

    // Validate registry number if provided
    if (!empty($registry_no)) {
        // Check if registry number already exists
        if (record_exists($pdo, 'certificate_of_death', 'registry_no', $registry_no)) {
            $errors[] = "Registry number already exists.";
        }
    }

    if (empty($date_of_registration)) {
        $errors[] = "Date of registration is required.";
    }

    if (empty($deceased_first_name)) {
        $errors[] = "Deceased's first name is required.";
    }

    if (empty($deceased_last_name)) {
        $errors[] = "Deceased's last name is required.";
    }

    if (empty($date_of_birth)) {
        $errors[] = "Date of birth is required.";
    }

    if (empty($date_of_death)) {
        $errors[] = "Date of death is required.";
    }

    if (empty($age)) {
        $errors[] = "Age is required.";
    }

    if (empty($place_of_death)) {
        $errors[] = "Place of death is required.";
    }

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

    // Upload PDF file
    $upload_result = upload_file($_FILES['pdf_file']);

    if (!$upload_result['success']) {
        json_response(false, implode(' ', $upload_result['errors']), null, 400);
    }

    $pdf_filename = $upload_result['filename'];
    $pdf_filepath = $upload_result['path'];

    // Convert date formats
    $date_of_registration = date('Y-m-d', strtotime($date_of_registration));
    $date_of_birth = date('Y-m-d', strtotime($date_of_birth));
    $date_of_death = date('Y-m-d', strtotime($date_of_death));

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert into database
        $sql = "INSERT INTO certificate_of_death (
                    registry_no,
                    date_of_registration,
                    deceased_first_name,
                    deceased_middle_name,
                    deceased_last_name,
                    date_of_birth,
                    date_of_death,
                    age,
                    occupation,
                    place_of_death,
                    father_first_name,
                    father_middle_name,
                    father_last_name,
                    mother_first_name,
                    mother_middle_name,
                    mother_last_name,
                    pdf_filename,
                    pdf_filepath,
                    created_at,
                    status
                ) VALUES (
                    :registry_no,
                    :date_of_registration,
                    :deceased_first_name,
                    :deceased_middle_name,
                    :deceased_last_name,
                    :date_of_birth,
                    :date_of_death,
                    :age,
                    :occupation,
                    :place_of_death,
                    :father_first_name,
                    :father_middle_name,
                    :father_last_name,
                    :mother_first_name,
                    :mother_middle_name,
                    :mother_last_name,
                    :pdf_filename,
                    :pdf_filepath,
                    NOW(),
                    'Active'
                )";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':registry_no' => $registry_no,
            ':date_of_registration' => $date_of_registration,
            ':deceased_first_name' => $deceased_first_name,
            ':deceased_middle_name' => $deceased_middle_name,
            ':deceased_last_name' => $deceased_last_name,
            ':date_of_birth' => $date_of_birth,
            ':date_of_death' => $date_of_death,
            ':age' => $age,
            ':occupation' => $occupation,
            ':place_of_death' => $place_of_death,
            ':father_first_name' => $father_first_name,
            ':father_middle_name' => $father_middle_name,
            ':father_last_name' => $father_last_name,
            ':mother_first_name' => $mother_first_name,
            ':mother_middle_name' => $mother_middle_name,
            ':mother_last_name' => $mother_last_name,
            ':pdf_filename' => $pdf_filename,
            ':pdf_filepath' => $pdf_filepath
        ]);

        $inserted_id = $pdo->lastInsertId();

        // Log activity
        log_activity(
            $pdo,
            'CREATE_CERTIFICATE',
            "Created Certificate of Death: Registry No. {$registry_no}",
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
            "Certificate of Death saved successfully! Registry No: {$registry_no}",
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

        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected Error: " . $e->getMessage());

    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
?>
