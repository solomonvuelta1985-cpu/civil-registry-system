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

$inputs = [
    'issue_date' => $issueDate,
    'page_number' => $pageNumber,
    'book_number' => $bookNumber,
    'population_reference_no' => crf_1a_post_string('population_reference_no', 100),
    'requester_name' => crf_1a_post_string('requester_name', 150),
    'amount_paid' => $amountPaid,
    'or_number' => $orNumber,
    'date_paid' => $datePaid,
    'certified_by_name' => crf_1a_post_string('certified_by_name', 150),
    'certified_by_position' => crf_1a_post_string('certified_by_position', 100),
];
$crfConfig = crf_1a_config();
$inputs['certified_by_name'] = $inputs['certified_by_name'] ?: $crfConfig['mcr_full_name'];
$inputs['certified_by_position'] = $inputs['certified_by_position'] ?: $crfConfig['mcr_title'];

try {
    $recordStmt = $pdo->prepare(
        "SELECT * FROM certificate_of_live_birth WHERE id = :id AND status = 'Active' LIMIT 1"
    );
    $recordStmt->execute([':id' => (int)$birthRecordId]);
    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        json_response(false, 'Birth record not found or is no longer active.', null, 404);
    }

    $values = crf_1a_record_values($record);
    $lastName = trim((string)($record['child_last_name'] ?? '')) ?: 'UNKNOWN';
    $year = (int)substr($issueDate, 0, 4);
    $pdfRelativePath = null;
    $pdfAbsolutePath = null;
    $pdo->beginTransaction();

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
    if (!crf_1a_render_pdf($html, $pdfAbsolutePath, $renderError)) {
        throw new RuntimeException($renderError ?: 'Unable to generate the CRF PDF.');
    }

    $snapshot = json_encode([
        'raw' => $record,
        'display' => $values,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdfHash = hash_file('sha256', $pdfAbsolutePath);
    if ($pdfHash === false) {
        throw new RuntimeException('Unable to checksum the generated CRF PDF.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO crf_1a_issuances
            (crf_year, sequence_no, crf_number, birth_record_id,
             registry_no_snapshot, child_name_snapshot, child_last_name_snapshot,
             record_snapshot_json, issue_date, page_number, book_number,
             population_reference_no, requester_name, amount_paid, or_number,
             date_paid, certified_by_name, certified_by_position,
             pdf_filename, pdf_filepath, pdf_hash, created_by, status)
         VALUES
            (:crf_year, :sequence_no, :crf_number, :birth_record_id,
             :registry_no_snapshot, :child_name_snapshot, :child_last_name_snapshot,
             :record_snapshot_json, :issue_date, :page_number, :book_number,
             :population_reference_no, :requester_name, :amount_paid, :or_number,
             :date_paid, :certified_by_name, :certified_by_position,
             :pdf_filename, :pdf_filepath, :pdf_hash, :created_by, 'Active')"
    );
    $insert->execute([
        ':crf_year' => $year,
        ':sequence_no' => $sequence,
        ':crf_number' => $crfNumber,
        ':birth_record_id' => (int)$birthRecordId,
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
        ':certified_by_name' => $inputs['certified_by_name'] ?: null,
        ':certified_by_position' => $inputs['certified_by_position'] ?: null,
        ':pdf_filename' => basename($pdfAbsolutePath),
        ':pdf_filepath' => $pdfRelativePath,
        ':pdf_hash' => $pdfHash,
        ':created_by' => (int)getUserId(),
    ]);
    $issuanceId = (int)$pdo->lastInsertId();
    $pdo->commit();

    log_activity($pdo, 'Generate CRF No. 1A', "Generated {$crfNumber} for birth record #{$birthRecordId}", getUserId());

    $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
    json_response(true, 'CRF No. 1A generated successfully.', [
        'issuance_id' => $issuanceId,
        'crf_number' => $crfNumber,
        'issue_date' => $issueDate,
        'pdf_url' => $baseUrl . '/api/serve_crf_1a.php?id=' . $issuanceId,
        'download_url' => $baseUrl . '/api/serve_crf_1a.php?id=' . $issuanceId . '&download=1',
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
