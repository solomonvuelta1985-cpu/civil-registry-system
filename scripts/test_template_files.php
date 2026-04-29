<?php
/**
 * Diagnostic for Phase 4b — checks template files and a sample petition's generated docs.
 * Visit: http://localhost/iscan/scripts/test_template_files.php
 */

require_once __DIR__ . '/../includes/config_ra9048.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== RA 9048 Template & Generated-Doc Diagnostic ===\n\n";

// 1. Templates folder
echo "1. Templates folder: " . RA9048_TEMPLATES_PATH . "\n";
if (!is_dir(RA9048_TEMPLATES_PATH)) {
    echo "   ✗ MISSING — folder does not exist.\n\n";
} else {
    $files = glob(RA9048_TEMPLATES_PATH . '*.*');
    echo "   ✓ exists. Files (" . count($files) . "):\n";
    foreach ($files as $f) {
        $name = basename($f);
        $size = filesize($f);
        $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $kb   = round($size / 1024, 1);
        $usable = ($ext === 'docx') ? '  (engine-ready)' : '  (need to convert to .docx)';
        echo "     • {$name}  [{$kb} KB]{$usable}\n";
    }
    echo "\n";
}

// 2. Latest petition with generated docs
echo "2. Latest 5 petitions:\n";
try {
    $rows = $pdo_ra->query(
        "SELECT id, petition_number, petition_subtype, document_owner_names, created_at
         FROM petitions WHERE status='Active' ORDER BY id DESC LIMIT 5"
    )->fetchAll();
    if (!$rows) {
        echo "   (no active petitions yet — create one to test)\n\n";
    } else {
        foreach ($rows as $r) {
            $genDir = RA9048_UPLOAD_PATH . 'generated/petition_' . $r['id'] . '/';
            $exists = is_dir($genDir);
            $files  = $exists ? glob($genDir . '*.docx') : [];
            echo "   #{$r['id']}  {$r['petition_number']}  ({$r['petition_subtype']})  "
                . "owner=" . substr($r['document_owner_names'] ?? '', 0, 30) . "\n";
            echo "       generated dir: " . ($exists ? "EXISTS" : "(none)") . "\n";
            if ($files) {
                foreach ($files as $f) {
                    echo "         - " . basename($f) . " [" . round(filesize($f)/1024, 1) . " KB]\n";
                }
            } else if ($exists) {
                echo "         (folder empty)\n";
            }
        }
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. Upload path: " . RA9048_UPLOAD_PATH . "\n";
echo "   exists: " . (is_dir(RA9048_UPLOAD_PATH) ? "yes" : "NO") . "\n";
echo "   writable: " . (is_writable(RA9048_UPLOAD_PATH) ? "yes" : "NO") . "\n";

echo "\nDone.\n";
