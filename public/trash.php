<?php
/**
 * Trash - View, Restore, and Permanently Delete soft-deleted records
 * Aggregates soft-deleted records (status='Deleted') from all 4 record tables:
 *  - certificate_of_live_birth
 *  - certificate_of_marriage
 *  - certificate_of_death
 *  - application_for_marriage_license
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

// User must have at least one *_delete permission to access the trash
$delete_permissions = ['birth_delete', 'marriage_delete', 'death_delete', 'marriage_license_delete'];
if (!hasAnyPermission($delete_permissions)) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

// Map of record type => table config
$type_configs = [
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'label' => 'Birth Record',
        'icon' => 'baby',
        'permission' => 'birth_delete',
        'name_fields' => ['child_first_name', 'child_middle_name', 'child_last_name'],
        'name_label' => 'Child',
        'date_field' => 'child_date_of_birth',
        'date_label' => 'Date of Birth',
    ],
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'label' => 'Marriage Record',
        'icon' => 'heart',
        'permission' => 'marriage_delete',
        'name_fields' => ['husband_first_name', 'husband_middle_name', 'husband_last_name'],
        'secondary_fields' => ['wife_first_name', 'wife_middle_name', 'wife_last_name'],
        'name_label' => 'Husband & Wife',
        'date_field' => 'date_of_marriage',
        'date_label' => 'Date of Marriage',
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'label' => 'Death Record',
        'icon' => 'user-x',
        'permission' => 'death_delete',
        'name_fields' => ['deceased_first_name', 'deceased_middle_name', 'deceased_last_name'],
        'name_label' => 'Deceased',
        'date_field' => 'date_of_death',
        'date_label' => 'Date of Death',
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'label' => 'Marriage License',
        'icon' => 'file-heart',
        'permission' => 'marriage_license_delete',
        'name_fields' => ['groom_first_name', 'groom_middle_name', 'groom_last_name'],
        'secondary_fields' => ['bride_first_name', 'bride_middle_name', 'bride_last_name'],
        'name_label' => 'Groom & Bride',
        'date_field' => 'date_of_application',
        'date_label' => 'Application Date',
    ],
];

// Filter: record type
$filter_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
if (!empty($filter_type) && !isset($type_configs[$filter_type])) {
    $filter_type = '';
}

// Search query
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Pagination settings
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($records_per_page < 5 || $records_per_page > 100) {
    $records_per_page = 10;
}
$pagination_current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagination_current_page - 1) * $records_per_page;

// Sorting
$allowed_sort = ['deleted_at', 'registry_no', 'record_type'];
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort, true)
    ? $_GET['sort_by']
    : 'deleted_at';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC' ? 'ASC' : 'DESC';

/**
 * Build a SELECT query for one table normalized to a common shape.
 * Returns [sql, params] — uses unique param names per subquery.
 */
function build_type_select($record_type, $config, $search, $search_prefix) {
    $table = $config['table'];
    $name_fields = $config['name_fields'];
    $secondary_fields = $config['secondary_fields'] ?? null;
    $date_field = $config['date_field'];

    // Build display name expression
    $name_concat = "CONCAT_WS(' ', "
        . implode(', ', array_map(fn($f) => "COALESCE(NULLIF(TRIM(`$f`), ''), '')", $name_fields))
        . ")";
    if ($secondary_fields) {
        $sec_concat = "CONCAT_WS(' ', "
            . implode(', ', array_map(fn($f) => "COALESCE(NULLIF(TRIM(`$f`), ''), '')", $secondary_fields))
            . ")";
        $display_name = "CONCAT($name_concat, ' & ', $sec_concat)";
    } else {
        $display_name = $name_concat;
    }

    $sql = "SELECT
                id,
                '$record_type' AS record_type,
                registry_no,
                TRIM($display_name) AS display_name,
                `$date_field` AS record_date,
                COALESCE(updated_at, created_at) AS deleted_at,
                created_at
            FROM `$table`
            WHERE status = 'Deleted'";

    $params = [];

    if (!empty($search)) {
        $search_param = ":{$search_prefix}_search";
        $sql .= " AND (registry_no LIKE $search_param";
        foreach ($name_fields as $f) {
            $sql .= " OR `$f` LIKE $search_param";
        }
        if ($secondary_fields) {
            foreach ($secondary_fields as $f) {
                $sql .= " OR `$f` LIKE $search_param";
            }
        }
        $sql .= ")";
        $params[$search_param] = "%{$search}%";
    }

    return [$sql, $params];
}

// Build UNION across allowed record types (respecting permission + filter)
$union_parts = [];
$all_params = [];
$accessible_types = [];

foreach ($type_configs as $type_key => $config) {
    // Skip if user lacks delete permission for this type
    if (!hasPermission($config['permission'])) {
        continue;
    }
    $accessible_types[$type_key] = $config;

    // Skip if a specific filter is set and doesn't match
    if (!empty($filter_type) && $filter_type !== $type_key) {
        continue;
    }

    list($sql, $params) = build_type_select($type_key, $config, $search, $type_key);
    $union_parts[] = "($sql)";
    $all_params = array_merge($all_params, $params);
}

if (empty($union_parts)) {
    $records = [];
    $total_records = 0;
    $total_pages = 0;
} else {
    $union_sql = implode(' UNION ALL ', $union_parts);

    // Count total
    $count_sql = "SELECT COUNT(*) AS total FROM ($union_sql) AS trash_union";
    try {
        $stmt = $pdo->prepare($count_sql);
        foreach ($all_params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $total_records = (int)($stmt->fetch()['total'] ?? 0);
        $total_pages = (int)ceil($total_records / $records_per_page);
    } catch (PDOException $e) {
        error_log("Trash count error: " . $e->getMessage());
        $total_records = 0;
        $total_pages = 0;
    }

    // Fetch paginated results
    $order_by = $sort_by === 'deleted_at' ? 'deleted_at' : $sort_by;
    $fetch_sql = "SELECT * FROM ($union_sql) AS trash_union
                  ORDER BY $order_by $sort_order
                  LIMIT :limit OFFSET :offset";
    try {
        $stmt = $pdo->prepare($fetch_sql);
        foreach ($all_params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Trash fetch error: " . $e->getMessage());
        $records = [];
    }
}

// Helper: query-string builder
function build_query_string($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $k) unset($params[$k]);
    return http_build_query($params);
}

// Helper: sort URL
function get_sort_url($column) {
    global $sort_by, $sort_order;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $query = build_query_string(['sort_by', 'sort_order', 'page']);
    return '?sort_by=' . $column . '&sort_order=' . $new_order . ($query ? '&' . $query : '');
}

// Helper: sort icon
function get_sort_icon($column) {
    global $sort_by, $sort_order;
    if ($sort_by !== $column) return 'chevrons-up-down';
    return $sort_order === 'ASC' ? 'chevron-up' : 'chevron-down';
}

// Format date helper
function fmt_date($val) {
    if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return 'N/A';
    $ts = strtotime($val);
    return $ts ? date('M d, Y', $ts) : 'N/A';
}

function fmt_datetime($val) {
    if (empty($val) || $val === '0000-00-00 00:00:00') return 'N/A';
    $ts = strtotime($val);
    return $ts ? date('M d, Y g:i A', $ts) : 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash - Civil Registry</title>

    <!-- Google Fonts -->
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">

    <!-- Lucide Icons -->
    <script src="<?= asset_url('lucide') ?>"></script>

    <!-- Notiflix -->
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../assets/js/notiflix-config.js"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        /* ========================================
           CORPORATE MODERN DESIGN (matches records_viewer.php)
           ======================================== */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #F8FAFC;
            color: #1E293B;
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: -0.011em;
        }

        :root {
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8FAFC;
            --bg-tertiary: #F1F5F9;
            --bg-accent: #EFF6FF;

            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --text-muted: #CBD5E1;

            --border-light: #F1F5F9;
            --border-medium: #E2E8F0;
            --border-strong: #CBD5E1;

            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --primary-light: #DBEAFE;
            --primary-lighter: #EFF6FF;

            --success: #059669;
            --success-hover: #047857;
            --success-light: #D1FAE5;

            --warning: #D97706;
            --warning-hover: #B45309;
            --warning-light: #FEF3C7;

            --danger: #DC2626;
            --danger-hover: #B91C1C;
            --danger-light: #FEE2E2;

            --spacing-xs: 6px;
            --spacing-sm: 12px;
            --spacing-md: 20px;
            --spacing-lg: 32px;
            --spacing-xl: 48px;
            --spacing-2xl: 64px;

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow-md: 0 2px 8px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 8px 24px 0 rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 16px 40px 0 rgba(0, 0, 0, 0.1);
        }

        .page-container {
            padding: 24px var(--spacing-lg) var(--spacing-lg);
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-md);
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-light);
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 16px;
            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        .page-title [data-lucide] {
            color: var(--danger);
            width: 26px;
            height: 26px;
            stroke-width: 2;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: -0.01em;
            box-shadow: var(--shadow-sm);
        }

        .btn [data-lucide] {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
        }

        .btn-primary { background-color: var(--primary); color: #FFFFFF; }
        .btn-primary:hover { background-color: var(--primary-hover); box-shadow: var(--shadow-md); transform: translateY(-1px); }

        .btn-success { background-color: var(--success); color: #FFFFFF; }
        .btn-success:hover { background-color: var(--success-hover); box-shadow: var(--shadow-md); transform: translateY(-1px); }

        .btn-danger { background-color: var(--danger); color: #FFFFFF; }
        .btn-danger:hover { background-color: var(--danger-hover); box-shadow: var(--shadow-md); transform: translateY(-1px); }

        .btn-outline {
            background: var(--bg-primary);
            border: 1.5px solid var(--border-medium);
            color: var(--text-secondary);
            box-shadow: none;
        }
        .btn-outline:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 14px;
            gap: 6px;
        }
        .btn-sm [data-lucide] {
            width: 16px;
            height: 16px;
        }

        /* Search & Filter Section */
        .search-section {
            margin-bottom: 16px;
            background: var(--bg-primary);
            padding: 16px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }

        .search-form {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: 0;
            align-items: stretch;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px var(--spacing-md) 10px 48px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-md);
            font-size: 15px;
            background-color: var(--bg-secondary);
            transition: all 0.2s ease;
            font-family: inherit;
            font-weight: 400;
            color: var(--text-primary);
        }

        .search-input::placeholder { color: var(--text-tertiary); font-weight: 400; }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        .search-input-wrapper::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }

        .type-filter-select {
            padding: 10px 40px 10px 16px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            transition: all 0.2s ease;
            min-width: 200px;
        }

        .type-filter-select:hover {
            border-color: var(--primary);
        }

        .type-filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        /* Table */
        .table-container {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .records-table thead {
            background: var(--bg-secondary);
        }

        .records-table th {
            padding: 10px 6px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 11px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-medium);
            white-space: normal;
            word-wrap: break-word;
        }

        .records-table th.sortable {
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }

        .records-table th.sortable:hover {
            background-color: var(--bg-tertiary);
            color: var(--primary);
        }

        .records-table th.sortable a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: inherit;
            text-decoration: none;
        }

        .records-table th.sortable.active {
            background-color: var(--primary-lighter);
            color: var(--primary);
        }

        .sort-icon {
            opacity: 0.3;
            transition: all 0.2s ease;
            width: 16px;
            height: 16px;
        }

        .records-table th.sortable:hover .sort-icon { opacity: 0.6; }
        .records-table th.sortable.active .sort-icon { opacity: 1; }

        .records-table th.row-number-header,
        .records-table td.row-number {
            width: 50px;
            text-align: center;
            font-weight: 700;
            border-right: 2px solid var(--border-medium);
            background: var(--bg-secondary);
        }

        .records-table th.row-number-header { font-size: 12px; letter-spacing: 0.08em; }
        .records-table td.row-number { color: var(--text-tertiary); font-size: 14px; font-weight: 600; }

        .records-table tbody tr:hover td.row-number {
            background: var(--primary-lighter);
            color: var(--primary);
        }

        .records-table td {
            padding: 10px 6px;
            border-bottom: 1px solid var(--border-light);
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.4;
            font-weight: 400;
            white-space: normal;
            word-wrap: break-word;
            max-width: 180px;
        }

        .records-table tbody tr { transition: all 0.2s ease; }
        .records-table tbody tr:nth-child(even) { background-color: var(--bg-secondary); }
        .records-table tbody tr:nth-child(odd) { background-color: var(--bg-primary); }
        .records-table tbody tr:hover { background-color: var(--bg-accent); }
        .records-table tbody tr:last-child td { border-bottom: none; }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-light);
        }

        .table-controls-left { display: flex; align-items: center; gap: 20px; }
        .table-controls-right { color: var(--text-secondary); font-size: 14px; font-weight: 500; }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .per-page-selector label { font-weight: 600; }

        .per-page-selector select {
            padding: 8px 14px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-sm);
            font-size: 14px;
            cursor: pointer;
            background-color: var(--bg-primary);
            font-weight: 600;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .per-page-selector select:hover { border-color: var(--primary); }
        .per-page-selector select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        /* Type badge in trash table */
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .type-badge [data-lucide] { width: 13px; height: 13px; stroke-width: 2.5; }

        .type-badge.birth { background: #DBEAFE; color: #1D4ED8; }
        .type-badge.marriage { background: #FCE7F3; color: #BE185D; }
        .type-badge.death { background: #E5E7EB; color: #374151; }
        .type-badge.marriage_license { background: #FEF3C7; color: #92400E; }

        /* Action column / buttons */
        .records-table th.actions-header,
        .records-table td.actions-cell {
            width: 240px;
            min-width: 240px;
            max-width: 240px;
            text-align: center;
            white-space: nowrap;
            overflow: visible;
            padding-left: 10px;
            padding-right: 10px;
        }

        .action-buttons {
            display: inline-flex;
            gap: 6px;
            justify-content: center;
            flex-wrap: nowrap;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .action-btn [data-lucide] { width: 14px; height: 14px; stroke-width: 2.5; }

        .action-btn-restore {
            background: var(--success-light);
            color: #065F46;
        }
        .action-btn-restore:hover {
            background: var(--success);
            color: #FFFFFF;
        }

        .action-btn-delete {
            background: var(--danger-light);
            color: #991B1B;
        }
        .action-btn-delete:hover {
            background: var(--danger);
            color: #FFFFFF;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 16px 0;
            margin-top: 8px;
        }

        .pagination-btn {
            min-width: 44px;
            height: 44px;
            padding: 0;
            border: 1.5px solid var(--border-medium);
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .pagination-btn [data-lucide] {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
        }

        .pagination-btn:hover:not(.disabled):not(.active) {
            background: var(--primary-lighter);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .pagination-btn.disabled {
            opacity: 0.25;
            cursor: not-allowed;
            pointer-events: none;
            background: var(--bg-tertiary);
        }

        .pagination-btn.active {
            background: var(--primary) !important;
            color: #FFFFFF !important;
            border-color: var(--primary) !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25) !important;
            cursor: default !important;
            pointer-events: none !important;
        }

        .pagination-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 var(--spacing-md);
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
        }

        /* No records */
        .no-records {
            text-align: center;
            padding: var(--spacing-2xl) var(--spacing-lg);
            color: var(--text-tertiary);
        }

        .no-records [data-lucide] {
            margin-bottom: var(--spacing-md);
            color: var(--text-muted);
        }

        .no-records p {
            margin: 10px 0;
            font-size: 16px;
            font-weight: 500;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .no-records p:first-of-type {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Trash info bar */
        .trash-info {
            background: var(--warning-light);
            border: 1px solid #FDE68A;
            border-left: 4px solid var(--warning);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: #78350F;
            font-weight: 500;
        }

        .trash-info [data-lucide] {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            margin-top: 2px;
            color: var(--warning);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .page-container { padding: var(--spacing-lg) var(--spacing-md); }
        }

        @media (max-width: 768px) {
            .page-title { font-size: 26px; }
            .page-title [data-lucide] { width: 26px; height: 26px; }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
                padding-bottom: var(--spacing-md);
            }
            .search-section { padding: var(--spacing-sm); }
            .search-form { flex-direction: column; }
            .type-filter-select { width: 100%; }
            .table-container { overflow-x: auto; border-radius: var(--radius-md); }
            .records-table { min-width: 800px; }
            .records-table th, .records-table td { padding: 14px var(--spacing-sm); font-size: 14px; }
            .pagination { gap: 6px; padding: var(--spacing-lg) 0; }
            .pagination-btn { min-width: 40px; height: 40px; font-size: 14px; }
        }

        @media (max-width: 480px) {
            .page-container { padding: var(--spacing-md) var(--spacing-sm); }
            .btn { padding: 10px 16px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>

    <?php include '../includes/sidebar_nav.php'; ?>

    <?php include '../includes/top_navbar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i data-lucide="trash-2"></i>
                    Trash
                </h1>
            </div>

            <!-- Info bar -->
            <div class="trash-info">
                <i data-lucide="info"></i>
                <div>
                    Records in the Trash were soft-deleted and can be restored at any time.
                    Use <strong>Delete Permanently</strong> to remove them forever &mdash; this action cannot be undone.
                </div>
            </div>

            <!-- Search & Filter Section -->
            <div class="search-section">
                <form method="GET" action="" class="search-form" id="searchForm">
                    <div class="search-input-wrapper">
                        <input
                            type="text"
                            name="search"
                            id="liveSearchInput"
                            class="search-input"
                            placeholder="Search by registry number or name..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            autocomplete="off"
                        >
                    </div>

                    <select name="type" class="type-filter-select" onchange="this.form.submit()">
                        <option value="">All Record Types</option>
                        <?php foreach ($accessible_types as $key => $cfg): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cfg['label']); ?>s
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary" style="display: none;">
                        <i data-lucide="search"></i>
                        Search
                    </button>

                    <?php if (!empty($search) || !empty($filter_type)): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline">
                        <i data-lucide="x"></i>
                        Clear
                    </a>
                    <?php endif; ?>
                </form>
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
                        Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> of <?php echo number_format($total_records); ?> records
                    </div>
                </div>

                <table class="records-table">
                    <thead>
                        <tr>
                            <th class="row-number-header">#</th>
                            <th class="sortable <?php echo $sort_by === 'record_type' ? 'active' : ''; ?>">
                                <a href="<?php echo get_sort_url('record_type'); ?>">
                                    Type
                                    <i data-lucide="<?php echo get_sort_icon('record_type'); ?>" class="sort-icon"></i>
                                </a>
                            </th>
                            <th class="sortable <?php echo $sort_by === 'registry_no' ? 'active' : ''; ?>">
                                <a href="<?php echo get_sort_url('registry_no'); ?>">
                                    Registry No.
                                    <i data-lucide="<?php echo get_sort_icon('registry_no'); ?>" class="sort-icon"></i>
                                </a>
                            </th>
                            <th>Name</th>
                            <th>Record Date</th>
                            <th class="sortable <?php echo $sort_by === 'deleted_at' ? 'active' : ''; ?>">
                                <a href="<?php echo get_sort_url('deleted_at'); ?>">
                                    Deleted On
                                    <i data-lucide="<?php echo get_sort_icon('deleted_at'); ?>" class="sort-icon"></i>
                                </a>
                            </th>
                            <th class="actions-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $row_number = $offset + 1;
                        foreach ($records as $record):
                            $rtype = $record['record_type'];
                            $rcfg = $type_configs[$rtype] ?? null;
                            if (!$rcfg) continue;
                        ?>
                        <tr>
                            <td class="row-number"><?php echo $row_number++; ?></td>
                            <td>
                                <span class="type-badge <?php echo htmlspecialchars($rtype); ?>">
                                    <i data-lucide="<?php echo htmlspecialchars($rcfg['icon']); ?>"></i>
                                    <?php echo htmlspecialchars($rcfg['label']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($record['registry_no'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars(!empty(trim($record['display_name'])) ? $record['display_name'] : 'N/A'); ?></td>
                            <td><?php echo fmt_date($record['record_date']); ?></td>
                            <td><?php echo fmt_datetime($record['deleted_at']); ?></td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button type="button" class="action-btn action-btn-restore"
                                            onclick="restoreRecord(<?php echo (int)$record['id']; ?>, '<?php echo htmlspecialchars($rtype); ?>', '<?php echo htmlspecialchars(addslashes($record['registry_no'] ?? '')); ?>')">
                                        <i data-lucide="rotate-ccw"></i>
                                        Restore
                                    </button>
                                    <button type="button" class="action-btn action-btn-delete"
                                            onclick="permanentlyDelete(<?php echo (int)$record['id']; ?>, '<?php echo htmlspecialchars($rtype); ?>', '<?php echo htmlspecialchars(addslashes($record['registry_no'] ?? '')); ?>')">
                                        <i data-lucide="trash"></i>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-records">
                    <i data-lucide="trash-2" style="width: 48px; height: 48px; stroke: #adb5bd;"></i>
                    <p>Trash is empty.</p>
                    <?php if (!empty($search) || !empty($filter_type)): ?>
                    <p>Try adjusting your search or filter.</p>
                    <?php else: ?>
                    <p>Deleted records will appear here and can be restored.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $pagination_current_page = (int)$pagination_current_page;
                $total_pages = (int)$total_pages;

                $base_query = build_query_string(['page']);
                $query_prefix = $base_query ? '&' . $base_query : '';
                ?>

                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === 1 ? 'disabled' : ''; ?>"
                   title="First page">
                    <i data-lucide="chevrons-left"></i>
                </a>

                <a href="?page=<?php echo max(1, $pagination_current_page - 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === 1 ? 'disabled' : ''; ?>"
                   title="Previous page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <?php
                $start_page = max(1, $pagination_current_page - 2);
                $end_page = min($total_pages, $pagination_current_page + 2);

                if ($start_page > 1):
                ?>
                <a href="?page=1<?php echo $query_prefix; ?>" class="pagination-btn">1</a>
                <?php if ($start_page > 2): ?>
                <span class="pagination-info">...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++):
                    $is_active = $i === $pagination_current_page;
                ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $is_active ? 'active' : ''; ?>"
                   title="Page <?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="pagination-info">...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <a href="?page=<?php echo min($total_pages, $pagination_current_page + 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === $total_pages ? 'disabled' : ''; ?>"
                   title="Next page">
                    <i data-lucide="chevron-right"></i>
                </a>

                <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === $total_pages ? 'disabled' : ''; ?>"
                   title="Last page">
                    <i data-lucide="chevrons-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/sidebar_scripts.php'; ?>

    <script>
        // Record-type label → delete API endpoint
        const DELETE_API_MAP = {
            'birth': '../api/certificate_of_live_birth_delete.php',
            'marriage': '../api/certificate_of_marriage_delete.php',
            'death': '../api/certificate_of_death_delete.php',
            'marriage_license': '../api/application_for_marriage_license_delete.php'
        };

        const TYPE_LABELS = {
            'birth': 'Birth Record',
            'marriage': 'Marriage Record',
            'death': 'Death Record',
            'marriage_license': 'Marriage License'
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Change records per page
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        // Restore a record
        function restoreRecord(id, recordType, registryNo) {
            const label = TYPE_LABELS[recordType] || 'Record';
            const title = `Restore ${label}`;
            const message = `Are you sure you want to restore this record?<br><br>` +
                (registryNo ? `<strong>Registry No:</strong> ${registryNo}<br>` : '') +
                `<strong>Type:</strong> ${label}<br><br>` +
                `<span style="color: #059669; font-weight: 600;">This record will be moved back to active records.</span>`;

            if (typeof Notiflix === 'undefined') {
                if (confirm(message.replace(/<[^>]+>/g, ''))) {
                    performRestore(id, recordType);
                }
                return;
            }

            Notiflix.Confirm.show(
                title,
                message,
                'Cancel',
                'Restore',
                function okCb() { console.log('Restore cancelled'); },
                function cancelCb() { performRestore(id, recordType); },
                {
                    width: '500px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 600,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: '#059669',
                    buttonsFontSize: '15px',
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        function performRestore(id, recordType) {
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle('Restoring record...');
            }

            const formData = new FormData();
            formData.append('record_id', id);
            formData.append('record_type', recordType);

            fetch('../api/trash_restore.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();

                if (data.success) {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message);
                    } else {
                        alert('Error: ' + data.message);
                    }
                }
            })
            .catch(err => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();
                console.error(err);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('An error occurred while restoring the record.');
                } else {
                    alert('An error occurred while restoring the record.');
                }
            });
        }

        // Permanently delete
        function permanentlyDelete(id, recordType, registryNo) {
            const label = TYPE_LABELS[recordType] || 'Record';
            const title = `Delete ${label} Permanently`;
            const message = `This record will be <strong>permanently deleted</strong>.<br><br>` +
                (registryNo ? `<strong>Registry No:</strong> ${registryNo}<br>` : '') +
                `<strong>Type:</strong> ${label}<br><br>` +
                `<span style="color: #DC2626; font-weight: 600;">⚠ This action cannot be undone.</span>`;

            if (typeof Notiflix === 'undefined') {
                if (confirm(message.replace(/<[^>]+>/g, ''))) {
                    performHardDelete(id, recordType);
                }
                return;
            }

            Notiflix.Confirm.show(
                title,
                message,
                'Cancel',
                'Delete Permanently',
                function okCb() { console.log('Hard delete cancelled'); },
                function cancelCb() { performHardDelete(id, recordType); },
                {
                    width: '500px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 600,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: '#EF4444',
                    buttonsFontSize: '15px',
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        function performHardDelete(id, recordType) {
            const apiUrl = DELETE_API_MAP[recordType];
            if (!apiUrl) {
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('Unknown record type.');
                }
                return;
            }

            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle('Deleting permanently...');
            }

            const formData = new FormData();
            formData.append('record_id', id);
            formData.append('delete_type', 'hard');

            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();

                if (data.success) {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message);
                    } else {
                        alert('Error: ' + data.message);
                    }
                }
            })
            .catch(err => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();
                console.error(err);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('An error occurred while deleting the record.');
                } else {
                    alert('An error occurred while deleting the record.');
                }
            });
        }

        // Live search — reload with query after short delay
        let searchTimeout;
        const liveSearchInput = document.getElementById('liveSearchInput');
        if (liveSearchInput) {
            liveSearchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('searchForm').submit();
                }, 400);
            });
        }

        window.addEventListener('load', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
