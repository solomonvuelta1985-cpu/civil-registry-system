<?php
/** PDF protection job status, filtering, sorting, pagination, and reports API. */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
if (getUserRole() !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator access required.']);
    exit;
}

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));
$jobId = max(0, (int)($_GET['job_id'] ?? 0));

function pdf_job_valid_date(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) ? $value : null;
}

try {
    if ($action === 'list') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) $perPage = 25;

        $sortMap = [
            'id' => 'j.id',
            'job_type' => 'j.job_type',
            'status' => 'j.status',
            'total_items' => 'j.total_items',
            'scanned_items' => 'j.scanned_items',
            'copied_items' => 'j.copied_items',
            'failed_items' => 'j.failed_items',
            'review_items' => 'j.review_items',
            'created_at' => 'j.created_at',
            'updated_at' => 'j.updated_at',
        ];
        $sortKey = strtolower(trim((string)($_GET['sort'] ?? 'updated_at')));
        $sortColumn = $sortMap[$sortKey] ?? $sortMap['updated_at'];
        $direction = strtolower(trim((string)($_GET['direction'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        $jobType = strtolower(trim((string)($_GET['job_type'] ?? '')));
        if (in_array($jobType, ['backup', 'recovery'], true)) {
            $where[] = 'j.job_type = :job_type';
            $params[':job_type'] = $jobType;
        }

        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $allowedStatuses = ['queued','scanning','preview_ready','approved','running','paused','completed','completed_with_errors','failed','cancelled'];
        if (in_array($status, $allowedStatuses, true)) {
            $where[] = 'j.status = :status';
            $params[':status'] = $status;
        }

        $outcome = strtolower(trim((string)($_GET['outcome'] ?? '')));
        if ($outcome === 'active') $where[] = "j.status IN ('queued','scanning','approved','running','paused')";
        if ($outcome === 'attention') $where[] = "(j.failed_items > 0 OR j.review_items > 0 OR j.status = 'completed_with_errors')";
        if ($outcome === 'success') $where[] = "j.status = 'completed' AND j.failed_items = 0 AND j.review_items = 0";

        $hasErrors = strtolower(trim((string)($_GET['has_errors'] ?? '')));
        if ($hasErrors === 'yes') $where[] = 'j.failed_items > 0';
        if ($hasErrors === 'no') $where[] = 'j.failed_items = 0';

        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(CAST(j.id AS CHAR) LIKE :search OR j.source_root LIKE :search OR j.destination_root LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $dateFrom = pdf_job_valid_date($_GET['date_from'] ?? null);
        if ($dateFrom !== null) {
            $where[] = 'j.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        $dateTo = pdf_job_valid_date($_GET['date_to'] ?? null);
        if ($dateTo !== null) {
            $where[] = 'j.created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_protection_jobs j{$whereSql}");
        foreach ($params as $key => $value) $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT j.id, j.job_type, j.status, j.source_root, j.destination_root, j.created_by,
                    j.approved_by, j.total_items, j.scanned_items, j.copied_items,
                    j.skipped_items, j.failed_items, j.review_items, j.bytes_scanned,
                    j.bytes_copied, j.last_error, j.started_at, j.last_activity_at,
                    j.completed_at, j.created_at, j.updated_at
               FROM pdf_protection_jobs j{$whereSql}
              ORDER BY {$sortColumn} {$direction}, j.id DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'jobs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
            'query' => [
                'sort' => $sortKey,
                'direction' => strtolower($direction),
                'job_type' => $jobType,
                'status' => $status,
                'outcome' => $outcome,
            ],
        ]);
        exit;
    }

    if ($jobId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid job_id is required.']);
        exit;
    }

    $jobStmt = $pdo->prepare('SELECT * FROM pdf_protection_jobs WHERE id = :id LIMIT 1');
    $jobStmt->execute([':id' => $jobId]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job not found.']);
        exit;
    }

    if ($action === 'job') {
        $countsStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS count
               FROM pdf_protection_job_items
              WHERE job_id = :job_id
              GROUP BY status
              ORDER BY status'
        );
        $countsStmt->execute([':job_id' => $jobId]);
        echo json_encode(['success' => true, 'job' => $job, 'item_counts' => $countsStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'items' || $action === 'report') {
        $itemStatus = trim((string)($_GET['item_status'] ?? $_GET['status'] ?? ''));
        $allowedItemStatuses = ['queued','scanned','already_present','matched','imported','skipped','needs_review','unmatched','invalid_pdf','corrupt_source','duplicate_source','failed'];
        $where = 'job_id = :job_id';
        $params = [':job_id' => $jobId];
        if ($itemStatus !== '' && in_array($itemStatus, $allowedItemStatuses, true)) {
            $where .= ' AND status = :item_status';
            $params[':item_status'] = $itemStatus;
        }

        $countsStmt = $pdo->prepare("SELECT status, COUNT(*) AS count FROM pdf_protection_job_items WHERE {$where} GROUP BY status ORDER BY status");
        $countsStmt->execute($params);
        $counts = $countsStmt->fetchAll(PDO::FETCH_ASSOC);

        $limit = min(500, max(1, (int)($_GET['limit'] ?? 100)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $itemsStmt = $pdo->prepare(
            "SELECT id, source_path, destination_path, source_filename, source_size,
                    source_mtime, source_hash, destination_hash, expected_hash,
                    cert_type, record_id, registry_no, match_method, status,
                    attempts, last_error, reviewed_by, reviewed_at, imported_at,
                    created_at, updated_at
               FROM pdf_protection_job_items
              WHERE {$where}
              ORDER BY id ASC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) $itemsStmt->bindValue($key, $value, PDO::PARAM_STR);
        $itemsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $itemsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $itemsStmt->execute();

        echo json_encode([
            'success' => true,
            'job' => $job,
            'counts' => $counts,
            'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
            'offset' => $offset,
            'limit' => $limit,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
} catch (Throwable $e) {
    error_log('pdf_protection_job_status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load PDF protection job status.']);
}
