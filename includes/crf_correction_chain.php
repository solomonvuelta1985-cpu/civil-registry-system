<?php
/** Shared correction-chain helpers for CRF 1A, 2A, and 3A. */

function crf_correction_config(string $form): array
{
    $configs = [
        '1a' => [
            'table' => 'crf_1a_issuances', 'history' => 'crf_1a_issuance_history',
            'source_column' => 'birth_record_id', 'source_table' => 'certificate_of_live_birth',
            'folder' => 'crf_1a', 'prefix' => 'CRF1A',
        ],
        '2a' => [
            'table' => 'crf_2a_issuances', 'history' => 'crf_2a_issuance_history',
            'source_column' => 'death_record_id', 'source_table' => 'certificate_of_death',
            'folder' => 'crf_2a', 'prefix' => 'CRF2A',
        ],
        '3a' => [
            'table' => 'crf_3a_issuances', 'history' => 'crf_3a_issuance_history',
            'source_column' => 'marriage_record_id', 'source_table' => 'certificate_of_marriage',
            'folder' => 'crf_3a', 'prefix' => 'CRF3A',
        ],
    ];
    $key = strtolower(preg_replace('/^crf[_-]?/', '', trim($form)) ?? '');
    if (!isset($configs[$key])) {
        throw new InvalidArgumentException('Unknown CRF correction type.');
    }
    return $configs[$key];
}

function crf_correction_record_history(PDO $pdo, string $form, int $issuanceId, string $crfNumber, string $action, string $details = ''): void
{
    $config = crf_correction_config($form);
    $stmt = $pdo->prepare("INSERT INTO {$config['history']} (issuance_id, crf_number, action, details, actor_id) VALUES (:issuance_id, :crf_number, :action, :details, :actor_id)");
    $stmt->execute([
        ':issuance_id' => $issuanceId,
        ':crf_number' => $crfNumber,
        ':action' => $action,
        ':details' => $details !== '' ? mb_substr($details, 0, 500, 'UTF-8') : null,
        ':actor_id' => function_exists('getUserId') ? (getUserId() ?: null) : null,
    ]);
}

function crf_correction_record_history_once(PDO $pdo, string $form, int $issuanceId, string $crfNumber, string $action, string $details): void
{
    $config = crf_correction_config($form);
    $check = $pdo->prepare("SELECT 1 FROM {$config['history']} WHERE issuance_id = :issuance_id AND action = :action AND details = :details LIMIT 1");
    $check->execute([':issuance_id' => $issuanceId, ':action' => $action, ':details' => mb_substr($details, 0, 500, 'UTF-8')]);
    if (!$check->fetchColumn()) crf_correction_record_history($pdo, $form, $issuanceId, $crfNumber, $action, $details);
}

function crf_correction_latest_current(PDO $pdo, string $form, int $sourceId): ?array
{
    $config = crf_correction_config($form);
    $stmt = $pdo->prepare("SELECT id, crf_number, issuance_kind, status FROM {$config['table']} WHERE {$config['source_column']} = :source_id AND issuance_kind IN ('Original', 'Corrected') AND status IN ('Active', 'Archived', 'Voided') ORDER BY id DESC LIMIT 1");
    $stmt->execute([':source_id' => $sourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function crf_correction_load_chain(PDO $pdo, string $form, int $issuanceId, bool $strict = false): array
{
    $config = crf_correction_config($form);
    $stmt = $pdo->prepare("SELECT * FROM {$config['table']} WHERE id = :id LIMIT 1");
    $chain = [];
    $visited = [];
    $nextId = $issuanceId;
    $expectedSource = null;

    for ($depth = 0; $nextId > 0 && $depth < 1000; $depth++) {
        if (isset($visited[$nextId])) {
            if ($strict) throw new RuntimeException('The existing CRF correction chain contains a cycle.');
            break;
        }
        $visited[$nextId] = true;
        $stmt->execute([':id' => $nextId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if ($strict) throw new RuntimeException('The selected CRF correction chain is incomplete.');
            break;
        }
        $sourceId = (int)$row[$config['source_column']];
        if ($expectedSource !== null && $sourceId !== $expectedSource) {
            if ($strict) throw new RuntimeException('The selected CRF correction chain crosses source records.');
            break;
        }
        $expectedSource = $sourceId;
        if (!in_array((string)$row['issuance_kind'], ['Original', 'Corrected'], true)) {
            if ($strict) throw new RuntimeException('A reprint cannot be used as the base of a corrected CRF.');
            break;
        }
        $chain[] = $row;
        $nextId = (int)($row['replaces_issuance_id'] ?? 0);
    }

    if ($strict && count($chain) >= 1000) {
        throw new RuntimeException('The CRF correction chain exceeds the supported depth.');
    }
    return $chain;
}

function crf_correction_chain_history(PDO $pdo, string $form, int $issuanceId): array
{
    $config = crf_correction_config($form);
    $chain = crf_correction_load_chain($pdo, $form, $issuanceId);
    $ids = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $chain)));
    if (!$ids) return [];
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT h.action, h.details, h.created_at, h.actor_id, u.full_name AS actor_name FROM {$config['history']} h LEFT JOIN users u ON u.id = h.actor_id WHERE h.issuance_id IN ({$placeholders}) ORDER BY h.created_at ASC, h.id ASC");
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function crf_correction_snapshot(array $row): array
{
    $snapshot = json_decode((string)($row['record_snapshot_json'] ?? ''), true);
    return is_array($snapshot) ? $snapshot : [];
}

function crf_correction_value(array $values, string $key): string
{
    $value = $values[$key] ?? '';
    if (is_array($value) || is_object($value)) return '';
    return trim(str_replace(["\r\n", "\r"], "\n", (string)$value));
}

function crf_correction_diff_fields(string $form): array
{
    $common = [
        'issue_date' => ['Issue Date', 'column'],
        'page_number' => ['Page Number', 'column'],
        'book_number' => ['Book Number', 'column'],
        'requester_name' => ['Requester', 'column'],
        'amount_paid' => ['Amount Paid', 'column'],
        'or_number' => ['O.R. Number', 'column'],
        'date_paid' => ['Date Paid', 'column'],
        'mcr_full_name' => ['Municipal Civil Registrar', 'column'],
        'mcr_title' => ['Registrar Position', 'column'],
    ];
    if ($form === '1a') {
        return $common + [
            'population_reference_no' => ['Population Reference No.', 'column'],
            'certified_by_name' => ['Certified By', 'column'],
            'certified_by_position' => ['Certified By Position', 'column'],
            'registry_no' => ['Registry Number', 'display'],
            'date_of_registration' => ['Date of Registration', 'display'],
            'name_of_child' => ['Name of Child', 'display'],
            'sex' => ['Sex', 'display'],
            'date_of_birth' => ['Date of Birth', 'display'],
            'place_of_birth' => ['Place of Birth', 'display'],
            'name_of_mother' => ['Name of Mother', 'display'],
            'mother_citizenship' => ['Citizenship of Mother', 'display'],
            'name_of_father' => ['Name of Father', 'display'],
            'father_citizenship' => ['Citizenship of Father', 'display'],
            'parents_marriage_date' => ['Date of Marriage of Parents', 'display'],
            'parents_marriage_place' => ['Place of Marriage of Parents', 'display'],
        ];
    }
    if ($form === '2a') {
        return $common + [
            'certified_by_name' => ['Certified By', 'column'],
            'certified_by_position' => ['Certified By Position', 'column'],
            'registry_no' => ['Registry Number', 'display'],
            'date_of_registration' => ['Date of Registration', 'display'],
            'name_of_deceased' => ['Name of Deceased', 'display'],
            'sex' => ['Sex', 'display'],
            'age' => ['Age', 'display'],
            'civil_status' => ['Civil Status', 'display'],
            'citizenship' => ['Citizenship', 'display'],
            'date_of_death' => ['Date of Death', 'display'],
            'place_of_death' => ['Place of Death', 'display'],
            'cause_of_death' => ['Cause of Death', 'display'],
        ];
    }
    return $common + [
        'verified_by_name' => ['Verified By', 'column'],
        'verified_by_position' => ['Verified By Position', 'column'],
        'registry_no' => ['Registry Number', 'display'],
        'date_of_registration' => ['Date of Registration', 'display'],
        'husband_name' => ['Husband Name', 'display'],
        'husband_date_of_birth_age' => ['Husband Date of Birth / Age', 'display'],
        'husband_nationality' => ['Husband Nationality', 'display'],
        'husband_civil_status' => ['Husband Civil Status', 'display'],
        'husband_mother_name' => ['Husband Mother', 'display'],
        'husband_mother_nationality' => ['Husband Mother Nationality', 'display'],
        'husband_father_name' => ['Husband Father', 'display'],
        'husband_father_nationality' => ['Husband Father Nationality', 'display'],
        'wife_name' => ['Wife Name', 'display'],
        'wife_date_of_birth_age' => ['Wife Date of Birth / Age', 'display'],
        'wife_nationality' => ['Wife Nationality', 'display'],
        'wife_civil_status' => ['Wife Civil Status', 'display'],
        'wife_mother_name' => ['Wife Mother', 'display'],
        'wife_mother_nationality' => ['Wife Mother Nationality', 'display'],
        'wife_father_name' => ['Wife Father', 'display'],
        'wife_father_nationality' => ['Wife Father Nationality', 'display'],
        'date_of_marriage' => ['Date of Marriage', 'display'],
        'place_of_marriage' => ['Place of Marriage', 'display'],
    ];
}

function crf_correction_diff_rows(string $form, array $previous, array $current): array
{
    $previousSnapshot = crf_correction_snapshot($previous);
    $currentSnapshot = crf_correction_snapshot($current);
    $previousDisplay = is_array($previousSnapshot['display'] ?? null) ? $previousSnapshot['display'] : [];
    $currentDisplay = is_array($currentSnapshot['display'] ?? null) ? $currentSnapshot['display'] : [];
    $changes = [];
    foreach (crf_correction_diff_fields($form) as $key => [$label, $location]) {
        $oldValue = $location === 'column' ? ($previous[$key] ?? '') : ($previousDisplay[$key] ?? '');
        $newValue = $location === 'column' ? ($current[$key] ?? '') : ($currentDisplay[$key] ?? '');
        $oldText = crf_correction_value([$key => $oldValue], $key);
        $newText = crf_correction_value([$key => $newValue], $key);
        if ($oldText !== $newText) {
            $changes[] = ['label' => $label, 'previous' => $oldText, 'new' => $newText];
        }
    }

    $previousManual = is_array($previousSnapshot['manual_overrides'] ?? null) ? $previousSnapshot['manual_overrides'] : [];
    $currentManual = is_array($currentSnapshot['manual_overrides'] ?? null) ? $currentSnapshot['manual_overrides'] : [];
    if (crf_correction_value($previousManual, 'remarks_html') !== crf_correction_value($currentManual, 'remarks_html')) {
        $changes[] = ['label' => 'Remarks', 'previous' => '', 'new' => '', 'remarks' => true];
    }
    return $changes;
}

function crf_correction_compact_value(string $value, int $limit = 165): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (mb_strlen($value, 'UTF-8') > $limit) {
        return mb_substr($value, 0, $limit - 1, 'UTF-8') . '…';
    }
    return $value !== '' ? $value : '(blank)';
}

function crf_correction_record_diffs(PDO $pdo, string $form, int $issuanceId, string $crfNumber, array $previous, array $current, bool $skipIfRecorded = false): void
{
    if ($skipIfRecorded) {
        $config = crf_correction_config($form);
        $check = $pdo->prepare("SELECT 1 FROM {$config['history']} WHERE issuance_id = :id AND action = 'field_updated' LIMIT 1");
        $check->execute([':id' => $issuanceId]);
        if ($check->fetchColumn()) return;
    }
    foreach (crf_correction_diff_rows($form, $previous, $current) as $change) {
        if (!empty($change['remarks'])) {
            crf_correction_record_history($pdo, $form, $issuanceId, $crfNumber, 'field_updated', 'Remarks updated');
            continue;
        }
        $details = $change['label'] . ': ' . crf_correction_compact_value($change['previous']) . ' → ' . crf_correction_compact_value($change['new']);
        crf_correction_record_history($pdo, $form, $issuanceId, $crfNumber, 'field_updated', $details);
    }
}

function crf_correction_safe_pdf_path(string $form, string $relativePath): ?string
{
    $config = crf_correction_config($form);
    $relativePath = str_replace('\\', '/', trim($relativePath));
    $pattern = '~^' . preg_quote($config['folder'], '~') . '/\\d{4}/[A-Z0-9_]+/' . preg_quote($config['prefix'], '~') . '-\\d{4}-\\d{6}\\.pdf$~';
    if ($relativePath === '' || !preg_match($pattern, $relativePath)) return null;
    $base = realpath(UPLOAD_PATH);
    $absolute = realpath(UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($base === false || $absolute === false || !is_file($absolute)) return null;
    $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($absolute, $prefix) !== 0) return null;
    return $absolute;
}

function crf_correction_verify_pdf(string $form, array $row): array
{
    $relative = (string)($row['pdf_filepath'] ?? '');
    $absolute = crf_correction_safe_pdf_path($form, $relative);
    if ($absolute === null) return ['ok' => false, 'reason' => 'missing or unsafe PDF path'];
    $expected = trim((string)($row['pdf_hash'] ?? ''));
    if ($expected === '') return ['ok' => false, 'reason' => 'stored PDF hash is missing'];
    $actual = hash_file('sha256', $absolute);
    if ($actual === false || !hash_equals($expected, $actual)) return ['ok' => false, 'reason' => 'PDF hash mismatch'];
    return ['ok' => true, 'reason' => '', 'path' => $absolute];
}

function crf_correction_remove_pdf(string $form, array $row): array
{
    $relative = (string)($row['pdf_filepath'] ?? '');
    $config = crf_correction_config($form);
    $relative = str_replace('\\', '/', trim($relative));
    $pattern = '~^' . preg_quote($config['folder'], '~') . '/\\d{4}/[A-Z0-9_]+/' . preg_quote($config['prefix'], '~') . '-\\d{4}-\\d{6}\\.pdf$~';
    if ($relative === '' || !preg_match($pattern, $relative)) return ['ok' => false, 'reason' => 'unsafe PDF path'];
    $base = realpath(UPLOAD_PATH);
    if ($base === false) return ['ok' => false, 'reason' => 'upload folder is unavailable'];
    $candidate = UPLOAD_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!file_exists($candidate)) return ['ok' => true, 'reason' => 'already missing'];
    $absolute = realpath($candidate);
    $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($absolute === false || strpos($absolute, $prefix) !== 0 || !is_file($absolute)) return ['ok' => false, 'reason' => 'unsafe PDF target'];
    $expected = trim((string)($row['pdf_hash'] ?? ''));
    if ($expected !== '') {
        $actual = hash_file('sha256', $absolute);
        if ($actual === false || !hash_equals($expected, $actual)) return ['ok' => false, 'reason' => 'superseded PDF hash mismatch'];
    }
    if (!@unlink($absolute) && is_file($absolute)) return ['ok' => false, 'reason' => 'file deletion failed'];
    return ['ok' => true, 'reason' => 'deleted'];
}

function crf_correction_cleanup_after_generation(PDO $pdo, string $form, array $latest, array $supersededRows): array
{
    $latestCheck = crf_correction_verify_pdf($form, $latest);
    if (!$latestCheck['ok']) {
        $message = 'Superseded PDF cleanup skipped: newest PDF ' . $latestCheck['reason'] . '.';
        error_log('CRF ' . strtoupper($form) . ' correction cleanup: ' . $message);
        crf_correction_record_history_once($pdo, $form, (int)$latest['id'], (string)$latest['crf_number'], 'pdf_cleanup_skipped', $message);
        return [['id' => 0, 'reason' => $latestCheck['reason']]];
    }

    $failures = [];
    foreach ($supersededRows as $row) {
        $removed = crf_correction_remove_pdf($form, $row);
        if (!$removed['ok']) {
            $failure = ['id' => (int)$row['id'], 'reason' => $removed['reason']];
            $failures[] = $failure;
            $message = 'Could not remove superseded PDF for ' . (string)$row['crf_number'] . ': ' . $removed['reason'] . '.';
            error_log('CRF ' . strtoupper($form) . ' correction cleanup: ' . $message);
            crf_correction_record_history_once($pdo, $form, (int)$latest['id'], (string)$latest['crf_number'], 'pdf_cleanup_failed', $message);
        }
    }
    return $failures;
}
