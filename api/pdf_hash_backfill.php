<?php
/**
 * API: Backfill PDF Hash for a Single Record
 * Computes and stores the SHA-256 hash for an existing record that has no hash yet.
 * Used for records uploaded before the pdf_hash feature was added.
 * Admin access required.
 *
 * POST params:
 *   csrf_token   (string) CSRF token
 *   cert_type    (string) birth | death | marriage | marriage_license
 *   record_id    (int)    ID of the record
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

$table_map = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

$cert_type = trim($_POST['cert_type'] ?? '');
$record_id = (int)($_POST['record_id'] ?? 0);

if (!isset($table_map[$cert_type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid certificate type']);
    exit;
}
if ($record_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

$table = $table_map[$cert_type];

try {
    $stmt = $pdo->prepare("SELECT pdf_filename FROM {$table} WHERE id = :id AND status != 'Deleted' LIMIT 1");
    $stmt->execute([':id' => $record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    if (empty($record['pdf_filename'])) {
        echo json_encode(['success' => false, 'message' => 'This record has no PDF attached']);
        exit;
    }

    $abs_path = UPLOAD_DIR . $record['pdf_filename'];
    if (!file_exists($abs_path)) {
        echo json_encode(['success' => false, 'message' => 'PDF file not found on disk: ' . $record['pdf_filename']]);
        exit;
    }

    $hash = compute_file_hash($abs_path);
    if (empty($hash)) {
        echo json_encode(['success' => false, 'message' => 'Failed to compute file hash']);
        exit;
    }

    $pdo->prepare("UPDATE {$table} SET pdf_hash = :hash WHERE id = :id")
        ->execute([':hash' => $hash, ':id' => $record_id]);

    echo json_encode([
        'success'   => true,
        'hash'      => $hash,
        'message'   => 'Hash computed and stored successfully.',
        'record_id' => $record_id,
        'cert_type' => $cert_type,
    ]);

} catch (PDOException $e) {
    error_log('pdf_hash_backfill error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
