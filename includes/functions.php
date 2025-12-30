<?php
/**
 * Helper Functions for Certificate of Live Birth System
 */

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate file upload
 */
function validate_file_upload($file) {
    $errors = [];

    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "No file was uploaded.";
        return $errors;
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error code: " . $file['error'];
        return $errors;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size exceeds maximum allowed size of " . (MAX_FILE_SIZE / 1048576) . "MB.";
    }

    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
        $errors[] = "Invalid file type. Only PDF files are allowed.";
    }

    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime_type !== 'application/pdf') {
        $errors[] = "Invalid file format. File must be a PDF.";
    }

    return $errors;
}

/**
 * Upload file to server
 */
function upload_file($file, $custom_name = null) {
    // Validate file first
    $validation_errors = validate_file_upload($file);
    if (!empty($validation_errors)) {
        return ['success' => false, 'errors' => $validation_errors];
    }

    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($custom_name) {
        $new_filename = $custom_name . '.' . $file_extension;
    } else {
        $new_filename = uniqid('cert_', true) . '_' . time() . '.' . $file_extension;
    }

    $upload_path = UPLOAD_DIR . $new_filename;

    // Create upload directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'path' => $upload_path
        ];
    } else {
        return ['success' => false, 'errors' => ['Failed to move uploaded file.']];
    }
}

/**
 * Delete file from server
 */
function delete_file($filename) {
    $file_path = UPLOAD_DIR . $filename;
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'F d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function format_datetime($datetime, $format = 'F d, Y h:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Generate JSON response
 */
function json_response($success, $message, $data = null, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json');

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

/**
 * Validate registry number format
 */
function validate_registry_number($registry_no) {
    // Registry number should not be empty
    if (empty($registry_no)) {
        return "Registry number is required.";
    }

    // Add custom validation rules as needed
    if (strlen($registry_no) < 5) {
        return "Registry number must be at least 5 characters.";
    }

    return true;
}

/**
 * Validate date
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Check if record exists
 */
function record_exists($pdo, $table, $column, $value, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
    if ($exclude_id) {
        $sql .= " AND id != :exclude_id";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':value', $value);
    if ($exclude_id) {
        $stmt->bindParam(':exclude_id', $exclude_id);
    }
    $stmt->execute();

    return $stmt->fetchColumn() > 0;
}

/**
 * Log activity
 */
function log_activity($pdo, $action, $details, $user_id = null) {
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (:user_id, :action, :details, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':details' => $details
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}
?>
