<?php
/**
 * Application for Marriage License - Update API
 * Handles record updates and PDF replacement
 */

require_once '../includes/session_config.php';
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';

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
    $stmt = $pdo->prepare("SELECT * FROM application_for_marriage_license WHERE id = :id AND status = 'Active'");
    $stmt->execute([':id' => $record_id]);
    $existing_record = $stmt->fetch();

    if (!$existing_record) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
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
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Handle PDF file upload (optional for update)
    $pdf_filename = $existing_record['pdf_filename'];
    $pdf_filepath = $existing_record['pdf_filepath'];

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        // Upload new file into organized folder: marriage_license/{year}/
        $reg_year = date('Y', strtotime($date_of_application));
        $upload_result = upload_file($_FILES['pdf_file'], 'marriage_license', $reg_year);

        if (!$upload_result['success']) {
            echo json_encode(['success' => false, 'message' => implode(' ', $upload_result['errors'])]);
            exit;
        }

        // Delete old PDF file
        if (!empty($existing_record['pdf_filename'])) {
            delete_file($existing_record['pdf_filename']);
        }

        $pdf_filename = $upload_result['filename'];
        $pdf_filepath = $upload_result['path'];
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
        updated_by = :updated_by
    WHERE id = :id";

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
        ':updated_by' => $updated_by,
        ':id' => $record_id
    ];

    if ($stmt->execute($params)) {
        echo json_encode([
            'success' => true,
            'message' => 'Marriage license application updated successfully!',
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
