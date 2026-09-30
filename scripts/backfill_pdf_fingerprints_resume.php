<?php
/**
 * Idempotent/resumable PDF fingerprint backfill.
 *
 * This version is safe to rerun after an interrupted or concurrent backfill:
 * duplicate-key races are recorded as conflicts instead of terminating the
 * whole operation.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/pdf_fingerprint_registry.php';

$tables = [
    'birth'            => 'certificate_of_live_birth',
    'death'            => 'certificate_of_death',
    'marriage'         => 'certificate_of_marriage',
    'marriage_license' => 'application_for_marriage_license',
];
$stats = [
    'added' => 0,
    'existing' => 0,
    'conflicts' => 0,
    'no_hash' => 0,
    'races_or_unresolved' => 0,
];

foreach ($tables as $type => $table) {
    $stmt = $pdo->query(
        "SELECT id, registry_no, pdf_hash
           FROM {$table}
          WHERE status = 'Active'
            AND pdf_filename IS NOT NULL
            AND pdf_filename <> ''"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hash = strtolower(trim((string)($row['pdf_hash'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $stats['no_hash']++;
            continue;
        }

        $owner = pdf_fingerprint_owner($pdo, $hash);
        if ($owner !== null) {
            if ($owner['cert_type'] === $type && (int)$owner['record_id'] === (int)$row['id']) {
                $stats['existing']++;
            } else {
                $stats['conflicts']++;
            }
            continue;
        }

        try {
            $insert = $pdo->prepare(
                'INSERT INTO pdf_fingerprints
                    (pdf_hash, cert_type, record_id, registry_no)
                 VALUES (:hash, :type, :id, :registry_no)'
            );
            $insert->execute([
                ':hash'        => $hash,
                ':type'        => $type,
                ':id'          => (int)$row['id'],
                ':registry_no' => $row['registry_no'] ?? null,
            ]);
            $stats['added']++;
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                $stats['races_or_unresolved']++;
                continue;
            }
            throw $e;
        }
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT) . PHP_EOL;
exit($stats['conflicts'] > 0 ? 2 : 0);

