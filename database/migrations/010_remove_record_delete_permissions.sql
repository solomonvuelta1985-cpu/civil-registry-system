-- Migration 010: Remove dead *_delete permissions
--
-- As of this migration, record deletion (birth, death, marriage, marriage_license)
-- is enforced at the code level via isAdmin() / requireAdminApi() in:
--   api/certificate_of_live_birth_delete.php
--   api/certificate_of_death_delete.php
--   api/certificate_of_marriage_delete.php
--   api/application_for_marriage_license_delete.php
--   api/trash_restore.php
--
-- The four *_delete permissions (birth_delete, death_delete, marriage_delete,
-- marriage_license_delete) are therefore dead code -- the APIs no longer read
-- them, and leaving them in the `permissions` table is misleading because they
-- still appear in the Users UI permission picker as if they did something.
--
-- This migration removes them. It is safe to re-run (uses plain DELETE with
-- IN clause; no error if already gone). role_permissions rows that reference
-- them are removed first to satisfy any foreign key constraint.
--
-- ROLLBACK: if you need to restore these permissions, the original definitions
-- can be re-inserted manually, but the code no longer checks them, so restoring
-- them would only reintroduce the UI confusion this migration fixes.

-- Step 1: Remove role_permissions entries that reference the dead permissions
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE p.name IN (
    'birth_delete',
    'death_delete',
    'marriage_delete',
    'marriage_license_delete'
);

-- Step 2: Remove the dead permissions themselves
DELETE FROM `permissions`
WHERE `name` IN (
    'birth_delete',
    'death_delete',
    'marriage_delete',
    'marriage_license_delete'
);

-- Verification (run manually after migration):
-- SELECT * FROM permissions WHERE name LIKE '%_delete';
--   Expected: no rows returned for birth/death/marriage/marriage_license_delete
--   (users_delete and device_delete are separate and should remain).
