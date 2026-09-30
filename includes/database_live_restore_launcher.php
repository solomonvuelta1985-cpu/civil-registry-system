<?php
/** Launches the guarded full database restore worker in the background. */
require_once __DIR__ . '/database_backup.php';
require_once __DIR__ . '/settings.php';

function launch_database_live_restore_worker(int $jobId, string $action): void
{
    if ($jobId <= 0 || !in_array($action, ['restore', 'rollback'], true)) throw new InvalidArgumentException('Invalid live database restore worker request.');
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? (string)PHP_BINARY : 'php';
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'database_live_restore_worker.php';
    if (!is_file($script)) throw new RuntimeException('The live database restore worker is not installed.');
    if (DIRECTORY_SEPARATOR === '\\') {
        $runtime = realpath($php) ?: $php;
        if (strtolower(pathinfo($runtime, PATHINFO_BASENAME)) !== 'php.exe' && defined('PHP_BINDIR')) $runtime = rtrim((string)PHP_BINDIR, "\\/") . DIRECTORY_SEPARATOR . 'php.exe';
        if (!is_file($runtime) && defined('BASE_PATH')) { $candidate = dirname(dirname(BASE_PATH)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe'; if (is_file($candidate)) $runtime = $candidate; }
        if (!is_file($runtime)) throw new RuntimeException('The PHP CLI binary could not be found.');
        $command = 'start "" /B ' . database_backup_windows_arg($runtime) . ' ' . database_backup_windows_arg($script) . ' ' . database_backup_windows_arg('--job-id=' . $jobId) . ' ' . database_backup_windows_arg('--action=' . $action) . ' > NUL 2>&1';
        $comspec = getenv('ComSpec') ?: getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe'; if (!is_file($comspec)) $comspec = 'cmd.exe';
        $handle = @popen(database_backup_windows_arg($comspec) . ' /c ' . $command, 'r'); if (!is_resource($handle)) throw new RuntimeException('Could not start the live database restore worker.'); pclose($handle); return;
    }
    $command = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg('--job-id=' . $jobId) . ' ' . escapeshellarg('--action=' . $action) . ' > /dev/null 2>&1 &'; exec($command, $ignored, $exitCode); if ($exitCode !== 0) throw new RuntimeException('Could not start the live database restore worker.');
}
