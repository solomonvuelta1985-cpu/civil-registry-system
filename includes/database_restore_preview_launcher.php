<?php
/** Launches an isolated database restore-preview worker. */
require_once __DIR__ . '/database_restore_preview.php';

function launch_database_restore_preview_worker(int $jobId): void
{
    if ($jobId <= 0) throw new InvalidArgumentException('Invalid restore-preview job ID.');
    $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? (string)PHP_BINARY : 'php';
    if (DIRECTORY_SEPARATOR === '\\') {
        $runtime = realpath($php) ?: $php;
        if (strtolower(pathinfo($runtime, PATHINFO_BASENAME)) !== 'php.exe' && defined('PHP_BINDIR')) $runtime = rtrim((string)PHP_BINDIR, "\\/") . DIRECTORY_SEPARATOR . 'php.exe';
        if (!is_file($runtime) && defined('BASE_PATH')) { $candidate = dirname(dirname(BASE_PATH)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe'; if (is_file($candidate)) $runtime = $candidate; }
        if (!is_file($runtime)) throw new RuntimeException('The PHP CLI binary could not be found.');
        $script = BASE_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'database_restore_preview_worker.php';
        if (!is_file($script)) throw new RuntimeException('The restore-preview worker is not installed.');
        $command = 'start "" /B ' . database_backup_windows_arg($runtime) . ' ' . database_backup_windows_arg($script) . ' ' . database_backup_windows_arg('--job-id=' . $jobId) . ' > NUL 2>&1';
        $comspec = getenv('ComSpec') ?: getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe';
        if (!is_file($comspec)) $comspec = 'cmd.exe';
        $handle = @popen(database_backup_windows_arg($comspec) . ' /c ' . $command, 'r');
        if (!is_resource($handle)) throw new RuntimeException('Could not start the restore-preview worker.');
        pclose($handle);
        return;
    }
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'database_restore_preview_worker.php';
    $command = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg('--job-id=' . $jobId) . ' > /dev/null 2>&1 &';
    exec($command, $ignored, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException('Could not start the restore-preview worker.');
}
