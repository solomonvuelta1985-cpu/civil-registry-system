-- Migration 009: Add Archive Permissions
-- Adds 4 new permissions for archiving civil registry records:
--   birth_archive, marriage_archive, death_archive, marriage_license_archive
--
-- Archive is distinct from Delete:
--   - Active   -> visible in main record lists (default)
--   - Archived -> retained but hidden from daily lists (old/historical records)
--   - Deleted  -> soft-deleted, shown in Trash for restore or permanent removal
--
-- After running this migration:
--   1. The 4 new permissions will exist in the `permissions` table
--   2. All 4 permissions will be granted to the 'Admin' role automatically
--   3. Assign to 'Encoder' role manually via Users UI if that role should archive
--   4. Viewer role intentionally does NOT receive archive permissions

-- Insert the 4 new permissions (safe to re-run: uses INSERT IGNORE on unique name)
INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
    ('birth_archive',            'Archive birth records',                     'birth'),
    ('marriage_archive',         'Archive marriage records',                  'marriage'),
    ('death_archive',            'Archive death records',                     'death'),
    ('marriage_license_archive', 'Archive marriage license applications',     'marriage_license');

-- Grant the 4 new permissions to the Admin role
-- Uses INSERT IGNORE to avoid duplicates if re-run
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id`
FROM `permissions`
WHERE `name` IN (
    'birth_archive',
    'marriage_archive',
    'death_archive',
    'marriage_license_archive'
);

-- Verification query (run manually to confirm migration succeeded):
-- SELECT p.name, p.description, p.module
-- FROM permissions p
-- WHERE p.name LIKE '%_archive'
-- ORDER BY p.module;
--
-- SELECT rp.role, p.name
-- FROM role_permissions rp
-- JOIN permissions p ON p.id = rp.permission_id
-- WHERE p.name LIKE '%_archive'
-- ORDER BY rp.role, p.name;
