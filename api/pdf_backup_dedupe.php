<?php
/**
 * API: Find Near-Duplicate PDF Backups (Fuzzy / Hamming)
 *
 * Computes a 64-bit similarity fingerprint for each backup file:
 *   - Split file into 64 equal-size chunks (handles short files via padding).
 *   - For each chunk: 1 bit = parity of CRC32 byte sum.
 *   - Pack the 64 bits into a 16-char hex (CHAR(16) sim_hash column).
 *
 * Two backups are "near-duplicate" when their Hamming distance <= threshold (default 4).
 *
 * Fingerprints are cached in pdf_backups.sim_hash; only files without a cached value
 * are recomputed unless ?force=1 is sent.
 *
 * POST params:
 *   csrf_token (string)
 *   threshold  (int, optional)  Hamming distance, 0..16. Default 4.
 *   force      (int, optional)  Recompute all fingerprints if 1.
 *   scope      (string, opt.)   "pending" (default) or "all" — pending excludes restored backups.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isLoggedIn())            { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (getUserRole() !== 'Admin'){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'Admin access required']); exit; }

requireCSRFToken();

$threshold = (int)($_POST['threshold'] ?? 4);
$threshold = max(0, min(16, $threshold));
$force     = !empty($_POST['force']);
$scope     = ($_POST['scope'] ?? 'pending') === 'all' ? 'all' : 'pending';

/**
 * Compute a 64-bit fingerprint for a file as a 16-char hex string.
 * Splits the file into 64 chunks; each chunk contributes 1 bit (parity of CRC32 bytes).
 */
function compute_sim_hash(string $abs_path): ?string {
    if (!is_readable($abs_path)) return null;
    $size = filesize($abs_path);
    if ($size === false || $size <= 0) return null;

    $chunks = 64;
    $chunk_size = max(1, (int)ceil($size / $chunks));

    $fh = fopen($abs_path, 'rb');
    if (!$fh) return null;

    $bits = '';
    for ($i = 0; $i < $chunks; $i++) {
        $offset = $i * $chunk_size;
        if ($offset >= $size) {
            $bits .= '0';
            continue;
        }
        fseek($fh, $offset);
        $data = fread($fh, $chunk_size);
        if ($data === false || $data === '') { $bits .= '0'; continue; }
        // Parity of CRC32 (count of 1-bits mod 2)
        $crc = crc32($data) & 0xFFFFFFFF;
        $popcount = 0;
        while ($crc) { $popcount += $crc & 1; $crc >>= 1; }
        $bits .= ($popcount & 1) ? '1' : '0';
    }
    fclose($fh);

    // Pack 64 bits into 16 hex chars
    $hex = '';
    for ($i = 0; $i < 64; $i += 4) {
        $nibble = bindec(substr($bits, $i, 4));
        $hex .= dechex($nibble);
    }
    return $hex;
}

/** Hamming distance between two 16-char hex fingerprints (0..64). */
function hamming_hex(string $a, string $b): int {
    if (strlen($a) !== 16 || strlen($b) !== 16) return 64;
    if (function_exists('gmp_hamdist')) {
        return gmp_hamdist(gmp_init($a, 16), gmp_init($b, 16));
    }
    // Fallback: XOR each nibble, count bits.
    $dist = 0;
    for ($i = 0; $i < 16; $i++) {
        $xor = hexdec($a[$i]) ^ hexdec($b[$i]);
        $dist += substr_count(decbin($xor), '1');
    }
    return $dist;
}

try {
    $where = $scope === 'pending' ? 'WHERE restored_at IS NULL' : '';
    $rows = $pdo->query(
        "SELECT id, cert_type, record_id, original_path, backup_path, file_size, sim_hash, backed_up_at
           FROM pdf_backups
           {$where}
       ORDER BY cert_type, record_id, backed_up_at DESC"
    )->fetchAll();

    $computed = 0;
    $skipped  = 0;
    $missing  = 0;

    // Compute / refresh fingerprints
    $update = $pdo->prepare("UPDATE pdf_backups SET sim_hash = :sh, file_size = :sz WHERE id = :id");
    foreach ($rows as &$r) {
        $abs = UPLOAD_DIR . $r['backup_path'];
        if (!file_exists($abs)) { $r['sim_hash'] = null; $r['_missing'] = true; $missing++; continue; }
        if (!$force && !empty($r['sim_hash'])) { $skipped++; continue; }
        $sh = compute_sim_hash($abs);
        if ($sh !== null) {
            $r['sim_hash']  = $sh;
            $r['file_size'] = filesize($abs);
            $update->execute([':sh' => $sh, ':sz' => $r['file_size'], ':id' => $r['id']]);
            $computed++;
        }
    }
    unset($r);

    // Group near-duplicates within each (cert_type, record_id) bucket — duplicates only
    // make sense for the same logical record.
    $buckets = [];
    foreach ($rows as $r) {
        if (empty($r['sim_hash'])) continue;
        $key = $r['cert_type'] . ':' . $r['record_id'];
        $buckets[$key][] = $r;
    }

    $groups = [];
    foreach ($buckets as $key => $items) {
        if (count($items) < 2) continue;

        // Greedy clustering: for each unassigned item, gather others within threshold.
        $assigned = [];
        for ($i = 0; $i < count($items); $i++) {
            if (isset($assigned[$i])) continue;
            $cluster = [$items[$i]];
            $assigned[$i] = true;
            for ($j = $i + 1; $j < count($items); $j++) {
                if (isset($assigned[$j])) continue;
                $d = hamming_hex($items[$i]['sim_hash'], $items[$j]['sim_hash']);
                if ($d <= $threshold) {
                    $cluster[] = $items[$j];
                    $assigned[$j] = true;
                }
            }
            if (count($cluster) >= 2) {
                // Sort cluster newest-first so UI defaults to keeping the newest
                usort($cluster, fn($a, $b) => strcmp($b['backed_up_at'], $a['backed_up_at']));
                $cert = $cluster[0]['cert_type'];
                $rid  = (int)$cluster[0]['record_id'];
                $groups[] = [
                    'group_key' => $key,
                    'cert_type' => $cert,
                    'record_id' => $rid,
                    'count'     => count($cluster),
                    'total_size'=> array_sum(array_map(fn($m) => (int)$m['file_size'], $cluster)),
                    'members'   => array_map(function($m) {
                        return [
                            'id'           => (int)$m['id'],
                            'backup_path'  => $m['backup_path'],
                            'original'     => basename($m['original_path']),
                            'sim_hash'     => $m['sim_hash'],
                            'file_size'    => (int)$m['file_size'],
                            'backed_up_at' => $m['backed_up_at'],
                        ];
                    }, $cluster),
                ];
            }
        }
    }

    // Sort groups by largest reclaimable size (total minus the one we'd keep)
    usort($groups, function($a, $b) {
        $sa = $a['total_size'] - ($a['members'][0]['file_size'] ?? 0);
        $sb = $b['total_size'] - ($b['members'][0]['file_size'] ?? 0);
        return $sb <=> $sa;
    });

    log_activity($pdo, 'PDF_BACKUP_DEDUPE_SCAN',
        sprintf('Dedupe scan: threshold=%d scope=%s, %d groups, %d computed, %d cached, %d missing',
                $threshold, $scope, count($groups), $computed, $skipped, $missing),
        $_SESSION['user_id'] ?? null);

    echo json_encode([
        'success'    => true,
        'threshold'  => $threshold,
        'scope'      => $scope,
        'computed'   => $computed,
        'cached'     => $skipped,
        'missing'    => $missing,
        'groups'     => $groups,
    ]);

} catch (Exception $e) {
    error_log('pdf_backup_dedupe error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Dedupe scan failed']);
}
