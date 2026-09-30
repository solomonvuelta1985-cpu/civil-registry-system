<?php
/** Controlled end-to-end database backup regression test. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_backup.php';

$folder = BASE_PATH . DIRECTORY_SEPARATOR . '.db-backup-test-' . bin2hex(random_bytes(5));
$jobId = 0;
$removeTree = static function (string $path): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    @rmdir($path);
};
try {
    mkdir($folder, 0755, true);
    $jobId = create_database_backup_job($pdo, $folder, $folder, null);
    $command = database_backup_windows_arg(PHP_BINARY) . ' ' . database_backup_windows_arg(BASE_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'database_backup_worker.php') . ' ' . database_backup_windows_arg('--job-id=' . $jobId);
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start the test worker.');
    fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exitCode = proc_close($process);
    $job = get_database_backup_job($pdo, $jobId);
    if ($exitCode !== 0 || !$job || $job['status'] !== 'completed') throw new RuntimeException('Backup worker did not complete: ' . (string)($job['last_error'] ?? 'unknown error'));
    if (!is_file($job['dump_path']) || !is_file($job['manifest_path'])) throw new RuntimeException('Dump or manifest is missing.');
    $manifest = json_decode((string)file_get_contents($job['manifest_path']), true);
    if (!is_array($manifest) || strtolower((string)$manifest['file_sha256']) !== strtolower((string)$job['file_hash']) || strtolower((string)hash_file('sha256', $job['dump_path'])) !== strtolower((string)$job['file_hash'])) throw new RuntimeException('Dump or manifest hash verification failed.');
    $deleteLogs = $pdo->prepare('DELETE FROM database_backup_job_logs WHERE job_id=:id'); $deleteLogs->execute([':id' => $jobId]); $deleteJob = $pdo->prepare('DELETE FROM database_backup_jobs WHERE id=:id'); $deleteJob->execute([':id' => $jobId]); $removeTree($folder);
    echo json_encode(['pass'=>true,'dump_bytes'=>(int)$job['file_size'],'hash'=>$job['file_hash']], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($jobId > 0) { $pdo->prepare('DELETE FROM database_backup_job_logs WHERE job_id=:id')->execute([':id'=>$jobId]); $pdo->prepare('DELETE FROM database_backup_jobs WHERE id=:id')->execute([':id'=>$jobId]); }
    $removeTree($folder); fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL); exit(1);
}
