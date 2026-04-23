<?php
/**
 * Record Link Details API
 * GET: Returns link status, discrepancies, and paired record info for a given record
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$record_id = (int)($_GET['id'] ?? 0);
$certificate_type = sanitize_input($_GET['type'] ?? 'birth');

if ($record_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

$valid_types = ['birth', 'marriage', 'death'];
if (!in_array($certificate_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid certificate type']);
    exit;
}

try {
    $link = get_record_link_status($pdo, $record_id, $certificate_type);

    if (!$link) {
        echo json_encode([
            'success' => true,
            'linked' => false,
            'link' => null,
        ]);
        exit;
    }

    // Determine the paired record
    if ($link['role'] === 'primary') {
        $paired_id = (int)$link['duplicate_certificate_id'];
        $paired_type = $link['duplicate_certificate_type'];
    } else {
        $paired_id = (int)$link['primary_certificate_id'];
        $paired_type = $link['primary_certificate_type'];
    }

    // Fetch paired record's registry_no
    $table_map = [
        'birth' => 'certificate_of_live_birth',
        'marriage' => 'certificate_of_marriage',
        'death' => 'certificate_of_death',
    ];
    $paired_table = $table_map[$paired_type] ?? null;
    $paired_registry_no = '';
    if ($paired_table) {
        $stmt = $pdo->prepare("SELECT registry_no FROM {$paired_table} WHERE id = ? LIMIT 1");
        $stmt->execute([$paired_id]);
        $paired_registry_no = $stmt->fetchColumn() ?: '';
    }

    // Fetch linked_by user name
    $linked_by_name = '';
    if ($link['linked_by']) {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$link['linked_by']]);
        $linked_by_name = $stmt->fetchColumn() ?: '';
    }

    // Parse JSON fields
    $discrepancies = $link['discrepancies'] ? json_decode($link['discrepancies'], true) : [];
    $match_fields = $link['match_fields'] ? json_decode($link['match_fields'], true) : [];

    echo json_encode([
        'success' => true,
        'linked' => true,
        'link' => [
            'id'                 => (int)$link['id'],
            'role'               => $link['role'],
            'paired_id'          => $paired_id,
            'paired_type'        => $paired_type,
            'paired_registry_no' => $paired_registry_no,
            'match_score'        => $link['match_score'],
            'match_fields'       => $match_fields,
            'has_discrepancies'  => (bool)$link['has_discrepancies'],
            'discrepancies'      => $discrepancies,
            'needs_correction'   => (bool)$link['needs_correction'],
            'correction_status'  => $link['correction_status'],
            'correction_notes'   => $link['correction_notes'] ?? '',
            'link_reason'        => $link['link_reason'] ?? '',
            'linked_by'          => (int)$link['linked_by'],
            'linked_by_name'     => $linked_by_name,
            'linked_at'          => $link['linked_at'],
            'status'             => $link['status'],
        ],
    ]);

} catch (PDOException $e) {
    error_log("Record link details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
