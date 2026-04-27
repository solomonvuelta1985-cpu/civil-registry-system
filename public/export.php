<?php
/**
 * Civil Registry Records — Export Handler
 * Exports records as Excel (.xls via HTML table) or CSV (UTF-8 with BOM)
 *
 * Parameters:
 *   type   = birth | marriage | death | marriage_license
 *   format = xls | csv
 *   search, sort_by, sort_order, plus any filter params = optional
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAuth();

$type   = sanitize_input($_GET['type'] ?? 'birth');
$format = sanitize_input($_GET['format'] ?? 'xls');
$search = sanitize_input($_GET['search'] ?? '');

if (!in_array($type, ['birth', 'marriage', 'death', 'marriage_license'])) {
    die('Invalid record type.');
}
if (!in_array($format, ['xls', 'csv'])) {
    die('Invalid format.');
}

// Check permission
$permission_map = [
    'marriage' => 'marriage_view',
    'birth' => 'birth_view',
    'death' => 'death_view',
    'marriage_license' => 'marriage_license_view'
];
if (!hasPermission($permission_map[$type])) {
    http_response_code(403);
    die('Access denied.');
}

// Export column definitions per type (human-readable, no internal paths)
$export_configs = [
    'birth' => [
        'table'    => 'certificate_of_live_birth',
        'filename' => 'Birth_Records',
        'title'    => 'Certificate of Live Birth — Records',
        'search_fields' => ['registry_no', 'child_first_name', 'child_middle_name', 'child_last_name',
                            'father_first_name', 'father_middle_name', 'father_last_name',
                            'mother_first_name', 'mother_middle_name', 'mother_last_name',
                            'child_place_of_birth'],
        'columns' => [
            'registry_no'          => 'Registry No.',
            '_child_name'          => 'Child Name',
            'child_sex'            => 'Sex',
            'child_date_of_birth'  => 'Date of Birth',
            '_father_name'         => 'Father',
            '_mother_name'         => 'Mother',
            'child_place_of_birth' => 'Place of Birth',
            'date_of_registration' => 'Registration Date',
            '_encoded_by'          => 'Encoded By',
        ],
        'filters' => [
            'birth_date_from' => ['field' => 'child_date_of_birth', 'op' => '>='],
            'birth_date_to'   => ['field' => 'child_date_of_birth', 'op' => '<='],
            'reg_date_from'   => ['field' => 'date_of_registration', 'op' => '>='],
            'reg_date_to'     => ['field' => 'date_of_registration', 'op' => '<='],
            'place_type'      => ['field' => 'place_type', 'op' => '='],
            'child_place_of_birth' => ['field' => 'child_place_of_birth', 'op' => '='],
            'child_sex'       => ['field' => 'child_sex', 'op' => '='],
        ],
    ],
    'marriage' => [
        'table'    => 'certificate_of_marriage',
        'filename' => 'Marriage_Records',
        'title'    => 'Certificate of Marriage — Records',
        'search_fields' => ['registry_no', 'husband_first_name', 'husband_middle_name', 'husband_last_name',
                            'wife_first_name', 'wife_middle_name', 'wife_last_name',
                            'date_of_marriage', 'place_of_marriage'],
        'columns' => [
            'registry_no'          => 'Registry No.',
            '_husband_name'        => 'Husband',
            '_wife_name'           => 'Wife',
            'date_of_marriage'     => 'Marriage Date',
            'place_of_marriage'    => 'Place of Marriage',
            'date_of_registration' => 'Registration Date',
            '_encoded_by'          => 'Encoded By',
        ],
        'filters' => [
            'marriage_date_from' => ['field' => 'date_of_marriage', 'op' => '>='],
            'marriage_date_to'   => ['field' => 'date_of_marriage', 'op' => '<='],
            'reg_date_from'      => ['field' => 'date_of_registration', 'op' => '>='],
            'reg_date_to'        => ['field' => 'date_of_registration', 'op' => '<='],
            'place'              => ['field' => 'place_of_marriage', 'op' => 'LIKE'],
        ],
    ],
    'death' => [
        'table'    => 'certificate_of_death',
        'filename' => 'Death_Records',
        'title'    => 'Certificate of Death — Records',
        'search_fields' => ['registry_no', 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name',
                            'father_first_name', 'father_middle_name', 'father_last_name',
                            'mother_first_name', 'mother_middle_name', 'mother_last_name',
                            'date_of_death', 'place_of_death', 'occupation'],
        'columns' => [
            'registry_no'          => 'Registry No.',
            '_deceased_name'       => 'Deceased',
            'sex'                  => 'Sex',
            'age'                  => 'Age',
            'date_of_birth'        => 'Date of Birth',
            'date_of_death'        => 'Date of Death',
            'place_of_death'       => 'Place of Death',
            'date_of_registration' => 'Registration Date',
            '_encoded_by'          => 'Encoded By',
        ],
        'filters' => [
            'death_date_from' => ['field' => 'date_of_death', 'op' => '>='],
            'death_date_to'   => ['field' => 'date_of_death', 'op' => '<='],
            'reg_date_from'   => ['field' => 'date_of_registration', 'op' => '>='],
            'reg_date_to'     => ['field' => 'date_of_registration', 'op' => '<='],
            'place'           => ['field' => 'place_of_death', 'op' => 'LIKE'],
            'age_from'        => ['field' => 'age', 'op' => '>='],
            'age_to'          => ['field' => 'age', 'op' => '<='],
            'sex'             => ['field' => 'sex', 'op' => '='],
        ],
    ],
    'marriage_license' => [
        'table'    => 'application_for_marriage_license',
        'filename' => 'Marriage_License_Applications',
        'title'    => 'Application for Marriage License — Records',
        'search_fields' => ['registry_no', 'groom_first_name', 'groom_middle_name', 'groom_last_name',
                            'bride_first_name', 'bride_middle_name', 'bride_last_name',
                            'groom_residence', 'bride_residence'],
        'columns' => [
            'registry_no'          => 'Registry No.',
            '_groom_name'          => 'Groom',
            '_bride_name'          => 'Bride',
            'date_of_application'  => 'Application Date',
            'groom_residence'      => 'Groom Residence',
            'bride_residence'      => 'Bride Residence',
            '_encoded_by'          => 'Encoded By',
        ],
        'filters' => [
            'app_date_from'    => ['field' => 'date_of_application', 'op' => '>='],
            'app_date_to'      => ['field' => 'date_of_application', 'op' => '<='],
            'groom_residence'  => ['field' => 'groom_residence', 'op' => 'LIKE'],
            'bride_residence'  => ['field' => 'bride_residence', 'op' => 'LIKE'],
        ],
    ],
];

$config = $export_configs[$type];

// Build query with search + filters
$where_clauses = ["status = 'Active'"];
$params = [];

// Search
if (!empty($search)) {
    $tokens = preg_split('/\s+/', trim($search));
    $idx = 0;
    $token_clauses = [];
    foreach ($tokens as $token) {
        if ($token === '') continue;
        $field_matches = [];
        foreach ($config['search_fields'] as $field) {
            $p = ':s_' . $idx++;
            $field_matches[] = "{$field} LIKE {$p}";
            $params[$p] = "%{$token}%";
        }
        $token_clauses[] = '(' . implode(' OR ', $field_matches) . ')';
    }
    if (!empty($token_clauses)) {
        $where_clauses[] = '(' . implode(' AND ', $token_clauses) . ')';
    }
}

// Filters
foreach ($config['filters'] as $param_name => $filter_def) {
    $val = sanitize_input($_GET[$param_name] ?? '');
    if ($val === '') continue;
    $p = ':f_' . $param_name;
    if ($filter_def['op'] === 'LIKE') {
        $where_clauses[] = "{$filter_def['field']} LIKE {$p}";
        $params[$p] = "%{$val}%";
    } else {
        $where_clauses[] = "{$filter_def['field']} {$filter_def['op']} {$p}";
        $params[$p] = $val;
    }
}

$where_sql = ' WHERE ' . implode(' AND ', $where_clauses);

// Sorting
$sort_by = sanitize_input($_GET['sort_by'] ?? 'created_at');
$sort_order = (isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC') ? 'ASC' : 'DESC';

try {
    $stmt = $pdo->prepare("SELECT * FROM {$config['table']}{$where_sql} ORDER BY {$sort_by} {$sort_order}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    die('Database error during export.');
}

// Build user lookup for Encoded By
$user_ids = array_unique(array_filter(array_column($records, 'created_by')));
$user_map = [];
if (!empty($user_ids)) {
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $u_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id IN ($placeholders)");
    $u_stmt->execute(array_values($user_ids));
    foreach ($u_stmt->fetchAll() as $u) {
        $user_map[(int)$u['id']] = $u['full_name'];
    }
}

// Helper: resolve composite/virtual columns for a row
function resolve_export_value($col, $row, $type, $user_map) {
    // Composite name fields (prefixed with _)
    $name_map = [
        '_child_name'    => ['child_first_name', 'child_middle_name', 'child_last_name'],
        '_father_name'   => ['father_first_name', 'father_middle_name', 'father_last_name'],
        '_mother_name'   => ['mother_first_name', 'mother_middle_name', 'mother_last_name'],
        '_deceased_name' => ['deceased_first_name', 'deceased_middle_name', 'deceased_last_name'],
        '_husband_name'  => ['husband_first_name', 'husband_middle_name', 'husband_last_name'],
        '_wife_name'     => ['wife_first_name', 'wife_middle_name', 'wife_last_name'],
        '_groom_name'    => ['groom_first_name', 'groom_middle_name', 'groom_last_name'],
        '_bride_name'    => ['bride_first_name', 'bride_middle_name', 'bride_last_name'],
    ];

    if (isset($name_map[$col])) {
        $parts = $name_map[$col];
        return trim(($row[$parts[0]] ?? '') . ' ' . ($row[$parts[1]] ?? '') . ' ' . ($row[$parts[2]] ?? ''));
    }

    if ($col === '_encoded_by') {
        $uid = $row['created_by'] ?? null;
        if (!$uid) return '';
        return $user_map[(int)$uid] ?? 'Unknown';
    }

    // Age with unit for death records
    if ($col === 'age' && $type === 'death') {
        $age = $row['age'] ?? '';
        $unit = $row['age_unit'] ?? 'years';
        return ($age !== '' && $age !== null) ? $age . ' ' . ucfirst($unit) : '';
    }

    // Date formatting
    $date_cols = ['date_of_registration', 'child_date_of_birth', 'date_of_birth', 'date_of_death',
                  'date_of_marriage', 'date_of_application'];
    if (in_array($col, $date_cols) && !empty($row[$col])) {
        return date('M d, Y', strtotime($row[$col]));
    }

    return $row[$col] ?? '';
}

$timestamp = date('Y-m-d_His');
$filename = $config['filename'] . '_' . $timestamp;
$headers = array_values($config['columns']);
$col_keys = array_keys($config['columns']);

// =========================================
// CSV Export
// =========================================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, $headers);

    foreach ($records as $row) {
        $line = [];
        foreach ($col_keys as $col) {
            $line[] = resolve_export_value($col, $row, $type, $user_map);
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;
}

// =========================================
// XLS Export (HTML table that Excel opens)
// =========================================
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
header('Cache-Control: max-age=0');

echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<style>
    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
    th { background-color: #2563eb; color: #ffffff; font-weight: bold; padding: 8px 10px; border: 1px solid #1e40af; text-align: left; }
    td { padding: 6px 10px; border: 1px solid #d1d5db; vertical-align: top; }
    tr:nth-child(even) td { background-color: #f1f5f9; }
    .title { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    .meta { font-size: 10px; color: #64748b; margin-bottom: 10px; }
</style>
</head>
<body>
<p class="title"><?= htmlspecialchars($config['title']) ?></p>
<p class="meta">Exported: <?= date('F j, Y g:i A') ?> &nbsp;|&nbsp; Records: <?= count($records) ?><?= !empty($search) ? ' &nbsp;|&nbsp; Search: ' . htmlspecialchars($search) : '' ?></p>
<table>
    <thead>
        <tr>
            <?php foreach ($headers as $h): ?>
                <th><?= htmlspecialchars($h) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($records)): ?>
            <tr><td colspan="<?= count($headers) ?>" style="text-align:center;color:#94a3b8;padding:20px;">No records found.</td></tr>
        <?php else: ?>
            <?php foreach ($records as $row): ?>
                <tr>
                    <?php foreach ($col_keys as $col): ?>
                        <td><?= htmlspecialchars(resolve_export_value($col, $row, $type, $user_map)) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
