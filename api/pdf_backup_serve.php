<?php
/**
 * API: Serve a PDF Backup File (Preview / Download)
 * Streams a backup file inline (preview) or as attachment (download).
 * Admin access required.
 *
 * GET params:
 *   id          (int)    pdf_backups.id
 *   disposition (string) "inline" (default) or "attachment"
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn())            { http_response_code(401); exit('Not authenticated'); }
if (getUserRole() !== 'Admin'){ http_response_code(403); exit('Admin access required'); }

$backup_id   = (int)($_GET['id'] ?? 0);
$disposition = ($_GET['disposition'] ?? 'inline') === 'attachment' ? 'attachment' : 'inline';

if ($backup_id <= 0) { http_response_code(400); exit('Invalid backup ID'); }

try {
    $stmt = $pdo->prepare("SELECT cert_type, record_id, original_path, backup_path FROM pdf_backups WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $backup_id]);
    $backup = $stmt->fetch();

    if (!$backup) { http_response_code(404); exit('Backup record not found'); }

    $abs = UPLOAD_DIR . $backup['backup_path'];
    if (!file_exists($abs) || !is_readable($abs)) { http_response_code(404); exit('Backup file not found on disk'); }

    $handle = fopen($abs, 'rb');
    $header = fread($handle, 5);
    fclose($handle);
    if ($header !== '%PDF-') { http_response_code(415); exit('File is not a valid PDF'); }

    log_activity($pdo, 'PDF_BACKUP_VIEW',
        "Viewed backup #{$backup_id} ({$disposition}) for {$backup['cert_type']} record {$backup['record_id']}",
        $_SESSION['user_id'] ?? null);

    $filename = basename($backup['original_path']);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($abs));
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');

    while (ob_get_level() > 0) ob_end_clean();
    readfile($abs);
    exit;

} catch (PDOException $e) {
    error_log('pdf_backup_serve error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database error');
}
