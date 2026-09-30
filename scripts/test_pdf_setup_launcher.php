<?php
/** Verify the web-style server-side launcher starts a dry-run worker. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdf_protection_jobs.php';
require_once __DIR__ . '/../includes/pdf_protection_launcher.php';

$token = bin2hex(random_bytes(8));
$source = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'launcher-source-' . $token;
$destination = BASE_PATH . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'launcher-destination-' . $token;
$jobId = 0;

function launcher_test_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
    @rmdir($path);
}

try {
    if (!mkdir($source, 0755, true) || !mkdir($destination, 0755, true)) throw new RuntimeException('Could not create launcher test roots.');
    file_put_contents($source . DIRECTORY_SEPARATOR . 'launcher.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nLAUNCH\n%%EOF\n");
    $jobId = create_pdf_protection_job($pdo, 'backup', realpath($source), realpath($destination), null);
    launch_pdf_protection_worker($jobId, 'backup', realpath($source), realpath($destination), true);

    $status = null;
    for ($i = 0; $i < 50; $i++) {
        usleep(100000);
        $status = get_pdf_protection_job($pdo, $jobId);
        if ($status && in_array($status['status'], ['preview_ready', 'completed', 'completed_with_errors', 'failed'], true)) break;
    }
    if (!$status || $status['status'] !== 'preview_ready') throw new RuntimeException('Background launcher did not finish the dry run: ' . json_encode($status));
    echo "PASS: server-side launcher started and completed a dry-run worker.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    if ($jobId) $pdo->prepare('DELETE FROM pdf_protection_jobs WHERE id = :id')->execute([':id' => $jobId]);
    launcher_test_remove_tree($source);
    launcher_test_remove_tree($destination);
}
exit($exit ?? 0);
