<?php
/** Emit active-record PDF hash conflicts as CSV for manual review. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$sql = "SELECT pdf_hash, cert_type, record_id, registry_no, pdf_filename FROM (
    SELECT pdf_hash, 'birth' cert_type, id record_id, registry_no, pdf_filename
      FROM certificate_of_live_birth
     WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
    UNION ALL
    SELECT pdf_hash, 'death', id, registry_no, pdf_filename
      FROM certificate_of_death
     WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
    UNION ALL
    SELECT pdf_hash, 'marriage', id, registry_no, pdf_filename
      FROM certificate_of_marriage
     WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
    UNION ALL
    SELECT pdf_hash, 'marriage_license', id, registry_no, pdf_filename
      FROM application_for_marriage_license
     WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
) records
WHERE pdf_hash IN (
    SELECT pdf_hash FROM (
        SELECT pdf_hash FROM certificate_of_live_birth WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
        UNION ALL SELECT pdf_hash FROM certificate_of_death WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
        UNION ALL SELECT pdf_hash FROM certificate_of_marriage WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
        UNION ALL SELECT pdf_hash FROM application_for_marriage_license WHERE status='Active' AND pdf_hash IS NOT NULL AND pdf_hash<>''
    ) hashes GROUP BY pdf_hash HAVING COUNT(*) > 1
)
ORDER BY pdf_hash, cert_type, record_id";

$stmt = $pdo->query($sql);
$out = fopen('php://stdout', 'w');
fputcsv($out, ['pdf_hash', 'cert_type', 'record_id', 'registry_no', 'pdf_filename']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}

