<?php
/**
 * Live Search API Endpoint
 * Returns search results for records in real-time
 */

header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get parameters
$record_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'marriage';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
$include_archived = isset($_GET['include_archived']) && $_GET['include_archived'] === '1';

// Validate record type
$valid_types = ['marriage', 'birth', 'death'];
if (!in_array($record_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record type']);
    exit;
}

// Check permissions
$permission_map = [
    'marriage' => 'marriage_view',
    'birth' => 'birth_view',
    'death' => 'death_view'
];

if (!hasPermission($permission_map[$record_type])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Get configuration based on record type
$configs = [
    'marriage' => [
        'table' => 'certificate_of_marriage',
        'search_fields' => [
            'registry_no',
            'husband_first_name', 'husband_middle_name', 'husband_last_name',
            'wife_first_name', 'wife_middle_name', 'wife_last_name',
            'place_of_marriage'
        ],
        'columns' => ['id', 'status', 'registry_no', 'husband_first_name', 'husband_middle_name', 'husband_last_name',
                     'wife_first_name', 'wife_middle_name', 'wife_last_name', 'date_of_marriage',
                     'place_of_marriage', 'date_of_registration', 'pdf_filename']
    ],
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'search_fields' => [
            'registry_no',
            'child_first_name', 'child_middle_name', 'child_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'child_place_of_birth', 'barangay'
        ],
        'columns' => ['id', 'status', 'registry_no', 'child_first_name', 'child_middle_name', 'child_last_name',
                     'child_date_of_birth', 'time_of_birth', 'child_sex', 'barangay',
                     'father_first_name', 'father_middle_name', 'father_last_name',
                     'father_citizenship', 'mother_first_name', 'mother_middle_name', 'mother_last_name',
                     'mother_citizenship', 'date_of_registration', 'pdf_filename']
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'search_fields' => [
            'registry_no',
            'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'place_of_death', 'occupation'
        ],
        'columns' => ['id', 'status', 'registry_no', 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
                     'date_of_birth', 'date_of_death', 'age', 'sex', 'occupation', 'place_of_death',
                     'father_first_name', 'father_middle_name', 'father_last_name',
                     'mother_first_name', 'mother_middle_name', 'mother_last_name',
                     'date_of_registration', 'pdf_filename']
    ]
];

$config = $configs[$record_type];

/**
 * Build the WHERE clause for a given search mode.
 *
 * Modes:
 *  - 'strict': every token must match at least one search field (AND across tokens,
 *    OR across fields). This makes "Juan Dela Cruz" find rows where "Juan" matches
 *    first_name AND "Dela Cruz" matches last_name (or any combination).
 *  - 'fuzzy':  ANY token matching ANY field is a hit (pure OR). Used as a fallback
 *    to surface near / possible matches when strict returns zero rows.
 *
 * Returns [sql_fragment, params_array].
 */
function build_search_clause(array $tokens, array $search_fields, string $mode): array {
    $params = [];
    if (empty($tokens)) {
        return ['', $params];
    }

    $param_index = 0;

    if ($mode === 'strict') {
        $token_clauses = [];
        foreach ($tokens as $token) {
            $field_clauses = [];
            foreach ($search_fields as $field) {
                $param_name = ':s_' . $param_index++;
                $field_clauses[] = "{$field} LIKE {$param_name}";
                $params[$param_name] = "%{$token}%";
            }
            $token_clauses[] = '(' . implode(' OR ', $field_clauses) . ')';
        }
        return [' AND (' . implode(' AND ', $token_clauses) . ')', $params];
    }

    // fuzzy: any token in any field
    $field_clauses = [];
    foreach ($tokens as $token) {
        foreach ($search_fields as $field) {
            $param_name = ':s_' . $param_index++;
            $field_clauses[] = "{$field} LIKE {$param_name}";
            $params[$param_name] = "%{$token}%";
        }
    }
    return [' AND (' . implode(' OR ', $field_clauses) . ')', $params];
}

try {
    // Tokenize the search query on whitespace. Multi-word names like
    // "Dela Cruz" are handled naturally because each token only needs to
    // match SOME field — "Dela" and "Cruz" will both land in last_name.
    $tokens = [];
    if (!empty($search)) {
        $raw_tokens = preg_split('/\s+/', trim($search));
        foreach ($raw_tokens as $t) {
            if ($t !== '') {
                $tokens[] = $t;
            }
        }
    }

    // Status filter: Active by default, include Archived when requested. Never include Deleted.
    $status_in_list = $include_archived ? "'Active','Archived'" : "'Active'";
    $base_where = " WHERE status IN ({$status_in_list})";

    // First pass: strict multi-token search (all tokens must match).
    [$search_query, $params] = build_search_clause($tokens, $config['search_fields'], 'strict');
    $is_fuzzy = false;

    $run_query = function (string $where_extra, array $bind_params) use ($pdo, $config, $base_where, $page, $per_page) {
        $columns_str = implode(', ', $config['columns']);

        $count_query = "SELECT COUNT(*) as total FROM {$config['table']}{$base_where}{$where_extra}";
        $count_stmt = $pdo->prepare($count_query);
        foreach ($bind_params as $key => $value) {
            $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $count_stmt->execute();
        $total_records = (int) $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $offset = ($page - 1) * $per_page;
        $total_pages = $total_records > 0 ? (int) ceil($total_records / $per_page) : 0;

        $query = "SELECT {$columns_str} FROM {$config['table']}{$base_where}{$where_extra}
                  ORDER BY id DESC
                  LIMIT {$per_page} OFFSET {$offset}";
        $stmt = $pdo->prepare($query);
        foreach ($bind_params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [$records, $total_records, $total_pages, $offset];
    };

    [$records, $total_records, $total_pages, $offset] = $run_query($search_query, $params);

    // Fallback: if a multi-token strict search found nothing, rerun as a fuzzy
    // OR search to surface near / possible matches. Only triggers when the user
    // actually typed more than one token — a single-token search has no "near"
    // interpretation beyond the literal LIKE it already ran.
    if ($total_records === 0 && count($tokens) > 1) {
        [$fuzzy_query, $fuzzy_params] = build_search_clause($tokens, $config['search_fields'], 'fuzzy');
        [$records, $total_records, $total_pages, $offset] = $run_query($fuzzy_query, $fuzzy_params);
        if ($total_records > 0) {
            $is_fuzzy = true;
        }
    }

    echo json_encode([
        'success' => true,
        'records' => $records,
        'fuzzy' => $is_fuzzy,
        'search_tokens' => $tokens,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'per_page' => $per_page,
            'from' => $total_records > 0 ? $offset + 1 : 0,
            'to' => min($offset + $per_page, $total_records)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
