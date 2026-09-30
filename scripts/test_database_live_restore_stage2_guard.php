<?php
/** Read-only guard test for same-administrator Stage 2 rejection. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/database_live_restore_launcher.php';
require_once __DIR__ . '/../includes/database_live_restore.php';

try {
    database_live_restore_approve($pdo, 1, 'restore', 2, 4);
    fwrite(STDERR, "FAIL: same administrator was allowed to approve Stage 2.\n");
    exit(1);
} catch (RuntimeException $e) {
    echo $e->getMessage() . PHP_EOL;
    if ($e->getMessage() !== 'The second approval must come from a different administrator.') exit(1);
}
