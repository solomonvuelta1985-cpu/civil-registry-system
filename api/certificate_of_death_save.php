<?php
/**
 * Certificate of Death - Save API
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
    $date_of_registration_format = sanitize_input($_POST['date_of_registration_format'] ?? 'full');
    $date_of_registration        = sanitize_input($_POST['date_of_registration'] ?? '');
    $partial_date_month          = sanitize_input($_POST['partial_date_month'] ?? null) ?: null;
    $partial_date_year           = sanitize_input($_POST['partial_date_year'] ?? null) ?: null;
    $partial_date_day            = sanitize_input($_POST['partial_date_day'] ?? null) ?: null;

    // Deceased information
    $deceased_first_name = sanitize_input($_POST['deceased_first_name'] ?? '');
    $deceased_middle_name = sanitize_input($_POST['deceased_middle_name'] ?? null);
    $deceased_last_name = sanitize_input($_POST['deceased_last_name'] ?? '');
    $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
    $date_of_death = sanitize_input($_POST['date_of_death'] ?? '');
    $age = sanitize_input($_POST['age'] ?? '');
    $sex = sanitize_input($_POST['sex'] ?? '');
    $occupation = sanitize_input($_POST['occupation'] ?? null);

    // Place of death
    $place_of_death = sanitize_input($_POST['place_of_death'] ?? '');

    // Father's information
    $father_first_name = sanitize_input($_POST['father_first_name'] ?? null);
    $father_middle_name = sanitize_input($_POST['father_middle_name'] ?? null);
    $father_last_name = sanitize_input($_POST['father_last_name'] ?? null);
    $father_citizenship = sanitize_input($_POST['father_citizenship'] ?? null);
    if ($father_citizenship === 'Other') {
        $father_citizenship = sanitize_input($_POST['father_citizenship_other'] ?? null);
    }

    // Mother's information
    $mother_first_name = sanitize_input($_POST['mother_first_name'] ?? null);
    $mother_middle_name = sanitize_input($_POST['mother_middle_name'] ?? null);
    $mother_last_name = sanitize_input($_POST['mother_last_name'] ?? null);
    $mother_citizenship = sanitize_input($_POST['mother_citizenship'] ?? null);
    if ($mother_citizenship === 'Other') {
        $mother_citizenship = sanitize_input($_POST['mother_citizenship_other'] ?? null);
    }

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

    // Validation
    $errors = [];

    $allowed_formats = ['full', 'month_only', 'year_only', 'month_year', 'month_day', 'na'];
    if (!in_array($date_of_registration_format, $allowed_formats, true)) {
        $errors[] = "Invalid date format type.";
    }
    if ($date_of_registration_format === 'full' && empty($date_of_registration)) {
        $errors[] = "Date of registration is required.";
    }

    if (empty($deceased_first_name)) {
        $errors[] = "Deceased's first name is required.";
    }

    if (empty($deceased_last_name)) {
        $errors[] = "Deceased's last name is required.";
    }

    if (empty($date_of_death)) {
        $errors[] = "Date of death is required.";
    }

    if (empty($age)) {
        $errors[] = "Age is required.";
    }

    if (empty($sex)) {
        $errors[] = "Sex is required.";
    } elseif (!in_array($sex, ['Male', 'Female'], true)) {
        $errors[] = "Sex must be either Male or Female.";
    }

    if (empty($place_of_death)) {
        $errors[] = "Place of death is required.";
    }

    // Validate field lengths against database column limits
    $length_errors = validate_field_lengths([
        'Registry number'       => [$registry_no, 100],
        'Deceased first name'   => [$deceased_first_name, 100],
        'Deceased middle name'  => [$deceased_middle_name, 100],
        'Deceased last name'    => [$deceased_last_name, 100],
        'Occupation'            => [$occupation, 100],
        'Place of death'        => [$place_of_death, 255],
        'Father first name'     => [$father_first_name, 100],
        'Father middle name'    => [$father_middle_name, 100],
        'Father last name'      => [$father_last_name, 100],
        'Mother first name'     => [$mother_first_name, 100],
        'Mother middle name'    => [$mother_middle_name, 100],
        'Mother last name'      => [$mother_last_name, 100],
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

    // Normalize partial or full registration date
    $norm = normalize_registration_date(
        $date_of_registration_format,
        $date_of_registration,
        $partial_date_month,
        $partial_date_year,
        $partial_date_day
    );
    if ($norm['error'] !== null) {
        json_response(false, $norm['error'], null, 400);
    }
    $date_of_registration        = $norm['date'];
    $stored_partial_month        = in_array($date_of_registration_format, ['month_only', 'month_year', 'month_day'])
        ? ((int)$partial_date_month ?: null) : null;
    $stored_partial_year         = in_array($date_of_registration_format, ['year_only', 'month_year'])
        ? ((int)$partial_date_year ?: null) : null;
    $stored_partial_day          = ($date_of_registration_format === 'month_day')
        ? ((int)$partial_date_day ?: null) : null;

    // Upload PDF file into organized folder: death/{year}/
    $reg_year = !empty($date_of_registration) ? date('Y', strtotime($date_of_registration)) : date('Y');
    $upload_result = upload_file($_FILES['pdf_file'], 'death', $reg_year);

    if (!$upload_result['success']) {
        json_response(false, implode(' ', $upload_result['errors']), null, 400);
    }

    $pdf_filename = $upload_result['filename'];
    $pdf_filepath = $upload_result['path'];
    $pdf_hash     = $upload_result['hash'] ?? null;

    // Duplicate-PDF guard: reject if this exact file is already attached
    // to another record (any certificate type).
    if ($pdf_hash) {
        $dup = check_pdf_duplicate($pdo, $pdf_hash);
        if ($dup) {
            delete_file($pdf_filename);
            json_response(
                false,
                "This PDF is already attached to {$dup['label']} Registry No. {$dup['registry_no']}. "
              . "Please verify you selected the correct file. If this is the same document, open the existing record instead.",
                null,
                409
            );
        }
    }

    if (!empty($date_of_birth)) {
        $date_of_birth = safe_date_convert($date_of_birth);
    } else {
        $date_of_birth = null;
    }
    $date_of_death = safe_date_convert($date_of_death);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert into database
        $sql = "INSERT INTO certificate_of_death (
                    registry_no,
                    date_of_registration,
                    date_of_registration_format,
                    date_of_registration_partial_month,
                    date_of_registration_partial_year,
                    date_of_registration_partial_day,
                    deceased_first_name,
                    deceased_middle_name,
                    deceased_last_name,
                    sex,
                    date_of_birth,
                    date_of_death,
                    age,
                    occupation,
                    place_of_death,
                    father_first_name,
                    father_middle_name,
                    father_last_name,
                    father_citizenship,
                    mother_first_name,
                    mother_middle_name,
                    mother_last_name,
                    mother_citizenship,
                    pdf_filename,
                    pdf_filepath,
                    pdf_hash,
                    created_at,
                    status,
                    created_by
                ) VALUES (
                    :registry_no,
                    :date_of_registration,
                    :date_of_registration_format,
                    :date_of_registration_partial_month,
                    :date_of_registration_partial_year,
                    :date_of_registration_partial_day,
                    :deceased_first_name,
                    :deceased_middle_name,
                    :deceased_last_name,
                    :sex,
                    :date_of_birth,
                    :date_of_death,
                    :age,
                    :occupation,
                    :place_of_death,
                    :father_first_name,
                    :father_middle_name,
                    :father_last_name,
                    :father_citizenship,
                    :mother_first_name,
                    :mother_middle_name,
                    :mother_last_name,
                    :mother_citizenship,
                    :pdf_filename,
                    :pdf_filepath,
                    :pdf_hash,
                    NOW(),
                    'Active',
                    :created_by
                )";

        $stmt = $pdo->prepare($sql);

        $created_by = $_SESSION['user_id'] ?? null;

        $stmt->execute([
            ':registry_no'                         => $registry_no,
            ':date_of_registration'                => $date_of_registration,
            ':date_of_registration_format'         => $date_of_registration_format,
            ':date_of_registration_partial_month'  => $stored_partial_month,
            ':date_of_registration_partial_year'   => $stored_partial_year,
            ':date_of_registration_partial_day'    => $stored_partial_day,
            ':deceased_first_name' => $deceased_first_name,
            ':deceased_middle_name' => $deceased_middle_name,
            ':deceased_last_name' => $deceased_last_name,
            ':sex' => $sex,
            ':date_of_birth' => $date_of_birth,
            ':date_of_death' => $date_of_death,
            ':age' => $age,
            ':occupation' => $occupation,
            ':place_of_death' => $place_of_death,
            ':father_first_name' => $father_first_name,
            ':father_middle_name' => $father_middle_name,
            ':father_last_name' => $father_last_name,
            ':father_citizenship' => $father_citizenship ?: null,
            ':mother_first_name' => $mother_first_name,
            ':mother_middle_name' => $mother_middle_name,
            ':mother_last_name' => $mother_last_name,
            ':mother_citizenship' => $mother_citizenship ?: null,
            ':pdf_filename' => $pdf_filename,
            ':pdf_filepath' => $pdf_filepath,
            ':pdf_hash'     => $pdf_hash,
            ':created_by'   => $created_by
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

        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_registry_no') !== false) {
            json_response(false, 'Registry number already exists. Please use a unique registry number.', null, 409);
        } else {
            // DEBUG: expose real DB error (remove after debugging on production)
            json_response(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected Error: " . $e->getMessage());

    // DEBUG: expose real error (remove after debugging on production)
    json_response(false, 'Unexpected error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), null, 500);
}
?>
