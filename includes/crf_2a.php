<?php
/** Shared helpers for the immutable Civil Registry Form No. 2A workflow. */

require_once __DIR__ . '/crf_1a.php';

function crf_2a_view_permission(): string
{
    return 'death_crf_2a_view';
}

function crf_2a_generate_permission(): string
{
    return 'death_crf_2a_generate';
}

function crf_2a_issuance_kinds(): array
{
    return ['Original', 'Corrected', 'Reprint'];
}

function crf_2a_duplicate_key(int $deathRecordId, string $amountPaid, string $orNumber, string $datePaid, string $issueDate): string
{
    return hash('sha256', implode('|', [$deathRecordId, number_format((float)$amountPaid, 2, '.', ''), trim($orNumber), $datePaid, $issueDate]));
}

function crf_2a_record_history(PDO $pdo, ?int $issuanceId, ?string $crfNumber, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO crf_2a_issuance_history (issuance_id, crf_number, action, details, actor_id) VALUES (:issuance_id, :crf_number, :action, :details, :actor_id)');
    $stmt->execute([
        ':issuance_id' => $issuanceId ?: null,
        ':crf_number' => $crfNumber ?: null,
        ':action' => $action,
        ':details' => $details !== '' ? mb_substr($details, 0, 500, 'UTF-8') : null,
        ':actor_id' => function_exists('getUserId') ? (getUserId() ?: null) : null,
    ]);
}

function crf_2a_config(): array
{
    static $config;
    if ($config !== null) return $config;

    $oneA = crf_1a_config();
    $config = branding_apply_crf_logo_overrides([
        'office_name' => env('CRF2A_OFFICE_NAME', $oneA['office_name']),
        'municipality' => env('CRF2A_MUNICIPALITY', $oneA['municipality']),
        'province' => env('CRF2A_PROVINCE', $oneA['province']),
        'address' => env('CRF2A_OFFICE_ADDRESS', $oneA['address']),
        'logo_seal' => env('CRF2A_LOGO_SEAL', $oneA['logo_seal']),
        'logo_baggao' => env('CRF2A_LOGO_BAGGAO', $oneA['logo_baggao']),
        'logo_pilipinas' => env('CRF2A_LOGO_PILIPINAS', $oneA['logo_pilipinas']),
    ]);
    return $config;
}

function crf_2a_full_name(array $record): string
{
    return trim(implode(' ', array_filter([
        $record['deceased_first_name'] ?? '',
        $record['deceased_middle_name'] ?? '',
        $record['deceased_last_name'] ?? '',
    ], static fn($value) => trim((string)$value) !== '')));
}

function crf_2a_date(array $record, string $field): string
{
    $format = (string)($record[$field . '_format'] ?? 'full');
    $date = $record[$field] ?? null;
    if (function_exists('format_registration_date')) {
        $formatted = format_registration_date(
            $date ? (string)$date : null,
            $format,
            isset($record[$field . '_partial_month']) ? (int)$record[$field . '_partial_month'] : null,
            isset($record[$field . '_partial_year']) ? (int)$record[$field . '_partial_year'] : null,
            isset($record[$field . '_partial_day']) ? (int)$record[$field . '_partial_day'] : null
        );
        return $formatted === 'N/A' ? '' : $formatted;
    }
    if (!$date) return '';
    $timestamp = strtotime((string)$date);
    return $timestamp === false ? (string)$date : date('M d, Y', $timestamp);
}

function crf_2a_place_of_death(array $record): string
{
    return trim((string)($record['place_of_death'] ?? ''));
}

function crf_2a_record_values(array $record): array
{
    $age = trim((string)($record['age'] ?? ''));
    $ageUnit = trim((string)($record['age_unit'] ?? 'years'));
    $ageText = $age === '' ? '' : $age . ' ' . ($ageUnit === 'years' ? ((int)$age === 1 ? 'year' : 'years') : $ageUnit);
    return [
        'registry_no' => trim((string)($record['registry_no'] ?? '')),
        'date_of_registration' => crf_2a_date($record, 'date_of_registration'),
        'name_of_deceased' => crf_2a_full_name($record),
        'sex' => trim((string)($record['sex'] ?? '')),
        'age' => $ageText,
        'civil_status' => trim((string)($record['civil_status'] ?? '')),
        'citizenship' => trim((string)($record['citizenship'] ?? '')),
        'date_of_death' => crf_2a_date($record, 'date_of_death'),
        'place_of_death' => crf_2a_place_of_death($record),
        'cause_of_death' => trim((string)($record['cause_of_death'] ?? '')),
    ];
}

function crf_2a_output_relative_path(string $year, string $lastName, string $crfNumber): string
{
    $safeLastName = function_exists('folder_safe_last_name')
        ? folder_safe_last_name($lastName)
        : strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $lastName) ?: 'UNKNOWN');
    return 'crf_2a/' . $year . '/' . (trim($safeLastName, '_') ?: 'UNKNOWN') . '/' . $crfNumber . '.pdf';
}

function crf_2a_render_document_html(array $record, array $inputs, string $crfNumber): string
{
    $values = crf_2a_record_values($record);
    if (crf_1a_edge_path() === null) return crf_1a_render_table_pdf_html(
        crf_2a_config(),
        'Civil Registry Form No. 2A',
        '(Death-Available)',
        'death',
        'Deaths',
        [
            'Registry Number' => $values['registry_no'],
            'Date of Registration' => $values['date_of_registration'],
            'Name of the Deceased' => $values['name_of_deceased'],
            'Sex' => $values['sex'],
            'Age' => $values['age'],
            'Civil Status' => $values['civil_status'],
            'Citizenship' => $values['citizenship'],
            'Date of Death' => $values['date_of_death'],
            'Place of Death' => $values['place_of_death'],
            'Cause of Death' => $values['cause_of_death'],
        ],
        $inputs,
        $crfNumber
    );

    $cfg = crf_2a_config();
    $values = crf_2a_record_values($record);
    $e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    $line = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="filled-line">' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
    };
    $shortLine = static fn($value) => '<span class="short-line">' . (trim((string)$value) !== '' ? $e($value) : '&nbsp;') . '</span>';
    $field = static function (string $label, $value) use ($line, $e): string {
        return '<div class="data-row"><span class="data-label">' . $e($label) . '</span><span class="data-colon">:</span><span class="data-value">' . $line($value) . '</span></div>';
    };
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="requester-line">' . ($requester !== '' ? $e($requester) : '&nbsp;') . '</span>';
    $issueTimestamp = strtotime((string)($inputs['issue_date'] ?? date('Y-m-d')));
    $issueDateText = $issueTimestamp === false ? $e($inputs['issue_date'] ?? '') : $e(date('F j, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $logo = static function (string $path, string $alt, string $size) use ($e): string {
        $uri = crf_1a_asset_data_uri($path);
        return $uri !== '' ? '<img src="' . $uri . '" alt="' . $e($alt) . '" style="display:block;width:' . $size . ';height:' . $size . ';object-fit:contain;">' : '';
    };
    $officeName = trim((string)$cfg['office_name']);
    $officeHtml = $officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR' ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR' : $e($officeName);

    return '<!doctype html><html><head><meta charset="utf-8"><style>
@page{size:A4 portrait;margin:0}*{box-sizing:border-box}html,body{width:210mm;height:297mm;margin:0;padding:0;background:#fff}body{font-family:"Courier New",monospace;color:#111;font-size:9.5pt}.sheet{width:210mm;height:297mm;max-height:297mm;position:relative;overflow:hidden;page-break-after:avoid;break-after:avoid}.header{position:absolute;top:5mm;left:14mm;right:12mm;height:30mm}.logo{position:absolute;display:block;width:27mm;height:27mm}.logo-seal{left:0;top:1.5mm}.logo-baggao{left:31mm;top:1.5mm}.logo img{width:100%;height:100%;object-fit:contain}.logo-baggao img{transform:scale(1.35)}.right>img{transform:scale(1.2)}.copy{position:absolute;left:62mm;top:4.5mm;width:84mm;height:24mm;line-height:1.05}.copy .small{font-size:9pt}.copy .municipality{font-family:"Times New Roman",serif;font-size:13.5pt;font-weight:700}.copy .office{font-family:"Times New Roman",serif;font-size:12pt;font-weight:700}.copy .address{font-size:7.5pt;margin-top:1mm}.right{position:absolute;top:0;right:0;width:34mm;height:30mm}.right>.logo{left:0;top:0;width:34mm;height:28mm}.meta{position:absolute;top:28mm;left:0;right:0;width:100%;text-align:right;font-family:Arial,sans-serif;font-size:5.5pt;color:#334155}.meta strong{display:inline;font-family:"Courier New",monospace;font-size:5.5pt;color:#111;margin-left:1mm}.rule{position:absolute;top:38mm;left:12mm;right:12mm;border-top:1px solid #9c2020}.title{position:absolute;top:43mm;left:21mm;margin:0;font-size:12pt}.subtitle{position:absolute;top:49mm;left:21mm;margin:0;font-size:9pt}.date{position:absolute;top:56mm;left:21mm;right:35mm;text-align:right;line-height:4mm}.intro{position:absolute;top:66mm;left:21mm;right:21mm;line-height:1.25}.intro-statement{margin-top:7mm;text-align:center}.short-line{display:inline-block;width:12mm;min-height:3.8mm;border-bottom:1px solid #222;text-align:center;vertical-align:bottom}.grid{position:absolute;top:90mm;left:34mm;right:35mm}.data-row{display:table;table-layout:fixed;width:100%;min-height:4.6mm;line-height:1.05}.data-label{display:table-cell;width:78mm;white-space:nowrap;vertical-align:baseline}.data-colon{display:table-cell;width:4mm;vertical-align:baseline}.data-value{display:table-cell;width:auto;vertical-align:baseline}.filled-line{display:inline-block;width:100%;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;text-overflow:clip;vertical-align:bottom}.requester-line{display:inline-block;width:62mm;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;text-overflow:clip;vertical-align:bottom}.certification{position:absolute;top:154mm;left:29mm;right:20mm;line-height:1.3}.signature{position:absolute;top:178mm;right:30mm;width:62mm;text-align:center;font-size:9pt}.signature strong{display:block;font-family:Arial,sans-serif;font-size:9.5pt}.certified{position:absolute;top:199mm;left:21mm;width:105mm}.certified-heading{display:table;table-layout:fixed;width:95mm}.certified-heading>span:first-child{display:table-cell;width:32mm;vertical-align:bottom}.certified-heading>.certified-line{display:table-cell;vertical-align:bottom}.certified-line{width:62mm;height:5mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden}.certified-position{margin-left:32mm;width:62mm;text-align:left;min-height:4mm}.payment{position:absolute;top:219mm;left:21mm;width:90mm}.payment-row{display:table;table-layout:fixed;width:100%;min-height:4.6mm;line-height:1.05}.payment-label{display:table-cell;width:35mm;vertical-align:baseline}.payment-colon{display:table-cell;width:5mm;vertical-align:baseline}.payment-value{display:table-cell;width:auto;vertical-align:baseline}.payment .filled-line{width:38mm}.note{position:absolute;left:21mm;top:243mm;width:168mm;font-size:8pt;line-height:1.1}
</style></head><body><div class="sheet"><div class="header"><div class="logo logo-seal">' . $logo($cfg['logo_seal'], 'Baggao seal', '27mm') . '</div><div class="logo logo-baggao">' . $logo($cfg['logo_baggao'], 'Baggao reference logo', '27mm') . '</div><div class="copy"><div class="small">Republic of the Philippines</div><div class="small">Province of ' . $e($cfg['province']) . '</div><div class="municipality">MUNICIPALITY OF ' . $e($cfg['municipality']) . '</div><div class="office">' . $officeHtml . '</div><div class="address">' . $e($cfg['address']) . '</div></div><div class="right">' . $logo($cfg['logo_pilipinas'], 'Bagong Pilipinas', '28mm') . '<div class="meta">CRF ID<strong>' . $e($crfNumber) . '</strong></div></div></div><div class="rule"></div><div class="title">Civil Registry Form No. 2A</div><div class="subtitle">(Death-Available)</div><div class="date">Date: ' . $issueDateText . '</div><div class="intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="intro-statement">We certify that, among others, the following facts of death<br>appear in our Register of Deaths on page ' . $shortLine($inputs['page_number'] ?? '') . ' Book number ' . $shortLine($inputs['book_number'] ?? '') . '.</div></div><div class="grid">' . $field('Registry Number', $values['registry_no']) . $field('Date of Registration', $values['date_of_registration']) . $field('Name of the Deceased', $values['name_of_deceased']) . $field('Sex', $values['sex']) . $field('Age', $values['age']) . $field('Civil Status', $values['civil_status']) . $field('Citizenship', $values['citizenship']) . $field('Date of Death', $values['date_of_death']) . $field('Place of Death', $values['place_of_death']) . $field('Cause of Death', $values['cause_of_death']) . '</div><div class="certification">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</div><div class="signature"><strong>' . $e($inputs['mcr_full_name'] ?? '') . '</strong><div>' . $e($inputs['mcr_title'] ?? '') . '</div></div><div class="certified"><div class="certified-heading"><span>Certified by:</span><span class="certified-line">' . $e($inputs['certified_by_name'] ?? '') . '</span></div><div class="certified-position">' . $e($inputs['certified_by_position'] ?? '') . '</div></div><div class="payment"><div class="payment-row"><span class="payment-label">Amount paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($amount) . '</span></div><div class="payment-row"><span class="payment-label">O.R. Number</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['or_number'] ?? '') . '</span></div><div class="payment-row"><span class="payment-label">Date paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['date_paid'] ?? '') . '</span></div></div><div class="note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div></div></body></html>';
}

function crf_2a_render_pdf(string $html, string $outputPdf, ?string &$error = null): bool
{
    return crf_1a_render_pdf($html, $outputPdf, $error);
}
