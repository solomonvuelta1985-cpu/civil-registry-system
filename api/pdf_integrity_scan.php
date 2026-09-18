<?php
/**
 * API: Batched PDF Archive Integrity Scan
 * Scans a bounded batch of certificate records and checks if their PDFs exist and are uncorrupted.
 * Results include: ok | corrupt | missing | no_hash
 * Admin access required.
 *
 * POST params:
 *   csrf_token  (string) CSRF token
 *   offset      (int)    Zero-based scan offset
 *   batch_size  (int)    Records per request (25-200)
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required']); exit; }

requireCSRFToken();

// Allow enough time for a bounded batch without holding one request for the whole archive.
set_time_limit(300);

$scanOffset = max(0, (int)($_POST['offset'] ?? 0));
$batchSize  = min(200, max(25, (int)($_POST['batch_size'] ?? 100)));

$tables = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

$results = [];
$counts  = ['total' => 0, 'ok' => 0, 'corrupt' => 0, 'missing' => 0, 'no_hash' => 0];

try {
    // Count candidates once per request so the browser can show reliable progress.
    $countRows = $pdo->query(
        "SELECT 'birth' AS cert_type, COUNT(*) AS total
           FROM certificate_of_live_birth
          WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status != 'Deleted'
         UNION ALL
         SELECT 'death', COUNT(*)
           FROM certificate_of_death
          WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status != 'Deleted'
         UNION ALL
         SELECT 'marriage', COUNT(*)
           FROM certificate_of_marriage
          WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status != 'Deleted'
         UNION ALL
         SELECT 'marriage_license', COUNT(*)
           FROM application_for_marriage_license
          WHERE pdf_filename IS NOT NULL AND pdf_filename != '' AND status != 'Deleted'"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $totalCandidates = array_sum(array_map('intval', $countRows));
    $remainingOffset = $scanOffset;
    $remainingLimit  = $batchSize;

    foreach ($tables as $cert_type => $table) {
        $tableTotal = (int)($countRows[$cert_type] ?? 0);
        if ($remainingOffset >= $tableTotal) {
            $remainingOffset -= $tableTotal;
            continue;
        }
        if ($remainingLimit <= 0) break;

        $tableLimit = min($remainingLimit, $tableTotal - $remainingOffset);
        $stmt = $pdo->prepare(
            "SELECT t.id, t.pdf_filename, t.pdf_hash,
                    (SELECT MAX(b.id)
                       FROM pdf_backups b
                      WHERE b.cert_type = :backup_type AND b.record_id = t.id) AS backup_id
               FROM {$table} t
              WHERE t.pdf_filename IS NOT NULL
                AND t.pdf_filename != ''
                AND t.status != 'Deleted'
              ORDER BY t.id ASC
              LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':backup_type', $cert_type, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $tableLimit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $remainingOffset), PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rec) {
            $counts['total']++;
            $abs_path = resolve_upload_path($rec['pdf_filename']);
            $backup_id = $rec['backup_id'] !== null ? (int)$rec['backup_id'] : null;

            $row = [
                'cert_type'    => $cert_type,
                'record_id'    => (int)$rec['id'],
                'pdf_filename' => $rec['pdf_filename'],
                'stored_hash'  => $rec['pdf_hash'],
                'actual_hash'  => null,
                'status'       => 'ok',
                'has_backup'   => $backup_id !== null,
                'backup_id'    => $backup_id,
            ];

            if (!file_exists($abs_path)) {
                $row['status'] = 'missing';
                $counts['missing']++;
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('PDF_INTEGRITY_FAILURE', 'HIGH',
                        json_encode(['status' => 'missing', 'file' => $rec['pdf_filename'], 'type' => $cert_type]),
                        $_SESSION['user_id'] ?? null);
                }
            } elseif (empty($rec['pdf_hash'])) {
                $row['status'] = 'no_hash';
                $counts['no_hash']++;
            } else {
                $actual = hash_file('sha256', $abs_path);
                $row['actual_hash'] = $actual;
                if (!is_string($actual) || !hash_equals(strtolower($rec['pdf_hash']), strtolower($actual))) {
                    $row['status'] = 'corrupt';
                    $counts['corrupt']++;
                    if (function_exists('logSecurityEvent')) {
                        logSecurityEvent('PDF_INTEGRITY_FAILURE', 'HIGH',
                            json_encode(['status' => 'corrupt', 'file' => $rec['pdf_filename'], 'type' => $cert_type]),
                            $_SESSION['user_id'] ?? null);
                    }
                } else {
                    $counts['ok']++;
                }
            }

            $results[] = $row;
        }

        $remainingLimit -= $tableLimit;
        $remainingOffset = 0;
    }

    $scanned = count($results);
    $nextOffset = $scanOffset + $scanned;

    echo json_encode([
        'success'          => true,
        'counts'           => $counts,
        'results'          => $results,
        'offset'           => $scanOffset,
        'batch_size'       => $batchSize,
        'scanned'          => $scanned,
        'total_candidates' => $totalCandidates,
        'has_more'         => $nextOffset < $totalCandidates && $scanned > 0,
        'next_offset'      => $nextOffset,
    ]);
} catch (PDOException $e) {
    error_log('pdf_integrity_scan batch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The integrity scan could not read the next batch.']);
}
