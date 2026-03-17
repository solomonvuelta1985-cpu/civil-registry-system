<?php
/**
 * API: Clean Up Old PDF Backups
 * Deletes backup files and DB rows older than N days that have NOT been restored.
 * Backups that were used for restoration are kept permanently.
 * Admin access required.
 *
 * POST params:
 *   csrf_token        (string) CSRF token
 *   older_than_days   (int)    Delete backups older than this many days (min 7, max 365)
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

$days = (int)($_POST['older_than_days'] ?? 90);
$days = max(7, min(365, $days)); // Clamp between 7 and 365

try {
    // Find backups older than $days that have NOT been restored
    $stmt = $pdo->prepare(
        "SELECT id, backup_path FROM pdf_backups
          WHERE backed_up_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND restored_at IS NULL"
    );
    $stmt->execute([':days' => $days]);
    $to_delete = $stmt->fetchAll();

    $deleted_files  = 0;
    $freed_bytes    = 0;
    $deleted_ids    = [];

    foreach ($to_delete as $row) {
        $abs_path = UPLOAD_DIR . $row['backup_path'];
        if (file_exists($abs_path)) {
            $size = filesize($abs_path);
            if (@unlink($abs_path)) {
                $freed_bytes += $size;
                $deleted_files++;
            }
        }
        $deleted_ids[] = $row['id'];
    }

    // Delete DB rows for files we processed (even if file was already missing)
    if (!empty($deleted_ids)) {
        $placeholders = implode(',', array_fill(0, count($deleted_ids), '?'));
        $pdo->prepare("DELETE FROM pdf_backups WHERE id IN ({$placeholders})")
            ->execute($deleted_ids);
    }

    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'] ?? null, 'PDF_BACKUP_CLEANUP',
            "Cleaned {$deleted_files} backup files older than {$days} days, freed " . round($freed_bytes / 1024 / 1024, 2) . ' MB');
    }

    echo json_encode([
        'success'       => true,
        'deleted_files' => $deleted_files,
        'freed_bytes'   => $freed_bytes,
        'freed_mb'      => round($freed_bytes / 1024 / 1024, 2),
        'message'       => "Deleted {$deleted_files} backup file(s), freed " . round($freed_bytes / 1024 / 1024, 2) . ' MB.',
    ]);

} catch (PDOException $e) {
    error_log('pdf_backup_cleanup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during cleanup']);
}
