<?php
/** CRF No. 3A issuance history API. */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_3a.php';

requireAuth();
if (!hasPermission(crf_3a_view_permission())) json_response(false, 'You do not have permission to view CRF No. 3A records.', null, 403);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') json_response(false, 'Invalid request method.', null, 405);

function crf_3a_records_date(string $value, string $label): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) json_response(false, $label . ' must be a valid date.', null, 422);
    return $value;
}

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));
try {
    if ($action === 'detail') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) json_response(false, 'A valid issuance id is required.', null, 422);
        $stmt = $pdo->prepare("SELECT c.*, u.full_name AS created_by_name FROM crf_3a_issuances c LEFT JOIN users u ON u.id = c.created_by WHERE c.id = :id AND c.status = 'Active' LIMIT 1");
        $stmt->execute([':id' => (int)$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) json_response(false, 'CRF No. 3A issuance not found.', null, 404);
        $record['record_snapshot'] = json_decode((string)$record['record_snapshot_json'], true);
        unset($record['record_snapshot_json']);
        $historyStmt = $pdo->prepare("SELECT h.action, h.details, h.created_at, h.actor_id, u.full_name AS actor_name FROM crf_3a_issuance_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.issuance_id = :issuance_id ORDER BY h.created_at DESC, h.id DESC");
        $historyStmt->execute([':issuance_id' => (int)$record['id']]);
        $record['history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        $base = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
        $record['pdf_url'] = $base . '/api/serve_crf_3a.php?id=' . (int)$record['id'];
        $record['download_url'] = $record['pdf_url'] . '&download=1&action=download';
        json_response(true, 'CRF No. 3A detail loaded.', $record);
    }
    if ($action !== 'list') json_response(false, 'Invalid action.', null, 400);

    $search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 100);
    $issueYear = trim((string)($_GET['issue_year'] ?? ''));
    $dateFrom = trim((string)($_GET['date_paid_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_paid_to'] ?? ''));
    $marriageRecordId = filter_var($_GET['marriage_record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
    $sortBy = strtolower(trim((string)($_GET['sort_by'] ?? 'crf_id')));
    $sortDir = strtolower(trim((string)($_GET['sort_dir'] ?? 'desc')));
    $sortColumns = [
        'crf_id' => 'c.id', 'registry' => 'c.registry_no_snapshot', 'husband' => 'c.husband_name_snapshot',
        'wife' => 'c.wife_name_snapshot', 'page_book' => 'c.page_number', 'amount' => 'c.amount_paid',
        'date_paid' => 'c.date_paid', 'issue_date' => 'c.issue_date',
    ];
    if (!isset($sortColumns[$sortBy])) $sortBy = 'crf_id';
    if (!in_array($sortDir, ['asc', 'desc'], true)) $sortDir = 'desc';
    $where = ["c.status = 'Active'"];
    $params = [];
    if ($issueYear !== '') { if (!preg_match('/^\d{4}$/', $issueYear)) json_response(false, 'Issue year must be a four-digit year.', null, 422); $where[] = 'c.crf_year = :year'; $params[':year'] = (int)$issueYear; }
    if ($dateFrom !== '') { $where[] = 'c.date_paid >= :from_date'; $params[':from_date'] = crf_3a_records_date($dateFrom, 'Date Paid From'); }
    if ($dateTo !== '') { $where[] = 'c.date_paid <= :to_date'; $params[':to_date'] = crf_3a_records_date($dateTo, 'Date Paid To'); }
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) json_response(false, 'Date Paid From cannot be later than Date Paid To.', null, 422);
    if ($marriageRecordId) { $where[] = 'c.marriage_record_id = :marriage_id'; $params[':marriage_id'] = (int)$marriageRecordId; }
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(c.crf_number LIKE :s_crf OR c.registry_no_snapshot LIKE :s_registry OR c.husband_name_snapshot LIKE :s_husband OR c.wife_name_snapshot LIKE :s_wife OR c.page_number LIKE :s_page OR c.book_number LIKE :s_book OR c.or_number LIKE :s_or OR c.requester_name LIKE :s_requester)';
        foreach (['crf', 'registry', 'husband', 'wife', 'page', 'book', 'or', 'requester'] as $key) $params[':s_' . $key] = $like;
    }
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM crf_3a_issuances c WHERE {$whereSql}"); $count->execute($params);
    $total = (int)$count->fetchColumn(); $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT c.id, c.crf_year, c.sequence_no, c.crf_number, c.marriage_record_id, c.replaces_issuance_id, c.issuance_kind, c.registry_no_snapshot, c.husband_name_snapshot, c.wife_name_snapshot, c.husband_last_name_snapshot, c.wife_last_name_snapshot, c.issue_date, c.page_number, c.book_number, c.requester_name, c.amount_paid, c.or_number, c.date_paid, c.pdf_filename, c.pdf_hash, c.created_at, c.created_by FROM crf_3a_issuances c WHERE {$whereSql} ORDER BY {$sortColumns[$sortBy]} {$sortDir}, c.id DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params); $records = $stmt->fetchAll(PDO::FETCH_ASSOC); $base = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
    foreach ($records as &$record) { $record['pdf_url'] = $base . '/api/serve_crf_3a.php?id=' . (int)$record['id']; $record['download_url'] = $record['pdf_url'] . '&download=1&action=download'; } unset($record);
    json_response(true, 'CRF No. 3A records loaded.', ['records' => $records, 'pagination' => ['current_page' => $page, 'total_pages' => $pages, 'total_records' => $total, 'per_page' => $perPage, 'from' => $total ? $offset + 1 : 0, 'to' => min($offset + $perPage, $total)]]);
} catch (Throwable $e) {
    error_log('CRF 3A records error: ' . $e->getMessage());
    json_response(false, 'Unable to load CRF No. 3A records.', null, 500);
}
