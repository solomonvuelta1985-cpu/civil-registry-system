<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

function migration_stop(string $message, int $status = 1): never
{
    fwrite(STDERR, 'STOP: ' . $message . PHP_EOL);
    exit($status);
}

if (($argc < 3 || $argc > 4) || !in_array($argv[1] ?? '', ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php apply_single_migration.php --check|--apply NNN_migration_name.sql [--env-file=/absolute/path/.env]\n");
    exit(2);
}

$mode = $argv[1];
$migrationName = $argv[2];
$envFileOption = $argv[3] ?? null;
$envFile = null;
if ($envFileOption !== null) {
    if (!str_starts_with($envFileOption, '--env-file=')) {
        fwrite(STDERR, "Usage: php apply_single_migration.php --check|--apply NNN_migration_name.sql [--env-file=/absolute/path/.env]\n");
        exit(2);
    }
    $envFile = substr($envFileOption, strlen('--env-file='));
    if ($envFile === '' || basename($envFile) !== '.env' || !str_starts_with($envFile, DIRECTORY_SEPARATOR)
        || is_link($envFile) || !is_file($envFile)) {
        migration_stop('The optional environment file must be an existing, non-symlink absolute .env file.');
    }
}
if (!preg_match('/\A[0-9]{3}_[a-zA-Z0-9_-]+\.sql\z/', $migrationName)) {
    migration_stop('Provide one numbered migration filename only; paths are not accepted.');
}

$migrationDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'migrations');
$migrationPath = $migrationDirectory === false
    ? false
    : realpath($migrationDirectory . DIRECTORY_SEPARATOR . $migrationName);
if ($migrationDirectory === false || $migrationPath === false
    || dirname($migrationPath) !== $migrationDirectory || is_link($migrationDirectory . DIRECTORY_SEPARATOR . $migrationName)
    || !is_file($migrationPath)) {
    migration_stop('Migration file not found under database/migrations.');
}

$sql = file_get_contents($migrationPath);
if ($sql === false || trim($sql) === '') {
    migration_stop('Migration file is empty or unreadable.');
}

require_once __DIR__ . '/../includes/env_loader.php';
if ($envFile !== null) {
    // A staged runner may live under /tmp while production credentials remain
    // in the live release. Clear inherited values so this file supplies every
    // database setting instead of silently mixing two environments.
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
    if (!loadEnv($envFile)) {
        migration_stop('Could not load the specified environment file.');
    }
    $missing = array_values(array_filter(
        ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'],
        static fn(string $key): bool => getenv($key) === false
    ));
    if ($missing !== []) {
        migration_stop('The specified environment file is missing required database settings: ' . implode(', ', $missing));
    }
}
$dbHost = (string) env('DB_HOST', 'localhost');
$dbName = (string) env('DB_NAME', 'iscan_db');
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $currentDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($currentDatabase !== $dbName) {
        migration_stop('Connected database does not match DB_NAME from the live environment.');
    }

    $trackingTable = $pdo->query("SHOW TABLES LIKE 'migrations'")->fetchColumn();
    if ($trackingTable === false) {
        migration_stop('Migration tracking table is missing; stop for manual review.');
    }

    $findApplied = $pdo->prepare('SELECT migration_name FROM migrations WHERE migration_name = :name LIMIT 1');
    $findApplied->execute([':name' => $migrationName]);
    if ($findApplied->fetchColumn() !== false) {
        echo "MIGRATION_STATUS=ALREADY_APPLIED\nDATABASE={$currentDatabase}\nMIGRATION={$migrationName}\n";
        exit(0);
    }

    $hash = hash('sha256', $sql);
    if ($mode === '--check') {
        echo "MIGRATION_STATUS=PENDING\nDATABASE={$currentDatabase}\nMIGRATION={$migrationName}\nSHA256={$hash}\n";
        exit(0);
    }

    if ((int) $pdo->query("SELECT GET_LOCK('iscan_single_migration', 0)")->fetchColumn() !== 1) {
        migration_stop('Another migration is running; retry only after confirming it has finished.');
    }

    // Recheck after taking the lock so simultaneous invocations cannot apply the same file twice.
    $findApplied->execute([':name' => $migrationName]);
    if ($findApplied->fetchColumn() !== false) {
        echo "MIGRATION_STATUS=ALREADY_APPLIED\nDATABASE={$currentDatabase}\nMIGRATION={$migrationName}\n";
        exit(0);
    }

    // MySQL DDL may implicitly commit; a failed migration must be inspected before any retry.
    $pdo->exec($sql);
    $record = $pdo->prepare('INSERT INTO migrations (migration_name) VALUES (:name)');
    $record->execute([':name' => $migrationName]);
    echo "MIGRATION_STATUS=APPLIED\nDATABASE={$currentDatabase}\nMIGRATION={$migrationName}\nSHA256={$hash}\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    if ($mode === '--apply') {
        fwrite(STDERR, "STOP: Inspect the database schema and migration tracking row before considering any retry.\n");
    }
    exit(1);
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('iscan_single_migration')");
        } catch (Throwable $ignored) {
        }
    }
}
