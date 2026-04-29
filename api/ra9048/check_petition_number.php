<?php
/**
 * RA 9048 — Petition Number Uniqueness Check
 *
 * Quick endpoint the form calls on blur of the petition-number field to warn
 * the admin before they spend time encoding a duplicate.
 *
 * Query params:
 *   number      The fully composed number, e.g. "CCE-0130-2025" or "CFN-0005-2024".
 *   exclude_id  Optional petition.id to exclude (so editing a record doesn't flag itself).
 *
 * Response:
 *   { success: true, message: "...", data: { available: bool, existing_id: ?int } }
 */

require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

header('Content-Type: application/json');

requireAuth();

$number     = sanitize_input($_GET['number'] ?? $_POST['number'] ?? '');
$excludeId  = (int) ($_GET['exclude_id'] ?? $_POST['exclude_id'] ?? 0);

$number = trim($number);

if ($number === '') {
    json_response(true, 'Empty.', ['available' => true, 'existing_id' => null]);
}

// Format guard — same regex used by the save/update validators.
if (!preg_match('/^(CCE|CFN)-\d{1,6}-\d{4}$/i', $number)) {
    json_response(true, 'Invalid format.', [
        'available'   => false,
        'existing_id' => null,
        'reason'      => 'invalid_format',
    ]);
}

try {
    if ($excludeId > 0) {
        $stmt = $pdo_ra->prepare(
            "SELECT id FROM petitions WHERE petition_number = :n AND id <> :id LIMIT 1"
        );
        $stmt->execute([':n' => $number, ':id' => $excludeId]);
    } else {
        $stmt = $pdo_ra->prepare("SELECT id FROM petitions WHERE petition_number = :n LIMIT 1");
        $stmt->execute([':n' => $number]);
    }
    $row = $stmt->fetch();

    json_response(true, 'OK', [
        'available'   => !$row,
        'existing_id' => $row ? (int) $row['id'] : null,
    ]);

} catch (PDOException $e) {
    error_log('RA9048 check_petition_number error: ' . $e->getMessage());
    json_response(false, 'Lookup failed.', null, 500);
}
