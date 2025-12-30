<?php
/**
 * Certificate of Live Birth - Update API
 * Handles updating existing records
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
    // Get record ID
    $record_id = sanitize_input($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Check if record exists
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        json_response(false, 'Record not found.', null, 404);
    }

    // Sanitize input data
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    $date_of_registration = sanitize_input($_POST['date_of_registration'] ?? '');

    // Child information
    $child_first_name = sanitize_input($_POST['child_first_name'] ?? '');
    $child_middle_name = sanitize_input($_POST['child_middle_name'] ?? null);
    $child_last_name = sanitize_input($_POST['child_last_name'] ?? '');
    $child_date_of_birth = sanitize_input($_POST['child_date_of_birth'] ?? '');
    $child_place_of_birth = sanitize_input($_POST['child_place_of_birth'] ?? '');

    $type_of_birth = sanitize_input($_POST['type_of_birth'] ?? '');
    $type_of_birth_other = sanitize_input($_POST['type_of_birth_other'] ?? null);
    $birth_order = sanitize_input($_POST['birth_order'] ?? null);
    $birth_order_other = sanitize_input($_POST['birth_order_other'] ?? null);

    // Mother's information
    $mother_first_name = sanitize_input($_POST['mother_first_name'] ?? '');
    $mother_middle_name = sanitize_input($_POST['mother_middle_name'] ?? null);
    $mother_last_name = sanitize_input($_POST['mother_last_name'] ?? '');

    // Father's information
    $father_first_name = sanitize_input($_POST['father_first_name'] ?? null);
    $father_middle_name = sanitize_input($_POST['father_middle_name'] ?? null);
    $father_last_name = sanitize_input($_POST['father_last_name'] ?? null);

    // Marriage information
    $date_of_marriage = sanitize_input($_POST['date_of_marriage'] ?? null);
    $place_of_marriage = sanitize_input($_POST['place_of_marriage'] ?? null);

    // Validation
    $errors = [];

    // Validate registry number if provided
    if (!empty($registry_no)) {
        // Check if registry number already exists (excluding current record)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificate_of_live_birth WHERE registry_no = :registry_no AND id != :id");
        $stmt->execute([':registry_no' => $registry_no, ':id' => $record_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Registry number already exists.";
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

    if (empty($child_date_of_birth)) {
        $errors[] = "Child's date of birth is required.";
    }

    if (empty($child_place_of_birth)) {
        $errors[] = "Child's place of birth (Barangay/Hospital) is required.";
    }

    // Handle PDF file upload (optional for update)
    $pdf_filename = $existing_record['pdf_filename'];
    $pdf_filepath = $existing_record['pdf_filepath'];
    $old_pdf_filename = null;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Validate new file
        $file_errors = validate_file_upload($_FILES['pdf_file']);
        if (!empty($file_errors)) {
            $errors = array_merge($errors, $file_errors);
        } else {
            // Upload new file
            $upload_result = upload_file($_FILES['pdf_file']);

            if (!$upload_result['success']) {
                $errors = array_merge($errors, $upload_result['errors']);
            } else {
                // Mark old file for deletion
                $old_pdf_filename = $existing_record['pdf_filename'];

                // Use new file
                $pdf_filename = $upload_result['filename'];
                $pdf_filepath = $upload_result['path'];
            }
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        // If new file was uploaded, delete it
        if ($old_pdf_filename && $pdf_filename !== $existing_record['pdf_filename']) {
            delete_file($pdf_filename);
        }
        json_response(false, implode(' ', $errors), null, 400);
    }

    // Convert date format to MySQL date format
    $date_of_registration = date('Y-m-d', strtotime($date_of_registration));

    // Convert child date of birth format
    if (!empty($child_date_of_birth)) {
        $child_date_of_birth = date('Y-m-d', strtotime($child_date_of_birth));
    } else {
        $child_date_of_birth = null;
    }

    // Convert date format if provided
    if (!empty($date_of_marriage)) {
        $date_of_marriage = date('Y-m-d', strtotime($date_of_marriage));
    } else {
        $date_of_marriage = null;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update database
        $sql = "UPDATE certificate_of_live_birth SET
                    registry_no = :registry_no,
                    date_of_registration = :date_of_registration,
                    child_first_name = :child_first_name,
                    child_middle_name = :child_middle_name,
                    child_last_name = :child_last_name,
                    child_date_of_birth = :child_date_of_birth,
                    child_place_of_birth = :child_place_of_birth,
                    type_of_birth = :type_of_birth,
                    type_of_birth_other = :type_of_birth_other,
                    birth_order = :birth_order,
                    birth_order_other = :birth_order_other,
                    mother_first_name = :mother_first_name,
                    mother_middle_name = :mother_middle_name,
                    mother_last_name = :mother_last_name,
                    father_first_name = :father_first_name,
                    father_middle_name = :father_middle_name,
                    father_last_name = :father_last_name,
                    date_of_marriage = :date_of_marriage,
                    place_of_marriage = :place_of_marriage,
                    pdf_filename = :pdf_filename,
                    pdf_filepath = :pdf_filepath,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':registry_no' => $registry_no,
            ':date_of_registration' => $date_of_registration,
            ':child_first_name' => $child_first_name,
            ':child_middle_name' => $child_middle_name,
            ':child_last_name' => $child_last_name,
            ':child_date_of_birth' => $child_date_of_birth,
            ':child_place_of_birth' => $child_place_of_birth,
            ':type_of_birth' => $type_of_birth,
            ':type_of_birth_other' => $type_of_birth_other,
            ':birth_order' => $birth_order,
            ':birth_order_other' => $birth_order_other,
            ':mother_first_name' => $mother_first_name,
            ':mother_middle_name' => $mother_middle_name,
            ':mother_last_name' => $mother_last_name,
            ':father_first_name' => $father_first_name,
            ':father_middle_name' => $father_middle_name,
            ':father_last_name' => $father_last_name,
            ':date_of_marriage' => $date_of_marriage,
            ':place_of_marriage' => $place_of_marriage,
            ':pdf_filename' => $pdf_filename,
            ':pdf_filepath' => $pdf_filepath,
            ':updated_by' => $_SESSION['user_id'] ?? null,
            ':id' => $record_id
        ]);

        // Log activity
        log_activity(
            $pdo,
            'UPDATE_CERTIFICATE',
            "Updated Certificate of Live Birth: Registry No. {$registry_no} (ID: {$record_id})",
            $_SESSION['user_id'] ?? null
        );

        // Commit transaction
        $pdo->commit();

        // Delete old PDF file if new one was uploaded
        if ($old_pdf_filename) {
            delete_file($old_pdf_filename);
        }

        // Prepare success response
        $response_data = [
            'id' => $record_id,
            'registry_no' => $registry_no
        ];

        json_response(
            true,
            "Certificate of Live Birth updated successfully! Registry No: {$registry_no}",
            $response_data,
            200
        );

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();

        // Delete new uploaded file if exists
        if ($old_pdf_filename && $pdf_filename !== $existing_record['pdf_filename']) {
            delete_file($pdf_filename);
        }

        // Log error
        error_log("Database Update Error: " . $e->getMessage());

        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }

} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected Error: " . $e->getMessage());

    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
?>
