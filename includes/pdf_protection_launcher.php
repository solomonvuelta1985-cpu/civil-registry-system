<?php
/** Server-side launcher for a queued PDF protection worker. */

/** Resolve a real CLI binary even when the web SAPI exposes php-cgi.exe. */
function pdf_worker_cli_binary(): string {
    if (DIRECTORY_SEPARATOR !== '\\') return defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';

    $candidates = [];
    $runtime = defined('PHP_BINARY') ? (string)PHP_BINARY : '';
    if ($runtime !== '') {
        $runtimePath = realpath($runtime) ?: $runtime;
        $runtimeName = strtolower(pathinfo($runtimePath, PATHINFO_BASENAME));
        if (in_array($runtimeName, ['php-cgi.exe', 'php-win.exe'], true)) $candidates[] = dirname($runtimePath) . DIRECTORY_SEPARATOR . 'php.exe';
        if ($runtimeName === 'php.exe') $candidates[] = $runtimePath;
    }
    if (defined('PHP_BINDIR') && PHP_BINDIR !== '') $candidates[] = rtrim((string)PHP_BINDIR, "\\/") . DIRECTORY_SEPARATOR . 'php.exe';
    if (defined('BASE_PATH') && BASE_PATH !== '') {
        $xamppRoot = dirname(dirname(BASE_PATH));
        $candidates[] = $xamppRoot . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
    }
    foreach (array_unique($candidates) as $candidate) if (is_file($candidate)) return $candidate;
    throw new RuntimeException('The PHP CLI binary could not be found. Configure the XAMPP PHP CLI path before starting a PDF job.');
}

/** Quote a Windows command-line argument, including trailing backslashes. */
function pdf_worker_windows_arg(string $value): string {
    $result = '';
    $slashes = 0;
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($char === '\\') {
            $slashes++;
            continue;
        }
        if ($char === '"') {
            $result .= str_repeat('\\', ($slashes * 2) + 1) . '"';
            $slashes = 0;
            continue;
        }
        if ($slashes > 0) {
            $result .= str_repeat('\\', $slashes);
            $slashes = 0;
        }
        $result .= $char;
    }
    if ($slashes > 0) $result .= str_repeat('\\', $slashes * 2);
    return '"' . $result . '"';
}

function launch_pdf_protection_worker(
    int $jobId,
    string $mode,
    string $source,
    string $destination,
    bool $dryRun = false
): void {
    if ($jobId <= 0 || !in_array($mode, ['backup', 'recovery'], true)) throw new InvalidArgumentException('Invalid worker launch parameters.');

    $php = pdf_worker_cli_binary();
    $script = BASE_PATH . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'pdf_protection_resumable_worker.php';
    if (!is_file($script)) throw new RuntimeException('The resumable PDF worker is not installed.');

    $args = [
        '--job-id=' . $jobId,
        '--mode=' . $mode,
        '--source=' . $source,
        '--destination=' . $destination,
    ];
    if ($dryRun) $args[] = '--dry-run';

    if (DIRECTORY_SEPARATOR === '\\') {
        $command = 'start "" /B ' . pdf_worker_windows_arg($php) . ' ' . pdf_worker_windows_arg($script);
        foreach ($args as $arg) $command .= ' ' . pdf_worker_windows_arg($arg);
        $command .= ' > NUL 2>&1';
        $comspec = getenv('ComSpec') ?: getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe';
        if (!is_file($comspec)) $comspec = 'cmd.exe';
        $handle = @popen(pdf_worker_windows_arg($comspec) . ' /c ' . $command, 'r');
        if (!is_resource($handle)) throw new RuntimeException('Could not start the background PDF worker.');
        // START is asynchronous and its shell return code is not reliable under Apache.
        pclose($handle);
        return;
    }

    $command = escapeshellarg($php) . ' ' . escapeshellarg($script);
    foreach ($args as $arg) $command .= ' ' . escapeshellarg($arg);
    $command .= ' > /dev/null 2>&1 &';
    exec($command, $ignored, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException('Could not start the background PDF worker.');
}
