<?php
/**
 * Backfill the centralized PDF fingerprint registry from active certificate
 * records. Run once after the registry migration and review all conflicts.
 *
 * CLI only; this script does not delete records or files.
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

$stats = ['added' => 0, 'existing' => 0, 'conflicts' => 0, 'no_hash' => 0];

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
            echo "NO_HASH {$type} #{$row['id']} {$row['registry_no']}\n";
            continue;
        }

        $owner = pdf_fingerprint_owner($pdo, $hash);
        if ($owner !== null) {
            if ($owner['cert_type'] === $type && (int)$owner['record_id'] === (int)$row['id']) {
                $stats['existing']++;
                continue;
            }
            $stats['conflicts']++;
            echo "CONFLICT hash={$hash} existing={$owner['cert_type']}#{$owner['record_id']} "
               . "candidate={$type}#{$row['id']}\n";
            continue;
        }

        $insert = $pdo->prepare(
            'INSERT INTO pdf_fingerprints
                (pdf_hash, cert_type, record_id, registry_no)
             VALUES (:hash, :type, :id, :registry_no)'
        );
        $insert->execute([
            ':hash'       => $hash,
            ':type'       => $type,
            ':id'         => (int)$row['id'],
            ':registry_no'=> $row['registry_no'] ?? null,
        ]);
        $stats['added']++;
        echo "ADDED {$type} #{$row['id']} {$row['registry_no']}\n";
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT) . PHP_EOL;
exit($stats['conflicts'] > 0 ? 2 : 0);

