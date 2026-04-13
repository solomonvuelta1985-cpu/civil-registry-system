-- Migration 018: Restrict all users module permissions to Admin role only
--
-- Encoder and Viewer roles had users_view and users_edit permissions assigned,
-- allowing them to access User Management and edit user accounts.
-- Only Admin should be able to view, create, edit, or delete users.
--
-- After running this migration:
--   - Admin:   users_view, users_create, users_edit, users_delete (unchanged)
--   - Encoder: no users_* permissions
--   - Viewer:  no users_* permissions

-- Remove all users module permissions from Encoder role
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE rp.role = 'Encoder'
  AND p.module = 'users';

-- Remove all users module permissions from Viewer role
DELETE rp FROM `role_permissions` rp
INNER JOIN `permissions` p ON rp.permission_id = p.id
WHERE rp.role = 'Viewer'
  AND p.module = 'users';

-- Verify (run manually to confirm):
-- SELECT p.name, rp.role
-- FROM permissions p
-- JOIN role_permissions rp ON p.id = rp.permission_id
-- WHERE p.module = 'users'
-- ORDER BY p.name, rp.role;
-- Expected: only 'Admin' rows
