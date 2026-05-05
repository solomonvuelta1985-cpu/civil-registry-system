<?php
/**
 * API: Clean Up Old PDF Backups
 *
 * Two cleanup modes:
 *   mode=age      Delete pending backups older than N days (default mode, original behavior).
 *   mode=keep_n   For each (cert_type, record_id), keep the latest N pending backups; delete the rest.
 *
 * Restored backups are NEVER deleted by either mode.
 * Admin access required.
 *
 * POST params:
 *   csrf_token        (string)
 *   mode              (string)  "age" (default) or "keep_n"
 *   older_than_days   (int)     when mode=age. 7..365, default 90.
 *   keep_per_record   (int)     when mode=keep_n. 1..20, default 3.
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

$mode = ($_POST['mode'] ?? 'age') === 'keep_n' ? 'keep_n' : 'age';

try {
    $to_delete = [];

    if ($mode === 'age') {
        $days = (int)($_POST['older_than_days'] ?? 90);
        $days = max(7, min(365, $days));
        $stmt = $pdo->prepare(
            "SELECT id, backup_path FROM pdf_backups
              WHERE backed_up_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND restored_at IS NULL"
        );
        $stmt->execute([':days' => $days]);
        $to_delete = $stmt->fetchAll();
        $mode_desc = "older than {$days} days";

    } else { // keep_n
        $keep = (int)($_POST['keep_per_record'] ?? 3);
        $keep = max(1, min(20, $keep));

        // For each (cert_type, record_id) bucket, keep the latest $keep pending backups
        // and mark the rest for deletion. Compatible with older MariaDB (no window functions).
        $rows = $pdo->query(
            "SELECT id, cert_type, record_id, backup_path, backed_up_at
               FROM pdf_backups
              WHERE restored_at IS NULL
           ORDER BY cert_type, record_id, backed_up_at DESC, id DESC"
        )->fetchAll();

        $bucket_count = [];
        foreach ($rows as $r) {
            $key = $r['cert_type'] . ':' . $r['record_id'];
            $bucket_count[$key] = ($bucket_count[$key] ?? 0) + 1;
            if ($bucket_count[$key] > $keep) {
                $to_delete[] = ['id' => $r['id'], 'backup_path' => $r['backup_path']];
            }
        }
        $mode_desc = "keep latest {$keep} per record";
    }

    $deleted_files = 0;
    $freed_bytes   = 0;
    $deleted_ids   = [];

    foreach ($to_delete as $row) {
        $abs_path = UPLOAD_DIR . $row['backup_path'];
        if (file_exists($abs_path)) {
            $size = @filesize($abs_path) ?: 0;
            if (@unlink($abs_path)) {
                $freed_bytes += $size;
                $deleted_files++;
            }
        }
        $deleted_ids[] = (int)$row['id'];
    }

    if (!empty($deleted_ids)) {
        $placeholders = implode(',', array_fill(0, count($deleted_ids), '?'));
        $pdo->prepare("DELETE FROM pdf_backups WHERE id IN ({$placeholders})")
            ->execute($deleted_ids);
    }

    log_activity($pdo, 'PDF_BACKUP_CLEANUP',
        sprintf('Cleanup (%s): %d files deleted, freed %.2f MB',
                $mode_desc, $deleted_files, $freed_bytes / 1024 / 1024),
        $_SESSION['user_id'] ?? null);

    echo json_encode([
        'success'       => true,
        'mode'          => $mode,
        'deleted_files' => $deleted_files,
        'freed_bytes'   => $freed_bytes,
        'freed_mb'      => round($freed_bytes / 1024 / 1024, 2),
        'message'       => sprintf('Deleted %d backup(s), freed %.2f MB (%s).',
                                   $deleted_files, $freed_bytes / 1024 / 1024, $mode_desc),
    ]);

} catch (PDOException $e) {
    error_log('pdf_backup_cleanup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during cleanup']);
}
