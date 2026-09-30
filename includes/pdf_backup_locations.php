<?php
/** Safe storage-root discovery and custom-folder validation for PDF jobs. */

function pdf_backup_normalize_root(string $path): ?string {
    $path = trim($path);
    if ($path === '') return null;
    $real = realpath($path);
    return $real !== false && is_dir($real) ? rtrim($real, "\\/") . DIRECTORY_SEPARATOR : null;
}

function pdf_backup_path_key(string $path): string {
    $path = str_replace('/', DIRECTORY_SEPARATOR, trim($path));
    return DIRECTORY_SEPARATOR === '\\' ? strtolower(rtrim($path, "\\/")) : rtrim($path, '/');
}

function pdf_backup_path_within(string $path, string $root): bool {
    $pathKey = pdf_backup_path_key($path);
    $rootKey = pdf_backup_path_key($root);
    return $pathKey === $rootKey || str_starts_with($pathKey, $rootKey . DIRECTORY_SEPARATOR);
}

function pdf_backup_allowed_roots(): array {
    $configured = trim((string)env('PDF_BACKUP_ALLOWED_ROOTS', ''));
    $paths = $configured !== '' ? preg_split('/[;,]/', $configured) : [];
    if (empty($paths) && DIRECTORY_SEPARATOR === '\\') foreach (range('A', 'Z') as $letter) $paths[] = $letter . ':\\';
    if (empty($paths)) $paths = [BASE_PATH, sys_get_temp_dir()];
    $roots = [];
    foreach ($paths as $path) {
        $root = pdf_backup_normalize_root((string)$path);
        if ($root === null) continue;
        $roots[pdf_backup_path_key($root)] = $root;
    }
    ksort($roots, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values($roots);
}

function pdf_backup_find_allowed_root(string $selectedRoot): ?string {
    $selectedReal = realpath($selectedRoot);
    if ($selectedReal === false || !is_dir($selectedReal)) return null;
    $selectedKey = pdf_backup_path_key($selectedReal);
    foreach (pdf_backup_allowed_roots() as $root) if (pdf_backup_path_key($root) === $selectedKey) return $root;
    return null;
}

function pdf_backup_validate_folder_name(string $folderName): string {
    $folderName = trim($folderName);
    if ($folderName === '' || $folderName === '.' || $folderName === '..') throw new InvalidArgumentException('A folder name is required.');
    if (strlen($folderName) > 80 || strpos($folderName, '/') !== false || strpos($folderName, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $folderName)) throw new InvalidArgumentException('Folder name contains invalid characters or is too long.');
    if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9 _.-]*[A-Za-z0-9])?$/', $folderName)) throw new InvalidArgumentException('Folder name must use letters, numbers, spaces, dots, hyphens, or underscores.');
    if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\..*)?$/i', $folderName)) throw new InvalidArgumentException('That folder name is reserved by Windows.');
    return $folderName;
}

function pdf_backup_storage_info(string $root, bool $requireWritable = false): array {
    $root = pdf_backup_find_allowed_root($root);
    if ($root === null) throw new RuntimeException('Selected storage root is not allowed.');
    $free = @disk_free_space($root);
    if ($free === false) throw new RuntimeException('Could not read free space for the selected storage root.');
    $writable = @is_writable($root);
    if ($requireWritable && !$writable) throw new RuntimeException('The selected storage root cannot create a new folder. Choose a writable external drive or configure its permissions.');
    return ['root' => $root, 'free_bytes' => (int)$free, 'free_gb' => round($free / 1024 / 1024 / 1024, 2), 'writable' => $writable];
}

function pdf_backup_assert_writable_path(string $path): void {
    if (!is_dir($path) || !is_writable($path)) throw new RuntimeException('The selected backup folder is not writable by the web server account.');
    $probe = rtrim($path, "\\/") . DIRECTORY_SEPARATOR . '.iscan-write-test-' . bin2hex(random_bytes(6));
    if (@file_put_contents($probe, 'iSCAN write test') === false) throw new RuntimeException('The selected backup folder is not writable by the web server account.');
    @unlink($probe);
}

/** Resolve one safe child folder below an approved root. Recovery sources may be read-only. */
function pdf_backup_resolve_folder(string $rootInput, string $folderName, bool $create, bool $requireWritable = true): array {
    $root = pdf_backup_find_allowed_root($rootInput);
    if ($root === null) throw new RuntimeException('Selected storage root is not allowed.');
    $folderName = pdf_backup_validate_folder_name($folderName);
    $candidate = rtrim($root, "\\/") . DIRECTORY_SEPARATOR . $folderName;

    if (is_dir($candidate)) {
        $resolved = realpath($candidate);
        if ($resolved === false || !pdf_backup_path_within($resolved, $root)) throw new RuntimeException('The selected folder resolves outside the approved storage root.');
        $path = rtrim($resolved, "\\/") . DIRECTORY_SEPARATOR;
    } else {
        if (!$create) throw new RuntimeException('The selected recovery folder does not exist.');
        if (file_exists($candidate)) throw new RuntimeException('A file already uses the requested folder name.');
        if (!mkdir($candidate, 0755, false) && !is_dir($candidate)) throw new RuntimeException('The selected storage root cannot create the requested folder. Choose a writable external drive or configure its permissions.');
        $resolved = realpath($candidate);
        if ($resolved === false || !pdf_backup_path_within($resolved, $root)) throw new RuntimeException('Created folder failed the storage-root safety check.');
        $path = rtrim($resolved, "\\/") . DIRECTORY_SEPARATOR;
    }
    if (!is_readable($path)) throw new RuntimeException('The selected folder is not readable.');
    if ($requireWritable) pdf_backup_assert_writable_path($path);
    $info = pdf_backup_storage_info($root, false);
    return ['root' => $root, 'folder_name' => $folderName, 'path' => $path, 'free_bytes' => $info['free_bytes'], 'free_gb' => $info['free_gb'], 'writable' => $info['writable']];
}
