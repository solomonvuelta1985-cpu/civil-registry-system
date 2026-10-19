<?php
/**
 * Keep only the latest corrected CRF PDF for each source record.
 *
 * Dry-run is the default. Pass --apply to void superseded issuances and delete
 * their PDFs. The latest corrected file must exist and match its stored SHA-256
 * hash before any older issuance is changed.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/crf_correction_chain.php';

$apply = in_array('--apply', $argv, true);
echo $apply ? "Mode: APPLY\n" : "Mode: DRY RUN (pass --apply to make changes)\n";

$forms = ['1a', '2a', '3a'];
$totals = ['groups' => 0, 'skipped' => 0, 'rows' => 0, 'deleted' => 0, 'failures' => 0];

foreach ($forms as $form) {
    $config = crf_correction_config($form);
    $sourceIdsStmt = $pdo->query("SELECT DISTINCT {$config['source_column']} FROM {$config['table']} WHERE issuance_kind = 'Corrected' AND status IN ('Active', 'Archived', 'Voided') ORDER BY {$config['source_column']}");
    $sourceIds = array_map('intval', $sourceIdsStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($sourceIds as $sourceId) {
        $totals['groups']++;
        $started = false;
        try {
            if ($apply) {
                $pdo->beginTransaction();
                $started = true;
                $sourceLock = $pdo->prepare("SELECT id FROM {$config['source_table']} WHERE id = :id LIMIT 1 FOR UPDATE");
                $sourceLock->execute([':id' => $sourceId]);
                if (!$sourceLock->fetchColumn()) {
                    $pdo->rollBack();
                    $started = false;
                    echo "CRF " . strtoupper($form) . " source #{$sourceId}: skipped (source row missing)\n";
                    $totals['skipped']++;
                    continue;
                }
            }

            $rowsStmt = $pdo->prepare("SELECT * FROM {$config['table']} WHERE {$config['source_column']} = :source_id AND issuance_kind IN ('Original', 'Corrected') AND status IN ('Active', 'Archived', 'Voided') ORDER BY id ASC" . ($apply ? ' FOR UPDATE' : ''));
            $rowsStmt->execute([':source_id' => $sourceId]);
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            $rowById = [];
            $latest = null;
            foreach ($rows as $row) {
                $rowById[(int)$row['id']] = $row;
                if ($row['issuance_kind'] === 'Corrected') $latest = $row;
            }
            if (!$latest || !in_array($latest['status'], ['Active', 'Archived'], true)) {
                if ($started) { $pdo->rollBack(); $started = false; }
                echo "CRF " . strtoupper($form) . " source #{$sourceId}: skipped (no current corrected issuance)\n";
                $totals['skipped']++;
                continue;
            }

            $integrity = crf_correction_verify_pdf($form, $latest);
            if (!$integrity['ok']) {
                if ($started) { $pdo->rollBack(); $started = false; }
                echo "CRF " . strtoupper($form) . " source #{$sourceId}: skipped; latest " . $latest['crf_number'] . " " . $integrity['reason'] . "\n";
                if ($apply) {
                    $message = 'Superseded PDF cleanup skipped: newest PDF ' . $integrity['reason'] . '.';
                    error_log('CRF ' . strtoupper($form) . ' correction cleanup: ' . $message);
                    crf_correction_record_history_once($pdo, $form, (int)$latest['id'], (string)$latest['crf_number'], 'pdf_cleanup_skipped', $message);
                }
                $totals['skipped']++;
                continue;
            }

            $olderRows = array_values(array_filter($rows, static fn(array $row): bool => (int)$row['id'] !== (int)$latest['id']));
            echo "CRF " . strtoupper($form) . " source #{$sourceId}: keep " . $latest['crf_number'] . '; superseded records=' . count($olderRows) . "\n";
            $totals['rows'] += count($olderRows);
            if (!$apply) continue;

            // Backfill field-by-field differences for older corrected edges.
            foreach ($rows as $row) {
                if ($row['issuance_kind'] !== 'Corrected') continue;
                $parentId = (int)($row['replaces_issuance_id'] ?? 0);
                if ($parentId > 0 && isset($rowById[$parentId])) {
                    crf_correction_record_diffs($pdo, $form, (int)$row['id'], (string)$row['crf_number'], $rowById[$parentId], $row, true);
                }
            }

            $voidStmt = $pdo->prepare("UPDATE {$config['table']} SET status = 'Voided' WHERE id = :id AND status IN ('Active', 'Archived')");
            foreach ($olderRows as $older) {
                if (in_array($older['status'], ['Active', 'Archived'], true)) {
                    $voidStmt->execute([':id' => (int)$older['id']]);
                    crf_correction_record_history($pdo, $form, (int)$older['id'], (string)$older['crf_number'], 'voided', 'Superseded by ' . $latest['crf_number'] . ' during correction-chain cleanup.');
                }
            }
            $pdo->commit();
            $started = false;

            $failures = [];
            foreach ($olderRows as $older) {
                $removed = crf_correction_remove_pdf($form, $older);
                if ($removed['ok'] && $removed['reason'] === 'deleted') $totals['deleted']++;
                elseif (!$removed['ok']) {
                    $totals['failures']++;
                    $failures[] = $older['crf_number'] . ': ' . $removed['reason'];
                    $message = 'Could not remove superseded PDF for ' . (string)$older['crf_number'] . ': ' . $removed['reason'] . '.';
                    error_log('CRF ' . strtoupper($form) . ' correction cleanup: ' . $message);
                    crf_correction_record_history_once($pdo, $form, (int)$latest['id'], (string)$latest['crf_number'], 'pdf_cleanup_failed', $message);
                }
            }
            if ($failures) {
                echo "  Deletion failures recorded for retry: " . implode('; ', $failures) . "\n";
            }
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            $totals['failures']++;
            echo "CRF " . strtoupper($form) . " source #{$sourceId}: ERROR " . $e->getMessage() . "\n";
            error_log('CRF correction cleanup failed for ' . $form . ' source #' . $sourceId . ': ' . $e->getMessage());
        }
    }
}

echo "Summary: groups={$totals['groups']}, skipped={$totals['skipped']}, superseded_rows={$totals['rows']}, deleted={$totals['deleted']}, failures={$totals['failures']}\n";
