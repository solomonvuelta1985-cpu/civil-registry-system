<?php
/** Background isolated database restore-preview worker. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_restore_preview.php';

$options = getopt('', ['job-id:']);
$jobId = (int)($options['job-id'] ?? 0);
if ($jobId <= 0) { fwrite(STDERR, "Usage: php scripts/database_restore_preview_worker.php --job-id=N\n"); exit(2); }
$job = get_database_restore_preview_job($pdo, $jobId);
if (!$job) { fwrite(STDERR, "Restore preview job not found.\n"); exit(2); }
$lockName = 'database_restore_preview_job_' . $jobId;
$lockStmt = $pdo->prepare('SELECT GET_LOCK(:name,0)');
$lockStmt->execute([':name' => $lockName]);
if ((int)$lockStmt->fetchColumn() !== 1) { fwrite(STDERR, "Another worker is processing this restore preview.\n"); exit(3); }
$release = static function () use ($pdo, $lockName): void { try { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)'); $stmt->execute([':name' => $lockName]); } catch (Throwable $e) {} };
try {
    $result = database_restore_preview_run($pdo, $jobId);
    echo json_encode(['job_id' => $jobId, 'status' => $result['status'] ?? null, 'staging_database_name' => $result['staging_database_name'] ?? null], JSON_PRETTY_PRINT) . PHP_EOL;
    $release();
    exit(0);
} catch (Throwable $e) {
    $release();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
