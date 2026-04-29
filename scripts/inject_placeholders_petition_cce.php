<?php
/**
 * One-time fix-up for the user's manually-edited RA 9048 petition (birth) 2.docx.
 *
 * The user has already done most of the find/replace work in Word, but a few
 * issues remain and Word has split many ${placeholder} tokens across multiple
 * <w:r> runs which makes them fragile. This script:
 *
 *   1) Loads documents/templates/RA 9048 petition (birth) 2.docx
 *   2) Reads word/document.xml
 *   3) Normalizes ALL split placeholders by collapsing them to clean ${name} form
 *   4) Fixes the 6 remaining content issues:
 *      - SS header "Baggao, Cagayan" -> ${office_municipality}, ${office_province}
 *      - Item 5 "FATHER'S FULL NAME is ${value_to}" -> "${first_description} is ${first_value_to}"
 *      - Stray "1" leftover before supporting_block row
 *      - ACTION TAKEN paragraph: "RA 9048" -> "${law}"
 *      - Payment table: 0963723 -> ${receipt_number}, 1,000.00 -> ${fee_amount},
 *        07/15/2025 -> ${payment_date}
 *   5) Writes the modified document.xml back into the .docx zip
 *
 * Backup of the original lives at "RA 9048 petition (birth) 2.original.docx".
 *
 * Usage:
 *   php scripts/inject_placeholders_petition_cce.php
 */

require_once __DIR__ . '/../includes/config_ra9048.php';

$tplPath = RA9048_TEMPLATES_PATH . 'RA 9048 petition (birth) 2.docx';

if (!is_file($tplPath)) {
    fwrite(STDERR, "Template not found: {$tplPath}\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($tplPath) !== true) {
    fwrite(STDERR, "Failed to open .docx (zip).\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
if ($xml === false) {
    $zip->close();
    fwrite(STDERR, "word/document.xml not found inside zip.\n");
    exit(1);
}

$originalLength = strlen($xml);
$report = [];

// ---------------------------------------------------------------------
// Step 1: Normalize ALL split ${placeholder} tokens.
//
// Word frequently splits a typed placeholder across multiple <w:r> runs
// when formatting is applied. The DocxTemplateProcessor at runtime DOES
// handle this, but baking clean placeholders now makes the file robust
// and easier to inspect.
//
// Pattern: \$ then optional XML tags+whitespace, then { , optional content
// (which may include any number of XML tags/whitespace/text),
// then a closing } . We capture the inner identifier text.
// ---------------------------------------------------------------------

// IMPORTANT: only consume the SPECIFIC tag sequences Word inserts when it
// fragments a placeholder ACROSS RUNS in a SINGLE PARAGRAPH:
//   </w:t></w:r><w:proofErr .../><w:r ...><w:rPr>...</w:rPr><w:t...>
// We must NOT consume <w:trPr>, <w:trHeight>, <w:jc>, etc. — those are
// row/cell-level tags and swallowing them would tear the table apart.
//
// Strategy: a placeholder fragment is allowed to traverse only safe-to-skip
// closure-and-reopen markup AND `<w:proofErr ...>` markers, then a new <w:t>.
// We never let it cross a `</w:p>` (paragraph end), `<w:tr` (row start), or
// `<w:tc` (cell start). Inner identifier chars are just [A-Za-z0-9_].

$beforeNormalizationXml = $xml;

// Allowed "bridge" segments inside a placeholder when split across runs.
// Each bridge ends with a fresh <w:t...> opening tag.
$bridge = '(?:'
        . '</w:t>\s*</w:r>\s*'                               // close text + run
        . '(?:<w:proofErr[^/]*/>\s*)*'                       // optional spell-check markers
        . '<w:r(?:\s[^>]*)?>\s*'                             // open new run
        . '(?:<w:rPr>(?:(?!</w:rPr>).)*</w:rPr>\s*)?'        // optional run properties
        . '<w:t(?:\s[^>]*)?>'                                // open new text
        . ')';

$xml = preg_replace_callback(
    '#\$\s*\{((?:[A-Za-z0-9_]|' . $bridge . ')+)\}#s',
    function ($m) {
        // Strip any XML markup from the captured identifier (only bridges remain).
        $inner = preg_replace('/<[^>]+>/', '', $m[1]);
        // Strip whitespace too (some splits leave residual spaces).
        $inner = preg_replace('/\s+/', '', $inner);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $inner)) {
            return $m[0]; // not actually a placeholder — leave untouched
        }
        return '${' . $inner . '}';
    },
    $xml
);

if ($xml !== $beforeNormalizationXml) {
    $report[] = "Normalized split placeholders (collapsed run-bridges inside \${...}).";
}

// ---------------------------------------------------------------------
// Step 2: SS header — replace the "Baggao, Cagayan" parenthesized line
// with placeholder form. We have to be precise: the petitioner's address
// in the body might contain "BAGGAO" too. The SS header has a unique
// preceding "Republic of the Philippines" line so we anchor on that.
//
// In the extracted text we see:
//   Republic of the Philippines  )
//   ${petition_number}
//   Baggao
//   , Cagayan
//   ) SS
//
// The "Baggao" and ", Cagayan" appear as two separate runs (split by
// Word's spell-check around the PH word). After step 1's normalization,
// they may be unified or still split. We do a targeted text replacement
// that handles both forms.
// ---------------------------------------------------------------------

$ssReplacements = 0;

// The SS header structure in this template:
//   <w:proofErr w:type="spellStart"/><w:r><w:t>Baggao</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r ...><w:t>, Cagayan</w:t></w:r>
// Match this pattern as a whole and replace with a single clean run containing
// our placeholder pair.
$pattern = '#<w:proofErr w:type="spellStart"\s*/>\s*<w:r[^>]*>\s*<w:t>Baggao</w:t>\s*</w:r>\s*<w:proofErr w:type="spellEnd"\s*/>\s*<w:r[^>]*>\s*<w:t[^>]*>,\s*Cagayan</w:t>\s*</w:r>#s';
$xml = preg_replace_callback($pattern, function ($m) use (&$ssReplacements) {
    $ssReplacements++;
    return '<w:r><w:t xml:space="preserve">${office_municipality}, ${office_province}</w:t></w:r>';
}, $xml);

// Fallback for any remaining unsplit occurrences
$pattern = '/<w:t([^>]*)>([^<]*)Baggao,\s*Cagayan([^<]*)<\/w:t>/i';
$xml = preg_replace_callback($pattern, function ($m) use (&$ssReplacements) {
    $ssReplacements++;
    return '<w:t' . $m[1] . '>' . $m[2] . '${office_municipality}, ${office_province}' . $m[3] . '</w:t>';
}, $xml);

if ($ssReplacements > 0) {
    $report[] = "Replaced 'Baggao, Cagayan' (SS header): {$ssReplacements} occurrence(s).";
}

// ---------------------------------------------------------------------
// Step 3: Fix Item 5 — "The true and correct FATHER'S FULL NAME is ${value_to}"
//
// ${value_to} is a row-scoped placeholder; outside the corrections_block
// row it never gets filled. We swap in the inline scalars I already added
// to the value bag: ${first_description} and ${first_value_to}.
// ---------------------------------------------------------------------

$item5Replacements = 0;

// Item 5 sentence in this template:
//   <w:r><w:t xml:space="preserve">The true and correct </w:t></w:r>
//   <w:r ...><w:t xml:space="preserve">FATHER'S </w:t></w:r>
//   <w:r ...><w:t>FULL</w:t></w:r>
//   <w:r ...><w:t xml:space="preserve"> NAME</w:t></w:r>
//   <w:r><w:t xml:space="preserve"> is </w:t></w:r>
//   <w:r .../w:rPr><w:t>${value_to}</w:t></w:r>
//
// Replace this whole run-sequence with one paragraph fragment:
//   <w:r><w:t>The true and correct </w:t></w:r>
//   <w:r><w:t>${first_description}</w:t></w:r>
//   <w:r><w:t> is </w:t></w:r>
//   <w:r><w:rPr><w:b/></w:rPr><w:t>${first_value_to}</w:t></w:r>

$pattern = '#<w:r[^>]*>\s*<w:t[^>]*>The true and correct\s*</w:t>\s*</w:r>'
         . '\s*<w:r[^>]*>\s*<w:t[^>]*>FATHER(?:\'|\x{2019})S\s*</w:t>\s*</w:r>'
         . '\s*<w:r[^>]*>\s*<w:t[^>]*>FULL</w:t>\s*</w:r>'
         . '\s*<w:r[^>]*>\s*<w:t[^>]*>\s*NAME</w:t>\s*</w:r>'
         . '\s*<w:r[^>]*>\s*<w:t[^>]*>\s*is\s*</w:t>\s*</w:r>'
         . '\s*<w:r[^>]*>(?:<w:rPr>.*?</w:rPr>)?\s*<w:t[^>]*>\$\{value_to\}</w:t>\s*</w:r>#us';

$xml = preg_replace_callback($pattern, function ($m) use (&$item5Replacements) {
    $item5Replacements++;
    return '<w:r><w:t xml:space="preserve">The true and correct </w:t></w:r>'
         . '<w:r><w:rPr><w:b/></w:rPr><w:t>${first_description}</w:t></w:r>'
         . '<w:r><w:t xml:space="preserve"> is </w:t></w:r>'
         . '<w:r><w:rPr><w:b/></w:rPr><w:t>${first_value_to}</w:t></w:r>';
}, $xml);

if ($item5Replacements > 0) {
    $report[] = "Item 5 sentence (FATHER'S FULL NAME...\${value_to}) -> placeholders: {$item5Replacements} occurrence(s).";
}

// ---------------------------------------------------------------------
// Step 4: Remove the stray "1" before ${supporting_block} in the supporting
// docs grid. The user's earlier table-deletion left a literal "1" that
// shows up as part of the cell rendering.
//
// Pattern: a <w:tc> cell containing JUST "1" as text, followed by another
// cell with ${supporting_block}${item_no}. We can't reliably match across
// cells without more XML context, so we instead delete the trivial run
// in the FIRST cell of the supporting-docs row.
//
// Simpler approach: find the standalone "1" that immediately precedes
// "${supporting_block}" anywhere in the doc and remove it.
// ---------------------------------------------------------------------

$strayOneRemovals = 0;
// Match a <w:t>1</w:t> run that comes shortly before ${supporting_block}
// (within the same <w:tr>). Allow up to ~500 chars of XML between them.
$xml = preg_replace_callback(
    '/(<w:t[^>]*>)1(<\/w:t>)(.{0,800}?\$\{supporting_block\})/s',
    function ($m) use (&$strayOneRemovals) {
        $strayOneRemovals++;
        return $m[1] . $m[2] . $m[3];  // strip the "1"
    },
    $xml
);

if ($strayOneRemovals > 0) {
    $report[] = "Removed stray '1' before \${supporting_block}: {$strayOneRemovals} occurrence(s).";
}

// ---------------------------------------------------------------------
// Step 5: ACTION TAKEN paragraph — "RA 9048" -> "${law}"
//
// Inside the ACTION TAKEN BY THE C/MCR section the original sample text has
// "REQUIREMENTS SETFORTH BY RA 9048,". We swap "RA 9048" -> "${law}" only
// in the context of that specific phrase, to avoid changing other RA 9048
// mentions if any.
// ---------------------------------------------------------------------

$actionLawReplacements = 0;
$xml = preg_replace_callback(
    '/(SETFORTH\s+BY\s+)(RA\s*9048)(?=\s*,)/i',
    function ($m) use (&$actionLawReplacements) {
        $actionLawReplacements++;
        return $m[1] . '${law}';
    },
    $xml
);
if ($actionLawReplacements > 0) {
    $report[] = "ACTION TAKEN 'RA 9048' -> \${law}: {$actionLawReplacements} occurrence(s).";
}

// ---------------------------------------------------------------------
// Step 6: Payment table — replace hardcoded sample values with placeholders
//   0963723       -> ${receipt_number}
//   1,000.00      -> ${fee_amount}
//   07/15/2025    -> ${payment_date}
// ---------------------------------------------------------------------

$payRcpt = 0; $payFee = 0; $payDate = 0;

$xml = preg_replace_callback(
    '/(<w:t[^>]*>)([^<]*?)0963723([^<]*?)(<\/w:t>)/i',
    function ($m) use (&$payRcpt) {
        $payRcpt++;
        return $m[1] . $m[2] . '${receipt_number}' . $m[3] . $m[4];
    },
    $xml
);

$xml = preg_replace_callback(
    '/(<w:t[^>]*>)([^<]*?)1,000\.00([^<]*?)(<\/w:t>)/',
    function ($m) use (&$payFee) {
        $payFee++;
        return $m[1] . $m[2] . '${fee_amount}' . $m[3] . $m[4];
    },
    $xml
);

$xml = preg_replace_callback(
    '/(<w:t[^>]*>)([^<]*?)07\/15\/2025([^<]*?)(<\/w:t>)/',
    function ($m) use (&$payDate) {
        $payDate++;
        return $m[1] . $m[2] . '${payment_date}' . $m[3] . $m[4];
    },
    $xml
);

if ($payRcpt + $payFee + $payDate > 0) {
    $report[] = "Payment table — receipt:{$payRcpt}, fee:{$payFee}, date:{$payDate}";
}

// ---------------------------------------------------------------------
// Step 7: "Municipal Civil Registrar" -> ${mcr_title}
//
// In this template the MCR title is split across two runs:
//   <w:r><w:t>Municipal</w:t></w:r>
//   <w:r ...><w:t xml:space="preserve"> Civil Registrar ...</w:t></w:r>
//
// We collapse both into a single ${mcr_title} run. Two occurrences expected
// (verification block + ACTION TAKEN signature line).
// ---------------------------------------------------------------------

$mcrTitleReplacements = 0;
$pattern = '#<w:r[^>]*>\s*<w:t[^>]*>Municipal</w:t>\s*</w:r>\s*<w:r[^>]*>\s*<w:t[^>]*>\s*Civil Registrar([^<]*)</w:t>\s*</w:r>#s';
$xml = preg_replace_callback($pattern, function ($m) use (&$mcrTitleReplacements) {
    $mcrTitleReplacements++;
    $trailing = $m[1]; // anything after "Civil Registrar" (e.g. trailing space)
    return '<w:r><w:t xml:space="preserve">${mcr_title}' . $trailing . '</w:t></w:r>';
}, $xml);

// Fallback for unsplit form
$pattern = '#<w:t([^>]*)>([^<]*)Municipal\s+Civil\s+Registrar([^<]*)</w:t>#';
$xml = preg_replace_callback($pattern, function ($m) use (&$mcrTitleReplacements) {
    $mcrTitleReplacements++;
    return '<w:t' . $m[1] . '>' . $m[2] . '${mcr_title}' . $m[3] . '</w:t>';
}, $xml);

if ($mcrTitleReplacements > 0) {
    $report[] = "'Municipal Civil Registrar' -> \${mcr_title}: {$mcrTitleReplacements} occurrence(s).";
}

// ---------------------------------------------------------------------
// Persist back to the docx
// ---------------------------------------------------------------------

if ($zip->addFromString('word/document.xml', $xml) === false) {
    $zip->close();
    fwrite(STDERR, "Failed to write document.xml back into zip.\n");
    exit(1);
}
$zip->close();

$newSize = filesize($tplPath);

echo "=== Injection complete ===\n";
echo "File: {$tplPath}\n";
echo "Size: " . round($newSize / 1024, 1) . " KB\n";
echo "XML length before -> after: " . $originalLength . " -> " . strlen($xml) . " chars\n";
echo "\nChanges applied:\n";
foreach ($report as $line) {
    echo "  - {$line}\n";
}
if (empty($report)) {
    echo "  (no changes — file may already be clean)\n";
}

// Verify all final placeholders are well-formed and list them
echo "\nPlaceholders in final document:\n";
preg_match_all('/\$\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $xml, $matches);
$unique = array_unique($matches[0]);
sort($unique);
foreach ($unique as $ph) {
    echo "  {$ph}\n";
}
echo "\nTotal unique placeholders: " . count($unique) . "\n";
