<?php
/** Non-destructive guard test for Phase 6 live database restore controls. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/database_live_restore.php';

$checks = 0;
$passed = 0;
$expectReject = static function (array $preview) use (&$checks, &$passed): void {
    $checks++;
    try {
        database_live_restore_assert_preview($preview);
        throw new RuntimeException('Guard accepted an invalid preview.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Guard accepted an invalid preview.') {
            throw $e;
        }
        $passed++;
    }
};

$expectReject(['status' => 'running', 'table_statements' => 10, 'staging_table_count' => 10, 'dump_path' => __FILE__, 'manifest_path' => __FILE__]);
$expectReject(['status' => 'completed', 'table_statements' => 0, 'staging_table_count' => 0, 'dump_path' => __FILE__, 'manifest_path' => __FILE__]);
$expectReject(['status' => 'completed', 'table_statements' => 10, 'staging_table_count' => 10, 'dump_path' => __FILE__, 'manifest_path' => 'C:\nonexistent\iscan-manifest.json']);

echo "PASS: {$passed}/{$checks} live database restore guard checks rejected unsafe previews.\n";
