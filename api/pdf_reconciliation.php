<?php
/** Read-only database-to-PDF reconciliation API. */
require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/pdf_reconciliation.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Authentication required.']); exit; }
if (getUserRole() !== 'Admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Administrator access required.']); exit; }

$action = strtolower(trim((string)($_GET['action'] ?? 'summary')));
$root = realpath(UPLOAD_DIR);
if ($root === false || !is_dir($root)) { http_response_code(409); echo json_encode(['success' => false, 'message' => 'The iSCAN uploads root is unavailable.']); exit; }

try {
    $records = pdf_reconciliation_record_rows($pdo);
    $total = count($records);
    $indexes = pdf_reconciliation_duplicate_indexes($records);

    if ($action === 'summary') {
        echo json_encode(['success' => true, 'root' => $root, 'total_records' => $total, 'generated_at' => date('c')]);
        exit;
    }

    if ($action === 'scan') {
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 250)));
        $page = array_slice($records, $offset, $limit);
        $items = [];
        $counts = ['healthy' => 0, 'missing' => 0, 'corrupt' => 0, 'duplicate' => 0, 'invalid_record' => 0, 'unreadable' => 0];
        $started = microtime(true);
        foreach ($page as $record) {
            $item = pdf_reconciliation_classify_record($root, $record);
            if (in_array($item['status'], ['healthy', 'missing', 'corrupt'], true)) $item = pdf_reconciliation_apply_duplicates($item, $indexes);
            $counts[$item['status']] = ($counts[$item['status']] ?? 0) + 1;
            $items[] = $item;
        }
        $next = $offset + count($page);
        echo json_encode(['success' => true, 'root' => $root, 'offset' => $offset, 'limit' => $limit, 'processed' => $next, 'total_records' => $total, 'has_more' => $next < $total, 'elapsed_ms' => (int)round((microtime(true) - $started) * 1000), 'counts' => $counts, 'items' => $items]);
        exit;
    }

    if ($action === 'orphans') {
        $expectedPaths = [];
        foreach ($records as $record) { $path = str_replace('\\', '/', ltrim((string)($record['pdf_filename'] ?? ''), '/')); if ($path !== '') $expectedPaths[$path] = true; }
        $started = microtime(true); $items = pdf_reconciliation_scan_orphans($root, $expectedPaths);
        echo json_encode(['success' => true, 'root' => $root, 'processed' => count($items), 'counts' => ['orphan' => count($items), 'invalid_pdf' => count(array_filter($items, static fn(array $item): bool => $item['status'] === 'invalid_pdf'))], 'elapsed_ms' => (int)round((microtime(true) - $started) * 1000), 'items' => $items]);
        exit;
    }

    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Unsupported reconciliation action.']);
} catch (Throwable $e) {
    error_log('pdf_reconciliation error: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Reconciliation scan failed: ' . $e->getMessage()]);
}
