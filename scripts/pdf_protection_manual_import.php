<?php
/**
 * Manually approve and import one recovery item after an administrator has
 * reviewed an unmatched or ambiguous source PDF.
 *
 * Usage:
 *   php scripts/pdf_protection_manual_import.php --job-id=4 --item-id=22 --cert-type=birth --record-id=145
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pdf_fingerprint_registry.php';
require_once __DIR__ . '/../includes/pdf_backup_manifest.php';

$options = getopt('', ['job-id:', 'item-id:', 'cert-type:', 'record-id:']);
$jobId = (int)($options['job-id'] ?? 0);
$itemId = (int)($options['item-id'] ?? 0);
$type = strtolower(trim((string)($options['cert-type'] ?? '')));
$recordId = (int)($options['record-id'] ?? 0);
$tables = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

if ($jobId <= 0 || $itemId <= 0 || !isset($tables[$type]) || $recordId <= 0) {
    fwrite(STDERR, "Usage: php scripts/pdf_protection_manual_import.php --job-id=N --item-id=N --cert-type=birth|death|marriage|marriage_license --record-id=N\n");
    exit(2);
}

$jobStmt = $pdo->prepare("SELECT * FROM pdf_protection_jobs WHERE id = :id AND job_type = 'recovery' LIMIT 1");
$jobStmt->execute([':id' => $jobId]);
$job = $jobStmt->fetch(PDO::FETCH_ASSOC);
$itemStmt = $pdo->prepare('SELECT * FROM pdf_protection_job_items WHERE id = :id AND job_id = :job_id LIMIT 1');
$itemStmt->execute([':id' => $itemId, ':job_id' => $jobId]);
$item = $itemStmt->fetch(PDO::FETCH_ASSOC);
if (!$job || !$item) { fwrite(STDERR, "Job or item not found.\n"); exit(1); }

$source = (string)$item['source_path'];
if (!is_file($source)) { fwrite(STDERR, "Source PDF is missing.\n"); exit(1); }
$hash = hash_file('sha256', $source);
if (!is_string($hash) || !empty(validate_pdf_integrity($source))) { fwrite(STDERR, "Source PDF is invalid or unreadable.\n"); exit(1); }

$table = $tables[$type];
$recordStmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id AND status = 'Active' LIMIT 1");
$recordStmt->execute([':id' => $recordId]);
$record = $recordStmt->fetch(PDO::FETCH_ASSOC);
if (!$record) { fwrite(STDERR, "Active target record not found.\n"); exit(1); }

$owner = pdf_fingerprint_owner($pdo, $hash);
if ($owner !== null && !($owner['cert_type'] === $type && (int)$owner['record_id'] === $recordId)) {
    fwrite(STDERR, "Source PDF is already reserved by {$owner['cert_type']} #{$owner['record_id']}.\n");
    exit(1);
}

$targetRelative = str_replace('\\', '/', ltrim((string)($record['pdf_filename'] ?? ''), '/'));
if ($targetRelative === '') {
    $targetRelative = 'recovered/' . $type . '/record_' . $recordId . '/recovered_' . strtolower($hash) . '.pdf';
}
$target = pdf_manifest_join_path(UPLOAD_DIR, $targetRelative);

try {
    pdf_worker_manual_install($source, $target, $hash);
} catch (Throwable $e) {
    // The helper is defined below to keep this CLI script self-contained.
    fwrite(STDERR, 'Copy failed: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $pdo->beginTransaction();
    $update = $pdo->prepare(
        "UPDATE {$table}
            SET pdf_filename = :filename, pdf_filepath = :filepath, pdf_hash = :hash,
                updated_at = NOW()
          WHERE id = :id"
    );
    $update->execute([
        ':filename' => $targetRelative,
        ':filepath' => UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative),
        ':hash'     => strtolower($hash),
        ':id'       => $recordId,
    ]);
    $itemUpdate = $pdo->prepare(
        "UPDATE pdf_protection_job_items
            SET destination_path = :destination_path, destination_hash = :destination_hash,
                cert_type = :cert_type, record_id = :record_id, registry_no = :registry_no,
                match_method = 'manual', status = 'imported', reviewed_at = NOW(),
                imported_at = NOW(), last_error = NULL, updated_at = NOW()
          WHERE id = :id AND job_id = :job_id"
    );
    $itemUpdate->execute([
        ':destination_path' => $target,
        ':destination_hash' => strtolower($hash),
        ':cert_type' => $type,
        ':record_id' => $recordId,
        ':registry_no' => $record['registry_no'] ?? null,
        ':id' => $itemId,
        ':job_id' => $jobId,
    ]);
    if (function_exists('log_activity')) {
        log_activity($pdo, 'PDF_RECOVERY_MANUAL_IMPORT', "Manually mapped recovery item {$itemId} to {$type} record {$recordId}", null);
    }
    $pdo->commit();
    echo "Imported item {$itemId} to {$type} record {$recordId}.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Database update failed: ' . $e->getMessage() . "\n");
    exit(1);
}

function pdf_worker_manual_install(string $source, string $destination, string $hash): void {
    if (is_file($destination)) {
        $current = hash_file('sha256', $destination);
        if (is_string($current) && hash_equals(strtolower($hash), strtolower($current))) return;
        $quarantine = $destination . '.corrupt-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!rename($destination, $quarantine)) throw new RuntimeException('Could not quarantine existing target PDF.');
        try { copy_pdf_verified($source, $destination, $hash); }
        catch (Throwable $e) { if (!is_file($destination)) @rename($quarantine, $destination); throw $e; }
        return;
    }
    copy_pdf_verified($source, $destination, $hash);
}

