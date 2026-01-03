<?php
/**
 * Civil Registry Records Viewer - View, Search, Edit, Delete
 * Supports: Marriage Certificates, Birth Certificates, Death Certificates, Marriage Licence Applications
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Optional: Check authentication
// require_once '../includes/auth.php';
// if (!isLoggedIn()) {
//     header('Location: ../login.php');
//     exit;
// }

// Determine record type - defaults to 'marriage' if not already set
if (!isset($record_type)) {
    $record_type = 'marriage';
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
            ['label' => 'Child Name', 'field' => 'child_name', 'sortable' => true, 'sort_field' => 'child_first_name'],
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
            ['label' => 'Deceased Name', 'field' => 'deceased_name', 'sortable' => true, 'sort_field' => 'deceased_first_name'],
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
    foreach ($config['search_fields'] as $field) {
        $search_conditions[] = "{$field} LIKE :search";
    }
    $search_query = " AND (" . implode(' OR ', $search_conditions) . ")";
    $params[':search'] = "%{$search}%";
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
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch records
$sql = "SELECT * FROM {$config['table']} WHERE status = 'Active'"
    . $search_query
    . $filter_query
    . " ORDER BY {$sort_by} {$sort_order} LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

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
        $last = $record['husband_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
    } elseif ($field === 'wife_name' && $record_type === 'marriage') {
        $first = $record['wife_first_name'] ?? '';
        $last = $record['wife_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
    } elseif ($field === 'child_name' && $record_type === 'birth') {
        $first = $record['child_first_name'] ?? '';
        $last = $record['child_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
    } elseif ($field === 'deceased_name' && $record_type === 'death') {
        $first = $record['deceased_first_name'] ?? '';
        $last = $record['deceased_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
    } elseif ($field === 'father_name') {
        $first = $record['father_first_name'] ?? '';
        $last = $record['father_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
    } elseif ($field === 'mother_name') {
        $first = $record['mother_first_name'] ?? '';
        $last = $record['mother_last_name'] ?? '';
        return htmlspecialchars(trim($first . ' ' . $last)) ?: 'N/A';
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

    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #1a1a1a;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #051f3a;
            --sidebar-item-hover: rgba(59, 130, 246, 0.1);
            --sidebar-item-active: rgba(59, 130, 246, 0.2);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-color: #3b82f6;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background: var(--sidebar-bg);
            color: var(--text-primary);
            padding: 16px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mobile-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        #mobileSidebarToggle {
            background: none;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
        }

        /* Sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--text-primary);
            z-index: 1000;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            transition: width 0.3s;
            overflow: hidden;
        }

        .sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
            min-height: 64px;
        }

        .sidebar-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .sidebar-header h4 [data-lucide] {
            min-width: 28px;
            color: var(--accent-color);
        }

        .sidebar-collapsed .sidebar-header h4 span {
            display: none;
        }

        .sidebar-menu {
            list-style: none;
            padding: 12px 0;
            margin: 0;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.3) transparent;
        }

        /* Custom scrollbar for webkit browsers */
        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.3);
            border-radius: 3px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background-color: rgba(148, 163, 184, 0.5);
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 2px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
        }

        .sidebar-menu li a:hover {
            background: var(--sidebar-item-hover);
            color: var(--text-primary);
            transform: translateX(3px);
        }

        .sidebar-menu li a.active {
            background: var(--sidebar-item-active);
            color: #b7ff9a;
            font-weight: 600;
        }

        .sidebar-menu li a.active::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 22px;
            background: var(--accent-color);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-menu li a [data-lucide] {
            min-width: 28px;
        }

        .sidebar-collapsed .sidebar-menu li a {
            justify-content: center;
            padding: 14px 10px;
        }

        .sidebar-collapsed .sidebar-menu li a span {
            display: none;
        }

        .sidebar-divider {
            border-top: 1px solid rgba(148, 163, 184, 0.15);
            margin: 12px 16px;
        }

        .sidebar-heading {
            padding: 14px 20px 8px;
            font-size: 10.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }

        .sidebar-collapsed .sidebar-heading {
            text-indent: -9999px;
        }

        /* Top Navbar */
        .top-navbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 64px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            z-index: 100;
            transition: left 0.3s;
        }

        .sidebar-collapsed .top-navbar {
            left: var(--sidebar-collapsed-width);
        }

        #sidebarCollapse {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #374151;
            cursor: pointer;
            padding: 10px;
            margin-left: 20px;
            border-radius: 8px;
        }

        #sidebarCollapse:hover {
            background: #f3f4f6;
            color: var(--accent-color);
        }

        .top-navbar-info {
            margin-left: 16px;
        }

        .welcome-text {
            color: #6b7280;
            font-size: 13.5px;
            font-weight: 500;
        }

        /* User Profile Dropdown */
        .user-profile-dropdown {
            margin-left: auto;
            margin-right: 20px;
            position: relative;
        }

        .user-profile-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px 6px 6px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-profile-btn:hover {
            background: #f9fafb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .user-profile-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #111827;
        }

        .user-role {
            font-size: 11.5px;
            color: #6b7280;
        }

        .dropdown-arrow {
            color: #9ca3af;
        }

        /* Main Content */
        .content {
            margin-left: var(--sidebar-width);
            padding-top: 64px;
            min-height: 100vh;
            background: #f8f9fa;
            transition: margin-left 0.3s;
        }

        .sidebar-collapsed .content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .page-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }

        .page-title [data-lucide] {
            color: #3b82f6;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background-color: #10b981;
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background-color: #f59e0b;
            color: #ffffff;
            border: 1px solid #d97706;
        }

        .btn-warning:hover {
            background-color: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background-color: #ef4444;
            color: #ffffff;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #6b7280;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #374151;
        }

        /* Search & Filter */
        .search-section {
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }

        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            align-items: center;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.9375rem;
            background-color: #f9fafb;
            transition: all 0.2s ease-in-out;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .search-input-wrapper::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }

        .filter-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4b5563;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .filter-toggle-btn:hover {
            background: #f9fafb;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .filter-toggle-btn.active {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .advanced-filters {
            display: none;
            padding-top: 20px;
            border-top: 2px solid #f3f4f6;
            margin-top: 16px;
        }

        .advanced-filters.show {
            display: block;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #374151;
            letter-spacing: 0.01em;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            background-color: #ffffff;
            transition: all 0.2s ease-in-out;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #3b82f6;
            color: #ffffff;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        /* Table */
        .table-container {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table thead {
            background: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .records-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.8125rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-bottom: 2px solid #e5e7eb;
        }

        .records-table th.sortable {
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease-in-out;
        }

        .records-table th.sortable:hover {
            background-color: #f3f4f6;
            color: #3b82f6;
        }

        .records-table th.sortable a {
            display: flex;
            align-items: center;
            gap: 6px;
            color: inherit;
            text-decoration: none;
        }

        .records-table th.sortable.active {
            background-color: #eff6ff;
            color: #3b82f6;
        }

        .sort-icon {
            opacity: 0.3;
            transition: opacity 0.2s;
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
            padding: 16px 20px;
            background: #f9fafb;
            border-bottom: 2px solid #f3f4f6;
        }

        .table-controls-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table-controls-right {
            color: #6b7280;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8125rem;
            color: #4b5563;
            font-weight: 500;
        }

        .per-page-selector select {
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.8125rem;
            cursor: pointer;
            background-color: #ffffff;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .per-page-selector select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .records-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            color: #374151;
        }

        .records-table tbody tr {
            transition: all 0.15s ease-in-out;
        }

        .records-table tbody tr:hover {
            background-color: #f9fafb;
            box-shadow: inset 0 0 0 1px #e5e7eb;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 24px;
            background: #ffffff;
            border-radius: 12px;
            margin-top: 24px;
            border: 1px solid #e5e7eb;
        }

        .pagination-btn {
            min-width: 40px;
            height: 40px;
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            background: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
        }

        .pagination-btn:hover:not(:disabled):not(.active) {
            background: #f9fafb;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .no-records {
            text-align: center;
            padding: 64px 20px;
            color: #9ca3af;
        }

        .no-records i {
            margin-bottom: 16px;
        }

        .no-records p {
            margin: 8px 0;
            font-size: 0.9375rem;
        }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid transparent;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #ef4444;
        }

        .alert [data-lucide] {
            flex-shrink: 0;
        }

        /* Record Stats Badge */
        .record-stats {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e40af;
        }

        .record-stats [data-lucide] {
            width: 16px;
            height: 16px;
        }

        /* Quick Stats Row */
        .quick-stats {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .stat-card {
            flex: 1;
            min-width: 200px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-icon.green {
            background: #d1fae5;
            color: #10b981;
        }

        .stat-icon.purple {
            background: #f3e8ff;
            color: #a855f7;
        }

        .stat-content h4 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px 0;
        }

        .stat-content p {
            font-size: 0.8125rem;
            color: #6b7280;
            margin: 0;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-header {
                display: block;
            }

            .top-navbar {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                padding-top: 70px;
            }

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
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h4><i data-lucide="file-badge"></i> Civil Registry</h4>
            <button type="button" id="mobileSidebarToggle">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i data-lucide="file-badge"></i> <span>Civil Registry</span></h4>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-heading">Overview</li>
            <li>
                <a href="../admin/dashboard.php" title="Dashboard">
                    <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">Certificates</li>
            <li>
                <a href="certificate_of_live_birth.php" title="Birth Certificates">
                    <i data-lucide="baby"></i> <span>Birth Certificates</span>
                </a>
            </li>
            <li>
                <a href="certificate_of_marriage.php" title="Marriage Certificates">
                    <i data-lucide="heart"></i> <span>Marriage Certificates</span>
                </a>
            </li>
            <li>
                <a href="#" title="Death Certificates">
                    <i data-lucide="cross"></i> <span>Death Certificates</span>
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">Management</li>
            <li>
                <a href="birth_records.php" class="<?php echo $record_type === 'birth' ? 'active' : ''; ?>" title="Birth Records">
                    <i data-lucide="baby"></i> <span>Birth Records</span>
                </a>
            </li>
            <li>
                <a href="marriage_records.php" class="<?php echo $record_type === 'marriage' ? 'active' : ''; ?>" title="Marriage Records">
                    <i data-lucide="heart"></i> <span>Marriage Records</span>
                </a>
            </li>
            <li>
                <a href="#" title="Reports">
                    <i data-lucide="bar-chart-3"></i> <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="#" title="Archives">
                    <i data-lucide="archive"></i> <span>Archives</span>
                </a>
            </li>

            <li class="sidebar-divider"></li>
            <li class="sidebar-heading">System</li>
            <li>
                <a href="#" title="Users">
                    <i data-lucide="users"></i> <span>Users</span>
                </a>
            </li>
            <li>
                <a href="#" title="Settings">
                    <i data-lucide="settings"></i> <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Top Navbar -->
    <div class="top-navbar" id="topNavbar">
        <button type="button" id="sidebarCollapse" title="Toggle Sidebar">
            <i data-lucide="menu"></i>
        </button>
        <div class="top-navbar-info">
            <span class="welcome-text">Welcome, Admin User</span>
        </div>

        <div class="user-profile-dropdown">
            <button class="user-profile-btn" type="button">
                <div class="user-avatar">AU</div>
                <div class="user-profile-info">
                    <span class="user-name">Admin User</span>
                    <span class="user-role">Administrator</span>
                </div>
                <i data-lucide="chevron-down" class="dropdown-arrow"></i>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i data-lucide="<?php echo $config['icon']; ?>"></i>
                    <?php echo htmlspecialchars($config['title']); ?>
                </h1>
                <a href="<?php echo $config['entry_form']; ?>" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Add New Record
                </a>
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
                            class="search-input"
                            placeholder="Search by registry number, names, date, or place..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    <button type="submit" class="btn btn-primary">
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
                                >
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="filter-actions">
                            <button type="button" class="btn btn-warning" onclick="clearFilters()">
                                <i data-lucide="x"></i>
                                Clear Filters
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="check"></i>
                                Apply Filters
                            </button>
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
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <?php foreach ($config['table_columns'] as $column): ?>
                            <td><?php echo get_field_value($record, $column['field'], $column['type'] ?? 'text'); ?></td>
                            <?php endforeach; ?>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!empty($record['pdf_filename'])): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($record['pdf_filename']); ?>"
                                       target="_blank"
                                       class="btn btn-success btn-sm"
                                       title="View PDF">
                                        <i data-lucide="file-text"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?php echo $config['entry_form']; ?>?id=<?php echo $record['id']; ?>"
                                       class="btn btn-primary btn-sm"
                                       title="Edit">
                                        <i data-lucide="edit"></i>
                                    </a>
                                    <button onclick="deleteRecord(<?php echo $record['id']; ?>)"
                                            class="btn btn-danger btn-sm"
                                            title="Delete">
                                        <i data-lucide="trash-2"></i>
                                    </button>
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
                $base_query = build_query_string(['page']);
                $query_prefix = $base_query ? '&' . $base_query : '';
                ?>
                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === 1 ? 'disabled' : ''; ?>">
                    <i data-lucide="chevrons-left"></i>
                </a>
                <a href="?page=<?php echo max(1, $current_page - 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === 1 ? 'disabled' : ''; ?>">
                    <i data-lucide="chevron-left"></i>
                </a>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <a href="?page=<?php echo min($total_pages, $current_page + 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>">
                    <i data-lucide="chevron-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>">
                    <i data-lucide="chevrons-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const body = document.body;

        if (sidebarCollapse) {
            sidebarCollapse.addEventListener('click', function() {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            });
        }

        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Restore sidebar state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed && window.innerWidth > 768) {
            body.classList.add('sidebar-collapsed');
        }

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
        function deleteRecord(id) {
            const recordType = '<?php echo $record_type; ?>';
            const recordTitle = '<?php echo $config['title']; ?>';

            if (!confirm(`Are you sure you want to delete this record? This action cannot be undone.`)) {
                return;
            }

            fetch('<?php echo $config['delete_api']; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while deleting the record.');
            });
        }

        // Show alert function
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;

            const icon = type === 'success' ? 'check-circle' : 'alert-circle';

            alertDiv.innerHTML = `
                <i data-lucide="${icon}"></i>
                <span>${message}</span>
            `;

            alertContainer.innerHTML = '';
            alertContainer.appendChild(alertDiv);

            lucide.createIcons();

            window.scrollTo({ top: 0, behavior: 'smooth' });

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>
