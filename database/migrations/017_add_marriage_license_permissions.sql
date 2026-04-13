-- Migration 017: Add missing marriage_license permissions for Encoder and Viewer roles
--
-- The marriage_license module was missing view/create/edit permissions in the
-- permissions table. Only marriage_license_archive existed (Admin only).
-- This migration inserts the missing permissions and assigns them to the
-- appropriate roles to match the pattern used by birth, death, and marriage modules.
--
-- After running this migration:
--   - Encoder: marriage_license_view, marriage_license_create, marriage_license_edit
--   - Viewer:  marriage_license_view
--   - Admin:   all (already granted via the Admin bypass in auth.php)

-- Step 1: Insert the missing permissions (INSERT IGNORE = safe to re-run)
INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('marriage_license_view',   'View marriage license applications',   'marriage_license'),
    ('marriage_license_create', 'Create marriage license applications', 'marriage_license'),
    ('marriage_license_edit',   'Edit marriage license applications',   'marriage_license');

-- Step 2: Grant all three permissions to Admin role
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', id FROM `permissions`
WHERE `name` IN (
    'marriage_license_view',
    'marriage_license_create',
    'marriage_license_edit'
);

-- Step 3: Grant view + create + edit to Encoder role
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', id FROM `permissions`
WHERE `name` IN (
    'marriage_license_view',
    'marriage_license_create',
    'marriage_license_edit'
);

-- Step 4: Grant view-only to Viewer role
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Viewer', id FROM `permissions`
WHERE `name` = 'marriage_license_view';

-- Verify (run manually to confirm):
-- SELECT p.name, rp.role
-- FROM permissions p
-- JOIN role_permissions rp ON p.id = rp.permission_id
-- WHERE p.module = 'marriage_license'
-- ORDER BY p.name, rp.role;
