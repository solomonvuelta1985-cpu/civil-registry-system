<?php
/**
 * End-to-end development test for one exact-hash recovery import.
 * Creates a synthetic record/source, runs the real recovery worker, verifies
 * the restored file and job item, then removes all test artifacts.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

require_once __DIR__ . '/../includes/config.php';

$token = bin2hex(random_bytes(8));
$registry = '__CODEX_RECOVERY_' . $token;
$relativeTarget = 'recovery_test/' . $token . '/restored.pdf';
$sourceRoot = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'recovery-source-' . $token;
$sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . 'source.pdf';
$targetPath = UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $relativeTarget);
$recordId = null;
$jobId = null;

function test_recovery_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    if (!mkdir($sourceRoot, 0755, true)) throw new RuntimeException('Could not create recovery fixture root.');
    $fixture = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    if (file_put_contents($sourcePath, $fixture) === false) throw new RuntimeException('Could not create recovery fixture.');
    $hash = hash_file('sha256', $sourcePath);

    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Recovery', :filename, :filepath, :hash, 'Active')"
    );
    $insert->execute([
        ':registry_no' => $registry,
        ':filename' => $relativeTarget,
        ':filepath' => $targetPath,
        ':hash' => $hash,
    ]);
    $recordId = (int)$pdo->lastInsertId();

    $worker = PHP_BINARY;
    $workerScript = __DIR__ . DIRECTORY_SEPARATOR . 'pdf_protection_worker.php';
    $command = escapeshellarg($worker) . ' ' . escapeshellarg($workerScript)
        . ' --mode=recovery --source=' . escapeshellarg($sourceRoot)
        . ' --destination=' . escapeshellarg(UPLOAD_DIR)
        . ' --limit=1';
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start the recovery worker.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) throw new RuntimeException("Recovery worker failed: {$stderr}{$stdout}");

    $jsonStart = strrpos($stdout, "{\n");
    $summary = $jsonStart === false ? null : json_decode(substr($stdout, $jsonStart), true);
    $jobId = isset($summary['job_id']) ? (int)$summary['job_id'] : null;
    if (!$jobId || ($summary['copied_or_imported'] ?? 0) !== 1) {
        throw new RuntimeException('Recovery worker did not report one imported item: ' . $stdout);
    }
    if (!is_file($targetPath) || !hash_equals(strtolower($hash), strtolower((string)hash_file('sha256', $targetPath)))) {
        throw new RuntimeException('Recovered target file is missing or hash verification failed.');
    }

    $itemStmt = $pdo->prepare("SELECT status, match_method, record_id FROM pdf_protection_job_items WHERE job_id = :job_id");
    $itemStmt->execute([':job_id' => $jobId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || $item['status'] !== 'imported' || $item['match_method'] !== 'exact_hash' || (int)$item['record_id'] !== $recordId) {
        throw new RuntimeException('Recovery job item did not record the expected imported mapping.');
    }

    echo "PASS: exact-hash recovery imported and verified one synthetic record.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($jobId) {
        $stmt = $pdo->prepare('DELETE FROM pdf_protection_jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
    }
    if ($recordId) {
        $stmt = $pdo->prepare('DELETE FROM certificate_of_live_birth WHERE id = :id');
        $stmt->execute([':id' => $recordId]);
    }
    if (is_file($targetPath)) @unlink($targetPath);
    test_recovery_remove_tree(dirname($targetPath));
    test_recovery_remove_tree($sourceRoot);
}

exit($exit ?? 0);

