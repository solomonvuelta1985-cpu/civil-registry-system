<?php
require_once __DIR__ . '/branding.php';
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

/** Format a displayed CRF entry without changing stored values. */
function crf_1a_manual_entry_html($value): string
{
    $text = (string)($value ?? '');
    if (trim($text) === '') return '&nbsp;';
    $uppercase = function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
    return '<span class="crf-manual-entry" style="font-weight:700;text-transform:uppercase">' . htmlspecialchars($uppercase, ENT_QUOTES, 'UTF-8') . '</span>';
}

/** Keep only the formatting supported by the remarks editor. */
function crf_1a_sanitize_remarks_html($html): string
{
    if (!is_string($html) || trim($html) === '') return '';
    if (!class_exists(DOMDocument::class)) {
        $plain = trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return $plain === '' ? '' : nl2br(htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body><div id="remarks-root">' . $html . '</div></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="remarks-root"]')?->item(0);
    if (!$root) return '';

    $allowed = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'span', 'ol', 'ul', 'li', 'blockquote'];
    $discarded = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'video', 'audio', 'form', 'input', 'button'];
    $cleanStyle = static function (string $style): string {
        $fonts = ['Arial', 'Calibri', 'Cambria', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Tahoma'];
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) continue;
            $property = strtolower(trim(substr($declaration, 0, $colon)));
            $value = trim(substr($declaration, $colon + 1));
            if (in_array($property, ['color', 'background-color'], true) && preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?(?:[0-9a-f]{2})?$/i', $value)) {
                $safe[] = $property . ':' . strtolower($value);
            } elseif ($property === 'font-size' && preg_match('/^(\d+(?:\.\d+)?)pt$/i', $value, $match) && (float)$match[1] >= 6 && (float)$match[1] <= 48) {
                $safe[] = 'font-size:' . (float)$match[1] . 'pt';
            } elseif ($property === 'font-family') {
                $family = trim($value, " \t\n\r\0\x0B\"'");
                foreach ($fonts as $font) {
                    if (strcasecmp($family, $font) === 0) {
                        $safe[] = 'font-family:"' . $font . '"';
                        break;
                    }
                }
            }
        }
        return implode(';', $safe);
    };
    $copyChildren = static function (DOMNode $source, DOMNode $destination) use (&$copyChildren, $dom, $allowed, $discarded, $cleanStyle): void {
        foreach ($source->childNodes as $child) {
            if ($child instanceof DOMText) {
                $destination->appendChild($dom->createTextNode($child->nodeValue ?? ''));
                continue;
            }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, $discarded, true)) continue;
            if (!in_array($tag, $allowed, true)) {
                $copyChildren($child, $destination);
                continue;
            }
            $outputTag = $tag === 'div' ? 'p' : $tag;
            $element = $dom->createElement($outputTag);
            if ($child->hasAttribute('style')) {
                $style = $cleanStyle($child->getAttribute('style'));
                if ($style !== '') $element->setAttribute('style', $style);
            }
            $destination->appendChild($element);
            $copyChildren($child, $element);
        }
    };

    $cleanRoot = $dom->createElement('div');
    $copyChildren($root, $cleanRoot);
    $result = '';
    foreach ($cleanRoot->childNodes as $child) $result .= $dom->saveHTML($child);
    $plain = preg_replace('/[\s\x{00a0}\x{200b}]+/u', '', strip_tags($result)) ?? '';
    return $plain === '' ? '' : $result;
}

/** Estimate remarks page space in base-font character units. */
function crf_1a_remarks_weight(string $html): float
{
    $tokens = preg_split('~(<[^>]+>|[^<]+)~u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    $stack = [];
    $weight = 0.0;
    foreach ($tokens as $token) {
        if ($token[0] === '<') {
            $closing = preg_match('~^<\s*/~', $token) === 1;
            preg_match('~^<\s*/?\s*([a-z0-9]+)~i', $token, $match);
            $tag = strtolower($match[1] ?? '');
            if ($tag === 'br') { $weight += 82; continue; }
            if (in_array($tag, ['p', 'div', 'li', 'blockquote'], true)) $weight += 28;
            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['tag'] === $tag) { array_splice($stack, $i, 1); break; }
                }
            } elseif (!preg_match('~/>$~', $token)) {
                $size = preg_match('~font-size\s*:\s*(\d+(?:\.\d+)?)pt~i', $token, $sizeMatch) ? (float)$sizeMatch[1] : null;
                $stack[] = ['tag' => $tag, 'size' => $size];
            }
            continue;
        }
        $text = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $fontScale = 1.0;
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['size'] !== null) { $fontScale = max(0.6, $stack[$i]['size'] / 10.5); break; }
        }
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($characters as $character) $weight += ($character === "\n" || $character === "\r") ? 82 : $fontScale;
    }
    return $weight;
}

/** Split a safe remarks fragment while carrying inline formatting across page breaks. */
function crf_1a_split_remarks_html(string $html, int $firstLimit, int $continuationLimit = 3600): array
{
    $tokens = preg_split('~(<[^>]+>|[^<]+)~u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    $pages = [];
    $output = '';
    $units = 0.0;
    $limit = max(1, $firstLimit);
    $stack = [];
    $plainInPage = false;
    $closeStack = static function (array $stack): string { return implode('', array_map(static fn($item) => '</' . $item['tag'] . '>', array_reverse($stack))); };
    foreach ($tokens as $token) {
        if ($token[0] === '<') {
            $closing = preg_match('~^<\s*/~', $token) === 1;
            preg_match('~^<\s*/?\s*([a-z0-9]+)~i', $token, $match);
            $tag = strtolower($match[1] ?? '');
            $output .= $token;
            if ($tag === 'br') $units += 82;
            elseif (in_array($tag, ['p', 'div', 'li', 'blockquote'], true)) $units += 28;
            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['tag'] === $tag) { array_splice($stack, $i, 1); break; }
                }
            } elseif (!preg_match('~/>$~', $token)) {
                $size = preg_match('~font-size\s*:\s*(\d+(?:\.\d+)?)pt~i', $token, $sizeMatch) ? (float)$sizeMatch[1] : null;
                $stack[] = ['tag' => $tag, 'open' => $token, 'size' => $size];
            }
            continue;
        }
        $text = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $fontScale = 1.0;
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['size'] !== null) { $fontScale = max(0.6, $stack[$i]['size'] / 10.5); break; }
        }
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($characters as $character) {
            if ($units >= $limit && $plainInPage) {
                $pages[] = $output . $closeStack($stack);
                $output = implode('', array_map(static fn($item) => $item['open'], $stack));
                $units = 0.0;
                $limit = max(1, $continuationLimit);
                $plainInPage = false;
            }
            $output .= htmlspecialchars($character, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            $units += ($character === "\n" || $character === "\r") ? 82 : $fontScale;
            if (!preg_match('/[\s\x{00a0}]/u', $character)) $plainInPage = true;
        }
    }
    $last = $output . $closeStack($stack);
    if (trim(strip_tags($last)) !== '') $pages[] = $last;
    return $pages;
}

function crf_1a_remarks_page_plan($html, int $singleLimit = 1200, int $firstLimit = 2300, int $continuationLimit = 3600): array
{
    $safe = crf_1a_sanitize_remarks_html($html);
    if ($safe === '') return [];
    if (crf_1a_remarks_weight($safe) <= $singleLimit) return [$safe];
    return crf_1a_split_remarks_html($safe, $firstLimit, $continuationLimit);
}

function crf_1a_remarks_height_mm(string $html): float
{
    $weight = crf_1a_remarks_weight($html);
    return $weight > 0 ? max(4.5, ceil($weight / 82) * 4.5) : 0.0;
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

    $config = branding_apply_crf_logo_overrides([
        'office_name' => env('CRF1A_OFFICE_NAME', 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'),
        'municipality' => env('CRF1A_MUNICIPALITY', 'BAGGAO'),
        'province' => env('CRF1A_PROVINCE', 'CAGAYAN'),
        'address' => env('CRF1A_OFFICE_ADDRESS', 'Ground Floor, Executive Building, San Jose, Baggao, Cagayan'),
        'mcr_full_name' => env('CRF1A_MCR_FULL_NAME', 'ATANACIO G. TUNGPALAN'),
        'mcr_title' => env('CRF1A_MCR_TITLE', 'Municipal Civil Registrar'),
        'logo_seal' => env('CRF1A_LOGO_SEAL', 'assets/img/LOGO1.png'),
        'logo_baggao' => env('CRF1A_LOGO_BAGGAO', 'assets/img/CRF1A_BAGGAO_REFERENCE.png'),
        'logo_pilipinas' => env('CRF1A_LOGO_PILIPINAS', 'assets/img/CRF1A_BAGONG_PILIPINAS.png'),
    ]);

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

function crf_1a_full_date($value): string
{
    $date = trim((string)$value);
    if ($date === '') return '';
    $timestamp = strtotime($date);
    return $timestamp === false ? $date : date('F d, Y', $timestamp);
}

function crf_1a_date_value(array $record, string $field): string
{
    $format = (string)($record[$field . '_format'] ?? 'full');
    $date = $record[$field] ?? null;

    if ($format === 'full' && $date) {
        return crf_1a_full_date($date);
    }

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

    if (!$date) {
        return $field === 'date_of_registration' ? 'No Entry' : '';
    }
    $timestamp = strtotime((string)$date);
    return $timestamp === false ? (string)$date : date('F d, Y', $timestamp);
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
    $config = crf_1a_config();
    $municipality = trim((string)($record['municipality'] ?? ''));
    $province = trim((string)($record['province'] ?? ''));
    if ($municipality === '') $municipality = trim((string)($config['municipality'] ?? ''));
    if ($province === '') $province = trim((string)($config['province'] ?? ''));
    $place = array_values(array_unique(array_filter(
        [$municipality, $province],
        static fn(string $value): bool => $value !== ''
    )));

    return implode(', ', $place);
}

function crf_1a_record_values(array $record): array
{
    return [
        'registry_no' => format_registry_number($record, true),
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
    $inputs['remarks_html'] = crf_1a_sanitize_remarks_html($inputs['remarks_html'] ?? '');
    $remarksPages = crf_1a_remarks_page_plan($inputs['remarks_html']);
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
    $line = static function ($value, bool $manual = false) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="filled-line">' . ($manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;')) . '</span>';
    };
    $shortLine = static function ($value, bool $manual = false) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="short-line">' . ($manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;')) . '</span>';
    };
    $field = static function (string $label, $value, bool $manual = true) use ($line, $e): string {
        return '<div class="data-row"><span class="data-label">' . $e($label)
            . '</span><span class="data-colon">:</span><span class="data-value">'
            . $line($value, $manual) . '</span></div>';
    };
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="requester-line">' . crf_1a_manual_entry_html($requester) . '</span>';

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
    $issueDateText = $issueTimestamp === false ? $e($issueDate) : $e(date('F d, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $remarksTop = 170;
    $singleRemarksHeight = count($remarksPages) === 1 ? crf_1a_remarks_height_mm($remarksPages[0]) : 0;
    $signatureTop = count($remarksPages) === 1 ? max(178, $remarksTop + $singleRemarksHeight + 10) : 178;
    $remarksBlock = static function (string $html, float $top, bool $continued = false): string {
        if ($html === '') return '';
        return '<div class="doc-remarks" style="top:' . $top . 'mm"><div class="doc-remarks-heading">'
            . ($continued ? 'REMARKS (CONTINUED)' : 'REMARKS') . '</div><div class="doc-remarks-content">' . $html . '</div></div>';
    };
    $closing = static function (array $positions) use ($inputs, $cfg, $crfNumber, $e, $line, $amount): string {
        $registrarName = array_key_exists('mcr_full_name', $inputs) ? $inputs['mcr_full_name'] : $cfg['mcr_full_name'];
        $registrarTitle = array_key_exists('mcr_title', $inputs) ? $inputs['mcr_title'] : $cfg['mcr_title'];
        return '<div class="signature-block" style="top:' . $positions['signature'] . 'mm"><strong>' . crf_1a_manual_entry_html($registrarName) . '</strong><div>' . crf_1a_manual_entry_html($registrarTitle) . '</div></div>'
            . '<div class="certified-block" style="top:' . $positions['certified'] . 'mm"><div class="certified-heading"><span>Certified by:</span><span class="certified-line">' . crf_1a_manual_entry_html($inputs['certified_by_name'] ?? '') . '</span></div><div class="certified-position">' . crf_1a_manual_entry_html($inputs['certified_by_position'] ?? '') . '</div></div>'
            . '<div class="payment-block" style="top:' . $positions['payment'] . 'mm"><div class="payment-row"><span class="payment-label">Amount paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line($amount, true) . '</span></div><div class="payment-row"><span class="payment-label">O.R. Number</span><span class="payment-colon">:</span><span class="payment-value">' . $line($inputs['or_number'] ?? '', true) . '</span></div><div class="payment-row"><span class="payment-label">Date paid</span><span class="payment-colon">:</span><span class="payment-value">' . $line(crf_1a_full_date($inputs['date_paid'] ?? ''), true) . '</span></div></div>'
            . '<div class="note" style="top:' . $positions['note'] . 'mm"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div>';
    };
    $mainClosing = count($remarksPages) > 1 ? '' : $closing(['signature' => $signatureTop, 'certified' => $signatureTop + 21, 'payment' => $signatureTop + 41, 'note' => $signatureTop + 65]);

    $html = '<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { size: 215.9mm 330.2mm; margin: 0; }
* { box-sizing: border-box; }
html, body { width: 215.9mm; min-height: 330.2mm; margin: 0; padding: 0; background: #fff; }
body { font-family: "Courier New", Courier, monospace; color: #111; font-size: 9.5pt; }
 .sheet { width: 215.9mm; height: 330.2mm; max-height: 330.2mm; padding: 0; position: relative; overflow: hidden; page-break-after: avoid; break-after: avoid; }
.sheet-continuation { page-break-before: always; break-before: page; }
 .header { position: absolute; top: 5mm; left: 14mm; right: 12mm; height: 30mm; }
 .header-logo { position: absolute; display: block; }
 .header-logo-seal { left: 0; top: 1.5mm; width: 27mm; height: 27mm; }
 .header-logo-baggao { left: 31mm; top: 1.5mm; width: 27mm; height: 27mm; }
 .header-logo-pilipinas { left: 0; top: 0; width: 34mm; height: 28mm; }
 .header-logo img { width: 100%; height: 100%; object-fit: contain; }
 .header-logo-baggao img { transform: scale(1.35); }
 .header-logo-pilipinas img { transform: scale(1.2); }
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
 .crf-manual-entry { font-weight: 700; text-transform: uppercase; }
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
.doc-remarks { position:absolute; left:21mm; right:21mm; margin:0; color:#111; font-family:Arial,sans-serif; font-size:10.5pt; line-height:1.25; overflow-wrap:anywhere; word-break:break-word; }
.doc-remarks-heading { margin:0 0 2mm; font-family:"Courier New",monospace; font-size:9.5pt; font-weight:700; text-transform:uppercase; }
.doc-remarks-content { white-space:normal; overflow-wrap:anywhere; word-break:break-word; }
.doc-remarks-content p { margin:0 0 1.5mm; }.doc-remarks-content p:last-child { margin-bottom:0; }
.doc-continuation-heading { position:absolute; top:10mm; left:21mm; right:21mm; font-size:11pt; }
.doc-continuation-id { float:right; font-size:8pt; }
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
 <div class="date-line">Date: ' . crf_1a_manual_entry_html($issueDateText) . '</div>
 <div class="intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="intro-statement">
 We certify that, among others, the following facts of birth<br>appear in our Register of Births on page '
     . $shortLine($inputs['page_number'] ?? '', true) . ' Book number ' . $shortLine($inputs['book_number'] ?? '', true) . '.</div></div>
<div class="data-grid">'
    . $field('Registry Number', $values['registry_no'])
    . $field('Date of Registration', $values['date_of_registration'])
    . $field('Population Reference No.', $inputs['population_reference_no'] ?? '', true)
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
 <div class="certification">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</div>'
    . $remarksBlock($remarksPages[0] ?? '', $remarksTop) . $mainClosing . '
</div>';
    if (count($remarksPages) > 1) {
        foreach (array_slice($remarksPages, 1) as $index => $chunk) {
            $height = crf_1a_remarks_height_mm($chunk);
            $last = $index === count($remarksPages) - 2;
            $footerTop = 35 + $height + 12;
            $html .= '<div class="sheet sheet-continuation"><div class="doc-continuation-heading">Civil Registry Form No. 1A - continuation<span class="doc-continuation-id">CRF ID ' . $e($crfNumber) . '</span></div>'
                . $remarksBlock($chunk, 35, true)
                . ($last ? $closing(['signature' => $footerTop, 'certified' => $footerTop + 21, 'payment' => $footerTop + 41, 'note' => $footerTop + 65]) : '')
                . '</div>';
        }
    }
    return $html . '</body></html>';
}

/**
 * Render a LibreOffice-safe PDF document. LibreOffice's HTML importer does
 * not reliably support flexbox or positioned elements, so this template uses
 * fixed-width tables and flow rows for the same legal-size geometry as the browser preview.
 */
function crf_1a_render_table_pdf_html(
    array $cfg,
    string $title,
    string $subtitle,
    string $subject,
    string $registerNoun,
    array $fields,
    array $inputs,
    string $crfNumber,
    array $multilineFieldLines = []
): string {
    $inputs['remarks_html'] = crf_1a_sanitize_remarks_html($inputs['remarks_html'] ?? '');
    $remarksPages = crf_1a_remarks_page_plan($inputs['remarks_html']);
    $e = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    $valueLine = static function ($value, bool $manual = false) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="pdf-line">' . ($manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;')) . '</span>';
    };
    $shortLine = static function ($value, bool $manual = false) use ($e): string {
        $value = trim((string)($value ?? ''));
        return '<span class="pdf-short-line">' . ($manual ? crf_1a_manual_entry_html($value) : ($value !== '' ? $e($value) : '&nbsp;')) . '</span>';
    };
    $logo = static function (string $path, string $alt, string $width, string $height) use ($e): string {
        $uri = crf_1a_asset_data_uri($path);
        return $uri !== ''
            ? '<img src="' . $uri . '" alt="' . $e($alt) . '" style="display:block;width:' . $width . ';height:' . $height . ';">'
            : '';
    };

    // Only reserve header space for left logos that are actually rendered.
    $sealLogoHtml = $logo((string)($cfg['logo_seal'] ?? ''), 'Baggao seal', '27mm', '27mm');
    $baggaoLogoHtml = $logo((string)($cfg['logo_baggao'] ?? ''), 'Baggao reference logo', '27mm', '27mm');
    $leftLogoCount = (int)($sealLogoHtml !== '') + (int)($baggaoLogoHtml !== '');
    $leftLogoWidth = $leftLogoCount === 2 ? 32 : ($leftLogoCount === 1 ? 18 : 0);
    $copyWidth = 82 - $leftLogoWidth;
    $leftLogoHtml = $sealLogoHtml . $baggaoLogoHtml;
    if ($leftLogoCount === 2) {
        $leftLogoHtml = '<table class="logo-pair" cellspacing="0" cellpadding="0" border="0"><tr><td class="pair-logo-cell" width="50%">' . $sealLogoHtml . '</td><td class="pair-logo-cell pair-logo-baggao" width="50%">' . $baggaoLogoHtml . '</td></tr></table>';
    }
    $leftHeaderHtml = $leftLogoCount > 0
        ? '<td class="logo-group" width="' . $leftLogoWidth . '%">' . $leftLogoHtml . '</td>'
        : '';

    $issueTimestamp = strtotime((string)($inputs['issue_date'] ?? date('Y-m-d')));
    $issueDateText = $issueTimestamp === false
        ? crf_1a_manual_entry_html($inputs['issue_date'] ?? '')
        : crf_1a_manual_entry_html(date('F d, Y', $issueTimestamp));
    $amount = number_format((float)($inputs['amount_paid'] ?? 0), 2);
    $requester = trim((string)($inputs['requester_name'] ?? ''));
    $requesterLine = '<span class="pdf-requester-line">' . crf_1a_manual_entry_html($requester) . '</span>';
    $officeName = trim((string)($cfg['office_name'] ?? 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'));
    $officeHtml = $officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'
        ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR'
        : $e($officeName);

    $causePlan = isset($multilineFieldLines['Cause of Death']) && function_exists('crf_2a_cause_page_plan')
        ? crf_2a_cause_page_plan($multilineFieldLines['Cause of Death'])
        : null;
    $causeField = static function (array $lines, string $label = 'Cause of Death') use ($e): string {
        $lineHtml = implode('', array_map(static function ($line): string {
            return '<div class="pdf-cause-line">' . crf_1a_manual_entry_html($line) . '</div>';
        }, $lines));
        return '<table class="pdf-cause-block" cellspacing="0" cellpadding="0" border="0"><tr>'
            . '<td class="pdf-cause-label">' . $e($label) . '</td><td class="pdf-cause-colon">:</td>'
            . '<td class="pdf-cause-lines">' . $lineHtml . '</td></tr></table>';
    };
    $fieldRows = '';
    foreach ($fields as $label => $value) {
        if ($causePlan !== null && $label === 'Cause of Death') {
            $fieldRows .= $causeField($causePlan['first_page_lines']);
            continue;
        }
        $fieldValue = trim((string)($value ?? ''));
        $fieldDisplay = crf_1a_manual_entry_html($fieldValue);
        $fieldRows .= '<div style="height:4.6mm;line-height:4.6mm;white-space:nowrap;font-size:9pt;overflow:hidden">'
            . '<span style="display:inline-block;width:78mm;vertical-align:bottom">' . $e($label) . '</span>'
            . '<span style="display:inline-block;width:4mm;vertical-align:bottom">:</span>'
            . '<span style="display:inline-block;width:59mm;height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom">'
            . $fieldDisplay . '</span>'
            . '</div>';
    }

    $introText = 'We certify that, among others, the following facts of ' . $e($subject)
        . '<br>appear in our Register of ' . $e($registerNoun) . ' on page '
        . $shortLine($inputs['page_number'] ?? '', true) . ' Book number '
        . $shortLine($inputs['book_number'] ?? '', true) . '.';

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . '@page{size:215.9mm 330.2mm;margin:0}'
        . '*{box-sizing:border-box}'
        . 'html,body{width:215.9mm;height:330.2mm;margin:0;padding:0;background:#fff}'
        . 'body{font-family:"Courier New",monospace;color:#111;font-size:9.5pt}'
        . '.pdf-page{width:215.9mm;height:327mm;border-collapse:collapse;table-layout:fixed}'
        . '.pdf-page>tbody>tr>td{padding:0;vertical-align:top}'
        . '.top-spacer{height:5mm;line-height:0;font-size:0}'
        . '.header-table{width:100%;height:30mm;border-collapse:collapse;table-layout:fixed}'
        . '.header-table td{padding:0;vertical-align:middle}'
        . '.header-table .logo-group{width:' . $leftLogoWidth . '%;padding:0}'
        . '.logo-pair{width:100%;border-collapse:collapse;table-layout:fixed}'
        . '.logo-pair td{padding:0;text-align:center;vertical-align:middle}'
        . '.logo-pair .pair-logo-cell{width:50%}'
        . '.header-table .copy-cell{width:' . $copyWidth . '%;text-align:left;line-height:1.05}'
        . '.header-table .right-cell{width:18%;padding:0;vertical-align:top}'
        . '.logo-pair img{display:block;width:27mm;height:27mm;margin:0 auto}.logo-pair .pair-logo-baggao img{transform:scale(1.35)}'
        . '.copy-cell .small{font-size:9pt}'
        . '.copy-cell .municipality{font-family:"Times New Roman",serif;font-size:13.5pt;font-weight:700}'
        . '.copy-cell .office{font-family:"Times New Roman",serif;font-size:12pt;font-weight:700}'
        . '.copy-cell .address{font-size:7.5pt;margin-top:1mm}'
        . '.right-table{width:100%;height:30mm;border-collapse:collapse;table-layout:fixed}'
        . '.right-table td{padding:0;text-align:center}'
        . '.right-logo{height:28mm;vertical-align:top}'
        . '.right-logo img{display:block;width:34mm;height:28mm;margin:0 auto;transform:scale(1.2)}'
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
        . '.crf-manual-entry{font-weight:700;text-transform:uppercase}'
        . '.pdf-short-line{display:inline-block;width:12mm;height:3.8mm;border-bottom:1px solid #222;text-align:center;vertical-align:bottom;white-space:nowrap;overflow:hidden}'
        . '.pdf-line{display:inline-block;width:100%;height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}'
        . '.pdf-cause-block{width:141mm;border-collapse:collapse;table-layout:fixed;font-size:9pt;line-height:1.05}.pdf-cont-page .pdf-cause-block{margin-left:34mm}'
        . '.pdf-cause-block td{padding:0;vertical-align:top}.pdf-cause-label{width:78mm;height:4.6mm;line-height:4.6mm;white-space:nowrap}'
        . '.pdf-cause-colon{width:4mm;height:4.6mm;line-height:4.6mm}.pdf-cause-lines{width:59mm;vertical-align:top}'
        . '.pdf-cause-line{display:block;width:100%;height:4.6mm;line-height:4.6mm;border-bottom:1px solid #222;white-space:pre;overflow:hidden}'
        . '.pdf-cont-page{page-break-before:always;break-before:page}.pdf-cont-title{height:16mm;padding-left:21mm!important;padding-right:21mm!important;font-size:11pt;line-height:1.2}'
        . '.grid-spacer{height:4.2mm;font-size:0;line-height:0}'
        . '.certification-row{height:24mm;padding:0 20mm 0 29mm!important;line-height:1.3}'
        . '.pdf-requester-line{display:inline-block;width:62mm;height:3.8mm;border-bottom:1px solid #222;white-space:nowrap;overflow:hidden;vertical-align:bottom}'
        . '.signature-row{height:21mm}'
        . '.signature-table{width:215.9mm;height:21mm;border-collapse:collapse;table-layout:fixed}'
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
        . '.remarks-row{padding:0 21mm 0 29mm!important;font:10.5pt/1.25 Arial,sans-serif;overflow-wrap:anywhere;word-break:break-word}'
        . '.remarks-heading{margin:0 0 2mm;font:700 9.5pt "Courier New",monospace;text-transform:uppercase}'
        . '.remarks-content p{margin:0 0 1.5mm}.remarks-content p:last-child{margin-bottom:0}'
        . '.remarks-page{page-break-before:always;break-before:page}.remarks-cont-title{height:16mm;padding:0 21mm!important;font:11pt/1.2 "Courier New",monospace}'
        . '</style></head><body><table class="pdf-page" cellspacing="0" cellpadding="0" border="0"><tr><td class="top-spacer">&nbsp;</td></tr>'
        . '<tr><td style="padding-left:14mm;padding-right:12mm;"><table class="header-table" cellspacing="0" cellpadding="0" border="0"><tr>'
        . $leftHeaderHtml
        . '<td class="copy-cell" width="' . $copyWidth . '%"><div class="small">Republic of the Philippines</div><div class="small">Province of ' . $e($cfg['province'] ?? '') . '</div><div class="municipality">MUNICIPALITY OF ' . $e($cfg['municipality'] ?? '') . '</div><div class="office">' . $officeHtml . '</div><div class="address">' . $e($cfg['address'] ?? '') . '</div></td>'
        // Keep this logo directly in its cell. Writer can move a nested table below the office text.
        . '<td class="right-cell" width="18%" valign="top">' . $logo((string)($cfg['logo_pilipinas'] ?? ''), 'Bagong Pilipinas', '34mm', '28mm') . '<p class="header-meta" style="margin:0;font-size:5.5pt;text-align:right">CRF ID<strong>' . $e($crfNumber) . '</strong></p></td>'
        . '</tr></table></td></tr><tr><td class="header-gap">&nbsp;</td></tr><tr><td class="rule-row"><div class="red-rule">&nbsp;</div></td></tr><tr><td class="after-rule">&nbsp;</td></tr>'
        . '<tr><td class="title-row">' . $e($title) . '</td></tr><tr><td class="subtitle-row">' . $e($subtitle) . '</td></tr><tr><td class="date-row">Date: ' . $issueDateText . '</td></tr>'
        . '<tr><td class="intro-row"><table class="intro-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="intro-heading"><strong>TO WHOM IT MAY CONCERN:</strong></td></tr><tr><td class="intro-gap">&nbsp;</td></tr><tr><td class="intro-statement">' . $introText . '</td></tr></table></td></tr>'
        . '<tr><td><div style="width:141mm;margin-left:34mm">' . $fieldRows . '</div></td></tr>';

    $certificationRow = '<tr><td class="grid-spacer">&nbsp;</td></tr>'
        . '<tr><td class="certification-row">This certification is issued to ' . $requesterLine . ' upon his/her<br>request.</td></tr>';
    $remarksRow = static function (string $html, bool $continued = false): string {
        if ($html === '') return '';
        return '<tr><td class="remarks-row"><div class="remarks-heading">' . ($continued ? 'REMARKS (CONTINUED)' : 'REMARKS') . '</div><div class="remarks-content">' . $html . '</div></td></tr>';
    };
    $closingRows = '<tr><td class="signature-row"><table class="signature-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="signature-spacer">&nbsp;</td><td class="signature-cell"><strong>' . crf_1a_manual_entry_html($inputs['mcr_full_name'] ?? '') . '</strong><div>' . crf_1a_manual_entry_html($inputs['mcr_title'] ?? '') . '</div></td><td class="signature-right-spacer">&nbsp;</td></tr></table></td></tr>'
        . '<tr><td class="certified-row"><table class="certified-table" cellspacing="0" cellpadding="0" border="0"><tr><td><table class="certified-heading" cellspacing="0" cellpadding="0" border="0"><tr><td class="certified-label">Certified by:</td><td class="certified-line">' . crf_1a_manual_entry_html($inputs['certified_by_name'] ?? '') . '</td></tr></table><div class="certified-position">' . crf_1a_manual_entry_html($inputs['certified_by_position'] ?? '') . '</div></td></tr></table></td></tr>'
        . '<tr><td class="payment-row"><table class="payment-table" cellspacing="0" cellpadding="0" border="0"><tr><td class="payment-label">Amount paid</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine($amount, true) . '</td></tr><tr><td class="payment-label">O.R. Number</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine($inputs['or_number'] ?? '', true) . '</td></tr><tr><td class="payment-label">Date paid</td><td class="payment-colon">:</td><td class="payment-value">' . $valueLine(crf_1a_full_date($inputs['date_paid'] ?? ''), true) . '</td></tr></table></td></tr>'
        . '<tr><td class="note-row"><div class="note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ' . $e($crfNumber) . '</small></div></td></tr>';

    if ($causePlan === null || $causePlan['continuation_pages'] === []) {
        $firstRemarks = $remarksPages[0] ?? '';
        if (count($remarksPages) > 1) {
            $html .= $certificationRow . $remarksRow($firstRemarks);
            $html .= '</table>';
            foreach (array_slice($remarksPages, 1) as $index => $chunk) {
                $finalPage = $index === count($remarksPages) - 2;
                $html .= '<table class="pdf-page pdf-cont-page remarks-page" cellspacing="0" cellpadding="0" border="0"><tr><td class="top-spacer">&nbsp;</td></tr>'
                    . '<tr><td class="remarks-cont-title">Civil Registry Form No. ' . $e(str_replace(['Civil Registry Form No. ', ' (Birth-Available)', ' (Death-Available)', ' (Marriage-Available)'], '', $title)) . ' - continuation <span style="float:right;font-size:8pt">CRF ID: ' . $e($crfNumber) . '</span></td></tr>'
                    . $remarksRow($chunk, true) . ($finalPage ? $closingRows : '') . '</table>';
            }
            return $html . '</body></html>';
        }
        return $html . $certificationRow . $remarksRow($firstRemarks) . $closingRows . '</table></body></html>';
    }

    $html .= '</table>';
    foreach ($causePlan['continuation_pages'] as $continuation) {
        $continuationLines = $continuation['lines'];
        $continuationCause = $continuationLines
            ? '<tr><td>' . $causeField($continuationLines, 'Cause of Death (continued)') . '</td></tr>'
            : '';
        $html .= '<table class="pdf-page pdf-cont-page" cellspacing="0" cellpadding="0" border="0"><tr><td class="top-spacer">&nbsp;</td></tr>'
            . '<tr><td class="pdf-cont-title">Civil Registry Form No. 2A — continuation <span style="float:right;font-size:8pt">CRF ID: ' . $e($crfNumber) . '</span></td></tr>'
            . $continuationCause
            . ($continuation['include_footer'] ? $certificationRow . $remarksRow($remarksPages[0] ?? '') . (count($remarksPages) > 1 ? '' : $closingRows) : '')
            . '</table>';
    }
    if (count($remarksPages) > 1) {
        foreach (array_slice($remarksPages, 1) as $index => $chunk) {
            $finalPage = $index === count($remarksPages) - 2;
            $html .= '<table class="pdf-page pdf-cont-page remarks-page" cellspacing="0" cellpadding="0" border="0"><tr><td class="top-spacer">&nbsp;</td></tr>'
                . '<tr><td class="remarks-cont-title">' . $e($title) . ' - continuation <span style="float:right;font-size:8pt">CRF ID: ' . $e($crfNumber) . '</span></td></tr>'
                . $remarksRow($chunk, true) . ($finalPage ? $closingRows : '') . '</table>';
        }
    }
    return $html . '</body></html>';
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

function crf_1a_render_pdf_with_edge(string $html, string $outputPdf, string $edgePath, ?string &$error = null, bool $allowMultiplePages = false): bool
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
        $pagesBeforeNormalization = crf_1a_pdf_page_count($generatedPdf);
        $normalized = crf_1a_remove_trailing_blank_pages($generatedPdf);
        $pagesAfterNormalization = crf_1a_pdf_page_count($generatedPdf);
        if (!$normalized) {
            throw new RuntimeException('Unable to normalize the generated CRF PDF to one page (pages before=' . $pagesBeforeNormalization . ', after=' . $pagesAfterNormalization . ').');
        }
        if ($pagesAfterNormalization < 1 || (!$allowMultiplePages && $pagesAfterNormalization !== 1)) {
            throw new RuntimeException('The generated CRF PDF is not a one-page document (pages before=' . $pagesBeforeNormalization . ', after=' . $pagesAfterNormalization . ').');
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

function crf_1a_render_pdf(string $html, string $outputPdf, ?string &$error = null, bool $allowMultiplePages = false): bool
{
    $edgePath = crf_1a_edge_path();
    if ($edgePath !== null) {
        return crf_1a_render_pdf_with_edge($html, $outputPdf, $edgePath, $error, $allowMultiplePages);
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
        $pagesBeforeNormalization = crf_1a_pdf_page_count($generatedPdf);
        $normalized = crf_1a_remove_trailing_blank_pages($generatedPdf);
        $pagesAfterNormalization = crf_1a_pdf_page_count($generatedPdf);
        if (!$normalized) {
            throw new RuntimeException('Unable to normalize the generated CRF PDF to one page (pages before=' . $pagesBeforeNormalization . ', after=' . $pagesAfterNormalization . ').');
        }
        if ($pagesAfterNormalization < 1 || (!$allowMultiplePages && $pagesAfterNormalization !== 1)) {
            throw new RuntimeException('The generated CRF PDF is not a one-page document (pages before=' . $pagesBeforeNormalization . ', after=' . $pagesAfterNormalization . ').');
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
    $pageDiag = crf_1a_pdf_page_count($pdfPath) > 1;
    if ($pageDiag) {
        preg_match_all('~/Type\\s*/Pages\\b|/Kids\\b|/Count\\s+\\d+\\b~', $pdf, $pageShape);
        error_log('CRF_PAGE_DIAG structure=' . json_encode(array_slice($pageShape[0], 0, 20)));
    }

    if (!preg_match('~/Type\\s*/Pages.*?/Kids\\s*\\[(.*?)\\].*?/Count\\s+(\\d+)~s', $pdf, $treeMatch, PREG_OFFSET_CAPTURE)) {
        if ($pageDiag) error_log('CRF_PAGE_DIAG tree=unmatched');
        return true;
    }
    $count = (int)$treeMatch[2][0];
    $kidsText = $treeMatch[1][0];
    $refs = [];
    preg_match_all('~(\\d+)\\s+0\\s+R~', $kidsText, $refMatches);
    foreach ($refMatches[1] as $ref) $refs[] = (int)$ref;
    if ($pageDiag) error_log('CRF_PAGE_DIAG tree_count=' . $count . ' kids=' . count($refs));
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
    if ($pageDiag) error_log('CRF_PAGE_DIAG decoded_bytes=' . ($decoded === false ? 'FAILED' : strlen($decoded)));
    // An undecodable stream is not evidence that its page is blank.
    if ($decoded === false) return true;
    if ($decoded !== false) {
        // LibreOffice paints a white page background inside an Artifact block
        // even when its final page is otherwise empty. Ignore only that known
        // background rectangle before checking for actual page content.
        $inspected = preg_replace(
            '~/?Artifact\\s+BMC\\s+q\\s+[-+0-9.]+\\s+[-+0-9.]+\\s+[-+0-9.]+\\s+[-+0-9.]+\\s+re\\s+W\\*\\s+n\\s+1(?:\\.0*)?\\s+1(?:\\.0*)?\\s+1(?:\\.0*)?\\s+rg\\s+'
            . '[-+0-9.]+\\s+[-+0-9.]+\\s+m\\s+[-+0-9.]+\\s+[-+0-9.]+\\s+l\\s+'
            . '[-+0-9.]+\\s+[-+0-9.]+\\s+l\\s+[-+0-9.]+\\s+[-+0-9.]+\\s+l\\s+'
            . '[-+0-9.]+\\s+[-+0-9.]+\\s+l\\s+h\\s+f\\*\\s+EMC~s',
            '',
            $decoded,
            1
        );
        if (!is_string($inspected)) $inspected = $decoded;
        $inspected = trim($inspected);
        if ($inspected === 'Q') $inspected = '';
        if ($pageDiag) error_log('CRF_PAGE_DIAG remaining_bytes=' . strlen($inspected));

        // Text, image, and paint operators mean the page has visible content.
        if (preg_match('~(?:^|[\\s>\\)\\]])(?:Tj|TJ|Do|S|s|f|F|B|b)(?:\\s|$)~', $inspected, $pagePaint)) {
            if ($pageDiag) error_log('CRF_PAGE_DIAG keep_operator=' . trim($pagePaint[0]));
            return true;
        }
    }

    if ($pageDiag) error_log('CRF_PAGE_DIAG rewrite=attempted');
    $oldTree = $treeMatch[0][0];
    $newKids = '[ ' . implode(' ', array_map(static fn($ref) => $ref . ' 0 R', array_slice($refs, 0, -1))) . ' ]';
    $newTree = preg_replace('~/Kids\\s*\\[.*?\\].*?/Count\\s+\\d+~s', '/Kids' . $newKids . ' /Count ' . ($count - 1), $oldTree, 1);
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
