<?php
/**
 * API: Reconcile PDF Backups
 * Walks uploads/backup/ and cross-references with the pdf_backups table.
 * Reports DB rows whose backup file is missing AND files on disk that have no DB row.
 * Admin access required.
 *
 * POST params:
 *   csrf_token (string)
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

try {
    // 1. Pull all DB rows
    $rows = $pdo->query("SELECT id, cert_type, record_id, backup_path, backed_up_at, restored_at FROM pdf_backups")
                ->fetchAll();

    $db_paths = [];
    foreach ($rows as $r) $db_paths[$r['backup_path']] = $r;

    // 2. Walk the backup directory
    $backup_root = UPLOAD_DIR . 'backup/';
    $disk_paths  = [];
    if (is_dir($backup_root)) {
        $rit = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($rit as $file) {
            if (!$file->isFile()) continue;
            // Build relative path under UPLOAD_DIR (e.g. "backup/birth/2026/x_172_.bak.pdf")
            $abs = str_replace('\\', '/', $file->getPathname());
            $base = str_replace('\\', '/', UPLOAD_DIR);
            if (strpos($abs, $base) === 0) {
                $rel = substr($abs, strlen($base));
                $disk_paths[$rel] = ['size' => $file->getSize(), 'mtime' => $file->getMTime()];
            }
        }
    }

    // 3. Missing on disk: DB row points to a file that doesn't exist
    $missing = [];
    foreach ($db_paths as $rel => $row) {
        if (!isset($disk_paths[$rel])) {
            $missing[] = [
                'id'           => (int)$row['id'],
                'cert_type'    => $row['cert_type'],
                'record_id'    => (int)$row['record_id'],
                'backup_path'  => $rel,
                'backed_up_at' => $row['backed_up_at'],
                'restored_at'  => $row['restored_at'],
            ];
        }
    }

    // 4. Orphan files: file on disk has no DB row
    $orphans = [];
    foreach ($disk_paths as $rel => $info) {
        if (!isset($db_paths[$rel])) {
            $orphans[] = [
                'backup_path' => $rel,
                'size'        => $info['size'],
                'mtime'       => date('Y-m-d H:i:s', $info['mtime']),
            ];
        }
    }

    // 5. Log security event
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('PDF_BACKUP_RECONCILE',
            (count($missing) || count($orphans)) ? 'MEDIUM' : 'LOW',
            $_SESSION['user_id'] ?? null,
            json_encode([
                'missing_count' => count($missing),
                'orphan_count'  => count($orphans),
                'db_total'      => count($db_paths),
                'disk_total'    => count($disk_paths),
            ]));
    }
    log_activity($pdo, 'PDF_BACKUP_RECONCILE',
        sprintf('Reconcile: %d missing files, %d orphan files (%d DB / %d disk)',
                count($missing), count($orphans), count($db_paths), count($disk_paths)),
        $_SESSION['user_id'] ?? null);

    echo json_encode([
        'success'    => true,
        'db_total'   => count($db_paths),
        'disk_total' => count($disk_paths),
        'missing'    => $missing,
        'orphans'    => $orphans,
    ]);

} catch (Exception $e) {
    error_log('pdf_backup_reconcile error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Reconcile failed: ' . $e->getMessage()]);
}
