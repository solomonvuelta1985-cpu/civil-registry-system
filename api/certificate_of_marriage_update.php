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
    $pdf_filename = $existing_record['pdf_filename'];
    $pdf_filepath = $existing_record['pdf_filepath'];

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $pdf_file = $_FILES['pdf_file'];

        // Validate file type
        $allowed_types = ['application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $pdf_file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed.']);
            exit;
        }

        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($pdf_file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit.']);
            exit;
        }

        // Generate unique filename
        $file_extension = 'pdf';
        $unique_filename = 'marriage_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
        $upload_dir = '../uploads/';

        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $upload_path = $upload_dir . $unique_filename;

        // Move uploaded file
        if (move_uploaded_file($pdf_file['tmp_name'], $upload_path)) {
            // Delete old PDF file
            if (!empty($existing_record['pdf_filepath']) && file_exists($existing_record['pdf_filepath'])) {
                unlink($existing_record['pdf_filepath']);
            }

            $pdf_filename = $unique_filename;
            $pdf_filepath = $upload_path;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload PDF file.']);
            exit;
        }
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
        wife_father_name = :wife_father_name,
        wife_father_residence = :wife_father_residence,
        wife_mother_name = :wife_mother_name,
        wife_mother_residence = :wife_mother_residence,
        date_of_marriage = :date_of_marriage,
        place_of_marriage = :place_of_marriage,
        nature_of_solemnization = :nature_of_solemnization,
        pdf_filename = :pdf_filename,
        pdf_filepath = :pdf_filepath,
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
        ':wife_father_name' => $wife_father_name ?: null,
        ':wife_father_residence' => $wife_father_residence ?: null,
        ':wife_mother_name' => $wife_mother_name ?: null,
        ':wife_mother_residence' => $wife_mother_residence ?: null,
        ':date_of_marriage' => $date_of_marriage,
        ':place_of_marriage' => $place_of_marriage,
        ':nature_of_solemnization' => $nature_of_solemnization,
        ':pdf_filename' => $pdf_filename,
        ':pdf_filepath' => $pdf_filepath,
        ':updated_by' => $updated_by,
        ':id' => $record_id
    ];

    if ($stmt->execute($params)) {
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
