<?php
/** CLI worker for the two-person full database restore/rollback gate. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/database_live_restore.php';

$options = getopt('', ['job-id:', 'action:']);
$jobId = (int)($options['job-id'] ?? 0);
$action = (string)($options['action'] ?? '');
if ($jobId <= 0 || !in_array($action, ['restore', 'rollback'], true)) {
    fwrite(STDERR, "Usage: php scripts/database_live_restore_worker.php --job-id=N --action=restore|rollback\n");
    exit(2);
}

$job = get_database_live_restore_job($pdo, $jobId);
if (!$job) {
    fwrite(STDERR, "Live database restore job not found.\n");
    exit(2);
}

$lockName = 'database_live_restore_job_' . $jobId;
$lockStmt = $pdo->prepare('SELECT GET_LOCK(:name,0)');
$lockStmt->execute([':name' => $lockName]);
if ((int)$lockStmt->fetchColumn() !== 1) {
    fwrite(STDERR, "Another worker is processing this live database restore.\n");
    exit(3);
}

$release = static function () use ($pdo, $lockName): void {
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $stmt->execute([':name' => $lockName]);
    } catch (Throwable $e) {
    }
};

try {
    $result = database_live_restore_run($pdo, $jobId, $action);
    echo json_encode(['job_id' => $jobId, 'action' => $action, 'status' => $result['status'] ?? null], JSON_PRETTY_PRINT) . PHP_EOL;
    $release();
    exit(0);
} catch (Throwable $e) {
    try {
        update_database_live_restore_job($pdo, $jobId, 'failed', ['last_error' => $e->getMessage()]);
        database_live_restore_log($pdo, $jobId, 'error', $e->getMessage());
    } catch (Throwable $ignored) {
    }
    $release();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
