<?php
/** Background full-database backup worker. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_backup.php';

$options = getopt('', ['job-id:']); $jobId = (int)($options['job-id'] ?? 0);
if ($jobId <= 0) { fwrite(STDERR, "Usage: php scripts/database_backup_worker.php --job-id=N\n"); exit(2); }
$job = get_database_backup_job($pdo, $jobId); if (!$job) { fwrite(STDERR, "Database backup job not found.\n"); exit(2); }
$lockName = 'database_backup_job_' . $jobId; $lockStmt = $pdo->prepare('SELECT GET_LOCK(:name,0)'); $lockStmt->execute([':name' => $lockName]);
if ((int)$lockStmt->fetchColumn() !== 1) { fwrite(STDERR, "Another worker is processing this database backup.\n"); exit(3); }
$release = static function () use ($pdo, $lockName): void { try { $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)'); $stmt->execute([':name' => $lockName]); } catch (Throwable $e) {} };

try {
    if (in_array((string)$job['status'], ['completed','cancelled'], true)) { echo json_encode(['job_id' => $jobId, 'status' => $job['status']]) . PHP_EOL; $release(); exit(0); }
    $folder = (string)$job['backup_folder']; if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) throw new RuntimeException('Could not create the selected database backup folder.');
    if (!is_writable($folder)) throw new RuntimeException('The selected database backup folder is not writable.');
    update_database_backup_job($pdo, $jobId, 'running', ['last_error' => null]); database_backup_log($pdo, $jobId, 'info', 'Starting mysqldump for database ' . DB_NAME . '.');
    $dumpPath = rtrim($folder, "\\/") . DIRECTORY_SEPARATOR . 'iscan-db-' . date('Ymd-His') . '-job-' . $jobId . '.sql';
    $command = database_backup_windows_arg(database_backup_cli_binary('mysqldump'));
    $args = ['--host=' . DB_HOST, '--user=' . DB_USER, '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--no-tablespaces', '--default-character-set=utf8mb4', '--result-file=' . $dumpPath, DB_NAME];
    if (DB_PASS !== '') $args[] = '--password=' . DB_PASS;
    foreach ($args as $arg) $command .= ' ' . database_backup_windows_arg($arg);
    $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $process = proc_open($command, $descriptors, $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start mysqldump.');
    fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exitCode = proc_close($process);
    if ($exitCode !== 0 || !is_file($dumpPath)) throw new RuntimeException('mysqldump failed: ' . trim($stderr ?: $stdout));
    $size = (int)filesize($dumpPath); $hash = hash_file('sha256', $dumpPath); if ($size <= 0 || !is_string($hash)) throw new RuntimeException('The database dump is empty or could not be hashed.');
    database_backup_log($pdo, $jobId, 'info', 'Database dump completed; writing verified manifest.');
    $manifestPath = rtrim($folder, "\\/") . DIRECTORY_SEPARATOR . pathinfo($dumpPath, PATHINFO_FILENAME) . '.manifest.json';
    $manifest = ['format' => 'iSCAN database backup manifest v1', 'job_id' => $jobId, 'created_at' => date('c'), 'database_name' => DB_NAME, 'database_host' => DB_HOST, 'dump_filename' => basename($dumpPath), 'file_size' => $size, 'file_sha256' => strtolower($hash), 'application' => APP_SHORT_NAME, 'application_version' => APP_VERSION];
    $tempManifest = $manifestPath . '.part-' . bin2hex(random_bytes(5)); if (file_put_contents($tempManifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX) === false || !rename($tempManifest, $manifestPath)) throw new RuntimeException('Could not write the database backup manifest.');
    $manifestHash = hash_file('sha256', $manifestPath); update_database_backup_job($pdo, $jobId, 'completed', ['dump_path' => $dumpPath, 'manifest_path' => $manifestPath, 'file_size' => $size, 'file_hash' => strtolower($hash), 'manifest_hash' => strtolower((string)$manifestHash), 'last_error' => null]); database_backup_log($pdo, $jobId, 'info', 'Backup completed and SHA-256 verified.');
    echo json_encode(['job_id' => $jobId, 'status' => 'completed', 'dump_path' => $dumpPath, 'file_size' => $size, 'file_hash' => strtolower($hash), 'manifest_path' => $manifestPath], JSON_PRETTY_PRINT) . PHP_EOL; $release(); exit(0);
} catch (Throwable $e) {
    update_database_backup_job($pdo, $jobId, 'failed', ['last_error' => $e->getMessage()]); database_backup_log($pdo, $jobId, 'error', $e->getMessage()); $release(); fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL); exit(1);
}
