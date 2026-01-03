<?php
/**
 * Live Search API Endpoint
 * Returns search results for records in real-time
 */

header('Content-Type: application/json');
require_once '../includes/auth.php';

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
        'table' => 'marriage_records',
        'search_fields' => [
            'registry_no',
            'husband_first_name', 'husband_middle_name', 'husband_last_name',
            'wife_first_name', 'wife_middle_name', 'wife_last_name',
            'place_of_marriage'
        ],
        'columns' => ['id', 'registry_no', 'husband_first_name', 'husband_middle_name', 'husband_last_name',
                     'wife_first_name', 'wife_middle_name', 'wife_last_name', 'date_of_marriage',
                     'place_of_marriage', 'pdf_filename']
    ],
    'birth' => [
        'table' => 'birth_records',
        'search_fields' => [
            'registry_no',
            'child_first_name', 'child_middle_name', 'child_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'place_of_birth'
        ],
        'columns' => ['id', 'registry_no', 'child_first_name', 'child_middle_name', 'child_last_name',
                     'child_date_of_birth', 'child_sex', 'father_first_name', 'father_middle_name', 'father_last_name',
                     'mother_first_name', 'mother_middle_name', 'mother_last_name', 'date_of_registration', 'pdf_filename']
    ],
    'death' => [
        'table' => 'death_records',
        'search_fields' => [
            'registry_no',
            'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
            'place_of_death'
        ],
        'columns' => ['id', 'registry_no', 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
                     'date_of_death', 'age_at_death', 'place_of_death', 'date_of_registration', 'pdf_filename']
    ]
];

$config = $configs[$record_type];

try {
    // Build search query
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
        $search_query = " WHERE " . implode(' OR ', $search_conditions);
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM {$config['table']}{$search_query}";
    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Calculate pagination
    $offset = ($page - 1) * $per_page;
    $total_pages = ceil($total_records / $per_page);

    // Get records
    $columns_str = implode(', ', $config['columns']);
    $query = "SELECT {$columns_str} FROM {$config['table']}{$search_query}
              ORDER BY id DESC
              LIMIT {$per_page} OFFSET {$offset}";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'per_page' => $per_page,
            'from' => $offset + 1,
            'to' => min($offset + $per_page, $total_records)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
