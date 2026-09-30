<?php
/** Isolated database restore preview and PDF-reference validation helpers. */
require_once __DIR__ . '/database_backup_verify.php';
require_once __DIR__ . '/pdf_backup_manifest.php';

function create_database_restore_preview_job(PDO $pdo, int $backupJobId, ?int $userId = null): int
{
    $backup = get_database_backup_job($pdo, $backupJobId);
    if (!$backup) throw new RuntimeException('Database backup job not found.');
    if ((string)$backup['status'] !== 'completed') throw new RuntimeException('Only completed database backups can be previewed.');
    $verification = verify_database_backup_job($pdo, $backupJobId);
    if (empty($verification['valid'])) throw new RuntimeException('The database backup failed hash verification and cannot be previewed.');

    $stmt = $pdo->prepare('INSERT INTO database_restore_preview_jobs (backup_job_id, staging_database_host, dump_path, manifest_path, expected_file_hash, expected_manifest_hash, dump_size, created_by, last_activity_at) VALUES (:backup_job_id, :staging_database_host, :dump_path, :manifest_path, :expected_file_hash, :expected_manifest_hash, :dump_size, :created_by, NOW())');
    $stmt->execute([
        ':backup_job_id' => $backupJobId,
        ':staging_database_host' => (string)$backup['database_host'],
        ':dump_path' => (string)$backup['dump_path'],
        ':manifest_path' => (string)$backup['manifest_path'],
        ':expected_file_hash' => $backup['file_hash'] ?: null,
        ':expected_manifest_hash' => $backup['manifest_hash'] ?: null,
        ':dump_size' => $backup['file_size'] ?: null,
        ':created_by' => $userId,
    ]);
    $id = (int)$pdo->lastInsertId();
    database_restore_preview_log($pdo, $id, 'info', 'Restore preview job created after backup hash verification.');
    return $id;
}

function get_database_restore_preview_job(PDO $pdo, int $jobId): ?array
{
    $stmt = $pdo->prepare('SELECT r.*, b.database_name, b.database_host AS backup_database_host, b.backup_folder FROM database_restore_preview_jobs r JOIN database_backup_jobs b ON b.id=r.backup_job_id WHERE r.id=:id LIMIT 1');
    $stmt->execute([':id' => $jobId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function update_database_restore_preview_job(PDO $pdo, int $jobId, string $status, array $fields = []): void
{
    $allowed = [
        'staging_database_name','actual_file_hash','actual_manifest_hash','dump_size',
        'table_statements','insert_statements','staging_table_count','pdf_reference_count',
        'pdf_present_count','pdf_missing_count','pdf_invalid_count','report_path',
        'last_error','started_at','last_activity_at','completed_at'
    ];
    $sets = ['status=:status', 'last_activity_at=NOW()'];
    $params = [':status' => $status, ':id' => $jobId];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $fields)) {
            $sets[] = "{$field}=:{$field}";
            $params[":{$field}"] = $fields[$field];
        }
    }
    if ($status === 'running') $sets[] = 'started_at=COALESCE(started_at,NOW())';
    if (in_array($status, ['completed','completed_with_errors','failed','cancelled','cleaned'], true)) $sets[] = 'completed_at=COALESCE(completed_at,NOW())';
    $stmt = $pdo->prepare('UPDATE database_restore_preview_jobs SET ' . implode(',', $sets) . ' WHERE id=:id');
    $stmt->execute($params);
}

function database_restore_preview_log(PDO $pdo, int $jobId, string $level, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO database_restore_preview_logs (job_id, level, message) VALUES (:job_id, :level, :message)');
    $stmt->execute([':job_id' => $jobId, ':level' => $level, ':message' => mb_substr($message, 0, 1000)]);
}

function database_restore_preview_job_logs(PDO $pdo, int $jobId, int $limit = 100): array
{
    $stmt = $pdo->prepare('SELECT id, level, message, created_at FROM database_restore_preview_logs WHERE job_id=:job_id ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function database_restore_preview_identifier(string $value): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) throw new InvalidArgumentException('Invalid staging database identifier.');
    return '`' . $value . '`';
}

function database_restore_preview_mysql_command(?string $database = null, ?string $execute = null): string
{
    $command = database_backup_windows_arg(database_backup_cli_binary('mysql'));
    $args = ['--host=' . DB_HOST, '--user=' . DB_USER, '--default-character-set=utf8mb4'];
    if (DB_PASS !== '') $args[] = '--password=' . DB_PASS;
    if ($database !== null) $args[] = '--database=' . $database;
    if ($execute !== null) $args[] = '--execute=' . $execute;
    foreach ($args as $arg) $command .= ' ' . database_backup_windows_arg($arg);
    return $command;
}

function database_restore_preview_process(string $command, ?string $inputFile = null): array
{
    $descriptors = [
        0 => $inputFile === null ? ['pipe', 'r'] : ['file', $inputFile, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, BASE_PATH);
    if (!is_resource($process)) throw new RuntimeException('Could not start the MySQL restore-preview process.');
    if ($inputFile === null) fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => trim((string)$stdout), 'stderr' => trim((string)$stderr)];
}

function database_restore_preview_normalize_dump(string $sourcePath, string $sourceDatabase, string $stagingDatabase): string
{
    $input = fopen($sourcePath, 'rb');
    if ($input === false) throw new RuntimeException('Could not read the verified database dump.');
    $temporary = tempnam(sys_get_temp_dir(), 'iscan-restore-preview-');
    if ($temporary === false) { fclose($input); throw new RuntimeException('Could not create a temporary restore-preview dump.'); }
    $output = fopen($temporary, 'wb');
    if ($output === false) { fclose($input); @unlink($temporary); throw new RuntimeException('Could not open the temporary restore-preview dump.'); }

    $sourcePattern = preg_quote($sourceDatabase, '~');
    while (($line = fgets($input)) !== false) {
        if (preg_match('~^(\s*USE\s+)(?:`' . $sourcePattern . '`|' . $sourcePattern . ')(\s*;\s*)$~i', $line, $matches)) {
            $line = $matches[1] . '`' . $stagingDatabase . '`' . $matches[2];
        } elseif (preg_match('~^(\s*CREATE\s+DATABASE\s+)(?:IF\s+NOT\s+EXISTS\s+)?(?:`' . $sourcePattern . '`|' . $sourcePattern . ')(\s*;\s*)$~i', $line)) {
            $line = 'CREATE DATABASE IF NOT EXISTS `' . $stagingDatabase . '`;' . PHP_EOL;
        }
        if (fwrite($output, $line) === false) {
            fclose($input); fclose($output); @unlink($temporary);
            throw new RuntimeException('Could not write the temporary restore-preview dump.');
        }
    }
    fclose($input);
    fclose($output);
    return $temporary;
}

function database_restore_preview_path_from_record(array $record): ?string
{
    $root = realpath(UPLOAD_DIR);
    if ($root === false || !is_dir($root)) return null;
    $rootNormalized = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $candidates = [(string)($record['pdf_filepath'] ?? ''), (string)($record['pdf_filename'] ?? '')];
    foreach ($candidates as $candidate) {
        $candidate = trim(str_replace("\0", '', $candidate));
        if ($candidate === '') continue;
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $candidate) || str_starts_with($candidate, '\\\\')) {
            $path = $candidate;
        } else {
            $relative = ltrim(str_replace('\\', '/', $candidate), '/');
            if (stripos($relative, 'uploads/') === 0) $relative = substr($relative, 8);
            try { $path = pdf_manifest_join_path($root, $relative); } catch (Throwable $e) { continue; }
        }
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) continue;
        $normalized = str_replace('\\', '/', $real);
        if (strncasecmp($normalized, $rootNormalized, strlen($rootNormalized)) !== 0) continue;
        return $real;
    }
    return null;
}

function database_restore_preview_pdf_references(PDO $stagingPdo): array
{
    $tables = [
        'birth' => 'certificate_of_live_birth',
        'death' => 'certificate_of_death',
        'marriage' => 'certificate_of_marriage',
        'marriage_license' => 'application_for_marriage_license',
    ];
    $summary = ['reference_count' => 0, 'present_count' => 0, 'missing_count' => 0, 'invalid_count' => 0, 'details' => []];
    foreach ($tables as $certType => $table) {
        $columnsStmt = $stagingPdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=:schema AND table_name=:table');
        $columnsStmt->execute([':schema' => $stagingPdo->query('SELECT DATABASE()')->fetchColumn(), ':table' => $table]);
        $columns = array_fill_keys($columnsStmt->fetchAll(PDO::FETCH_COLUMN), true);
        if (!isset($columns['id']) || (!isset($columns['pdf_filename']) && !isset($columns['pdf_filepath']) && !isset($columns['pdf_hash']))) continue;
        $select = ['id'];
        foreach (['pdf_filename','pdf_filepath','pdf_hash'] as $column) $select[] = isset($columns[$column]) ? database_restore_preview_identifier($column) . ' AS ' . $column : 'NULL AS ' . $column;
        $where = [];
        if (isset($columns['status'])) $where[] = "status='Active'";
        $pdfWhere = [];
        foreach (['pdf_filename','pdf_filepath','pdf_hash'] as $column) if (isset($columns[$column])) $pdfWhere[] = database_restore_preview_identifier($column) . " IS NOT NULL AND " . database_restore_preview_identifier($column) . " <> ''";
        if (!$pdfWhere) continue;
        $where[] = '(' . implode(' OR ', $pdfWhere) . ')';
        $sql = 'SELECT ' . implode(',', $select) . ' FROM ' . database_restore_preview_identifier($table) . ' WHERE ' . implode(' AND ', $where);
        $rows = $stagingPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $summary['reference_count']++;
            $path = database_restore_preview_path_from_record($row);
            $expectedHash = strtolower(trim((string)($row['pdf_hash'] ?? '')));
            $detail = ['cert_type' => $certType, 'record_id' => (int)$row['id'], 'pdf_filename' => (string)($row['pdf_filename'] ?? ''), 'status' => 'missing'];
            if ($path === null) {
                $summary['missing_count']++;
                $summary['details'][] = $detail;
                continue;
            }
            $actualHash = strtolower((string)hash_file('sha256', $path));
            $integrityErrors = function_exists('validate_pdf_integrity') ? validate_pdf_integrity($path) : [];
            if (!empty($integrityErrors) || ($expectedHash !== '' && (!preg_match('/^[a-f0-9]{64}$/', $expectedHash) || !hash_equals($expectedHash, $actualHash)))) {
                $summary['invalid_count']++;
                $detail['status'] = 'invalid';
                $detail['actual_hash'] = $actualHash;
                $detail['expected_hash'] = $expectedHash;
                $detail['errors'] = $integrityErrors;
                $summary['details'][] = $detail;
                continue;
            }
            $summary['present_count']++;
            $detail['status'] = 'present';
            $detail['actual_hash'] = $actualHash;
            $summary['details'][] = $detail;
        }
    }
    return $summary;
}

function database_restore_preview_report_path(int $jobId): string
{
    $directory = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'restore_previews';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Could not create the restore-preview report directory.');
    return $directory . DIRECTORY_SEPARATOR . 'restore-preview-job-' . $jobId . '.json';
}

function database_restore_preview_run(PDO $pdo, int $jobId): array
{
    $job = get_database_restore_preview_job($pdo, $jobId);
    if (!$job) throw new RuntimeException('Restore preview job not found.');
    if (in_array((string)$job['status'], ['completed','completed_with_errors','cleaned','cancelled'], true)) return $job;
    $stagingDatabase = 'iscan_restore_preview_job_' . $jobId;
    $temporaryDump = null;
    $stagingCreated = false;
    try {
        update_database_restore_preview_job($pdo, $jobId, 'running', ['staging_database_name' => $stagingDatabase, 'last_error' => null]);
        database_restore_preview_log($pdo, $jobId, 'info', 'Re-validating the backup manifest and SHA-256 before staging import.');
        $verification = verify_database_backup_job($pdo, (int)$job['backup_job_id']);
        if (empty($verification['valid'])) throw new RuntimeException('The source database backup failed verification.');
        update_database_restore_preview_job($pdo, $jobId, 'running', ['actual_file_hash' => $verification['file_hash'], 'actual_manifest_hash' => $verification['manifest_hash'], 'dump_size' => $verification['file_size'], 'table_statements' => $verification['table_statements'], 'insert_statements' => $verification['insert_statements']]);

        $pdo->exec('DROP DATABASE IF EXISTS ' . database_restore_preview_identifier($stagingDatabase));
        $pdo->exec('CREATE DATABASE ' . database_restore_preview_identifier($stagingDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $stagingCreated = true;
        database_restore_preview_log($pdo, $jobId, 'info', 'Created isolated staging database ' . $stagingDatabase . '.');
        $temporaryDump = database_restore_preview_normalize_dump((string)$job['dump_path'], (string)$job['database_name'], $stagingDatabase);
        $import = database_restore_preview_process(database_restore_preview_mysql_command($stagingDatabase), $temporaryDump);
        if ((int)$import['exit_code'] !== 0) throw new RuntimeException('Staging SQL import failed: ' . ($import['stderr'] ?: $import['stdout']));
        database_restore_preview_log($pdo, $jobId, 'info', 'SQL dump imported into the isolated staging database.');

        $stagingPdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $stagingDatabase . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $tableStmt = $stagingPdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=:schema AND table_type='BASE TABLE'");
        $tableStmt->execute([':schema' => $stagingDatabase]);
        $stagingTableCount = (int)$tableStmt->fetchColumn();
        $pdfSummary = database_restore_preview_pdf_references($stagingPdo);
        $report = ['format' => 'iSCAN restore preview report v1', 'preview_job_id' => $jobId, 'backup_job_id' => (int)$job['backup_job_id'], 'generated_at' => date('c'), 'backup_verification' => $verification, 'staging_database' => ['host' => DB_HOST, 'name' => $stagingDatabase, 'table_count' => $stagingTableCount], 'pdf_summary' => ['reference_count' => $pdfSummary['reference_count'], 'present_count' => $pdfSummary['present_count'], 'missing_count' => $pdfSummary['missing_count'], 'invalid_count' => $pdfSummary['invalid_count']], 'pdf_details' => $pdfSummary['details']];
        $reportPath = database_restore_preview_report_path($jobId);
        if (file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Could not write the restore-preview report.');
        $hasWarnings = $stagingTableCount !== (int)$verification['table_statements'] || $pdfSummary['missing_count'] > 0 || $pdfSummary['invalid_count'] > 0;
        $nextStatus = $hasWarnings ? 'completed_with_errors' : 'completed';
        update_database_restore_preview_job($pdo, $jobId, $nextStatus, ['staging_table_count' => $stagingTableCount, 'pdf_reference_count' => $pdfSummary['reference_count'], 'pdf_present_count' => $pdfSummary['present_count'], 'pdf_missing_count' => $pdfSummary['missing_count'], 'pdf_invalid_count' => $pdfSummary['invalid_count'], 'report_path' => $reportPath, 'last_error' => null]);
        database_restore_preview_log($pdo, $jobId, $hasWarnings ? 'warning' : 'info', $hasWarnings ? 'Preview completed with validation findings; production restore is not approved.' : 'Preview completed with matching table and PDF checks.');
        return get_database_restore_preview_job($pdo, $jobId) ?: [];
    } catch (Throwable $e) {
        update_database_restore_preview_job($pdo, $jobId, 'failed', ['last_error' => $e->getMessage()]);
        database_restore_preview_log($pdo, $jobId, 'error', $e->getMessage());
        if ($stagingCreated) {
            try { $pdo->exec('DROP DATABASE IF EXISTS ' . database_restore_preview_identifier($stagingDatabase)); database_restore_preview_log($pdo, $jobId, 'warning', 'Partial staging database removed after preview failure.'); } catch (Throwable $cleanupError) { database_restore_preview_log($pdo, $jobId, 'error', 'Could not remove partial staging database: ' . $cleanupError->getMessage()); }
        }
        throw $e;
    } finally {
        if (is_string($temporaryDump) && is_file($temporaryDump)) @unlink($temporaryDump);
    }
}

function cleanup_database_restore_preview(PDO $pdo, int $jobId): array
{
    $job = get_database_restore_preview_job($pdo, $jobId);
    if (!$job) throw new RuntimeException('Restore preview job not found.');
    if (!in_array((string)$job['status'], ['completed','completed_with_errors','failed'], true)) throw new RuntimeException('Only finished restore previews can be cleaned up.');
    $database = (string)($job['staging_database_name'] ?? '');
    if (!preg_match('/^iscan_restore_preview_job_[0-9]+$/', $database)) throw new RuntimeException('The staging database name is not recognized as system-created.');
    $pdo->exec('DROP DATABASE IF EXISTS ' . database_restore_preview_identifier($database));
    update_database_restore_preview_job($pdo, $jobId, 'cleaned', ['last_error' => null]);
    database_restore_preview_log($pdo, $jobId, 'info', 'Staging database removed by administrator.');
    return get_database_restore_preview_job($pdo, $jobId) ?: [];
}

function get_database_restore_preview_report(array $job): array
{
    $path = (string)($job['report_path'] ?? '');
    if ($path === '' || !is_file($path) || !is_readable($path)) throw new RuntimeException('Restore-preview report is not available.');
    $base = realpath(BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'restore_previews');
    $real = realpath($path);
    if ($base === false || $real === false || strncasecmp(str_replace('\\', '/', $real), rtrim(str_replace('\\', '/', $base), '/') . '/', strlen(rtrim(str_replace('\\', '/', $base), '/') . '/')) !== 0) throw new RuntimeException('Restore-preview report path is outside the protected report directory.');
    $report = json_decode((string)file_get_contents($real), true);
    if (!is_array($report)) throw new RuntimeException('Restore-preview report is not valid JSON.');
    return $report;
}
