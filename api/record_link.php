<?php
/**
 * Record Link API — Create a double registration link
 * POST: Links two records (1st Registration + 2nd Registration)
 * Auto-detects discrepancies between the two records
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$primary_type = $input['primary_certificate_type'] ?? '';
$primary_id   = (int)($input['primary_certificate_id'] ?? 0);
$dup_type     = $input['duplicate_certificate_type'] ?? '';
$dup_id       = (int)($input['duplicate_certificate_id'] ?? 0);

$valid_types = ['birth', 'marriage', 'death'];
if (!in_array($primary_type, $valid_types) || !in_array($dup_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid certificate type']);
    exit;
}

if ($primary_id <= 0 || $dup_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record IDs']);
    exit;
}

if ($primary_type === $dup_type && $primary_id === $dup_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot link a record to itself']);
    exit;
}

// Check permission
$perm = $primary_type . '_link';
if (!hasPermission($perm)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to link records']);
    exit;
}

// Check neither record is already actively linked
if (is_record_linked($pdo, $primary_id, $primary_type) || is_record_linked($pdo, $dup_id, $dup_type)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'One or both records are already linked to another record. Unlink first.']);
    exit;
}

// Fetch both records for discrepancy detection
$table_map = [
    'birth' => 'certificate_of_live_birth',
    'marriage' => 'certificate_of_marriage',
    'death' => 'certificate_of_death',
];
$primary_table = $table_map[$primary_type] ?? null;
$dup_table = $table_map[$dup_type] ?? null;

if (!$primary_table || !$dup_table) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported certificate type']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM {$primary_table} WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$primary_id]);
    $primary_record = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT * FROM {$dup_table} WHERE id = ? AND status = 'Active' LIMIT 1");
    $stmt2->execute([$dup_id]);
    $dup_record = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$primary_record || !$dup_record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'One or both records not found or not active']);
        exit;
    }

    // Auto-detect discrepancies
    $discrepancies = detect_discrepancies($primary_record, $dup_record, $primary_type);
    $has_discrepancies = !empty($discrepancies);
    $needs_correction = (bool)($input['needs_correction'] ?? false);
    $link_reason = sanitize_input($input['link_reason'] ?? '');

    // Build match_fields from the duplicate detection scoring (re-run)
    $match_score = null;
    $match_fields = null;
    if ($primary_type === 'birth') {
        $duplicates = find_potential_duplicates($pdo, $primary_id, 'birth');
        foreach ($duplicates as $d) {
            if ((int)$d['id'] === $dup_id) {
                $match_score = $d['match_score'];
                $match_fields = $d['match_fields'];
                break;
            }
        }
    }

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }

    // Insert the link
    $pdo->beginTransaction();

    $sql = "INSERT INTO record_links (
                primary_certificate_type, primary_certificate_id,
                duplicate_certificate_type, duplicate_certificate_id,
                link_type, link_reason, match_fields, match_score,
                has_discrepancies, discrepancies, needs_correction,
                linked_by
            ) VALUES (
                :p_type, :p_id, :d_type, :d_id,
                'double_registration', :reason, :match_fields, :match_score,
                :has_disc, :discrepancies, :needs_corr,
                :linked_by
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':p_type'        => $primary_type,
        ':p_id'          => $primary_id,
        ':d_type'        => $dup_type,
        ':d_id'          => $dup_id,
        ':reason'        => $link_reason ?: null,
        ':match_fields'  => $match_fields ? json_encode($match_fields) : null,
        ':match_score'   => $match_score,
        ':has_disc'      => $has_discrepancies ? 1 : 0,
        ':discrepancies' => $has_discrepancies ? json_encode($discrepancies) : null,
        ':needs_corr'    => $needs_correction ? 1 : 0,
        ':linked_by'     => $user_id,
    ]);

    $link_id = $pdo->lastInsertId();

    // Log activity
    $p_reg = $primary_record['registry_no'] ?? 'N/A';
    $d_reg = $dup_record['registry_no'] ?? 'N/A';
    log_activity(
        $pdo,
        'LINK_DOUBLE_REGISTRATION',
        "Linked double registration: 1st Reg #{$p_reg} (ID:{$primary_id}) ↔ 2nd Reg #{$d_reg} (ID:{$dup_id}). Score: {$match_score}%. link_id:{$link_id}",
        $user_id
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Records linked as double registration. 2nd Reg (#{$d_reg}) is now blocked from issuance.",
        'data' => [
            'link_id'          => (int)$link_id,
            'primary_id'       => $primary_id,
            'duplicate_id'     => $dup_id,
            'has_discrepancies' => $has_discrepancies,
            'discrepancy_count' => count($discrepancies),
            'match_score'      => $match_score,
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Record link error: " . $e->getMessage());

    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'These records are already linked.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
