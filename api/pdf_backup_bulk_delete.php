<?php
/**
 * API: Bulk Delete PDF Backups
 * Deletes multiple pending (non-restored) backup files and DB rows.
 * Restored backups are protected and never deleted.
 * Admin access required.
 *
 * POST params:
 *   csrf_token (string)
 *   ids        (array of int) pdf_backups.id values
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn())            { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (getUserRole() !== 'Admin'){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }

requireCSRFToken();

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

if (empty($ids)) { echo json_encode(['success'=>false,'message'=>'No backup IDs provided']); exit; }
if (count($ids) > 500) { echo json_encode(['success'=>false,'message'=>'Too many IDs (max 500 per call)']); exit; }

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, backup_path, restored_at FROM pdf_backups
          WHERE id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $deleted_files = 0;
    $freed_bytes   = 0;
    $deleted_ids   = [];
    $skipped_restored = 0;

    foreach ($rows as $row) {
        if (!empty($row['restored_at'])) { $skipped_restored++; continue; }
        $abs = UPLOAD_DIR . $row['backup_path'];
        if (file_exists($abs)) {
            $size = @filesize($abs) ?: 0;
            if (@unlink($abs)) {
                $freed_bytes  += $size;
                $deleted_files++;
            }
        }
        $deleted_ids[] = (int)$row['id'];
    }

    if (!empty($deleted_ids)) {
        $ph = implode(',', array_fill(0, count($deleted_ids), '?'));
        $pdo->prepare("DELETE FROM pdf_backups WHERE id IN ({$ph})")->execute($deleted_ids);
    }

    log_activity($pdo, 'PDF_BACKUP_BULK_DELETE',
        sprintf('Bulk-deleted %d backups (%.2f MB), %d skipped restored',
                $deleted_files, $freed_bytes / 1024 / 1024, $skipped_restored),
        $_SESSION['user_id'] ?? null);

    echo json_encode([
        'success'          => true,
        'deleted'          => $deleted_files,
        'freed_bytes'      => $freed_bytes,
        'freed_mb'         => round($freed_bytes / 1024 / 1024, 2),
        'skipped_restored' => $skipped_restored,
        'message'          => sprintf('Deleted %d backup(s), freed %.2f MB%s',
                                      $deleted_files,
                                      $freed_bytes / 1024 / 1024,
                                      $skipped_restored ? " ({$skipped_restored} restored backups protected)" : ''),
    ]);

} catch (PDOException $e) {
    error_log('pdf_backup_bulk_delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during bulk delete']);
}
