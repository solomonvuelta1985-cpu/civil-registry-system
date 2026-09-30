<?php
/** Verify a failed file can be retried and imported exactly once. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$token = bin2hex(random_bytes(8));
$sourceRoot = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'retry-source-' . $token;
$relativeTarget = 'retry_test/' . $token . '/record.pdf';
$targetPath = UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $relativeTarget);
$recordId = 0;
$jobId = 0;

function retry_test_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
    @rmdir($path);
}

function retry_test_run(string $worker, array $args): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker);
    foreach ($args as $key => $value) $command .= ' --' . $key . ($value === true ? '' : '=' . escapeshellarg((string)$value));
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start worker.');
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
    $jsonStart = strrpos($stdout, "{\n");
    return [$exitCode, $stdout, $stderr, $jsonStart === false ? null : json_decode(substr($stdout, $jsonStart), true)];
}

try {
    if (!mkdir($sourceRoot, 0755, true)) throw new RuntimeException('Could not create source root.');
    $fixture = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nRETRY\n%%EOF\n";
    $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . 'record.pdf';
    file_put_contents($sourcePath, $fixture);
    $hash = hash_file('sha256', $sourcePath);
    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Retry', :filename, :filepath, :hash, 'Active')"
    );
    $insert->execute([':registry_no' => '__CODEX_RETRY_' . $token, ':filename' => $relativeTarget, ':filepath' => $targetPath, ':hash' => $hash]);
    $recordId = (int)$pdo->lastInsertId();
    mkdir(dirname($targetPath), 0755, true);
    mkdir($targetPath, 0755, true); // Deliberately makes the first copy fail.

    $worker = __DIR__ . DIRECTORY_SEPARATOR . 'pdf_protection_resumable_worker.php';
    [$exit1, $out1, $err1, $summary1] = retry_test_run($worker, ['mode' => 'recovery', 'source' => $sourceRoot, 'destination' => UPLOAD_DIR]);
    $jobId = (int)($summary1['job_id'] ?? 0);
    if ($exit1 !== 0 || !$jobId || ($summary1['failed'] ?? 0) !== 1) throw new RuntimeException('Initial failure was not recorded: ' . $err1 . $out1);

    $item = $pdo->prepare('SELECT status, attempts FROM pdf_protection_job_items WHERE job_id = :job_id');
    $item->execute([':job_id' => $jobId]);
    $failed = $item->fetch(PDO::FETCH_ASSOC);
    if (!$failed || $failed['status'] !== 'failed' || (int)$failed['attempts'] !== 1) throw new RuntimeException('Failed item attempt state is incorrect.');

    rmdir($targetPath);
    [$exit2, $out2, $err2, $summary2] = retry_test_run($worker, [
        'job-id' => $jobId, 'mode' => 'recovery', 'source' => $sourceRoot, 'destination' => UPLOAD_DIR, 'retry-failed' => true,
    ]);
    if ($exit2 !== 0 || ($summary2['status'] ?? '') !== 'completed' || ($summary2['copied_or_imported'] ?? 0) !== 1) throw new RuntimeException('Retry did not complete: ' . $err2 . $out2);
    if (!is_file($targetPath) || !hash_equals($hash, hash_file('sha256', $targetPath))) throw new RuntimeException('Retried file hash is incorrect.');

    $item->execute([':job_id' => $jobId]);
    $imported = $item->fetch(PDO::FETCH_ASSOC);
    if (!$imported || $imported['status'] !== 'imported' || (int)$imported['attempts'] !== 2) throw new RuntimeException('Retry item state is incorrect.');
    echo "PASS: failed PDF was retried, verified, and imported without duplicate items.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($jobId) $pdo->prepare('DELETE FROM pdf_protection_jobs WHERE id = :id')->execute([':id' => $jobId]);
    if ($recordId) $pdo->prepare('DELETE FROM certificate_of_live_birth WHERE id = :id')->execute([':id' => $recordId]);
    if (is_file($targetPath)) @unlink($targetPath);
    retry_test_remove_tree(dirname($targetPath));
    retry_test_remove_tree($sourceRoot);
}
exit($exit ?? 0);
