<?php
/** Admin controls for PDF protection jobs, including safe terminal-job deletion. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/pdf_protection_jobs.php';
require_once '../includes/pdf_protection_launcher.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'POST required']); exit; }

requireCSRFToken();

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$jobId = (int)($_POST['job_id'] ?? 0);
$itemId = (int)($_POST['item_id'] ?? 0);
$allowedActions = ['approve', 'pause', 'resume', 'cancel', 'retry', 'delete'];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid job action is required']);
    exit;
}

if ($action === 'delete') {
    $rawIds = $_POST['job_ids'] ?? [];
    if (!is_array($rawIds)) $rawIds = preg_split('/[\s,]+/', (string)$rawIds, -1, PREG_SPLIT_NO_EMPTY);
    if ($jobId > 0) $rawIds[] = $jobId;
    $jobIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
    if (!$jobIds) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Select at least one job record to delete.']);
        exit;
    }
} elseif ($jobId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid job_id is required.']);
    exit;
}

try {
    if ($action === 'delete') {
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $read = $pdo->prepare("SELECT id, status FROM pdf_protection_jobs WHERE id IN ({$placeholders})");
        $read->execute($jobIds);
        $rows = $read->fetchAll(PDO::FETCH_ASSOC);
        $foundIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $missing = array_values(array_diff($jobIds, $foundIds));
        if ($missing) throw new RuntimeException('One or more selected job records no longer exist.');

        $deletable = ['preview_ready', 'completed', 'completed_with_errors', 'failed', 'cancelled'];
        $blocked = [];
        foreach ($rows as $row) if (!in_array((string)$row['status'], $deletable, true)) $blocked[] = '#' . (int)$row['id'] . ' (' . $row['status'] . ')';
        if ($blocked) throw new RuntimeException('Only inactive terminal jobs can be deleted. Active jobs: ' . implode(', ', $blocked));

        $pdo->beginTransaction();
        try {
            $manifestDelete = $pdo->prepare("DELETE FROM pdf_backup_manifest_items WHERE manifest_id IN ({$placeholders})");
            $manifestDelete->execute($jobIds);
            $itemDelete = $pdo->prepare("DELETE FROM pdf_protection_job_items WHERE job_id IN ({$placeholders})");
            $itemDelete->execute($jobIds);
            $jobDelete = $pdo->prepare("DELETE FROM pdf_protection_jobs WHERE id IN ({$placeholders})");
            $jobDelete->execute($jobIds);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        foreach ($jobIds as $deletedId) {
            if (function_exists('log_activity')) log_activity($pdo, 'PDF_PROTECTION_JOB_DELETED', sprintf('Job %d deleted by administrator', $deletedId), $_SESSION['user_id'] ?? null);
        }
        echo json_encode(['success' => true, 'deleted_job_ids' => $jobIds, 'message' => count($jobIds) . ' job record(s) deleted.']);
        exit;
    }

    $job = get_pdf_protection_job($pdo, $jobId);
    if (!$job) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Job not found']); exit; }

    $current = (string)$job['status'];
    $next = null;
    $message = '';
    $shouldLaunch = false;

    if ($action === 'approve') {
        if ($current !== 'preview_ready') throw new RuntimeException('Only preview_ready jobs can be approved.');
        $stmt = $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'approved', approved_by = :user_id, last_error = NULL, last_activity_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'preview_ready'");
        $stmt->execute([':user_id' => (int)($_SESSION['user_id'] ?? 0), ':id' => $jobId]);
        $next = 'approved'; $message = 'Job approved and worker started.'; $shouldLaunch = true;
    } elseif ($action === 'pause') {
        if (!in_array($current, ['queued', 'scanning', 'approved', 'running'], true)) throw new RuntimeException('This job cannot be paused from its current state.');
        $stmt = $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'paused', last_activity_at = NOW(), updated_at = NOW() WHERE id = :id AND status IN ('queued','scanning','approved','running')");
        $stmt->execute([':id' => $jobId]);
        $next = 'paused'; $message = 'Pause requested. The worker will stop after the current file.';
    } elseif ($action === 'resume') {
        if ($current !== 'paused') throw new RuntimeException('Only paused jobs can be resumed.');
        $stmt = $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'queued', completed_at = NULL, last_error = NULL, last_activity_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'paused'");
        $stmt->execute([':id' => $jobId]);
        $next = 'queued'; $message = 'Job resumed and worker started.'; $shouldLaunch = true;
    } elseif ($action === 'cancel') {
        if (in_array($current, ['completed', 'completed_with_errors', 'failed', 'cancelled'], true)) throw new RuntimeException('This job is already terminal.');
        $stmt = $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'cancelled', last_activity_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $jobId]);
        $next = 'cancelled'; $message = 'Cancellation requested. The worker will stop at the next file boundary.';
    } else {
        $params = [':job_id' => $jobId];
        $where = "job_id = :job_id AND status IN ('failed','invalid_pdf','corrupt_source')";
        if ($itemId > 0) { $where .= ' AND id = :item_id'; $params[':item_id'] = $itemId; }
        $stmt = $pdo->prepare("UPDATE pdf_protection_job_items SET status = 'queued', last_error = NULL, imported_at = NULL, reviewed_by = NULL, reviewed_at = NULL, updated_at = NOW() WHERE {$where}");
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) throw new RuntimeException('No retryable failed item was found.');
        $jobStmt = $pdo->prepare("UPDATE pdf_protection_jobs SET status = 'queued', completed_at = NULL, last_error = NULL, last_activity_at = NOW(), updated_at = NOW() WHERE id = :id");
        $jobStmt->execute([':id' => $jobId]);
        $next = 'queued'; $message = $itemId > 0 ? 'The failed item was queued and the worker started.' : 'Failed items were queued and the worker started.'; $shouldLaunch = true;
    }

    if ($shouldLaunch) {
        try {
            launch_pdf_protection_worker($jobId, (string)$job['job_type'], (string)$job['source_root'], (string)($job['destination_root'] ?? ''));
        } catch (Throwable $e) {
            update_pdf_protection_job($pdo, $jobId, 'failed', ['last_error' => $e->getMessage()]);
            throw new RuntimeException('The job state changed, but the worker could not be started: ' . $e->getMessage(), 0, $e);
        }
    }

    if (function_exists('log_activity')) log_activity($pdo, 'PDF_PROTECTION_JOB_' . strtoupper($action), sprintf('Job %d changed from %s to %s%s', $jobId, $current, $next, $itemId > 0 ? " (item {$itemId})" : ''), $_SESSION['user_id'] ?? null);
    echo json_encode(['success' => true, 'job_id' => $jobId, 'status' => $next, 'message' => $message]);
} catch (Throwable $e) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
