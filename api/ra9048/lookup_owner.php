<?php
/**
 * RA 9048 — Document Owner Lookup
 *
 * Searches existing scanned Certificates of Live Birth in iscan_db and returns
 * a payload the petition form can use to pre-fill the Document Owner section.
 *
 * Query params:
 *   q        Free-text query (matches registry_no OR child name parts). Min 2 chars.
 *   limit    Optional max results (default 10, max 25).
 *
 * Response shape:
 *   { success: true, message: "OK", data: [
 *       { id, registry_no, document_owner_names, owner_dob,
 *         owner_birthplace_city, owner_birthplace_province, owner_birthplace_country,
 *         father_full_name, mother_full_name, display_label },
 *       ...
 *   ] }
 *
 * Only birth records (COLB) are searched in this phase.
 */

require_once '../../includes/config_ra9048.php';   // pulls config.php → $pdo, helpers
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

header('Content-Type: application/json');

requireAuth();

// Read-only endpoint — accept GET (and POST as a fallback in case the form sends it that way)
$q = sanitize_input($_GET['q'] ?? $_POST['q'] ?? '');
$q = trim($q);

$limit = (int) ($_GET['limit'] ?? $_POST['limit'] ?? 10);
if ($limit < 1)  $limit = 10;
if ($limit > 25) $limit = 25;

if ($q === '' || mb_strlen($q) < 2) {
    json_response(true, 'Type at least 2 characters to search.', []);
}

try {
    /*
     * Search strategy:
     *   - Exact-ish match on registry_no (LIKE q% — registries are short numeric strings)
     *   - Token match on child name: split q into tokens, each token must appear in
     *     first/middle/last name (case-insensitive). This handles
     *       "MEJIA"           → finds anyone named MEJIA
     *       "LOWELYN MEJIA"   → finds owners with both tokens
     *       "MEJIA LOWELYN"   → same (order-insensitive)
     *
     *   We rank registry_no matches above name matches.
     */
    $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

    $params = [];
    $whereParts = [];

    /*
     * Note on placeholders:
     * The PDO connection runs with ATTR_EMULATE_PREPARES = false (see
     * includes/config_ra9048.php). In that mode each :name may appear at most
     * ONCE in the SQL — so we bind two separate placeholders for the same
     * registry-prefix value (one for WHERE, one for ORDER BY).
     */
    $params[':reg_q']     = $q . '%';
    $params[':reg_q_ord'] = $q . '%';
    $regClause = "registry_no LIKE :reg_q";

    // Per-token name matching — bind a separate placeholder per OR branch
    $tokenClauses = [];
    foreach ($tokens as $i => $tok) {
        $kFirst  = ":tok{$i}_first";
        $kMid    = ":tok{$i}_mid";
        $kLast   = ":tok{$i}_last";
        $val     = '%' . $tok . '%';
        $params[$kFirst] = $val;
        $params[$kMid]   = $val;
        $params[$kLast]  = $val;
        $tokenClauses[] = "(child_first_name LIKE {$kFirst}"
                       . " OR child_middle_name LIKE {$kMid}"
                       . " OR child_last_name LIKE {$kLast})";
    }
    $nameClause = $tokenClauses ? '(' . implode(' AND ', $tokenClauses) . ')' : '';

    $whereParts[] = $regClause;
    if ($nameClause !== '') $whereParts[] = $nameClause;

    $where = "(" . implode(' OR ', $whereParts) . ") AND status = 'Active'";

    $sql = "SELECT
                id, registry_no,
                child_first_name, child_middle_name, child_last_name,
                child_date_of_birth, child_place_of_birth, barangay,
                mother_first_name, mother_middle_name, mother_last_name,
                father_first_name, father_middle_name, father_last_name
            FROM certificate_of_live_birth
            WHERE {$where}
            ORDER BY
                CASE WHEN registry_no LIKE :reg_q_ord THEN 0 ELSE 1 END,
                child_last_name ASC, child_first_name ASC
            LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $r) {
        $owner = trim(implode(' ', array_filter([
            $r['child_first_name']  ?? '',
            $r['child_middle_name'] ?? '',
            $r['child_last_name']   ?? '',
        ])));

        $father = trim(implode(' ', array_filter([
            $r['father_first_name']  ?? '',
            $r['father_middle_name'] ?? '',
            $r['father_last_name']   ?? '',
        ])));

        $mother = trim(implode(' ', array_filter([
            $r['mother_first_name']  ?? '',
            $r['mother_middle_name'] ?? '',
            $r['mother_last_name']   ?? '',
        ])));

        // Try to split child_place_of_birth into city/province if it looks like "CITY, PROVINCE".
        $city = $province = '';
        $place = trim((string) ($r['child_place_of_birth'] ?? ''));
        if ($place !== '') {
            $parts = array_map('trim', explode(',', $place));
            $city     = $parts[0] ?? '';
            $province = isset($parts[1]) ? $parts[1] : '';
        }
        if ($city === '' && !empty($r['barangay'])) {
            $city = trim((string) $r['barangay']);
        }

        $label = $owner;
        if (!empty($r['registry_no'])) $label .= ' — Reg #' . $r['registry_no'];
        if (!empty($r['child_date_of_birth'])) $label .= ' (' . $r['child_date_of_birth'] . ')';

        $results[] = [
            'id'                          => (int) $r['id'],
            'registry_no'                 => $r['registry_no'] ?? '',
            'document_owner_names'        => strtoupper($owner),
            'owner_dob'                   => $r['child_date_of_birth'] ?? '',
            'owner_birthplace_city'       => strtoupper($city),
            'owner_birthplace_province'   => strtoupper($province),
            'owner_birthplace_country'    => 'PHILIPPINES',
            'father_full_name'            => strtoupper($father),
            'mother_full_name'            => strtoupper($mother),
            'display_label'               => $label,
        ];
    }

    json_response(true, 'OK', $results);

} catch (PDOException $e) {
    error_log('RA9048 lookup_owner error: ' . $e->getMessage());
    json_response(false, 'Lookup failed. Please try again.', null, 500);
}
