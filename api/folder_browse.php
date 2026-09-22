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
require_once '../includes/reorganize_uploads.php';
require_once '../includes/crf_3a.php';

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
        'message' => 'Server error. Please try again.',
    ]);
}

function handle_tree(PDO $pdo) {
    $reorgDefs = reorg_table_defs();
    $tables = [
        'birth' => [
            'table' => 'certificate_of_live_birth',
            'label' => 'Birth',
            'permission' => 'birth_view',
            'event_date' => $reorgDefs['birth']['event_date'],
            'last_name' => $reorgDefs['birth']['last_name'],
        ],
        'death' => [
            'table' => 'certificate_of_death',
            'label' => 'Death',
            'permission' => 'death_view',
            'event_date' => $reorgDefs['death']['event_date'],
            'last_name' => $reorgDefs['death']['last_name'],
        ],
        'marriage' => [
            'table' => 'certificate_of_marriage',
            'label' => 'Marriage',
            'permission' => 'marriage_view',
            'event_date' => $reorgDefs['marriage']['event_date'],
            'last_name' => $reorgDefs['marriage']['last_name'],
        ],
        'marriage_license' => [
            'table' => 'application_for_marriage_license',
            'label' => 'Marriage License',
            'permission' => 'marriage_license_view',
            'event_date' => $reorgDefs['marriage_license']['event_date'],
            'last_name' => $reorgDefs['marriage_license']['last_name'],
        ],
    ];

    $tree = [];

    foreach ($tables as $type => $def) {
        if (!hasPermission($def['permission'])) continue;

        $tbl     = $def['table'];
        $evtCol  = $def['event_date'];
        $nameCol = $def['last_name'];

        $sql = "SELECT pdf_filename, registry_no,
                       `{$evtCol}` AS event_date,
                       `{$nameCol}` AS last_name
                FROM `{$tbl}`
                WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status = 'Active'";
        $rows = $pdo->query($sql)->fetchAll();

        $folders = [];
        $total = 0;

        foreach ($rows as $row) {
            $total++;

            // Year priority: DB date column → registry-no prefix → file-path segment.
            $year = year_from_date($row['event_date'])
                 ?? registry_folder_year($row['registry_no']);

            if ($year === null) {
                $parts = explode('/', $row['pdf_filename']);
                if (count($parts) >= 3 && $parts[0] === $type && ctype_digit($parts[1]) && strlen($parts[1]) === 4) {
                    $year = (int)$parts[1];
                }
            }

            // Last-name bucket: prefer DB column, fall back to path segment.
            $lastName = null;
            if ($row['last_name'] !== null && trim($row['last_name']) !== '') {
                $lastName = folder_safe_last_name($row['last_name']);
            } else {
                $parts = explode('/', $row['pdf_filename']);
                if (count($parts) === 4 && $parts[0] === $type) {
                    $lastName = $parts[2];
                } elseif (count($parts) === 3 && $parts[0] === $type && !ctype_digit($parts[1])) {
                    $lastName = $parts[1];
                }
            }

            $yearKey = $year !== null ? (string)$year : '__no_year__';
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

    // CRF 1A issuances are stored separately from original certificate uploads.
    // Keep this branch tolerant while a deployment is between code and migration.
    if (hasPermission('birth_crf_1a_view')) {
        try {
            $rows = $pdo->query(
                "SELECT crf_year, child_last_name_snapshot
                 FROM crf_1a_issuances
                 WHERE status = 'Active' AND pdf_filename IS NOT NULL AND pdf_filename != ''"
            )->fetchAll();
            $folders = [];
            foreach ($rows as $row) {
                $yearKey = (string)((int)$row['crf_year']);
                if (!isset($folders[$yearKey])) $folders[$yearKey] = ['count' => 0, 'children' => []];
                $folders[$yearKey]['count']++;
                $lastName = folder_safe_last_name($row['child_last_name_snapshot'] ?? 'UNKNOWN');
                $folders[$yearKey]['children'][$lastName] = ($folders[$yearKey]['children'][$lastName] ?? 0) + 1;
            }
            ksort($folders);
            $yearNodes = [];
            foreach ($folders as $yearKey => $data) {
                ksort($data['children']);
                $children = [];
                foreach ($data['children'] as $name => $count) $children[] = ['name' => $name, 'count' => $count];
                $yearNodes[] = ['year' => $yearKey, 'label' => $yearKey, 'count' => $data['count'], 'children' => $children];
            }
            usort($yearNodes, static fn($a, $b) => (int)$b['year'] - (int)$a['year']);
            $tree[] = ['type' => 'crf_1a', 'label' => 'CRF No. 1A', 'count' => array_sum(array_column($yearNodes, 'count')), 'children' => $yearNodes];
        } catch (Throwable $e) {
            // Migration may not have run yet; omit the optional branch.
            error_log('CRF 1A folder tree unavailable: ' . $e->getMessage());
        }
    }

    if (hasPermission('death_crf_2a_view')) {
        try {
            $rows = $pdo->query("SELECT crf_year, deceased_last_name_snapshot FROM crf_2a_issuances WHERE status = 'Active' AND pdf_filename IS NOT NULL AND pdf_filename != ''")->fetchAll();
            $folders = [];
            foreach ($rows as $row) {
                $yearKey = (string)((int)$row['crf_year']);
                if (!isset($folders[$yearKey])) $folders[$yearKey] = ['count' => 0, 'children' => []];
                $folders[$yearKey]['count']++;
                $lastName = folder_safe_last_name($row['deceased_last_name_snapshot'] ?? 'UNKNOWN');
                $folders[$yearKey]['children'][$lastName] = ($folders[$yearKey]['children'][$lastName] ?? 0) + 1;
            }
            ksort($folders); $yearNodes = [];
            foreach ($folders as $yearKey => $data) { ksort($data['children']); $children = []; foreach ($data['children'] as $name => $count) $children[] = ['name' => $name, 'count' => $count]; $yearNodes[] = ['year' => $yearKey, 'label' => $yearKey, 'count' => $data['count'], 'children' => $children]; }
            usort($yearNodes, static fn($a, $b) => (int)$b['year'] - (int)$a['year']);
            $tree[] = ['type' => 'crf_2a', 'label' => 'CRF No. 2A', 'count' => array_sum(array_column($yearNodes, 'count')), 'children' => $yearNodes];
        } catch (Throwable $e) { error_log('CRF 2A folder tree unavailable: ' . $e->getMessage()); }
    }

    if (hasPermission('marriage_crf_3a_view')) {
        try {
            $rows = $pdo->query("SELECT crf_year, husband_last_name_snapshot FROM crf_3a_issuances WHERE status = 'Active' AND pdf_filename IS NOT NULL AND pdf_filename != ''")->fetchAll();
            $folders = [];
            foreach ($rows as $row) {
                $yearKey = (string)((int)$row['crf_year']);
                if (!isset($folders[$yearKey])) $folders[$yearKey] = ['count' => 0, 'children' => []];
                $folders[$yearKey]['count']++;
                $lastName = folder_safe_last_name($row['husband_last_name_snapshot'] ?? 'UNKNOWN');
                $folders[$yearKey]['children'][$lastName] = ($folders[$yearKey]['children'][$lastName] ?? 0) + 1;
            }
            ksort($folders); $yearNodes = [];
            foreach ($folders as $yearKey => $data) { ksort($data['children']); $children = []; foreach ($data['children'] as $name => $count) $children[] = ['name' => $name, 'count' => $count]; $yearNodes[] = ['year' => $yearKey, 'label' => $yearKey, 'count' => $data['count'], 'children' => $children]; }
            usort($yearNodes, static fn($a, $b) => (int)$b['year'] - (int)$a['year']);
            $tree[] = ['type' => 'crf_3a', 'label' => 'CRF No. 3A', 'count' => array_sum(array_column($yearNodes, 'count')), 'children' => $yearNodes];
        } catch (Throwable $e) { error_log('CRF 3A folder tree unavailable: ' . $e->getMessage()); }
    }

    echo json_encode(['success' => true, 'tree' => $tree]);
}

function handle_list(PDO $pdo) {
    $type = $_GET['type'] ?? '';
    $year = $_GET['year'] ?? null;
    $lastName = isset($_GET['last_name']) ? mb_substr(trim((string)$_GET['last_name']), 0, 80) : null;
    $search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 100);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));

    if ($type === 'crf_1a') {
        if (!hasPermission('birth_crf_1a_view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }
        $where = ["status = 'Active'", "pdf_filename IS NOT NULL", "pdf_filename != ''"];
        $params = [];
        if ($year !== null && $year !== '') {
            if (!preg_match('/^\d{4}$/', (string)$year)) {
                echo json_encode(['success' => false, 'message' => 'Invalid year']);
                return;
            }
            $where[] = 'crf_year = :crf_year';
            $params[':crf_year'] = (int)$year;
        }
        if ($lastName !== null && $lastName !== '') {
            $where[] = 'UPPER(child_last_name_snapshot) LIKE :crf_last_name';
            $params[':crf_last_name'] = str_replace('_', '%', strtoupper($lastName));
        }
        if ($search !== '') {
            $where[] = '(crf_number LIKE :crf_search OR registry_no_snapshot LIKE :crf_registry
                OR child_name_snapshot LIKE :crf_child OR page_number LIKE :crf_page
                OR book_number LIKE :crf_book OR or_number LIKE :crf_or
                OR requester_name LIKE :crf_requester)';
            $like = '%' . $search . '%';
            foreach (['search' => $like, 'registry' => $like, 'child' => $like, 'page' => $like, 'book' => $like, 'or' => $like, 'requester' => $like] as $key => $value) $params[':crf_' . $key] = $value;
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM crf_1a_issuances WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $dataStmt = $pdo->prepare(
            "SELECT id, crf_number, birth_record_id, registry_no_snapshot, child_name_snapshot,
                    child_last_name_snapshot, issue_date, page_number, book_number,
                    amount_paid, or_number, date_paid, pdf_filename, status, created_at
             FROM crf_1a_issuances WHERE {$whereSql}
             ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        echo json_encode([
            'success' => true,
            'type' => $type,
            'records' => $dataStmt->fetchAll(),
            'pagination' => [
                'current_page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRecords,
                'per_page' => $perPage, 'from' => $totalRecords > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalRecords),
            ],
        ]);
        return;
    }

    if ($type === 'crf_2a') {
        if (!hasPermission('death_crf_2a_view')) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Permission denied']); return; }
        $where = ["status = 'Active'", "pdf_filename IS NOT NULL", "pdf_filename != ''"]; $params = [];
        if ($year !== null && $year !== '') { if (!preg_match('/^\d{4}$/', (string)$year)) { echo json_encode(['success' => false, 'message' => 'Invalid year']); return; } $where[] = 'crf_year = :year'; $params[':year'] = (int)$year; }
        if ($lastName !== null && $lastName !== '') { $where[] = 'UPPER(deceased_last_name_snapshot) LIKE :last_name'; $params[':last_name'] = str_replace('_', '%', strtoupper($lastName)); }
        if ($search !== '') { $where[] = '(crf_number LIKE :search OR registry_no_snapshot LIKE :search_registry OR deceased_name_snapshot LIKE :search_name OR page_number LIKE :search_page OR book_number LIKE :search_book OR or_number LIKE :search_or)'; $like = '%' . $search . '%'; foreach (['search' => $like, 'search_registry' => $like, 'search_name' => $like, 'search_page' => $like, 'search_book' => $like, 'search_or' => $like] as $k => $v) $params[':' . $k] = $v; }
        $whereSql = implode(' AND ', $where); $count = $pdo->prepare("SELECT COUNT(*) FROM crf_2a_issuances WHERE {$whereSql}"); $count->execute($params); $total = (int)$count->fetchColumn(); $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT id, crf_number, death_record_id, registry_no_snapshot, deceased_name_snapshot, deceased_last_name_snapshot, issue_date, page_number, book_number, amount_paid, or_number, date_paid, pdf_filename, status, created_at FROM crf_2a_issuances WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params);
        echo json_encode(['success' => true, 'type' => $type, 'records' => $stmt->fetchAll(), 'pagination' => ['current_page' => $page, 'total_pages' => $pages, 'total_records' => $total, 'per_page' => $perPage, 'from' => $total ? $offset + 1 : 0, 'to' => min($offset + $perPage, $total)]]); return;
    }

    if ($type === 'crf_3a') {
        if (!hasPermission('marriage_crf_3a_view')) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Permission denied']); return; }
        $where = ["status = 'Active'", "pdf_filename IS NOT NULL", "pdf_filename != ''"]; $params = [];
        if ($year !== null && $year !== '') { if (!preg_match('/^\d{4}$/', (string)$year)) { echo json_encode(['success' => false, 'message' => 'Invalid year']); return; } $where[] = 'crf_year = :year'; $params[':year'] = (int)$year; }
        if ($lastName !== null && $lastName !== '') { $where[] = 'UPPER(husband_last_name_snapshot) LIKE :last_name'; $params[':last_name'] = str_replace('_', '%', strtoupper($lastName)); }
        if ($search !== '') { $where[] = '(crf_number LIKE :search OR registry_no_snapshot LIKE :search_registry OR husband_name_snapshot LIKE :search_husband OR wife_name_snapshot LIKE :search_wife OR page_number LIKE :search_page OR book_number LIKE :search_book OR or_number LIKE :search_or)'; $like = '%' . $search . '%'; foreach (['search' => $like, 'search_registry' => $like, 'search_husband' => $like, 'search_wife' => $like, 'search_page' => $like, 'search_book' => $like, 'search_or' => $like] as $k => $v) $params[':' . $k] = $v; }
        $whereSql = implode(' AND ', $where); $count = $pdo->prepare("SELECT COUNT(*) FROM crf_3a_issuances WHERE {$whereSql}"); $count->execute($params); $total = (int)$count->fetchColumn(); $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT id, crf_number, marriage_record_id, registry_no_snapshot, husband_name_snapshot, husband_last_name_snapshot, wife_name_snapshot, wife_last_name_snapshot, issue_date, page_number, book_number, amount_paid, or_number, date_paid, pdf_filename, status, created_at FROM crf_3a_issuances WHERE {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"); $stmt->execute($params);
        echo json_encode(['success' => true, 'type' => $type, 'records' => $stmt->fetchAll(), 'pagination' => ['current_page' => $page, 'total_pages' => $pages, 'total_records' => $total, 'per_page' => $perPage, 'from' => $total ? $offset + 1 : 0, 'to' => min($offset + $perPage, $total)]]); return;
    }

    $reorgDefs = reorg_table_defs();
    $tableDefs = [
        'birth' => [
            'table' => 'certificate_of_live_birth',
            'permission' => 'birth_view',
            'event_date' => $reorgDefs['birth']['event_date'],
            'last_name_col' => $reorgDefs['birth']['last_name'],
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
            'event_date' => $reorgDefs['death']['event_date'],
            'last_name_col' => $reorgDefs['death']['last_name'],
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
            'event_date' => $reorgDefs['marriage']['event_date'],
            'last_name_col' => $reorgDefs['marriage']['last_name'],
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
            'event_date' => $reorgDefs['marriage_license']['event_date'],
            'last_name_col' => $reorgDefs['marriage_license']['last_name'],
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

    $tbl     = $def['table'];
    $evtCol  = $def['event_date'];
    $nameCol = $def['last_name_col'];

    $where = ["status = 'Active'", "pdf_filename IS NOT NULL", "pdf_filename != ''"];
    $params = [];

    if ($year !== null && $year !== '') {
        // Year folder: match by event-date column OR registry-number prefix,
        // mirroring the year-derivation logic in handle_tree().
        $yearInt = (int)$year;
        $where[] = "(YEAR(`{$evtCol}`) = :year_val
                     OR (`{$evtCol}` IS NULL AND registry_no LIKE :reg_yyyy)
                     OR (`{$evtCol}` IS NULL AND registry_no LIKE :reg_yy))";
        $params[':year_val'] = $yearInt;
        $params[':reg_yyyy'] = $yearInt . '-%';
        $params[':reg_yy']   = sprintf('%02d', $yearInt % 100) . '-%';

        if ($lastName !== null && $lastName !== '') {
            // Match the normalized last-name bucket: folder_safe_last_name() uppercases
            // and replaces non-alphanumeric runs with '_'. Reversing that for SQL: turn
            // each '_' in the bucket label into a '%' wildcard for a case-insensitive LIKE.
            $where[] = "UPPER(`{$nameCol}`) LIKE :ln";
            $params[':ln'] = str_replace('_', '%', strtoupper($lastName));
        }
    } elseif ($lastName !== null && $lastName !== '') {
        $where[] = "UPPER(`{$nameCol}`) LIKE :ln";
        $params[':ln'] = str_replace('_', '%', strtoupper($lastName));
    }
    // else: no year + no last-name -> all records of this type.

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
