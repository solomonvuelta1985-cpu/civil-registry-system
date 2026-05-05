<?php
/**
 * API: Bulk Export PDF Backups as ZIP
 * Builds a ZIP archive of selected backup files and streams it to the client.
 * Admin access required.
 *
 * POST params:
 *   csrf_token (string)
 *   ids        (array of int)  pdf_backups.id values
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn())            { http_response_code(401); exit('Not authenticated'); }
if (getUserRole() !== 'Admin'){ http_response_code(403); exit('Admin access required'); }

requireCSRFToken();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server is missing the ZipArchive extension']);
    exit;
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

if (empty($ids)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No backup IDs provided']); exit;
}
if (count($ids) > 500) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Too many IDs (max 500 per export)']); exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, cert_type, record_id, original_path, backup_path, backed_up_at
           FROM pdf_backups WHERE id IN ({$placeholders})"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No matching backups found']); exit;
    }

    // Temp ZIP under uploads/tmp/
    $tmp_dir = UPLOAD_DIR . 'tmp/';
    if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0755, true);
    $zip_path = $tmp_dir . 'pdf_backups_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Could not create ZIP archive']); exit;
    }

    $manifest = ["id,cert_type,record_id,original_path,backup_path,backed_up_at"];
    $added = 0;
    $missing = 0;
    foreach ($rows as $r) {
        $abs = UPLOAD_DIR . $r['backup_path'];
        if (!file_exists($abs)) { $missing++; continue; }
        $entry = sprintf('%s/record_%d/%d_%s',
                         $r['cert_type'],
                         (int)$r['record_id'],
                         (int)$r['id'],
                         basename($r['backup_path']));
        $zip->addFile($abs, $entry);
        $manifest[] = sprintf('%d,%s,%d,"%s","%s",%s',
                              $r['id'], $r['cert_type'], $r['record_id'],
                              str_replace('"', '""', $r['original_path']),
                              str_replace('"', '""', $r['backup_path']),
                              $r['backed_up_at']);
        $added++;
    }

    $zip->addFromString('manifest.csv', implode("\n", $manifest));
    $zip->close();

    if ($added === 0) {
        @unlink($zip_path);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "All {$missing} selected backup file(s) are missing on disk"]);
        exit;
    }

    log_activity($pdo, 'PDF_BACKUP_BULK_EXPORT',
        sprintf('Exported ZIP of %d backups (%d missing skipped)', $added, $missing),
        $_SESSION['user_id'] ?? null);

    $zip_size = filesize($zip_path);
    $download_name = 'pdf_backups_' . date('Ymd_His') . '.zip';

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Length: ' . $zip_size);
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($zip_path);
    @unlink($zip_path);
    exit;

} catch (Exception $e) {
    error_log('pdf_backup_bulk_export error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Export failed']);
}
