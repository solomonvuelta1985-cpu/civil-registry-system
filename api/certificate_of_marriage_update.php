<?php
/**
 * Certificate of Marriage - Update API
 * Handles record updates and PDF replacement
 */

require_once '../includes/session_config.php';
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Optional: Check authentication
// if (!isLoggedIn()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
//     exit;
// }

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Get record ID
    $record_id = sanitize_input($_POST['record_id'] ?? '');

    if (empty($record_id)) {
        echo json_encode(['success' => false, 'message' => 'Record ID is required.']);
        exit;
    }

    // Fetch existing record
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_marriage WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
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
        empty($husband_date_of_birth) || empty($husband_place_of_birth) || empty($husband_residence) ||
        empty($wife_first_name) || empty($wife_last_name) ||
        empty($wife_date_of_birth) || empty($wife_place_of_birth) || empty($wife_residence) ||
        empty($date_of_marriage) || empty($place_of_marriage) || empty($nature_of_solemnization)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
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
            echo json_encode(['success' => false, 'message' => implode(' ', $upload_result['errors'])]);
            exit;
        }

        // Mark old file for backup (done after update)
        $old_pdf_filename = $existing_record['pdf_filename'];

        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'];
        $pdf_hash     = $upload_result['hash'] ?? null;
    }

    // Update database
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

    if ($stmt->execute($params)) {
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
        echo json_encode([
            'success' => true,
            'message' => 'Marriage certificate updated successfully!',
            'record_id' => $record_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}
