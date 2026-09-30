<?php
/** Read-only verification helpers for database backup dumps. */
require_once __DIR__ . '/database_backup.php';

function verify_database_backup_job(PDO $pdo, int $jobId): array
{
    $job = get_database_backup_job($pdo, $jobId);
    if (!$job) throw new RuntimeException('Database backup job not found.');
    if ((string)$job['status'] !== 'completed') throw new RuntimeException('Only completed database backups can be verified.');
    $dump = (string)$job['dump_path']; $manifestPath = (string)$job['manifest_path'];
    if (!is_file($dump) || !is_readable($dump)) throw new RuntimeException('The database dump is missing or unreadable.');
    if (!is_file($manifestPath) || !is_readable($manifestPath)) throw new RuntimeException('The backup manifest is missing or unreadable.');
    $manifest = json_decode((string)file_get_contents($manifestPath), true); if (!is_array($manifest)) throw new RuntimeException('The backup manifest is not valid JSON.');
    $dumpHash = strtolower((string)hash_file('sha256', $dump)); $manifestHash = strtolower((string)hash_file('sha256', $manifestPath));
    $tableStatements = 0; $insertStatements = 0; $handle = fopen($dump, 'rb');
    if ($handle !== false) { while (($line = fgets($handle)) !== false) { if (preg_match('/^CREATE TABLE/i', ltrim($line))) $tableStatements++; if (preg_match('/^INSERT INTO/i', ltrim($line))) $insertStatements++; } fclose($handle); }
    $fileMatch = hash_equals(strtolower((string)$job['file_hash']), $dumpHash) && hash_equals(strtolower((string)($manifest['file_sha256'] ?? '')), $dumpHash);
    $manifestMatch = hash_equals(strtolower((string)$job['manifest_hash']), $manifestHash);
    return ['job_id'=>$jobId,'valid'=>$fileMatch&&$manifestMatch,'file_exists'=>true,'manifest_exists'=>true,'file_size'=>(int)filesize($dump),'file_hash'=>$dumpHash,'expected_file_hash'=>(string)$job['file_hash'],'manifest_hash'=>$manifestHash,'expected_manifest_hash'=>(string)$job['manifest_hash'],'database_name'=>$manifest['database_name']??$job['database_name'],'table_statements'=>$tableStatements,'insert_statements'=>$insertStatements,'checked_at'=>date('c')];
}
