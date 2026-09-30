<?php
/** Verify that the same active PDF hash is rejected across certificate modules. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

$hash = hash('sha256', 'codex-cross-module-' . bin2hex(random_bytes(8)));
$birthRegistry = '__CODEX_CROSS_B_' . bin2hex(random_bytes(8));
$deathRegistry = '__CODEX_CROSS_D_' . bin2hex(random_bytes(8));
$pdo->beginTransaction();

try {
    $birth = $pdo->prepare(
        "INSERT INTO certificate_of_live_birth
            (registry_no, date_of_registration, mother_first_name, mother_last_name,
             pdf_filename, pdf_filepath, pdf_hash, status)
         VALUES (:registry_no, CURDATE(), 'Codex', 'Birth', 'cross-birth.pdf', 'cross-birth.pdf', :hash, 'Active')"
    );
    $birth->execute([':registry_no' => $birthRegistry, ':hash' => $hash]);

    $deathRejected = false;
    try {
        $death = $pdo->prepare(
            "INSERT INTO certificate_of_death
                (registry_no, date_of_registration, deceased_first_name, deceased_last_name,
                 date_of_death, pdf_filename, pdf_filepath, pdf_hash, status)
             VALUES (:registry_no, CURDATE(), 'Codex', 'Death', CURDATE(), 'cross-death.pdf', 'cross-death.pdf', :hash, 'Active')"
        );
        $death->execute([':registry_no' => $deathRegistry, ':hash' => $hash]);
    } catch (PDOException $e) {
        $deathRejected = ((string)$e->getCode() === '23000');
    }
    if (!$deathRejected) throw new RuntimeException('Cross-module duplicate was not rejected.');

    $pdo->rollBack();
    echo "PASS: one active PDF hash cannot be reused across birth and death modules.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
