<?php
/**
 * RA 9048 Records — Search API
 * Returns paginated, searchable records for Petition / Legal Instrument / Court Decree
 */

header('Content-Type: application/json');

require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireAuth();

// Parameters
$record_type = sanitize_input($_GET['type'] ?? 'petition');
$search      = sanitize_input($_GET['search'] ?? '');
$page        = max(1, intval($_GET['page'] ?? 1));
$per_page    = max(1, min(100, intval($_GET['per_page'] ?? 10)));
$date_from   = sanitize_input($_GET['date_from'] ?? '');
$date_to     = sanitize_input($_GET['date_to'] ?? '');

$valid_types = ['petition', 'legal_instrument', 'court_decree'];
if (!in_array($record_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record type']);
    exit;
}

// Configuration per type
$configs = [
    'petition' => [
        'table' => 'petitions',
        'search_fields' => ['document_owner_names', 'petitioner_names', 'petition_of', 'remarks', 'petition_number'],
        'columns' => ['id', 'petition_number', 'petition_type', 'petition_subtype',
                      'date_of_filing', 'document_owner_names', 'petitioner_names',
                      'document_type', 'petition_of', 'special_law', 'fee_amount', 'remarks',
                      'status_workflow',
                      'pdf_filename', 'status', 'created_at'],
        'date_field' => 'date_of_filing',
    ],
    'legal_instrument' => [
        'table' => 'legal_instruments',
        'search_fields' => ['document_owner_names', 'affiant_names', 'father_name', 'mother_name',
                           'registry_number', 'supplemental_info', 'remarks'],
        'columns' => ['id', 'instrument_type', 'date_of_filing', 'document_owner_names', 'father_name',
                      'mother_name', 'affiant_names', 'document_type', 'registry_number',
                      'supplemental_info', 'legitimation_date', 'applicable_law', 'remarks',
                      'pdf_filename', 'status', 'created_at'],
        'date_field' => 'date_of_filing',
    ],
    'court_decree' => [
        'table' => 'court_decrees',
        'search_fields' => ['document_owner_names', 'petitioner_names', 'case_number',
                           'court_region', 'court_branch', 'court_city_municipality', 'court_province',
                           'decree_details', 'registry_number', 'remarks'],
        'columns' => ['id', 'decree_type', 'decree_type_other', 'court_region', 'court_branch',
                      'court_city_municipality', 'court_province', 'case_number', 'date_of_decree',
                      'date_of_filing', 'document_owner_names', 'petitioner_names', 'document_type',
                      'registry_number', 'decree_details', 'remarks', 'pdf_filename', 'status', 'created_at'],
        'date_field' => 'date_of_filing',
    ],
];

$config = $configs[$record_type];

try {
    $where_clauses = ["status = 'Active'"];
    $params = [];

    // Search tokens
    if (!empty($search)) {
        $tokens = preg_split('/\s+/', trim($search));
        $token_clauses = [];
        $idx = 0;
        foreach ($tokens as $token) {
            if ($token === '') continue;
            $field_matches = [];
            foreach ($config['search_fields'] as $field) {
                $param = ':s_' . $idx++;
                $field_matches[] = "{$field} LIKE {$param}";
                $params[$param] = "%{$token}%";
            }
            $token_clauses[] = '(' . implode(' OR ', $field_matches) . ')';
        }
        if (!empty($token_clauses)) {
            $where_clauses[] = '(' . implode(' AND ', $token_clauses) . ')';
        }
    }

    // Date range filter
    if (!empty($date_from)) {
        $params[':date_from'] = $date_from;
        $where_clauses[] = "{$config['date_field']} >= :date_from";
    }
    if (!empty($date_to)) {
        $params[':date_to'] = $date_to;
        $where_clauses[] = "{$config['date_field']} <= :date_to";
    }

    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
    $columns_str = implode(', ', $config['columns']);

    // Count
    $count_stmt = $pdo_ra->prepare("SELECT COUNT(*) AS total FROM {$config['table']}{$where_sql}");
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = (int) $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $offset = ($page - 1) * $per_page;
    $total_pages = $total_records > 0 ? (int) ceil($total_records / $per_page) : 0;

    // Fetch records
    $query = "SELECT {$columns_str} FROM {$config['table']}{$where_sql} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}";
    $stmt = $pdo_ra->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'current_page'  => $page,
            'total_pages'   => $total_pages,
            'total_records' => $total_records,
            'per_page'      => $per_page,
            'from'          => $total_records > 0 ? $offset + 1 : 0,
            'to'            => min($offset + $per_page, $total_records),
        ],
    ]);

} catch (PDOException $e) {
    error_log("RA9048 records search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
