<?php
/**
 * API: Full PDF Archive Integrity Scan
 * Scans all certificate records and checks if their PDFs exist and are uncorrupted.
 * Results include: ok | corrupt | missing | no_hash
 * Admin access required.
 *
 * POST params:
 *   csrf_token  (string) CSRF token
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

// Allow longer execution for large archives
set_time_limit(300);

$tables = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];

$results = [];
$counts  = ['total' => 0, 'ok' => 0, 'corrupt' => 0, 'missing' => 0, 'no_hash' => 0];

// Fetch backups available per cert_type+record_id for quick lookup
try {
    $backupStmt = $pdo->query(
        "SELECT cert_type, record_id, MAX(id) AS backup_id
           FROM pdf_backups
          GROUP BY cert_type, record_id"
    );
    $backupMap = [];
    foreach ($backupStmt->fetchAll() as $b) {
        $backupMap[$b['cert_type'] . '_' . $b['record_id']] = $b['backup_id'];
    }
} catch (PDOException $e) {
    $backupMap = [];
}

foreach ($tables as $cert_type => $table) {
    try {
        $stmt = $pdo->query(
            "SELECT id, pdf_filename, pdf_hash
               FROM {$table}
              WHERE pdf_filename IS NOT NULL
                AND pdf_filename != ''
                AND status != 'Deleted'"
        );
        $records = $stmt->fetchAll();

        foreach ($records as $rec) {
            $counts['total']++;
            $abs_path   = UPLOAD_DIR . $rec['pdf_filename'];
            $backup_key = $cert_type . '_' . $rec['id'];
            $has_backup = isset($backupMap[$backup_key]);
            $backup_id  = $backupMap[$backup_key] ?? null;

            $row = [
                'cert_type'    => $cert_type,
                'record_id'    => $rec['id'],
                'pdf_filename' => $rec['pdf_filename'],
                'stored_hash'  => $rec['pdf_hash'],
                'actual_hash'  => null,
                'status'       => 'ok',
                'has_backup'   => $has_backup,
                'backup_id'    => $backup_id,
            ];

            if (!file_exists($abs_path)) {
                $row['status'] = 'missing';
                $counts['missing']++;
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('PDF_INTEGRITY_FAILURE', 'HIGH', $_SESSION['user_id'] ?? null,
                        json_encode(['status' => 'missing', 'file' => $rec['pdf_filename'], 'type' => $cert_type]));
                }
            } elseif (empty($rec['pdf_hash'])) {
                $row['status'] = 'no_hash';
                $counts['no_hash']++;
            } else {
                $actual = hash_file('sha256', $abs_path);
                $row['actual_hash'] = $actual;
                if (!hash_equals($rec['pdf_hash'], $actual)) {
                    $row['status'] = 'corrupt';
                    $counts['corrupt']++;
                    if (function_exists('logSecurityEvent')) {
                        logSecurityEvent('PDF_INTEGRITY_FAILURE', 'HIGH', $_SESSION['user_id'] ?? null,
                            json_encode(['status' => 'corrupt', 'file' => $rec['pdf_filename'], 'type' => $cert_type]));
                    }
                } else {
                    $counts['ok']++;
                }
            }

            $results[] = $row;
        }
    } catch (PDOException $e) {
        error_log('pdf_integrity_scan error for ' . $table . ': ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'counts'  => $counts,
    'results' => $results,
]);
