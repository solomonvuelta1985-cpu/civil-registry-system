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

function crf_1a_render_document_html(array $record, array $inputs, string $crfNumber): string
{
    $cfg = crf_1a_config();
    $values = crf_1a_record_values($record);
    $e = static function ($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    };
    $line = static function ($value) use ($e): string {
        $value = trim((string)($value ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        $fontSize = $length > 68 ? '6.5pt' : ($length > 48 ? '7.5pt' : '');
        $style = $fontSize !== '' ? ' style="font-size:' . $fontSize . ';"' : '';
        return '<span class="filled-line"' . $style . '>' . ($value !== '' ? $e($value) : '&nbsp;') . '</span>';
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
    $requesterLength = function_exists('mb_strlen') ? mb_strlen($requester, 'UTF-8') : strlen($requester);
    $requesterSize = $requesterLength > 68 ? '6.5pt' : ($requesterLength > 48 ? '7.5pt' : '');
    $requesterStyle = $requesterSize !== '' ? ' style="font-size:' . $requesterSize . ';"' : '';
    $requesterLine = '<span class="requester-line"' . $requesterStyle . '>' . ($requester !== '' ? $e($requester) : '&nbsp;') . '</span>';

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
 .header { position: absolute; top: 5mm; left: 14mm; right: 12mm; height: 30mm; display: flex; align-items: center; gap: 4mm; }
 .header-logo { display: flex; align-items: center; justify-content: center; }
 .header-logo-seal { width: 27mm; height: 27mm; flex: 0 0 27mm; }
 .header-logo-baggao { width: 27mm; height: 27mm; flex: 0 0 27mm; }
 .header-logo-pilipinas { width: 34mm; height: 28mm; flex: 0 0 34mm; }
 .header-logo img { width: 100%; height: 100%; object-fit: contain; }
 .header-copy { flex: 1; text-align: left; line-height: 1.05; }
 .header-copy .republic, .header-copy .province { font-size: 9pt; }
 .header-copy .municipality { font-family: "Times New Roman", serif; font-size: 13.5pt; font-weight: 700; letter-spacing: .01em; }
 .header-copy .office { font-family: "Times New Roman", serif; font-size: 12pt; font-weight: 700; letter-spacing: .01em; }
 .header-copy .address { font-size: 7.5pt; margin-top: 1mm; }
 .header-right { width: 34mm; flex: 0 0 34mm; height: 30mm; display: flex; flex-direction: column; align-items: center; justify-content: center; }
 .header-meta { width: 100%; text-align: right; font-family: Arial, sans-serif; font-size: 5.5pt; color: #334155; margin-top: 1mm; }
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
 .data-row { display: flex; align-items: baseline; min-height: 4.6mm; line-height: 1.05; }
 .data-label { width: 78mm; white-space: nowrap; }
.data-colon { width: 4mm; }
.data-value { flex: 1; min-width: 0; }
 .filled-line { display: inline-block; width: 100%; min-height: 3.8mm; border-bottom: 1px solid #222; white-space: nowrap !important; overflow: hidden !important; text-overflow: clip; vertical-align: bottom; }
 .requester-line { display: inline-block; width: 62mm; min-height: 3.8mm; border-bottom: 1px solid #222; white-space: nowrap !important; overflow: hidden !important; text-overflow: clip; vertical-align: bottom; }
 .certification { position: absolute; top: 154mm; left: 29mm; right: 20mm; margin: 0; line-height: 1.3; }
 .signature-block { position: absolute; top: 178mm; right: 30mm; margin: 0; width: 62mm; text-align: center; font-size: 9pt; }
.signature-block strong { display: block; font-family: Arial, sans-serif; font-size: 9.5pt; }
 .certified-block { position: absolute; top: 199mm; left: 21mm; margin: 0; width: 105mm; }
 .certified-heading { display: flex; align-items: flex-end; width: 95mm; }
 .certified-heading > span:first-child { width: 32mm; flex: 0 0 32mm; }
.certified-line { display: inline-block; width: 62mm; border-bottom: 1px solid #222; vertical-align: bottom; height: 5mm; }
.certified-label { margin-left: 32mm; font-weight: 700; text-align: center; width: 62mm; }
.certified-position { margin-left: 32mm; text-align: left; width: 62mm; }
 .payment-block { position: absolute; top: 219mm; left: 21mm; margin: 0; width: 90mm; }
 .payment-row { display: flex; min-height: 4.6mm; line-height: 1.05; }
.payment-row .payment-label { width: 35mm; }
.payment-row .payment-colon { width: 5mm; }
.payment-row .payment-value { flex: 1; }
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

function crf_1a_render_pdf(string $html, string $outputPdf, ?string &$error = null): bool
{
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
    $decoded = @gzuncompress($contentsStream[1]) ?: @zlib_decode($contentsStream[1]);
    // Text, image, and paint operators mean the page is not merely the
    // unpainted trailing page produced by the HTML importer.
    if ($decoded === false || strlen(trim($decoded)) > 128
        || preg_match('~(?:^|[\\s>\\)\\]])(?:Tj|TJ|Do|S|s|f|F|B|b)(?:\\s|$)~', $decoded)) return true;

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
    if (preg_match('~/Type/Pages.*?/Count\\s+(\\d+)~s', $pdf, $match)) return (int)$match[1];
    return 0;
}
