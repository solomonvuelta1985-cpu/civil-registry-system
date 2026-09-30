<?php
/**
 * Development regression test for database-level PDF fingerprint enforcement.
 * Inserts only synthetic rows inside a transaction and rolls everything back.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';

$hash = hash('sha256', 'codex-pdf-fingerprint-trigger-' . bin2hex(random_bytes(8)));
$registryA = '__CODEX_FP_A_' . bin2hex(random_bytes(8));
$registryB = '__CODEX_FP_B_' . bin2hex(random_bytes(8));
$pdo->beginTransaction();

try {
    $insert = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Test', 'test.pdf', 'test.pdf', :hash, 'Active')"
    );
    $insert->execute([':registry_no' => $registryA, ':hash' => $hash]);

    $owner = $pdo->prepare(
        "SELECT cert_type, record_id FROM pdf_fingerprints WHERE pdf_hash = :hash"
    );
    $owner->execute([':hash' => $hash]);
    if (!$owner->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Trigger did not create the fingerprint reservation.');
    }

    $duplicateRejected = false;
    try {
        $insert->execute([':registry_no' => $registryB, ':hash' => $hash]);
    } catch (PDOException $e) {
        $duplicateRejected = ((string)$e->getCode() === '23000');
    }
    if (!$duplicateRejected) {
        throw new RuntimeException('Duplicate synthetic PDF was not rejected.');
    }

    $pdo->rollBack();
    echo "PASS: trigger reserved the first hash and rejected the duplicate.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

