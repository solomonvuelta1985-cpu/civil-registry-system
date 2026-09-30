<?php
/**
 * Filesystem manifest and verified-copy helpers for PDF backup/recovery jobs.
 *
 * Callers must enforce administrator authorization before using these helpers
 * with external paths. Source and destination roots are treated as explicit
 * operator-selected locations; relative paths are always normalized and kept
 * below those roots.
 */

function pdf_manifest_relative_path(string $root, string $absolute): string {
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    $absolute = str_replace('\\', '/', $absolute);
    $prefix = $root . '/';
    if (strncmp($absolute, $prefix, strlen($prefix)) !== 0) {
        throw new RuntimeException('File is outside the selected manifest root.');
    }
    return ltrim(substr($absolute, strlen($prefix)), '/');
}

function pdf_manifest_join_path(string $root, string $relative): string {
    $relative = str_replace('\\', '/', $relative);
    if ($relative === '' || $relative[0] === '/' || preg_match('~(^|/)\.\.?(/|$)~', $relative)) {
        throw new InvalidArgumentException('Invalid relative PDF path.');
    }
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

/**
 * Scan PDF files under a root and return a verified manifest.
 *
 * The callback receives (scannedCount, currentFile, manifestItem) after each
 * PDF so a persistent job can publish live progress in small batches.
 */
function scan_pdf_manifest(string $root, ?callable $progress = null): array {
    $rootReal = realpath($root);
    if ($rootReal === false || !is_dir($rootReal)) {
        throw new InvalidArgumentException('Manifest root does not exist or is not a directory.');
    }

    $items = [];
    $scanned = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'pdf') {
            continue;
        }

        $absolute = $fileInfo->getPathname();
        $relative = pdf_manifest_relative_path($rootReal, $absolute);
        $size = (int)$fileInfo->getSize();
        $mtime = $fileInfo->getMTime();
        $hash = hash_file('sha256', $absolute);
        $valid = function_exists('validate_pdf_integrity')
            ? validate_pdf_integrity($absolute)
            : [];

        $item = [
            'absolute_path' => $absolute,
            'relative_path' => $relative,
            'filename'      => $fileInfo->getFilename(),
            'size'          => $size,
            'mtime'         => $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : null,
            'hash'          => is_string($hash) ? strtolower($hash) : null,
            'valid'         => empty($valid),
            'errors'        => $valid,
        ];
        $items[$relative] = $item;
        $scanned++;

        if ($progress !== null) {
            $progress($scanned, $absolute, $item);
        }
    }

    ksort($items, SORT_NATURAL | SORT_FLAG_CASE);
    return $items;
}

/**
 * Compare source and destination manifests. No files are copied here.
 */
function compare_pdf_manifests(array $source, array $destination): array {
    $result = [
        'unchanged' => [],
        'new'       => [],
        'changed'   => [],
        'orphan'    => [],
        'invalid'   => [],
    ];

    foreach ($source as $relative => $sourceItem) {
        if (!$sourceItem['valid']) {
            $result['invalid'][$relative] = $sourceItem;
            continue;
        }
        if (!isset($destination[$relative])) {
            $result['new'][$relative] = $sourceItem;
            continue;
        }
        if (($sourceItem['hash'] ?? null) === ($destination[$relative]['hash'] ?? null)) {
            $result['unchanged'][$relative] = $sourceItem;
        } else {
            $result['changed'][$relative] = [
                'source'      => $sourceItem,
                'destination' => $destination[$relative],
            ];
        }
    }

    foreach ($destination as $relative => $destinationItem) {
        if (!isset($source[$relative])) {
            $result['orphan'][$relative] = $destinationItem;
        }
    }

    return $result;
}

/**
 * Copy one file through a temporary path and verify the resulting hash.
 */
function copy_pdf_verified(string $source, string $destination, string $expectedHash): string {
    if (!is_file($source) || !is_readable($source)) {
        throw new RuntimeException('Source PDF is missing or unreadable.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/i', $expectedHash)) {
        throw new InvalidArgumentException('Invalid expected PDF hash.');
    }

    $sourceHash = hash_file('sha256', $source);
    if (!is_string($sourceHash) || !hash_equals(strtolower($expectedHash), strtolower($sourceHash))) {
        throw new RuntimeException('Source PDF hash does not match the expected hash.');
    }

    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create destination directory.');
    }

    $temporary = $destination . '.part-' . bin2hex(random_bytes(8));
    try {
        if (!copy($source, $temporary)) {
            throw new RuntimeException('Could not copy source PDF.');
        }
        $destinationHash = hash_file('sha256', $temporary);
        if (!is_string($destinationHash) || !hash_equals(strtolower($expectedHash), strtolower($destinationHash))) {
            throw new RuntimeException('Copied PDF failed post-copy hash verification.');
        }
        if (!rename($temporary, $destination)) {
            throw new RuntimeException('Could not finalize copied PDF.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }

    return strtolower($destinationHash);
}

