<?php
/** Development regression test for fingerprint lifecycle triggers. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$hashA = hash('sha256', 'codex-lifecycle-a-' . bin2hex(random_bytes(8)));
$hashB = hash('sha256', 'codex-lifecycle-b-' . bin2hex(random_bytes(8)));
$registry = '__CODEX_LIFE_' . bin2hex(random_bytes(8));
$pdo->beginTransaction();

try {
    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Lifecycle', 'test.pdf', 'test.pdf', :hash, 'Active')"
    );
    $insert->execute([':registry_no' => $registry, ':hash' => $hashA]);

    $update = $pdo->prepare(
        "UPDATE certificate_of_live_birth SET pdf_hash = :hash WHERE registry_no = :registry_no"
    );
    $update->execute([':hash' => $hashB, ':registry_no' => $registry]);

    $countA = $pdo->prepare('SELECT COUNT(*) FROM pdf_fingerprints WHERE pdf_hash = :hash');
    $countA->execute([':hash' => $hashA]);
    $countB = $pdo->prepare('SELECT COUNT(*) FROM pdf_fingerprints WHERE pdf_hash = :hash');
    $countB->execute([':hash' => $hashB]);
    if ((int)$countA->fetchColumn() !== 0 || (int)$countB->fetchColumn() !== 1) {
        throw new RuntimeException('Hash replacement trigger state is incorrect.');
    }

    $delete = $pdo->prepare(
        "UPDATE certificate_of_live_birth SET status = 'Deleted' WHERE registry_no = :registry_no"
    );
    $delete->execute([':registry_no' => $registry]);
    $countB->execute([':hash' => $hashB]);
    if ((int)$countB->fetchColumn() !== 0) {
        throw new RuntimeException('Status-release trigger did not remove the reservation.');
    }

    $pdo->rollBack();
    echo "PASS: replacement and status-release lifecycle triggers work.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

