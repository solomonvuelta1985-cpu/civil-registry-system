<?php
/**
 * Certificate of Death - Update API
 * Handles form submission for updating existing records
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
    // Get record ID
    $record_id = sanitize_input($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Check if record exists
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_death WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        json_response(false, 'Record not found.', null, 404);
    }

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
    $place_of_death = sanitize_input($_POST['place_of_death'] ?? '');

    // Parents information
    $father_first_name = sanitize_input($_POST['father_first_name'] ?? null);
    $father_middle_name = sanitize_input($_POST['father_middle_name'] ?? null);
    $father_last_name = sanitize_input($_POST['father_last_name'] ?? null);
    $father_citizenship = sanitize_input($_POST['father_citizenship'] ?? null);
    if ($father_citizenship === 'Other') {
        $father_citizenship = sanitize_input($_POST['father_citizenship_other'] ?? null);
    }
    $mother_first_name = sanitize_input($_POST['mother_first_name'] ?? null);
    $mother_middle_name = sanitize_input($_POST['mother_middle_name'] ?? null);
    $mother_last_name = sanitize_input($_POST['mother_last_name'] ?? null);
    $mother_citizenship = sanitize_input($_POST['mother_citizenship'] ?? null);
    if ($mother_citizenship === 'Other') {
        $mother_citizenship = sanitize_input($_POST['mother_citizenship_other'] ?? null);
    }

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

    // Handle PDF file upload (optional for update)
    $pdf_filename     = $existing_record['pdf_filename'];
    $pdf_filepath     = $existing_record['pdf_filepath'];
    $pdf_hash         = $existing_record['pdf_hash'] ?? null;
    $old_pdf_filename = null;

    // Normalize partial or full registration date
    $norm = normalize_registration_date(
        $date_of_registration_format,
        $date_of_registration,
        $partial_date_month,
        $partial_date_year,
        $partial_date_day
    );
    if ($norm['error'] !== null) {
        $errors[] = $norm['error'];
    }
    $date_of_registration        = $norm['date'];
    $stored_partial_month        = in_array($date_of_registration_format, ['month_only', 'month_year', 'month_day'])
        ? ((int)$partial_date_month ?: null) : null;
    $stored_partial_year         = in_array($date_of_registration_format, ['year_only', 'month_year'])
        ? ((int)$partial_date_year ?: null) : null;
    $stored_partial_day          = ($date_of_registration_format === 'month_day')
        ? ((int)$partial_date_day ?: null) : null;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file_errors = validate_file_upload($_FILES['pdf_file']);
        if (!empty($file_errors)) {
            $errors = array_merge($errors, $file_errors);
        } else {
            // Upload new file into organized folder: death/{year}/
            $reg_year = !empty($date_of_registration) ? date('Y', strtotime($date_of_registration)) : date('Y');
            $upload_result = upload_file($_FILES['pdf_file'], 'death', $reg_year);

            if (!$upload_result['success']) {
                json_response(false, implode(' ', $upload_result['errors']), null, 400);
            }

            // Mark old file for backup (done after commit)
            $old_pdf_filename = $existing_record['pdf_filename'];

            $pdf_filename = $upload_result['filename'];
            $pdf_filepath = $upload_result['path'];
            $pdf_hash     = $upload_result['hash'] ?? null;

            // Duplicate-PDF guard: reject if this exact file is already
            // attached to another record. Exclude the current record from
            // the check so re-uploading the same file to itself is a no-op.
            if ($pdf_hash) {
                $dup = check_pdf_duplicate($pdo, $pdf_hash, 'death', (int)$record_id);
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
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        json_response(false, implode(' ', $errors), null, 400);
    }
    if (!empty($date_of_birth)) {
        $date_of_birth = safe_date_convert($date_of_birth);
        if ($date_of_birth === null) {
            json_response(false, 'Invalid date of birth.', null, 400);
        }
    } else {
        $date_of_birth = null;
    }
    $date_of_death = safe_date_convert($date_of_death);
    if ($date_of_death === null) {
        json_response(false, 'Invalid date of death.', null, 400);
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update database
        $sql = "UPDATE certificate_of_death SET
                    registry_no = :registry_no,
                    date_of_registration = :date_of_registration,
                    date_of_registration_format = :date_of_registration_format,
                    date_of_registration_partial_month = :date_of_registration_partial_month,
                    date_of_registration_partial_year = :date_of_registration_partial_year,
                    date_of_registration_partial_day = :date_of_registration_partial_day,
                    deceased_first_name = :deceased_first_name,
                    deceased_middle_name = :deceased_middle_name,
                    deceased_last_name = :deceased_last_name,
                    sex = :sex,
                    date_of_birth = :date_of_birth,
                    date_of_death = :date_of_death,
                    age = :age,
                    occupation = :occupation,
                    place_of_death = :place_of_death,
                    father_first_name = :father_first_name,
                    father_middle_name = :father_middle_name,
                    father_last_name = :father_last_name,
                    father_citizenship = :father_citizenship,
                    mother_first_name = :mother_first_name,
                    mother_middle_name = :mother_middle_name,
                    mother_last_name = :mother_last_name,
                    mother_citizenship = :mother_citizenship,
                    pdf_filename = :pdf_filename,
                    pdf_filepath = :pdf_filepath,
                    pdf_hash = :pdf_hash,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

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
            ':updated_by'   => $_SESSION['user_id'] ?? null,
            ':id'           => $record_id
        ]);

        // Log activity
        log_activity(
            $pdo,
            'UPDATE_CERTIFICATE',
            "Updated Certificate of Death: Registry No. {$registry_no} (ID: {$record_id})",
            $_SESSION['user_id'] ?? null
        );

        // Commit transaction
        $pdo->commit();

        // Backup old PDF instead of deleting it
        if ($old_pdf_filename) {
            $backup_path = backup_pdf_file($old_pdf_filename);
            if ($backup_path) {
                $bkpStmt = $pdo->prepare(
                    "INSERT INTO pdf_backups (cert_type, record_id, original_path, backup_path, file_hash, backed_up_by)
                     VALUES ('death', :rid, :orig, :bkp, :hash, :uid)"
                );
                $bkpStmt->execute([
                    ':rid'  => $record_id,
                    ':orig' => $old_pdf_filename,
                    ':bkp'  => $backup_path,
                    ':hash' => $existing_record['pdf_hash'] ?? null,
                    ':uid'  => $_SESSION['user_id'] ?? null,
                ]);
            }
        }

        json_response(
            true,
            "Certificate of Death updated successfully! Registry No: {$registry_no}",
            ['id' => $record_id, 'registry_no' => $registry_no],
            200
        );

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();

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
