<?php
/**
 * Family Relations API
 * Returns siblings, parents' marriage record, and parent death records
 * for a given birth certificate. Read-only aggregation over existing data.
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

if (!hasPermission('birth_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($record_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid record id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = ? LIMIT 1");
    $stmt->execute([$record_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Birth record not found']);
        exit;
    }

    $father_first = trim($source['father_first_name'] ?? '');
    $father_mid   = trim($source['father_middle_name'] ?? '');
    $father_last  = trim($source['father_last_name'] ?? '');
    $mother_first = trim($source['mother_first_name'] ?? '');
    $mother_mid   = trim($source['mother_middle_name'] ?? '');
    $mother_last  = trim($source['mother_last_name'] ?? '');

    $father_filled = ($father_first !== '' || $father_last !== '');
    $mother_filled = ($mother_first !== '' || $mother_last !== '');

    $marriage_date  = trim($source['date_of_marriage'] ?? '');
    $marriage_place = trim($source['place_of_marriage'] ?? '');
    $marriage_stated = ($marriage_date !== '');

    // Find any other birth records that are linked to this one as
    // double-registrations (per PSA MC 2019-23). Those are the *same person*,
    // not siblings, and must be excluded from the sibling list.
    $linked_birth_ids = fr_get_linked_birth_ids($pdo, $record_id);

    // ---- 1. SIBLINGS ----
    $siblings = [];
    $siblings_label = '';
    [$siblings, $siblings_label] = fr_find_siblings(
        $pdo, $record_id,
        $father_first, $father_mid, $father_last, $father_filled,
        $mother_first, $mother_mid, $mother_last, $mother_filled,
        $linked_birth_ids
    );

    // ---- 2. PARENTS' MARRIAGE ----
    $parents_marriage = ['stated' => $marriage_stated, 'matched' => []];
    if ($marriage_stated) {
        $parents_marriage['date']  = $marriage_date;
        $parents_marriage['place'] = $marriage_place;
        $parents_marriage['matched'] = fr_find_marriage(
            $pdo, $marriage_date,
            $father_first, $father_mid, $father_last,
            $mother_first, $mother_mid, $mother_last
        );
    }

    // ---- 3. PARENT DEATH RECORDS ----
    $parent_deaths = ['father' => [], 'mother' => []];
    if ($father_filled) {
        $parent_deaths['father'] = fr_find_deaths($pdo, $father_first, $father_mid, $father_last);
    }
    if ($mother_filled) {
        $parent_deaths['mother'] = fr_find_deaths($pdo, $mother_first, $mother_mid, $mother_last);
    }

    $child_full_name = trim(
        ($source['child_first_name'] ?? '') . ' ' .
        ($source['child_middle_name'] ?? '') . ' ' .
        ($source['child_last_name'] ?? '')
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'source' => [
                'id'                  => (int)$source['id'],
                'registry_no'         => $source['registry_no'] ?? '',
                'child_name'          => $child_full_name !== '' ? $child_full_name : '(unnamed)',
                'child_date_of_birth' => $source['child_date_of_birth'] ?? null,
                'father_filled'       => $father_filled,
                'father_name'         => $father_filled ? trim("$father_first $father_mid $father_last") : '',
                'mother_filled'       => $mother_filled,
                'mother_name'         => $mother_filled ? trim("$mother_first $mother_mid $mother_last") : '',
                'marriage_stated'     => $marriage_stated,
                'date_of_marriage'    => $marriage_date,
                'place_of_marriage'   => $marriage_place,
            ],
            'siblings_label'   => $siblings_label,
            'siblings'         => $siblings,
            'parents_marriage' => $parents_marriage,
            'parent_deaths'    => $parent_deaths,
        ],
    ]);
} catch (Throwable $e) {
    error_log('family_relations error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

// ---- helpers ----

function fr_confidence($percent) {
    if ($percent >= 95) return 'high';
    if ($percent >= 85) return 'medium';
    return 'low';
}

function fr_name_similarity($a, $b) {
    $a = mb_strtoupper(trim($a));
    $b = mb_strtoupper(trim($b));
    if ($a === '' || $b === '') return 0.0;
    similar_text($a, $b, $pct);
    return (float)$pct;
}

function fr_score_pair($srcFirst, $srcMid, $srcLast, $candFirst, $candMid, $candLast) {
    // Returns ['score' => avg of available comparisons, 'matched_count' => int]
    $parts = [];
    if ($srcLast !== '' && $candLast !== '') $parts[] = fr_name_similarity($srcLast, $candLast);
    if ($srcFirst !== '' && $candFirst !== '') $parts[] = fr_name_similarity($srcFirst, $candFirst);
    if ($srcMid !== '' && $candMid !== '') $parts[] = fr_name_similarity($srcMid, $candMid);
    if (empty($parts)) return ['score' => 0.0, 'matched_count' => 0];
    return [
        'score' => array_sum($parts) / count($parts),
        'matched_count' => count($parts),
    ];
}

function fr_get_linked_birth_ids($pdo, $sourceId) {
    // Returns IDs of any birth records linked to $sourceId via record_links
    // (active links only). Both directions covered: source-as-primary and
    // source-as-duplicate. These represent the SAME person, not siblings.
    $sql = "SELECT primary_certificate_id AS linked_id
              FROM record_links
             WHERE status = 'active'
               AND duplicate_certificate_type = 'birth'
               AND duplicate_certificate_id = :sid
               AND primary_certificate_type = 'birth'
            UNION
            SELECT duplicate_certificate_id AS linked_id
              FROM record_links
             WHERE status = 'active'
               AND primary_certificate_type = 'birth'
               AND primary_certificate_id = :sid2
               AND duplicate_certificate_type = 'birth'";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid' => $sourceId, ':sid2' => $sourceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    } catch (Throwable $e) {
        // record_links table may not exist on older deployments — degrade gracefully
        error_log('fr_get_linked_birth_ids: ' . $e->getMessage());
        return [];
    }
}

function fr_find_siblings($pdo, $sourceId,
        $fFirst, $fMid, $fLast, $fatherFilled,
        $mFirst, $mMid, $mLast, $motherFilled,
        array $excludeIds = []) {

    $label = '';
    $useFather = $fatherFilled && $fLast !== '';
    $useMother = $motherFilled && $mLast !== '';

    if (!$useFather && !$useMother) {
        return [[], ''];
    }

    // Build a lookup set of IDs to skip (linked double-registrations of source)
    $skip = [];
    foreach ($excludeIds as $eid) { $skip[(int)$eid] = true; }

    if ($useFather && $useMother) {
        $label = 'Siblings';
        $sql = "SELECT * FROM certificate_of_live_birth
                WHERE id != :sid AND status = 'Active'
                  AND LOWER(TRIM(father_last_name)) = :flast
                  AND LOWER(TRIM(mother_last_name)) = :mlast
                LIMIT 50";
        $params = [
            ':sid'   => $sourceId,
            ':flast' => mb_strtolower($fLast),
            ':mlast' => mb_strtolower($mLast),
        ];
    } elseif ($useMother) {
        $label = 'Maternal Siblings';
        $sql = "SELECT * FROM certificate_of_live_birth
                WHERE id != :sid AND status = 'Active'
                  AND LOWER(TRIM(mother_last_name)) = :mlast
                LIMIT 50";
        $params = [':sid' => $sourceId, ':mlast' => mb_strtolower($mLast)];
    } else {
        $label = 'Paternal Siblings';
        $sql = "SELECT * FROM certificate_of_live_birth
                WHERE id != :sid AND status = 'Active'
                  AND LOWER(TRIM(father_last_name)) = :flast
                LIMIT 50";
        $params = [':sid' => $sourceId, ':flast' => mb_strtolower($fLast)];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($candidates as $c) {
        // Skip records that are linked to source as double-registrations
        // (same person, not a sibling).
        if (isset($skip[(int)$c['id']])) continue;

        $cFFirst = trim($c['father_first_name'] ?? '');
        $cFMid   = trim($c['father_middle_name'] ?? '');
        $cFLast  = trim($c['father_last_name'] ?? '');
        $cMFirst = trim($c['mother_first_name'] ?? '');
        $cMMid   = trim($c['mother_middle_name'] ?? '');
        $cMLast  = trim($c['mother_last_name'] ?? '');

        $scores = [];
        if ($useFather) {
            $f = fr_score_pair($fFirst, $fMid, $fLast, $cFFirst, $cFMid, $cFLast);
            if ($f['matched_count'] === 0 || $f['score'] < 85) continue;
            $scores[] = $f['score'];
        }
        if ($useMother) {
            $m = fr_score_pair($mFirst, $mMid, $mLast, $cMFirst, $cMMid, $cMLast);
            if ($m['matched_count'] === 0 || $m['score'] < 85) continue;
            $scores[] = $m['score'];
        }

        $score = array_sum($scores) / count($scores);
        $childName = trim(($c['child_first_name'] ?? '') . ' ' . ($c['child_middle_name'] ?? '') . ' ' . ($c['child_last_name'] ?? ''));

        $results[] = [
            'id'           => (int)$c['id'],
            'type'         => 'birth',
            'registry_no'  => $c['registry_no'] ?? '',
            'display_name' => $childName !== '' ? $childName : '(unnamed)',
            'date'         => $c['child_date_of_birth'] ?? null,
            'date_label'   => 'Born',
            'score'        => round($score, 2),
            'confidence'   => fr_confidence($score),
        ];
    }

    usort($results, function($a, $b) { return $b['score'] <=> $a['score']; });
    return [array_slice($results, 0, 20), $label];
}

function fr_find_marriage($pdo, $marriageDate,
        $fFirst, $fMid, $fLast,
        $mFirst, $mMid, $mLast) {

    $hasHusband = ($fFirst !== '' || $fLast !== '');
    $hasWife    = ($mFirst !== '' || $mLast !== '');
    if (!$hasHusband || !$hasWife || $marriageDate === '') return [];

    $sql = "SELECT * FROM certificate_of_marriage
            WHERE status = 'Active' AND date_of_marriage = :dom
              AND LOWER(TRIM(husband_last_name)) = :hlast
              AND LOWER(TRIM(wife_last_name)) = :wlast
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dom'   => $marriageDate,
        ':hlast' => mb_strtolower($fLast),
        ':wlast' => mb_strtolower($mLast),
    ]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($candidates as $c) {
        $h = fr_score_pair($fFirst, $fMid, $fLast,
            $c['husband_first_name'] ?? '', $c['husband_middle_name'] ?? '', $c['husband_last_name'] ?? '');
        $w = fr_score_pair($mFirst, $mMid, $mLast,
            $c['wife_first_name'] ?? '', $c['wife_middle_name'] ?? '', $c['wife_last_name'] ?? '');

        if ($h['matched_count'] === 0 || $w['matched_count'] === 0) continue;
        if ($h['score'] < 85 || $w['score'] < 85) continue;

        $score = ($h['score'] + $w['score']) / 2;
        $husband = trim(($c['husband_first_name'] ?? '') . ' ' . ($c['husband_middle_name'] ?? '') . ' ' . ($c['husband_last_name'] ?? ''));
        $wife    = trim(($c['wife_first_name'] ?? '') . ' ' . ($c['wife_middle_name'] ?? '') . ' ' . ($c['wife_last_name'] ?? ''));

        $results[] = [
            'id'           => (int)$c['id'],
            'type'         => 'marriage',
            'registry_no'  => $c['registry_no'] ?? '',
            'display_name' => "{$husband} & {$wife}",
            'date'         => $c['date_of_marriage'] ?? null,
            'date_label'   => 'Married',
            'place'        => $c['place_of_marriage'] ?? '',
            'score'        => round($score, 2),
            'confidence'   => fr_confidence($score),
        ];
    }

    usort($results, function($a, $b) { return $b['score'] <=> $a['score']; });
    return $results;
}

function fr_find_deaths($pdo, $first, $mid, $last) {
    if ($last === '' && $first === '') return [];

    if ($last !== '') {
        $sql = "SELECT * FROM certificate_of_death
                WHERE status = 'Active'
                  AND LOWER(TRIM(deceased_last_name)) = :last
                LIMIT 50";
        $params = [':last' => mb_strtolower($last)];
    } else {
        $sql = "SELECT * FROM certificate_of_death
                WHERE status = 'Active'
                  AND LOWER(TRIM(deceased_first_name)) = :first
                LIMIT 50";
        $params = [':first' => mb_strtolower($first)];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($candidates as $c) {
        $cmp = fr_score_pair($first, $mid, $last,
            $c['deceased_first_name'] ?? '', $c['deceased_middle_name'] ?? '', $c['deceased_last_name'] ?? '');
        if ($cmp['matched_count'] < 2 || $cmp['score'] < 85) continue;

        $name = trim(($c['deceased_first_name'] ?? '') . ' ' . ($c['deceased_middle_name'] ?? '') . ' ' . ($c['deceased_last_name'] ?? ''));

        $results[] = [
            'id'           => (int)$c['id'],
            'type'         => 'death',
            'registry_no'  => $c['registry_no'] ?? '',
            'display_name' => $name !== '' ? $name : '(unnamed)',
            'date'         => $c['date_of_death'] ?? null,
            'date_label'   => 'Died',
            'score'        => round($cmp['score'], 2),
            'confidence'   => fr_confidence($cmp['score']),
        ];
    }

    usort($results, function($a, $b) { return $b['score'] <=> $a['score']; });
    return array_slice($results, 0, 5);
}
