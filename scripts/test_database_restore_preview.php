<?php
/** Controlled end-to-end test for isolated database restore preview. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_restore_preview.php';

$backupJobId = (int)($argv[1] ?? 0);
if ($backupJobId <= 0) $backupJobId = (int)$pdo->query("SELECT id FROM database_backup_jobs WHERE status='completed' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($backupJobId <= 0) { fwrite(STDERR, "No completed database backup job is available for the controlled test.\n"); exit(2); }
$previewJobId = 0; $reportPath = null;
try {
    $previewJobId = create_database_restore_preview_job($pdo, $backupJobId, null);
    $result = database_restore_preview_run($pdo, $previewJobId);
    $reportPath = $result['report_path'] ?? null;
    if (!in_array((string)($result['status'] ?? ''), ['completed','completed_with_errors'], true)) throw new RuntimeException('Restore preview did not reach a completed state.');
    if ((int)($result['staging_table_count'] ?? 0) <= 0) throw new RuntimeException('The isolated staging database contains no tables.');
    if (!is_string($result['report_path'] ?? null) || !is_file((string)$result['report_path'])) throw new RuntimeException('The restore-preview report was not written.');
    $report = get_database_restore_preview_report($result);
    if (($report['staging_database']['name'] ?? '') !== ($result['staging_database_name'] ?? '')) throw new RuntimeException('The report staging database does not match the job.');
    $summary = ['pass'=>true,'backup_job_id'=>$backupJobId,'preview_job_id'=>$previewJobId,'status'=>$result['status'],'staging_table_count'=>(int)$result['staging_table_count'],'pdf_reference_count'=>(int)$result['pdf_reference_count'],'pdf_present_count'=>(int)$result['pdf_present_count'],'pdf_missing_count'=>(int)$result['pdf_missing_count'],'pdf_invalid_count'=>(int)$result['pdf_invalid_count']];
    echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    $summary = ['pass'=>false,'backup_job_id'=>$backupJobId,'preview_job_id'=>$previewJobId,'error'=>$e->getMessage()];
    echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
    if ($previewJobId <= 0) exit(1);
} finally {
    if ($previewJobId > 0) {
        try {
            $job = get_database_restore_preview_job($pdo, $previewJobId);
            if ($job && in_array((string)$job['status'], ['completed','completed_with_errors','failed'], true)) cleanup_database_restore_preview($pdo, $previewJobId);
            if ($job && is_string($job['report_path'] ?? null)) $reportPath = $job['report_path'];
        } catch (Throwable $cleanupError) { fwrite(STDERR, 'CLEANUP ERROR: ' . $cleanupError->getMessage() . PHP_EOL); }
        if (is_string($reportPath) && is_file($reportPath)) @unlink($reportPath);
        $delete = $pdo->prepare('DELETE FROM database_restore_preview_jobs WHERE id=:id'); $delete->execute([':id'=>$previewJobId]);
    }
}
if (isset($summary) && empty($summary['pass'])) exit(1);
