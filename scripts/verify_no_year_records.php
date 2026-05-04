<?php
/**
 * Verify "No Year" records — read-only diagnostic.
 *
 * Finds certificate records whose pdf_filename path lacks a year segment
 * (so the folder browser shows them under "No Year"), then checks whether
 * the DB row actually has a derivable year (event date or registry-no prefix).
 *
 * Rows that DO have a derivable year are mis-foldered: the file should have
 * been placed under uploads/{type}/{YEAR}/{LAST_NAME}/ but lives in the
 * year-less Case B path uploads/{type}/{LAST_NAME}/ instead.
 *
 * Usage:
 *   php scripts/verify_no_year_records.php
 *   php scripts/verify_no_year_records.php --type=birth
 *   php scripts/verify_no_year_records.php --csv > no_year_report.csv
 *   php scripts/verify_no_year_records.php --limit=20
 *
 * Read-only. Makes no changes. Run reorganize_uploads.php to fix.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reorganize_uploads.php';

// --- Parse CLI args ---
$filterType = null;
$asCsv      = false;
$limit      = 0;

foreach ($argv as $arg) {
    if ($arg === '--csv') {
        $asCsv = true;
    } elseif (strpos($arg, '--type=') === 0) {
        $filterType = substr($arg, 7);
    } elseif (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

$tables = reorg_table_defs();
if ($filterType !== null && !isset($tables[$filterType])) {
    fwrite(STDERR, "ERROR: Unknown --type. Valid: " . implode(', ', array_keys($tables)) . "\n");
    exit(1);
}

$grand = [
    'no_year_total'      => 0,
    'fixable_event_date' => 0,
    'fixable_registry'   => 0,
    'truly_no_year'      => 0,
];

$csvRows = [];
if ($asCsv) {
    $csvRows[] = ['type', 'id', 'registry_no', 'last_name', 'event_date', 'derived_year', 'year_source', 'current_path', 'expected_path'];
}

foreach ($tables as $type => $def) {
    if ($filterType !== null && $type !== $filterType) continue;

    $tbl     = $def['table'];
    $evtCol  = $def['event_date'];
    $nameCol = $def['last_name'];

    // Pull all active rows with a pdf_filename that does NOT contain a 4-digit
    // year segment as the second path component (i.e. Case B paths).
    // Pattern matched: "{type}/NON-DIGIT.../..." — any path where the second
    // segment is not a 4-digit year.
    $sql = "
        SELECT id, registry_no, `{$evtCol}` AS event_date,
               `{$nameCol}` AS last_name, pdf_filename
        FROM `{$tbl}`
        WHERE pdf_filename IS NOT NULL
          AND pdf_filename != ''
          AND status = 'Active'
          AND pdf_filename NOT REGEXP '^[^/]+/[0-9]{4}/'
        ORDER BY id
    ";

    $rows = $pdo->query($sql)->fetchAll();

    if (count($rows) === 0) {
        if (!$asCsv) {
            echo "--- {$type} ---  No \"No Year\" rows found.\n\n";
        }
        continue;
    }

    if (!$asCsv) {
        echo "--- {$type} ({$tbl}) ---\n";
        echo "  \"No Year\" rows: " . count($rows) . "\n";
    }

    $typeFixableEvt   = 0;
    $typeFixableReg   = 0;
    $typeTrulyNoYear  = 0;
    $shown            = 0;

    foreach ($rows as $row) {
        $grand['no_year_total']++;

        $evtYear = year_from_date($row['event_date']);
        $regYear = registry_folder_year($row['registry_no']);

        $derivedYear = $evtYear ?? $regYear;
        $source      = $evtYear !== null ? 'event_date'
                     : ($regYear !== null ? 'registry_no' : 'none');

        if ($source === 'event_date') {
            $typeFixableEvt++;
            $grand['fixable_event_date']++;
        } elseif ($source === 'registry_no') {
            $typeFixableReg++;
            $grand['fixable_registry']++;
        } else {
            $typeTrulyNoYear++;
            $grand['truly_no_year']++;
        }

        $expectedPath = null;
        if ($derivedYear !== null) {
            $lastFolder   = folder_safe_last_name($row['last_name']);
            $base         = reorg_basename($row['pdf_filename']);
            $expectedPath = upload_sub_dir($type, $derivedYear, $lastFolder) . $base;
        }

        if ($asCsv) {
            $csvRows[] = [
                $type,
                $row['id'],
                $row['registry_no'] ?? '',
                $row['last_name'] ?? '',
                $row['event_date'] ?? '',
                $derivedYear ?? '',
                $source,
                $row['pdf_filename'],
                $expectedPath ?? '',
            ];
        } elseif ($limit === 0 || $shown < $limit) {
            if ($derivedYear !== null) {
                printf(
                    "  [#%d] reg=%-15s last=%-20s evt=%-12s -> YEAR %d (%s)\n         current : %s\n         expected: %s\n",
                    $row['id'],
                    $row['registry_no'] ?? '(none)',
                    $row['last_name'] ?? '(none)',
                    $row['event_date'] ?? '(none)',
                    $derivedYear,
                    $source,
                    $row['pdf_filename'],
                    $expectedPath
                );
            } else {
                printf(
                    "  [#%d] reg=%-15s last=%-20s evt=%-12s -> truly no year\n         current : %s\n",
                    $row['id'],
                    $row['registry_no'] ?? '(none)',
                    $row['last_name'] ?? '(none)',
                    $row['event_date'] ?? '(none)',
                    $row['pdf_filename']
                );
            }
            $shown++;
        }
    }

    if (!$asCsv) {
        if ($limit > 0 && count($rows) > $limit) {
            echo "  ... " . (count($rows) - $limit) . " more not shown (use --limit=0 or --csv).\n";
        }
        echo "  Fixable via event_date : {$typeFixableEvt}\n";
        echo "  Fixable via registry_no: {$typeFixableReg}\n";
        echo "  Truly no year          : {$typeTrulyNoYear}\n\n";
    }
}

if ($asCsv) {
    $out = fopen('php://stdout', 'w');
    foreach ($csvRows as $r) fputcsv($out, $r);
    fclose($out);
    exit(0);
}

echo "=== GRAND TOTAL ===\n";
echo "Rows under \"No Year\"        : {$grand['no_year_total']}\n";
echo "  Fixable (event_date year) : {$grand['fixable_event_date']}\n";
echo "  Fixable (registry-no year): {$grand['fixable_registry']}\n";
echo "  Truly no derivable year   : {$grand['truly_no_year']}\n";

$fixable = $grand['fixable_event_date'] + $grand['fixable_registry'];
if ($fixable > 0) {
    echo "\n";
    echo "{$fixable} record(s) are mis-foldered. Their pdf_filename lacks a year\n";
    echo "segment, but a year IS derivable from the row data.\n";
    echo "\n";
    echo "Fix:\n";
    echo "  1. Dry-run: visit /admin/reorganize_uploads.php (admin login)\n";
    echo "             or run scripts/reorganize_uploads_by_registry.php --token=...\n";
    echo "  2. If the dry-run looks right, run with --apply.\n";
}
