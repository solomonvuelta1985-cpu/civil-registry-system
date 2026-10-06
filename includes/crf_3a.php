<?php
/** Shared helpers for the immutable Civil Registry Form No. 3A workflow. */

require_once __DIR__ . '/crf_1a.php';

function crf_3a_view_permission(): string
{
    return 'marriage_crf_3a_view';
}

function crf_3a_generate_permission(): string
{
    return 'marriage_crf_3a_generate';
}

function crf_3a_issuance_kinds(): array
{
    return ['Original', 'Corrected', 'Reprint'];
}

function crf_3a_duplicate_key(int $marriageRecordId, string $amountPaid, string $orNumber, string $datePaid, string $issueDate): string
{
    return hash('sha256', implode('|', [
        $marriageRecordId,
        number_format((float)$amountPaid, 2, '.', ''),
        trim($orNumber),
        $datePaid,
        $issueDate,
    ]));
}

function crf_3a_record_history(PDO $pdo, ?int $issuanceId, ?string $crfNumber, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO crf_3a_issuance_history (issuance_id, crf_number, action, details, actor_id) VALUES (:issuance_id, :crf_number, :action, :details, :actor_id)');
    $stmt->execute([
        ':issuance_id' => $issuanceId ?: null,
        ':crf_number' => $crfNumber ?: null,
        ':action' => $action,
        ':details' => $details !== '' ? mb_substr($details, 0, 500, 'UTF-8') : null,
        ':actor_id' => function_exists('getUserId') ? (getUserId() ?: null) : null,
    ]);
}

function crf_3a_config(): array
{
    static $config;
    if ($config !== null) return $config;

    $oneA = crf_1a_config();
    $config = branding_apply_crf_logo_overrides([
        'office_name' => env('CRF3A_OFFICE_NAME', $oneA['office_name']),
        'municipality' => env('CRF3A_MUNICIPALITY', $oneA['municipality']),
        'province' => env('CRF3A_PROVINCE', $oneA['province']),
        'address' => env('CRF3A_OFFICE_ADDRESS', $oneA['address']),
        'logo_seal' => env('CRF3A_LOGO_SEAL', $oneA['logo_seal']),
        'logo_baggao' => env('CRF3A_LOGO_BAGGAO', $oneA['logo_baggao']),
        'logo_pilipinas' => env('CRF3A_LOGO_PILIPINAS', $oneA['logo_pilipinas']),
    ]);
    return $config;
}

function crf_3a_full_name(array $record, string $prefix): string
{
    return trim(implode(' ', array_filter([
        $record[$prefix . '_first_name'] ?? '',
        $record[$prefix . '_middle_name'] ?? '',
        $record[$prefix . '_last_name'] ?? '',
    ], static fn($value) => trim((string)$value) !== '')));
}

function crf_3a_age_at_marriage(array $record, string $prefix): string
{
    $dob = trim((string)($record[$prefix . '_date_of_birth'] ?? ''));
    $marriage = trim((string)($record['date_of_marriage'] ?? ''));
    if ($dob === '' || $marriage === '') return '';
    try {
        $birthDate = new DateTimeImmutable($dob);
        $marriageDate = new DateTimeImmutable($marriage);
        if ($birthDate > $marriageDate) return '';
        $age = $birthDate->diff($marriageDate)->y;
        return $age . ' ' . ($age === 1 ? 'year' : 'years');
    } catch (Throwable $e) {
        return '';
    }
}

function crf_3a_date(array $record, string $field): string
{
    return crf_1a_date_value($record, $field);
}

function crf_3a_record_values(array $record, array $inputs = []): array
{
    $pick = static function (string $key) use ($inputs): string {
        return trim((string)($inputs[$key] ?? ''));
    };
    $recordValue = static function (array $keys) use ($record): string {
        foreach ($keys as $key) {
            $value = trim((string)($record[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return '';
    };
    $pickOrRecord = static function (string $inputKey, array $recordKeys) use ($pick, $recordValue): string {
        $inputValue = $pick($inputKey);
        return $inputValue !== '' ? $inputValue : $recordValue($recordKeys);
    };
    $husbandDob = crf_3a_date($record, 'husband_date_of_birth');
    $wifeDob = crf_3a_date($record, 'wife_date_of_birth');
    $husbandAge = crf_3a_age_at_marriage($record, 'husband');
    $wifeAge = crf_3a_age_at_marriage($record, 'wife');

    return [
        'registry_no' => format_registry_number($record, true),
        'date_of_registration' => crf_3a_date($record, 'date_of_registration'),
        'husband_name' => crf_3a_full_name($record, 'husband'),
        'husband_date_of_birth' => $husbandDob,
        'husband_age' => $husbandAge,
        'husband_date_of_birth_age' => $husbandDob !== '' && $husbandAge !== '' ? $husbandDob . ' / ' . $husbandAge : $husbandDob,
        // The marriage record stores these fields as citizenship. CRF 3A's
        // issuance schema historically calls the same values nationality.
        'husband_nationality' => $pickOrRecord('husband_nationality', ['husband_citizenship', 'husband_nationality']),
        'husband_civil_status' => $pickOrRecord('husband_civil_status', ['husband_civil_status']),
        'husband_mother_name' => $pick('husband_mother_name') !== '' ? $pick('husband_mother_name') : trim((string)($record['husband_mother_name'] ?? '')),
        'husband_mother_nationality' => $pickOrRecord('husband_mother_nationality', ['husband_mother_citizenship', 'husband_mother_nationality']),
        'husband_father_name' => $pick('husband_father_name') !== '' ? $pick('husband_father_name') : trim((string)($record['husband_father_name'] ?? '')),
        'husband_father_nationality' => $pickOrRecord('husband_father_nationality', ['husband_father_citizenship', 'husband_father_nationality']),
        'wife_name' => crf_3a_full_name($record, 'wife'),
        'wife_date_of_birth' => $wifeDob,
        'wife_age' => $wifeAge,
        'wife_date_of_birth_age' => $wifeDob !== '' && $wifeAge !== '' ? $wifeDob . ' / ' . $wifeAge : $wifeDob,
        'wife_nationality' => $pickOrRecord('wife_nationality', ['wife_citizenship', 'wife_nationality']),
        'wife_civil_status' => $pickOrRecord('wife_civil_status', ['wife_civil_status']),
        'wife_mother_name' => $pick('wife_mother_name') !== '' ? $pick('wife_mother_name') : trim((string)($record['wife_mother_name'] ?? '')),
        'wife_mother_nationality' => $pickOrRecord('wife_mother_nationality', ['wife_mother_citizenship', 'wife_mother_nationality']),
        'wife_father_name' => $pick('wife_father_name') !== '' ? $pick('wife_father_name') : trim((string)($record['wife_father_name'] ?? '')),
        'wife_father_nationality' => $pickOrRecord('wife_father_nationality', ['wife_father_citizenship', 'wife_father_nationality']),
        'date_of_marriage' => crf_3a_date($record, 'date_of_marriage'),
        'place_of_marriage' => trim((string)($record['place_of_marriage'] ?? '')),
    ];
}

function crf_3a_output_relative_path(string $year, string $lastName, string $crfNumber): string
{
    $safeLastName = function_exists('folder_safe_last_name')
        ? folder_safe_last_name($lastName)
        : strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $lastName) ?: 'UNKNOWN');
    return 'crf_3a/' . $year . '/' . (trim($safeLastName, '_') ?: 'UNKNOWN') . '/' . $crfNumber . '.pdf';
}

function crf_3a_preview_style(): string
{
    return <<<'CSS'
.crf1a-document{width:215.9mm;height:330.2mm;min-height:330.2mm;margin:0 auto;padding:0;position:relative;overflow:hidden;background:#fff;color:#111;font-family:"Courier New",Courier,monospace;font-size:9.5pt;}
.crf1a-document *{box-sizing:border-box;}
.crf1a-document .crf-manual-entry{font-weight:700;text-transform:uppercase;}
.crf1a-document .doc-header{position:absolute;top:5mm;left:14mm;right:12mm;height:30mm;display:flex;align-items:center;gap:4mm;}
.crf1a-document .doc-logo{display:flex;align-items:center;justify-content:center;}
.crf1a-document .doc-logo-seal{width:27mm;height:27mm;flex:0 0 27mm;}
.crf1a-document .doc-logo-baggao{width:27mm;height:27mm;flex:0 0 27mm;}
.crf1a-document .doc-logo-pilipinas{width:34mm;height:28mm;flex:0 0 34mm;}
.crf1a-document .doc-logo img{width:100%;height:100%;object-fit:contain;}.crf1a-document .doc-logo-baggao img{transform:scale(1.35);}.crf1a-document .doc-logo-pilipinas img{transform:scale(1.2);}
.crf1a-document .doc-header-copy{flex:1;text-align:left;line-height:1.05;}
.crf1a-document .doc-republic,.crf1a-document .doc-province{font-size:9pt;}
.crf1a-document .doc-municipality{font-family:"Times New Roman",serif;font-size:13.5pt;font-weight:700;letter-spacing:.01em;}
.crf1a-document .doc-office{font-family:"Times New Roman",serif;font-size:12pt;font-weight:700;letter-spacing:.01em;}
.crf1a-document .doc-address{font-size:7.5pt;margin-top:1mm;}
.crf1a-document .doc-header-right{width:34mm;flex:0 0 34mm;height:30mm;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.crf1a-document .doc-meta{width:100%;text-align:right;font-family:Arial,sans-serif;font-size:5.5pt;color:#334155;margin-top:1mm;}
.crf1a-document .doc-meta strong{display:inline;font-family:"Courier New",monospace;font-size:5.5pt;color:#111;margin-left:1mm;}
.crf1a-document .doc-rule{position:absolute;top:38mm;left:12mm;right:12mm;border-top:1px solid #9c2020;margin:0;}
.crf1a-document .doc-title{position:absolute;top:43mm;left:21mm;margin:0;font-size:12pt;font-weight:400;}
.crf1a-document .doc-subtitle{position:absolute;top:49mm;left:21mm;margin:0;font-size:9pt;}
.crf1a-document .doc-date{position:absolute;top:56mm;left:21mm;right:35mm;text-align:right;margin:0;line-height:4mm;}
.crf1a-document .doc-intro{position:absolute;top:66mm;left:21mm;right:21mm;margin:0;line-height:1.25;text-align:left;}
.crf1a-document .doc-intro-statement{margin-top:7mm;text-align:center;}
.crf1a-document .doc-short-line{display:inline-block;width:12mm;min-height:3.8mm;border-bottom:1px solid #222;vertical-align:bottom;text-align:center;white-space:nowrap;overflow:hidden;}
.crf1a-document .doc-requester-line{display:inline-block;width:62mm;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;text-overflow:clip;vertical-align:bottom;}
.crf1a-document .doc-payment{position:absolute;top:219mm;left:21mm;margin:0;width:90mm;}
.crf1a-document .doc-payment-row{display:flex;min-height:4.6mm;line-height:1.05;}
.crf1a-document .doc-payment-label{width:35mm;}
.crf1a-document .doc-payment-colon{width:5mm;}
.crf1a-document .doc-payment-value{flex:1;}
.crf1a-document .doc-payment .doc-line{display:inline-block;width:38mm;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom;}
.crf1a-document .doc-note{position:absolute;left:21mm;top:243mm;width:168mm;margin:0;font-size:8pt;line-height:1.1;}
.crf1a-document .crf3a-details{position:absolute;top:88mm;left:21mm;right:21mm;width:auto;margin:0;font-size:8.4pt;}
.crf1a-document .crf3a-details-table{width:100%;border-collapse:collapse;table-layout:fixed;}
.crf1a-document .crf3a-details-table th,.crf1a-document .crf3a-details-table td{padding:0;vertical-align:baseline;line-height:1.05;white-space:nowrap;}
.crf1a-document .crf3a-details-table th{height:8mm;font-size:10pt;text-align:center;font-weight:400;}
.crf1a-document .crf3a-details-table .crf3a-label{width:43mm;text-align:left;}
.crf1a-document .crf3a-details-table .crf3a-party{width:61mm;text-align:left;}
.crf1a-document .crf3a-details-table .crf3a-party-line{display:inline-block;width:56mm;height:4.4mm;line-height:3.25mm;padding-top:.9mm;border-bottom:1px solid #222;overflow:hidden;white-space:nowrap;vertical-align:bottom;}
.crf1a-document .crf3a-details-table .crf3a-shared-line{display:inline-block;width:117mm;height:4.4mm;line-height:3.25mm;padding-top:.9mm;border-bottom:1px solid #222;overflow:hidden;white-space:nowrap;vertical-align:bottom;text-align:center;}
.crf1a-document .crf3a-details-table .crf3a-single-line{display:inline-block;width:45mm;height:4.4mm;line-height:3.25mm;padding-top:.9mm;border-bottom:1px solid #222;overflow:hidden;white-space:nowrap;vertical-align:bottom;}
.crf1a-document .crf3a-certification{position:absolute;top:157mm;left:21mm;right:21mm;line-height:1.3;}
.crf1a-document .crf3a-signature{position:absolute;top:177mm;right:30mm;width:62mm;text-align:center;font-size:9pt;}
.crf1a-document .crf3a-signature strong{display:block;font-family:Arial,sans-serif;font-size:9.5pt;}
.crf1a-document .crf3a-verified{position:absolute;top:199mm;left:21mm;width:120mm;}
.crf1a-document .crf3a-verified-heading{display:flex;align-items:flex-end;width:105mm;}
.crf1a-document .crf3a-verified-heading>span:first-child{width:32mm;flex:0 0 32mm;}
.crf1a-document .crf3a-verified-line{display:inline-block;width:62mm;height:5mm;border-bottom:1px solid #222;overflow:hidden;white-space:nowrap;}
.crf1a-document .crf3a-verified-position{margin-left:32mm;width:62mm;min-height:4mm;text-align:left;}
.crf1a-document .doc-remarks{position:absolute;left:21mm;right:21mm;margin:0;color:#111;font:10.5pt/1.25 Arial,sans-serif;overflow-wrap:anywhere;word-break:break-word;}
.crf1a-document .doc-remarks-heading{margin:0 0 2mm;font:700 9.5pt "Courier New",monospace;text-transform:uppercase;}
.crf1a-document .doc-remarks-content{white-space:normal;overflow-wrap:anywhere;word-break:break-word;}
.crf1a-document .doc-remarks-content p{margin:0 0 1.5mm;}.crf1a-document .doc-remarks-content p:last-child{margin-bottom:0;}
.crf1a-document .doc-continuation-heading{position:absolute;top:10mm;left:21mm;right:21mm;font-size:11pt;}
.crf1a-document .doc-continuation-id{float:right;font-size:8pt;}
CSS;
}

function crf_3a_render_document_html(array $record, array $inputs, string $crfNumber): string
{
    $inputs['remarks_html'] = crf_1a_sanitize_remarks_html($inputs['remarks_html'] ?? '');
    $remarksPages = crf_1a_remarks_page_plan($inputs['remarks_html']);
    $values = crf_3a_record_values($record, $inputs);
    if (crf_1a_edge_path() === null) {
        $fallbackInputs = $inputs;
        $fallbackInputs['certified_by_name'] = $inputs['verified_by_name'] ?? '';
        $fallbackInputs['certified_by_position'] = $inputs['verified_by_position'] ?? '';
        return crf_1a_render_table_pdf_html(
            crf_3a_config(),
            'Civil Registry Form No. 3A',
            '(Marriage-Available)',
            'marriage',
            'Marriages',
            [
                'Registry Number' => $values['registry_no'],
                'Husband' => $values['husband_name'],
                'Wife' => $values['wife_name'],
                'Husband Date of Birth / Age' => $values['husband_date_of_birth_age'],
                'Wife Date of Birth / Age' => $values['wife_date_of_birth_age'],
                'Date of Marriage' => $values['date_of_marriage'],
                'Place of Marriage' => $values['place_of_marriage'],
                'Date of Registration' => $values['date_of_registration'],
            ],
            $fallbackInputs,
            $crfNumber
        );
    }

    $cfg = crf_3a_config();
    $e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    $line = static function ($value, bool $manual = true) use ($e): string {
        $value = trim((string)($value ?? ''));
        $display = $manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;');
        return '<span class="crf3a-party-line">' . $display . '</span>';
    };
    $row = static function (string $label, $husband, $wife, bool $manualHusband = true, bool $manualWife = true) use ($e, $line): string {
        return '<tr><td class="crf3a-label">' . $e($label) . '</td><td class="crf3a-party">' . $line($husband, $manualHusband) . '</td><td class="crf3a-party">' . $line($wife, $manualWife) . '</td></tr>';
    };
    $sharedRow = static function (string $label, $value) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<tr><td class="crf3a-label">' . $e($label) . '</td><td colspan="2"><span class="crf3a-shared-line">' . ($value !== '' ? crf_1a_manual_entry_html($value) : '&nbsp;') . '</span></td></tr>';
    };
    $date = strtotime((string)($inputs['issue_date'] ?? date('Y-m-d')));
    $issueDateText = $date === false ? crf_1a_manual_entry_html($inputs['issue_date'] ?? '') : crf_1a_manual_entry_html(date('F j, Y', $date));
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="doc-requester-line">' . crf_1a_manual_entry_html($requester) . '</span>';
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $remarksTop = 173;
    $remarksHeight = count($remarksPages) === 1 ? crf_1a_remarks_height_mm($remarksPages[0]) : 0;
    $signatureTop = count($remarksPages) === 1 ? max(177, $remarksTop + $remarksHeight + 10) : 177;
    $remarksBlock = static function (string $html, float $top, bool $continued = false): string {
        if ($html === '') return '';
        return '<div class="doc-remarks" style="top:' . $top . 'mm"><div class="doc-remarks-heading">'
            . ($continued ? 'REMARKS (CONTINUED)' : 'REMARKS') . '</div><div class="doc-remarks-content">' . $html . '</div></div>';
    };
    $closing = static function (array $positions) use ($inputs, $e, $amount, $crfNumber): string {
        return '<div class="crf3a-signature" style="top:' . $positions['signature'] . 'mm"><strong>' . crf_1a_manual_entry_html($inputs['mcr_full_name'] ?? '') . '</strong><div>' . crf_1a_manual_entry_html($inputs['mcr_title'] ?? '') . '</div></div>'
            . '<div class="crf3a-verified" style="top:' . $positions['verified'] . 'mm"><div class="crf3a-verified-heading"><span>VERIFIED BY:</span><span class="crf3a-verified-line">' . crf_1a_manual_entry_html($inputs['verified_by_name'] ?? '') . '</span></div><div class="crf3a-verified-position">' . crf_1a_manual_entry_html($inputs['verified_by_position'] ?? '') . '</div></div>'
            . '<div class="doc-payment" style="top:' . $positions['payment'] . 'mm"><div class="doc-payment-row"><span class="doc-payment-label">Amount paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">' . crf_1a_manual_entry_html($amount) . '</span></span></div><div class="doc-payment-row"><span class="doc-payment-label">O.R. Number</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">' . crf_1a_manual_entry_html($inputs['or_number'] ?? '') . '</span></span></div><div class="doc-payment-row"><span class="doc-payment-label">Date paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">' . crf_1a_manual_entry_html($inputs['date_paid'] ?? '') . '</span></span></div></div>'
            . '<div class="doc-note" style="top:' . $positions['note'] . 'mm"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div>';
    };
    $mainClosing = count($remarksPages) > 1 ? '' : $closing(['signature' => $signatureTop, 'verified' => $signatureTop + 22, 'payment' => $signatureTop + 42, 'note' => $signatureTop + 66]);
    $logo = static function (string $path, string $alt, string $size) use ($e): string {
        $uri = crf_1a_asset_data_uri($path);
        return $uri !== '' ? '<img src="' . $uri . '" alt="' . $e($alt) . '" style="display:block;width:' . $size . ';height:' . $size . ';object-fit:contain;">' : '';
    };
    $officeName = trim((string)$cfg['office_name']);
    $officeHtml = $officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR' ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR' : $e($officeName);
    $css = '<style>@page{size:215.9mm 330.2mm;margin:0}html,body{width:215.9mm;min-height:330.2mm;margin:0;padding:0;background:#fff}body{overflow:visible}.crf1a-document{page-break-after:always;break-after:page}.crf1a-document:last-child{page-break-after:auto;break-after:auto}' . crf_3a_preview_style() . '</style>';
    $html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body><div class="crf1a-document">'
        . '<div class="doc-header"><div class="doc-logo doc-logo-seal">' . $logo($cfg['logo_seal'], 'Baggao seal', '27mm') . '</div><div class="doc-logo doc-logo-baggao">' . $logo($cfg['logo_baggao'], 'Baggao reference logo', '27mm') . '</div><div class="doc-header-copy"><div class="doc-republic">Republic of the Philippines</div><div class="doc-province">Province of ' . $e($cfg['province']) . '</div><div class="doc-municipality">MUNICIPALITY OF ' . $e($cfg['municipality']) . '</div><div class="doc-office">' . $officeHtml . '</div><div class="doc-address">' . $e($cfg['address']) . '</div></div><div class="doc-header-right"><div class="doc-logo doc-logo-pilipinas">' . $logo($cfg['logo_pilipinas'], 'Bagong Pilipinas', '28mm') . '</div><div class="doc-meta">CRF ID<strong>' . $e($crfNumber) . '</strong></div></div></div>'
        . '<div class="doc-rule"></div><div class="doc-title">Civil Registry Form No. 3A</div><div class="doc-subtitle">(Marriage-Available)</div><div class="doc-date">Date: ' . $issueDateText . '</div>'
        . '<div class="doc-intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="doc-intro-statement">We certify that, among others, the following facts of Marriage<br>appear in our Register of Marriages on page <span class="doc-short-line">' . crf_1a_manual_entry_html($inputs['page_number'] ?? '') . '</span> Book number <span class="doc-short-line">' . crf_1a_manual_entry_html($inputs['book_number'] ?? '') . '.</span></div></div>'
        . '<div class="crf3a-details"><table class="crf3a-details-table"><thead><tr><th class="crf3a-label"></th><th class="crf3a-party">HUSBAND</th><th class="crf3a-party">WIFE</th></tr></thead><tbody>'
        . $row('Name', $values['husband_name'], $values['wife_name'])
        . $row('Date of Birth / Age', $values['husband_date_of_birth_age'], $values['wife_date_of_birth_age'])
        . $row('Citizenship', $values['husband_nationality'], $values['wife_nationality'], true, true)
        . $row('Civil Status', $values['husband_civil_status'], $values['wife_civil_status'], true, true)
        . $row('Name of Mother', $values['husband_mother_name'], $values['wife_mother_name'], true, true)
        . $row('Nationality', $values['husband_mother_nationality'], $values['wife_mother_nationality'], true, true)
        . $row('Name of Father', $values['husband_father_name'], $values['wife_father_name'], true, true)
        . $row('Nationality', $values['husband_father_nationality'], $values['wife_father_nationality'], true, true)
        . $sharedRow('Civil Registry Number', $values['registry_no'])
        . $sharedRow('Date of Marriage', $values['date_of_marriage'])
        . $sharedRow('Place of Marriage', $values['place_of_marriage'])
        . $sharedRow('Date of Registration', $values['date_of_registration'])
        . '</tbody></table></div>'
        . '<div class="crf3a-certification">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</div>'
        . $remarksBlock($remarksPages[0] ?? '', $remarksTop) . $mainClosing
        . '</div>';
    if (count($remarksPages) > 1) {
        foreach (array_slice($remarksPages, 1) as $index => $chunk) {
            $height = crf_1a_remarks_height_mm($chunk);
            $finalPage = $index === count($remarksPages) - 2;
            $footerTop = 35 + $height + 12;
            $html .= '<div class="crf1a-document doc-continuation"><div class="doc-continuation-heading">Civil Registry Form No. 3A - continuation<span class="doc-continuation-id">CRF ID ' . $e($crfNumber) . '</span></div>'
                . $remarksBlock($chunk, 35, true)
                . ($finalPage ? $closing(['signature' => $footerTop, 'verified' => $footerTop + 22, 'payment' => $footerTop + 42, 'note' => $footerTop + 66]) : '')
                . '</div>';
        }
    }
    return $html . '</body></html>';
}

function crf_3a_render_pdf(string $html, string $outputPdf, ?string &$error = null): bool
{
    return crf_1a_render_pdf($html, $outputPdf, $error, true);
}
