<?php
/** Guard test: production restore must reject a recovery job without preview_ready status. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_backup.php';
require_once __DIR__ . '/../includes/production_restore_control.php';

$recoveryJobId = (int)($argv[1] ?? 0);
if ($recoveryJobId <= 0) $recoveryJobId = (int)$pdo->query("SELECT id FROM pdf_protection_jobs WHERE job_type='recovery' AND status <> 'preview_ready' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($recoveryJobId <= 0) { fwrite(STDERR, "No non-preview recovery job is available for the guard test.\n"); exit(2); }
$before = (int)$pdo->query('SELECT COUNT(*) FROM production_restore_jobs')->fetchColumn();
$rejected = false; $message = '';
try {
    create_production_restore_job($pdo, $recoveryJobId, BASE_PATH, BASE_PATH . DIRECTORY_SEPARATOR . '.production-restore-guard-test', null);
} catch (Throwable $e) {
    $rejected = true; $message = $e->getMessage();
}
$after = (int)$pdo->query('SELECT COUNT(*) FROM production_restore_jobs')->fetchColumn();
$pass = $rejected && $before === $after;
echo json_encode(['pass'=>$pass,'recovery_job_id'=>$recoveryJobId,'rejected'=>$rejected,'message'=>$message,'production_restore_jobs_before'=>$before,'production_restore_jobs_after'=>$after],JSON_PRETTY_PRINT).PHP_EOL;
exit($pass ? 0 : 1);
