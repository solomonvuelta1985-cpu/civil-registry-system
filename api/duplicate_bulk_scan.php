<?php
/**
 * Duplicate Bulk Scan API
 * POST: Batch scan all records for duplicates (admin only, AJAX paginated)
 * Scans records in batches and returns unlinked duplicates above threshold
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Admin only
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$certificate_type = sanitize_input($input['type'] ?? 'birth');
$batch_offset = max(0, (int)($input['offset'] ?? 0));
$batch_size = min(50, max(1, (int)($input['batch_size'] ?? 20)));
$min_score = max(40, min(100, (float)($input['min_score'] ?? 70)));

if ($certificate_type !== 'birth') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only birth type supported currently']);
    exit;
}

try {
    // Count total active records
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM certificate_of_live_birth WHERE status = 'Active'");
    $total_records = (int)$total_stmt->fetchColumn();

    // Fetch a batch of records
    $stmt = $pdo->prepare(
        "SELECT id, registry_no, child_first_name, child_last_name
         FROM certificate_of_live_birth
         WHERE status = 'Active'
         ORDER BY id ASC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $batch_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $batch_offset, PDO::PARAM_INT);
    $stmt->execute();
    $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $scanned = 0;

    foreach ($batch as $record) {
        $scanned++;
        $record_id = (int)$record['id'];

        // Skip if already linked
        if (is_record_linked($pdo, $record_id, 'birth')) {
            continue;
        }

        // Find duplicates
        $duplicates = find_potential_duplicates($pdo, $record_id, 'birth');

        // Filter by minimum score and exclude already-linked candidates
        $matches = [];
        foreach ($duplicates as $dup) {
            if ($dup['match_score'] >= $min_score && !is_record_linked($pdo, $dup['id'], 'birth')) {
                $matches[] = $dup;
            }
        }

        if (!empty($matches)) {
            $results[] = [
                'source_id' => $record_id,
                'source_registry_no' => $record['registry_no'] ?? '',
                'source_name' => trim(($record['child_first_name'] ?? '') . ' ' . ($record['child_last_name'] ?? '')),
                'matches' => $matches,
            ];
        }
    }

    $has_more = ($batch_offset + $batch_size) < $total_records;

    echo json_encode([
        'success' => true,
        'total_records' => $total_records,
        'batch_offset' => $batch_offset,
        'batch_size' => $batch_size,
        'scanned' => $scanned,
        'has_more' => $has_more,
        'next_offset' => $has_more ? $batch_offset + $batch_size : null,
        'results' => $results,
        'result_count' => count($results),
    ]);

} catch (Exception $e) {
    error_log("Bulk scan error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error during bulk scan']);
}
