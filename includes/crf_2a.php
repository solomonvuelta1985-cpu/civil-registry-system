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
        if ($formatted === 'N/A' && $field === 'date_of_registration') return 'No Entry';
        return $formatted === 'N/A' ? '' : $formatted;
    }
    if (!$date) return $field === 'date_of_registration' ? 'No Entry' : '';
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
        'registry_no' => format_registry_number($record, true),
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

/**
 * Split cause-of-death text into printable lines without dropping spaces or
 * explicit line breaks. The CRF uses Courier New at 9pt and a 59mm value
 * column, which holds about 30 Latin Courier columns per line.
 */
function crf_2a_wrap_cause_lines(string $cause): array
{
    $cause = str_replace(["\r\n", "\r"], "\n", $cause);
    $lines = [];
    foreach (explode("\n", $cause) as $paragraph) {
        if ($paragraph === '') {
            $lines[] = '';
            continue;
        }
        while (mb_strwidth($paragraph, 'UTF-8') > 30) {
            $characters = preg_split('//u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $width = 0;
            $cut = 0;
            $spaceCut = 0;
            foreach ($characters as $character) {
                $characterWidth = mb_strwidth($character, 'UTF-8');
                if ($width + $characterWidth > 30) break;
                $width += $characterWidth;
                $cut += mb_strlen($character, 'UTF-8');
                if ($character === ' ') $spaceCut = $cut;
            }
            $cut = $spaceCut > 0 ? $spaceCut : max(1, $cut);
            $lines[] = mb_substr($paragraph, 0, $cut, 'UTF-8');
            $paragraph = mb_substr($paragraph, $cut, null, 'UTF-8');
        }
        $lines[] = $paragraph;
    }
    return $lines ?: [''];
}

/**
 * Keep the usual issuance on one sheet. Longer entries get cause-only
 * continuation sheets, with the certification and payment area on the final
 * sheet so no content is overprinted or clipped.
 */
function crf_2a_cause_page_plan(array $lines): array
{
    if (count($lines) <= 10) {
        return ['first_page_lines' => $lines, 'continuation_pages' => []];
    }

    $firstPage = array_slice($lines, 0, 10);
    $remaining = array_slice($lines, count($firstPage));
    $pages = [];
    while (count($remaining) > 30) {
        $take = min(45, count($remaining) - 30);
        $pages[] = ['lines' => array_slice($remaining, 0, $take), 'include_footer' => false];
        $remaining = array_slice($remaining, $take);
    }
    $pages[] = ['lines' => $remaining, 'include_footer' => true];

    return ['first_page_lines' => $firstPage, 'continuation_pages' => $pages];
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
    $inputs['remarks_html'] = crf_1a_sanitize_remarks_html($inputs['remarks_html'] ?? '');
    $values = crf_2a_record_values($record);
    $displayCause = function_exists('mb_strtoupper')
        ? mb_strtoupper($values['cause_of_death'], 'UTF-8')
        : strtoupper($values['cause_of_death']);
    $causeLines = crf_2a_wrap_cause_lines($displayCause);
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
        $crfNumber,
        ['Cause of Death' => $causeLines]
    );

    return crf_2a_render_edge_document_html($record, $inputs, $crfNumber, $causeLines);
}

function crf_2a_render_edge_document_html(array $record, array $inputs, string $crfNumber, array $causeLines): string
{
    $inputs['remarks_html'] = crf_1a_sanitize_remarks_html($inputs['remarks_html'] ?? '');
    $cfg = crf_2a_config();
    $values = crf_2a_record_values($record);
    $plan = crf_2a_cause_page_plan($causeLines);
    $e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    $line = static function ($value, bool $manual = false) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="filled-line">' . ($manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;')) . '</span>';
    };
    $shortLine = static function ($value, bool $manual = false) use ($e): string {
        $text = trim((string)($value ?? ''));
        return '<span class="short-line">' . ($manual ? crf_1a_manual_entry_html($text) : ($text !== '' ? $e($text) : '&nbsp;')) . '</span>';
    };
    $field = static function (string $label, $value, bool $manual = true) use ($line, $e): string {
        return '<div class="data-row"><span class="data-label">' . $e($label) . '</span><span class="data-colon">:</span><span class="data-value">' . $line($value, $manual) . '</span></div>';
    };
    $causeBlock = static function (array $lines, string $label = 'Cause of Death') use ($e): string {
        $lineHtml = '';
        foreach ($lines as $causeLine) {
            $lineHtml .= '<div class="cause-line">' . crf_1a_manual_entry_html($causeLine) . '</div>';
        }
        return '<div class="cause-row"><span class="cause-label">' . $e($label) . '</span><span class="cause-colon">:</span><div class="cause-lines">' . $lineHtml . '</div></div>';
    };
    $logo = static function (string $path, string $alt, string $size) use ($e): string {
        $uri = crf_1a_asset_data_uri($path);
        return $uri !== '' ? '<img src="' . $uri . '" alt="' . $e($alt) . '" style="display:block;width:' . $size . ';height:' . $size . ';object-fit:contain;">' : '';
    };
    $officeName = trim((string)$cfg['office_name']);
    $officeHtml = $officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR' ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR' : $e($officeName);
    $issueTimestamp = strtotime((string)($inputs['issue_date'] ?? date('Y-m-d')));
    $issueDateText = $issueTimestamp === false ? crf_1a_manual_entry_html($inputs['issue_date'] ?? '') : crf_1a_manual_entry_html(date('F j, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="requester-line">' . crf_1a_manual_entry_html($requester) . '</span>';

    $certification = static fn(float $top): string => '<div class="certification" style="top:' . $top . 'mm">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</div>';
    $closing = static function (array $positions) use ($inputs, $crfNumber, $e, $line, $amount): string {
        return '<div class="signature" style="top:' . $positions['signature'] . 'mm"><strong>' . crf_1a_manual_entry_html($inputs['mcr_full_name'] ?? '') . '</strong><div>' . crf_1a_manual_entry_html($inputs['mcr_title'] ?? '') . '</div></div>'
            . '<div class="certified" style="top:' . $positions['certified'] . 'mm"><div class="certified-heading"><span>Certified by:</span><span class="certified-line">' . crf_1a_manual_entry_html($inputs['certified_by_name'] ?? '') . '</span></div><div class="certified-position">' . crf_1a_manual_entry_html($inputs['certified_by_position'] ?? '') . '</div></div>'
            . '<div class="payment" style="top:' . $positions['payment'] . 'mm"><div class="payment-row"><span class="payment-label">Amount paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($amount, true) . '</span></div><div class="payment-row"><span class="payment-label">O.R. Number</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['or_number'] ?? '', true) . '</span></div><div class="payment-row"><span class="payment-label">Date paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['date_paid'] ?? '', true) . '</span></div></div>'
            . '<div class="note" style="top:' . $positions['note'] . 'mm"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div>';
    };
    $remarksBlock = static function (string $html, float $top, bool $continued = false): string {
        if ($html === '') return '';
        return '<div class="doc-remarks" style="top:' . $top . 'mm"><div class="doc-remarks-heading">' . ($continued ? 'REMARKS (CONTINUED)' : 'REMARKS') . '</div><div class="doc-remarks-content">' . $html . '</div></div>';
    };
    $head = '<div class="header"><div class="logo logo-seal">' . $logo($cfg['logo_seal'], 'Baggao seal', '27mm') . '</div><div class="logo logo-baggao">' . $logo($cfg['logo_baggao'], 'Baggao reference logo', '27mm') . '</div><div class="copy"><div class="small">Republic of the Philippines</div><div class="small">Province of ' . $e($cfg['province']) . '</div><div class="municipality">MUNICIPALITY OF ' . $e($cfg['municipality']) . '</div><div class="office">' . $officeHtml . '</div><div class="address">' . $e($cfg['address']) . '</div></div><div class="right">' . $logo($cfg['logo_pilipinas'], 'Bagong Pilipinas', '28mm') . '<div class="meta">CRF ID<strong>' . $e($crfNumber) . '</strong></div></div></div>';
    $top = '<div class="rule"></div><div class="title">Civil Registry Form No. 2A</div><div class="subtitle">(Death-Available)</div><div class="date">Date: ' . $issueDateText . '</div><div class="intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="intro-statement">We certify that, among others, the following facts of death<br>appear in our Register of Deaths on page ' . $shortLine($inputs['page_number'] ?? '', true) . ' Book number ' . $shortLine($inputs['book_number'] ?? '', true) . '.</div></div>';
    $regularFields = $field('Registry Number', $values['registry_no'])
        . $field('Date of Registration', $values['date_of_registration'])
        . $field('Name of the Deceased', $values['name_of_deceased'])
        . $field('Sex', $values['sex'])
        . $field('Age', $values['age'])
        . $field('Civil Status', $values['civil_status'], true)
        . $field('Citizenship', $values['citizenship'], true)
        . $field('Date of Death', $values['date_of_death'])
        . $field('Place of Death', $values['place_of_death']);

    $css = '<style>@page{size:215.9mm 330.2mm;margin:0}*{box-sizing:border-box}html,body{width:215.9mm;margin:0;padding:0;background:#fff}body{font-family:"Courier New",monospace;color:#111;font-size:9.5pt}.sheet{width:215.9mm;height:330.2mm;max-height:330.2mm;position:relative;overflow:hidden;page-break-after:always;break-after:page}.sheet:last-child{page-break-after:auto;break-after:auto}.header{position:absolute;top:5mm;left:14mm;right:12mm;height:30mm}.logo{position:absolute;display:block;width:27mm;height:27mm}.logo-seal{left:0;top:1.5mm}.logo-baggao{left:31mm;top:1.5mm}.logo img{width:100%;height:100%;object-fit:contain}.logo-baggao img{transform:scale(1.35)}.right>img{transform:scale(1.2)}.copy{position:absolute;left:62mm;top:4.5mm;width:84mm;height:24mm;line-height:1.05}.copy .small{font-size:9pt}.copy .municipality{font-family:"Times New Roman",serif;font-size:13.5pt;font-weight:700}.copy .office{font-family:"Times New Roman",serif;font-size:12pt;font-weight:700}.copy .address{font-size:7.5pt;margin-top:1mm}.right{position:absolute;top:0;right:0;width:34mm;height:30mm}.right>.logo{left:0;top:0;width:34mm;height:28mm}.meta{position:absolute;top:28mm;left:0;right:0;width:100%;text-align:right;font-family:Arial,sans-serif;font-size:5.5pt;color:#334155}.meta strong{display:inline;font-family:"Courier New",monospace;font-size:5.5pt;color:#111;margin-left:1mm}.rule{position:absolute;top:38mm;left:12mm;right:12mm;border-top:1px solid #9c2020}.title{position:absolute;top:43mm;left:21mm;font-size:12pt}.subtitle{position:absolute;top:49mm;left:21mm;font-size:9pt}.date{position:absolute;top:56mm;left:21mm;right:35mm;text-align:right;line-height:4mm}.intro{position:absolute;top:66mm;left:21mm;right:21mm;line-height:1.25}.intro-statement{margin-top:7mm;text-align:center}.short-line{display:inline-block;width:12mm;min-height:3.8mm;border-bottom:1px solid #222;text-align:center;vertical-align:bottom}.grid{position:absolute;top:90mm;left:34mm;right:35mm}.data-row,.cause-row{display:table;table-layout:fixed;width:100%;min-height:4.6mm;line-height:1.05}.data-label,.cause-label{display:table-cell;width:78mm;white-space:nowrap;vertical-align:top}.data-colon,.cause-colon{display:table-cell;width:4mm;vertical-align:top}.data-value,.cause-lines{display:table-cell;width:auto;vertical-align:top}.filled-line{display:inline-block;width:100%;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}.cause-row{font-size:9pt;line-height:1.05}.cause-label,.cause-colon{height:4.6mm;line-height:4.6mm}.cause-line{display:block;width:100%;height:4.6mm;line-height:4.6mm;border-bottom:1px solid #222;white-space:pre;overflow:hidden}.continuation-heading{position:absolute;top:10mm;left:21mm;right:21mm;font-size:11pt}.continuation-id{float:right;font-size:8pt}.continuation-cause{position:absolute;top:35mm;left:34mm;right:35mm}.certification{position:absolute;left:29mm;right:20mm;line-height:1.3}.requester-line{display:inline-block;width:62mm;min-height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}.signature{position:absolute;right:30mm;width:62mm;text-align:center;font-size:9pt}.signature strong{display:block;font-family:Arial,sans-serif;font-size:9.5pt}.certified{position:absolute;left:21mm;width:105mm}.certified-heading{display:table;table-layout:fixed;width:95mm}.certified-heading>span:first-child{display:table-cell;width:32mm;vertical-align:bottom}.certified-line{display:table-cell;width:62mm;height:5mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden}.certified-position{margin-left:32mm;width:62mm;text-align:left;min-height:4mm}.payment{position:absolute;left:21mm;width:90mm}.payment-row{display:table;table-layout:fixed;width:100%;min-height:4.6mm;line-height:1.05}.payment-label{display:table-cell;width:35mm;vertical-align:baseline}.payment-colon{display:table-cell;width:5mm;vertical-align:baseline}.payment-value{display:table-cell;width:auto;vertical-align:baseline}.payment .filled-line{width:38mm}.note{position:absolute;left:21mm;width:168mm;font-size:8pt;line-height:1.1}</style>';

    $css .= '<style>@page{size:215.9mm 330.2mm;margin:0}html,body{width:215.9mm;min-height:330.2mm;margin:0;padding:0;background:#fff}.sheet{width:215.9mm;height:330.2mm;max-height:330.2mm}.doc-remarks{position:absolute;left:21mm;right:21mm;margin:0;color:#111;font:10.5pt/1.25 Arial,sans-serif;overflow-wrap:anywhere;word-break:break-word}.doc-remarks-heading{margin:0 0 2mm;font:700 9.5pt "Courier New",monospace;text-transform:uppercase}.doc-remarks-content{white-space:normal;overflow-wrap:anywhere;word-break:break-word}.doc-remarks-content p{margin:0 0 1.5mm}.doc-remarks-content p:last-child{margin-bottom:0}.doc-continuation-heading{position:absolute;top:10mm;left:21mm;right:21mm;font-size:11pt}.doc-continuation-id{float:right;font-size:8pt}</style>';
    $mainLines = $plan['first_page_lines'];
    $mainShift = max(0, count($mainLines) - 1) * 4.6;
    if ($plan['continuation_pages'] === []) {
        $certificationTop = 154 + $mainShift;
        $remarksTop = $certificationTop + 15;
        $noteTop = 243 + $mainShift;
        $singleLimit = max(1, (int)floor(max(0, 330.2 - $noteTop - 14) / 4.5 * 82));
        $firstLimit = max($singleLimit, min(2300, (int)floor(max(0, 330.2 - $remarksTop - 12) / 4.5 * 82)));
    } else {
        $lastCauseLines = count($plan['continuation_pages'][count($plan['continuation_pages']) - 1]['lines']);
        $lastCauseFooterTop = 55 + $lastCauseLines * 4.6;
        $remarksTop = $lastCauseFooterTop + 15;
        $noteTop = $lastCauseFooterTop + 89;
        $singleLimit = max(1, (int)floor(max(0, 330.2 - $noteTop - 14) / 4.5 * 82));
        $firstLimit = max($singleLimit, min(2300, (int)floor(max(0, 330.2 - $remarksTop - 12) / 4.5 * 82)));
    }
    $remarksPages = crf_1a_remarks_page_plan($inputs['remarks_html'] ?? '', $singleLimit, $firstLimit, 3600);
    $main = '<div class="sheet"><div class="header">' . substr($head, strlen('<div class="header">')) . $top . '<div class="grid">' . $regularFields . $causeBlock($mainLines) . '</div>';
    if ($plan['continuation_pages'] === []) {
        if ($remarksPages === []) {
            $main .= $certification($certificationTop) . $closing(['signature' => 178 + $mainShift, 'certified' => 199 + $mainShift, 'payment' => 219 + $mainShift, 'note' => 243 + $mainShift]);
        } elseif (count($remarksPages) === 1) {
            $height = crf_1a_remarks_height_mm($remarksPages[0]);
            $signatureTop = max(178 + $mainShift, $remarksTop + $height + 10);
            $main .= $certification($certificationTop) . $remarksBlock($remarksPages[0], $remarksTop)
                . $closing(['signature' => $signatureTop, 'certified' => $signatureTop + 21, 'payment' => $signatureTop + 41, 'note' => $signatureTop + 65]);
        } else {
            $main .= $certification($certificationTop) . $remarksBlock($remarksPages[0], $remarksTop);
        }
    }
    $html = '<!doctype html><html><head><meta charset="utf-8">' . $css . '</head><body>' . $main . '</div>';
    foreach ($plan['continuation_pages'] as $continuation) {
        $chunk = $continuation['lines'];
        $page = '<div class="sheet continuation"><div class="continuation-heading">Civil Registry Form No. 2A - continuation<span class="continuation-id">CRF ID ' . $e($crfNumber) . '</span></div>';
        if ($chunk) $page .= '<div class="continuation-cause">' . $causeBlock($chunk, 'Cause of Death (continued)') . '</div>';
        if ($continuation['include_footer']) {
            $footerTop = 55 + (count($chunk) * 4.6);
            if ($remarksPages === []) {
                $page .= $certification($footerTop) . $closing(['signature' => $footerTop + 24, 'certified' => $footerTop + 45, 'payment' => $footerTop + 65, 'note' => $footerTop + 89]);
            } elseif (count($remarksPages) === 1) {
                $remarksTop = $footerTop + 15;
                $height = crf_1a_remarks_height_mm($remarksPages[0]);
                $signatureTop = max($footerTop + 24, $remarksTop + $height + 10);
                $page .= $certification($footerTop) . $remarksBlock($remarksPages[0], $remarksTop)
                    . $closing(['signature' => $signatureTop, 'certified' => $signatureTop + 21, 'payment' => $signatureTop + 41, 'note' => $signatureTop + 65]);
            } else {
                $page .= $certification($footerTop) . $remarksBlock($remarksPages[0], $footerTop + 15);
            }
        }
        $html .= $page . '</div>';
    }
    if (count($remarksPages) > 1) {
        foreach (array_slice($remarksPages, 1) as $index => $chunk) {
            $height = crf_1a_remarks_height_mm($chunk);
            $finalPage = $index === count($remarksPages) - 2;
            $footerTop = 35 + $height + 12;
            $html .= '<div class="sheet continuation"><div class="continuation-heading">Civil Registry Form No. 2A - continuation<span class="continuation-id">CRF ID ' . $e($crfNumber) . '</span></div>'
                . $remarksBlock($chunk, 35, true)
                . ($finalPage ? $closing(['signature' => $footerTop, 'certified' => $footerTop + 21, 'payment' => $footerTop + 41, 'note' => $footerTop + 65]) : '')
                . '</div>';
        }
    }
    return $html . '</body></html>';
}

function crf_2a_render_pdf(string $html, string $outputPdf, ?string &$error = null): bool
{
    return crf_1a_render_pdf($html, $outputPdf, $error, true);
}
