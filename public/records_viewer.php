<?php
/**
 * Civil Registry Records Viewer - View, Search, Edit, Delete
 * Supports: Marriage Certificates, Birth Certificates, Death Certificates, Marriage Licence Applications
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check permission based on record type
$permission_map = [
    'marriage' => 'marriage_view',
    'birth' => 'birth_view',
    'death' => 'death_view',
    'marriage_license' => 'marriage_license_view'
];

// Determine record type - defaults to 'marriage' if not already set
if (!isset($record_type)) {
    $record_type = 'marriage';
}

// Check if user has permission for this record type
$required_permission = $permission_map[$record_type] ?? 'marriage_view';
if (!hasPermission($required_permission)) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

// Configuration for different certificate types
$record_configs = [
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'title' => 'Marriage Records',
        'icon' => 'heart',
        'entry_form' => 'certificate_of_marriage.php',
        'delete_api' => '../api/certificate_of_marriage_delete.php',
        'search_fields' => [
            'registry_no',
            'husband_first_name',
            'husband_middle_name',
            'husband_last_name',
            'wife_first_name',
            'wife_middle_name',
            'wife_last_name',
            'date_of_marriage',
            'place_of_marriage',
            'husband_place_of_birth',
            'wife_place_of_birth'
        ],
        'sort_columns' => [
            'registry_no',
            'husband_first_name',
            'wife_first_name',
            'date_of_marriage',
            'place_of_marriage',
            'date_of_registration',
            'created_at'
        ],
        'table_columns' => [
            ['label' => 'Registry No.', 'field' => 'registry_no', 'sortable' => true],
            ['label' => 'Husband', 'field' => 'husband_name', 'sortable' => true, 'sort_field' => 'husband_first_name'],
            ['label' => 'Wife', 'field' => 'wife_name', 'sortable' => true, 'sort_field' => 'wife_first_name'],
            ['label' => 'Marriage Date', 'field' => 'date_of_marriage', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Place', 'field' => 'place_of_marriage', 'sortable' => true],
            ['label' => 'Registration Date', 'field' => 'date_of_registration', 'sortable' => true, 'type' => 'date']
        ],
        'filters' => [
            ['name' => 'marriage_date_from', 'label' => 'Marriage Date From', 'type' => 'date', 'field' => 'date_of_marriage', 'operator' => '>='],
            ['name' => 'marriage_date_to', 'label' => 'Marriage Date To', 'type' => 'date', 'field' => 'date_of_marriage', 'operator' => '<='],
            ['name' => 'reg_date_from', 'label' => 'Registration Date From', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '>='],
            ['name' => 'reg_date_to', 'label' => 'Registration Date To', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '<='],
            ['name' => 'place', 'label' => 'Place of Marriage', 'type' => 'text', 'field' => 'place_of_marriage', 'operator' => 'LIKE']
        ]
    ],
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'title' => 'Birth Records',
        'icon' => 'baby',
        'entry_form' => 'certificate_of_live_birth.php',
        'delete_api' => '../api/certificate_of_live_birth_delete.php',
        'search_fields' => [
            'registry_no',
            'child_first_name',
            'child_middle_name',
            'child_last_name',
            'father_first_name',
            'father_middle_name',
            'father_last_name',
            'mother_first_name',
            'mother_middle_name',
            'mother_last_name',
            'child_date_of_birth',
            'child_place_of_birth'
        ],
        'sort_columns' => [
            'registry_no',
            'child_first_name',
            'child_date_of_birth',
            'father_first_name',
            'mother_first_name',
            'date_of_registration',
            'type_of_birth',
            'created_at'
        ],
        'table_columns' => [
            ['label' => 'Registry No.', 'field' => 'registry_no', 'sortable' => true],
            ['label' => 'Child', 'field' => 'child_name', 'sortable' => true, 'sort_field' => 'child_first_name'],
            ['label' => 'Birth Date', 'field' => 'child_date_of_birth', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Sex', 'field' => 'child_sex', 'sortable' => true],
            ['label' => 'Father', 'field' => 'father_name', 'sortable' => true, 'sort_field' => 'father_first_name'],
            ['label' => 'Mother', 'field' => 'mother_name', 'sortable' => true, 'sort_field' => 'mother_first_name'],
            ['label' => 'Registration Date', 'field' => 'date_of_registration', 'sortable' => true, 'type' => 'date']
        ],
        'filters' => [
            ['name' => 'birth_date_from', 'label' => 'Birth Date From', 'type' => 'date', 'field' => 'child_date_of_birth', 'operator' => '>='],
            ['name' => 'birth_date_to', 'label' => 'Birth Date To', 'type' => 'date', 'field' => 'child_date_of_birth', 'operator' => '<='],
            ['name' => 'reg_date_from', 'label' => 'Registration Date From', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '>='],
            ['name' => 'reg_date_to', 'label' => 'Registration Date To', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '<='],
            ['name' => 'child_sex', 'label' => 'Sex', 'type' => 'text', 'field' => 'child_sex', 'operator' => 'LIKE']
        ]
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'title' => 'Death Records',
        'icon' => 'user-x',
        'entry_form' => 'certificate_of_death.php',
        'delete_api' => '../api/certificate_of_death_delete.php',
        'search_fields' => [
            'registry_no',
            'deceased_first_name',
            'deceased_middle_name',
            'deceased_last_name',
            'father_first_name',
            'father_middle_name',
            'father_last_name',
            'mother_first_name',
            'mother_middle_name',
            'mother_last_name',
            'date_of_birth',
            'date_of_death',
            'place_of_death',
            'occupation'
        ],
        'sort_columns' => [
            'registry_no',
            'deceased_first_name',
            'date_of_birth',
            'date_of_death',
            'age',
            'place_of_death',
            'date_of_registration',
            'created_at'
        ],
        'table_columns' => [
            ['label' => 'Registry No.', 'field' => 'registry_no', 'sortable' => true],
            ['label' => 'Deceased', 'field' => 'deceased_name', 'sortable' => true, 'sort_field' => 'deceased_first_name'],
            ['label' => 'Date of Birth', 'field' => 'date_of_birth', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Date of Death', 'field' => 'date_of_death', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Age', 'field' => 'age', 'sortable' => true],
            ['label' => 'Place of Death', 'field' => 'place_of_death', 'sortable' => true],
            ['label' => 'Registration Date', 'field' => 'date_of_registration', 'sortable' => true, 'type' => 'date']
        ],
        'filters' => [
            ['name' => 'death_date_from', 'label' => 'Death Date From', 'type' => 'date', 'field' => 'date_of_death', 'operator' => '>='],
            ['name' => 'death_date_to', 'label' => 'Death Date To', 'type' => 'date', 'field' => 'date_of_death', 'operator' => '<='],
            ['name' => 'reg_date_from', 'label' => 'Registration Date From', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '>='],
            ['name' => 'reg_date_to', 'label' => 'Registration Date To', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '<='],
            ['name' => 'place', 'label' => 'Place of Death', 'type' => 'text', 'field' => 'place_of_death', 'operator' => 'LIKE'],
            ['name' => 'age_from', 'label' => 'Age From', 'type' => 'number', 'field' => 'age', 'operator' => '>='],
            ['name' => 'age_to', 'label' => 'Age To', 'type' => 'number', 'field' => 'age', 'operator' => '<=']
        ]
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'title' => 'Marriage License Applications',
        'icon' => 'file-heart',
        'entry_form' => 'application_for_marriage_license.php',
        'delete_api' => '../api/application_for_marriage_license_delete.php',
        'search_fields' => [
            'registry_no',
            'groom_first_name',
            'groom_middle_name',
            'groom_last_name',
            'bride_first_name',
            'bride_middle_name',
            'bride_last_name',
            'groom_place_of_birth',
            'bride_place_of_birth',
            'groom_citizenship',
            'bride_citizenship'
        ],
        'sort_columns' => [
            'registry_no',
            'groom_first_name',
            'bride_first_name',
            'date_of_application',
            'groom_citizenship',
            'bride_citizenship',
            'created_at'
        ],
        'table_columns' => [
            ['label' => 'Registry No.', 'field' => 'registry_no', 'sortable' => true],
            ['label' => 'Groom', 'field' => 'groom_name', 'sortable' => true, 'sort_field' => 'groom_first_name'],
            ['label' => 'Bride', 'field' => 'bride_name', 'sortable' => true, 'sort_field' => 'bride_first_name'],
            ['label' => 'Application Date', 'field' => 'date_of_application', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Groom Citizenship', 'field' => 'groom_citizenship', 'sortable' => true],
            ['label' => 'Bride Citizenship', 'field' => 'bride_citizenship', 'sortable' => true]
        ],
        'filters' => [
            ['name' => 'app_date_from', 'label' => 'Application Date From', 'type' => 'date', 'field' => 'date_of_application', 'operator' => '>='],
            ['name' => 'app_date_to', 'label' => 'Application Date To', 'type' => 'date', 'field' => 'date_of_application', 'operator' => '<='],
            ['name' => 'groom_citizenship', 'label' => 'Groom Citizenship', 'type' => 'text', 'field' => 'groom_citizenship', 'operator' => 'LIKE'],
            ['name' => 'bride_citizenship', 'label' => 'Bride Citizenship', 'type' => 'text', 'field' => 'bride_citizenship', 'operator' => 'LIKE']
        ]
    ]
];

// Validate record type
if (!isset($record_configs[$record_type])) {
    $record_type = 'marriage';
}

$config = $record_configs[$record_type];

// Pagination settings
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($records_per_page < 5 || $records_per_page > 100) {
    $records_per_page = 10;
}
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query = '';
$params = [];

if (!empty($search)) {
    $search_conditions = [];
    $search_index = 0;
    foreach ($config['search_fields'] as $field) {
        $param_name = ':search_' . $search_index;
        $search_conditions[] = "{$field} LIKE {$param_name}";
        $params[$param_name] = "%{$search}%";
        $search_index++;
    }
    $search_query = " AND (" . implode(' OR ', $search_conditions) . ")";
}

// Advanced filters
$filter_query = '';
$active_filters = [];

foreach ($config['filters'] as $filter) {
    if (!empty($_GET[$filter['name']])) {
        $active_filters[] = $filter['name'];
        $param_name = ':' . $filter['name'];

        if ($filter['operator'] === 'LIKE') {
            $filter_query .= " AND {$filter['field']} LIKE {$param_name}";
            $params[$param_name] = "%{$_GET[$filter['name']]}%";
        } else {
            $filter_query .= " AND {$filter['field']} {$filter['operator']} {$param_name}";
            $params[$param_name] = $_GET[$filter['name']];
        }
    }
}

$has_active_filters = !empty($active_filters);

// Sorting functionality
$allowed_sort_columns = $config['sort_columns'];

$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_columns)
    ? $_GET['sort_by']
    : 'created_at';

$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC'
    ? 'ASC'
    : 'DESC';

// Get total records count
$count_sql = "SELECT COUNT(*) as total FROM {$config['table']} WHERE status = 'Active'" . $search_query . $filter_query;

try {
    $count_stmt = $pdo->prepare($count_sql);

    // Bind parameters for count query
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }

    $count_stmt->execute();
    $total_records = (int)($count_stmt->fetch()['total'] ?? 0);
    $total_pages = (int)ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Count query error: " . $e->getMessage());
    error_log("Count SQL: " . $count_sql);
    error_log("Params: " . print_r($params, true));
    $total_records = 0;
    $total_pages = 0;
}

// Fetch records
$sql = "SELECT * FROM {$config['table']} WHERE status = 'Active'"
    . $search_query
    . $filter_query
    . " ORDER BY {$sort_by} {$sort_order} LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);

    // Bind parameters for main query
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Main query error: " . $e->getMessage());
    error_log("Main SQL: " . $sql);
    error_log("Params: " . print_r($params, true));
    $records = [];
}

// Helper function to build query string for pagination/sorting
function build_query_string($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}

// Helper function to get sort URL
function get_sort_url($column) {
    global $sort_by, $sort_order;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $query = build_query_string(['sort_by', 'sort_order', 'page']);
    return '?sort_by=' . $column . '&sort_order=' . $new_order . ($query ? '&' . $query : '');
}

// Helper function to get sort icon
function get_sort_icon($column) {
    global $sort_by, $sort_order;
    if ($sort_by !== $column) {
        return 'chevrons-up-down';
    }
    return $sort_order === 'ASC' ? 'chevron-up' : 'chevron-down';
}

// Helper function to get field value from record
function get_field_value($record, $field, $type = 'text') {
    global $record_type;

    // Handle composite fields (e.g., full names)
    if ($field === 'husband_name' && $record_type === 'marriage') {
        $first = $record['husband_first_name'] ?? '';
        $middle = $record['husband_middle_name'] ?? '';
        $last = $record['husband_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'wife_name' && $record_type === 'marriage') {
        $first = $record['wife_first_name'] ?? '';
        $middle = $record['wife_middle_name'] ?? '';
        $last = $record['wife_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'child_name' && $record_type === 'birth') {
        $first = $record['child_first_name'] ?? '';
        $middle = $record['child_middle_name'] ?? '';
        $last = $record['child_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'deceased_name' && $record_type === 'death') {
        $first = $record['deceased_first_name'] ?? '';
        $middle = $record['deceased_middle_name'] ?? '';
        $last = $record['deceased_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'groom_name' && $record_type === 'marriage_license') {
        $first = $record['groom_first_name'] ?? '';
        $middle = $record['groom_middle_name'] ?? '';
        $last = $record['groom_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'bride_name' && $record_type === 'marriage_license') {
        $first = $record['bride_first_name'] ?? '';
        $middle = $record['bride_middle_name'] ?? '';
        $last = $record['bride_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'father_name') {
        $first = $record['father_first_name'] ?? '';
        $middle = $record['father_middle_name'] ?? '';
        $last = $record['father_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($field === 'mother_name') {
        $first = $record['mother_first_name'] ?? '';
        $middle = $record['mother_middle_name'] ?? '';
        $last = $record['mother_last_name'] ?? '';
        $full_name = trim($first . ' ' . $middle . ' ' . $last);
        return htmlspecialchars($full_name) ?: 'N/A';
    } elseif ($type === 'date' && !empty($record[$field])) {
        return date('M d, Y', strtotime($record[$field]));
    } else {
        return htmlspecialchars($record[$field] ?? 'N/A');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['title']); ?> - Civil Registry</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Notiflix - Modern Notification Library -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js"></script>
    <script src="../assets/js/notiflix-config.js"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <!-- Record Preview Modal Styles -->
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css">

    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Configure PDF.js worker
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>

    <style>
        /* ========================================
           MODERN CLEAN DESIGN - 2026 STANDARDS
           No gradients, minimal styling, professional
           ======================================== */

        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #F9FAFB;
            color: #111827;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        :root {
            /* Base Colors - Flat, No Gradients */
            --bg-primary: #FFFFFF;
            --bg-secondary: #F9FAFB;
            --bg-tertiary: #F3F4F6;

            /* Text Colors */
            --text-primary: #111827;
            --text-secondary: #6B7280;
            --text-tertiary: #9CA3AF;

            /* Border Colors */
            --border-light: #F3F4F6;
            --border-medium: #E5E7EB;
            --border-strong: #D1D5DB;

            /* Action Colors - Flat */
            --primary: #3B82F6;
            --primary-hover: #2563EB;
            --primary-light: #EFF6FF;

            --success: #10B981;
            --success-hover: #059669;
            --success-light: #D1FAE5;

            --warning: #F59E0B;
            --warning-hover: #D97706;
            --warning-light: #FEF3C7;

            --danger: #EF4444;
            --danger-hover: #DC2626;
            --danger-light: #FEE2E2;

            /* Spacing */
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;

            /* Border Radius */
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;

            /* Shadows - Minimal */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        /* Page Layout */
        .page-container {
            padding: var(--spacing-xl);
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            margin-bottom: var(--spacing-xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            letter-spacing: -0.02em;
        }

        .page-title [data-lucide] {
            color: var(--primary);
            width: 28px;
            height: 28px;
        }

        /* Buttons - Clean Flat Design */
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-success {
            background-color: var(--success);
            color: #FFFFFF;
        }

        .btn-success:hover {
            background-color: var(--success-hover);
        }

        .btn-warning {
            background-color: var(--warning);
            color: #FFFFFF;
        }

        .btn-warning:hover {
            background-color: var(--warning-hover);
        }

        .btn-danger {
            background-color: var(--danger);
            color: #FFFFFF;
        }

        .btn-danger:hover {
            background-color: var(--danger-hover);
        }

        .btn-sm {
            padding: 7px 12px;
            font-size: 13px;
        }

        .btn-outline {
            background: var(--bg-primary);
            border: 1px solid var(--border-medium);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-strong);
            color: var(--text-primary);
        }

        /* Search & Filter - Clean Spacing */
        .search-section {
            margin-bottom: var(--spacing-lg);
        }

        .search-form {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
            align-items: stretch;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px var(--spacing-md) 12px 44px;
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-md);
            font-size: 14px;
            background-color: var(--bg-primary);
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-input-wrapper::before {
            content: '';
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }

        .filter-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text-secondary);
            background: var(--bg-primary);
            border: 1px solid var(--border-medium);
            padding: 12px var(--spacing-lg);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .filter-toggle-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--border-strong);
            color: var(--text-primary);
        }

        .filter-toggle-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
        }

        .advanced-filters {
            display: none;
            padding: 0;
            background: transparent;
            border-radius: 0;
            border: none;
            margin-bottom: var(--spacing-lg);
        }

        .advanced-filters.show {
            display: block;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: var(--spacing-sm);
            margin-bottom: 0;
            padding: var(--spacing-md);
            background: var(--bg-primary);
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-md);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-group input,
        .filter-group select {
            padding: 10px var(--spacing-sm);
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-md);
            font-size: 14px;
            background-color: var(--bg-primary);
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-start;
            margin-top: 0;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: #FFFFFF;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        /* Table - Modern Clean Design, NO GRADIENTS, NO EXTRA BOXES */
        .table-container {
            background: var(--bg-primary);
            border-radius: 0;
            border: 1px solid var(--border-medium);
            overflow: hidden;
            box-shadow: none;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table thead {
            background: var(--bg-secondary);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .records-table th {
            padding: 14px var(--spacing-lg);
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 13px;
            letter-spacing: 0.02em;
            border-bottom: 1px solid var(--border-medium);
            white-space: nowrap;
        }

        .records-table th.sortable {
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease;
        }

        .records-table th.sortable:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .records-table th.sortable a {
            display: flex;
            align-items: center;
            gap: 6px;
            color: inherit;
            text-decoration: none;
        }

        .records-table th.sortable.active {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .sort-icon {
            opacity: 0.4;
            transition: opacity 0.15s;
            width: 16px;
            height: 16px;
        }

        .records-table th.sortable:hover .sort-icon,
        .records-table th.sortable.active .sort-icon {
            opacity: 1;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) var(--spacing-lg);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-medium);
        }

        .table-controls-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .table-controls-right {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .per-page-selector select {
            padding: 6px var(--spacing-sm);
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-sm);
            font-size: 13px;
            cursor: pointer;
            background-color: var(--bg-primary);
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .per-page-selector select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .records-table td {
            padding: 16px var(--spacing-lg);
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .records-table tbody {
            transition: opacity 0.3s ease;
        }

        .records-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .records-table tbody tr:hover {
            background-color: var(--bg-secondary);
        }

        .records-table tbody tr:last-child td {
            border-bottom: none;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        /* Pagination - Clean Design, NO CARD BOX */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            padding: var(--spacing-lg) 0;
            margin-top: var(--spacing-lg);
            background: transparent;
            border-radius: 0;
            border: none;
        }

        .pagination-btn {
            min-width: 40px;
            height: 40px;
            padding: 0;
            border: 2px solid var(--border-medium);
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .pagination-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
            background: var(--bg-tertiary);
        }

        .pagination-btn.active {
            background: #3B82F6 !important;
            background: var(--primary) !important;
            color: #FFFFFF !important;
            border-color: #3B82F6 !important;
            border-color: var(--primary) !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3) !important;
            cursor: default !important;
            pointer-events: none !important;
        }

        .pagination-info {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 0 var(--spacing-md);
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .pagination-divider {
            width: 1px;
            height: 20px;
            background: var(--border-medium);
            margin: 0 var(--spacing-xs);
        }

        .no-records {
            text-align: center;
            padding: 60px var(--spacing-lg);
            color: var(--text-tertiary);
        }

        .no-records i {
            margin-bottom: var(--spacing-md);
        }

        .no-records p {
            margin: var(--spacing-xs) 0;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* Alert - Clean Design */
        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-xl);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            border-left: 3px solid transparent;
            font-weight: 500;
            font-size: 14px;
        }

        .alert-success {
            background-color: var(--success-light);
            color: #065F46;
            border-left-color: var(--success);
        }

        .alert-danger {
            background-color: var(--danger-light);
            color: #991B1B;
            border-left-color: var(--danger);
        }

        .alert [data-lucide] {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }

        /* Record Stats Badge - Removed for minimal design */

        /* Remove stat cards - keeping design minimal as requested */

        /* Skeleton Loading Styles */
        .skeleton {
            background: linear-gradient(
                90deg,
                #E5E7EB 0%,
                #F3F4F6 50%,
                #E5E7EB 100%
            );
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
            border-radius: var(--radius-sm);
            height: 20px;
            width: 100%;
        }

        @keyframes skeleton-loading {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        .skeleton-row {
            height: 60px;
            margin-bottom: 1px;
        }

        .skeleton-input {
            height: 42px;
            width: 100%;
        }

        .skeleton-text {
            height: 18px;
            width: 85%;
            margin: 0;
        }

        .skeleton-text.short {
            width: 60%;
        }

        .skeleton-text.medium {
            width: 80%;
        }

        .skeleton-text.long {
            width: 95%;
        }

        .table-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Skeleton in table cells */
        .records-table td .skeleton {
            height: 18px;
            border-radius: 4px;
        }

        /* Skeleton variations for different column widths */
        .records-table tr:nth-child(odd) td:nth-child(1) .skeleton {
            width: 70%;
        }

        .records-table tr:nth-child(even) td:nth-child(1) .skeleton {
            width: 80%;
        }

        .records-table tr:nth-child(odd) td:nth-child(2) .skeleton {
            width: 90%;
        }

        .records-table tr:nth-child(even) td:nth-child(2) .skeleton {
            width: 85%;
        }

        .records-table tr:nth-child(odd) td:nth-child(3) .skeleton {
            width: 75%;
        }

        .records-table tr:nth-child(even) td:nth-child(3) .skeleton {
            width: 65%;
        }

        .search-input.loading {
            background-image: linear-gradient(
                90deg,
                transparent 0%,
                rgba(59, 130, 246, 0.1) 50%,
                transparent 100%
            );
            background-size: 200% 100%;
            animation: input-loading 1.5s ease-in-out infinite;
        }

        @keyframes input-loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .loading-overlay {
            position: relative;
        }

        .loading-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .loading-overlay.loading::after {
            display: flex;
        }

        /* Responsive - Page Specific */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-form {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .records-table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/mobile_header.php'; ?>

    <?php include '../includes/sidebar_nav.php'; ?>

    <?php include '../includes/top_navbar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i data-lucide="<?php echo $config['icon']; ?>"></i>
                    <?php echo htmlspecialchars($config['title']); ?>
                </h1>
                <?php
                $create_permission = str_replace('_view', '_create', $required_permission);
                if (hasPermission($create_permission)):
                ?>
                <a href="<?php echo $config['entry_form']; ?>" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Add New Record
                </a>
                <?php endif; ?>
            </div>

            <!-- Alert Messages -->
            <div id="alertContainer"></div>

            <!-- Search & Filter Section -->
            <div class="search-section">
                <!-- Quick Search -->
                <form method="GET" action="" class="search-form" id="searchForm">
                    <div class="search-input-wrapper">
                        <input
                            type="text"
                            name="search"
                            id="liveSearchInput"
                            class="search-input"
                            placeholder="Search by registry number, names, date, or place..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="btn btn-primary" style="display: none;">
                        <i data-lucide="search"></i>
                        Search
                    </button>
                    <button type="button" class="filter-toggle-btn <?php echo $has_active_filters ? 'active' : ''; ?>" onclick="toggleFilters()">
                        <i data-lucide="sliders-horizontal"></i>
                        Filters
                        <?php if ($has_active_filters): ?>
                            <span class="filter-badge">Active</span>
                        <?php endif; ?>
                    </button>
                    <?php if (!empty($search) || $has_active_filters): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline">
                        <i data-lucide="x"></i>
                        Clear
                    </a>
                    <?php endif; ?>
                </form>

                <!-- Advanced Filters -->
                <div class="advanced-filters <?php echo $has_active_filters ? 'show' : ''; ?>" id="advancedFilters">
                    <form method="GET" action="" id="filterForm">
                        <!-- Preserve search query and type -->
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($record_type); ?>">

                        <div class="filter-grid">
                            <?php foreach ($config['filters'] as $filter): ?>
                            <div class="filter-group">
                                <label for="<?php echo $filter['name']; ?>"><?php echo htmlspecialchars($filter['label']); ?></label>
                                <input
                                    type="<?php echo $filter['type']; ?>"
                                    id="<?php echo $filter['name']; ?>"
                                    name="<?php echo $filter['name']; ?>"
                                    <?php if ($filter['type'] === 'text'): ?>
                                    placeholder="Enter <?php echo strtolower($filter['label']); ?>..."
                                    <?php endif; ?>
                                    value="<?php echo htmlspecialchars($_GET[$filter['name']] ?? ''); ?>"
                                    onchange="this.form.submit()"
                                >
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Records Table -->
            <div class="table-container">
                <?php if (count($records) > 0): ?>
                <!-- Table Controls -->
                <div class="table-controls">
                    <div class="table-controls-left">
                        <div class="per-page-selector">
                            <label for="perPageSelect">Show</label>
                            <select id="perPageSelect" onchange="changePerPage(this.value)">
                                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span>entries</span>
                        </div>
                    </div>
                    <div class="table-controls-right">
                        Showing <?php echo number_format(($offset + 1)); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> of <?php echo number_format($total_records); ?> records
                    </div>
                </div>

                <table class="records-table">
                    <thead>
                        <tr>
                            <?php foreach ($config['table_columns'] as $column): ?>
                            <?php if ($column['sortable']): ?>
                            <?php
                                $sort_field = $column['sort_field'] ?? $column['field'];
                                $is_active = $sort_by === $sort_field;
                            ?>
                            <th class="sortable <?php echo $is_active ? 'active' : ''; ?>">
                                <a href="<?php echo get_sort_url($sort_field); ?>">
                                    <?php echo htmlspecialchars($column['label']); ?>
                                    <i data-lucide="<?php echo get_sort_icon($sort_field); ?>" class="sort-icon"></i>
                                </a>
                            </th>
                            <?php else: ?>
                            <th><?php echo htmlspecialchars($column['label']); ?></th>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordsTableBody" style="opacity: 0;">
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <?php foreach ($config['table_columns'] as $column): ?>
                            <td><?php echo get_field_value($record, $column['field'], $column['type'] ?? 'text'); ?></td>
                            <?php endforeach; ?>
                            <td>
                                <div class="action-buttons">
                                    <!-- View Record Button - Opens Modal -->
                                    <button onclick="recordPreviewModal.open(<?php echo $record['id']; ?>, '<?php echo $record_type; ?>')"
                                            class="btn btn-success btn-sm"
                                            title="View Record">
                                        <i data-lucide="file-text"></i>
                                    </button>
                                    <?php
                                    $edit_permission = str_replace('_view', '_edit', $required_permission);
                                    $delete_permission = str_replace('_view', '_delete', $required_permission);
                                    ?>
                                    <?php if (hasPermission($edit_permission)): ?>
                                    <button onclick="editRecord(<?php echo $record['id']; ?>, '<?php echo $config['entry_form']; ?>', <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"
                                       class="btn btn-primary btn-sm"
                                       title="Edit">
                                        <i data-lucide="pen-line"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (hasPermission($delete_permission)): ?>
                                    <button onclick="deleteRecord(<?php echo $record['id']; ?>, <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="btn btn-danger btn-sm"
                                            title="Delete">
                                        <i data-lucide="x-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-records">
                    <i data-lucide="inbox" style="width: 48px; height: 48px; stroke: #adb5bd;"></i>
                    <p>No records found.</p>
                    <?php if (!empty($search) || $has_active_filters): ?>
                    <p>Try adjusting your search terms or filters.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // Ensure variables are integers for pagination math
                $current_page = (int)$current_page;
                $total_pages = (int)$total_pages;

                $base_query = build_query_string(['page']);
                $query_prefix = $base_query ? '&' . $base_query : '';
                ?>

                <!-- First Page Button -->
                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === 1 ? 'disabled' : ''; ?>"
                   title="First page">
                    <i data-lucide="chevrons-left"></i>
                </a>

                <!-- Previous Page Button -->
                <a href="?page=<?php echo max(1, $current_page - 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === 1 ? 'disabled' : ''; ?>"
                   title="Previous page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                // Show ellipsis at start if needed
                if ($start_page > 1):
                ?>
                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo (int)$current_page === 1 ? 'active' : ''; ?>"
                   <?php if ((int)$current_page === 1): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>>1</a>
                <?php if ($start_page > 2): ?>
                <span class="pagination-info">...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo (int)$i === (int)$current_page ? 'active' : ''; ?>"
                   <?php if ((int)$i === (int)$current_page): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>
                   title="Page <?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <!-- Show ellipsis at end if needed -->
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="pagination-info">...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo (int)$current_page === (int)$total_pages ? 'active' : ''; ?>"
                   <?php if ((int)$current_page === (int)$total_pages): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <!-- Next Page Button -->
                <a href="?page=<?php echo min($total_pages, $current_page + 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>"
                   title="Next page">
                    <i data-lucide="chevron-right"></i>
                </a>

                <!-- Last Page Button -->
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>"
                   title="Last page">
                    <i data-lucide="chevrons-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Initialize Lucide icons after DOM loads
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
                console.log('Lucide icons initialized');
            }

            // Notiflix is initialized via notiflix-config.js

            // Show skeleton loading on page load, then fade in real content
            initPageLoadSkeleton();
        });

        // Toggle advanced filters
        function toggleFilters() {
            const filters = document.getElementById('advancedFilters');
            const toggleBtn = document.querySelector('.filter-toggle-btn');
            filters.classList.toggle('show');

            // Store filter state in localStorage
            if (filters.classList.contains('show')) {
                localStorage.setItem('filtersExpanded', 'true');
            } else {
                localStorage.setItem('filtersExpanded', 'false');
            }
        }

        // Restore filter state on page load
        window.addEventListener('DOMContentLoaded', function() {
            const filtersExpanded = localStorage.getItem('filtersExpanded');
            const hasActiveFilters = <?php echo $has_active_filters ? 'true' : 'false'; ?>;

            if (filtersExpanded === 'true' || hasActiveFilters) {
                document.getElementById('advancedFilters').classList.add('show');
            }
        });

        // Clear all filters
        function clearFilters() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            let url = window.location.pathname;

            if (searchParam) {
                url += '?search=' + encodeURIComponent(searchParam);
            }

            window.location.href = url;
        }

        // Change records per page
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('page'); // Reset to page 1
            window.location.href = url.toString();
        }

        // Delete record function
        function deleteRecord(id, recordData = null) {
            const recordType = '<?php echo $record_type; ?>';
            const recordTitle = '<?php echo $config['title']; ?>';

            // Build dialog title based on record type
            const recordTypeLabels = {
                'birth': 'Birth Record',
                'marriage': 'Marriage Record',
                'death': 'Death Record',
                'marriage_license': 'Marriage License'
            };
            const recordLabel = recordTypeLabels[recordType] || 'Record';
            const dialogTitle = `Delete ${recordLabel}`;

            // Build message with structured details - using HTML for proper line breaks
            let message = `Are you sure you want to delete this record?<br><br>`;

            if (recordData) {
                const details = getRecordDetails(recordData, recordType);
                if (details) {
                    message += details;
                    message += `<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
                } else {
                    message = `Are you sure you want to delete this record?<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
                }
            } else {
                message = `Are you sure you want to delete this record?<br><br><span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;
            }

            // Check if Notiflix is available
            if (typeof Notiflix === 'undefined') {
                console.error('Notiflix is not loaded');
                if (confirm(message)) {
                    performDelete(id);
                }
                return;
            }

            Notiflix.Confirm.show(
                dialogTitle,
                message,
                'Cancel',
                'Delete Permanently',
                function okCb() {
                    // User cancelled
                    console.log('Delete cancelled by user');
                },
                function cancelCb() {
                    performDelete(id);
                },
                {
                    width: '500px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    titleMaxLength: 50,
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 600,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: '#EF4444',
                    buttonsFontSize: '15px',
                    buttonsMaxLength: 50,
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        // Perform the actual delete operation
        function performDelete(id) {
            // Show loading
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle('Deleting record...');
            }

            // Create FormData object (API expects POST form data, not JSON)
            const formData = new FormData();
            formData.append('record_id', id);
            formData.append('delete_type', 'soft');

            fetch('<?php echo $config['delete_api']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Loading.remove();
                }

                if (data.success) {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    } else {
                        alert(data.message);
                    }
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message);
                    } else {
                        alert('Error: ' + data.message);
                    }
                }
            })
            .catch(error => {
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Loading.remove();
                }
                console.error('Error:', error);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('An error occurred while deleting the record.');
                } else {
                    alert('An error occurred while deleting the record.');
                }
            });
        }

        // Edit record function with confirmation
        function editRecord(id, formUrl, recordData = null) {
            const recordType = '<?php echo $record_type; ?>';
            const recordTitle = '<?php echo $config['title']; ?>';

            // Remove 's' from the end for singular form
            const singularTitle = recordTitle.replace(/s$/, '');

            // Build dialog title based on record type
            const recordTypeLabels = {
                'birth': 'Birth Record',
                'marriage': 'Marriage Record',
                'death': 'Death Record',
                'marriage_license': 'Marriage License'
            };
            const recordLabel = recordTypeLabels[recordType] || 'Record';
            const dialogTitle = `Edit ${recordLabel}`;

            // Build message with structured details - using HTML for proper line breaks
            let message = `Are you sure you want to edit this record?<br><br>`;

            if (recordData) {
                const details = getRecordDetails(recordData, recordType);
                if (details) {
                    message += details;
                } else {
                    // Fallback if no details
                    message = `Are you sure you want to edit this ${singularTitle.toLowerCase()}?`;
                }
            } else {
                message = `Are you sure you want to edit this ${singularTitle.toLowerCase()}?`;
            }

            // Check if Notiflix is available
            if (typeof Notiflix === 'undefined' || !Notiflix.Confirm) {
                console.warn('Notiflix not loaded, using native confirm dialog');
                if (confirm(message)) {
                    window.location.href = formUrl + '?id=' + id;
                }
                return;
            }

            Notiflix.Confirm.show(
                dialogTitle,
                message,
                'Cancel',
                'Proceed to Edit',
                function okCb() {
                    // User cancelled
                    console.log('Edit cancelled by user');
                },
                function cancelCb() {
                    // User confirmed - navigate to edit page
                    window.location.href = formUrl + '?id=' + id;
                },
                {
                    width: '500px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    titleMaxLength: 50,
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 600,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: '#3B82F6',
                    buttonsFontSize: '15px',
                    buttonsMaxLength: 50,
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        // Get record details for confirmation dialogs
        function getRecordDetails(record, recordType) {
            let details = '';

            if (record.registry_no) {
                details += `<strong>Registry No:</strong> ${record.registry_no}<br>`;
            }

            switch(recordType) {
                case 'birth':
                    const childName = capitalizeNames([record.child_first_name, record.child_middle_name, record.child_last_name]);
                    if (childName) details += `<strong>Child:</strong> ${childName}<br>`;
                    if (record.child_date_of_birth) details += `<strong>Date of Birth:</strong> ${formatDateFull(record.child_date_of_birth)}`;
                    break;

                case 'marriage':
                    const husbandName = capitalizeNames([record.husband_first_name, record.husband_middle_name, record.husband_last_name]);
                    const wifeName = capitalizeNames([record.wife_first_name, record.wife_middle_name, record.wife_last_name]);
                    if (husbandName) details += `<strong>Husband:</strong> ${husbandName}<br>`;
                    if (wifeName) details += `<strong>Wife:</strong> ${wifeName}<br>`;
                    if (record.date_of_marriage) details += `<strong>Marriage Date:</strong> ${formatDateFull(record.date_of_marriage)}`;
                    break;

                case 'death':
                    const deceasedName = capitalizeNames([record.deceased_first_name, record.deceased_middle_name, record.deceased_last_name]);
                    if (deceasedName) details += `<strong>Deceased:</strong> ${deceasedName}<br>`;
                    if (record.date_of_death) details += `<strong>Date of Death:</strong> ${formatDateFull(record.date_of_death)}<br>`;
                    if (record.age) details += `<strong>Age:</strong> ${record.age}`;
                    break;

                case 'marriage_license':
                    const groomName = capitalizeNames([record.groom_first_name, record.groom_middle_name, record.groom_last_name]);
                    const brideName = capitalizeNames([record.bride_first_name, record.bride_middle_name, record.bride_last_name]);
                    if (groomName) details += `<strong>Groom:</strong> ${groomName}<br>`;
                    if (brideName) details += `<strong>Bride:</strong> ${brideName}<br>`;
                    if (record.date_of_application) details += `<strong>Application Date:</strong> ${formatDateFull(record.date_of_application)}`;
                    break;
            }

            return details.trim();
        }

        // Capitalize names properly
        function capitalizeNames(nameParts) {
            const filtered = nameParts.filter(n => n && n.trim());
            if (filtered.length === 0) return '';

            return filtered.map(name => {
                return name.split(' ').map(word => {
                    return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                }).join(' ');
            }).join(' ');
        }

        // Format date for display (full month name)
        function formatDateFull(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                          'July', 'August', 'September', 'October', 'November', 'December'];
            return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }

        // Show alert function - Using Notiflix
        function showAlert(type, message) {
            switch(type) {
                case 'success':
                    Notiflix.Notify.success(message);
                    break;
                case 'danger':
                case 'error':
                    Notiflix.Notify.failure(message);
                    break;
                case 'warning':
                    Notiflix.Notify.warning(message);
                    break;
                case 'info':
                    Notiflix.Notify.info(message);
                    break;
                default:
                    Notiflix.Notify.info(message);
            }
        }

        // Re-initialize icons after page fully loads (backup)
        window.addEventListener('load', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
                console.log('Lucide icons re-initialized on window load');
            }
        });

        // Live Search Functionality
        const liveSearchInput = document.getElementById('liveSearchInput');
        const tableContainer = document.querySelector('.table-container');
        const recordsTable = document.querySelector('.records-table tbody');
        const tableControls = document.querySelector('.table-controls-right');
        let searchTimeout;
        let currentPage = 1;
        const recordType = '<?php echo $record_type; ?>';

        // Initialize page load skeleton
        function initPageLoadSkeleton() {
            const tbody = document.getElementById('recordsTableBody');
            if (!tbody) return;

            // Check if table has actual data
            const hasData = tbody.children.length > 0;

            if (hasData) {
                // Show skeleton briefly, then fade in real content
                const columns = <?php echo json_encode($config['table_columns']); ?>;
                const numColumns = columns.length + 1;

                // Create skeleton rows
                let skeletonHTML = '';
                for (let i = 0; i < Math.min(<?php echo $records_per_page; ?>, 10); i++) {
                    skeletonHTML += '<tr class="skeleton-loading-row">';
                    for (let j = 0; j < numColumns; j++) {
                        skeletonHTML += '<td><div class="skeleton skeleton-text"></div></td>';
                    }
                    skeletonHTML += '</tr>';
                }

                // Store real content
                const realContent = tbody.innerHTML;

                // Show skeleton
                tbody.innerHTML = skeletonHTML;
                tbody.style.opacity = '1';

                // After a short delay, fade in real content
                setTimeout(() => {
                    tbody.innerHTML = realContent;

                    // Re-initialize Lucide icons for the real content
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }, 400);
            } else {
                // No data, just show the tbody
                tbody.style.opacity = '1';
            }
        }

        // Debounce function - wait for user to stop typing
        function debounce(func, delay) {
            return function(...args) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => func.apply(this, args), delay);
            };
        }

        // Show skeleton loading in table
        function showSkeletonLoading() {
            if (!recordsTable) return;

            const columns = <?php echo json_encode($config['table_columns']); ?>;
            const numColumns = columns.length + 1; // +1 for Actions column
            const numRows = <?php echo $records_per_page; ?>;

            let skeletonHTML = '';
            for (let i = 0; i < Math.min(numRows, 10); i++) {
                skeletonHTML += '<tr>';
                for (let j = 0; j < numColumns; j++) {
                    skeletonHTML += '<td><div class="skeleton skeleton-text"></div></td>';
                }
                skeletonHTML += '</tr>';
            }

            recordsTable.innerHTML = skeletonHTML;
        }

        // Perform live search
        async function performLiveSearch(query) {
            // Add loading state
            liveSearchInput.classList.add('loading');

            // Show skeleton loading
            showSkeletonLoading();

            try {
                const url = `../api/records_search.php?type=${recordType}&search=${encodeURIComponent(query)}&page=${currentPage}&per_page=<?php echo $records_per_page; ?>`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    updateTable(data.records, data.pagination);
                } else {
                    console.error('Search error:', data.error);
                }
            } catch (error) {
                console.error('Live search failed:', error);
            } finally {
                // Remove loading state
                liveSearchInput.classList.remove('loading');
            }
        }

        // Update table with new results
        function updateTable(records, pagination) {
            if (!recordsTable) return;

            // Update table body
            if (records.length === 0) {
                recordsTable.innerHTML = `
                    <tr>
                        <td colspan="100%" style="text-align: center; padding: 60px;">
                            <div class="no-records">
                                <i data-lucide="inbox" style="width: 48px; height: 48px; stroke: #adb5bd;"></i>
                                <p>No records found.</p>
                                <p>Try adjusting your search terms.</p>
                            </div>
                        </td>
                    </tr>
                `;
            } else {
                let html = '';
                records.forEach(record => {
                    html += buildTableRow(record);
                });
                recordsTable.innerHTML = html;
            }

            // Update table controls
            if (tableControls && pagination) {
                tableControls.textContent = `Showing ${pagination.from} to ${pagination.to} of ${pagination.total_records} records`;
            }

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Build table row HTML based on record type
        function buildTableRow(record) {
            const columns = <?php echo json_encode($config['table_columns']); ?>;
            let html = '<tr>';

            // Build cells for each column
            columns.forEach(column => {
                const value = getFieldValue(record, column.field, column.type || 'text');
                html += `<td>${value}</td>`;
            });

            // Actions column
            html += '<td><div class="action-buttons">';

            // View Record Button - Opens Modal
            html += `<button onclick="recordPreviewModal.open(${record.id}, '${recordType}')" class="btn btn-success btn-sm" title="View Record">
                <i data-lucide="file-text"></i>
            </button>`;

            <?php
            $edit_permission = str_replace('_view', '_edit', $required_permission);
            $delete_permission = str_replace('_view', '_delete', $required_permission);
            ?>

            <?php if (hasPermission($edit_permission)): ?>
            const recordDataEdit = JSON.stringify(record).replace(/"/g, '&quot;');
            html += `<button onclick='editRecord(${record.id}, "<?php echo $config['entry_form']; ?>", JSON.parse("${recordDataEdit}"))' class="btn btn-primary btn-sm" title="Edit">
                <i data-lucide="pen-line"></i>
            </button>`;
            <?php endif; ?>

            <?php if (hasPermission($delete_permission)): ?>
            const recordDataDelete = JSON.stringify(record).replace(/"/g, '&quot;');
            html += `<button onclick='deleteRecord(${record.id}, JSON.parse("${recordDataDelete}"))' class="btn btn-danger btn-sm" title="Delete">
                <i data-lucide="x-circle"></i>
            </button>`;
            <?php endif; ?>

            html += '</div></td>';
            html += '</tr>';

            return html;
        }

        // Get field value (similar to PHP function)
        function getFieldValue(record, field, type) {
            const recordType = '<?php echo $record_type; ?>';

            // Handle composite name fields
            if (field === 'husband_name' && recordType === 'marriage') {
                return buildFullName(record.husband_first_name, record.husband_middle_name, record.husband_last_name);
            } else if (field === 'wife_name' && recordType === 'marriage') {
                return buildFullName(record.wife_first_name, record.wife_middle_name, record.wife_last_name);
            } else if (field === 'child_name' && recordType === 'birth') {
                return buildFullName(record.child_first_name, record.child_middle_name, record.child_last_name);
            } else if (field === 'deceased_name' && recordType === 'death') {
                return buildFullName(record.deceased_first_name, record.deceased_middle_name, record.deceased_last_name);
            } else if (field === 'father_name') {
                return buildFullName(record.father_first_name, record.father_middle_name, record.father_last_name);
            } else if (field === 'mother_name') {
                return buildFullName(record.mother_first_name, record.mother_middle_name, record.mother_last_name);
            } else if (type === 'date' && record[field]) {
                return formatDate(record[field]);
            } else {
                return escapeHtml(record[field] || 'N/A');
            }
        }

        // Build full name from parts
        function buildFullName(first, middle, last) {
            const parts = [first, middle, last].filter(p => p && p.trim());
            return escapeHtml(parts.length > 0 ? parts.join(' ') : 'N/A');
        }

        // Format date
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Attach live search to input
        if (liveSearchInput) {
            liveSearchInput.addEventListener('input', debounce(function(e) {
                const query = e.target.value.trim();

                // Only search if query is at least 1 character or empty (to reset)
                if (query.length >= 1 || query.length === 0) {
                    performLiveSearch(query);
                }
            }, 300)); // 300ms delay after user stops typing
        }
    </script>

    <!-- Record Preview Modal Script -->
    <script src="../assets/js/record-preview-modal.js"></script>
</body>
</html>
