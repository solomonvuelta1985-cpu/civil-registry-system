<?php
/**
 * Application for Marriage License - Save API
 * Handles form submission and PDF upload
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
    $groom_residence = sanitize_input($_POST['groom_residence'] ?? '');

    // Groom's Father Information
    $groom_father_first_name = sanitize_input($_POST['groom_father_first_name'] ?? '');
    $groom_father_middle_name = sanitize_input($_POST['groom_father_middle_name'] ?? '');
    $groom_father_last_name = sanitize_input($_POST['groom_father_last_name'] ?? '');
    $groom_father_citizenship = sanitize_input($_POST['groom_father_citizenship'] ?? '');
    $groom_father_residence = sanitize_input($_POST['groom_father_residence'] ?? '');

    // Groom's Mother Information
    $groom_mother_first_name = sanitize_input($_POST['groom_mother_first_name'] ?? '');
    $groom_mother_middle_name = sanitize_input($_POST['groom_mother_middle_name'] ?? '');
    $groom_mother_last_name = sanitize_input($_POST['groom_mother_last_name'] ?? '');
    $groom_mother_citizenship = sanitize_input($_POST['groom_mother_citizenship'] ?? '');
    $groom_mother_residence = sanitize_input($_POST['groom_mother_residence'] ?? '');

    // Bride's Information
    $bride_first_name = sanitize_input($_POST['bride_first_name'] ?? '');
    $bride_middle_name = sanitize_input($_POST['bride_middle_name'] ?? '');
    $bride_last_name = sanitize_input($_POST['bride_last_name'] ?? '');
    $bride_date_of_birth = sanitize_input($_POST['bride_date_of_birth'] ?? '');
    $bride_place_of_birth = sanitize_input($_POST['bride_place_of_birth'] ?? '');
    $bride_citizenship = sanitize_input($_POST['bride_citizenship'] ?? '');
    $bride_residence = sanitize_input($_POST['bride_residence'] ?? '');

    // Bride's Father Information
    $bride_father_first_name = sanitize_input($_POST['bride_father_first_name'] ?? '');
    $bride_father_middle_name = sanitize_input($_POST['bride_father_middle_name'] ?? '');
    $bride_father_last_name = sanitize_input($_POST['bride_father_last_name'] ?? '');
    $bride_father_citizenship = sanitize_input($_POST['bride_father_citizenship'] ?? '');
    $bride_father_residence = sanitize_input($_POST['bride_father_residence'] ?? '');

    // Bride's Mother Information
    $bride_mother_first_name = sanitize_input($_POST['bride_mother_first_name'] ?? '');
    $bride_mother_middle_name = sanitize_input($_POST['bride_mother_middle_name'] ?? '');
    $bride_mother_last_name = sanitize_input($_POST['bride_mother_last_name'] ?? '');
    $bride_mother_citizenship = sanitize_input($_POST['bride_mother_citizenship'] ?? '');
    $bride_mother_residence = sanitize_input($_POST['bride_mother_residence'] ?? '');

    $add_new = isset($_POST['add_new']) && $_POST['add_new'] === '1';

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

    // Validate PDF file upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'PDF file is required.']);
        exit;
    }

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
    $unique_filename = 'marriage_license_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
    $upload_dir = '../uploads/';

    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $upload_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($pdf_file['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload PDF file.']);
        exit;
    }

    // Insert into database
    $sql = "INSERT INTO application_for_marriage_license (
        registry_no, date_of_application,
        groom_first_name, groom_middle_name, groom_last_name,
        groom_date_of_birth, groom_place_of_birth, groom_citizenship, groom_residence,
        groom_father_first_name, groom_father_middle_name, groom_father_last_name,
        groom_father_citizenship, groom_father_residence,
        groom_mother_first_name, groom_mother_middle_name, groom_mother_last_name,
        groom_mother_citizenship, groom_mother_residence,
        bride_first_name, bride_middle_name, bride_last_name,
        bride_date_of_birth, bride_place_of_birth, bride_citizenship, bride_residence,
        bride_father_first_name, bride_father_middle_name, bride_father_last_name,
        bride_father_citizenship, bride_father_residence,
        bride_mother_first_name, bride_mother_middle_name, bride_mother_last_name,
        bride_mother_citizenship, bride_mother_residence,
        pdf_filename, pdf_filepath,
        status, created_by
    ) VALUES (
        :registry_no, :date_of_application,
        :groom_first_name, :groom_middle_name, :groom_last_name,
        :groom_date_of_birth, :groom_place_of_birth, :groom_citizenship, :groom_residence,
        :groom_father_first_name, :groom_father_middle_name, :groom_father_last_name,
        :groom_father_citizenship, :groom_father_residence,
        :groom_mother_first_name, :groom_mother_middle_name, :groom_mother_last_name,
        :groom_mother_citizenship, :groom_mother_residence,
        :bride_first_name, :bride_middle_name, :bride_last_name,
        :bride_date_of_birth, :bride_place_of_birth, :bride_citizenship, :bride_residence,
        :bride_father_first_name, :bride_father_middle_name, :bride_father_last_name,
        :bride_father_citizenship, :bride_father_residence,
        :bride_mother_first_name, :bride_mother_middle_name, :bride_mother_last_name,
        :bride_mother_citizenship, :bride_mother_residence,
        :pdf_filename, :pdf_filepath,
        'Active', :created_by
    )";

    $stmt = $pdo->prepare($sql);

    $created_by = $_SESSION['user_id'] ?? 1;

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
        ':pdf_filename' => $unique_filename,
        ':pdf_filepath' => $upload_path,
        ':created_by' => $created_by
    ];

    if ($stmt->execute($params)) {
        $message = $add_new
            ? 'Marriage license application saved successfully! You can add another record.'
            : 'Marriage license application saved successfully!';

        echo json_encode([
            'success' => true,
            'message' => $message,
            'record_id' => $pdo->lastInsertId()
        ]);
    } else {
        // Delete uploaded file if database insert fails
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        echo json_encode(['success' => false, 'message' => 'Failed to save record to database.']);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());

    // Delete uploaded file if there was an error
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }

    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());

    // Delete uploaded file if there was an error
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }

    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request.']);
}
