<?php
/**
 * Quick verifier for migration 023_ra9048_workflow_fields.
 * Run from CLI: php scripts/verify_023_migration.php
 * Or visit:    http://localhost/iscan/scripts/verify_023_migration.php
 */

require_once __DIR__ . '/../includes/config_ra9048.php';

$expectedNewColumns = [
    'petition_number', 'petition_subtype',
    'petitioner_nationality', 'petitioner_address', 'petitioner_id_type', 'petitioner_id_number',
    'is_self_petition', 'relation_to_owner',
    'owner_dob', 'owner_birthplace_city', 'owner_birthplace_province', 'owner_birthplace_country',
    'registry_number', 'father_full_name', 'mother_full_name',
    'cfn_ground', 'cfn_ground_detail',
    'notarized_at', 'order_date',
    'posting_start_date', 'posting_end_date', 'posting_location', 'posting_cert_issued_at',
    'publication_date_1', 'publication_date_2', 'publication_newspaper', 'publication_place',
    'opposition_deadline',
    'receipt_number', 'payment_date', 'certification_issued_at', 'decision_date',
    'status_workflow',
];

$expectedTables = ['petitions', 'petition_corrections', 'petition_supporting_docs'];

$out = [];
$out[] = '=== Migration 023 verification ===';

// 1. Check tables exist
$existingTables = $pdo_ra->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$out[] = 'Tables present in iscan_db: ' . (empty($existingTables) ? '(none)' : implode(', ', $existingTables));

// Stop here if base tables aren't there — migration 021 is missing.
if (!in_array('petitions', $existingTables, true)) {
    $out[] = '';
    $out[] = 'STOP: petitions table missing. Migration 021 has not been applied.';
    $out[] = 'Run database/migrations/021_ra9048_database.sql FIRST,';
    $out[] = 'then re-run database/migrations/023_ra9048_workflow_fields.sql.';
    echo implode("\n", $out) . "\n";
    exit;
}

$missingTables = array_diff($expectedTables, $existingTables);
if ($missingTables) {
    $out[] = 'MISSING TABLES: ' . implode(', ', $missingTables);
} else {
    $out[] = 'All required tables present.';
}

// 2. Check petitions columns
$cols = $pdo_ra->query("SHOW COLUMNS FROM petitions")->fetchAll(PDO::FETCH_COLUMN);
$missingCols = array_diff($expectedNewColumns, $cols);
$out[] = '';
$out[] = 'petitions columns: ' . count($cols);
if ($missingCols) {
    $out[] = 'MISSING COLUMNS: ' . implode(', ', $missingCols);
} else {
    $out[] = 'All expected new columns present on petitions.';
}

// 3. Existing-row backfill check
try {
    $count = (int) $pdo_ra->query("SELECT COUNT(*) FROM petitions")->fetchColumn();
    $out[] = '';
    $out[] = "Existing petitions rows: {$count}";
    if ($count > 0) {
        $unbackfilled = (int) $pdo_ra->query(
            "SELECT COUNT(*) FROM petitions WHERE petition_subtype IS NULL"
        )->fetchColumn();
        $out[] = "Rows missing petition_subtype: {$unbackfilled}";

        $sample = $pdo_ra->query(
            "SELECT id, petition_type, petition_subtype, status_workflow, petition_number
               FROM petitions ORDER BY id DESC LIMIT 5"
        )->fetchAll();
        $out[] = 'Sample rows (latest 5):';
        foreach ($sample as $r) {
            $out[] = '  #' . $r['id']
                   . ' type=' . ($r['petition_type'] ?? '?')
                   . ' subtype=' . ($r['petition_subtype'] ?? 'NULL')
                   . ' status=' . ($r['status_workflow'] ?? 'NULL')
                   . ' number=' . ($r['petition_number'] ?? 'NULL');
        }
    }
} catch (PDOException $e) {
    $out[] = 'Backfill check failed: ' . $e->getMessage();
}

// 4. Child tables quick check
foreach (['petition_corrections', 'petition_supporting_docs'] as $tbl) {
    try {
        $tcols = $pdo_ra->query("SHOW COLUMNS FROM {$tbl}")->fetchAll(PDO::FETCH_COLUMN);
        $out[] = '';
        $out[] = "{$tbl} columns: " . implode(', ', $tcols);
    } catch (PDOException $e) {
        $out[] = "{$tbl}: ERROR — " . $e->getMessage();
    }
}

$body = implode("\n", $out);

if (php_sapi_name() === 'cli') {
    echo $body . "\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
}
