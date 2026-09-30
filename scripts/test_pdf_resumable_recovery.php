<?php
/** End-to-end test for chunked pause/resume recovery. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$token = bin2hex(random_bytes(8));
$sourceRoot = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'resumable-source-' . $token;
$targetRelativeA = 'resumable_test/' . $token . '/a.pdf';
$targetRelativeB = 'resumable_test/' . $token . '/b.pdf';
$targetA = UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelativeA);
$targetB = UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelativeB);
$recordIds = [];
$jobId = 0;

function resumable_test_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname());
    }
    @rmdir($path);
}

function resumable_test_run(string $script, array $args): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    foreach ($args as $key => $value) {
        $command .= ' --' . $key . ($value === true ? '' : '=' . escapeshellarg((string)$value));
    }
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start resumable worker.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
    $jsonStart = strrpos($stdout, "{\n");
    $summary = $jsonStart === false ? null : json_decode(substr($stdout, $jsonStart), true);
    return [$exitCode, $stdout, $stderr, $summary];
}

try {
    if (!mkdir($sourceRoot, 0755, true)) throw new RuntimeException('Could not create source fixture root.');
    $fixtureA = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nA\n%%EOF\n";
    $fixtureB = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nB\n%%EOF\n";
    file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'a.pdf', $fixtureA);
    file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'b.pdf', $fixtureB);
    $hashA = hash_file('sha256', $sourceRoot . DIRECTORY_SEPARATOR . 'a.pdf');
    $hashB = hash_file('sha256', $sourceRoot . DIRECTORY_SEPARATOR . 'b.pdf');

    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', :last_name, :filename, :filepath, :hash, 'Active')"
    );
    foreach ([['A', $targetRelativeA, $targetA, $hashA], ['B', $targetRelativeB, $targetB, $hashB]] as $row) {
        $insert->execute([
            ':registry_no' => '__CODEX_RESUMABLE_' . $token . '_' . $row[0],
            ':last_name' => 'Resumable' . $row[0],
            ':filename' => $row[1], ':filepath' => $row[2], ':hash' => $row[3],
        ]);
        $recordIds[] = (int)$pdo->lastInsertId();
    }

    $worker = __DIR__ . DIRECTORY_SEPARATOR . 'pdf_protection_resumable_worker.php';
    [$exit1, $out1, $err1, $summary1] = resumable_test_run($worker, [
        'mode' => 'recovery', 'source' => $sourceRoot, 'destination' => UPLOAD_DIR, 'limit' => 1,
    ]);
    $jobId = (int)($summary1['job_id'] ?? 0);
    if ($exit1 !== 0 || !$jobId || ($summary1['copied_or_imported'] ?? 0) !== 1) {
        throw new RuntimeException('First resumable chunk failed: ' . $err1 . $out1);
    }

    $statusStmt = $pdo->prepare('SELECT status, scanned_items FROM pdf_protection_jobs WHERE id = :id');
    $statusStmt->execute([':id' => $jobId]);
    $state = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if (!$state || $state['status'] !== 'running' || (int)$state['scanned_items'] !== 1) {
        throw new RuntimeException('First chunk did not leave the job resumable.');
    }

    $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'paused' WHERE id = :id")->execute([':id' => $jobId]);
    [$exitPaused, $outPaused] = resumable_test_run($worker, [
        'job-id' => $jobId, 'mode' => 'recovery', 'source' => $sourceRoot, 'destination' => UPLOAD_DIR,
    ]);
    if ($exitPaused !== 0 || strpos($outPaused, '"status": "paused"') === false) {
        throw new RuntimeException('Paused job did not remain paused.');
    }

    $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'queued' WHERE id = :id")->execute([':id' => $jobId]);
    [$exit2, $out2, $err2, $summary2] = resumable_test_run($worker, [
        'job-id' => $jobId, 'mode' => 'recovery', 'source' => $sourceRoot, 'destination' => UPLOAD_DIR,
    ]);
    if ($exit2 !== 0 || ($summary2['status'] ?? '') !== 'completed' || ($summary2['copied_or_imported'] ?? 0) !== 1) {
        throw new RuntimeException('Resumed chunk did not complete: ' . $err2 . $out2);
    }
    foreach ([$targetA => $hashA, $targetB => $hashB] as $target => $hash) {
        if (!is_file($target) || !hash_equals(strtolower($hash), strtolower((string)hash_file('sha256', $target)))) {
            throw new RuntimeException('Resumed target hash verification failed.');
        }
    }

    $items = $pdo->prepare('SELECT COUNT(*) AS total, SUM(status = \'imported\') AS imported FROM pdf_protection_job_items WHERE job_id = :id');
    $items->execute([':id' => $jobId]);
    $counts = $items->fetch(PDO::FETCH_ASSOC);
    if ((int)$counts['total'] !== 2 || (int)$counts['imported'] !== 2) {
        throw new RuntimeException('Resumable job did not retain both imported item records.');
    }
    echo "PASS: resumable recovery paused, resumed, and verified two exact-hash imports.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($jobId) $pdo->prepare('DELETE FROM pdf_protection_jobs WHERE id = :id')->execute([':id' => $jobId]);
    foreach ($recordIds as $recordId) $pdo->prepare('DELETE FROM certificate_of_live_birth WHERE id = :id')->execute([':id' => $recordId]);
    if (is_file($targetA)) @unlink($targetA);
    if (is_file($targetB)) @unlink($targetB);
    resumable_test_remove_tree(dirname($targetA));
    resumable_test_remove_tree($sourceRoot);
}
exit($exit ?? 0);
