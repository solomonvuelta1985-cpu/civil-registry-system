<?php
/** Persist a staff decision that two birth-record suggestions are different people. */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    json_response(false, 'Unauthorized', null, 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(false, 'Method not allowed', null, 405);
}

$input = json_decode(requestBody(), true);
if (!is_array($input)) {
    json_response(false, 'Invalid JSON input', null, 400);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
if (!verifyCSRFToken($csrf)) {
    json_response(false, 'CSRF token validation failed. Please refresh the page and try again.', null, 403);
}
if (!hasAnyPermission(['birth_link', 'birth_create', 'birth_edit'])) {
    json_response(false, 'You do not have permission to dismiss duplicate suggestions.', null, 403);
}

$recordAId = filter_var($input['record_a_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$recordBId = filter_var($input['record_b_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reason = trim((string)($input['reason'] ?? 'Marked not a match by staff'));
if ($recordAId === false || $recordBId === false || $recordAId === $recordBId) {
    json_response(false, 'Two different valid birth record IDs are required.', null, 400);
}
if (mb_strlen($reason, 'UTF-8') > 255) {
    json_response(false, 'Decision note must not exceed 255 characters.', null, 422);
}

try {
    $lowId = min((int)$recordAId, (int)$recordBId);
    $highId = max((int)$recordAId, (int)$recordBId);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        json_response(false, 'Session expired. Please sign in again.', null, 401);
    }

    $pdo->beginTransaction();
    $lockStmt = $pdo->prepare(
        "SELECT id FROM certificate_of_live_birth WHERE id IN (?, ?) ORDER BY id FOR UPDATE"
    );
    $lockStmt->execute([$lowId, $highId]);
    if (count($lockStmt->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
        $pdo->rollBack();
        json_response(false, 'Both records must exist and be active to dismiss this suggestion.', null, 404);
    }

    $recordStmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = :id AND status = 'Active' LIMIT 1");
    $recordStmt->execute([':id' => (int)$recordAId]);
    $recordA = $recordStmt->fetch(PDO::FETCH_ASSOC);
    $recordStmt->execute([':id' => (int)$recordBId]);
    $recordB = $recordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$recordA || !$recordB) {
        $pdo->rollBack();
        json_response(false, 'Both records must exist and be active to dismiss this suggestion.', null, 404);
    }

    if (is_record_linked($pdo, (int)$recordAId, 'birth') || is_record_linked($pdo, (int)$recordBId, 'birth')) {
        $pdo->rollBack();
        json_response(false, 'One or both records are already linked. Review or unlink the existing relationship first.', null, 409);
    }

    $lowRecord = $lowId === (int)$recordAId ? $recordA : $recordB;
    $highRecord = $lowId === (int)$recordAId ? $recordB : $recordA;
    $dismissStmt = $pdo->prepare(
        "INSERT INTO duplicate_match_dismissals
            (certificate_type, record_id_low, record_id_high, fingerprint_low, fingerprint_high, dismissed_by, reason)
         VALUES ('birth', :low_id, :high_id, :low_fingerprint, :high_fingerprint, :user_id, :reason)
         ON DUPLICATE KEY UPDATE
            fingerprint_low = VALUES(fingerprint_low),
            fingerprint_high = VALUES(fingerprint_high),
            dismissed_by = VALUES(dismissed_by),
            reason = VALUES(reason),
            dismissed_at = CURRENT_TIMESTAMP"
    );
    $dismissStmt->execute([
        ':low_id' => $lowId,
        ':high_id' => $highId,
        ':low_fingerprint' => duplicate_record_fingerprint($lowRecord),
        ':high_fingerprint' => duplicate_record_fingerprint($highRecord),
        ':user_id' => $userId,
        ':reason' => $reason !== '' ? $reason : null,
    ]);
    log_activity(
        $pdo,
        'DISMISS_DUPLICATE_SUGGESTION',
        "Marked birth records #{$lowId} and #{$highId} as different people.",
        $userId
    );
    $pdo->commit();

    json_response(true, 'Suggestion dismissed. It will be reconsidered if identifying details change.', [
        'record_a_id' => (int)$recordAId,
        'record_b_id' => (int)$recordBId,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Duplicate dismissal error: ' . $e->getMessage());
    $errorInfo = $e->errorInfo ?? [];
    if ((string)$e->getCode() === '42S02' || (int)($errorInfo[1] ?? 0) === 1146) {
        json_response(false, 'Database migration 052 is required before dismissal decisions can be saved.', null, 503);
    }
    json_response(false, 'Database error while saving the dismissal decision.', null, 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Duplicate dismissal error: ' . $e->getMessage());
    json_response(false, 'Unable to save the dismissal decision.', null, 500);
}
