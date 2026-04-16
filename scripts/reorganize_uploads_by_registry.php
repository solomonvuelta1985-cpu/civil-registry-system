<?php
/**
 * CLI script to reorganize existing PDF uploads into the new
 * year + last-name folder structure.
 *
 * Usage:
 *   php scripts/reorganize_uploads_by_registry.php --dry-run --token=SECRET
 *   php scripts/reorganize_uploads_by_registry.php --apply  --token=SECRET
 *
 * The REORG_TOKEN env var (or .env entry) must match the --token value.
 * Default mode is dry-run (no changes).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reorganize_uploads.php';

// --- Parse CLI args ---
$apply   = false;
$token   = null;

foreach ($argv as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (strpos($arg, '--token=') === 0) {
        $token = substr($arg, 8);
    }
}

// --- Token guard ---
$expected = env('REORG_TOKEN', '');
if ($expected === '' || $token === null || $token !== $expected) {
    fwrite(STDERR, "ERROR: Invalid or missing --token. Set REORG_TOKEN in .env.\n");
    exit(1);
}

// --- Set up log file ---
$logDir  = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/reorganize_' . date('Ymd_His') . '.log';
$fh      = fopen($logFile, 'w');

$logFn = function (string $line) use ($fh) {
    $ts = date('Y-m-d H:i:s');
    $formatted = "[{$ts}] {$line}";
    fwrite($fh, $formatted . "\n");
    echo $formatted . "\n";
};

$mode = $apply ? 'APPLY' : 'DRY-RUN';
$logFn("=== Reorganize Uploads ({$mode}) ===");
$logFn("Upload dir: " . UPLOAD_DIR);

// --- Run ---
$stats = reorganize_uploads($pdo, UPLOAD_DIR, $apply, $logFn);

$logFn("Log saved to: {$logFile}");
fclose($fh);

exit($stats['error'] > 0 ? 2 : 0);
