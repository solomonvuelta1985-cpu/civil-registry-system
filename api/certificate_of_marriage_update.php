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

    // Validation: Required fields
    if (empty($date_of_registration) || empty($husband_first_name) || empty($husband_last_name) ||
        empty($husband_place_of_birth) || empty($husband_residence) ||
        empty($wife_first_name) || empty($wife_last_name) ||
        empty($wife_place_of_birth) || empty($wife_residence) ||
        empty($date_of_marriage) || empty($place_of_marriage) || empty($nature_of_solemnization)) {
        json_response(false, 'Please fill in all required fields.', null, 400);
    }

    // Convert date formats
    $date_of_registration = safe_date_convert($date_of_registration);
    if ($date_of_registration === null) {
        json_response(false, 'Invalid date of registration.', null, 400);
    }
    if (!empty($husband_date_of_birth)) {
        $husband_date_of_birth = safe_date_convert($husband_date_of_birth);
        if ($husband_date_of_birth === null) {
            json_response(false, 'Invalid husband date of birth.', null, 400);
        }
    } else {
        $husband_date_of_birth = null;
    }
    if (!empty($wife_date_of_birth)) {
        $wife_date_of_birth = safe_date_convert($wife_date_of_birth);
        if ($wife_date_of_birth === null) {
            json_response(false, 'Invalid wife date of birth.', null, 400);
        }
    } else {
        $wife_date_of_birth = null;
    }
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
        // Upload new file into organized folder: marriage/{year}/
        $reg_year = date('Y', strtotime($date_of_registration));
        $upload_result = upload_file($_FILES['pdf_file'], 'marriage', $reg_year);

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

    // Update database
    $pdo->beginTransaction();

    $sql = "UPDATE certificate_of_marriage SET
        registry_no = :registry_no,
        date_of_registration = :date_of_registration,
        husband_first_name = :husband_first_name,
        husband_middle_name = :husband_middle_name,
        husband_last_name = :husband_last_name,
        husband_date_of_birth = :husband_date_of_birth,
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
    json_response(false, 'Database error occurred.', null, 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    json_response(false, 'An error occurred while processing your request.', null, 500);
}
