<?php
/**
 * Folder Browse API
 * Returns folder tree structure and records for the folder browser page.
 *
 * Actions:
 *   ?action=tree           — folder hierarchy with record counts
 *   ?action=list&type=...  — records in a specific folder path
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'tree') {
        handle_tree($pdo);
    } elseif ($action === 'list') {
        handle_list($pdo);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    error_log('folder_browse error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}

function handle_tree(PDO $pdo) {
    $tables = [
        'birth' => [
            'table' => 'certificate_of_live_birth',
            'label' => 'Birth',
            'permission' => 'birth_view',
        ],
        'death' => [
            'table' => 'certificate_of_death',
            'label' => 'Death',
            'permission' => 'death_view',
        ],
        'marriage' => [
            'table' => 'certificate_of_marriage',
            'label' => 'Marriage',
            'permission' => 'marriage_view',
        ],
        'marriage_license' => [
            'table' => 'application_for_marriage_license',
            'label' => 'Marriage License',
            'permission' => 'marriage_license_view',
        ],
    ];

    $tree = [];

    foreach ($tables as $type => $def) {
        if (!hasPermission($def['permission'])) continue;

        $tbl = $def['table'];
        $rows = $pdo->query(
            "SELECT pdf_filename FROM `{$tbl}` WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status = 'Active'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $folders = [];
        $total = 0;

        foreach ($rows as $path) {
            $parts = explode('/', $path);
            if (count($parts) < 2) continue;

            $total++;

            if (count($parts) === 4 && $parts[0] === $type) {
                $year = $parts[1];
                $lastName = $parts[2];
            } elseif (count($parts) === 3 && $parts[0] === $type) {
                if (ctype_digit($parts[1])) {
                    $year = $parts[1];
                    $lastName = null;
                } else {
                    $year = null;
                    $lastName = $parts[1];
                }
            } elseif (count($parts) === 2 && $parts[0] === $type) {
                $year = null;
                $lastName = null;
            } else {
                continue;
            }

            $yearKey = $year ?? '__no_year__';
            if (!isset($folders[$yearKey])) {
                $folders[$yearKey] = ['count' => 0, 'children' => []];
            }
            $folders[$yearKey]['count']++;

            if ($lastName !== null) {
                if (!isset($folders[$yearKey]['children'][$lastName])) {
                    $folders[$yearKey]['children'][$lastName] = 0;
                }
                $folders[$yearKey]['children'][$lastName]++;
            }
        }

        ksort($folders);
        $yearNodes = [];
        foreach ($folders as $yearKey => $data) {
            ksort($data['children']);
            $children = [];
            foreach ($data['children'] as $name => $count) {
                $children[] = ['name' => $name, 'count' => $count];
            }
            $yearNodes[] = [
                'year' => $yearKey === '__no_year__' ? null : $yearKey,
                'label' => $yearKey === '__no_year__' ? 'No Year' : $yearKey,
                'count' => $data['count'],
                'children' => $children,
            ];
        }

        usort($yearNodes, function ($a, $b) {
            if ($a['year'] === null) return 1;
            if ($b['year'] === null) return -1;
            return (int)$b['year'] - (int)$a['year'];
        });

        $tree[] = [
            'type' => $type,
            'label' => $def['label'],
            'count' => $total,
            'children' => $yearNodes,
        ];
    }

    echo json_encode(['success' => true, 'tree' => $tree]);
}

function handle_list(PDO $pdo) {
    $type = $_GET['type'] ?? '';
    $year = $_GET['year'] ?? null;
    $lastName = $_GET['last_name'] ?? null;
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));

    $tableDefs = [
        'birth' => [
            'table' => 'certificate_of_live_birth',
            'permission' => 'birth_view',
            'columns' => 'id, registry_no, child_first_name, child_middle_name, child_last_name, child_sex,
                          child_date_of_birth, father_first_name, father_middle_name, father_last_name,
                          mother_first_name, mother_middle_name, mother_last_name,
                          date_of_registration,
                          pdf_filename, status, created_at',
            'search_fields' => ['registry_no', 'child_first_name', 'child_middle_name', 'child_last_name',
                                'father_first_name', 'father_last_name', 'mother_first_name', 'mother_last_name'],
            'order' => 'registry_no DESC, created_at DESC',
        ],
        'death' => [
            'table' => 'certificate_of_death',
            'permission' => 'death_view',
            'columns' => 'id, registry_no, deceased_first_name, deceased_middle_name, deceased_last_name, sex,
                          date_of_death, date_of_birth, age, age_unit, place_of_death,
                          father_first_name, father_middle_name, father_last_name,
                          mother_first_name, mother_middle_name, mother_last_name,
                          date_of_registration,
                          pdf_filename, status, created_at',
            'search_fields' => ['registry_no', 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
                                'father_first_name', 'father_last_name', 'mother_first_name', 'mother_last_name'],
            'order' => 'registry_no DESC, created_at DESC',
        ],
        'marriage' => [
            'table' => 'certificate_of_marriage',
            'permission' => 'marriage_view',
            'columns' => 'id, registry_no, husband_first_name, husband_middle_name, husband_last_name,
                          wife_first_name, wife_middle_name, wife_last_name,
                          date_of_marriage, place_of_marriage,
                          date_of_registration,
                          pdf_filename, status, created_at',
            'search_fields' => ['registry_no', 'husband_first_name', 'husband_last_name',
                                'wife_first_name', 'wife_last_name'],
            'order' => 'registry_no DESC, created_at DESC',
        ],
        'marriage_license' => [
            'table' => 'application_for_marriage_license',
            'permission' => 'marriage_license_view',
            'columns' => 'id, registry_no, groom_first_name, groom_middle_name, groom_last_name,
                          bride_first_name, bride_middle_name, bride_last_name,
                          date_of_application,
                          pdf_filename, status, created_at',
            'search_fields' => ['registry_no', 'groom_first_name', 'groom_last_name',
                                'bride_first_name', 'bride_last_name'],
            'order' => 'registry_no DESC, created_at DESC',
        ],
    ];

    if (!isset($tableDefs[$type])) {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        return;
    }

    $def = $tableDefs[$type];
    if (!hasPermission($def['permission'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }

    $tbl = $def['table'];
    $where = ["status = 'Active'"];
    $params = [];

    if ($year !== null && $year !== '') {
        $where[] = "pdf_filename LIKE :year_pattern";
        if ($lastName !== null && $lastName !== '') {
            $params[':year_pattern'] = "{$type}/{$year}/{$lastName}/%";
        } else {
            $params[':year_pattern'] = "{$type}/{$year}/%";
        }
    } elseif ($lastName !== null && $lastName !== '') {
        $where[] = "(pdf_filename LIKE :ln_pattern_a OR pdf_filename LIKE :ln_pattern_b)";
        $params[':ln_pattern_a'] = "{$type}/{$lastName}/%";
        $params[':ln_pattern_b'] = "{$type}/%/{$lastName}/%";
    } else {
        $where[] = "pdf_filename LIKE :type_pattern";
        $params[':type_pattern'] = "{$type}/%";
    }

    if ($search !== '') {
        $searchClauses = [];
        foreach ($def['search_fields'] as $i => $f) {
            $searchClauses[] = "`{$f}` LIKE :search_{$i}";
            $params[":search_{$i}"] = "%{$search}%";
        }
        $where[] = '(' . implode(' OR ', $searchClauses) . ')';
    }

    $whereSQL = implode(' AND ', $where);

    $countSQL = "SELECT COUNT(*) FROM `{$tbl}` WHERE {$whereSQL}";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $dataSQL = "SELECT {$def['columns']} FROM `{$tbl}` WHERE {$whereSQL} ORDER BY {$def['order']} LIMIT {$perPage} OFFSET {$offset}";
    $dataStmt = $pdo->prepare($dataSQL);
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'type' => $type,
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $perPage,
            'from' => $totalRecords > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $totalRecords),
        ],
    ]);
}
