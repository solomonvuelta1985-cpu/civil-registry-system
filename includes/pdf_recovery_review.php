<?php
/** Approved, hash-verified recovery review/import helpers. */
require_once __DIR__ . '/pdf_backup_manifest.php';
require_once __DIR__ . '/pdf_fingerprint_registry.php';
require_once __DIR__ . '/pdf_protection_resumable.php';

function pdf_recovery_review_tables(): array
{
    return ['birth' => 'certificate_of_live_birth', 'death' => 'certificate_of_death', 'marriage' => 'certificate_of_marriage', 'marriage_license' => 'application_for_marriage_license'];
}

function pdf_recovery_review_manual_import(PDO $pdo, int $jobId, int $itemId, string $certType, int $recordId, ?int $userId = null): array
{
    $tables = pdf_recovery_review_tables();
    if (!isset($tables[$certType]) || $recordId <= 0) throw new InvalidArgumentException('A valid certificate type and active record ID are required.');
    $jobStmt = $pdo->prepare("SELECT * FROM pdf_protection_jobs WHERE id = :id AND job_type = 'recovery' LIMIT 1");
    $jobStmt->execute([':id' => $jobId]); $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) throw new RuntimeException('Recovery job not found.');
    if (!in_array((string)$job['status'], ['completed_with_errors'], true)) throw new RuntimeException('Manual import is available only after a recovery job completes with review findings.');
    $itemStmt = $pdo->prepare("SELECT * FROM pdf_protection_job_items WHERE id = :id AND job_id = :job_id AND status IN ('unmatched','needs_review','corrupt_source') LIMIT 1");
    $itemStmt->execute([':id' => $itemId, ':job_id' => $jobId]); $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new RuntimeException('The recovery item is not pending manual review.');

    $sourceRoot = realpath((string)$job['source_root']); $source = realpath((string)$item['source_path']);
    if ($sourceRoot === false || $source === false || !is_file($source)) throw new RuntimeException('The recovery source PDF is missing.');
    $rootPrefix = rtrim(str_replace('\\', '/', $sourceRoot), '/') . '/'; $sourceNormalized = str_replace('\\', '/', $source);
    if (strncmp($sourceNormalized, $rootPrefix, strlen($rootPrefix)) !== 0) throw new RuntimeException('The source PDF is outside the recovery job root.');
    $integrityErrors = validate_pdf_integrity($source); if (!empty($integrityErrors)) throw new RuntimeException('The source PDF is invalid: ' . implode('; ', $integrityErrors));
    $hash = strtolower((string)hash_file('sha256', $source)); if (!preg_match('/^[a-f0-9]{64}$/', $hash)) throw new RuntimeException('Could not calculate a valid source PDF hash.');

    $table = $tables[$certType]; $recordStmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id AND status = 'Active' LIMIT 1");
    $recordStmt->execute([':id' => $recordId]); $record = $recordStmt->fetch(PDO::FETCH_ASSOC); if (!$record) throw new RuntimeException('The target record does not exist or is not active.');
    $owner = pdf_fingerprint_owner($pdo, $hash); if ($owner !== null && !($owner['cert_type'] === $certType && (int)$owner['record_id'] === $recordId)) throw new RuntimeException('This PDF hash is already reserved by another active record.');

    $targetRelative = str_replace('\\', '/', ltrim((string)($record['pdf_filename'] ?? ''), '/')); if ($targetRelative === '') $targetRelative = 'recovered/' . $certType . '/record_' . $recordId . '/recovered_' . $hash . '.pdf';
    $target = pdf_manifest_join_path(UPLOAD_DIR, $targetRelative); $existingHash = is_file($target) ? hash_file('sha256', $target) : null;
    if (is_string($existingHash) && !hash_equals($hash, strtolower($existingHash))) throw new RuntimeException('The destination already contains a different PDF; it was not overwritten.');
    if (!is_string($existingHash)) pdf_resumable_install_verified($source, $target, $hash);

    try {
        $pdo->beginTransaction();
        release_pdf_fingerprint($pdo, $certType, $recordId);
        reserve_pdf_fingerprint($pdo, $hash, $certType, $recordId, $record['registry_no'] ?? null, $userId);
        $update = $pdo->prepare("UPDATE {$table} SET pdf_filename=:filename, pdf_filepath=:filepath, pdf_hash=:hash, updated_at=NOW() WHERE id=:id AND status='Active'");
        $update->execute([':filename' => $targetRelative, ':filepath' => UPLOAD_DIR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative), ':hash' => $hash, ':id' => $recordId]);
        $itemUpdate = $pdo->prepare("UPDATE pdf_protection_job_items SET destination_path=:destination_path, destination_hash=:destination_hash, cert_type=:cert_type, record_id=:record_id, registry_no=:registry_no, expected_hash=:expected_hash, match_method='manual', status='imported', reviewed_by=:reviewed_by, reviewed_at=NOW(), imported_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=:id AND job_id=:job_id");
        $itemUpdate->execute([':destination_path' => $target, ':destination_hash' => $hash, ':cert_type' => $certType, ':record_id' => $recordId, ':registry_no' => $record['registry_no'] ?? null, ':expected_hash' => $hash, ':reviewed_by' => $userId, ':id' => $itemId, ':job_id' => $jobId]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

    $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_protection_job_items WHERE job_id=:job_id AND status IN ('failed','invalid_pdf','corrupt_source','needs_review','unmatched')"); $remainingStmt->execute([':job_id' => $jobId]);
    $nextStatus = (int)$remainingStmt->fetchColumn() > 0 ? 'completed_with_errors' : 'completed'; pdf_resumable_refresh_counters($pdo, $jobId, $nextStatus);
    if (function_exists('log_activity')) log_activity($pdo, 'PDF_RECOVERY_MANUAL_IMPORT', sprintf('Recovery item %d mapped to %s record %d by administrator', $itemId, $certType, $recordId), $userId);
    return ['job_id' => $jobId, 'item_id' => $itemId, 'status' => 'imported', 'cert_type' => $certType, 'record_id' => $recordId, 'destination_path' => $target, 'hash' => $hash, 'job_status' => $nextStatus];
}
