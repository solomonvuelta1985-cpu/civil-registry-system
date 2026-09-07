<?php
/** Lightweight CLI regression checks for the audit remediations. */
declare(strict_types=1);
$root = dirname(__DIR__);
$checks = [
    ['OCR requires auth', 'api/ocr_process.php', ['requireAuth();', 'requireCSRFToken();']],
    ['OCR shell args are quoted', 'includes/TesseractOCR.php', ['escapeshellarg', 'random_bytes']],
    ['Export sort is allowlisted', 'public/export.php', ['$sort_allowlist', 'spreadsheet_safe_value']],
    ['Sessions rotate and revalidate', 'includes/auth.php', ['session_regenerate_id(true)', 'password_fingerprint']],
    ['PDF is record-bound', 'api/serve_pdf.php', ['$recordFound', 'status = \'Active\'']],
    ['Private upload root', '.htaccess', ['RewriteRule ^uploads', '(^\\.']],
    ['Scanner has pairing token', 'scanner_service/scanner_service.py', ['ISCAN_SCANNER_TOKEN', 'X-Scanner-Token']],
];
$failed = [];
foreach ($checks as [$label, $file, $needles]) {
    $source = @file_get_contents($root . DIRECTORY_SEPARATOR . $file);
    if ($source === false) { $failed[] = "$label (file missing)"; continue; }
    foreach ($needles as $needle) {
        if (strpos($source, $needle) === false) $failed[] = "$label (missing {$needle})";
    }
}
if ($failed) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo 'security regression checks passed (' . count($checks) . ' groups)' . PHP_EOL;
