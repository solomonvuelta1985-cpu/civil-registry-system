<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/database_live_restore_launcher.php';
require_once __DIR__ . '/../includes/database_live_restore.php';
echo json_encode(database_live_restore_approvals($pdo, 1, 'restore'), JSON_PRETTY_PRINT) . PHP_EOL;
