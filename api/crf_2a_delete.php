<?php
/** Move CRF No. 2A issuances to Trash or permanently delete from Trash. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_2a.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(false, 'Invalid request method.', null, 405);
requireAdminApi('Only administrators can delete CRF No. 2A issuances.'); requireCSRFToken();
$id = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); $deleteType = sanitize_input($_POST['delete_type'] ?? 'soft');
if (!$id) json_response(false, 'A valid issuance id is required.', null, 422);
try {
    $stmt = $pdo->prepare('SELECT id, crf_number, status, pdf_filepath FROM crf_2a_issuances WHERE id = :id LIMIT 1'); $stmt->execute([':id' => (int)$id]); $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) json_response(false, 'CRF No. 2A issuance not found.', null, 404);
    if ($deleteType === 'hard' && $record['status'] !== 'Deleted') json_response(false, 'Only issuances already in Trash can be permanently deleted.', null, 400);
    if ($deleteType !== 'hard' && !in_array($record['status'], ['Active', 'Archived'], true)) json_response(false, 'This issuance cannot be moved to Trash.', null, 400);
    $pdo->beginTransaction();
    if ($deleteType === 'hard') {
        crf_2a_record_history($pdo, (int)$id, (string)$record['crf_number'], 'hard_deleted', 'Issuance permanently deleted from Trash');
        $pdo->prepare('DELETE FROM crf_2a_issuances WHERE id = :id')->execute([':id' => (int)$id]);
        $path = str_replace('\\', '/', trim((string)$record['pdf_filepath'])); if (preg_match('~^crf_2a/\d{4}/[A-Z0-9_]+/CRF2A-\d{4}-\d{6}\.pdf$~', $path)) delete_file($path);
        log_activity($pdo, 'HARD_DELETE_CRF_2A', "Permanently deleted {$record['crf_number']} (ID: {$id})", getUserId()); $pdo->commit(); json_response(true, 'CRF No. 2A issuance permanently deleted.', ['id' => (int)$id]);
    }
    $pdo->prepare("UPDATE crf_2a_issuances SET status = 'Deleted', deleted_at = NOW() WHERE id = :id")->execute([':id' => (int)$id]);
    crf_2a_record_history($pdo, (int)$id, (string)$record['crf_number'], 'deleted', 'Issuance moved to Trash');
    log_activity($pdo, 'SOFT_DELETE_CRF_2A', "Moved {$record['crf_number']} to Trash (ID: {$id})", getUserId()); $pdo->commit(); json_response(true, 'CRF No. 2A issuance moved to Trash.', ['id' => (int)$id]);
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('CRF 2A delete error: ' . $e->getMessage()); json_response(false, 'Unable to delete the CRF No. 2A issuance.', null, 500); }
