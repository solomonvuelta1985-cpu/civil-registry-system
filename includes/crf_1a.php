<?php
/**
 * Shared CRF No. 1A helpers.
 *
 * The browser preview and the PDF generator use the same normalized record
 * values so an issued form remains faithful to what the user reviewed.
 */

function crf_1a_view_permission(): string
{
    return 'birth_crf_1a_view';
}

function crf_1a_generate_permission(): string
{
    return 'birth_crf_1a_generate';
}

function crf_1a_issuance_kinds(): array
{
    return ['Original', 'Corrected', 'Reprint'];
}

function crf_1a_duplicate_key(int $birthRecordId, string $amountPaid, string $orNumber, string $datePaid, string $issueDate): string
{
    return hash('sha256', implode('|', [$birthRecordId, number_format((float)$amountPaid, 2, '.', ''), trim($orNumber), $datePaid, $issueDate]));
}

function crf_1a_record_history(PDO $pdo, ?int $issuanceId, ?string $crfNumber, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO crf_1a_issuance_history (issuance_id, crf_number, action, details, actor_id) VALUES (:issuance_id, :crf_number, :action, :details, :actor_id)');
    $stmt->execute([
        ':issuance_id' => $issuanceId ?: null,
        ':crf_number' => $crfNumber ?: null,
        ':action' => $action,
        ':details' => $details !== '' ? mb_substr($details, 0, 500, 'UTF-8') : null,
        ':actor_id' => function_exists('getUserId') ? (getUserId() ?: null) : null,
    ]);
}

function crf_1a_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'office_name' => env('CRF1A_OFFICE_NAME', 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'),
        'municipality' => env('CRF1A_MUNICIPALITY', 'BAGGAO'),
        'province' => env('CRF1A_PROVINCE', 'CAGAYAN'),
        'address' => env('CRF1A_OFFICE_ADDRESS', 'Ground Floor, Executive Building, San Jose, Baggao, Cagayan'),
        'mcr_full_name' => env('CRF1A_MCR_FULL_NAME', 'ATANACIO G. TUNGPALAN'),
        'mcr_title' => env('CRF1A_MCR_TITLE', 'Municipal Civil Registrar'),
        'logo_seal' => env('CRF1A_LOGO_SEAL', 'assets/img/LOGO1.png'),
        'logo_baggao' => env('CRF1A_LOGO_BAGGAO', 'assets/img/CRF1A_BAGGAO_REFERENCE.png'),
        'logo_pilipinas' => env('CRF1A_LOGO_PILIPINAS', 'assets/img/CRF1A_BAGONG_PILIPINAS.png'),
    ];

    return $config;
}

function crf_1a_full_name(array $record, string $prefix): string
{
    return trim(implode(' ', array_filter([
        $record[$prefix . '_first_name'] ?? '',
        $record[$prefix . '_middle_name'] ?? '',
        $record[$prefix . '_last_name'] ?? '',
    ], static function ($value) {
        return trim((string)$value) !== '';
    })));
}

function crf_1a_date_value(array $record, string $field): string
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

    if (!$date) {
        return '';
    }
    $timestamp = strtotime((string)$date);
    return $timestamp === false ? (string)$date : date('F j, Y', $timestamp);
}

function crf_1a_registration_date(array $record): string
{
    return crf_1a_date_value($record, 'date_of_registration');
}

function crf_1a_birth_date(array $record): string
{
    return crf_1a_date_value($record, 'child_date_of_birth');
}

function crf_1a_marriage_date(array $record): string
{
    $other = trim((string)($record['date_of_marriage_others'] ?? ''));
    if ($other !== '') {
        return strtoupper(str_replace('_', ' ', $other));
    }

    return crf_1a_date_value($record, 'date_of_marriage');
}

function crf_1a_place_of_birth(array $record): string
{
    $place = trim((string)($record['child_place_of_birth'] ?? ''));
    $barangay = trim((string)($record['barangay'] ?? ''));
    $placeType = trim((string)($record['place_type'] ?? ''));

    if ($place !== '') {
        return $barangay !== '' && stripos($place, $barangay) === false
            ? $place . ', ' . $barangay
            : $place;
    }
    if ($placeType !== '' && in_array($placeType, ['Home', 'Other'], true)) {
        return $barangay !== '' ? $placeType . ', ' . $barangay : $placeType;
    }
    return $barangay;
}

function crf_1a_record_values(array $record): array
{
    return [
        'registry_no' => trim((string)($record['registry_no'] ?? '')),
        'date_of_registration' => crf_1a_registration_date($record),
        'name_of_child' => crf_1a_full_name($record, 'child'),
        'sex' => trim((string)($record['child_sex'] ?? '')),
        'date_of_birth' => crf_1a_birth_date($record),
        'place_of_birth' => crf_1a_place_of_birth($record),
        'name_of_mother' => crf_1a_full_name($record, 'mother'),
        'mother_citizenship' => trim((string)($record['mother_citizenship'] ?? '')),
        'name_of_father' => crf_1a_full_name($record, 'father'),
        'father_citizenship' => trim((string)($record['father_citizenship'] ?? '')),
        'parents_marriage_date' => crf_1a_marriage_date($record),
        'parents_marriage_place' => trim((string)($record['place_of_marriage'] ?? '')),
    ];
}

function crf_1a_asset_data_uri(string $relativePath): string
{
    $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        return '';
    }

    $mime = function_exists('mime_content_type') ? mime_content_type($absolutePath) : 'image/png';
    return 'data:' . ($mime ?: 'image/png') . ';base64,' . base64_encode((string)file_get_contents($absolutePath));
}

function crf_1a_edge_path(): ?string
{
    static $resolved = false;
    static $path = null;
    if ($resolved) return $path;
    $resolved = true;

    $engine = strtolower(trim((string)env('CRF_PDF_ENGINE', 'auto')));
    if ($engine === 'libreoffice') return null;
    $configured = trim((string)env('CRF_EDGE_PATH', ''));
    $candidates = $configured !== ''
        ? [$configured]
        : [
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }
    return $path;
}

function crf_1a_render_document_html(array $record, array $inputs, string $crfNumber): string
{
    if (crf_1a_edge_path() === null) return crf_1a_render_table_pdf_html(
        crf_1a_config(),
        'Civil Registry Form No. 1A',
        '(Birth-Available)',
        'birth',
        'Births',
        [
            'Registry Number' => crf_1a_record_values($record)['registry_no'],
            'Date of Registration' => crf_1a_record_values($record)['date_of_registration'],
            'Population Reference No.' => $record['population_reference_no'] ?? '',
            'Name of Child' => crf_1a_record_values($record)['name_of_child'],
            'Sex' => crf_1a_record_values($record)['sex'],
            'Date of Birth' => crf_1a_record_values($record)['date_of_birth'],
            'Place of Birth' => crf_1a_record_values($record)['place_of_birth'],
            'Name of Mother' => crf_1a_record_values($record)['name_of_mother'],
            'Citizenship of Mother' => crf_1a_record_values($record)['mother_citizenship'],
            'Name of father' => crf_1a_record_values($record)['name_of_father'],
            'Citizenship of Father' => crf_1a_record_values($record)['father_citizenship'],
            'Date of marriage of parents' => crf_1a_record_values($record)['parents_marriage_date'],
            'Place of Marriage of parents' => crf_1a_record_values($record)['parents_marriage_place'],
        ],
        $inputs,
        $crfNumber
    );

    $cfg = crf_1a_config();
    $values = crf_1a_record_values($record);
    $e = static function ($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    };
    $line = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="filled-line">' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
    };
    $shortLine = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="short-line">' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
    };
    $field = static function (string $label, $value) use ($line, $e): string {
        return '<div class="data-row"><span class="data-label">' . $e($label)
            . '</span><span class="data-colon">:</span><span class="data-value">'
            . $line($value) . '</span></div>';
    };
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="requester-line">' . ($requester !== '' ? $e($requester) : '&nbsp;') . '</span>';

    $logoSeal = crf_1a_asset_data_uri($cfg['logo_seal']);
    $logoBaggao = crf_1a_asset_data_uri($cfg['logo_baggao']);
    $logoPilipinas = crf_1a_asset_data_uri($cfg['logo_pilipinas']);
    $logoSealHtml = $logoSeal !== '' ? '<img src="' . $logoSeal . '" alt="Baggao seal" style="display:block;width:27mm;height:27mm;">' : '';
    $logoBaggaoHtml = $logoBaggao !== '' ? '<img src="' . $logoBaggao . '" alt="Baggao reference logo" style="display:block;width:27mm;height:27mm;">' : '';
    $logoPilipinasHtml = $logoPilipinas !== '' ? '<img src="' . $logoPilipinas . '" alt="Bagong Pilipinas" style="display:block;width:34mm;height:28mm;">' : '';
    $defaultOffice = 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR';
    $officeName = trim((string)$cfg['office_name']);
    $officeHtml = $officeName === $defaultOffice
        ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR'
        : $e($officeName);
    $issueDate = (string)($inputs['issue_date'] ?? date('Y-m-d'));
    $issueTimestamp = strtotime($issueDate);
    $issueDateText = $issueTimestamp === false ? $e($issueDate) : $e(date('F j, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);

    return '<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
html, body { width: 210mm; min-height: 297mm; margin: 0; padding: 0; background: #fff; }
body { font-family: "Courier New", Courier, monospace; color: #111; font-size: 9.5pt; }
 .sheet { width: 210mm; height: 297mm; max-height: 297mm; padding: 0; position: relative; overflow: hidden; page-break-after: avoid; break-after: avoid; }
 .header { position: absolute; top: 5mm; left: 14mm; right: 12mm; height: 30mm; }
 .header-logo { position: absolute; display: block; }
 .header-logo-seal { left: 0; top: 1.5mm; width: 27mm; height: 27mm; }
 .header-logo-baggao { left: 31mm; top: 1.5mm; width: 27mm; height: 27mm; }
 .header-logo-pilipinas { left: 0; top: 0; width: 34mm; height: 28mm; }
 .header-logo img { width: 100%; height: 100%; object-fit: contain; }
 .header-copy { position: absolute; left: 62mm; top: 4.5mm; width: 84mm; height: 24mm; text-align: left; line-height: 1.05; }
 .header-copy .republic, .header-copy .province { font-size: 9pt; }
 .header-copy .municipality { font-family: "Times New Roman", serif; font-size: 13.5pt; font-weight: 700; letter-spacing: .01em; }
 .header-copy .office { font-family: "Times New Roman", serif; font-size: 12pt; font-weight: 700; letter-spacing: .01em; }
 .header-copy .address { font-size: 7.5pt; margin-top: 1mm; }
 .header-right { position: absolute; top: 0; right: 0; width: 34mm; height: 30mm; }
 .header-meta { position: absolute; top: 28mm; left: 0; right: 0; width: 100%; text-align: right; font-family: Arial, sans-serif; font-size: 5.5pt; color: #334155; margin: 0; }
 .header-meta strong { display: inline; font-family: "Courier New", monospace; font-size: 5.5pt; color: #111; margin-left: 1mm; }
 .red-rule { position: absolute; top: 38mm; left: 12mm; right: 12mm; border-top: 1px solid #9c2020; margin: 0; }
 .form-title { position: absolute; top: 43mm; left: 21mm; margin: 0; font-size: 12pt; font-weight: 400; }
 .form-subtitle { position: absolute; top: 49mm; left: 21mm; margin: 0; font-size: 9pt; }
 .date-line { position: absolute; top: 56mm; left: 21mm; right: 35mm; text-align: right; margin: 0; line-height: 4mm; }
 .inline-note { display: none; }
 .intro { position: absolute; top: 66mm; left: 21mm; right: 21mm; margin: 0; line-height: 1.25; text-align: left; }
 .intro-statement { margin-top: 7mm; text-align: center; }
 .short-line { display: inline-block; width: 12mm; min-height: 3.8mm; border-bottom: 1px solid #222; vertical-align: bottom; text-align: center; white-space: nowrap; overflow: hidden; }
 .data-grid { position: absolute; top: 90mm; left: 34mm; right: 35mm; width: auto; margin: 0; }
 .data-row { display: table; table-layout: fixed; width: 100%; min-height: 4.6mm; line-height: 1.05; }
 .data-label { display: table-cell; width: 78mm; white-space: nowrap; vertical-align: baseline; }
.data-colon { display: table-cell; width: 4mm; vertical-align: baseline; }
.data-value { display: table-cell; width: auto; vertical-align: baseline; }
 .filled-line { display: inline-block; width: 100%; min-height: 3.8mm; border-bottom: 1px solid #222; white-space: nowrap !important; overflow: hidden !important; text-overflow: clip; vertical-align: bottom; }
 .requester-line { display: inline-block; width: 62mm; min-height: 3.8mm; border-bottom: 1px solid #222; white-space: nowrap !important; overflow: hidden !important; text-overflow: clip; vertical-align: bottom; }
 .certification { position: absolute; top: 154mm; left: 29mm; right: 20mm; margin: 0; line-height: 1.3; }
 .signature-block { position: absolute; top: 178mm; right: 30mm; margin: 0; width: 62mm; text-align: center; font-size: 9pt; }
.signature-block strong { display: block; font-family: Arial, sans-serif; font-size: 9.5pt; }
 .certified-block { position: absolute; top: 199mm; left: 21mm; margin: 0; width: 105mm; }
 .certified-heading { display: table; table-layout: fixed; width: 95mm; }
 .certified-heading > span:first-child { display: table-cell; width: 32mm; vertical-align: bottom; }
.certified-heading > .certified-line { display: table-cell; vertical-align: bottom; }
.certified-line { display: table-cell; width: 62mm; border-bottom: 1px solid #222; vertical-align: bottom; height: 5mm; }
.certified-label { margin-left: 32mm; font-weight: 700; text-align: center; width: 62mm; }
.certified-position { margin-left: 32mm; text-align: left; width: 62mm; }
 .payment-block { position: absolute; top: 219mm; left: 21mm; margin: 0; width: 90mm; }
 .payment-row { display: table; table-layout: fixed; width: 100%; min-height: 4.6mm; line-height: 1.05; }
.payment-row .payment-label { display: table-cell; width: 35mm; vertical-align: baseline; }
.payment-row .payment-colon { display: table-cell; width: 5mm; vertical-align: baseline; }
.payment-row .payment-value { display: table-cell; width: auto; vertical-align: baseline; }
.payment-row .filled-line { width: 38mm; }
 .note { position: absolute; left: 21mm; top: 243mm; width: 168mm; margin: 0; font-size: 8pt; line-height: 1.1; }
</style></head><body><div class="sheet">
 <div class="header"><div class="header-logo header-logo-seal">' . $logoSealHtml . '</div><div class="header-logo header-logo-baggao">' . $logoBaggaoHtml . '</div><div class="header-copy">
 <div class="republic">Republic of the Philippines</div>
 <div class="province">Province of ' . $e($cfg['province']) . '</div>
 <div class="municipality">MUNICIPALITY OF ' . $e($cfg['municipality']) . '</div>
 <div class="office">' . $officeHtml . '</div>
 <div class="address">' . $e($cfg['address']) . '</div>
 </div><div class="header-right"><div class="header-logo header-logo-pilipinas">' . $logoPilipinasHtml . '</div><div class="header-meta">CRF ID<strong>' . $e($crfNumber) . '</strong></div></div></div>
<div class="red-rule"></div>
<div class="form-title">Civil Registry Form No. 1A</div><div class="form-subtitle">(Birth-Available)</div>
 <div class="date-line">Date: ' . $e($issueDateText) . '</div>
 <div class="intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="intro-statement">
 We certify that, among others, the following facts of birth<br>appear in our Register of Births on page '
     . $shortLine($inputs['page_number'] ?? '') . ' Book number ' . $shortLine($inputs['book_number'] ?? '') . '.</div></div>
<div class="data-grid">'
    . $field('Registry Number', $values['registry_no'])
    . $field('Date of Registration', $values['date_of_registration'])
    . $field('Population Reference No.', $inputs['population_reference_no'] ?? '')
    . $field('Name of Child', $values['name_of_child'])
    . $field('Sex', $values['sex'])
    . $field('Date of Birth', $values['date_of_birth'])
    . $field('Place of Birth', $values['place_of_birth'])
    . $field('Name of Mother', $values['name_of_mother'])
    . $field('Citizenship of Mother', $values['mother_citizenship'])
     . $field('Name of father', $values['name_of_father'])
    . $field('Citizenship of Father', $values['father_citizenship'])
    . $field('Date of marriage of parents', $values['parents_marriage_date'])
    . $field('Place of Marriage of parents', $values['parents_marriage_place'])
    . '</div>
 <div class="certification">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</div>
<div class="signature-block"><strong>' . $e(array_key_exists('mcr_full_name', $inputs) ? $inputs['mcr_full_name'] : $cfg['mcr_full_name']) . '</strong><div>' . $e(array_key_exists('mcr_title', $inputs) ? $inputs['mcr_title'] : $cfg['mcr_title']) . '</div></div>
 <div class="certified-block"><div class="certified-heading"><span>Certified by:</span><span class="certified-line">' . $e($inputs['certified_by_name'] ?? '') . '</span></div><div class="certified-position">' . $e($inputs['certified_by_position'] ?? '') . '</div></div>
<div class="payment-block"><div class="payment-row"><span class="payment-label">Amount paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($amount) . '</span></div>
<div class="payment-row"><span class="payment-label">O.R. Number</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['or_number'] ?? '') . '</span></div>
 <div class="payment-row"><span class="payment-label">Date paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['date_paid'] ?? '') . '</span></div></div>
 <div class="note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div>
</div></body></html>';
}

/**
 * Render a LibreOffice-safe PDF document. LibreOffice's HTML importer does
 * not reliably support flexbox or positioned elements, so this template uses
 * fixed-width tables and flow rows for the same A4 geometry as the browser preview.
 */
function crf_1a_render_table_pdf_html(
    array $cfg,
    string $title,
    string $subtitle,
    string $subject,
    string $registerNoun,
    array $fields,
    array $inputs,
    string $crfNumber
): string {
    $e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    $valueLine = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="pdf-line">' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
    };
    $shortLine = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="pdf-short-line">' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
    };
    $logo = static function (string $path, string $alt, string $width, string $height) use ($e): string {
        $uri = crf_1a_asset_data_uri($path);
        return $uri !== ''
            ? '<img src="' . $uri . '" alt="' . $e($alt) . '" style="display:block;width:' . $width . ';height:' . $height . ';">'
            : '';
    };

    $issueTimestamp = strtotime((string)($inputs['issue_date'] ?? date('Y-m-d')));
    $issueDateText = $issueTimestamp === false
        ? $e($inputs['issue_date'] ?? '')
        : $e(date('F j, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="pdf-requester-line">' . ($requester !== '' ? $e($requester) : '&nbsp;') . '</span>';
    $officeName = trim((string)($cfg['office_name'] ?? 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'));
    $officeHtml = $officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'
        ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR'
        : $e($officeName);

    $fieldRows = '';
    foreach ($fields as $label => $value) {
        $fieldRows .= '<tr style="height:4.6mm;">'
            . '<td class="field-label">' . $e($label) . '</td>'
            . '<td class="field-colon">:</td>'
            . '<td class="field-value">' . $valueLine($value) . '</td>'
            . '</tr>';
    }

    $introText = 'We certify that, among others, the following facts of ' . $e($subject)
        . '<br>appear in our Register of ' . $e($registerNoun) . ' on page '
        . $shortLine($inputs['page_number'] ?? '') . ' Book number '
        . $shortLine($inputs['book_number'] ?? '') . '.';

    return '<!doctype html><html><head><meta charset="utf-8"><style>'
        . '@page{size:A4 portrait;margin:0}'
        . '*{box-sizing:border-box}'
        . 'html,body{width:210mm;height:297mm;margin:0;padding:0;background:#fff}'
        . 'body{font-family:"Courier New",monospace;color:#111;font-size:9.5pt}'
        . '.pdf-page{width:210mm;height:294mm;border-collapse:collapse;table-layout:fixed}'
        . '.pdf-page>tbody>tr>td{padding:0;vertical-align:top}'
        . '.top-spacer{height:5mm;line-height:0;font-size:0}'
        . '.header-table{width:100%;height:30mm;border-collapse:collapse;table-layout:fixed}'
        . '.header-table td{padding:0;vertical-align:middle}'
        . '.header-table .logo-group{width:32%;padding:0}'
        . '.logo-pair{width:100%;border-collapse:collapse;table-layout:fixed}'
        . '.logo-pair td{padding:0;text-align:center;vertical-align:middle}'
        . '.logo-pair .pair-logo-cell{width:50%}'
        . '.header-table .copy-cell{width:50%;text-align:left;line-height:1.05}'
        . '.header-table .right-cell{width:18%;padding:0;vertical-align:top}'
        . '.logo-pair img{display:block;width:27mm;height:27mm;margin:0 auto}'
        . '.copy-cell .small{font-size:9pt}'
        . '.copy-cell .municipality{font-family:"Times New Roman",serif;font-size:13.5pt;font-weight:700}'
        . '.copy-cell .office{font-family:"Times New Roman",serif;font-size:12pt;font-weight:700}'
        . '.copy-cell .address{font-size:7.5pt;margin-top:1mm}'
        . '.right-table{width:100%;height:30mm;border-collapse:collapse;table-layout:fixed}'
        . '.right-table td{padding:0;text-align:center}'
        . '.right-logo{height:28mm;vertical-align:top}'
        . '.right-logo img{display:block;width:34mm;height:28mm;margin:0 auto}'
        . '.header-meta{height:2mm;text-align:right;font-family:Arial,sans-serif;font-size:5.5pt;color:#334155;white-space:nowrap}'
        . '.header-meta strong{font-family:"Courier New",monospace;font-size:5.5pt;color:#111;margin-left:1mm}'
        . '.header-gap{height:3mm;line-height:0;font-size:0}'
        . '.rule-row{height:1mm;padding:0 12mm!important}'
        . '.red-rule{height:1mm;border-top:1px solid #9c2020;font-size:0;line-height:0}'
        . '.after-rule{height:4mm;line-height:0;font-size:0}'
        . '.title-row{height:6mm;padding-left:21mm!important;font-size:12pt;line-height:1.1}'
        . '.subtitle-row{height:7mm;padding-left:21mm!important;font-size:9pt;line-height:1.1}'
        . '.date-row{height:10mm;padding-right:35mm!important;text-align:right;line-height:4mm}'
        . '.intro-row{height:24mm;padding:0 21mm!important}'
        . '.intro-table{width:100%;height:24mm;border-collapse:collapse;table-layout:fixed}'
        . '.intro-table td{padding:0;line-height:1.25}'
        . '.intro-heading{height:4mm}'
        . '.intro-gap{height:7mm;font-size:0;line-height:0}'
        . '.intro-statement{text-align:center;vertical-align:top}'
        . '.pdf-short-line{display:inline-block;width:12mm;height:3.8mm;border-bottom:1px solid #222;text-align:center;vertical-align:bottom;white-space:nowrap;overflow:hidden}'
        . '.field-table{width:141mm;margin-left:34mm;border-collapse:collapse;table-layout:fixed}'
        . '.field-table td{padding:0;line-height:1.05;vertical-align:baseline;white-space:nowrap}'
        . '.field-label{width:78mm}'
        . '.field-colon{width:4mm}'
        . '.field-value{width:59mm;overflow:hidden}'
        . '.pdf-line{display:inline-block;width:100%;height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}'
        . '.grid-spacer{height:4.2mm;font-size:0;line-height:0}'
        . '.certification-row{height:24mm;padding:0 20mm 0 29mm!important;line-height:1.3}'
        . '.pdf-requester-line{display:inline-block;width:62mm;height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}'
        . '.signature-row{height:21mm}'
        . '.signature-table{width:210mm;height:21mm;border-collapse:collapse;table-layout:fixed}'
        . '.signature-table td{padding:0;vertical-align:top}'
        . '.signature-spacer{width:118mm}'
        . '.signature-cell{width:62mm;text-align:center;font-size:9pt}'
        . '.signature-cell strong{display:block;font-family:Arial,sans-serif;font-size:9.5pt}'
        . '.signature-right-spacer{width:30mm}'
        . '.certified-row{height:20mm}'
        . '.certified-table{width:105mm;margin-left:21mm;border-collapse:collapse;table-layout:fixed}'
        . '.certified-table td{padding:0;vertical-align:bottom}'
        . '.certified-heading{width:95mm;border-collapse:collapse;table-layout:fixed}'
        . '.certified-heading .certified-label{width:32mm}'
        . '.certified-heading .certified-line{width:62mm;height:5mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden}'
        . '.certified-position{width:62mm;margin-left:32mm;text-align:left;height:4mm;line-height:1.1}'
        . '.payment-row{height:24mm}'
        . '.payment-table{width:90mm;margin-left:21mm;border-collapse:collapse;table-layout:fixed}'
        . '.payment-table td{padding:0;height:4.6mm;line-height:1.05;vertical-align:baseline;white-space:nowrap}'
        . '.payment-label{width:35mm}'
        . '.payment-colon{width:5mm}'
        . '.payment-value{width:50mm}'
        . '.payment-value .pdf-line{width:38mm}'
        . '.note-row{padding-left:21mm!important;padding-top:0!important}'
        . '.note{width:168mm;font-size:8pt;line-height:1.1}'
        . '</style></head><body><table class="pdf-page" cellspacing="0" cellpadding="0" border="0"><tr><td class="top-spacer">&nbsp;</td></tr>'
        . '<tr><td style="padding-left:14mm;padding-right:12mm;"><table class="header-table" cellspacing="0" cellpadding="0" border="0"><tr>'
        . '<td class="logo-group" width="32%"><table class="logo-pair" cellspacing="0" cellpadding="0" border="0"><tr><td class="pair-logo-cell" width="50%">' . $logo((string)($cfg['logo_seal'] ?? ''), 'Baggao seal', '27mm', '27mm') . '</td><td class="pair-logo-cell" width="50%">' . $logo((string)($cfg['logo_baggao'] ?? ''), 'Baggao reference logo', '27mm', '27mm') . '</td></tr></table></td>'
        . '<td class="copy-cell" width="50%"><div class="small">Republic of the Philippines</div><div class="small">Province of ' . $e($cfg['province'] ?? '') . '</div><div class="municipality">MUNICIPALITY OF ' . $e($cfg['municipality'] ?? '') . '</div><div class="office">' . $officeHtml . '</div><div class="address">' . $e($cfg['address'] ?? '') . '</div></td>'
        . '<td class="right-cell" width="18%"><table class="right-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="right-logo">' . $logo((string)($cfg['logo_pilipinas'] ?? ''), 'Bagong Pilipinas', '34mm', '28mm') . '</td></tr><tr><td class="header-meta">CRF ID<strong>' . $e($crfNumber) . '</strong></td></tr></table></td>'
        . '</tr></table></td></tr><tr><td class="header-gap">&nbsp;</td></tr><tr><td class="rule-row"><div class="red-rule">&nbsp;</div></td></tr><tr><td class="after-rule">&nbsp;</td></tr>'
        . '<tr><td class="title-row">' . $e($title) . '</td></tr><tr><td class="subtitle-row">' . $e($subtitle) . '</td></tr><tr><td class="date-row">Date: ' . $issueDateText . '</td></tr>'
        . '<tr><td class="intro-row"><table class="intro-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="intro-heading"><strong>TO WHOM IT MAY CONCERN:</strong></td></tr><tr><td class="intro-gap">&nbsp;</td></tr><tr><td class="intro-statement">' . $introText . '</td></tr></table></td></tr>'
        . '<tr><td><table class="field-table" cellspacing="0" cellpadding="0" border="0">' . $fieldRows . '</table></td></tr><tr><td class="grid-spacer">&nbsp;</td></tr>'
        . '<tr><td class="certification-row">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</td></tr>'
        . '<tr><td class="signature-row"><table class="signature-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="signature-spacer">&nbsp;</td><td class="signature-cell"><strong>' . $e($inputs['mcr_full_name'] ?? '') . '</strong><div>' . $e($inputs['mcr_title'] ?? '') . '</div></td><td class="signature-right-spacer">&nbsp;</td></tr></table></td></tr>'
        . '<tr><td class="certified-row"><table class="certified-table" cellspacing="0" cellpadding="0" border="0"><tr><td><table class="certified-heading" cellspacing="0" cellpadding="0" border="0"><tr><td class="certified-label">Certified by:</td><td class="certified-line">' . $e($inputs['certified_by_name'] ?? '') . '</td></tr></table><div class="certified-position">' . $e($inputs['certified_by_position'] ?? '') . '</div></td></tr></table></td></tr>'
        . '<tr><td class="payment-row"><table class="payment-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="payment-label">Amount paid</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine($amount) . '</td></tr><tr><td class="payment-label">O.R. Number</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine($inputs['or_number'] ?? '') . '</td></tr><tr><td class="payment-label">Date paid</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine($inputs['date_paid'] ?? '') . '</td></tr></table></td></tr>'
        . '<tr><td class="note-row"><div class="note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div></td></tr>'
        . '</table></body></html>';
}

function crf_1a_output_relative_path(string $year, string $lastName, string $crfNumber): string
{
    $safeLastName = function_exists('folder_safe_last_name')
        ? folder_safe_last_name($lastName)
        : strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $lastName) ?: 'UNKNOWN');
    $safeLastName = trim($safeLastName, '_') ?: 'UNKNOWN';
    return 'crf_1a/' . $year . '/' . $safeLastName . '/' . $crfNumber . '.pdf';
}

function crf_1a_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
    }
    @rmdir($directory);
}

function crf_1a_render_pdf_with_edge(string $html, string $outputPdf, string $edgePath, ?string &$error = null): bool
{
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crf_edge_' . bin2hex(random_bytes(6));
    if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
        $error = 'Unable to create the temporary Edge PDF workspace.';
        return false;
    }

    $htmlPath = $tempDir . DIRECTORY_SEPARATOR . 'crf.html';
    $generatedPdf = $tempDir . DIRECTORY_SEPARATOR . 'crf.pdf';
    $profileDir = $tempDir . DIRECTORY_SEPARATOR . 'edge_profile';
    $htmlUrl = 'file:///' . str_replace('\\', '/', $htmlPath);

    try {
        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException('Unable to write the temporary CRF document.');
        }
        $command = escapeshellarg($edgePath)
            . ' --headless=new --disable-gpu --no-first-run --no-default-browser-check'
            . ' --user-data-dir=' . escapeshellarg($profileDir)
            . ' --print-to-pdf=' . escapeshellarg($generatedPdf)
            . ' --no-pdf-header-footer '
            . escapeshellarg($htmlUrl) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($generatedPdf) || filesize($generatedPdf) < 100) {
            throw new RuntimeException('Edge PDF conversion failed: ' . implode(' | ', $output));
        }
        if (crf_1a_pdf_page_count($generatedPdf) !== 1) {
            throw new RuntimeException('The generated CRF PDF is not a one-page document.');
        }
        $targetDir = dirname($outputPdf);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create the CRF output directory.');
        }
        if (!copy($generatedPdf, $outputPdf)) {
            throw new RuntimeException('Unable to store the generated CRF PDF.');
        }
        return true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        return false;
    } finally {
        crf_1a_remove_directory($tempDir);
    }
}

function crf_1a_render_pdf(string $html, string $outputPdf, ?string &$error = null): bool
{
    $edgePath = crf_1a_edge_path();
    if ($edgePath !== null) {
        return crf_1a_render_pdf_with_edge($html, $outputPdf, $edgePath, $error);
    }

    $soffice = trim((string)env('LIBREOFFICE_PATH', ''));
    $dockerImage = null;
    if (strncmp($soffice, 'docker:', 7) === 0) {
        $dockerImage = trim(substr($soffice, 7));
        if ($dockerImage === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*(?::[A-Za-z0-9._-]+)?$/', $dockerImage)) {
            $error = 'Invalid LibreOffice Docker image configured in LIBREOFFICE_PATH.';
            return false;
        }
    } elseif ($soffice === '' || !is_file($soffice)) {
        $error = 'LibreOffice is not configured. Set LIBREOFFICE_PATH in .env.';
        return false;
    }

    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crf1a_' . bin2hex(random_bytes(6));
    if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
        $error = 'Unable to create the temporary PDF workspace.';
        return false;
    }

    $htmlPath = $tempDir . DIRECTORY_SEPARATOR . 'crf_1a.html';
    $generatedPdf = $tempDir . DIRECTORY_SEPARATOR . 'crf_1a.pdf';
    $profileDir = $tempDir . DIRECTORY_SEPARATOR . 'lo_profile';
    $profileUri = 'file:///' . str_replace('\\', '/', $profileDir);

    try {
        if (file_put_contents($htmlPath, $html) === false) {
            throw new RuntimeException('Unable to write the temporary CRF document.');
        }

        if ($dockerImage !== null) {
            $dockerBinary = trim((string)env('DOCKER_PATH', 'docker')) ?: 'docker';
            $command = escapeshellarg($dockerBinary)
                . ' run --rm --user $(id -u):$(id -g) --env HOME=/tmp'
                . ' -v ' . escapeshellarg($tempDir . ':/data')
                . ' ' . escapeshellarg($dockerImage)
                . ' --headless --norestore --nolockcheck --nodefault --nofirststartwizard'
                . ' -env:UserInstallation=' . escapeshellarg('file:///tmp/lo_profile')
                . ' --convert-to pdf:writer_pdf_Export --outdir /data /data/crf_1a.html 2>&1';
        } else {
            $command = escapeshellarg($soffice)
                . ' --headless --norestore --nolockcheck --nodefault --nofirststartwizard'
                . ' -env:UserInstallation=' . escapeshellarg($profileUri)
                . ' --convert-to pdf:writer_pdf_Export --outdir ' . escapeshellarg($tempDir)
                . ' ' . escapeshellarg($htmlPath) . ' 2>&1';
        }
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($generatedPdf) || filesize($generatedPdf) < 100) {
            throw new RuntimeException('LibreOffice PDF conversion failed: ' . implode(' | ', $output));
        }
        if (!crf_1a_remove_trailing_blank_pages($generatedPdf)) {
            throw new RuntimeException('Unable to normalize the generated CRF PDF to one page.');
        }
        if (crf_1a_pdf_page_count($generatedPdf) !== 1) {
            throw new RuntimeException('The generated CRF PDF is not a one-page document.');
        }

        $targetDir = dirname($outputPdf);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create the CRF output directory.');
        }
        if (!copy($generatedPdf, $outputPdf)) {
            throw new RuntimeException('Unable to store the generated CRF PDF.');
        }
        return true;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        return false;
    } finally {
        crf_1a_remove_directory($tempDir);
    }
}

/**
 * LibreOffice's HTML import can append an unpainted trailing page. Remove only
 * that specific page-tree entry while leaving all referenced fonts/images and
 * the original PDF cross-reference offsets intact.
 */
function crf_1a_remove_trailing_blank_pages(string $pdfPath): bool
{
    $pdf = @file_get_contents($pdfPath);
    if ($pdf === false || $pdf === '') return false;

    if (!preg_match('~/Type/Pages.*?/Kids\\[(.*?)\\].*?/Count\\s+(\\d+)~s', $pdf, $treeMatch, PREG_OFFSET_CAPTURE)) {
        return true;
    }
    $count = (int)$treeMatch[2][0];
    $kidsText = $treeMatch[1][0];
    $refs = [];
    preg_match_all('~(\\d+)\\s+0\\s+R~', $kidsText, $refMatches);
    foreach ($refMatches[1] as $ref) $refs[] = (int)$ref;
    if ($count <= 1 || count($refs) <= 1) return true;

    $lastRef = end($refs);
    $pageObject = '';
    if (!preg_match('~(?<!\\d)' . $lastRef . '\\s+0\\s+obj\\s*<<(.*?)>>\\s*endobj~s', $pdf, $pageMatch)) return false;
    $pageObject = $pageMatch[1];
    if (!preg_match('~/Contents\\s+(\\d+)\\s+0\\s+R~', $pageObject, $contentsMatch)) return false;
    $contentsRef = (int)$contentsMatch[1];
    if (!preg_match('~(?<!\\d)' . $contentsRef . '\\s+0\\s+obj.*?stream\\r?\\n(.*?)endstream~s', $pdf, $contentsStream)) return false;
    $decoded = false;
    if (function_exists('gzuncompress')) {
        $decoded = @gzuncompress($contentsStream[1]);
    }
    if ($decoded === false && function_exists('zlib_decode')) {
        $decoded = @zlib_decode($contentsStream[1]);
    }
    // Text, image, and paint operators mean the page is not merely the
    // unpainted trailing page produced by the HTML importer.
    if ($decoded !== false && (strlen(trim($decoded)) > 128
        || preg_match('~(?:^|[\\s>\\)\\]])(?:Tj|TJ|Do|S|s|f|F|B|b)(?:\\s|$)~', $decoded))) return true;

    $oldTree = $treeMatch[0][0];
    $newKids = '[ ' . implode(' ', array_map(static fn($ref) => $ref . ' 0 R', array_slice($refs, 0, -1))) . ' ]';
    $newTree = preg_replace('~/Kids\\[.*?\\].*?/Count\\s+\\d+~s', '/Kids' . $newKids . ' /Count ' . ($count - 1), $oldTree, 1);
    if (!is_string($newTree) || strlen($newTree) > strlen($oldTree)) return false;
    $newTree = str_pad($newTree, strlen($oldTree), ' ');
    $offset = $treeMatch[0][1];
    $pdf = substr_replace($pdf, $newTree, $offset, strlen($oldTree));
    return @file_put_contents($pdfPath, $pdf, LOCK_EX) !== false;
}

function crf_1a_pdf_page_count(string $pdfPath): int
{
    $pdf = @file_get_contents($pdfPath);
    if ($pdf === false || $pdf === '') return 0;
    if (preg_match('~/Type\\s*/Pages.*?/Count\\s+(\\d+)~s', $pdf, $match)) return (int)$match[1];
    $pages = preg_match_all('~/Type\\s*/Page\\b~', $pdf, $matches);
    return $pages === false ? 0 : (int)$pages;
}
