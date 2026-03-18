<?php
/**
 * Application for Marriage License - Update API
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
    $stmt = $pdo->prepare("SELECT * FROM application_for_marriage_license WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        json_response(false, 'Record not found.', null, 404);
    }

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

    // Validation: Required fields
    if (empty($date_of_application) ||
        empty($groom_first_name) || empty($groom_last_name) ||
        empty($groom_date_of_birth) || empty($groom_place_of_birth) ||
        empty($groom_citizenship) || empty($groom_residence) ||
        empty($bride_first_name) || empty($bride_last_name) ||
        empty($bride_date_of_birth) || empty($bride_place_of_birth) ||
        empty($bride_citizenship) || empty($bride_residence)) {
        json_response(false, 'Please fill in all required fields.', null, 400);
    }

    // Convert date formats
    $date_of_application = safe_date_convert($date_of_application);
    if ($date_of_application === null) {
        json_response(false, 'Invalid date of application.', null, 400);
    }
    $groom_date_of_birth = safe_date_convert($groom_date_of_birth);
    if ($groom_date_of_birth === null) {
        json_response(false, 'Invalid groom date of birth.', null, 400);
    }
    $bride_date_of_birth = safe_date_convert($bride_date_of_birth);
    if ($bride_date_of_birth === null) {
        json_response(false, 'Invalid bride date of birth.', null, 400);
    }

    // Handle PDF file upload (optional for update)
    $pdf_filename     = $existing_record['pdf_filename'];
    $pdf_filepath     = $existing_record['pdf_filepath'];
    $pdf_hash         = $existing_record['pdf_hash'] ?? null;
    $old_pdf_filename = null;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        // Upload new file into organized folder: marriage_license/{year}/
        $reg_year = date('Y', strtotime($date_of_application));
        $upload_result = upload_file($_FILES['pdf_file'], 'marriage_license', $reg_year);

        if (!$upload_result['success']) {
            json_response(false, implode(' ', $upload_result['errors']), null, 400);
        }

        // Mark old file for backup (done after update)
        $old_pdf_filename = $existing_record['pdf_filename'];

        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'];
        $pdf_hash     = $upload_result['hash'] ?? null;
    }

    // Update database
    $sql = "UPDATE application_for_marriage_license SET
        registry_no = :registry_no,
        date_of_application = :date_of_application,
        groom_first_name = :groom_first_name,
        groom_middle_name = :groom_middle_name,
        groom_last_name = :groom_last_name,
        groom_date_of_birth = :groom_date_of_birth,
        groom_place_of_birth = :groom_place_of_birth,
        groom_citizenship = :groom_citizenship,
        groom_residence = :groom_residence,
        groom_father_first_name = :groom_father_first_name,
        groom_father_middle_name = :groom_father_middle_name,
        groom_father_last_name = :groom_father_last_name,
        groom_father_citizenship = :groom_father_citizenship,
        groom_father_residence = :groom_father_residence,
        groom_mother_first_name = :groom_mother_first_name,
        groom_mother_middle_name = :groom_mother_middle_name,
        groom_mother_last_name = :groom_mother_last_name,
        groom_mother_citizenship = :groom_mother_citizenship,
        groom_mother_residence = :groom_mother_residence,
        bride_first_name = :bride_first_name,
        bride_middle_name = :bride_middle_name,
        bride_last_name = :bride_last_name,
        bride_date_of_birth = :bride_date_of_birth,
        bride_place_of_birth = :bride_place_of_birth,
        bride_citizenship = :bride_citizenship,
        bride_residence = :bride_residence,
        bride_father_first_name = :bride_father_first_name,
        bride_father_middle_name = :bride_father_middle_name,
        bride_father_last_name = :bride_father_last_name,
        bride_father_citizenship = :bride_father_citizenship,
        bride_father_residence = :bride_father_residence,
        bride_mother_first_name = :bride_mother_first_name,
        bride_mother_middle_name = :bride_mother_middle_name,
        bride_mother_last_name = :bride_mother_last_name,
        bride_mother_citizenship = :bride_mother_citizenship,
        bride_mother_residence = :bride_mother_residence,
        pdf_filename = :pdf_filename,
        pdf_filepath = :pdf_filepath,
        pdf_hash = :pdf_hash,
        updated_by = :updated_by
    WHERE id = :id";

    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);

    $updated_by = $_SESSION['user_id'] ?? 1;

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
        ':pdf_filename' => $pdf_filename,
        ':pdf_filepath' => $pdf_filepath,
        ':pdf_hash'     => $pdf_hash,
        ':updated_by'   => $updated_by,
        ':id'           => $record_id
    ];

    $stmt->execute($params);

    // Backup old PDF instead of deleting it
    if ($old_pdf_filename) {
        $backup_path = backup_pdf_file($old_pdf_filename);
        if ($backup_path) {
            $bkpStmt = $pdo->prepare(
                "INSERT INTO pdf_backups (cert_type, record_id, original_path, backup_path, file_hash, backed_up_by)
                 VALUES ('marriage_license', :rid, :orig, :bkp, :hash, :uid)"
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

    json_response(true, 'Marriage license application updated successfully!', ['record_id' => $record_id]);

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
