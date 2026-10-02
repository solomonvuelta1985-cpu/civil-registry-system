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
    $allowed = ['full', 'month_only', 'year_only', 'month_year', 'month_day', 'na', 'dont_know', 'forgotten', 'not_married', 'not_readable', 'no_entry'];
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
        case 'dont_know':
        case 'forgotten':
        case 'not_married':
        case 'not_readable':
        case 'no_entry':
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
        case 'dont_know':
            return "Don't Know";
        case 'forgotten':
            return 'Forgotten';
        case 'not_married':
            return 'Not Married';
        case 'not_readable':
            return 'Not Readable';
        case 'no_entry':
            return 'No Entry';
    }

    return 'N/A';
}

/** Return a display value for registry number fields without storing status text in the unique registry_no column. */
function format_registry_number(array $record, bool $emptyAsNoEntry = false): string
{
    $number = trim((string)($record['registry_no'] ?? ''));
    if ($number !== '') return $number;
    $labels = ['not_readable' => 'Not Readable', 'no_entry' => 'No Entry'];
    $status = (string)($record['registry_no_status'] ?? '');
    if (isset($labels[$status])) return $labels[$status];
    return $emptyAsNoEntry ? 'No Entry' : 'N/A';
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

/** Resolve a relative upload path inside the private upload root. */
function resolve_upload_path($relative, $mustExist = true) {
    if (!is_string($relative) || $relative === '' || strpos($relative, "\0") !== false) return false;
    $relative = str_replace('\\', '/', $relative);
    if ($relative[0] === '/' || preg_match('~(^|/)\.\.?(/|$)~', $relative)) return false;
    $root = realpath(UPLOAD_DIR);
    if ($root === false) return false;
    $path = realpath(UPLOAD_DIR . $relative);
    if ($path === false) return $mustExist ? false : (rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative);
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($path, $prefix) === 0 ? $path : false;
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

/** Normalize names for duplicate matching while tolerating punctuation and accents. */
function normalize_duplicate_match_text($value): string {
    $value = mb_strtoupper(trim((string)($value ?? '')), 'UTF-8');
    if ($value === '') return '';
    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
    }
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

/** SQL-side name normalization for candidate retrieval; PHP scoring still does the final comparison. */
function duplicate_match_text_sql(string $column): string {
    return "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(TRIM({$column})), '-', ''), ' ', ''), '.', ''), CHAR(39), '')";
}

/** Return the known year/month/day components of a full or partial birth date. */
function duplicate_birth_date_components(array $record): array {
    $format = strtolower(trim((string)($record['child_date_of_birth_format'] ?? 'full')));
    $date = trim((string)($record['child_date_of_birth'] ?? ''));
    $year = null;
    $month = null;
    $day = null;

    if (in_array($format, ['full', 'month_year'], true) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
        $year = (int)$parts[1];
        $month = (int)$parts[2];
        if ($format === 'full') $day = (int)$parts[3];
    }
    if (in_array($format, ['year_only', 'month_year'], true) && !empty($record['child_date_of_birth_partial_year'])) {
        $year = (int)$record['child_date_of_birth_partial_year'];
    }
    if (in_array($format, ['month_only', 'month_year', 'month_day'], true) && !empty($record['child_date_of_birth_partial_month'])) {
        $month = (int)$record['child_date_of_birth_partial_month'];
    }
    if ($format === 'month_day' && !empty($record['child_date_of_birth_partial_day'])) {
        $day = (int)$record['child_date_of_birth_partial_day'];
    }

    $components = [];
    if ($year !== null && $year > 0) $components['year'] = $year;
    if ($month !== null && $month >= 1 && $month <= 12) $components['month'] = $month;
    if ($day !== null && $day >= 1 && $day <= 31) $components['day'] = $day;
    return $components;
}

/** Fingerprint the identifying birth fields used by the matcher. */
function duplicate_record_fingerprint(array $record): string {
    $fields = [
        'child_date_of_birth_format', 'child_date_of_birth',
        'child_date_of_birth_partial_year', 'child_date_of_birth_partial_month', 'child_date_of_birth_partial_day',
        'child_first_name', 'child_middle_name', 'child_last_name', 'child_sex', 'time_of_birth',
        'type_of_birth', 'type_of_birth_other', 'birth_order', 'birth_order_other',
        'mother_first_name', 'mother_middle_name', 'mother_last_name',
        'father_first_name', 'father_middle_name', 'father_last_name',
    ];
    $snapshot = [];
    foreach ($fields as $field) {
        $value = $record[$field] ?? '';
        $snapshot[$field] = in_array($field, ['child_date_of_birth', 'child_date_of_birth_partial_year', 'child_date_of_birth_partial_month', 'child_date_of_birth_partial_day'], true)
            ? trim((string)$value)
            : normalize_duplicate_match_text($value);
    }
    return hash('sha256', serialize($snapshot));
}

/** SQL expression for a candidate birth-date component, including partial-date rows. */
function duplicate_birth_component_sql(string $component): string {
    $format = "COALESCE(child_date_of_birth_format, 'full')";
    if ($component === 'year') {
        return "CASE WHEN {$format} = 'full' OR ({$format} = 'month_year' AND (child_date_of_birth_partial_year IS NULL OR child_date_of_birth_partial_year = 0)) THEN YEAR(child_date_of_birth) WHEN {$format} IN ('year_only','month_year') THEN child_date_of_birth_partial_year ELSE NULL END";
    }
    if ($component === 'month') {
        return "CASE WHEN {$format} = 'full' OR ({$format} = 'month_year' AND (child_date_of_birth_partial_month IS NULL OR child_date_of_birth_partial_month = 0)) THEN MONTH(child_date_of_birth) WHEN {$format} IN ('month_only','month_year','month_day') THEN child_date_of_birth_partial_month ELSE NULL END";
    }
    return "CASE WHEN {$format} = 'full' THEN DAY(child_date_of_birth) WHEN {$format} = 'month_day' THEN child_date_of_birth_partial_day ELSE NULL END";
}

/**
 * Find likely duplicate birth records using normalized names, partial DOBs,
 * and explicit contradiction penalties. The score ranks field evidence; it
 * is not a probability that the records belong to the same person.
 */
function find_potential_duplicates($pdo, $source_id, $certificate_type = 'birth') {
    if ($certificate_type !== 'birth') return [];

    $sourceStmt = $pdo->prepare('SELECT * FROM certificate_of_live_birth WHERE id = :id LIMIT 1');
    $sourceStmt->execute([':id' => (int)$source_id]);
    $source = $sourceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) return [];

    $dobComponents = duplicate_birth_date_components($source);
    $motherLast = str_replace(' ', '', normalize_duplicate_match_text($source['mother_last_name'] ?? ''));
    $fatherLast = str_replace(' ', '', normalize_duplicate_match_text($source['father_last_name'] ?? ''));
    $childFirst = str_replace(' ', '', normalize_duplicate_match_text($source['child_first_name'] ?? ''));
    $childLast = str_replace(' ', '', normalize_duplicate_match_text($source['child_last_name'] ?? ''));
    $normalizedRegistry = normalize_registry_no($source['registry_no'] ?? '');

    $conditions = [];
    $params = [':source_id' => (int)$source_id];
    $addDateAnchor = static function (string $anchorSql, array $anchorParams, string $prefix) use (&$conditions, &$params, $dobComponents): void {
        if (!$dobComponents) return;
        $dateParts = [];
        foreach ($dobComponents as $component => $value) {
            $placeholder = ':dob_' . $prefix . '_' . $component;
            $dateParts[] = duplicate_birth_component_sql($component) . ' = ' . $placeholder;
            $params[$placeholder] = $value;
        }
        foreach ($anchorParams as $placeholder => $value) $params[$placeholder] = $value;
        $conditions[] = '((' . implode(' OR ', $dateParts) . ') AND (' . $anchorSql . '))';
    };

    if ($motherLast !== '') {
        $addDateAnchor(duplicate_match_text_sql('mother_last_name') . ' = :m_last_dob', [':m_last_dob' => $motherLast], 'mother');
    }
    if ($fatherLast !== '') {
        $addDateAnchor(duplicate_match_text_sql('father_last_name') . ' = :f_last_dob', [':f_last_dob' => $fatherLast], 'father');
    }
    if ($childFirst !== '' && $childLast !== '') {
        $addDateAnchor(
            duplicate_match_text_sql('child_first_name') . ' = :child_first_dob AND ' . duplicate_match_text_sql('child_last_name') . ' = :child_last_dob',
            [':child_first_dob' => $childFirst, ':child_last_dob' => $childLast],
            'child'
        );
    }

    if ($motherLast !== '' && $fatherLast !== '') {
        $conditions[] = '(' . duplicate_match_text_sql('mother_last_name') . ' = :m_last_pair AND ' . duplicate_match_text_sql('father_last_name') . ' = :f_last_pair)';
        $params[':m_last_pair'] = $motherLast;
        $params[':f_last_pair'] = $fatherLast;
    }
    if ($childFirst !== '' && $childLast !== '' && ($motherLast !== '' || $fatherLast !== '')) {
        $parentAnchors = [];
        if ($motherLast !== '') {
            $parentAnchors[] = duplicate_match_text_sql('mother_last_name') . ' = :m_last_child';
            $params[':m_last_child'] = $motherLast;
        }
        if ($fatherLast !== '') {
            $parentAnchors[] = duplicate_match_text_sql('father_last_name') . ' = :f_last_child';
            $params[':f_last_child'] = $fatherLast;
        }
        $conditions[] = '(' . duplicate_match_text_sql('child_first_name') . ' = :child_first_pair AND ' . duplicate_match_text_sql('child_last_name') . ' = :child_last_pair AND (' . implode(' OR ', $parentAnchors) . '))';
        $params[':child_first_pair'] = $childFirst;
        $params[':child_last_pair'] = $childLast;
    }
    if ($normalizedRegistry !== '') {
        $conditions[] = "(UPPER(REPLACE(REPLACE(TRIM(registry_no), '-', ''), ' ', '')) = :normalized_registry)";
        $params[':normalized_registry'] = $normalizedRegistry;
    }
    if (!$conditions) return [];

    $params[':linked_source_primary'] = (int)$source_id;
    $params[':linked_source_duplicate'] = (int)$source_id;
    $candidateSql = "SELECT * FROM certificate_of_live_birth
        WHERE id != :source_id AND status = 'Active'
          AND NOT EXISTS (
              SELECT 1 FROM record_links rl
              WHERE rl.status = 'active' AND (
                  (rl.primary_certificate_type = 'birth' AND rl.primary_certificate_id = :linked_source_primary
                   AND rl.duplicate_certificate_type = 'birth' AND rl.duplicate_certificate_id = certificate_of_live_birth.id)
                  OR
                  (rl.duplicate_certificate_type = 'birth' AND rl.duplicate_certificate_id = :linked_source_duplicate
                   AND rl.primary_certificate_type = 'birth' AND rl.primary_certificate_id = certificate_of_live_birth.id)
              )
          )
          AND (" . implode(' OR ', $conditions) . ')
        ORDER BY id DESC';
    $candidateStmt = $pdo->prepare($candidateSql);
    $candidateStmt->execute($params);
    $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$candidates) return [];

    $dismissals = [];
    try {
        $dismissalStmt = $pdo->prepare(
            "SELECT record_id_low, record_id_high, fingerprint_low, fingerprint_high
             FROM duplicate_match_dismissals
             WHERE certificate_type = 'birth' AND (record_id_low = :low_id OR record_id_high = :high_id)"
        );
        $dismissalStmt->execute([':low_id' => (int)$source_id, ':high_id' => (int)$source_id]);
        foreach ($dismissalStmt->fetchAll(PDO::FETCH_ASSOC) as $dismissal) {
            $otherId = (int)$dismissal['record_id_low'] === (int)$source_id
                ? (int)$dismissal['record_id_high']
                : (int)$dismissal['record_id_low'];
            $dismissals[$otherId] = $dismissal;
        }
    } catch (PDOException $e) {
        $errorInfo = $e->errorInfo ?? [];
        $missingDismissalTable = (string)$e->getCode() === '42S02' || (int)($errorInfo[1] ?? 0) === 1146;
        if (!$missingDismissalTable) throw $e;
        // Duplicate matching remains available before migration 052 is applied.
        error_log('Duplicate dismissal table unavailable: ' . $e->getMessage());
    }

    $sourceFingerprint = duplicate_record_fingerprint($source);
    $weights = [
        'child_first_name' => 7,
        'child_last_name' => 5,
        'child_middle_name' => 4,
        'date_of_registration' => 2,
        'mother_last_name' => 14,
        'mother_first_name' => 9,
        'mother_middle_name' => 2,
        'father_last_name' => 14,
        'father_first_name' => 9,
        'father_middle_name' => 2,
        'birth_order' => 8,
        'child_sex' => 3,
        'time_of_birth' => 2,
        'type_of_birth' => 1,
    ]; // 82 points; DOB contributes up to 18 more.

    $results = [];
    foreach ($candidates as $candidate) {
        $candidateId = (int)$candidate['id'];
        if (isset($dismissals[$candidateId])) {
            $dismissal = $dismissals[$candidateId];
            $candidateFingerprint = duplicate_record_fingerprint($candidate);
            $sameSnapshot = (int)$dismissal['record_id_low'] === (int)$source_id
                ? hash_equals((string)$dismissal['fingerprint_low'], $sourceFingerprint)
                    && hash_equals((string)$dismissal['fingerprint_high'], $candidateFingerprint)
                : hash_equals((string)$dismissal['fingerprint_low'], $candidateFingerprint)
                    && hash_equals((string)$dismissal['fingerprint_high'], $sourceFingerprint);
            if ($sameSnapshot) continue;
        }

        $score = 0.0;
        $matchedFields = [];
        $conflictingFields = [];
        $penalties = [];

        $candidateDob = duplicate_birth_date_components($candidate);
        $sharedDobParts = 0;
        foreach ($dobComponents as $component => $value) {
            if (!array_key_exists($component, $candidateDob)) continue;
            $sharedDobParts++;
            if ((int)$candidateDob[$component] === (int)$value) {
                $score += 6;
            } else {
                $conflictingFields[] = 'child_date_of_birth';
                $penalties[] = 6;
            }
        }
        if ($sharedDobParts > 0 && !in_array('child_date_of_birth', $conflictingFields, true)) {
            $matchedFields[] = 'child_date_of_birth';
        }

        foreach ($weights as $field => $weight) {
            $sourceValue = trim((string)($source[$field] ?? ''));
            $candidateValue = trim((string)($candidate[$field] ?? ''));
            if ($sourceValue === '' || $candidateValue === '') continue;

            if ($field === 'date_of_registration' || $field === 'time_of_birth') {
                if ($sourceValue === $candidateValue) {
                    $score += $weight;
                    $matchedFields[] = $field;
                } elseif ($field === 'time_of_birth') {
                    $conflictingFields[] = $field;
                    $penalties[] = $weight;
                }
                continue;
            }

            if (in_array($field, ['birth_order', 'child_sex', 'type_of_birth'], true)) {
                $sourceNormalized = normalize_duplicate_match_text($sourceValue);
                $candidateNormalized = normalize_duplicate_match_text($candidateValue);
                if ($sourceNormalized === $candidateNormalized) {
                    $score += $weight;
                    $matchedFields[] = $field;
                } else {
                    $conflictingFields[] = $field;
                    $penalties[] = $field === 'birth_order' ? 14 : ($field === 'child_sex' ? 8 : 4);
                }
                continue;
            }

            $sourceNormalized = normalize_duplicate_match_text($sourceValue);
            $candidateNormalized = normalize_duplicate_match_text($candidateValue);
            if ($sourceNormalized === '' || $candidateNormalized === '') continue;
            similar_text($sourceNormalized, $candidateNormalized, $percent);
            if ($percent >= 85) {
                $score += $weight * ($percent / 100);
                $matchedFields[] = $field;
            } elseif ($percent < 70) {
                $nameMismatchPenalties = [
                    'child_first_name' => 5,
                    'child_middle_name' => 2,
                    'child_last_name' => 7,
                    'mother_last_name' => 8,
                    'mother_first_name' => 4,
                    'mother_middle_name' => 2,
                    'father_last_name' => 8,
                    'father_first_name' => 4,
                    'father_middle_name' => 2,
                ];
                if (isset($nameMismatchPenalties[$field])) {
                    $conflictingFields[] = $field;
                    $penalties[] = $nameMismatchPenalties[$field];
                }
            }
        }

        foreach ([
            ['birth_order', 'birth_order_other', 8],
            ['type_of_birth', 'type_of_birth_other', 4],
        ] as [$categoryField, $detailField, $penalty]) {
            if (normalize_duplicate_match_text($source[$categoryField] ?? '') !== 'OTHER'
                || normalize_duplicate_match_text($candidate[$categoryField] ?? '') !== 'OTHER') {
                continue;
            }
            $sourceDetail = normalize_duplicate_match_text($source[$detailField] ?? '');
            $candidateDetail = normalize_duplicate_match_text($candidate[$detailField] ?? '');
            if ($sourceDetail === '' || $candidateDetail === '') continue;
            if ($sourceDetail === $candidateDetail) {
                $matchedFields[] = $detailField;
            } else {
                $conflictingFields[] = $detailField;
                $penalties[] = $penalty;
            }
        }

        $score = round(max(0, $score - array_sum($penalties)), 2);
        $criticalIdentityFields = [
            'child_first_name', 'child_middle_name', 'child_last_name', 'child_date_of_birth', 'child_sex',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
        ];
        $criticalConflictCount = count(array_intersect(array_unique($conflictingFields), $criticalIdentityFields));
        if ($score < 55 || $criticalConflictCount >= 3) continue;

        $results[] = [
            'id' => $candidateId,
            'registry_no' => $candidate['registry_no'] ?? '',
            'child_name' => trim(($candidate['child_first_name'] ?? '') . ' ' . ($candidate['child_last_name'] ?? '')),
            'match_score' => $score,
            'match_fields' => array_values(array_unique($matchedFields)),
            'conflicting_fields' => array_values(array_unique($conflictingFields)),
            'date_of_registration' => $candidate['date_of_registration'] ?? null,
        ];
    }

    usort($results, static function ($a, $b) {
        $scoreOrder = $b['match_score'] <=> $a['match_score'];
        return $scoreOrder !== 0 ? $scoreOrder : ($b['id'] <=> $a['id']);
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

/** Stop issuance from a record actively marked as the second registration. */
function reject_active_duplicate_registration_issuance(PDO $pdo, string $certificate_type, int $certificate_id, string $document_label): void {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM record_links
         WHERE status = 'active'
           AND duplicate_certificate_type = :type
           AND duplicate_certificate_id = :id
         ORDER BY linked_at DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([':type' => $certificate_type, ':id' => $certificate_id]);
    $linkId = $stmt->fetchColumn();
    if ($linkId !== false) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(
            false,
            $document_label . ' cannot be generated from a record marked as the 2nd registration. Use the 1st Registration record.',
            ['link_id' => (int)$linkId],
            409
        );
    }
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
