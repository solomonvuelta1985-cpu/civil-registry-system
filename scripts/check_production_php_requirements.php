<?php
/** CLI preflight for the active PHP runtime. Use the web page for PHP-FPM truth. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/runtime_requirements.php';
$report = iscan_runtime_requirements();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (!$report['ok']) exit(2);
if ($report['feature_warnings']) exit(3);
