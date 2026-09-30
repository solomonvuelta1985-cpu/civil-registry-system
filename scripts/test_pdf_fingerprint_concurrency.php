<?php
/**
 * Two-process concurrency regression test for the global PDF hash key.
 * Child A holds the reservation briefly while child B attempts the same
 * hash. Expected result: one success and one duplicate rejection.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
require_once __DIR__ . '/../includes/config.php';

if (($argv[1] ?? '') === '--child') {
    $hash = (string)($argv[2] ?? '');
    $recordId = (int)($argv[3] ?? 0);
    $hold = max(0, (int)($argv[4] ?? 0));
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no)
             VALUES (:hash, :type, :record_id, :registry_no)'
        );
        $stmt->execute([
            ':hash' => $hash,
            ':type' => 'concurrency_test',
            ':record_id' => $recordId,
            ':registry_no' => '__CODEX_CONCURRENCY_' . $recordId,
        ]);
        if ($hold > 0) sleep($hold);
        $pdo->commit();
        echo "SUCCESS\n";
        exit(0);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') {
            echo "DUPLICATE\n";
            exit(2);
        }
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

$hash = hash('sha256', 'codex-concurrency-' . bin2hex(random_bytes(8)));
$script = __FILE__;
$commandA = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --child ' . $hash . ' 1 2';
$commandB = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --child ' . $hash . ' 2 0';
$pipesA = $pipesB = [];
$procA = proc_open($commandA, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipesA, BASE_PATH);
if (!is_resource($procA)) { fwrite(STDERR, "Could not start child A.\n"); exit(1); }
usleep(250000);
$procB = proc_open($commandB, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipesB, BASE_PATH);
if (!is_resource($procB)) { proc_terminate($procA); fwrite(STDERR, "Could not start child B.\n"); exit(1); }

$outA = stream_get_contents($pipesA[1]); $errA = stream_get_contents($pipesA[2]);
$outB = stream_get_contents($pipesB[1]); $errB = stream_get_contents($pipesB[2]);
fclose($pipesA[1]); fclose($pipesA[2]); fclose($pipesB[1]); fclose($pipesB[2]);
$exitA = proc_close($procA); $exitB = proc_close($procB);

$cleanup = $pdo->prepare('DELETE FROM pdf_fingerprints WHERE pdf_hash = :hash');
$cleanup->execute([':hash' => $hash]);

if ($exitA === 0 && $exitB === 2 && trim($outA) === 'SUCCESS' && trim($outB) === 'DUPLICATE') {
    echo "PASS: concurrent reservations produced one success and one duplicate rejection.\n";
    exit(0);
}

fwrite(STDERR, "FAIL: A exit={$exitA} output={$outA} error={$errA}; B exit={$exitB} output={$outB} error={$errB}\n");
exit(1);

