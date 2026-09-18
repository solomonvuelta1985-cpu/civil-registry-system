<?php
/**
 * CRF No. 1A issuance history API.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_1a.php';

requireAuth();
if (!hasPermission(crf_1a_view_permission())) {
    json_response(false, 'You do not have permission to view CRF No. 1A records.', null, 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(false, 'Invalid request method.', null, 405);
}

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));

try {
    if ($action === 'detail') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            json_response(false, 'A valid issuance id is required.', null, 422);
        }

        $stmt = $pdo->prepare(
            "SELECT c.*, u.full_name AS created_by_name
             FROM crf_1a_issuances c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = :id AND c.status = 'Active' LIMIT 1"
        );
        $stmt->execute([':id' => (int)$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            json_response(false, 'CRF No. 1A issuance not found.', null, 404);
        }

        $record['record_snapshot'] = json_decode((string)$record['record_snapshot_json'], true);
        unset($record['record_snapshot_json']);
        $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
        $record['pdf_url'] = $baseUrl . '/api/serve_crf_1a.php?id=' . (int)$record['id'];
        $record['download_url'] = $record['pdf_url'] . '&download=1';
        json_response(true, 'CRF No. 1A detail loaded.', $record);
    }

    if ($action !== 'list') {
        json_response(false, 'Invalid action.', null, 400);
    }

    $search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 100);
    $issueYear = trim((string)($_GET['issue_year'] ?? ''));
    $datePaidFrom = trim((string)($_GET['date_paid_from'] ?? ''));
    $datePaidTo = trim((string)($_GET['date_paid_to'] ?? ''));
    $birthRecordId = filter_var($_GET['birth_record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
    $sortBy = strtolower(trim((string)($_GET['sort_by'] ?? 'crf_id')));
    $sortDir = strtolower(trim((string)($_GET['sort_dir'] ?? 'desc')));

    $sortColumns = [
        'crf_id' => 'c.id',
        'registry' => 'c.registry_no_snapshot',
        'child' => 'c.child_name_snapshot',
        'page_book' => 'c.page_number',
        'amount' => 'c.amount_paid',
        'date_paid' => 'c.date_paid',
        'issue_date' => 'c.issue_date',
    ];
    if (!isset($sortColumns[$sortBy])) {
        $sortBy = 'crf_id';
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = 'desc';
    }

    $where = ["c.status = 'Active'"];
    $params = [];
    if ($issueYear !== '') {
        if (!preg_match('/^\d{4}$/', $issueYear)) {
            json_response(false, 'Issue year must be a four-digit year.', null, 422);
        }
        $where[] = 'c.crf_year = :issue_year';
        $params[':issue_year'] = (int)$issueYear;
    }
    if ($datePaidFrom !== '') {
        $datePaidFrom = crf_1a_records_date($datePaidFrom, 'Date paid from');
        $where[] = 'c.date_paid >= :date_paid_from';
        $params[':date_paid_from'] = $datePaidFrom;
    }
    if ($datePaidTo !== '') {
        $datePaidTo = crf_1a_records_date($datePaidTo, 'Date paid to');
        $where[] = 'c.date_paid <= :date_paid_to';
        $params[':date_paid_to'] = $datePaidTo;
    }
    if ($datePaidFrom !== '' && $datePaidTo !== '' && $datePaidFrom > $datePaidTo) {
        json_response(false, 'Date Paid From cannot be later than Date Paid To.', null, 422);
    }
    if ($birthRecordId !== false && $birthRecordId !== null) {
        $where[] = 'c.birth_record_id = :birth_record_id';
        $params[':birth_record_id'] = (int)$birthRecordId;
    }
    if ($search !== '') {
        $searchLike = '%' . $search . '%';
        $where[] = '(c.crf_number LIKE :search_crf
            OR c.registry_no_snapshot LIKE :search_registry
            OR c.child_name_snapshot LIKE :search_child
            OR c.page_number LIKE :search_page
            OR c.book_number LIKE :search_book
            OR c.or_number LIKE :search_or
            OR c.requester_name LIKE :search_requester)';
        foreach (['crf', 'registry', 'child', 'page', 'book', 'or', 'requester'] as $key) {
            $params[':search_' . $key] = $searchLike;
        }
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM crf_1a_issuances c WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $dataStmt = $pdo->prepare(
        "SELECT c.id, c.crf_year, c.sequence_no, c.crf_number, c.birth_record_id,
                c.registry_no_snapshot, c.child_name_snapshot, c.child_last_name_snapshot,
                c.issue_date, c.page_number, c.book_number, c.population_reference_no,
                c.requester_name, c.amount_paid, c.or_number, c.date_paid,
                c.certified_by_name, c.certified_by_position, c.pdf_filename,
                c.pdf_hash, c.created_at, c.created_by
         FROM crf_1a_issuances c
         WHERE {$whereSql}
         ORDER BY {$sortColumns[$sortBy]} {$sortDir}, c.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $dataStmt->execute($params);
    $records = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
    foreach ($records as &$record) {
        $record['pdf_url'] = $baseUrl . '/api/serve_crf_1a.php?id=' . (int)$record['id'];
        $record['download_url'] = $record['pdf_url'] . '&download=1';
    }
    unset($record);

    json_response(true, 'CRF No. 1A records loaded.', [
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $perPage,
            'from' => $totalRecords > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $totalRecords),
        ],
    ]);
} catch (Throwable $e) {
    error_log('CRF 1A records error: ' . $e->getMessage());
    json_response(false, 'Unable to load CRF No. 1A records.', null, 500);
}

function crf_1a_records_date(string $value, string $label): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    if ($date === false || $hasErrors || $date->format('Y-m-d') !== $value) {
        json_response(false, $label . ' must be a valid date in YYYY-MM-DD format.', null, 422);
    }
    return $value;
}
