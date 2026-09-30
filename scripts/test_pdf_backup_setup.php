<?php
/** Verify approved-root discovery and safe custom-folder validation. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
$root = __DIR__ . '/../tmp/pdf-setup-root-' . bin2hex(random_bytes(8));
$root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
$folder = 'iSCAN_Test_Backup_001';

function setup_test_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
    @rmdir($path);
}

try {
    if (!mkdir($root, 0755, true)) throw new RuntimeException('Could not create test storage root.');
    $root = realpath($root);
    putenv('PDF_BACKUP_ALLOWED_ROOTS=' . $root);
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/pdf_backup_locations.php';

    if (pdf_backup_find_allowed_root($root) === null) throw new RuntimeException('Configured root was not discovered.');
    $created = pdf_backup_resolve_folder($root, $folder, true);
    if (!is_dir($created['path']) || !pdf_backup_path_within($created['path'], $root)) throw new RuntimeException('Safe child folder was not created correctly.');
    $existing = pdf_backup_resolve_folder($root, $folder, false);
    if ($existing['path'] !== $created['path']) throw new RuntimeException('Existing folder resolution is inconsistent.');

    foreach (['../escape', 'nested/name', 'CON', 'bad.'] as $invalid) {
        $rejected = false;
        try { pdf_backup_validate_folder_name($invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
        if (!$rejected) throw new RuntimeException('Unsafe folder name was accepted: ' . $invalid);
    }
    echo "PASS: approved storage root and custom folder validation are safe and idempotent.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    setup_test_remove_tree($root);
}
exit($exit ?? 0);
