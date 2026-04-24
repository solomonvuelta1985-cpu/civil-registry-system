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
/**
 * Parse the folder-year prefix of a registry number.
 * Accepts "YYYY-NNNN" or "YY-NNNN". Returns null if no parseable year prefix.
 *
 * 2-digit expansion: YY > current 2-digit year -> 19YY, else 20YY.
 * This pivot slides forward each year automatically.
 */
function registry_folder_year(?string $registry_no): ?int {
    if ($registry_no === null) return null;
    $trimmed = trim($registry_no);
    if ($trimmed === '') return null;

    $first = explode('-', $trimmed, 2)[0];
    if (!ctype_digit($first)) return null;

    $len = strlen($first);
    $currentY = (int)date('Y');

    if ($len === 4) {
        $y = (int)$first;
        return ($y >= 1900 && $y <= $currentY + 1) ? $y : null;
    }
    if ($len === 2) {
        $yy    = (int)$first;
        $pivot = (int)date('y');
        return ($yy > $pivot) ? (1900 + $yy) : (2000 + $yy);
    }
    return null;
}

/**
 * Extract a 4-digit year from a date string. Returns null for empty/invalid input.
 */
function year_from_date(?string $date): ?int {
    if ($date === null) return null;
    $date = trim($date);
    if ($date === '') return null;
    $ts = strtotime($date);
    if ($ts === false) return null;
    $y = (int)date('Y', $ts);
    return ($y >= 1900 && $y <= (int)date('Y') + 1) ? $y : null;
}

/**
 * Normalize a last name into a filesystem-safe folder segment.
 * Uppercases, replaces spaces with '_', strips non-alphanumeric/underscore.
 * Returns 'UNKNOWN' when the input is blank after normalization.
 */
function folder_safe_last_name(?string $last_name): string {
    if ($last_name === null) return 'UNKNOWN';
    $s = trim($last_name);
    if ($s === '') return 'UNKNOWN';
    $s = strtoupper($s);
    $s = preg_replace('/\s+/', '_', $s);
    $s = preg_replace('/[^A-Z0-9_]/', '', $s);
    $s = trim($s, '_');
    return $s === '' ? 'UNKNOWN' : $s;
}

/**
 * Build the relative sub-directory path for an upload given the certificate
 * type, the derived year (may be null), and the normalized last-name folder.
 *
 * Case A (year known):       {type}/{YEAR}/{LAST_NAME}/
 * Case B (no year anywhere): {type}/{LAST_NAME}/
 */
function upload_sub_dir(string $type, ?int $year, string $last_name_folder): string {
    if ($year !== null) {
        return $type . '/' . (string)$year . '/' . $last_name_folder . '/';
    }
    return $type . '/' . $last_name_folder . '/';
}

/**
 * Reconcile an existing PDF's folder path with the current record state.
 *
 * Used by update endpoints when the user edits a last name or event date
 * without re-uploading the PDF. If the target folder differs from where the
 * file currently lives, the file is moved on disk and the new relative path
 * is returned. After the move, the old folder is removed if it is empty.
 *
 * Returns:
 *   ['moved' => bool, 'new_filename' => string, 'new_filepath' => string, 'error' => ?string]
 *   - moved=false, error=null → no change needed (already correct, or source missing)
 *   - moved=true              → file relocated; caller must UPDATE pdf_filename/pdf_filepath
 *   - error!=null             → move attempted but failed; caller should keep old path
 */
function reconcile_pdf_folder(string $type, ?int $year, string $last_name_folder, ?string $current_filename): array {
    $result = ['moved' => false, 'new_filename' => $current_filename, 'new_filepath' => null, 'error' => null];

    if ($current_filename === null || $current_filename === '') {
        return $result;
    }

    $basename = basename($current_filename);
    $target_sub = upload_sub_dir($type, $year, $last_name_folder);
    $target_rel = $target_sub . $basename;

    if ($current_filename === $target_rel) {
        return $result;
    }

    $src_abs = UPLOAD_DIR . $current_filename;
    $dst_abs = UPLOAD_DIR . $target_rel;

    if (!is_file($src_abs)) {
        return $result;
    }

    if (file_exists($dst_abs)) {
        $result['error'] = 'Collision: a file already exists at target path.';
        return $result;
    }

    $dst_dir = dirname($dst_abs);
    if (!is_dir($dst_dir) && !mkdir($dst_dir, 0755, true) && !is_dir($dst_dir)) {
        $result['error'] = 'Failed to create target directory.';
        return $result;
    }

    if (!@rename($src_abs, $dst_abs)) {
        $result['error'] = 'Failed to move file.';
        return $result;
    }

    @rmdir(dirname($src_abs));

    $result['moved']        = true;
    $result['new_filename'] = $target_rel;
    $result['new_filepath'] = $dst_abs;
    return $result;
}

function upload_file($file, $type = null, $year = null, $last_name_folder = null) {
    // Validate file first
    $validation_errors = validate_file_upload($file);
    if (!empty($validation_errors)) {
        return ['success' => false, 'errors' => $validation_errors];
    }

    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('cert_', true) . '_' . time() . '.' . $file_extension;

    // Build subdirectory path.
    // New scheme: {type}/{year}/{LAST_NAME}/ when last name provided,
    //             {type}/{LAST_NAME}/        when no year was derivable,
    //             {type}/{year}/             when last name omitted (legacy caller).
    $sub_dir = '';
    if ($type) {
        $allowed_types = ['birth', 'death', 'marriage', 'marriage_license',
            'ra9048_petition', 'ra9048_legal_instrument', 'ra9048_court_decree'];
        if (!in_array($type, $allowed_types)) {
            return ['success' => false, 'errors' => ['Invalid certificate type for upload.']];
        }

        $year_int = ($year === null || $year === '') ? null : (int)$year;

        if ($last_name_folder !== null && $last_name_folder !== '') {
            $sub_dir = upload_sub_dir($type, $year_int, $last_name_folder);
        } else {
            // Legacy fallback — preserve prior behavior if a caller doesn't pass
            // a last name (should not happen for the certificate endpoints now).
            $y = $year_int ?? (int)date('Y');
            $sub_dir = $type . '/' . $y . '/';
        }
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
        $sql    = "SELECT id, registry_no FROM {$meta['table']} WHERE pdf_hash = :h AND status = 'Active'";
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
            error_log("[check_pdf_duplicate] MATCH: hash={$hash} type={$type} id={$row['id']} registry_no={$row['registry_no']}");
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
 * Normalize a partial registration date into a storable DATE value (or NULL).
 *
 * @param string      $format     One of: full, month_only, year_only, month_year, month_day, na
 * @param string      $full_date  Raw full date string (used when format = 'full')
 * @param string|null $month      1-12 integer string (used when format includes month)
 * @param string|null $year       4-digit year string (used when format includes year)
 * @param string|null $day        1-31 integer string (used when format includes day)
 *
 * @return array{date: string|null, error: string|null}
 */
function normalize_registration_date(string $format, string $full_date = '',
    ?string $month = null, ?string $year = null, ?string $day = null): array
{
    $allowed = ['full', 'month_only', 'year_only', 'month_year', 'month_day', 'na'];
    if (!in_array($format, $allowed, true)) {
        return ['date' => null, 'error' => 'Invalid date format type.'];
    }

    switch ($format) {
        case 'full':
            $converted = safe_date_convert($full_date);
            if ($converted === null) {
                return ['date' => null, 'error' => 'Invalid date of registration.'];
            }
            return ['date' => $converted, 'error' => null];

        case 'month_only':
            $m = (int)($month ?? 0);
            if ($m < 1 || $m > 12) {
                return ['date' => null, 'error' => 'Month is required for Month Only format.'];
            }
            return ['date' => null, 'error' => null];

        case 'year_only':
            $y = (int)($year ?? 0);
            if ($y < 1800 || $y > (int)date('Y') + 1) {
                return ['date' => null, 'error' => 'A valid 4-digit year is required for Year Only format.'];
            }
            return ['date' => null, 'error' => null];

        case 'month_year':
            $m = (int)($month ?? 0);
            $y = (int)($year ?? 0);
            if ($m < 1 || $m > 12) {
                return ['date' => null, 'error' => 'Month is required for Month and Year format.'];
            }
            if ($y < 1800 || $y > (int)date('Y') + 1) {
                return ['date' => null, 'error' => 'A valid 4-digit year is required for Month and Year format.'];
            }
            // Store first day of month so year-based queries still work.
            return ['date' => sprintf('%04d-%02d-01', $y, $m), 'error' => null];

        case 'month_day':
            $m = (int)($month ?? 0);
            $d = (int)($day ?? 0);
            if ($m < 1 || $m > 12) {
                return ['date' => null, 'error' => 'Month is required for Month and Day format.'];
            }
            if ($d < 1 || $d > 31) {
                return ['date' => null, 'error' => 'Day is required for Month and Day format.'];
            }
            return ['date' => null, 'error' => null];

        case 'na':
            return ['date' => null, 'error' => null];
    }

    return ['date' => null, 'error' => 'Unknown format.'];
}

/**
 * Format a stored partial registration date for human display.
 *
 * @param string|null $date    The raw DATE column value (Y-m-d or null)
 * @param string      $format  The date_of_registration_format column value
 * @param int|null    $month   Stored partial_month value (1-12)
 * @param int|null    $year    Stored partial_year value (YYYY)
 * @param int|null    $day     Stored partial_day value (1-31)
 *
 * @return string  Human-readable date string.
 */
function format_registration_date(?string $date, string $format = 'full',
    ?int $month = null, ?int $year = null, ?int $day = null): string
{
    $month_names = [
        1=>'January', 2=>'February', 3=>'March',    4=>'April',
        5=>'May',     6=>'June',     7=>'July',      8=>'August',
        9=>'September',10=>'October',11=>'November', 12=>'December'
    ];

    switch ($format) {
        case 'full':
            return $date ? date('M d, Y', strtotime($date)) : 'N/A';

        case 'month_only':
            return ($month && isset($month_names[$month])) ? $month_names[$month] : 'N/A';

        case 'year_only':
            return $year ? (string)$year : 'N/A';

        case 'month_year':
            if ($date) {
                return date('F Y', strtotime($date));
            }
            if ($month && $year && isset($month_names[$month])) {
                return $month_names[$month] . ' ' . $year;
            }
            return 'N/A';

        case 'month_day':
            if ($month && $day && isset($month_names[$month])) {
                return $month_names[$month] . ' ' . $day;
            }
            return 'N/A';

        case 'na':
            return 'N/A';
    }

    return 'N/A';
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

// ============================================================================
// Double Registration Detection Functions (PSA MC 2019-23)
// ============================================================================

/**
 * Normalize a registry number for comparison.
 * Strips hyphens, spaces, and leading zeros from numeric portions.
 */
function normalize_registry_no($registry_no) {
    if (empty($registry_no)) return '';
    return preg_replace('/[\s\-]/', '', strtoupper(trim($registry_no)));
}

/**
 * Find potential duplicate records for a given birth certificate.
 *
 * Phase 1: SQL pre-filter on high-weight field combinations (fast).
 * Phase 2: PHP scoring with weighted fuzzy matching (accurate).
 *
 * @param PDO    $pdo              Database connection
 * @param int    $source_id        ID of the record to check
 * @param string $certificate_type 'birth' (marriage/death reserved for future)
 * @return array Candidates with scores >= 40, sorted by score descending
 */
function find_potential_duplicates($pdo, $source_id, $certificate_type = 'birth') {
    if ($certificate_type !== 'birth') {
        return []; // Only birth supported for now
    }

    // Fetch the source record
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $source_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) return [];

    // Normalize source fields for comparison
    $dob = $source['child_date_of_birth'] ?: null;
    $dor = $source['date_of_registration'] ?: null;
    $m_ln = mb_strtolower(trim($source['mother_last_name'] ?? ''));
    $f_ln = mb_strtolower(trim($source['father_last_name'] ?? ''));
    $birth_order = trim($source['birth_order'] ?? '');
    $norm_reg = normalize_registry_no($source['registry_no'] ?? '');

    // Phase 1: SQL pre-filter — must match at least 2 high-weight fields
    $sql = "SELECT * FROM certificate_of_live_birth
            WHERE id != :source_id AND status = 'Active' AND (";
    $conditions = [];
    $params = [':source_id' => $source_id];

    if ($dob) {
        $conditions[] = "(child_date_of_birth = :dob AND LOWER(TRIM(mother_last_name)) = :m_ln1)";
        $conditions[] = "(child_date_of_birth = :dob2 AND LOWER(TRIM(father_last_name)) = :f_ln1)";
        $params[':dob'] = $dob;
        $params[':dob2'] = $dob;
        $params[':m_ln1'] = $m_ln;
        $params[':f_ln1'] = $f_ln;

        if (!empty($birth_order)) {
            $conditions[] = "(child_date_of_birth = :dob3 AND birth_order = :bo1)";
            $params[':dob3'] = $dob;
            $params[':bo1'] = $birth_order;
        }
    }

    if (!empty($m_ln) && !empty($f_ln)) {
        $conditions[] = "(LOWER(TRIM(mother_last_name)) = :m_ln2 AND LOWER(TRIM(father_last_name)) = :f_ln2)";
        $params[':m_ln2'] = $m_ln;
        $params[':f_ln2'] = $f_ln;
    }

    if (!empty($norm_reg)) {
        $conditions[] = "(REPLACE(REPLACE(registry_no, '-', ''), ' ', '') = :norm_reg)";
        $params[':norm_reg'] = $norm_reg;
    }

    // If no usable fields for pre-filter, return empty
    if (empty($conditions)) return [];

    $sql .= implode(' OR ', $conditions) . ") LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($candidates)) return [];

    // Phase 2: PHP scoring with weighted fields
    $weights = [
        'child_date_of_birth' => 18,
        'child_first_name'    => 7,
        'child_last_name'     => 5,
        'date_of_registration'=> 8,
        'mother_last_name'    => 14,
        'mother_first_name'   => 9,
        'mother_middle_name'  => 4,
        'father_last_name'    => 14,
        'father_first_name'   => 9,
        'father_middle_name'  => 4,
        'birth_order'         => 8,
    ];

    $results = [];
    foreach ($candidates as $candidate) {
        $score = 0;
        $matched_fields = [];

        foreach ($weights as $field => $weight) {
            $src_val = trim($source[$field] ?? '');
            $cand_val = trim($candidate[$field] ?? '');

            // Skip if both empty
            if ($src_val === '' && $cand_val === '') continue;

            if ($field === 'child_date_of_birth' || $field === 'date_of_registration') {
                // Exact date comparison
                if (!empty($src_val) && !empty($cand_val) && $src_val === $cand_val) {
                    $score += $weight;
                    $matched_fields[] = $field;
                }
            } elseif ($field === 'birth_order') {
                // Exact match
                if (!empty($src_val) && !empty($cand_val) && mb_strtolower($src_val) === mb_strtolower($cand_val)) {
                    $score += $weight;
                    $matched_fields[] = $field;
                }
            } else {
                // Fuzzy name matching using similar_text
                if (!empty($src_val) && !empty($cand_val)) {
                    $s = mb_strtoupper($src_val);
                    $c = mb_strtoupper($cand_val);
                    similar_text($s, $c, $percent);
                    if ($percent >= 85) {
                        // Scale weight by similarity percentage
                        $score += $weight * ($percent / 100);
                        $matched_fields[] = $field;
                    }
                }
            }
        }

        $score = round($score, 2);
        if ($score >= 40) {
            $child_name = trim(($candidate['child_first_name'] ?? '') . ' ' . ($candidate['child_last_name'] ?? ''));
            $results[] = [
                'id'            => (int)$candidate['id'],
                'registry_no'   => $candidate['registry_no'] ?? '',
                'child_name'    => $child_name,
                'match_score'   => $score,
                'match_fields'  => $matched_fields,
                'date_of_registration' => $candidate['date_of_registration'] ?? null,
            ];
        }
    }

    // Sort by score descending
    usort($results, function($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    return $results;
}

/**
 * Get the link status for a single record.
 * Returns link details if the record is involved in any active link.
 *
 * @return array|null Link info with 'role' = 'primary' or 'duplicate', or null if not linked
 */
function get_record_link_status($pdo, $certificate_id, $certificate_type = 'birth') {
    $sql = "SELECT rl.*,
                CASE
                    WHEN rl.primary_certificate_type = :type1 AND rl.primary_certificate_id = :id1 THEN 'primary'
                    WHEN rl.duplicate_certificate_type = :type2 AND rl.duplicate_certificate_id = :id2 THEN 'duplicate'
                END AS role
            FROM record_links rl
            WHERE rl.status = 'active' AND (
                (rl.primary_certificate_type = :type3 AND rl.primary_certificate_id = :id3)
                OR (rl.duplicate_certificate_type = :type4 AND rl.duplicate_certificate_id = :id4)
            )
            ORDER BY rl.linked_at DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type1' => $certificate_type, ':id1' => $certificate_id,
        ':type2' => $certificate_type, ':id2' => $certificate_id,
        ':type3' => $certificate_type, ':id3' => $certificate_id,
        ':type4' => $certificate_type, ':id4' => $certificate_id,
    ]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    return $link ?: null;
}

/**
 * Batch query link status for multiple records at once.
 * Returns an associative array keyed by certificate_id.
 *
 * @param PDO    $pdo
 * @param string $certificate_type
 * @param array  $record_ids
 * @return array [certificate_id => ['role' => 'primary'|'duplicate', 'link_id' => int, ...]]
 */
function get_record_links_batch($pdo, $certificate_type, array $record_ids) {
    if (empty($record_ids)) return [];

    $placeholders = implode(',', array_fill(0, count($record_ids), '?'));
    $params = array_merge(
        [$certificate_type], $record_ids,
        [$certificate_type], $record_ids
    );

    $sql = "SELECT rl.*,
                CASE
                    WHEN rl.primary_certificate_type = ? AND rl.primary_certificate_id IN ($placeholders) THEN 'primary'
                    WHEN rl.duplicate_certificate_type = ? AND rl.duplicate_certificate_id IN ($placeholders) THEN 'duplicate'
                END AS role,
                CASE
                    WHEN rl.primary_certificate_type = ? AND rl.primary_certificate_id IN ($placeholders) THEN rl.primary_certificate_id
                    ELSE rl.duplicate_certificate_id
                END AS matched_record_id
            FROM record_links rl
            WHERE rl.status = 'active' AND (
                (rl.primary_certificate_type = ? AND rl.primary_certificate_id IN ($placeholders))
                OR (rl.duplicate_certificate_type = ? AND rl.duplicate_certificate_id IN ($placeholders))
            )";
    // Build params: type + ids repeated 5 times
    $params = [];
    // CASE 1: primary match
    $params[] = $certificate_type;
    $params = array_merge($params, $record_ids);
    // CASE 2: duplicate match
    $params[] = $certificate_type;
    $params = array_merge($params, $record_ids);
    // CASE 3: matched_record_id primary
    $params[] = $certificate_type;
    $params = array_merge($params, $record_ids);
    // WHERE primary
    $params[] = $certificate_type;
    $params = array_merge($params, $record_ids);
    // WHERE duplicate
    $params[] = $certificate_type;
    $params = array_merge($params, $record_ids);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $rec_id = (int)$row['matched_record_id'];
        // Determine the paired record
        if ($row['role'] === 'primary') {
            $paired_id = (int)$row['duplicate_certificate_id'];
            $paired_type = $row['duplicate_certificate_type'];
        } else {
            $paired_id = (int)$row['primary_certificate_id'];
            $paired_type = $row['primary_certificate_type'];
        }

        // Fetch paired registry_no
        $table_map = ['birth' => 'certificate_of_live_birth', 'marriage' => 'certificate_of_marriage', 'death' => 'certificate_of_death'];
        $paired_table = $table_map[$paired_type] ?? null;
        $paired_reg = '';
        if ($paired_table) {
            $s2 = $pdo->prepare("SELECT registry_no FROM {$paired_table} WHERE id = ? LIMIT 1");
            $s2->execute([$paired_id]);
            $paired_reg = $s2->fetchColumn() ?: '';
        }

        $result[$rec_id] = [
            'link_id'           => (int)$row['id'],
            'role'              => $row['role'],
            'paired_id'         => $paired_id,
            'paired_type'       => $paired_type,
            'paired_registry_no'=> $paired_reg,
            'match_score'       => $row['match_score'],
            'has_discrepancies' => (bool)$row['has_discrepancies'],
            'needs_correction'  => (bool)$row['needs_correction'],
            'correction_status' => $row['correction_status'],
        ];
    }
    return $result;
}

/**
 * Detect discrepancies between two records.
 * Compares all relevant fields and returns an array of differences.
 *
 * @param array  $primary_record   The 1st Registration record
 * @param array  $duplicate_record The 2nd Registration record
 * @param string $certificate_type
 * @return array Array of ['field' => ..., 'primary_value' => ..., 'duplicate_value' => ...]
 */
function detect_discrepancies($primary_record, $duplicate_record, $certificate_type = 'birth') {
    $fields_to_compare = [
        'registry_no', 'date_of_registration',
        'child_first_name', 'child_middle_name', 'child_last_name',
        'child_date_of_birth', 'child_sex',
        'mother_first_name', 'mother_middle_name', 'mother_last_name',
        'father_first_name', 'father_middle_name', 'father_last_name',
        'birth_order', 'child_place_of_birth', 'barangay',
    ];

    $discrepancies = [];
    foreach ($fields_to_compare as $field) {
        $pv = trim($primary_record[$field] ?? '');
        $dv = trim($duplicate_record[$field] ?? '');

        // Skip if both empty
        if ($pv === '' && $dv === '') continue;

        // Normalize for case-insensitive comparison
        if (mb_strtoupper($pv) !== mb_strtoupper($dv)) {
            $discrepancies[] = [
                'field'           => $field,
                'primary_value'   => $pv,
                'duplicate_value' => $dv,
            ];
        }
    }
    return $discrepancies;
}

/**
 * Check if a record is involved in any active link.
 *
 * @return bool True if record is linked (as primary or duplicate)
 */
function is_record_linked($pdo, $certificate_id, $certificate_type = 'birth') {
    $sql = "SELECT COUNT(*) FROM record_links
            WHERE status = 'active' AND (
                (primary_certificate_type = :type1 AND primary_certificate_id = :id1)
                OR (duplicate_certificate_type = :type2 AND duplicate_certificate_id = :id2)
            )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type1' => $certificate_type, ':id1' => $certificate_id,
        ':type2' => $certificate_type, ':id2' => $certificate_id,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Get timeline of events for a record link from activity_logs.
 *
 * @param PDO $pdo
 * @param int $link_id
 * @return array Activity log entries related to this link
 */
function get_link_timeline($pdo, $link_id) {
    $sql = "SELECT al.*, u.full_name as user_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.details LIKE :pattern
            ORDER BY al.created_at DESC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pattern' => "%link_id:{$link_id}%"]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
