<?php
/** Verify a corrupt source cannot overwrite a healthy external backup. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$token = bin2hex(random_bytes(8));
$sourceRoot = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'corrupt-source-' . $token;
$destinationRoot = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'corrupt-destination-' . $token;
$recordId = 0;
$jobId = 0;

function corrupt_test_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
    @rmdir($path);
}

try {
    if (!mkdir($sourceRoot, 0755, true) || !mkdir($destinationRoot, 0755, true)) throw new RuntimeException('Could not create test roots.');
    $healthy = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nHEALTHY\n%%EOF\n";
    $corrupt = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nCORRUPTED\n%%EOF\n";
    $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . 'record.pdf';
    $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . 'record.pdf';
    file_put_contents($sourcePath, $corrupt);
    file_put_contents($destinationPath, $healthy);
    $healthyHash = hash('sha256', $healthy);

    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Integrity', 'record.pdf', 'record.pdf', :hash, 'Active')"
    );
    $insert->execute([':registry_no' => '__CODEX_CORRUPT_' . $token, ':hash' => $healthyHash]);
    $recordId = (int)$pdo->lastInsertId();

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'pdf_protection_resumable_worker.php')
        . ' --mode=backup --source=' . escapeshellarg($sourceRoot)
        . ' --destination=' . escapeshellarg($destinationRoot);
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start backup worker.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
    $jsonStart = strrpos($stdout, "{\n");
    $summary = $jsonStart === false ? null : json_decode(substr($stdout, $jsonStart), true);
    $jobId = (int)($summary['job_id'] ?? 0);
    if ($exitCode !== 0 || !$jobId || ($summary['corrupt_source'] ?? 0) !== 1) {
        throw new RuntimeException('Corrupt source was not reported: ' . $stderr . $stdout);
    }
    if (!hash_equals($healthyHash, hash_file('sha256', $destinationPath))) {
        throw new RuntimeException('Healthy destination was overwritten by corrupt source.');
    }
    $item = $pdo->prepare('SELECT status FROM pdf_protection_job_items WHERE job_id = :job_id');
    $item->execute([':job_id' => $jobId]);
    if ($item->fetchColumn() !== 'corrupt_source') throw new RuntimeException('Per-file corrupt_source status was not recorded.');
    echo "PASS: corrupt source was reported and healthy backup remained unchanged.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($jobId) $pdo->prepare('DELETE FROM pdf_protection_jobs WHERE id = :id')->execute([':id' => $jobId]);
    if ($recordId) $pdo->prepare('DELETE FROM certificate_of_live_birth WHERE id = :id')->execute([':id' => $recordId]);
    corrupt_test_remove_tree($sourceRoot);
    corrupt_test_remove_tree($destinationRoot);
}
exit($exit ?? 0);
