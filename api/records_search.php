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
$valid_types = ['marriage', 'birth', 'death', 'marriage_license'];
if (!in_array($record_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record type']);
    exit;
}

// Check permissions
$permission_map = [
    'marriage' => 'marriage_view',
    'birth' => 'birth_view',
    'death' => 'death_view',
    'marriage_license' => 'marriage_license_view'
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
            'registry_no_status',
            'husband_first_name', 'husband_middle_name', 'husband_last_name',
            'wife_first_name', 'wife_middle_name', 'wife_last_name',
            'place_of_marriage'
        ],
        'columns' => ['id', 'status', 'registry_no', 'registry_no_status', 'date_of_registration_format', 'date_of_registration_partial_month', 'date_of_registration_partial_year', 'date_of_registration_partial_day', 'husband_first_name', 'husband_middle_name', 'husband_last_name',
                     'wife_first_name', 'wife_middle_name', 'wife_last_name', 'date_of_marriage',
                     'place_of_marriage', 'date_of_registration', 'pdf_filename']
    ],
    'birth' => [
        'table' => 'certificate_of_live_birth',
        'search_fields' => [
            'registry_no',
            'registry_no_status',
            'child_first_name', 'child_middle_name', 'child_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'child_date_of_birth', 'child_place_of_birth', 'barangay', 'place_type'
        ],
        'columns' => ['id', 'status', 'registry_no', 'registry_no_status', 'date_of_registration_format', 'date_of_registration_partial_month', 'date_of_registration_partial_year', 'date_of_registration_partial_day', 'child_first_name', 'child_middle_name', 'child_last_name',
                     'child_date_of_birth', 'time_of_birth', 'child_sex', 'child_place_of_birth', 'place_type', 'barangay',
                     'father_first_name', 'father_middle_name', 'father_last_name',
                     'father_citizenship', 'mother_first_name', 'mother_middle_name', 'mother_last_name',
                     'mother_citizenship', 'date_of_registration', 'pdf_filename']
    ],
    'death' => [
        'table' => 'certificate_of_death',
        'search_fields' => [
            'registry_no',
            'registry_no_status',
            'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'place_of_death', 'occupation'
        ],
        'columns' => ['id', 'status', 'registry_no', 'registry_no_status', 'date_of_registration_format', 'date_of_registration_partial_month', 'date_of_registration_partial_year', 'date_of_registration_partial_day', 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
                     'date_of_birth', 'date_of_death', 'age', 'sex', 'occupation', 'place_of_death',
                     'father_first_name', 'father_middle_name', 'father_last_name',
                     'mother_first_name', 'mother_middle_name', 'mother_last_name',
                     'date_of_registration', 'pdf_filename']
    ],
    'marriage_license' => [
        'table' => 'application_for_marriage_license',
        'search_fields' => [
            'registry_no', 'registry_no_status',
            'groom_first_name', 'groom_middle_name', 'groom_last_name',
            'bride_first_name', 'bride_middle_name', 'bride_last_name'
        ],
        'columns' => ['id', 'status', 'registry_no', 'registry_no_status',
                     'groom_first_name', 'groom_middle_name', 'groom_last_name',
                     'bride_first_name', 'bride_middle_name', 'bride_last_name',
                     'date_of_application', 'pdf_filename']
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

/**
 * Build a ranked typo-tolerant fallback. Longer text tokens use Soundex on
 * names and locations, plus one-character typo matching on name fields.
 * Dates and registry numbers stay literal.
 * Returns [where_fragment, where_params, score_sql, score_params].
 */
function build_fuzzy_search_clause(array $tokens, array $search_fields): array {
    if (empty($tokens)) {
        return ['', [], null, []];
    }

    $phonetic_fields = [];
    $name_fields = [];
    foreach ($search_fields as $field) {
        if (strpos($field, 'date') === false && strpos($field, 'registry_no') === false) {
            $phonetic_fields[] = $field;
        }
        if (preg_match('/(?:^|_)(?:first_name|middle_name|last_name)$/', $field)) {
            $name_fields[] = $field;
        }
    }

    $build_token_group = function (string $token, string $prefix, array &$params, int &$index) use ($search_fields, $phonetic_fields, $name_fields): string {
        $clauses = [];
        foreach ($search_fields as $field) {
            $name = ':' . $prefix . '_' . $index++;
            $clauses[] = "{$field} LIKE {$name}";
            $params[$name] = "%{$token}%";
        }

        if (strlen($token) >= 4 && preg_match('/^[\p{L}\p{N}]+$/u', $token)) {
            foreach ($phonetic_fields as $field) {
                $name = ':' . $prefix . '_' . $index++;
                $clauses[] = "SOUNDEX({$field}) = SOUNDEX({$name})";
                $params[$name] = $token;

                $last_word_name = ':' . $prefix . '_' . $index++;
                $clauses[] = "SOUNDEX(SUBSTRING_INDEX(TRIM({$field}), ' ', -1)) = SOUNDEX({$last_word_name})";
                $params[$last_word_name] = $token;
            }
        }

        // Catch a single inserted, omitted, or substituted character in names.
        // The exact search already ran first, so this broader match is only a fallback.
        $characters = preg_match('/^[\p{L}\p{N}]+$/u', $token)
            ? preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY)
            : false;
        if ($characters !== false && count($characters) >= 5 && count($characters) <= 32 && $name_fields) {
            $variants = [];
            $length = count($characters);
            for ($position = 0; $position <= $length; $position++) {
                $before = implode('', array_slice($characters, 0, $position));
                $after = implode('', array_slice($characters, $position));
                $variants[] = preg_quote($before, '~') . '.' . preg_quote($after, '~');
            }
            for ($position = 0; $position < $length; $position++) {
                $before = implode('', array_slice($characters, 0, $position));
                $after = implode('', array_slice($characters, $position + 1));
                $variants[] = preg_quote($before, '~') . '.' . preg_quote($after, '~');
                $variants[] = preg_quote($before . $after, '~');
            }
            $pattern = implode('|', array_unique($variants));
            foreach ($name_fields as $field) {
                $name = ':' . $prefix . '_' . $index++;
                $clauses[] = "{$field} REGEXP {$name}";
                $params[$name] = $pattern;
            }
        }

        return '(' . implode(' OR ', $clauses) . ')';
    };

    $where_groups = [];
    $where_params = [];
    $where_index = 0;
    $score_groups = [];
    $score_params = [];
    $score_index = 0;

    foreach ($tokens as $token) {
        $where_groups[] = $build_token_group($token, 'fz_w', $where_params, $where_index);
        $score_groups[] = $build_token_group($token, 'fz_r', $score_params, $score_index);
    }

    $where_score_parts = [];
    $rank_score_parts = [];
    foreach ($where_groups as $group) {
        $where_score_parts[] = "(CASE WHEN {$group} THEN 1 ELSE 0 END)";
    }
    foreach ($score_groups as $group) {
        $rank_score_parts[] = "(CASE WHEN {$group} THEN 1 ELSE 0 END)";
    }

    $minimum_matches = max(1, (int)ceil(count($tokens) * 0.6));
    $where_score = '(' . implode(' + ', $where_score_parts) . ')';
    $rank_score = '(' . implode(' + ', $rank_score_parts) . ')';

    return [
        ' AND (' . $where_score . ' >= ' . $minimum_matches . ')',
        $where_params,
        $rank_score,
        $score_params
    ];
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
    $tokens = array_values(array_unique($tokens));

    // Status filter: Active by default, include Archived when requested. Never include Deleted.
    $status_in_list = $include_archived ? "'Active','Archived'" : "'Active'";
    $base_where = " WHERE status IN ({$status_in_list})";

    // First pass: strict multi-token search (all tokens must match).
    [$search_query, $params] = build_search_clause($tokens, $config['search_fields'], 'strict');
    $is_fuzzy = false;

    $run_query = function (string $where_extra, array $bind_params, ?string $relevance_sql = null, array $relevance_params = []) use ($pdo, $config, $base_where, &$page, $per_page) {
        $columns_str = implode(', ', $config['columns']);

        $count_query = "SELECT COUNT(*) as total FROM {$config['table']}{$base_where}{$where_extra}";
        $count_stmt = $pdo->prepare($count_query);
        foreach ($bind_params as $key => $value) {
            $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $count_stmt->execute();
        $total_records = (int) $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $total_pages = $total_records > 0 ? (int) ceil($total_records / $per_page) : 0;
        $page = min($page, max(1, $total_pages));
        $offset = ($page - 1) * $per_page;

        $order_by = $relevance_sql !== null ? "{$relevance_sql} DESC, id DESC" : 'id DESC';

        $query = "SELECT {$columns_str} FROM {$config['table']}{$base_where}{$where_extra}
                  ORDER BY {$order_by}
                  LIMIT {$per_page} OFFSET {$offset}";
        $stmt = $pdo->prepare($query);
        foreach (array_merge($bind_params, $relevance_params) as $key => $value) {
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
    $can_try_fuzzy = count($tokens) > 1;
    if (count($tokens) === 1 && preg_match('/^[\p{L}\p{N}]{4,}$/u', $tokens[0])) {
        $can_try_fuzzy = true;
    }

    if ($total_records === 0 && $can_try_fuzzy) {
        [$fuzzy_query, $fuzzy_params, $fuzzy_rank_sql, $fuzzy_rank_params] = build_fuzzy_search_clause($tokens, $config['search_fields']);
        [$records, $total_records, $total_pages, $offset] = $run_query($fuzzy_query, $fuzzy_params, $fuzzy_rank_sql, $fuzzy_rank_params);
        if ($total_records > 0) {
            $is_fuzzy = true;
        }
    }

    // Batch-load double registration link status for all visible records
    $record_link_map = [];
    if (!empty($records)) {
        $visible_ids = array_column($records, 'id');
        try {
            require_once __DIR__ . '/../includes/functions.php';
            $record_link_map = get_record_links_batch($pdo, $record_type, $visible_ids);
        } catch (Exception $e) {
            error_log("Record link batch query error: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'records' => $records,
        'record_link_map' => $record_link_map,
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
