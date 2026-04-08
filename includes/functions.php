<?php
/**
 * Helper Functions for Certificate of Live Birth System
 */

/**
 * Sanitize input data for database storage.
 * NOTE: Does NOT apply htmlspecialchars — that belongs on OUTPUT (use escape_html).
 * Prepared statements already prevent SQL injection.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }

    if ($data === null) {
        return null;
    }

    $data = trim($data);
    return $data;
}

/**
 * Escape data for safe HTML output.
 * Use this when displaying user data in HTML templates.
 */
function escape_html($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
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

    // Deep PDF structure check (magic bytes + EOF marker)
    if (empty($errors)) {
        $integrity_errors = validate_pdf_integrity($file['tmp_name']);
        if (!empty($integrity_errors)) {
            $errors = array_merge($errors, $integrity_errors);
        }
    }

    return $errors;
}

/**
 * Upload file to server
 */
function upload_file($file, $type = null, $year = null) {
    // Validate file first
    $validation_errors = validate_file_upload($file);
    if (!empty($validation_errors)) {
        return ['success' => false, 'errors' => $validation_errors];
    }

    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('cert_', true) . '_' . time() . '.' . $file_extension;

    // Build subdirectory path: {type}/{year}/
    $sub_dir = '';
    if ($type) {
        $allowed_types = ['birth', 'death', 'marriage', 'marriage_license'];
        if (!in_array($type, $allowed_types)) {
            return ['success' => false, 'errors' => ['Invalid certificate type for upload.']];
        }
        $year = $year ? (int)$year : (int)date('Y');
        $sub_dir = $type . '/' . $year . '/';
    }

    $target_dir = UPLOAD_DIR . $sub_dir;
    $upload_path = $target_dir . $new_filename;

    // Create upload directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Return relative path (e.g., birth/2026/cert_xxx.pdf)
        $relative_path = $sub_dir . $new_filename;
        return [
            'success'  => true,
            'filename' => $relative_path,
            'path'     => $upload_path,
            'hash'     => compute_file_hash($upload_path),
        ];
    } else {
        return ['success' => false, 'errors' => ['Failed to move uploaded file.']];
    }
}

/**
 * Delete file from server
 * Accepts relative path (e.g., birth/2026/cert_xxx.pdf) or legacy filename
 */
function delete_file($filename) {
    $file_path = UPLOAD_DIR . $filename;
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

/**
 * Validate PDF structure via magic bytes and EOF marker.
 * Called on the PHP temp file BEFORE move_uploaded_file().
 *
 * @param  string $tmp_path  Path to temp file ($_FILES[...]['tmp_name'])
 * @return array             Empty array = valid; non-empty = error messages
 */
function validate_pdf_integrity(string $tmp_path): array {
    $errors = [];

    if (!file_exists($tmp_path) || !is_readable($tmp_path)) {
        $errors[] = 'Uploaded file is not accessible.';
        return $errors;
    }

    // Check magic bytes — every valid PDF starts with "%PDF-"
    $handle = fopen($tmp_path, 'rb');
    $header = fread($handle, 5);
    fclose($handle);

    if ($header !== '%PDF-') {
        $errors[] = 'The uploaded file is not a valid PDF (missing PDF header).';
        return $errors; // No point checking EOF on a non-PDF
    }

    // Check EOF marker — truncated PDFs are missing "%%EOF"
    $size   = filesize($tmp_path);
    $handle = fopen($tmp_path, 'rb');
    fseek($handle, max(0, $size - 1024));
    $tail = fread($handle, 1024);
    fclose($handle);

    if (strpos($tail, '%%EOF') === false) {
        $errors[] = 'The PDF appears to be incomplete or truncated (missing EOF marker).';
    }

    return $errors;
}

/**
 * Compute SHA-256 hash of a file on disk.
 * Called AFTER move_uploaded_file() to fingerprint the stored file.
 *
 * @param  string $filepath  Absolute path to the file
 * @return string            64-character hex string, or empty string on failure
 */
function compute_file_hash(string $filepath): string {
    if (!file_exists($filepath)) return '';
    return hash_file('sha256', $filepath) ?: '';
}

/**
 * Check whether a given SHA-256 hash is already attached to any certificate
 * record across all 4 certificate types. Used to prevent accidentally uploading
 * the same PDF to multiple records (e.g., user picks wrong file from folder).
 *
 * @param  PDO    $pdo              Active DB connection
 * @param  string $hash             SHA-256 hex string to look for
 * @param  string|null $exclude_type Certificate type to exclude (birth/death/marriage/marriage_license)
 * @param  int|null    $exclude_id   Record ID to exclude (used on update to ignore the current record)
 * @return array|null               ['cert_type' => ..., 'id' => ..., 'registry_no' => ..., 'label' => ...]
 *                                  or null if no duplicate found
 */
function check_pdf_duplicate(PDO $pdo, string $hash, ?string $exclude_type = null, ?int $exclude_id = null): ?array {
    if ($hash === '') return null;

    $tables = [
        'birth'            => ['table' => 'certificate_of_live_birth',       'label' => 'Certificate of Live Birth'],
        'death'            => ['table' => 'certificate_of_death',            'label' => 'Certificate of Death'],
        'marriage'         => ['table' => 'certificate_of_marriage',         'label' => 'Certificate of Marriage'],
        'marriage_license' => ['table' => 'application_for_marriage_license','label' => 'Application for Marriage License'],
    ];

    foreach ($tables as $type => $meta) {
        $sql    = "SELECT id, registry_no FROM {$meta['table']} WHERE pdf_hash = :h";
        $params = [':h' => $hash];

        // Exclude the current record being updated (same type + same id)
        if ($exclude_type === $type && $exclude_id !== null) {
            $sql .= " AND id <> :id";
            $params[':id'] = $exclude_id;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return [
                'cert_type'   => $type,
                'id'          => (int)$row['id'],
                'registry_no' => $row['registry_no'],
                'label'       => $meta['label'],
            ];
        }
    }

    return null;
}

/**
 * Move an existing PDF to the backup directory instead of deleting it.
 * Used by update endpoints to preserve the old version before replacing.
 *
 * @param  string       $relative_path  Relative path under UPLOAD_DIR (e.g. birth/2026/cert_xxx.pdf)
 * @return string|false                 Backup relative path on success, false on failure
 */
function backup_pdf_file(string $relative_path): string|false {
    $src = UPLOAD_DIR . $relative_path;
    if (!file_exists($src)) return false;

    $info       = pathinfo($relative_path);
    $backup_rel = 'backup/' . $info['dirname'] . '/'
                . $info['filename'] . '_' . time() . '.bak.pdf';
    $dest       = UPLOAD_DIR . $backup_rel;

    @mkdir(dirname($dest), 0755, true);
    return rename($src, $dest) ? $backup_rel : false;
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
    // Clear any output buffers to ensure clean JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

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
 * Validate date format.
 * Returns true if the date string matches the expected format.
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Safely convert a date string to Y-m-d format.
 * Returns the converted date, or null if invalid.
 * Use this instead of bare strtotime() to avoid silent 1970-01-01 bugs.
 */
function safe_date_convert($date_string, $output_format = 'Y-m-d') {
    if (empty($date_string)) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date_string)
        ?: DateTime::createFromFormat('m/d/Y', $date_string)
        ?: date_create($date_string);
    if (!$d) {
        return null;
    }
    return $d->format($output_format);
}

/**
 * Validate that a string does not exceed the database column length.
 * Returns true if valid, false if too long.
 */
function validate_string_length($value, $max_length, $field_name = 'Field') {
    if ($value === null) return true;
    if (mb_strlen($value, 'UTF-8') > $max_length) {
        return false;
    }
    return true;
}

/**
 * Validate multiple fields against their database column length limits.
 * Returns an array of error messages (empty if all valid).
 *
 * Usage:
 *   $errors = validate_field_lengths([
 *       'Child first name' => [$child_first_name, 100],
 *       'Place of birth'   => [$child_place_of_birth, 255],
 *   ]);
 */
function validate_field_lengths(array $fields) {
    $errors = [];
    foreach ($fields as $label => [$value, $max]) {
        if (!validate_string_length($value, $max, $label)) {
            $errors[] = "{$label} must not exceed {$max} characters.";
        }
    }
    return $errors;
}

/**
 * Log activity to the activity_logs table.
 * Unified function — use this everywhere instead of the auth.php version.
 */
function log_activity($pdo, $action, $details, $user_id = null) {
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                VALUES (:user_id, :action, :details, :ip_address, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}
?>
