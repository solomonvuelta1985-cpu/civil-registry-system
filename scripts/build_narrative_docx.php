<?php
declare(strict_types=1);

$mdPath   = __DIR__ . '/../docs/NARRATIVE_REPORT.md';
$docxPath = __DIR__ . '/../docs/NARRATIVE_REPORT.docx';

// If the primary path is locked (Word has it open), fall back to a versioned name
// so the user still gets the latest output without manual intervention.
if (file_exists($docxPath) && !@unlink($docxPath)) {
    $docxPath = __DIR__ . '/../docs/NARRATIVE_REPORT_v2.docx';
    @unlink($docxPath);
}

if (!is_file($mdPath)) {
    fwrite(STDERR, "Markdown source not found: $mdPath\n");
    exit(1);
}

$md = file_get_contents($mdPath);
if ($md === false) {
    fwrite(STDERR, "Failed to read $mdPath\n");
    exit(1);
}

$lines = preg_split("/\r\n|\r|\n/", $md);

function xmlEscape(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function renderRuns(string $text): string {
    $text = preg_replace('/`([^`]+)`/', "\x01CODE_OPEN\x01$1\x01CODE_CLOSE\x01", $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', "\x01B_OPEN\x01$1\x01B_CLOSE\x01", $text);
    $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', "\x01I_OPEN\x01$1\x01I_CLOSE\x01", $text);

    $tokens = preg_split("/(\x01(?:CODE_OPEN|CODE_CLOSE|B_OPEN|B_CLOSE|I_OPEN|I_CLOSE)\x01)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    $bold = false;
    $italic = false;
    $code = false;
    $xml = '';
    foreach ($tokens as $tok) {
        if ($tok === '') continue;
        switch ($tok) {
            case "\x01B_OPEN\x01":    $bold = true;   break;
            case "\x01B_CLOSE\x01":   $bold = false;  break;
            case "\x01I_OPEN\x01":    $italic = true; break;
            case "\x01I_CLOSE\x01":   $italic = false; break;
            case "\x01CODE_OPEN\x01": $code = true;   break;
            case "\x01CODE_CLOSE\x01":$code = false;  break;
            default:
                $rPr = '';
                if ($bold || $italic || $code) {
                    $rPr = '<w:rPr>';
                    if ($bold)   $rPr .= '<w:b/>';
                    if ($italic) $rPr .= '<w:i/>';
                    if ($code)   $rPr .= '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>';
                    $rPr .= '</w:rPr>';
                }
                $xml .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . xmlEscape($tok) . '</w:t></w:r>';
        }
    }
    return $xml;
}

function paragraph(string $styleId, string $text): string {
    $pPr = $styleId !== '' ? '<w:pPr><w:pStyle w:val="' . $styleId . '"/></w:pPr>' : '';
    return '<w:p>' . $pPr . renderRuns($text) . '</w:p>';
}

function emptyParagraph(): string {
    return '<w:p/>';
}

$body = '';
$inCodeBlock = false;
$inTable     = false;
$tableRows   = [];

foreach ($lines as $line) {
    $rtrim = rtrim($line);

    if (preg_match('/^```/', $rtrim)) {
        $inCodeBlock = !$inCodeBlock;
        continue;
    }
    if ($inCodeBlock) {
        $body .= paragraph('Code', $rtrim);
        continue;
    }

    if (preg_match('/^\s*\|.*\|\s*$/', $rtrim)) {
        if (preg_match('/^\s*\|\s*[-:\s|]+\s*\|\s*$/', $rtrim)) {
            continue;
        }
        $cells = array_map('trim', explode('|', trim($rtrim, " \t|")));
        $tableRows[] = $cells;
        $inTable = true;
        continue;
    } elseif ($inTable) {
        $body .= renderTable($tableRows);
        $tableRows = [];
        $inTable = false;
    }

    if ($rtrim === '' || $rtrim === '---') {
        $body .= emptyParagraph();
        continue;
    }

    if (preg_match('/^######\s+(.*)$/', $rtrim, $m)) { $body .= paragraph('Heading6', $m[1]); continue; }
    if (preg_match('/^#####\s+(.*)$/',  $rtrim, $m)) { $body .= paragraph('Heading5', $m[1]); continue; }
    if (preg_match('/^####\s+(.*)$/',   $rtrim, $m)) { $body .= paragraph('Heading4', $m[1]); continue; }
    if (preg_match('/^###\s+(.*)$/',    $rtrim, $m)) { $body .= paragraph('Heading3', $m[1]); continue; }
    if (preg_match('/^##\s+(.*)$/',     $rtrim, $m)) { $body .= paragraph('Heading2', $m[1]); continue; }
    if (preg_match('/^#\s+(.*)$/',      $rtrim, $m)) { $body .= paragraph('Heading1', $m[1]); continue; }

    if (preg_match('/^\s*[-*]\s+(.*)$/', $rtrim, $m)) {
        $body .= paragraph('ListBullet', $m[1]);
        continue;
    }
    if (preg_match('/^\s*\d+\.\s+(.*)$/', $rtrim, $m)) {
        $body .= paragraph('ListNumber', $m[1]);
        continue;
    }

    $body .= paragraph('', $rtrim);
}

if ($inTable && !empty($tableRows)) {
    $body .= renderTable($tableRows);
}

function renderTable(array $rows): string {
    if (empty($rows)) return '';
    $colCount = max(array_map('count', $rows));
    $xml = '<w:tbl>';
    $xml .= '<w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
         .  '<w:top w:val="single" w:sz="4" w:color="999999"/>'
         .  '<w:left w:val="single" w:sz="4" w:color="999999"/>'
         .  '<w:bottom w:val="single" w:sz="4" w:color="999999"/>'
         .  '<w:right w:val="single" w:sz="4" w:color="999999"/>'
         .  '<w:insideH w:val="single" w:sz="4" w:color="CCCCCC"/>'
         .  '<w:insideV w:val="single" w:sz="4" w:color="CCCCCC"/>'
         .  '</w:tblBorders></w:tblPr>';
    $xml .= '<w:tblGrid>' . str_repeat('<w:gridCol w:w="2000"/>', $colCount) . '</w:tblGrid>';
    foreach ($rows as $i => $row) {
        $isHeader = ($i === 0);
        $xml .= '<w:tr>';
        for ($c = 0; $c < $colCount; $c++) {
            $cell = $row[$c] ?? '';
            $rPr = $isHeader ? '<w:rPr><w:b/></w:rPr>' : '';
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
                 .  '<w:p>' . renderRuns($cell) . '</w:p>'
                 .  '</w:tc>';
        }
        $xml .= '</w:tr>';
    }
    $xml .= '</w:tbl>';
    $xml .= '<w:p/>';
    return $xml;
}

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:body>'
    . $body
    . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
    . '</w:body></w:document>';

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    . '</Types>';

$rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';

$documentRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault></w:docDefaults>'

    . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:pPr><w:spacing w:after="160" w:line="276" w:lineRule="auto"/></w:pPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="480" w:after="240"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="1F3864"/><w:sz w:val="36"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="360" w:after="180"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="1F3864"/><w:sz w:val="28"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="240" w:after="120"/><w:outlineLvl w:val="2"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="2E74B5"/><w:sz w:val="24"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading4"><w:name w:val="heading 4"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="200" w:after="100"/><w:outlineLvl w:val="3"/></w:pPr><w:rPr><w:b/><w:i/><w:color w:val="2E74B5"/><w:sz w:val="22"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading5"><w:name w:val="heading 5"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:outlineLvl w:val="4"/></w:pPr><w:rPr><w:b/><w:color w:val="2E74B5"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Heading6"><w:name w:val="heading 6"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:outlineLvl w:val="5"/></w:pPr><w:rPr><w:b/><w:i/><w:color w:val="2E74B5"/></w:rPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="ListBullet"><w:name w:val="List Bullet"/><w:basedOn w:val="Normal"/><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="ListNumber"><w:name w:val="List Number"/><w:basedOn w:val="Normal"/><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:style>'

    . '<w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="20"/></w:rPr></w:style>'

    . '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:basedOn w:val="TableNormal"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="999999"/><w:left w:val="single" w:sz="4" w:color="999999"/><w:bottom w:val="single" w:sz="4" w:color="999999"/><w:right w:val="single" w:sz="4" w:color="999999"/><w:insideH w:val="single" w:sz="4" w:color="CCCCCC"/><w:insideV w:val="single" w:sz="4" w:color="CCCCCC"/></w:tblBorders></w:tblPr></w:style>'

    . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/></w:style>'

    . '</w:styles>';

$zip = new ZipArchive();
if ($zip->open($docxPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Failed to create $docxPath\n");
    exit(1);
}

$zip->addFromString('[Content_Types].xml',  $contentTypesXml);
$zip->addFromString('_rels/.rels',          $rootRelsXml);
$zip->addFromString('word/document.xml',    $documentXml);
$zip->addFromString('word/_rels/document.xml.rels', $documentRelsXml);
$zip->addFromString('word/styles.xml',      $stylesXml);

$zip->close();

echo "Wrote: $docxPath\n";
echo "Size:  " . filesize($docxPath) . " bytes\n";
