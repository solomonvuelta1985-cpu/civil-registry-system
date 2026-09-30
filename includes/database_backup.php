<?php
/** Verified database backup job helpers. */

function database_backup_cli_binary(string $tool): string
{
    $tool = $tool === 'mysql' ? 'mysql.exe' : 'mysqldump.exe';
    $candidates = [];
    $configured = trim((string)env(strtoupper($tool === 'mysql.exe' ? 'DATABASE_MYSQL_BINARY' : 'DATABASE_MYSQLDUMP_BINARY'), ''));
    if ($configured !== '') $candidates[] = $configured;
    if (defined('BASE_PATH')) $candidates[] = dirname(dirname(BASE_PATH)) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $tool;
    if (defined('PHP_BINDIR')) $candidates[] = dirname((string)PHP_BINDIR) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $tool;
    foreach (array_unique($candidates) as $candidate) if (is_file($candidate)) return $candidate;
    throw new RuntimeException($tool . ' was not found. Configure the XAMPP MySQL binary path.');
}

function database_backup_windows_arg(string $value): string
{
    $result = ''; $slashes = 0; $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $char = $value[$i];
        if ($char === '\\') { $slashes++; continue; }
        if ($char === '"') { $result .= str_repeat('\\', ($slashes * 2) + 1) . '"'; $slashes = 0; continue; }
        if ($slashes > 0) { $result .= str_repeat('\\', $slashes); $slashes = 0; }
        $result .= $char;
    }
    if ($slashes > 0) $result .= str_repeat('\\', $slashes * 2);
    return '"' . $result . '"';
}

function create_database_backup_job(PDO $pdo, string $destinationRoot, string $backupFolder, ?int $userId = null): int
{
    $stmt = $pdo->prepare('INSERT INTO database_backup_jobs (destination_root, backup_folder, database_name, database_host, created_by, last_activity_at) VALUES (:destination_root, :backup_folder, :database_name, :database_host, :created_by, NOW())');
    $stmt->execute([':destination_root' => $destinationRoot, ':backup_folder' => $backupFolder, ':database_name' => DB_NAME, ':database_host' => DB_HOST, ':created_by' => $userId]);
    $id = (int)$pdo->lastInsertId();
    database_backup_log($pdo, $id, 'info', 'Database backup job created.');
    return $id;
}

function get_database_backup_job(PDO $pdo, int $jobId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM database_backup_jobs WHERE id=:id LIMIT 1'); $stmt->execute([':id' => $jobId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null;
}

function update_database_backup_job(PDO $pdo, int $jobId, string $status, array $fields = []): void
{
    $allowed = ['dump_path','manifest_path','file_size','file_hash','manifest_hash','last_error','started_at','last_activity_at','completed_at']; $sets = ['status=:status','last_activity_at=NOW()']; $params = [':status' => $status, ':id' => $jobId];
    foreach ($allowed as $field) if (array_key_exists($field, $fields)) { $sets[] = "{$field}=:{$field}"; $params[":{$field}"] = $fields[$field]; }
    if ($status === 'running') $sets[] = 'started_at=COALESCE(started_at,NOW())';
    if (in_array($status, ['completed','completed_with_errors','failed','cancelled'], true)) $sets[] = 'completed_at=COALESCE(completed_at,NOW())';
    $stmt = $pdo->prepare('UPDATE database_backup_jobs SET ' . implode(',', $sets) . ' WHERE id=:id'); $stmt->execute($params);
}

function database_backup_log(PDO $pdo, int $jobId, string $level, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO database_backup_job_logs (job_id, level, message) VALUES (:job_id, :level, :message)'); $stmt->execute([':job_id' => $jobId, ':level' => $level, ':message' => mb_substr($message, 0, 1000)]);
}

function database_backup_job_logs(PDO $pdo, int $jobId, int $limit = 100): array
{
    $stmt = $pdo->prepare('SELECT id, level, message, created_at FROM database_backup_job_logs WHERE job_id=:job_id ORDER BY id DESC LIMIT :limit'); $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT); $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
