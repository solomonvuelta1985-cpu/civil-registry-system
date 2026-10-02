<?php
/** Generate and record an immutable Civil Registry Form No. 2A issuance. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/crf_2a.php';

requireAuth();
if (!hasPermission(crf_2a_generate_permission())) json_response(false, 'You do not have permission to generate CRF No. 2A.', null, 403);
requireCSRFToken();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(false, 'Invalid request method.', null, 405);

function crf_2a_post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if (mb_strlen($value, 'UTF-8') > $maxLength) json_response(false, ucwords(str_replace('_', ' ', $key)) . ' must not exceed ' . $maxLength . ' characters.', null, 422);
    return $value;
}
function crf_2a_required(string $key, string $label, int $maxLength = 255): string
{
    $value = crf_2a_post_string($key, $maxLength);
    if ($value === '') json_response(false, $label . ' is required.', null, 422);
    return $value;
}
function crf_2a_date_input(string $key, string $label, bool $required = true): ?string
{
    $value = crf_2a_post_string($key, 10);
    if ($value === '') { if ($required) json_response(false, $label . ' is required.', null, 422); return null; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $bad = $date === false || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value;
    if ($bad) json_response(false, $label . ' must be a valid date in YYYY-MM-DD format.', null, 422);
    return $value;
}

$deathRecordId = filter_var($_POST['death_record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($deathRecordId === false || $deathRecordId === null) json_response(false, 'A valid death record is required.', null, 422);

$inputs = [
    'page_number' => crf_2a_required('page_number', 'Page Number', 50),
    'book_number' => crf_2a_required('book_number', 'Book Number', 50),
    'requester_name' => crf_2a_post_string('requester_name', 150),
    'amount_paid' => crf_2a_post_string('amount_paid', 30),
    'or_number' => crf_2a_required('or_number', 'O.R. Number', 100),
    'date_paid' => crf_2a_date_input('date_paid', 'Date Paid'),
    'issue_date' => crf_2a_date_input('issue_date', 'Issue Date', false) ?? date('Y-m-d'),
    'mcr_full_name' => crf_2a_required('mcr_full_name', 'Municipal Civil Registrar Name', 150),
    'mcr_title' => crf_2a_required('mcr_title', 'Municipal Civil Registrar Position', 100),
    'certified_by_name' => crf_2a_required('certified_by_name', 'Certified By Name', 150),
    'certified_by_position' => crf_2a_required('certified_by_position', 'Certified By Position', 100),
];
if ($inputs['amount_paid'] === '' || !preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/', $inputs['amount_paid'])) json_response(false, 'Amount Paid must be a non-negative amount with up to two decimal places.', null, 422);
$inputs['amount_paid'] = number_format((float)$inputs['amount_paid'], 2, '.', '');
$issuanceKind = crf_2a_post_string('issuance_kind', 20) ?: 'Original';
if (!in_array($issuanceKind, crf_2a_issuance_kinds(), true)) json_response(false, 'Issuance Type must be Original, Corrected, or Reprint.', null, 422);
$replacesId = filter_var($_POST['replaces_issuance_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
if ($issuanceKind === 'Corrected' && !$replacesId) json_response(false, 'A corrected issuance must identify the original CRF record it replaces.', null, 422);
if ($issuanceKind !== 'Corrected' && $replacesId) json_response(false, 'Only a Corrected issuance can replace another CRF record.', null, 422);

$pdfAbsolutePath = null;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_death WHERE id = :id AND status = 'Active' LIMIT 1 FOR UPDATE");
    $stmt->execute([':id' => (int)$deathRecordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) json_response(false, 'Death record not found or is no longer active.', null, 404);
    reject_active_duplicate_registration_issuance($pdo, 'death', (int)$deathRecordId, 'CRF No. 2A');

    $recordForIssuance = $record;
    foreach (['civil_status' => 50, 'citizenship' => 100, 'cause_of_death' => 500] as $key => $maxLength) {
        $posted = crf_2a_post_string($key, $maxLength);
        if ($posted !== '') $recordForIssuance[$key] = $posted;
    }
    $values = crf_2a_record_values($recordForIssuance);
    foreach (['civil_status' => 'Civil Status', 'citizenship' => 'Citizenship', 'cause_of_death' => 'Cause of Death'] as $key => $label) {
        if (trim((string)($values[$key] ?? '')) === '') json_response(false, $label . ' is required on the death record before generating CRF No. 2A.', null, 422);
    }

    if ($replacesId) {
        $replaceStmt = $pdo->prepare("SELECT id, crf_number, death_record_id, status FROM crf_2a_issuances WHERE id = :id LIMIT 1");
        $replaceStmt->execute([':id' => $replacesId]);
        $replacement = $replaceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$replacement || (int)$replacement['death_record_id'] !== (int)$deathRecordId || !in_array($replacement['status'], ['Active', 'Archived'], true)) {
            json_response(false, 'The original CRF record selected for correction is invalid or no longer available.', null, 422);
        }
    }

    $duplicateKey = crf_2a_duplicate_key((int)$deathRecordId, $inputs['amount_paid'], $inputs['or_number'], $inputs['date_paid'], $inputs['issue_date']);
    $duplicateStmt = $pdo->prepare("SELECT id, crf_number, issuance_kind FROM crf_2a_issuances
        WHERE death_record_id = :death_id
          AND status IN ('Active', 'Archived')
          AND (
              duplicate_key = :duplicate_key
              OR (duplicate_key IS NULL AND amount_paid = :amount_paid AND or_number = :or_number AND date_paid = :date_paid AND issue_date = :issue_date)
          )
        ORDER BY id DESC LIMIT 1");
    $duplicateStmt->execute([
        ':death_id' => (int)$deathRecordId,
        ':duplicate_key' => $duplicateKey,
        ':amount_paid' => $inputs['amount_paid'],
        ':or_number' => $inputs['or_number'],
        ':date_paid' => $inputs['date_paid'],
        ':issue_date' => $inputs['issue_date'],
    ]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate && $issuanceKind === 'Original') {
        json_response(false, 'A matching CRF No. 2A already exists for this death record, payment, and issue date. Choose Corrected or Reprint instead.', [
            'duplicate' => true,
            'existing_issuance_id' => (int)$duplicate['id'],
            'existing_crf_number' => $duplicate['crf_number'],
            'existing_issuance_kind' => $duplicate['issuance_kind'],
        ], 409);
    }
    if (!$duplicate && $issuanceKind === 'Original') {
        $existingStmt = $pdo->prepare("SELECT id, crf_number, issuance_kind FROM crf_2a_issuances WHERE death_record_id = :death_id AND status IN ('Active', 'Archived') ORDER BY id DESC LIMIT 1");
        $existingStmt->execute([':death_id' => (int)$deathRecordId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            json_response(false, 'An Original CRF No. 2A already exists for this death record. Choose Corrected or Reprint instead.', [
                'duplicate' => true,
                'existing_issuance_id' => (int)$existing['id'],
                'existing_crf_number' => $existing['crf_number'],
                'existing_issuance_kind' => $existing['issuance_kind'],
            ], 409);
        }
    }

    $lastName = trim((string)($record['deceased_last_name'] ?? '')) ?: 'UNKNOWN';
    $year = (int)substr($inputs['issue_date'], 0, 4);
    $upsert = $pdo->prepare('INSERT INTO crf_2a_sequences (issue_year, last_sequence) VALUES (:year, 0) ON DUPLICATE KEY UPDATE issue_year = VALUES(issue_year)');
    $upsert->execute([':year' => $year]);
    $seqStmt = $pdo->prepare('SELECT last_sequence FROM crf_2a_sequences WHERE issue_year = :year FOR UPDATE');
    $seqStmt->execute([':year' => $year]);
    $sequence = (int)$seqStmt->fetchColumn() + 1;
    if ($sequence > 999999) throw new RuntimeException('The CRF No. 2A sequence has reached its six-digit limit for this year.');
    $pdo->prepare('UPDATE crf_2a_sequences SET last_sequence = :sequence WHERE issue_year = :year')->execute([':sequence' => $sequence, ':year' => $year]);

    $crfNumber = sprintf('CRF2A-%04d-%06d', $year, $sequence);
    $relativePath = crf_2a_output_relative_path((string)$year, $lastName, $crfNumber);
    $pdfAbsolutePath = UPLOAD_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    $html = crf_2a_render_document_html($recordForIssuance, $inputs, $crfNumber);
    $renderError = null;
    if (!crf_2a_render_pdf($html, $pdfAbsolutePath, $renderError)) throw new RuntimeException($renderError ?: 'Unable to generate the CRF PDF.');

    $snapshot = json_encode(['raw' => $record, 'display' => $values, 'manual_overrides' => ['civil_status' => $values['civil_status'], 'citizenship' => $values['citizenship'], 'cause_of_death' => $values['cause_of_death']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $hash = hash_file('sha256', $pdfAbsolutePath);
    if ($hash === false) throw new RuntimeException('Unable to checksum the generated CRF PDF.');
    $insert = $pdo->prepare("INSERT INTO crf_2a_issuances
        (crf_year, sequence_no, crf_number, death_record_id, replaces_issuance_id, issuance_kind, registry_no_snapshot, deceased_name_snapshot, deceased_last_name_snapshot, record_snapshot_json, issue_date, page_number, book_number, requester_name, amount_paid, or_number, date_paid, mcr_full_name, mcr_title, certified_by_name, certified_by_position, pdf_filename, pdf_filepath, pdf_hash, duplicate_key, created_by, status)
        VALUES (:year, :seq, :number, :death_id, :replaces, :issuance_kind, :registry, :name, :last_name, :snapshot, :issue_date, :page, :book, :requester, :amount, :or_number, :date_paid, :mcr_name, :mcr_title, :cert_name, :cert_position, :filename, :filepath, :hash, :duplicate_key, :created_by, 'Active')");
    $insert->execute([
        ':year' => $year, ':seq' => $sequence, ':number' => $crfNumber, ':death_id' => (int)$deathRecordId,
        ':replaces' => $replacesId, ':issuance_kind' => $issuanceKind,
        ':registry' => $values['registry_no'], ':name' => $values['name_of_deceased'], ':last_name' => $lastName,
        ':snapshot' => $snapshot, ':issue_date' => $inputs['issue_date'], ':page' => $inputs['page_number'], ':book' => $inputs['book_number'],
        ':requester' => $inputs['requester_name'] ?: null, ':amount' => $inputs['amount_paid'], ':or_number' => $inputs['or_number'], ':date_paid' => $inputs['date_paid'],
        ':mcr_name' => $inputs['mcr_full_name'], ':mcr_title' => $inputs['mcr_title'], ':cert_name' => $inputs['certified_by_name'], ':cert_position' => $inputs['certified_by_position'],
        ':filename' => basename($pdfAbsolutePath), ':filepath' => $relativePath, ':hash' => $hash, ':duplicate_key' => $duplicateKey, ':created_by' => getUserId(),
    ]);
    $issuanceId = (int)$pdo->lastInsertId();
    crf_2a_record_history($pdo, $issuanceId, $crfNumber, 'generated', ucfirst($issuanceKind) . ' issuance generated from death record #' . (int)$deathRecordId);
    $pdo->commit();
    log_activity($pdo, 'Generate CRF No. 2A', "Generated {$crfNumber} for death record #{$deathRecordId}", getUserId());
    $baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/iscan/', '/');
    json_response(true, 'CRF No. 2A generated successfully.', ['issuance_id' => $issuanceId, 'crf_number' => $crfNumber, 'issuance_kind' => $issuanceKind, 'replaces_issuance_id' => $replacesId, 'issue_date' => $inputs['issue_date'], 'pdf_url' => $baseUrl . '/api/serve_crf_2a.php?id=' . $issuanceId, 'download_url' => $baseUrl . '/api/serve_crf_2a.php?id=' . $issuanceId . '&download=1&action=download']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ($pdfAbsolutePath && is_file($pdfAbsolutePath)) @unlink($pdfAbsolutePath);
    error_log('CRF 2A generation failed: ' . $e->getMessage());
    json_response(false, 'Unable to generate CRF No. 2A. Please try again.', null, 500);
}
