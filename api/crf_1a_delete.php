<?php
/**
 * CRF No. 1A delete API.
 * Uses the same soft-delete/Trash behavior as the other record modules.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
    exit;
}

requireAdminApi('Only administrators can delete CRF No. 1A issuances.');
requireCSRFToken();

try {
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $deleteType = sanitize_input($_POST['delete_type'] ?? 'soft');
    if ($recordId === false || $recordId === null) {
        json_response(false, 'A valid issuance id is required.', null, 422);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, crf_number, status, pdf_filepath FROM crf_1a_issuances WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        json_response(false, 'CRF No. 1A issuance not found.', null, 404);
        exit;
    }
    if ($deleteType === 'hard') {
        if ($record['status'] !== 'Deleted') {
            json_response(false, 'Only issuances already in the Trash can be permanently deleted.', null, 400);
            exit;
        }
    } elseif (!in_array($record['status'], ['Active', 'Archived'], true)) {
        json_response(false, 'This issuance cannot be moved to the Trash in its current status.', null, 400);
        exit;
    }

    $pdo->beginTransaction();
    try {
        if ($deleteType === 'hard') {
            $delete = $pdo->prepare('DELETE FROM crf_1a_issuances WHERE id = :id');
            $delete->execute([':id' => (int)$recordId]);

            $relativePath = str_replace('\\', '/', trim((string)($record['pdf_filepath'] ?? '')));
            if (preg_match('~^crf_1a/\\d{4}/[A-Z0-9_]+/CRF1A-\\d{4}-\\d{6}\\.pdf$~', $relativePath)) {
                delete_file($relativePath);
            }

            log_activity(
                $pdo,
                'HARD_DELETE_CRF_1A',
                "Permanently deleted CRF No. 1A issuance: {$record['crf_number']} (ID: {$recordId})",
                $_SESSION['user_id'] ?? null
            );

            $pdo->commit();
            json_response(true, 'CRF No. 1A issuance permanently deleted.', ['id' => (int)$recordId], 200);
            exit;
        }

        $update = $pdo->prepare("UPDATE crf_1a_issuances SET status = 'Deleted', deleted_at = NOW() WHERE id = :id");
        $update->execute([':id' => (int)$recordId]);

        log_activity(
            $pdo,
            'SOFT_DELETE_CRF_1A',
            "Moved CRF No. 1A issuance to Trash: {$record['crf_number']} (ID: {$recordId})",
            $_SESSION['user_id'] ?? null
        );

        $pdo->commit();
        json_response(true, 'CRF No. 1A issuance moved to Trash.', ['id' => (int)$recordId], 200);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('CRF 1A delete DB error: ' . $e->getMessage());
        json_response(false, 'Database error occurred. Please try again.', null, 500);
    }
} catch (Throwable $e) {
    error_log('CRF 1A delete error: ' . $e->getMessage());
    json_response(false, 'An unexpected error occurred. Please contact the administrator.', null, 500);
}
