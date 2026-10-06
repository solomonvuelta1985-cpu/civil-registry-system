<?php
/**
 * Generate and record an immutable Civil Registry Form No. 1A issuance.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_1a.php';

requireAuth();
if (!hasPermission(crf_1a_generate_permission())) {
    json_response(false, 'You do not have permission to generate CRF No. 1A.', null, 403);
}
requireCSRFToken();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(false, 'Invalid request method.', null, 405);
}

function crf_1a_post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        $label = ucwords(str_replace('_', ' ', $key));
        json_response(false, $label . ' must not exceed ' . $maxLength . ' characters.', null, 422);
    }
    return $value;
}

function crf_1a_required_string(string $key, string $label, int $maxLength = 255): string
{
    $value = crf_1a_post_string($key, $maxLength);
    if ($value === '') {
        json_response(false, $label . ' is required.', null, 422);
    }
    return $value;
}

function crf_1a_strict_date(string $value, string $label, bool $required = true): ?string
{
    if ($value === '') {
        if ($required) {
            json_response(false, $label . ' is required.', null, 422);
        }
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    if ($date === false || $hasErrors || $date->format('Y-m-d') !== $value) {
        json_response(false, $label . ' must be a valid date in YYYY-MM-DD format.', null, 422);
    }
    return $value;
}

$birthRecordId = filter_var($_POST['birth_record_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($birthRecordId === false || $birthRecordId === null) {
    json_response(false, 'A valid birth record is required.', null, 422);
}

$pageNumber = crf_1a_required_string('page_number', 'Page Number', 50);
$bookNumber = crf_1a_required_string('book_number', 'Book Number', 50);
$orNumber = crf_1a_required_string('or_number', 'O.R. Number', 100);
$datePaid = crf_1a_strict_date(crf_1a_post_string('date_paid', 10), 'Date Paid');
$issueDate = crf_1a_strict_date(crf_1a_post_string('issue_date', 10), 'Issue Date', false)
    ?? date('Y-m-d');

$amountPaid = crf_1a_post_string('amount_paid', 30);
if ($amountPaid === '' || !preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/', $amountPaid)) {
    json_response(false, 'Amount Paid must be a non-negative amount with up to two decimal places.', null, 422);
}
if ((float)$amountPaid > 9999999999.99) {
    json_response(false, 'Amount Paid is too large.', null, 422);
}
$amountPaid = number_format((float)$amountPaid, 2, '.', '');
$issuanceKind = crf_1a_post_string('issuance_kind', 20) ?: 'Original';
if (!in_array($issuanceKind, crf_1a_issuance_kinds(), true)) {
    json_response(false, 'Issuance Type must be Original, Corrected, or Reprint.', null, 422);
}
$replacesId = filter_var($_POST['replaces_issuance_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
if ($issuanceKind === 'Corrected' && !$replacesId) {
    json_response(false, 'A corrected issuance must identify the original CRF record it replaces.', null, 422);
}
if ($issuanceKind !== 'Corrected' && $replacesId) {
    json_response(false, 'Only a Corrected issuance can replace another CRF record.', null, 422);
}

$inputs = [
    'issue_date' => $issueDate,
    'page_number' => $pageNumber,
    'book_number' => $bookNumber,
    'population_reference_no' => crf_1a_post_string('population_reference_no', 100),
    'requester_name' => crf_1a_post_string('requester_name', 150),
    'amount_paid' => $amountPaid,
    'or_number' => $orNumber,
    'date_paid' => $datePaid,
    'mcr_full_name' => crf_1a_required_string('mcr_full_name', 'Municipal Civil Registrar Name', 150),
    'mcr_title' => crf_1a_required_string('mcr_title', 'Municipal Civil Registrar Position', 100),
    'certified_by_name' => crf_1a_required_string('certified_by_name', 'Certified By Name', 150),
    'certified_by_position' => crf_1a_required_string('certified_by_position', 'Certified By Position', 100),
    'remarks_html' => crf_1a_sanitize_remarks_html($_POST['remarks_html'] ?? ''),
];

try {
    $pdo->beginTransaction();
    $recordStmt = $pdo->prepare(
        "SELECT * FROM certificate_of_live_birth WHERE id = :id AND status = 'Active' LIMIT 1 FOR UPDATE"
    );
    $recordStmt->execute([':id' => (int)$birthRecordId]);
    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        json_response(false, 'Birth record not found or is no longer active.', null, 404);
    }
    reject_active_duplicate_registration_issuance($pdo, 'birth', (int)$birthRecordId, 'CRF No. 1A');

    $values = crf_1a_record_values($record);
    if ($replacesId) {
        $replaceStmt = $pdo->prepare("SELECT id, crf_number, birth_record_id, status FROM crf_1a_issuances WHERE id = :id LIMIT 1");
        $replaceStmt->execute([':id' => $replacesId]);
        $replacement = $replaceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$replacement || (int)$replacement['birth_record_id'] !== (int)$birthRecordId || !in_array($replacement['status'], ['Active', 'Archived'], true)) {
            json_response(false, 'The original CRF record selected for correction is invalid or no longer available.', null, 422);
        }
    }
    $duplicateKey = crf_1a_duplicate_key((int)$birthRecordId, $amountPaid, $orNumber, $datePaid, $issueDate);
    $duplicateStmt = $pdo->prepare("SELECT id, crf_number, issuance_kind FROM crf_1a_issuances
        WHERE birth_record_id = :birth_id
          AND status IN ('Active', 'Archived')
          AND (
              duplicate_key = :duplicate_key
              OR (duplicate_key IS NULL AND amount_paid = :amount_paid AND or_number = :or_number AND date_paid = :date_paid AND issue_date = :issue_date)
          )
        ORDER BY id DESC LIMIT 1");
    $duplicateStmt->execute([
        ':birth_id' => (int)$birthRecordId,
        ':duplicate_key' => $duplicateKey,
        ':amount_paid' => $amountPaid,
        ':or_number' => $orNumber,
        ':date_paid' => $datePaid,
        ':issue_date' => $issueDate,
    ]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate && $issuanceKind === 'Original') {
        json_response(false, 'A matching CRF No. 1A already exists for this birth record, payment, and issue date. Choose Corrected or Reprint instead.', [
            'duplicate' => true,
            'existing_issuance_id' => (int)$duplicate['id'],
            'existing_crf_number' => $duplicate['crf_number'],
            'existing_issuance_kind' => $duplicate['issuance_kind'],
        ], 409);
    }
    if (!$duplicate && $issuanceKind === 'Original') {
        $existingStmt = $pdo->prepare("SELECT id, crf_number, issuance_kind FROM crf_1a_issuances WHERE birth_record_id = :birth_id AND status IN ('Active', 'Archived') ORDER BY id DESC LIMIT 1");
        $existingStmt->execute([':birth_id' => (int)$birthRecordId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            json_response(false, 'An Original CRF No. 1A already exists for this birth record. Choose Corrected or Reprint instead.', [
                'duplicate' => true,
                'existing_issuance_id' => (int)$existing['id'],
                'existing_crf_number' => $existing['crf_number'],
                'existing_issuance_kind' => $existing['issuance_kind'],
            ], 409);
        }
    }
    $lastName = trim((string)($record['child_last_name'] ?? '')) ?: 'UNKNOWN';
    $year = (int)substr($issueDate, 0, 4);
    $pdfRelativePath = null;
    $pdfAbsolutePath = null;
    // The row lock serializes sequence allocation for the same issue year.
    $sequenceUpsert = $pdo->prepare(
        'INSERT INTO crf_1a_sequences (issue_year, last_sequence) VALUES (:year, 0)
         ON DUPLICATE KEY UPDATE issue_year = VALUES(issue_year)'
    );
    $sequenceUpsert->execute([':year' => $year]);

    $sequenceStmt = $pdo->prepare(
        'SELECT last_sequence FROM crf_1a_sequences WHERE issue_year = :year FOR UPDATE'
    );
    $sequenceStmt->execute([':year' => $year]);
    $sequence = (int)$sequenceStmt->fetchColumn() + 1;
    if ($sequence > 999999) {
        throw new RuntimeException('The CRF No. 1A sequence has reached its six-digit limit for this year.');
    }

    $sequenceUpdate = $pdo->prepare(
        'UPDATE crf_1a_sequences SET last_sequence = :sequence WHERE issue_year = :year'
    );
    $sequenceUpdate->execute([':sequence' => $sequence, ':year' => $year]);

    $crfNumber = sprintf('CRF1A-%04d-%06d', $year, $sequence);
    $pdfRelativePath = crf_1a_output_relative_path((string)$year, $lastName, $crfNumber);
    $pdfAbsolutePath = UPLOAD_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pdfRelativePath);
    $html = crf_1a_render_document_html($record, $inputs, $crfNumber);
    $renderError = null;
    if (!crf_1a_render_pdf($html, $pdfAbsolutePath, $renderError, true)) {
        throw new RuntimeException($renderError ?: 'Unable to generate the CRF PDF.');
    }

    $snapshot = json_encode([
        'raw' => $record,
        'display' => $values,
        'manual_overrides' => ['remarks_html' => $inputs['remarks_html']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdfHash = hash_file('sha256', $pdfAbsolutePath);
    if ($pdfHash === false) {
        throw new RuntimeException('Unable to checksum the generated CRF PDF.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO crf_1a_issuances
            (crf_year, sequence_no, crf_number, birth_record_id,
             replaces_issuance_id, issuance_kind,
             registry_no_snapshot, child_name_snapshot, child_last_name_snapshot,
             record_snapshot_json, issue_date, page_number, book_number,
             population_reference_no, requester_name, amount_paid, or_number,
             date_paid, mcr_full_name, mcr_title, certified_by_name, certified_by_position,
             pdf_filename, pdf_filepath, pdf_hash, duplicate_key, created_by, status)
         VALUES
            (:crf_year, :sequence_no, :crf_number, :birth_record_id,
             :replaces_issuance_id, :issuance_kind,
             :registry_no_snapshot, :child_name_snapshot, :child_last_name_snapshot,
             :record_snapshot_json, :issue_date, :page_number, :book_number,
             :population_reference_no, :requester_name, :amount_paid, :or_number,
             :date_paid, :mcr_full_name, :mcr_title, :certified_by_name, :certified_by_position,
             :pdf_filename, :pdf_filepath, :pdf_hash, :duplicate_key, :created_by, 'Active')"
    );
    $insert->execute([
        ':crf_year' => $year,
        ':sequence_no' => $sequence,
        ':crf_number' => $crfNumber,
        ':birth_record_id' => (int)$birthRecordId,
        ':replaces_issuance_id' => $replacesId,
        ':issuance_kind' => $issuanceKind,
        ':registry_no_snapshot' => $values['registry_no'],
        ':child_name_snapshot' => $values['name_of_child'],
        ':child_last_name_snapshot' => $lastName,
        ':record_snapshot_json' => $snapshot,
        ':issue_date' => $issueDate,
        ':page_number' => $pageNumber,
        ':book_number' => $bookNumber,
        ':population_reference_no' => $inputs['population_reference_no'] ?: null,
        ':requester_name' => $inputs['requester_name'] ?: null,
        ':amount_paid' => $amountPaid,
        ':or_number' => $orNumber,
        ':date_paid' => $datePaid,
        ':mcr_full_name' => $inputs['mcr_full_name'],
        ':mcr_title' => $inputs['mcr_title'],
        ':certified_by_name' => $inputs['certified_by_name'] ?: null,
        ':certified_by_position' => $inputs['certified_by_position'] ?: null,
        ':pdf_filename' => basename($pdfAbsolutePath),
        ':pdf_filepath' => $pdfRelativePath,
        ':pdf_hash' => $pdfHash,
        ':duplicate_key' => $duplicateKey,
        ':created_by' => (int)getUserId(),
    ]);
    $issuanceId = (int)$pdo->lastInsertId();
    crf_1a_record_history($pdo, $issuanceId, $crfNumber, 'generated', ucfirst($issuanceKind) . ' issuance generated from birth record #' . (int)$birthRecordId);
    $pdo->commit();

    log_activity($pdo, 'Generate CRF No. 1A', "Generated {$crfNumber} for birth record #{$birthRecordId}", getUserId());

    $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
    json_response(true, 'CRF No. 1A generated successfully.', [
        'issuance_id' => $issuanceId,
        'crf_number' => $crfNumber,
        'issuance_kind' => $issuanceKind,
        'replaces_issuance_id' => $replacesId,
        'issue_date' => $issueDate,
        'pdf_url' => $baseUrl . '/api/serve_crf_1a.php?id=' . $issuanceId,
        'download_url' => $baseUrl . '/api/serve_crf_1a.php?id=' . $issuanceId . '&download=1&action=download',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!empty($pdfAbsolutePath) && is_file($pdfAbsolutePath)) {
        @unlink($pdfAbsolutePath);
    }
    error_log('CRF 1A generation failed: ' . $e->getMessage());
    json_response(false, 'Unable to generate CRF No. 1A. Please try again.', null, 500);
}
