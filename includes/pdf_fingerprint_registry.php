<?php
/**
 * Central PDF fingerprint reservation helpers.
 *
 * This file is intentionally small and transaction-friendly. Callers must
 * invoke the reserve/release functions inside the same database transaction
 * as the certificate row they create or update.
 */

function pdf_fingerprint_owner(PDO $pdo, string $hash): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT pdf_hash, cert_type, record_id, registry_no, reserved_by, reserved_at
           FROM pdf_fingerprints
          WHERE pdf_hash = :hash
          LIMIT 1'
    );
    $stmt->execute([':hash' => strtolower($hash)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Reserve a hash for one active record.
 *
 * The PRIMARY KEY on pdf_fingerprints.pdf_hash is the concurrency boundary.
 * A duplicate-key exception must be handled by the caller as a 409 response
 * and the caller's transaction must be rolled back.
 */
function reserve_pdf_fingerprint(
    PDO $pdo,
    string $hash,
    string $certType,
    int $recordId,
    ?string $registryNo = null,
    ?int $userId = null
): void {
    if (!preg_match('/^[a-f0-9]{64}$/i', $hash)) {
        throw new InvalidArgumentException('Invalid PDF SHA-256 hash.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pdf_fingerprints
            (pdf_hash, cert_type, record_id, registry_no, reserved_by)
         VALUES (:hash, :type, :record_id, :registry_no, :user_id)'
    );
    $stmt->execute([
        ':hash'      => strtolower($hash),
        ':type'      => $certType,
        ':record_id' => $recordId,
        ':registry_no' => $registryNo,
        ':user_id'   => $userId,
    ]);
}

function release_pdf_fingerprint(PDO $pdo, string $certType, int $recordId): void {
    $stmt = $pdo->prepare(
        'DELETE FROM pdf_fingerprints
          WHERE cert_type = :type AND record_id = :record_id'
    );
    $stmt->execute([
        ':type'      => $certType,
        ':record_id' => $recordId,
    ]);
}

