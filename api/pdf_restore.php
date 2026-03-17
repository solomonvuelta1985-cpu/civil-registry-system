<?php
/**
 * API: Restore a PDF from Backup
 * Copies a backup file back as the current PDF for a certificate record.
 * Admin access required.
 *
 * POST params:
 *   csrf_token  (string) CSRF token
 *   backup_id   (int)    ID from pdf_backups table
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required']); exit; }

requireCSRFToken();

$backup_id = (int)($_POST['backup_id'] ?? 0);
if ($backup_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid backup ID']);
    exit;
}

$table_map = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

try {
    $stmt = $pdo->prepare("SELECT * FROM pdf_backups WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $backup_id]);
    $backup = $stmt->fetch();

    if (!$backup) {
        echo json_encode(['success' => false, 'message' => 'Backup record not found']);
        exit;
    }

    $backup_abs  = UPLOAD_DIR . $backup['backup_path'];
    $current_abs = UPLOAD_DIR . $backup['original_path'];

    // Verify backup file exists
    if (!file_exists($backup_abs)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found on disk — it may have been cleaned up']);
        exit;
    }

    // Verify backup integrity before restoring
    $bkp_header = fread(fopen($backup_abs, 'rb'), 5);
    if ($bkp_header !== '%PDF-') {
        echo json_encode(['success' => false, 'message' => 'Backup file is not a valid PDF — cannot restore a corrupt backup']);
        exit;
    }
    if (!empty($backup['file_hash'])) {
        $actual_bkp = hash_file('sha256', $backup_abs);
        if (!hash_equals($backup['file_hash'], $actual_bkp)) {
            echo json_encode(['success' => false, 'message' => 'Backup file is corrupted (hash mismatch). Manual intervention required.']);
            exit;
        }
    }

    // If a current file exists at the original path, chain-backup it first
    if (file_exists($current_abs)) {
        $chain_backup = backup_pdf_file($backup['original_path']);
        if ($chain_backup) {
            $chainStmt = $pdo->prepare(
                "INSERT INTO pdf_backups
                    (cert_type, record_id, original_path, backup_path, file_hash, backed_up_by)
                 VALUES (:type, :rid, :orig, :bkp, :hash, :uid)"
            );
            $chainStmt->execute([
                ':type' => $backup['cert_type'],
                ':rid'  => $backup['record_id'],
                ':orig' => $backup['original_path'],
                ':bkp'  => $chain_backup,
                ':hash' => compute_file_hash(UPLOAD_DIR . $chain_backup),
                ':uid'  => $_SESSION['user_id'] ?? null,
            ]);
        }
    }

    // Ensure target directory exists
    @mkdir(dirname($current_abs), 0755, true);

    // Copy backup to original path (keep backup in place)
    if (!copy($backup_abs, $current_abs)) {
        echo json_encode(['success' => false, 'message' => 'Failed to copy backup file — check disk permissions']);
        exit;
    }

    // Compute new hash of restored file
    $new_hash = compute_file_hash($current_abs);

    // Update the certificate table with new hash
    if (!isset($table_map[$backup['cert_type']])) {
        echo json_encode(['success' => false, 'message' => 'Unknown certificate type in backup record']);
        exit;
    }
    $tbl = $table_map[$backup['cert_type']];

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE {$tbl} SET pdf_hash = :hash WHERE id = :id")
        ->execute([':hash' => $new_hash, ':id' => $backup['record_id']]);
    $pdo->prepare("UPDATE pdf_backups SET restored_at = NOW(), restored_by = :uid WHERE id = :id")
        ->execute([':uid' => $_SESSION['user_id'] ?? null, ':id' => $backup_id]);
    $pdo->commit();

    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('PDF_RESTORED', 'MEDIUM', $_SESSION['user_id'] ?? null,
            json_encode(['backup_id' => $backup_id, 'cert_type' => $backup['cert_type'], 'record_id' => $backup['record_id']]));
    }
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'] ?? null, 'PDF_RESTORE',
            'Restored PDF backup ID ' . $backup_id . ' for ' . $backup['cert_type'] . ' record ' . $backup['record_id']);
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'PDF restored successfully.',
        'new_hash' => $new_hash,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('pdf_restore error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during restore']);
}
