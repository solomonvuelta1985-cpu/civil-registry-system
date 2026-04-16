<?php
/**
 * Shared core logic for reorganizing existing PDF uploads into the new
 * year + last-name folder structure.
 *
 * Used by both the CLI script (scripts/reorganize_uploads_by_registry.php)
 * and the admin web trigger (admin/reorganize_uploads.php).
 */

require_once __DIR__ . '/functions.php';

/**
 * Certificate table definitions used by the reorganizer.
 * Each entry maps a cert type to its table name and relevant column names.
 */
function reorg_table_defs(): array {
    return [
        'birth' => [
            'table'     => 'certificate_of_live_birth',
            'event_date'=> 'child_date_of_birth',
            'last_name' => 'child_last_name',
        ],
        'death' => [
            'table'     => 'certificate_of_death',
            'event_date'=> 'date_of_death',
            'last_name' => 'deceased_last_name',
        ],
        'marriage' => [
            'table'     => 'certificate_of_marriage',
            'event_date'=> 'date_of_marriage',
            'last_name' => 'husband_last_name',
        ],
        'marriage_license' => [
            'table'     => 'application_for_marriage_license',
            'event_date'=> 'date_of_application',
            'last_name' => 'groom_last_name',
        ],
    ];
}

/**
 * Extract the bare filename (last segment) from a relative pdf_filename path.
 * e.g. "birth/2026/cert_abc.pdf" -> "cert_abc.pdf"
 *      "cert_abc.pdf"            -> "cert_abc.pdf"
 */
function reorg_basename(string $relative): string {
    $parts = explode('/', $relative);
    return end($parts);
}

/**
 * Run the reorganization across all certificate tables.
 *
 * @param PDO    $pdo      Database connection.
 * @param string $uploadDir Absolute path to the uploads root (with trailing slash).
 * @param bool   $apply     false = dry-run, true = actually move + update.
 * @param callable|null $log  Receives (string $line). Defaults to echo.
 *
 * @return array Summary stats: total, skipped, moved, missing, collision, error.
 */
function reorganize_uploads(PDO $pdo, string $uploadDir, bool $apply = false, ?callable $log = null): array {
    if ($log === null) {
        $log = function (string $line) { echo $line . "\n"; };
    }

    $stats = ['total' => 0, 'skipped' => 0, 'moved' => 0, 'missing' => 0, 'collision' => 0, 'error' => 0];
    $tables = reorg_table_defs();

    foreach ($tables as $certType => $def) {
        $tbl       = $def['table'];
        $evtCol    = $def['event_date'];
        $nameCol   = $def['last_name'];

        $log("--- Processing: {$certType} ({$tbl}) ---");

        $sql = "SELECT id, registry_no, `{$evtCol}` AS event_date, `{$nameCol}` AS last_name,
                       pdf_filename, pdf_filepath
                FROM `{$tbl}`
                WHERE pdf_filename IS NOT NULL AND pdf_filename != ''
                ORDER BY id";

        $rows = $pdo->query($sql)->fetchAll();
        $log("  Found " . count($rows) . " rows with a PDF.");

        foreach ($rows as $row) {
            $stats['total']++;
            $id            = $row['id'];
            $registryNo    = $row['registry_no'];
            $eventDate     = $row['event_date'];
            $lastName       = $row['last_name'];
            $currentRel    = $row['pdf_filename'];

            // Compute the correct target path.
            $year = year_from_date($eventDate)
                 ?? registry_folder_year($registryNo);

            $lastFolder = folder_safe_last_name($lastName);
            $baseName   = reorg_basename($currentRel);

            $targetRel = upload_sub_dir($certType, $year, $lastFolder) . $baseName;

            // Already correct?
            if ($currentRel === $targetRel) {
                $stats['skipped']++;
                continue;
            }

            $srcAbs  = $uploadDir . $currentRel;
            $dstAbs  = $uploadDir . $targetRel;
            $dstDir  = dirname($dstAbs);

            // Source missing on disk?
            if (!file_exists($srcAbs)) {
                $log("  MISSING [{$id}] {$currentRel} (file not on disk — skipping)");
                $stats['missing']++;
                continue;
            }

            // Collision at destination?
            if (file_exists($dstAbs)) {
                $log("  COLLISION [{$id}] {$currentRel} -> {$targetRel} (target already exists — skipping)");
                $stats['collision']++;
                continue;
            }

            if (!$apply) {
                $log("  MOVE [{$id}] {$currentRel} -> {$targetRel}");
                $stats['moved']++;
                continue;
            }

            // Apply mode: actually move the file and update the DB.
            try {
                if (!is_dir($dstDir)) {
                    mkdir($dstDir, 0755, true);
                }

                if (!rename($srcAbs, $dstAbs)) {
                    $log("  ERROR [{$id}] rename failed: {$currentRel} -> {$targetRel}");
                    $stats['error']++;
                    continue;
                }

                $newAbsPath = $dstAbs;
                $stmt = $pdo->prepare(
                    "UPDATE `{$tbl}` SET pdf_filename = :fn, pdf_filepath = :fp WHERE id = :id"
                );
                $stmt->execute([
                    ':fn' => $targetRel,
                    ':fp' => $newAbsPath,
                    ':id' => $id,
                ]);

                $log("  MOVED [{$id}] {$currentRel} -> {$targetRel}");
                $stats['moved']++;
            } catch (\Exception $e) {
                $log("  ERROR [{$id}] {$e->getMessage()}");
                $stats['error']++;
            }
        }
    }

    $mode = $apply ? 'APPLY' : 'DRY-RUN';
    $log("");
    $log("=== Summary ({$mode}) ===");
    $log("Total rows with PDF : {$stats['total']}");
    $log("Already correct     : {$stats['skipped']}");
    $log("To move / Moved     : {$stats['moved']}");
    $log("Missing on disk     : {$stats['missing']}");
    $log("Collision at target : {$stats['collision']}");
    $log("Errors              : {$stats['error']}");

    return $stats;
}
