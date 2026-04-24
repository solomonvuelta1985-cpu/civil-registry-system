<?php
/**
 * RA 9048/10172 — Export Handler
 * Exports records as Excel (.xls via HTML table) or CSV (UTF-8 with BOM)
 *
 * Parameters:
 *   type   = petition | legal_instrument | court_decree
 *   format = xls | csv
 *   search, date_from, date_to = optional filters
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireAuth();

$type      = sanitize_input($_GET['type'] ?? 'petition');
$format    = sanitize_input($_GET['format'] ?? 'xls');
$search    = sanitize_input($_GET['search'] ?? '');
$date_from = sanitize_input($_GET['date_from'] ?? '');
$date_to   = sanitize_input($_GET['date_to'] ?? '');

if (!in_array($type, ['petition', 'legal_instrument', 'court_decree'])) {
    die('Invalid record type.');
}
if (!in_array($format, ['xls', 'csv'])) {
    die('Invalid format.');
}

// Column definitions per type
$export_configs = [
    'petition' => [
        'table'      => 'petitions',
        'filename'   => 'RA9048_Petitions',
        'title'      => 'RA 9048 — Petition Records',
        'date_field' => 'date_of_filing',
        'search_fields' => ['document_owner_names', 'petitioner_names', 'petition_of', 'remarks'],
        'columns' => [
            'id'                   => 'ID',
            'petition_type'        => 'Petition Type',
            'date_of_filing'       => 'Date of Filing',
            'document_owner_names' => 'Document Owner/s',
            'petitioner_names'     => 'Petitioner/s',
            'document_type'        => 'Document Type',
            'petition_of'          => 'Petition Of',
            'special_law'          => 'Special Law',
            'fee_amount'           => 'Fee Amount',
            'remarks'              => 'Remarks',
            'created_at'           => 'Created At',
        ],
    ],
    'legal_instrument' => [
        'table'      => 'legal_instruments',
        'filename'   => 'RA9048_Legal_Instruments',
        'title'      => 'RA 9048 — Legal Instrument Records',
        'date_field' => 'date_of_filing',
        'search_fields' => ['document_owner_names', 'affiant_names', 'father_name', 'mother_name', 'registry_number', 'supplemental_info', 'remarks'],
        'columns' => [
            'id'                   => 'ID',
            'instrument_type'      => 'Instrument Type',
            'date_of_filing'       => 'Date of Filing',
            'document_owner_names' => 'Document Owner/s',
            'father_name'          => "Father's Name",
            'mother_name'          => "Mother's Name",
            'affiant_names'        => 'Affiant/s',
            'document_type'        => 'Document Type',
            'registry_number'      => 'Registry Number',
            'applicable_law'       => 'Applicable Law',
            'supplemental_info'    => 'Supplemental Info',
            'legitimation_date'    => 'Legitimation Date',
            'remarks'              => 'Remarks',
            'created_at'           => 'Created At',
        ],
    ],
    'court_decree' => [
        'table'      => 'court_decrees',
        'filename'   => 'RA9048_Court_Decrees',
        'title'      => 'RA 9048 — Court Decree Records',
        'date_field' => 'date_of_filing',
        'search_fields' => ['document_owner_names', 'petitioner_names', 'case_number', 'court_region', 'court_branch', 'court_city_municipality', 'court_province', 'decree_details', 'registry_number', 'remarks'],
        'columns' => [
            'id'                      => 'ID',
            'decree_type'             => 'Decree Type',
            'decree_type_other'       => 'Other Type',
            'court_region'            => 'Court Region',
            'court_branch'            => 'Court Branch',
            'court_city_municipality' => 'Court City/Municipality',
            'court_province'          => 'Court Province',
            'case_number'             => 'Case Number',
            'date_of_decree'          => 'Date of Decree',
            'date_of_filing'          => 'Date of Filing',
            'document_owner_names'    => 'Document Owner/s',
            'petitioner_names'        => 'Petitioner/s',
            'document_type'           => 'Document Type',
            'registry_number'         => 'Registry Number',
            'decree_details'          => 'Decree Details',
            'remarks'                 => 'Remarks',
            'created_at'              => 'Created At',
        ],
    ],
];

$config = $export_configs[$type];

// Build query
$select_cols = implode(', ', array_keys($config['columns']));
$where_clauses = ["status = 'Active'"];
$params = [];

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

if (!empty($date_from)) {
    $params[':date_from'] = $date_from;
    $where_clauses[] = "{$config['date_field']} >= :date_from";
}
if (!empty($date_to)) {
    $params[':date_to'] = $date_to;
    $where_clauses[] = "{$config['date_field']} <= :date_to";
}

$where_sql = ' WHERE ' . implode(' AND ', $where_clauses);

try {
    $stmt = $pdo_ra->prepare("SELECT {$select_cols} FROM {$config['table']}{$where_sql} ORDER BY id DESC");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("RA9048 export error: " . $e->getMessage());
    die('Database error during export.');
}

$timestamp = date('Y-m-d_His');
$filename = $config['filename'] . '_' . $timestamp;
$headers = array_values($config['columns']);

// =========================================
// CSV Export
// =========================================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Header row
    fputcsv($output, $headers);

    // Data rows
    foreach ($records as $row) {
        $line = [];
        foreach (array_keys($config['columns']) as $col) {
            $line[] = $row[$col] ?? '';
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

echo "\xEF\xBB\xBF"; // UTF-8 BOM
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
                    <?php foreach (array_keys($config['columns']) as $col): ?>
                        <td><?= htmlspecialchars($row[$col] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
