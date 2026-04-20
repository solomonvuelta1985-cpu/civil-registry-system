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

// Derive edit/delete permission flags once — used in both the PHP table loop
// and the JavaScript section further down the page.
$edit_permission = str_replace('_view', '_edit', $required_permission);
$can_delete      = isAdmin();

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
            ['label' => 'Place of Marriage', 'field' => 'place_of_marriage', 'sortable' => true],
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
            ['label' => 'Sex', 'field' => 'child_sex', 'sortable' => true],
            ['label' => 'Birth Date', 'field' => 'child_date_of_birth', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Father', 'field' => 'father_name', 'sortable' => true, 'sort_field' => 'father_first_name'],
            ['label' => 'Mother', 'field' => 'mother_name', 'sortable' => true, 'sort_field' => 'mother_first_name'],
            ['label' => 'Place of Birth', 'field' => 'child_place_of_birth', 'sortable' => true],
            ['label' => 'Registration Date', 'field' => 'date_of_registration', 'sortable' => true, 'type' => 'date']
        ],
        'filters' => [
            ['name' => 'birth_date_from', 'label' => 'Birth Date From', 'type' => 'date', 'field' => 'child_date_of_birth', 'operator' => '>='],
            ['name' => 'birth_date_to', 'label' => 'Birth Date To', 'type' => 'date', 'field' => 'child_date_of_birth', 'operator' => '<='],
            ['name' => 'reg_date_from', 'label' => 'Registration Date From', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '>='],
            ['name' => 'reg_date_to', 'label' => 'Registration Date To', 'type' => 'date', 'field' => 'date_of_registration', 'operator' => '<='],
            [
                'name' => 'place_type',
                'label' => 'Place Type',
                'type' => 'select',
                'field' => 'place_type',
                'operator' => '=',
                'options' => [
                    '' => 'All',
                    'Barangay' => 'Barangay',
                    'Hospital' => 'Hospital'
                ]
            ],
            [
                'name' => 'child_place_of_birth',
                'label' => 'Location',
                'type' => 'select',
                'field' => 'child_place_of_birth',
                'operator' => '=',
                'dependent_on' => 'place_type',
                'options' => [
                    '' => 'All Locations',
                    'Barangay' => [
                        'Adaoag', 'Agaman (Proper)', 'Agaman Norte', 'Agaman Sur', 'Alba', 'Annayatan',
                        'Asassi', 'Asinga-Via', 'Awallan', 'Bacagan', 'Bagunot', 'Barsat East',
                        'Barsat West', 'Bitag Grande', 'Bitag Pequeño', 'Bunugan', 'C. Verzosa (Valley Cove)',
                        'Canagatan', 'Carupian', 'Catugay', 'Dabbac Grande', 'Dalin', 'Dalla',
                        'Hacienda Intal', 'Ibulo', 'Imurung', 'J. Pallagao', 'Lasilat', 'Mabini',
                        'Masical', 'Mocag', 'Nangalinan', 'Poblacion (Centro)', 'Remus', 'San Antonio',
                        'San Francisco', 'San Isidro', 'San Jose', 'San Miguel', 'San Vicente',
                        'Santa Margarita', 'Santor', 'Taguing', 'Taguntungan', 'Tallang', 'Taytay',
                        'Temblique', 'Tungel'
                    ],
                    'Hospital' => [
                        'Baggao District Hospital',
                        'Municipal Health Office'
                    ]
                ]
            ],
            [
                'name' => 'child_sex',
                'label' => 'Sex',
                'type' => 'select',
                'field' => 'child_sex',
                'operator' => '=',
                'options' => [
                    '' => 'All',
                    'Male' => 'Male',
                    'Female' => 'Female'
                ]
            ]
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
            ['label' => 'Sex', 'field' => 'sex', 'sortable' => true],
            ['label' => 'Age', 'field' => 'age', 'sortable' => true],
            ['label' => 'Date of Birth', 'field' => 'date_of_birth', 'sortable' => true, 'type' => 'date'],
            ['label' => 'Date of Death', 'field' => 'date_of_death', 'sortable' => true, 'type' => 'date'],
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
            ['name' => 'age_to', 'label' => 'Age To', 'type' => 'number', 'field' => 'age', 'operator' => '<='],
            [
                'name' => 'sex',
                'label' => 'Sex',
                'type' => 'select',
                'field' => 'sex',
                'operator' => '=',
                'options' => [
                    '' => 'All',
                    'Male' => 'Male',
                    'Female' => 'Female'
                ]
            ]
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
$pagination_current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagination_current_page - 1) * $records_per_page;

// Search functionality — multi-token: split query on whitespace so
// "richmond rosete" matches rows where "richmond" hits first_name AND
// "rosete" hits last_name, instead of LIKE '%richmond rosete%' on each
// column individually (which would find nothing). Fuzzy fallback below
// (see "Fuzzy fallback") surfaces near/possible matches if strict fails.
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$search_query = '';
$params = [];
$is_fuzzy = false;

$search_tokens = [];
if (!empty($search)) {
    foreach (preg_split('/\s+/', trim($search)) as $t) {
        if ($t !== '') {
            $search_tokens[] = $t;
        }
    }
}

/**
 * Build WHERE fragment and bind params for a search mode.
 * 'strict' — every token must match some search field (AND of ORs).
 * 'fuzzy'  — any token in any field (flat OR), used as fallback.
 */
$build_search_clause = function (array $tokens, array $search_fields, string $mode): array {
    if (empty($tokens)) {
        return ['', []];
    }
    $p = [];
    $i = 0;
    if ($mode === 'strict') {
        $token_clauses = [];
        foreach ($tokens as $token) {
            $field_clauses = [];
            foreach ($search_fields as $field) {
                $name = ':s_' . $i++;
                $field_clauses[] = "{$field} LIKE {$name}";
                $p[$name] = "%{$token}%";
            }
            $token_clauses[] = '(' . implode(' OR ', $field_clauses) . ')';
        }
        return [' AND (' . implode(' AND ', $token_clauses) . ')', $p];
    }
    $field_clauses = [];
    foreach ($tokens as $token) {
        foreach ($search_fields as $field) {
            $name = ':s_' . $i++;
            $field_clauses[] = "{$field} LIKE {$name}";
            $p[$name] = "%{$token}%";
        }
    }
    return [' AND (' . implode(' OR ', $field_clauses) . ')', $p];
};

$search_params = [];
if (!empty($search_tokens)) {
    [$search_query, $search_params] = $build_search_clause($search_tokens, $config['search_fields'], 'strict');
}

// Advanced filters
$filter_query = '';
$filter_params = [];
$active_filters = [];

foreach ($config['filters'] as $filter) {
    if (!empty($_GET[$filter['name']])) {
        $active_filters[] = $filter['name'];
        $param_name = ':' . $filter['name'];

        if ($filter['operator'] === 'LIKE') {
            $filter_query .= " AND {$filter['field']} LIKE {$param_name}";
            $filter_params[$param_name] = "%{$_GET[$filter['name']]}%";
        } else {
            $filter_query .= " AND {$filter['field']} {$filter['operator']} {$param_name}";
            $filter_params[$param_name] = $_GET[$filter['name']];
        }
    }
}

$has_active_filters = !empty($active_filters);

// Archive visibility toggle: Active only by default, include Archived when requested.
// Deleted records are NEVER shown here (they belong in Trash).
// This is the single place where the default status filter is decided, so changing
// the behaviour later (e.g. allowing "Archived only") is a one-file change.
$include_archived = isset($_GET['include_archived']) && $_GET['include_archived'] === '1';
$status_in_list = $include_archived ? "'Active','Archived'" : "'Active'";
$status_sql = "status IN ($status_in_list)";

// Sorting functionality
$allowed_sort_columns = $config['sort_columns'];

$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_columns)
    ? $_GET['sort_by']
    : 'created_at';

$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC'
    ? 'ASC'
    : 'DESC';

// Closure to run count + fetch for a given search fragment/params combo.
// Used twice when the strict search returns no hits so we can fall back
// to a fuzzy (any-token) search transparently.
$run_records_query = function (string $search_fragment, array $search_binds) use (
    $pdo, $config, $status_sql, $filter_query, $filter_params,
    $sort_by, $sort_order, $records_per_page, $offset
) {
    $all_params = array_merge($search_binds, $filter_params);

    $count_sql = "SELECT COUNT(*) as total FROM {$config['table']} WHERE {$status_sql}" . $search_fragment . $filter_query;
    $total_records = 0;
    $total_pages = 0;
    try {
        $count_stmt = $pdo->prepare($count_sql);
        foreach ($all_params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_records = (int)($count_stmt->fetch()['total'] ?? 0);
        $total_pages = (int)ceil($total_records / $records_per_page);
    } catch (PDOException $e) {
        error_log("Count query error: " . $e->getMessage());
        error_log("Count SQL: " . $count_sql);
        error_log("Params: " . print_r($all_params, true));
    }

    $sql = "SELECT * FROM {$config['table']} WHERE {$status_sql}"
        . $search_fragment
        . $filter_query
        . " ORDER BY {$sort_by} {$sort_order} LIMIT :limit OFFSET :offset";
    $records = [];
    try {
        $stmt = $pdo->prepare($sql);
        foreach ($all_params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Main query error: " . $e->getMessage());
        error_log("Main SQL: " . $sql);
        error_log("Params: " . print_r($all_params, true));
    }

    return [$records, $total_records, $total_pages];
};

// First pass: strict multi-token search.
[$records, $total_records, $total_pages] = $run_records_query($search_query, $search_params);

// Fuzzy fallback: if a multi-word search returned nothing, rerun with
// an OR across all tokens and surface the results as "possible matches".
// Only triggers when the user typed 2+ tokens — a single word has no
// "near" interpretation beyond the literal LIKE already attempted.
if ($total_records === 0 && count($search_tokens) > 1) {
    [$fuzzy_query, $fuzzy_params] = $build_search_clause($search_tokens, $config['search_fields'], 'fuzzy');
    [$records, $total_records, $total_pages] = $run_records_query($fuzzy_query, $fuzzy_params);
    if ($total_records > 0) {
        $is_fuzzy = true;
    }
}

// Keep $params populated for any legacy code below that still reads it
// (e.g. debug output). Not used by the queries themselves anymore.
$params = array_merge($search_params, $filter_params);

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
    } elseif ($field === 'date_of_registration') {
        $fmt = $record['date_of_registration_format'] ?? 'full';
        return htmlspecialchars(format_registration_date(
            $record['date_of_registration'] ?? null,
            $fmt,
            isset($record['date_of_registration_partial_month']) ? (int)$record['date_of_registration_partial_month'] : null,
            isset($record['date_of_registration_partial_year'])  ? (int)$record['date_of_registration_partial_year']  : null,
            isset($record['date_of_registration_partial_day'])   ? (int)$record['date_of_registration_partial_day']   : null
        ));
    } elseif (in_array($field, ['child_date_of_birth', 'husband_date_of_birth', 'wife_date_of_birth', 'date_of_birth'], true)
              && array_key_exists($field . '_format', $record)) {
        $fmt = $record[$field . '_format'] ?? 'full';
        return htmlspecialchars(format_registration_date(
            $record[$field] ?? null,
            $fmt,
            isset($record[$field . '_partial_month']) ? (int)$record[$field . '_partial_month'] : null,
            isset($record[$field . '_partial_year'])  ? (int)$record[$field . '_partial_year']  : null,
            isset($record[$field . '_partial_day'])   ? (int)$record[$field . '_partial_day']   : null
        ));
    } elseif ($field === 'child_place_of_birth' && $record_type === 'birth') {
        $place = $record['child_place_of_birth'] ?? '';
        $barangay = $record['barangay'] ?? '';
        $place_type = $record['place_type'] ?? '';

        if (!empty($place)) {
            // Has specific location (hospital/health center name)
            $result = $place;
            if (!empty($barangay) && stripos($place, $barangay) === false) {
                $result .= ', ' . $barangay;
            }
        } elseif (!empty($place_type) && in_array($place_type, ['Home', 'Other'], true)) {
            // Home/Other birth — show type + barangay
            $result = $place_type;
            if (!empty($barangay)) {
                $result .= ', ' . $barangay;
            }
        } elseif (!empty($barangay)) {
            $result = $barangay;
        } else {
            return 'N/A';
        }
        return htmlspecialchars($result);
    } elseif ($field === 'age' && $record_type === 'death') {
        if (!isset($record['age']) || $record['age'] === null || $record['age'] === '') return 'N/A';
        $unit = $record['age_unit'] ?? 'years';
        $label = ucfirst($unit);
        return htmlspecialchars($record['age'] . ' ' . $label);
    } elseif ($type === 'date' && !empty($record[$field])) {
        return date('M d, Y', strtotime($record[$field]));
    } else {
        return htmlspecialchars($record[$field] ?? 'N/A');
    }
}

/**
 * Detect late/delayed registration per PSA / Act 3753 rules:
 *   Birth    — timely if registered within 30 days of date of birth
 *   Death    — timely if registered within 30 days of date of death
 *   Marriage — timely if registered within 15 days of date of marriage
 *
 * Returns ['is_late' => bool, 'days_delayed' => int, 'threshold' => int]
 * or null when event/registration date is missing (can't determine).
 */
function detect_late_registration($record, $record_type) {
    $event_field = [
        'birth'    => 'child_date_of_birth',
        'death'    => 'date_of_death',
        'marriage' => 'date_of_marriage',
    ][$record_type] ?? null;

    $threshold = ($record_type === 'marriage') ? 15 : 30;

    if (!$event_field) return null;

    $event_date = $record[$event_field] ?? null;
    $reg_date   = $record['date_of_registration'] ?? null;

    if (empty($event_date) || empty($reg_date)) return null;

    $event_ts = strtotime($event_date);
    $reg_ts   = strtotime($reg_date);
    if ($event_ts === false || $reg_ts === false) return null;

    $days_delayed = (int) floor(($reg_ts - $event_ts) / 86400);

    return [
        'is_late'      => $days_delayed > $threshold,
        'days_delayed' => $days_delayed,
        'threshold'    => $threshold,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once '../includes/security.php'; echo csrfTokenMeta(); ?>
    <title><?php echo htmlspecialchars($config['title']); ?> - Civil Registry</title>

    <!-- Google Fonts (online only; system fonts used when OFFLINE_MODE=true) -->
    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">

    <!-- Lucide Icons -->
    <script src="<?= asset_url('lucide') ?>"></script>

    <!-- Notiflix - Modern Notification Library -->
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../assets/js/notiflix-config.js"></script>

    <!-- Shared Sidebar Styles -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <!-- Record Preview Modal Styles -->
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css?v=4">

    <!-- PDF.js Library -->
    <script src="<?= asset_url('pdfjs') ?>"></script>
    <script>
        // Configure PDF.js worker
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= asset_url("pdfjs_worker") ?>';
        }
    </script>

    <style>
        /* ========================================
           CORPORATE MODERN DESIGN - PROFESSIONAL & CLEAN
           Light colors, excellent typography, proper spacing
           ======================================== */

        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Font loaded via <link> in <head> — see google_fonts_tag() */

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
            /* Base Colors - Light & Professional */
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8FAFC;
            --bg-tertiary: #F1F5F9;
            --bg-accent: #EFF6FF;

            /* Text Colors - Refined Hierarchy */
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --text-muted: #CBD5E1;

            /* Border Colors - Subtle */
            --border-light: #F1F5F9;
            --border-medium: #E2E8F0;
            --border-strong: #CBD5E1;

            /* Action Colors - Professional Palette */
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

            /* Spacing System - Enhanced */
            --spacing-xs: 6px;
            --spacing-sm: 12px;
            --spacing-md: 20px;
            --spacing-lg: 32px;
            --spacing-xl: 48px;
            --spacing-2xl: 64px;

            /* Border Radius - Consistent */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;

            /* Shadows - Refined */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow-md: 0 2px 8px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 8px 24px 0 rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 16px 40px 0 rgba(0, 0, 0, 0.1);
        }

        /* Page Layout - Enhanced Spacing */
        .page-container {
            padding: 24px var(--spacing-lg) var(--spacing-lg);
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Header - Better Hierarchy */
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
            color: var(--primary);
            width: 26px;
            height: 26px;
            stroke-width: 2;
        }

        /* Buttons - Professional Design */
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

        .btn-primary {
            background-color: var(--primary);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-success {
            background-color: var(--success);
            color: #FFFFFF;
        }

        .btn-success:hover {
            background-color: var(--success-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: var(--warning);
            color: #FFFFFF;
        }

        .btn-warning:hover {
            background-color: var(--warning-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger);
            color: #FFFFFF;
        }

        .btn-danger:hover {
            background-color: var(--danger-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
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

        /* Search & Filter - Enhanced Professional Design */
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

        .search-input::placeholder {
            color: var(--text-tertiary);
            font-weight: 400;
        }

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

        .filter-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            background: var(--bg-secondary);
            border: 1.5px solid var(--border-medium);
            padding: 10px 16px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .filter-toggle-btn [data-lucide] {
            width: 18px;
            height: 18px;
            stroke-width: 2.5;
        }

        .filter-toggle-btn:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-toggle-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
        }

        .advanced-filters {
            display: none;
            margin-top: 12px;
        }

        .advanced-filters.show {
            display: block;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            padding: 14px;
            background: var(--bg-secondary);
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-md);
        }

        /* Ensure Place Type and Location are on the same row */
        .filter-group[data-filter-name="birth_date_from"] { grid-column: 1; }
        .filter-group[data-filter-name="birth_date_to"] { grid-column: 2; }
        .filter-group[data-filter-name="reg_date_from"] { grid-column: 3; }
        .filter-group[data-filter-name="reg_date_to"] { grid-column: 4; }
        .filter-group[data-filter-name="place_type"] { grid-column: 1; }
        .filter-group[data-filter-name="child_place_of_birth"] { grid-column: 2; }
        .filter-group[data-filter-name="child_sex"] { grid-column: 3; }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-sm);
            font-size: 15px;
            background-color: var(--bg-primary);
            transition: all 0.2s ease;
            font-family: inherit;
            color: var(--text-primary);
            font-weight: 400;
        }

        .filter-group input::placeholder {
            color: var(--text-tertiary);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        /* Professional Select Dropdown Styling */
        .filter-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            padding-right: 40px;
            cursor: pointer;
        }

        .filter-group select:hover {
            border-color: var(--border-strong);
        }

        .filter-group select:focus {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563EB' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        }

        /* Disabled Select Dropdown */
        .filter-group select:disabled {
            background-color: var(--bg-tertiary);
            color: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.6;
            border-color: var(--border-light);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23CBD5E1' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        }

        /* Hide dependent filter groups when parent is not selected */
        .filter-group.dependent-hidden {
            display: none;
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
            justify-content: center;
            background: #FFFFFF;
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-left: 4px;
        }

        /* Table - Professional Corporate Design */
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
            position: sticky;
            top: 0;
            z-index: 10;
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

        .records-table th.sortable:hover .sort-icon {
            opacity: 0.6;
        }

        .records-table th.sortable.active .sort-icon {
            opacity: 1;
        }

        /* Row Number Column Styles */
        .records-table th.row-number-header,
        .records-table td.row-number {
            width: 50px;
            text-align: center;
            font-weight: 700;
            border-right: 2px solid var(--border-medium);
            background: var(--bg-secondary);
        }

        .records-table th.row-number-header {
            font-size: 12px;
            letter-spacing: 0.08em;
        }

        .records-table td.row-number {
            color: var(--text-tertiary);
            font-size: 14px;
            font-weight: 600;
        }

        .records-table tbody tr:hover td.row-number {
            background: var(--primary-lighter);
            color: var(--primary);
        }

        .record-name-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s;
        }

        .record-name-link:hover {
            color: #0a58ca;
            text-decoration: underline;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-light);
        }

        .table-controls-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .table-controls-right {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .per-page-selector label {
            font-weight: 600;
        }

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

        .per-page-selector select:hover {
            border-color: var(--primary);
        }

        .per-page-selector select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
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
            max-width: 150px;
        }

        .records-table tbody {
            transition: opacity 0.3s ease;
        }

        .records-table tbody tr {
            transition: all 0.2s ease;
        }

        /* Zebra striping for better readability */
        .records-table tbody tr:nth-child(even) {
            background-color: var(--bg-secondary);
        }

        .records-table tbody tr:nth-child(odd) {
            background-color: var(--bg-primary);
        }

        .records-table tbody tr:hover {
            background-color: var(--bg-accent);
        }

        .records-table tbody tr.row-active,
        .records-table tbody tr.row-active:nth-child(even),
        .records-table tbody tr.row-active:nth-child(odd) {
            background-color: var(--primary-lighter);
        }

        .records-table tbody tr.row-active td.row-number {
            background: var(--primary-lighter);
            color: var(--primary);
        }

        .records-table tbody tr:last-child td {
            border-bottom: none;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        /* Action column should not shrink */
        .records-table th:last-child,
        .records-table td:last-child {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
            white-space: nowrap;
            text-align: center;
        }

        /* Dropdown Action Menu */
        .action-dropdown {
            position: relative;
            display: inline-block;
        }

        .action-dropdown-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .action-dropdown-btn:hover {
            background: var(--primary-hover);
        }

        .action-dropdown-menu {
            display: none;
            position: absolute;
            background: white;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-sm);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 150px;
            z-index: 9999;
        }

        .action-dropdown.active .action-dropdown-menu {
            display: block;
        }

        .action-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .action-dropdown-item:hover {
            background: var(--bg-secondary);
        }

        .action-dropdown-item:first-child {
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        }

        .action-dropdown-item:last-child {
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
        }

        .action-dropdown-item.view-action {
            color: #059669;
        }

        .action-dropdown-item.edit-action {
            color: var(--primary);
        }

        .action-dropdown-item.delete-action {
            color: #DC2626;
        }

        .action-dropdown-item.archive-action {
            color: #D97706;
        }
        .action-dropdown-item.unarchive-action {
            color: #059669;
        }

        .action-dropdown-item svg {
            width: 16px;
            height: 16px;
        }

        /* "Include archived" toggle in page header */
        .archive-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            user-select: none;
        }
        .archive-toggle:hover {
            border-color: #D97706;
            color: #D97706;
            background: #FFFBEB;
        }
        .archive-toggle input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #D97706;
        }
        .archive-toggle [data-lucide] {
            width: 16px;
            height: 16px;
        }
        .archive-toggle:has(input:checked) {
            background: #FEF3C7;
            border-color: #D97706;
            color: #92400E;
        }

        /* Archived row indicator (shown in mixed lists) */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin-left: 6px;
            vertical-align: middle;
        }
        .status-badge.archived {
            background: #FEF3C7;
            color: #92400E;
        }
        .status-badge.late-registration {
            background: #FEE2E2;
            color: #991B1B;
        }
        .status-badge.timely-registration {
            background: #DCFCE7;
            color: #166534;
        }
        .records-table tbody tr.row-archived {
            background: #FFFBEB !important;
        }
        .records-table tbody tr.row-archived:hover {
            background: #FEF3C7 !important;
        }

        /* Row-selection checkbox column */
        .records-table th.select-header,
        .records-table td.select-cell {
            width: 42px;
            text-align: center;
            padding: 8px 4px;
        }
        .records-table th.select-header input[type="checkbox"],
        .records-table td.select-cell input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        /* Bulk action bar (appears when rows are selected) */
        .bulk-action-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 16px;
            background: var(--primary-lighter);
            border: 1.5px solid var(--primary-light);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
        }
        .bulk-action-bar.active {
            display: flex;
        }
        .bulk-action-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bulk-action-count [data-lucide] {
            width: 18px;
            height: 18px;
        }
        .bulk-action-buttons {
            display: flex;
            gap: 8px;
        }
        .bulk-action-btn {
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1.5px solid transparent;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .bulk-action-btn [data-lucide] {
            width: 14px;
            height: 14px;
        }
        .bulk-action-btn.archive {
            background: #FEF3C7;
            color: #92400E;
            border-color: #FDE68A;
        }
        .bulk-action-btn.archive:hover {
            background: #D97706;
            color: #FFFFFF;
        }
        .bulk-action-btn.unarchive {
            background: var(--success-light);
            color: #065F46;
            border-color: #A7F3D0;
        }
        .bulk-action-btn.unarchive:hover {
            background: var(--success);
            color: #FFFFFF;
        }
        .bulk-action-btn.clear {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-color: var(--border-medium);
        }
        .bulk-action-btn.clear:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        /* Pagination - Professional Design */
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

        .pagination-divider {
            width: 1px;
            height: 24px;
            background: var(--border-medium);
            margin: 0 8px;
        }

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

        /* Alert - Professional Design */
        .alert {
            padding: var(--spacing-md) var(--spacing-md);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-sm);
            border-left: 4px solid transparent;
            font-weight: 500;
            font-size: 15px;
            box-shadow: var(--shadow-sm);
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
            width: 20px;
            height: 20px;
            margin-top: 2px;
        }

        /* Record Stats Badge - Removed for minimal design */

        /* Remove stat cards - keeping design minimal as requested */

        /* Skeleton Loading Styles - Professional */
        .skeleton {
            background: linear-gradient(
                90deg,
                #E2E8F0 0%,
                #F1F5F9 50%,
                #E2E8F0 100%
            );
            background-size: 200% 100%;
            animation: skeleton-loading 1.8s ease-in-out infinite;
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
            height: 64px;
            margin-bottom: 1px;
        }

        .skeleton-input {
            height: 48px;
            width: 100%;
            border-radius: var(--radius-md);
        }

        .skeleton-text {
            height: 20px;
            width: 85%;
            margin: 0;
            border-radius: 6px;
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
            height: 20px;
            border-radius: 6px;
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
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Adjust grid positions for 2-column layout */
            .filter-group[data-filter-name="birth_date_from"] { grid-column: 1; grid-row: 1; }
            .filter-group[data-filter-name="birth_date_to"] { grid-column: 2; grid-row: 1; }
            .filter-group[data-filter-name="reg_date_from"] { grid-column: 1; grid-row: 2; }
            .filter-group[data-filter-name="reg_date_to"] { grid-column: 2; grid-row: 2; }
            .filter-group[data-filter-name="place_type"] { grid-column: 1; grid-row: 3; }
            .filter-group[data-filter-name="child_place_of_birth"] { grid-column: 2; grid-row: 3; }
            .filter-group[data-filter-name="child_sex"] { grid-column: 1; grid-row: 4; }
        }

        @media (max-width: 1024px) {
            .page-container {
                padding: var(--spacing-lg) var(--spacing-md);
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 26px;
            }

            .page-title [data-lucide] {
                width: 26px;
                height: 26px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-md);
                padding-bottom: var(--spacing-md);
            }

            .search-section {
                padding: var(--spacing-sm);
            }

            .search-form {
                flex-direction: column;
            }

            .filter-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }

            /* Reset grid positions for single column */
            .filter-group[data-filter-name="birth_date_from"],
            .filter-group[data-filter-name="birth_date_to"],
            .filter-group[data-filter-name="reg_date_from"],
            .filter-group[data-filter-name="reg_date_to"],
            .filter-group[data-filter-name="place_type"],
            .filter-group[data-filter-name="child_place_of_birth"],
            .filter-group[data-filter-name="child_sex"] {
                grid-column: 1;
                grid-row: auto;
            }

            .table-container {
                overflow-x: auto;
                border-radius: var(--radius-md);
            }

            .records-table {
                min-width: 800px;
            }

            .records-table th,
            .records-table td {
                padding: 14px var(--spacing-sm);
                font-size: 14px;
            }

            .pagination {
                gap: 6px;
                padding: var(--spacing-lg) 0;
            }

            .pagination-btn {
                min-width: 40px;
                height: 40px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .page-container {
                padding: var(--spacing-md) var(--spacing-sm);
            }

            .btn {
                padding: 10px 16px;
                font-size: 14px;
            }
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
                    <i data-lucide="<?php echo $config['icon']; ?>"></i>
                    <?php echo htmlspecialchars($config['title']); ?>
                </h1>
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <?php if (canArchive($record_type)): ?>
                    <label class="archive-toggle" title="Include archived records in this list">
                        <input type="checkbox" id="includeArchivedToggle" <?php echo $include_archived ? 'checked' : ''; ?>>
                        <i data-lucide="archive"></i>
                        <span>Include archived</span>
                    </label>
                    <?php endif; ?>
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
                            <?php
                            // Check if this is a dependent filter and if parent has no value
                            $is_dependent = isset($filter['dependent_on']);
                            $parent_has_value = $is_dependent && !empty($_GET[$filter['dependent_on']] ?? '');
                            $should_hide = $is_dependent && !$parent_has_value;
                            ?>
                            <div class="filter-group <?php echo $should_hide ? 'dependent-hidden' : ''; ?>"
                                 data-filter-name="<?php echo $filter['name']; ?>"
                                 <?php echo $is_dependent ? 'data-dependent-on="' . $filter['dependent_on'] . '"' : ''; ?>>
                                <label for="<?php echo $filter['name']; ?>"><?php echo htmlspecialchars($filter['label']); ?></label>
                                <?php if ($filter['type'] === 'select'): ?>
                                    <?php if ($is_dependent): ?>
                                        <!-- Cascading/Dependent Dropdown -->
                                        <select
                                            id="<?php echo $filter['name']; ?>"
                                            name="<?php echo $filter['name']; ?>"
                                            onchange="this.form.submit()"
                                            data-dependent-on="<?php echo $filter['dependent_on']; ?>"
                                            <?php echo !$parent_has_value ? 'disabled' : ''; ?>
                                        >
                                            <option value=""><?php echo $filter['options']['']; ?></option>
                                            <?php
                                            // Get the parent filter value
                                            $parent_value = $_GET[$filter['dependent_on']] ?? '';

                                            // If parent has a value, show options for that category
                                            if (!empty($parent_value) && isset($filter['options'][$parent_value]) && is_array($filter['options'][$parent_value])) {
                                                foreach ($filter['options'][$parent_value] as $location) {
                                                    $selected = (isset($_GET[$filter['name']]) && $_GET[$filter['name']] === $location) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($location) . "\" $selected>" . htmlspecialchars($location) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    <?php else: ?>
                                        <!-- Regular Dropdown -->
                                        <select
                                            id="<?php echo $filter['name']; ?>"
                                            name="<?php echo $filter['name']; ?>"
                                            onchange="this.form.submit()"
                                        >
                                            <?php foreach ($filter['options'] as $value => $label): ?>
                                                <?php if (!is_array($label)): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($_GET[$filter['name']]) && $_GET[$filter['name']] == $value) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                <?php else: ?>
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
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Action Bar (shown when rows are selected) -->
            <?php $can_archive = canArchive($record_type); ?>
            <?php if ($can_archive): ?>
            <div class="bulk-action-bar" id="bulkActionBar">
                <div class="bulk-action-count">
                    <i data-lucide="check-square"></i>
                    <span id="bulkSelectedCount">0</span> selected
                </div>
                <div class="bulk-action-buttons">
                    <button type="button" class="bulk-action-btn archive" id="bulkArchiveBtn" onclick="bulkArchiveSelected()">
                        <i data-lucide="archive"></i>
                        Archive Selected
                    </button>
                    <button type="button" class="bulk-action-btn unarchive" id="bulkUnarchiveBtn" onclick="bulkUnarchiveSelected()" style="display:none;">
                        <i data-lucide="archive-restore"></i>
                        Unarchive Selected
                    </button>
                    <button type="button" class="bulk-action-btn clear" onclick="clearBulkSelection()">
                        <i data-lucide="x"></i>
                        Clear
                    </button>
                </div>
            </div>
            <?php endif; ?>

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
                        Showing <?php echo number_format(($offset + 1)); ?> to <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> of <?php echo number_format($total_records); ?> <?php echo $is_fuzzy ? 'possible matches' : 'records'; ?>
                    </div>
                </div>

                <?php if ($is_fuzzy): ?>
                <div class="fuzzy-notice" style="background:#fff8e1; border-left:4px solid #f5a623; padding:12px 16px; margin:0 0 12px 0; color:#8a6d3b; border-radius:6px; display:flex; align-items:center; gap:10px;">
                    <i data-lucide="search" style="width:18px; height:18px;"></i>
                    <div>
                        <strong>No exact match found for "<?php echo htmlspecialchars($search); ?>".</strong>
                        Showing near / possible results based on the words you typed.
                    </div>
                </div>
                <?php endif; ?>

                <table class="records-table">
                    <thead>
                        <tr>
                            <?php if ($can_archive): ?>
                            <th class="select-header">
                                <input type="checkbox" id="selectAllRows" title="Select all on this page" onclick="toggleSelectAllRows(this)">
                            </th>
                            <?php endif; ?>
                            <th class="row-number-header">#</th>
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
                        <?php
                        $row_number = $offset + 1; // Start from the current offset
                        // Initialize here so these are always defined even when $records is empty
                        $edit_permission = str_replace('_view', '_edit', $required_permission);
                        // Delete is Admin-only (enforced by isAdmin() below + server via requireAdminApi())
                        $can_delete = isAdmin();
                        foreach ($records as $record):
                            $is_archived_row = ($record['status'] ?? 'Active') === 'Archived';
                        ?>
                        <tr class="<?php echo $is_archived_row ? 'row-archived' : ''; ?>" data-record-id="<?php echo (int)$record['id']; ?>" data-record-status="<?php echo htmlspecialchars($record['status'] ?? 'Active'); ?>">
                            <?php if ($can_archive): ?>
                            <td class="select-cell">
                                <input type="checkbox" class="row-checkbox" value="<?php echo (int)$record['id']; ?>" data-status="<?php echo htmlspecialchars($record['status'] ?? 'Active'); ?>" onclick="updateBulkSelection()">
                            </td>
                            <?php endif; ?>
                            <td class="row-number"><?php echo $row_number++; ?></td>
                            <?php
                            $name_fields = ['child_name', 'husband_name', 'wife_name', 'deceased_name', 'groom_name', 'bride_name'];
                            $first_name_shown = false;
                            foreach ($config['table_columns'] as $column):
                                $value = get_field_value($record, $column['field'], $column['type'] ?? 'text');
                                $is_name = in_array($column['field'], $name_fields);
                                $is_first_name_cell = $is_name && !$first_name_shown;
                                if ($is_name) $first_name_shown = true;
                            ?>
                            <td>
                                <?php if ($is_name): ?>
                                    <a href="javascript:void(0)" class="record-name-link" onclick="recordPreviewModal.open(<?php echo $record['id']; ?>, '<?php echo $record_type; ?>')"><?php echo $value; ?></a>
                                <?php else: echo $value; endif; ?>
                                <?php if ($is_first_name_cell && $is_archived_row): ?>
                                    <span class="status-badge archived" title="This record is archived">
                                        <i data-lucide="archive" style="width:10px;height:10px;"></i>
                                        Archived
                                    </span>
                                <?php endif; ?>
                                <?php
                                if ($column['field'] === 'date_of_registration'
                                    && in_array($record_type, ['birth', 'death', 'marriage'], true)):
                                    $late_info = detect_late_registration($record, $record_type);
                                    if ($late_info !== null):
                                        $tooltip = $late_info['is_late']
                                            ? 'Late — registered ' . $late_info['days_delayed'] . ' days after event (threshold: ' . $late_info['threshold'] . ' days)'
                                            : 'Registered within the ' . $late_info['threshold'] . '-day timely period';
                                ?>
                                    <?php if ($late_info['is_late']): ?>
                                    <span class="status-badge late-registration" title="<?php echo htmlspecialchars($tooltip); ?>">
                                        <i data-lucide="alert-triangle" style="width:10px;height:10px;"></i>
                                        Late
                                    </span>
                                    <?php else: ?>
                                    <span class="status-badge timely-registration" title="<?php echo htmlspecialchars($tooltip); ?>">
                                        <i data-lucide="check" style="width:10px;height:10px;"></i>
                                        Timely
                                    </span>
                                    <?php endif; ?>
                                <?php endif; endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <div class="action-dropdown">
                                    <button class="action-dropdown-btn" onclick="toggleActionDropdown(event, this)">
                                        <i data-lucide="more-vertical" style="width: 16px; height: 16px;"></i>
                                    </button>
                                    <div class="action-dropdown-menu">
                                        <button class="action-dropdown-item view-action"
                                                onclick="recordPreviewModal.open(<?php echo $record['id']; ?>, '<?php echo $record_type; ?>'); closeAllDropdowns();">
                                            <i data-lucide="file-text"></i>
                                            <span>View</span>
                                        </button>
                                        <?php if (hasPermission($edit_permission)): ?>
                                        <button class="action-dropdown-item edit-action"
                                                onclick="editRecord(<?php echo $record['id']; ?>, '<?php echo $config['entry_form']; ?>', <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>); closeAllDropdowns();">
                                            <i data-lucide="pen-line"></i>
                                            <span>Edit</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($can_archive): ?>
                                            <?php if ($is_archived_row): ?>
                                            <button class="action-dropdown-item unarchive-action"
                                                    onclick="toggleArchive(<?php echo $record['id']; ?>, 'unarchive', <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>); closeAllDropdowns();">
                                                <i data-lucide="archive-restore"></i>
                                                <span>Unarchive</span>
                                            </button>
                                            <?php else: ?>
                                            <button class="action-dropdown-item archive-action"
                                                    onclick="toggleArchive(<?php echo $record['id']; ?>, 'archive', <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>); closeAllDropdowns();">
                                                <i data-lucide="archive"></i>
                                                <span>Archive</span>
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                        <button class="action-dropdown-item delete-action"
                                                onclick="deleteRecord(<?php echo $record['id']; ?>, <?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>); closeAllDropdowns();">
                                            <i data-lucide="x-circle"></i>
                                            <span>Delete</span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
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
                $pagination_current_page = (int)$pagination_current_page;
                $total_pages = (int)$total_pages;

                $base_query = build_query_string(['page']);
                $query_prefix = $base_query ? '&' . $base_query : '';
                ?>

                <!-- First Page Button -->
                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === 1 ? 'disabled' : ''; ?>"
                   title="First page">
                    <i data-lucide="chevrons-left"></i>
                </a>

                <!-- Previous Page Button -->
                <a href="?page=<?php echo max(1, $pagination_current_page - 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === 1 ? 'disabled' : ''; ?>"
                   title="Previous page">
                    <i data-lucide="chevron-left"></i>
                </a>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $pagination_current_page - 2);
                $end_page = min($total_pages, $pagination_current_page + 2);

                // Show ellipsis at start if needed
                if ($start_page > 1):
                ?>
                <a href="?page=1<?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === 1 ? 'active' : ''; ?>"
                   <?php if ($pagination_current_page === 1): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>>1</a>
                <?php if ($start_page > 2): ?>
                <span class="pagination-info">...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++):
                    $is_active = $i === $pagination_current_page;
                ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $is_active ? 'active' : ''; ?>"
                   <?php if ($is_active): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>
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
                   class="pagination-btn <?php echo $pagination_current_page === $total_pages ? 'active' : ''; ?>"
                   <?php if ($pagination_current_page === $total_pages): ?>style="background: #3B82F6 !important; color: #FFFFFF !important; border-color: #3B82F6 !important; font-weight: 700 !important;"<?php endif; ?>><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <!-- Next Page Button -->
                <a href="?page=<?php echo min($total_pages, $pagination_current_page + 1); ?><?php echo $query_prefix; ?>"
                   class="pagination-btn <?php echo $pagination_current_page === $total_pages ? 'disabled' : ''; ?>"
                   title="Next page">
                    <i data-lucide="chevron-right"></i>
                </a>

                <!-- Last Page Button -->
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

        // Strip empty params from filter form before submit so the URL stays clean
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const inputs = filterForm.querySelectorAll('input[name], select[name]');
                    inputs.forEach(function(input) {
                        if (!input.value || (input.type === 'hidden' && input.name === 'type')) {
                            input.removeAttribute('name');
                        }
                    });
                });
            }
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

            // Initialize cascading dropdowns for birth records
            <?php if ($record_type === 'birth'): ?>
            initializeCascadingFilters();
            <?php endif; ?>
        });

        // Cascading Filter Logic for Birth Records
        function initializeCascadingFilters() {
            const placeTypeSelect = document.getElementById('place_type');
            const locationSelect = document.getElementById('child_place_of_birth');
            const locationGroup = locationSelect ? locationSelect.closest('.filter-group') : null;

            if (!placeTypeSelect || !locationSelect || !locationGroup) return;

            // Define location options
            const locationOptions = {
                'Barangay': [
                    'Adaoag', 'Agaman (Proper)', 'Agaman Norte', 'Agaman Sur', 'Alba', 'Annayatan',
                    'Asassi', 'Asinga-Via', 'Awallan', 'Bacagan', 'Bagunot', 'Barsat East',
                    'Barsat West', 'Bitag Grande', 'Bitag Pequeño', 'Bunugan', 'C. Verzosa (Valley Cove)',
                    'Canagatan', 'Carupian', 'Catugay', 'Dabbac Grande', 'Dalin', 'Dalla',
                    'Hacienda Intal', 'Ibulo', 'Imurung', 'J. Pallagao', 'Lasilat', 'Mabini',
                    'Masical', 'Mocag', 'Nangalinan', 'Poblacion (Centro)', 'Remus', 'San Antonio',
                    'San Francisco', 'San Isidro', 'San Jose', 'San Miguel', 'San Vicente',
                    'Santa Margarita', 'Santor', 'Taguing', 'Taguntungan', 'Tallang', 'Taytay',
                    'Temblique', 'Tungel'
                ],
                'Hospital': [
                    'Baggao District Hospital',
                    'Municipal Health Office'
                ]
            };

            // Handle place type change
            placeTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                const locationLabel = locationGroup.querySelector('label[for="child_place_of_birth"]');

                // Reset location dropdown
                locationSelect.innerHTML = '<option value="">All Locations</option>';
                locationSelect.value = '';

                if (selectedType && locationOptions[selectedType]) {
                    // Show the location filter group
                    locationGroup.classList.remove('dependent-hidden');

                    // Populate with new options
                    locationOptions[selectedType].forEach(location => {
                        const option = document.createElement('option');
                        option.value = location;
                        option.textContent = location;
                        locationSelect.appendChild(option);
                    });

                    // Enable the location dropdown
                    locationSelect.disabled = false;

                    // Update label dynamically
                    if (locationLabel) {
                        const labelText = selectedType === 'Barangay' ? 'Barangay' : 'Hospital';
                        locationLabel.textContent = labelText;
                    }
                } else {
                    // Hide and disable the location filter group
                    locationGroup.classList.add('dependent-hidden');
                    locationSelect.disabled = true;

                    // Reset label
                    if (locationLabel) {
                        locationLabel.textContent = 'Location';
                    }
                }

                // Submit form to apply the filter
                this.form.submit();
            });

            // Initialize on page load - set proper state
            const initialPlaceType = placeTypeSelect.value;
            if (!initialPlaceType || initialPlaceType === '') {
                locationGroup.classList.add('dependent-hidden');
                locationSelect.disabled = true;
            } else {
                locationGroup.classList.remove('dependent-hidden');
                locationSelect.disabled = false;

                // Update label based on initial value
                const locationLabel = locationGroup.querySelector('label[for="child_place_of_birth"]');
                if (locationLabel && initialPlaceType) {
                    const labelText = initialPlaceType === 'Barangay' ? 'Barangay' : 'Hospital';
                    locationLabel.textContent = labelText;
                }
            }
        }

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

        // Toggle action dropdown
        function toggleActionDropdown(event, button) {
            event.stopPropagation();
            const dropdown = button.closest('.action-dropdown');
            const menu = dropdown.querySelector('.action-dropdown-menu');
            const isActive = dropdown.classList.contains('active');

            // Close all dropdowns
            closeAllDropdowns();

            // Toggle current dropdown
            if (!isActive) {
                const rect = button.getBoundingClientRect();
                const menuWidth = 150;
                const menuHeight = 130; // ~3 items × 42px + borders
                const scrollX = window.pageXOffset;
                const scrollY = window.pageYOffset;

                // Use document-level coordinates so the menu scrolls with the page
                menu.style.position = 'absolute';
                menu.style.left = Math.max(4, rect.right + scrollX - menuWidth) + 'px';

                const spaceBelow = window.innerHeight - rect.bottom;
                menu.style.top = spaceBelow >= menuHeight + 8
                    ? (rect.bottom + scrollY + 4) + 'px'
                    : (rect.top + scrollY - menuHeight - 4) + 'px';

                menu.style.display = 'block';

                // Move menu to <body> to escape overflow: hidden on .table-container
                menu._dropdownOwner = dropdown;
                document.body.appendChild(menu);

                dropdown.classList.add('active');

                // Highlight the row so users know which record the menu belongs to
                const row = button.closest('tr');
                if (row) row.classList.add('row-active');
            }
        }

        // Close all dropdowns
        function closeAllDropdowns() {
            // Return any portaled menus back to their original parent
            document.querySelectorAll('body > .action-dropdown-menu').forEach(menu => {
                if (menu._dropdownOwner) {
                    menu._dropdownOwner.appendChild(menu);
                    menu._dropdownOwner = null;
                }
                menu.style.display = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.position = '';
            });
            document.querySelectorAll('.action-dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
            // Remove row highlight
            document.querySelectorAll('.records-table tbody tr.row-active').forEach(row => {
                row.classList.remove('row-active');
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.action-dropdown')) {
                closeAllDropdowns();
            }
        });

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
            // CSRF token (required by API)
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                formData.append('csrf_token', csrfMeta.content);
            }

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
                const numColumns = columns.length + 2; // +1 for row number, +1 for actions

                // Create skeleton rows
                let skeletonHTML = '';
                for (let i = 0; i < Math.min(<?php echo $records_per_page; ?>, 10); i++) {
                    skeletonHTML += '<tr class="skeleton-loading-row">';
                    for (let j = 0; j < numColumns; j++) {
                        const cellClass = j === 0 ? 'row-number' : '';
                        skeletonHTML += `<td class="${cellClass}"><div class="skeleton skeleton-text"></div></td>`;
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
            const numColumns = columns.length + 2; // +1 for row number, +1 for Actions column
            const numRows = <?php echo $records_per_page; ?>;

            let skeletonHTML = '';
            for (let i = 0; i < Math.min(numRows, 10); i++) {
                skeletonHTML += '<tr>';
                for (let j = 0; j < numColumns; j++) {
                    const cellClass = j === 0 ? 'row-number' : '';
                    skeletonHTML += `<td class="${cellClass}"><div class="skeleton skeleton-text"></div></td>`;
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
                const includeArchivedParam = <?php echo $include_archived ? '1' : '0'; ?>;
                const url = `../api/records_search.php?type=${recordType}&search=${encodeURIComponent(query)}&page=${currentPage}&per_page=<?php echo $records_per_page; ?>&include_archived=${includeArchivedParam}`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    updateTable(data.records, data.pagination, data.fuzzy === true);
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
        function updateTable(records, pagination, isFuzzy) {
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
                // Fuzzy notice row: shown when the strict search returned nothing and
                // the API fell back to a partial-match ("any token in any field") search.
                if (isFuzzy) {
                    const colspan = (recordsTable.closest('table')?.querySelectorAll('thead th').length) || 100;
                    html += `
                        <tr class="fuzzy-notice-row">
                            <td colspan="${colspan}" style="background:#fff8e1; border-left:4px solid #f5a623; padding:12px 16px; color:#8a6d3b;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <i data-lucide="search" style="width:18px; height:18px;"></i>
                                    <div>
                                        <strong>No exact match found.</strong>
                                        Showing near / possible results based on the words you typed.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                records.forEach((record, index) => {
                    html += buildTableRow(record, pagination.from + index);
                });
                recordsTable.innerHTML = html;
            }

            // Update table controls
            if (tableControls && pagination) {
                const label = isFuzzy ? 'possible matches' : 'records';
                tableControls.textContent = `Showing ${pagination.from} to ${pagination.to} of ${pagination.total_records} ${label}`;
            }

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Build table row HTML based on record type
        function buildTableRow(record, rowNumber) {
            const columns = <?php echo json_encode($config['table_columns']); ?>;
            const canArchive = <?php echo $can_archive ? 'true' : 'false'; ?>;
            const isArchived = (record.status || 'Active') === 'Archived';
            const rowClass = isArchived ? 'row-archived' : '';
            let html = `<tr class="${rowClass}" data-record-id="${record.id}" data-record-status="${escapeHtml(record.status || 'Active')}">`;

            // Checkbox column (only if archiving is enabled for this type)
            if (canArchive) {
                html += `<td class="select-cell"><input type="checkbox" class="row-checkbox" value="${record.id}" data-status="${escapeHtml(record.status || 'Active')}" onclick="updateBulkSelection()"></td>`;
            }

            // Add row number cell
            html += `<td class="row-number">${rowNumber}</td>`;

            // Build cells for each column
            const nameFields = ['child_name', 'husband_name', 'wife_name', 'deceased_name', 'groom_name', 'bride_name'];
            let firstNameShown = false;
            columns.forEach(column => {
                const value = getFieldValue(record, column.field, column.type || 'text');
                const isNameField = nameFields.includes(column.field);
                const badge = (isNameField && !firstNameShown && isArchived)
                    ? ' <span class="status-badge archived"><i data-lucide="archive" style="width:10px;height:10px;"></i> Archived</span>'
                    : '';
                const regBadge = (column.field === 'date_of_registration')
                    ? buildLateRegistrationBadge(record)
                    : '';
                if (isNameField) {
                    firstNameShown = true;
                    html += `<td><a href="javascript:void(0)" class="record-name-link" onclick="recordPreviewModal.open(${record.id}, '${recordType}')">${value}</a>${badge}</td>`;
                } else {
                    html += `<td>${value}${regBadge}</td>`;
                }
            });

            // Actions column with dropdown
            const recordDataJson = JSON.stringify(record).replace(/"/g, '&quot;');
            html += `<td>
                <div class="action-dropdown">
                    <button class="action-dropdown-btn" onclick="toggleActionDropdown(event, this)">
                        <i data-lucide="more-vertical" style="width: 16px; height: 16px;"></i>
                    </button>
                    <div class="action-dropdown-menu">
                        <button class="action-dropdown-item view-action" onclick="recordPreviewModal.open(${record.id}, '${recordType}'); closeAllDropdowns();">
                            <i data-lucide="file-text"></i>
                            <span>View</span>
                        </button>`;

            <?php if (hasPermission($edit_permission)): ?>
            html += `<button class="action-dropdown-item edit-action" onclick='editRecord(${record.id}, "<?php echo $config['entry_form']; ?>", JSON.parse("${recordDataJson}")); closeAllDropdowns();'>
                            <i data-lucide="pen-line"></i>
                            <span>Edit</span>
                        </button>`;
            <?php endif; ?>

            if (canArchive) {
                if (isArchived) {
                    html += `<button class="action-dropdown-item unarchive-action" onclick='toggleArchive(${record.id}, "unarchive", JSON.parse("${recordDataJson}")); closeAllDropdowns();'>
                                <i data-lucide="archive-restore"></i>
                                <span>Unarchive</span>
                            </button>`;
                } else {
                    html += `<button class="action-dropdown-item archive-action" onclick='toggleArchive(${record.id}, "archive", JSON.parse("${recordDataJson}")); closeAllDropdowns();'>
                                <i data-lucide="archive"></i>
                                <span>Archive</span>
                            </button>`;
                }
            }

            <?php if ($can_delete): ?>
            html += `<button class="action-dropdown-item delete-action" onclick='deleteRecord(${record.id}, JSON.parse("${recordDataJson}")); closeAllDropdowns();'>
                            <i data-lucide="x-circle"></i>
                            <span>Delete</span>
                        </button>`;
            <?php endif; ?>

            html += `</div>
                </div>
            </td>`;
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
            } else if (field === 'child_place_of_birth' && recordType === 'birth') {
                const place = record.child_place_of_birth || '';
                const barangay = record.barangay || '';
                const placeType = record.place_type || '';

                if (place) {
                    let result = place;
                    if (barangay && !place.toLowerCase().includes(barangay.toLowerCase())) {
                        result += ', ' + barangay;
                    }
                    return escapeHtml(result);
                } else if (placeType && (placeType === 'Home' || placeType === 'Other')) {
                    return escapeHtml(barangay ? placeType + ', ' + barangay : placeType);
                } else if (barangay) {
                    return escapeHtml(barangay);
                }
                return 'N/A';
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

        // Late/Timely registration badge — mirrors PHP detect_late_registration().
        // Birth/Death: 30-day window. Marriage: 15-day window (Act 3753 / PSA).
        function buildLateRegistrationBadge(record) {
            const eventFieldMap = {
                birth: 'child_date_of_birth',
                death: 'date_of_death',
                marriage: 'date_of_marriage'
            };
            const rType = '<?php echo $record_type; ?>';
            const eventField = eventFieldMap[rType];
            if (!eventField) return '';

            const threshold = (rType === 'marriage') ? 15 : 30;
            const eventDate = record[eventField];
            const regDate = record.date_of_registration;
            if (!eventDate || !regDate) return '';

            const eventTs = Date.parse(eventDate);
            const regTs = Date.parse(regDate);
            if (isNaN(eventTs) || isNaN(regTs)) return '';

            const days = Math.floor((regTs - eventTs) / 86400000);
            const isLate = days > threshold;
            const tooltip = isLate
                ? `Late — registered ${days} days after event (threshold: ${threshold} days)`
                : `Registered within the ${threshold}-day timely period`;
            const cls = isLate ? 'late-registration' : 'timely-registration';
            const icon = isLate ? 'alert-triangle' : 'check';
            const label = isLate ? 'Late' : 'Timely';
            return ` <span class="status-badge ${cls}" title="${escapeHtml(tooltip)}"><i data-lucide="${icon}" style="width:10px;height:10px;"></i> ${label}</span>`;
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

        // ==========================================================
        // ARCHIVE / UNARCHIVE — single-record and bulk
        // ==========================================================

        // "Include archived" toggle — reload page with/without param
        const includeArchivedToggle = document.getElementById('includeArchivedToggle');
        if (includeArchivedToggle) {
            includeArchivedToggle.addEventListener('change', function() {
                const url = new URL(window.location);
                if (this.checked) {
                    url.searchParams.set('include_archived', '1');
                } else {
                    url.searchParams.delete('include_archived');
                }
                url.searchParams.delete('page'); // reset to page 1
                window.location.href = url.toString();
            });
        }

        // Single-record archive/unarchive
        function toggleArchive(id, action, recordData) {
            const recordTypeLabels = {
                'birth': 'Birth Record',
                'marriage': 'Marriage Record',
                'death': 'Death Record',
                'marriage_license': 'Marriage License'
            };
            const recordType = '<?php echo $record_type; ?>';
            const recordLabel = recordTypeLabels[recordType] || 'Record';
            const verb = action === 'archive' ? 'Archive' : 'Unarchive';
            const dialogTitle = `${verb} ${recordLabel}`;

            let details = '';
            if (recordData && typeof getRecordDetails === 'function') {
                details = getRecordDetails(recordData, recordType) || '';
            }

            const actionColor = action === 'archive' ? '#D97706' : '#059669';
            const actionNote = action === 'archive'
                ? 'This record will be hidden from the main list but kept for legal/historical reference.'
                : 'This record will return to the main list as Active.';
            const message = `Are you sure you want to ${action} this record?<br><br>` +
                details +
                `<br><br><span style="color: ${actionColor}; font-weight: 600;">${actionNote}</span>`;

            if (typeof Notiflix === 'undefined') {
                if (confirm(message.replace(/<[^>]+>/g, ''))) {
                    performArchiveToggle(id, action);
                }
                return;
            }

            Notiflix.Confirm.show(
                dialogTitle,
                message,
                'Cancel',
                verb,
                function okCb() { /* cancelled */ },
                function cancelCb() { performArchiveToggle(id, action); },
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
                    cancelButtonBackground: actionColor,
                    buttonsFontSize: '15px',
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        function performArchiveToggle(id, action) {
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle(action === 'archive' ? 'Archiving...' : 'Unarchiving...');
            }

            const formData = new FormData();
            formData.append('record_id', id);
            formData.append('record_type', '<?php echo $record_type; ?>');
            formData.append('action', action);

            fetch('../api/archive_toggle.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();
                if (data.success) {
                    if (typeof Notiflix !== 'undefined') Notiflix.Notify.success(data.message);
                    setTimeout(() => location.reload(), 1000);
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
                    Notiflix.Notify.failure('An error occurred. Please try again.');
                }
            });
        }

        // ===== Bulk selection =====

        function getSelectedCheckboxes() {
            return Array.from(document.querySelectorAll('.row-checkbox:checked'));
        }

        function updateBulkSelection() {
            const bar = document.getElementById('bulkActionBar');
            if (!bar) return;

            const selected = getSelectedCheckboxes();
            const count = selected.length;
            const countEl = document.getElementById('bulkSelectedCount');
            if (countEl) countEl.textContent = count;

            if (count === 0) {
                bar.classList.remove('active');
                return;
            }
            bar.classList.add('active');

            // Count statuses of selected rows to decide which bulk action makes sense
            let activeCount = 0, archivedCount = 0;
            selected.forEach(cb => {
                const s = cb.getAttribute('data-status') || 'Active';
                if (s === 'Archived') archivedCount++;
                else activeCount++;
            });

            // Show Archive button if any Active rows are selected;
            // show Unarchive button if any Archived rows are selected.
            const archiveBtn = document.getElementById('bulkArchiveBtn');
            const unarchiveBtn = document.getElementById('bulkUnarchiveBtn');
            if (archiveBtn) archiveBtn.style.display = activeCount > 0 ? '' : 'none';
            if (unarchiveBtn) unarchiveBtn.style.display = archivedCount > 0 ? '' : 'none';

            // Sync the select-all checkbox state
            const selectAll = document.getElementById('selectAllRows');
            if (selectAll) {
                const allCheckboxes = document.querySelectorAll('.row-checkbox');
                selectAll.checked = count === allCheckboxes.length && count > 0;
                selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
            }
        }

        function toggleSelectAllRows(source) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => { cb.checked = source.checked; });
            updateBulkSelection();
        }

        function clearBulkSelection() {
            document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
            const selectAll = document.getElementById('selectAllRows');
            if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
            updateBulkSelection();
        }

        function bulkArchiveSelected() { bulkArchiveAction('archive'); }
        function bulkUnarchiveSelected() { bulkArchiveAction('unarchive'); }

        function bulkArchiveAction(action) {
            const selected = getSelectedCheckboxes();
            // Only send IDs whose current status matches the starting state for the action
            const requiredStartStatus = action === 'archive' ? 'Active' : 'Archived';
            const ids = selected
                .filter(cb => (cb.getAttribute('data-status') || 'Active') === requiredStartStatus)
                .map(cb => cb.value);

            if (ids.length === 0) {
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.warning('No eligible records selected for this action.');
                }
                return;
            }

            const verb = action === 'archive' ? 'Archive' : 'Unarchive';
            const actionColor = action === 'archive' ? '#D97706' : '#059669';
            const noun = ids.length === 1 ? 'record' : 'records';
            const dialogTitle = `${verb} ${ids.length} ${noun}`;
            const message = `Are you sure you want to ${action} <strong>${ids.length}</strong> ${noun}?<br><br>` +
                `<span style="color: ${actionColor}; font-weight: 600;">` +
                (action === 'archive'
                    ? 'Archived records will be hidden from the main list but retained.'
                    : 'Unarchived records will return to the main list as Active.') +
                `</span>`;

            if (typeof Notiflix === 'undefined') {
                if (confirm(message.replace(/<[^>]+>/g, ''))) {
                    performBulkArchive(action, ids);
                }
                return;
            }

            Notiflix.Confirm.show(
                dialogTitle,
                message,
                'Cancel',
                verb + ' All',
                function okCb() { /* cancelled */ },
                function cancelCb() { performBulkArchive(action, ids); },
                {
                    width: '500px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 800,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: actionColor,
                    buttonsFontSize: '15px',
                    buttonsBorderRadius: '60px',
                    cssAnimationStyle: 'zoom',
                    cssAnimationDuration: 250,
                    distance: '24px',
                    backOverlayColor: 'rgba(0,0,0,0.6)',
                }
            );
        }

        function performBulkArchive(action, ids) {
            if (typeof Notiflix !== 'undefined') {
                Notiflix.Loading.circle(action === 'archive' ? 'Archiving records...' : 'Unarchiving records...');
            }

            const formData = new FormData();
            formData.append('record_type', '<?php echo $record_type; ?>');
            formData.append('action', action);
            ids.forEach(id => formData.append('ids[]', id));

            fetch('../api/archive_bulk.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading.remove();
                if (data.success) {
                    if (typeof Notiflix !== 'undefined') Notiflix.Notify.success(data.message);
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
                    Notiflix.Notify.failure('An error occurred. Please try again.');
                }
            });
        }
    </script>

    <!-- Record Preview Modal Script -->
    <script src="../assets/js/record-preview-modal.js?v=4"></script>
</body>
</html>
