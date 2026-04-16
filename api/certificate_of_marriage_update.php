<?php
/**
 * Certificate of Marriage - Update API
 * Handles record updates and PDF replacement
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
    // Get record ID
    $record_id = sanitize_input($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        json_response(false, 'Record ID is required.', null, 400);
    }

    // Fetch existing record
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_marriage WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        json_response(false, 'Record not found.', null, 404);
    }

    // Sanitize and validate input
    $registry_no = sanitize_input($_POST['registry_no'] ?? '');
    $date_of_registration_format = sanitize_input($_POST['date_of_registration_format'] ?? 'full');
    $date_of_registration        = sanitize_input($_POST['date_of_registration'] ?? '');
    $partial_date_month          = sanitize_input($_POST['partial_date_month'] ?? null) ?: null;
    $partial_date_year           = sanitize_input($_POST['partial_date_year'] ?? null) ?: null;
    $partial_date_day            = sanitize_input($_POST['partial_date_day'] ?? null) ?: null;

    // Husband's Information
    $husband_first_name = sanitize_input($_POST['husband_first_name'] ?? '');
    $husband_middle_name = sanitize_input($_POST['husband_middle_name'] ?? '');
    $husband_last_name = sanitize_input($_POST['husband_last_name'] ?? '');
    $husband_date_of_birth = sanitize_input($_POST['husband_date_of_birth'] ?? '');
    $husband_dob_format        = sanitize_input($_POST['husband_date_of_birth_format'] ?? 'full');
    $husband_dob_partial_month = sanitize_input($_POST['husband_date_of_birth_partial_month'] ?? null) ?: null;
    $husband_dob_partial_year  = sanitize_input($_POST['husband_date_of_birth_partial_year'] ?? null) ?: null;
    $husband_dob_partial_day   = sanitize_input($_POST['husband_date_of_birth_partial_day'] ?? null) ?: null;
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
    $wife_dob_format        = sanitize_input($_POST['wife_date_of_birth_format'] ?? 'full');
    $wife_dob_partial_month = sanitize_input($_POST['wife_date_of_birth_partial_month'] ?? null) ?: null;
    $wife_dob_partial_year  = sanitize_input($_POST['wife_date_of_birth_partial_year'] ?? null) ?: null;
    $wife_dob_partial_day   = sanitize_input($_POST['wife_date_of_birth_partial_day'] ?? null) ?: null;
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

    // Validation: Required fields
    $allowed_formats = ['full', 'month_only', 'year_only', 'month_year', 'month_day', 'na'];
    if (!in_array($date_of_registration_format, $allowed_formats, true)) {
        json_response(false, 'Invalid date format type.', null, 400);
    }
    if (!in_array($husband_dob_format, $allowed_formats, true)) {
        json_response(false, 'Invalid husband date of birth format type.', null, 400);
    }
    if (!in_array($wife_dob_format, $allowed_formats, true)) {
        json_response(false, 'Invalid wife date of birth format type.', null, 400);
    }
    if (empty($husband_first_name) || empty($husband_last_name) ||
        empty($husband_place_of_birth) || empty($husband_residence) ||
        empty($wife_first_name) || empty($wife_last_name) ||
        empty($wife_place_of_birth) || empty($wife_residence) ||
        empty($date_of_marriage) || empty($place_of_marriage) || empty($nature_of_solemnization)) {
        json_response(false, 'Please fill in all required fields.', null, 400);
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

    // Normalize husband date of birth (supports partial formats)
    $h_dob_norm = normalize_registration_date(
        $husband_dob_format,
        $husband_date_of_birth,
        $husband_dob_partial_month,
        $husband_dob_partial_year,
        $husband_dob_partial_day
    );
    if ($h_dob_norm['error'] !== null) {
        json_response(false, 'Husband date of birth: ' . $h_dob_norm['error'], null, 400);
    }
    $husband_date_of_birth = $h_dob_norm['date'];
    $husband_dob_stored_month = in_array($husband_dob_format, ['month_only', 'month_year', 'month_day'])
        ? ((int)$husband_dob_partial_month ?: null) : null;
    $husband_dob_stored_year  = in_array($husband_dob_format, ['year_only', 'month_year'])
        ? ((int)$husband_dob_partial_year ?: null) : null;
    $husband_dob_stored_day   = ($husband_dob_format === 'month_day')
        ? ((int)$husband_dob_partial_day ?: null) : null;

    // Normalize wife date of birth (supports partial formats)
    $w_dob_norm = normalize_registration_date(
        $wife_dob_format,
        $wife_date_of_birth,
        $wife_dob_partial_month,
        $wife_dob_partial_year,
        $wife_dob_partial_day
    );
    if ($w_dob_norm['error'] !== null) {
        json_response(false, 'Wife date of birth: ' . $w_dob_norm['error'], null, 400);
    }
    $wife_date_of_birth = $w_dob_norm['date'];
    $wife_dob_stored_month = in_array($wife_dob_format, ['month_only', 'month_year', 'month_day'])
        ? ((int)$wife_dob_partial_month ?: null) : null;
    $wife_dob_stored_year  = in_array($wife_dob_format, ['year_only', 'month_year'])
        ? ((int)$wife_dob_partial_year ?: null) : null;
    $wife_dob_stored_day   = ($wife_dob_format === 'month_day')
        ? ((int)$wife_dob_partial_day ?: null) : null;
    $date_of_marriage = safe_date_convert($date_of_marriage);
    if ($date_of_marriage === null) {
        json_response(false, 'Invalid date of marriage.', null, 400);
    }

    // Handle PDF file upload (optional for update)
    $pdf_filename     = $existing_record['pdf_filename'];
    $pdf_filepath     = $existing_record['pdf_filepath'];
    $pdf_hash         = $existing_record['pdf_hash'] ?? null;
    $old_pdf_filename = null;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        // Upload new file into organized folder: marriage/{year}/{HUSBAND_LAST_NAME}/
        $upload_year = year_from_date($date_of_marriage)
                    ?? registry_folder_year($registry_no);
        $upload_last = folder_safe_last_name($husband_last_name);
        $upload_result = upload_file($_FILES['pdf_file'], 'marriage', $upload_year, $upload_last);

        if (!$upload_result['success']) {
            json_response(false, implode(' ', $upload_result['errors']), null, 400);
        }

        // Mark old file for backup (done after update)
        $old_pdf_filename = $existing_record['pdf_filename'];

        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'];
        $pdf_hash     = $upload_result['hash'] ?? null;

        // Duplicate-PDF guard: reject if this exact file is already
        // attached to another record. Exclude the current record from
        // the check so re-uploading the same file to itself is a no-op.
        if ($pdf_hash) {
            $dup = check_pdf_duplicate($pdo, $pdf_hash, 'marriage', (int)$record_id);
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

    // Reconcile PDF folder with (possibly renamed) husband last name / date of marriage.
    if ($old_pdf_filename === null && $pdf_filename) {
        $reconcile_year = year_from_date($date_of_marriage) ?? registry_folder_year($registry_no);
        $reconcile_last = folder_safe_last_name($husband_last_name);
        $rec = reconcile_pdf_folder('marriage', $reconcile_year, $reconcile_last, $pdf_filename);
        if ($rec['moved']) {
            $pdf_filename = $rec['new_filename'];
            $pdf_filepath = $rec['new_filepath'];
        }
    }

    // Update database
    $pdo->beginTransaction();

    $sql = "UPDATE certificate_of_marriage SET
        registry_no = :registry_no,
        date_of_registration = :date_of_registration,
        date_of_registration_format = :date_of_registration_format,
        date_of_registration_partial_month = :date_of_registration_partial_month,
        date_of_registration_partial_year = :date_of_registration_partial_year,
        date_of_registration_partial_day = :date_of_registration_partial_day,
        husband_first_name = :husband_first_name,
        husband_middle_name = :husband_middle_name,
        husband_last_name = :husband_last_name,
        husband_date_of_birth = :husband_date_of_birth,
        husband_date_of_birth_format = :husband_date_of_birth_format,
        husband_date_of_birth_partial_month = :husband_date_of_birth_partial_month,
        husband_date_of_birth_partial_year = :husband_date_of_birth_partial_year,
        husband_date_of_birth_partial_day = :husband_date_of_birth_partial_day,
        husband_place_of_birth = :husband_place_of_birth,
        husband_residence = :husband_residence,
        husband_citizenship = :husband_citizenship,
        husband_father_name = :husband_father_name,
        husband_father_residence = :husband_father_residence,
        husband_mother_name = :husband_mother_name,
        husband_mother_residence = :husband_mother_residence,
        wife_first_name = :wife_first_name,
        wife_middle_name = :wife_middle_name,
        wife_last_name = :wife_last_name,
        wife_date_of_birth = :wife_date_of_birth,
        wife_date_of_birth_format = :wife_date_of_birth_format,
        wife_date_of_birth_partial_month = :wife_date_of_birth_partial_month,
        wife_date_of_birth_partial_year = :wife_date_of_birth_partial_year,
        wife_date_of_birth_partial_day = :wife_date_of_birth_partial_day,
        wife_place_of_birth = :wife_place_of_birth,
        wife_residence = :wife_residence,
        wife_citizenship = :wife_citizenship,
        wife_father_name = :wife_father_name,
        wife_father_residence = :wife_father_residence,
        wife_mother_name = :wife_mother_name,
        wife_mother_residence = :wife_mother_residence,
        date_of_marriage = :date_of_marriage,
        place_of_marriage = :place_of_marriage,
        nature_of_solemnization = :nature_of_solemnization,
        pdf_filename = :pdf_filename,
        pdf_filepath = :pdf_filepath,
        pdf_hash = :pdf_hash,
        updated_by = :updated_by
    WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $updated_by = $_SESSION['user_id'] ?? 1;

    $params = [
        ':registry_no'                         => $registry_no ?: null,
        ':date_of_registration'                => $date_of_registration,
        ':date_of_registration_format'         => $date_of_registration_format,
        ':date_of_registration_partial_month'  => $stored_partial_month,
        ':date_of_registration_partial_year'   => $stored_partial_year,
        ':date_of_registration_partial_day'    => $stored_partial_day,
        ':husband_first_name' => $husband_first_name,
        ':husband_middle_name' => $husband_middle_name ?: null,
        ':husband_last_name' => $husband_last_name,
        ':husband_date_of_birth' => $husband_date_of_birth,
        ':husband_date_of_birth_format'        => $husband_dob_format,
        ':husband_date_of_birth_partial_month' => $husband_dob_stored_month,
        ':husband_date_of_birth_partial_year'  => $husband_dob_stored_year,
        ':husband_date_of_birth_partial_day'   => $husband_dob_stored_day,
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
        ':wife_date_of_birth_format'        => $wife_dob_format,
        ':wife_date_of_birth_partial_month' => $wife_dob_stored_month,
        ':wife_date_of_birth_partial_year'  => $wife_dob_stored_year,
        ':wife_date_of_birth_partial_day'   => $wife_dob_stored_day,
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
        ':pdf_filename' => $pdf_filename,
        ':pdf_filepath' => $pdf_filepath,
        ':pdf_hash'     => $pdf_hash,
        ':updated_by'   => $updated_by,
        ':id'           => $record_id
    ];

    if (!$stmt->execute($params)) {
        $pdo->rollBack();
        json_response(false, 'Failed to update record.', null, 500);
    }

    // Backup old PDF instead of deleting it
    if ($old_pdf_filename) {
        $backup_path = backup_pdf_file($old_pdf_filename);
        if ($backup_path) {
            $bkpStmt = $pdo->prepare(
                "INSERT INTO pdf_backups (cert_type, record_id, original_path, backup_path, file_hash, backed_up_by)
                 VALUES ('marriage', :rid, :orig, :bkp, :hash, :uid)"
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

    $pdo->commit();

    json_response(true, 'Marriage certificate updated successfully!', ['record_id' => $record_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Error: " . $e->getMessage());
    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_registry_no') !== false) {
        json_response(false, 'Registry number already exists on another record. Please use a unique registry number.', null, 409);
    }
    json_response(false, 'Database error occurred.', null, 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    json_response(false, 'An error occurred while processing your request.', null, 500);
}
