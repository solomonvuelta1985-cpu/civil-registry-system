<?php
/** Shared database-backed guard for two-person restore workflows. */

function restore_active_super_admin_ids(PDO $pdo, bool $lock = false): array
{
    if ($lock && !$pdo->inTransaction()) throw new LogicException('Restore approver locks require an active transaction.');
    $sql = "SELECT id FROM users WHERE role='Super Admin' AND status='Active' ORDER BY id" . ($lock ? ' FOR UPDATE' : '');
    return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

function restore_assert_two_active_super_admins(PDO $pdo): void
{
    if (count(restore_active_super_admin_ids($pdo)) < 2) {
        throw new RuntimeException('At least two active Super Admin accounts are required for restore workflows.');
    }
}

function restore_assert_active_super_admin_approver(PDO $pdo, int $userId): void
{
    $activeIds = restore_active_super_admin_ids($pdo, true);
    if (count($activeIds) < 2) {
        throw new RuntimeException('At least two active Super Admin accounts are required for restore workflows.');
    }
    if ($userId <= 0 || !in_array($userId, $activeIds, true)) {
        throw new RuntimeException('The approver must be an active Super Admin account.');
    }
}
