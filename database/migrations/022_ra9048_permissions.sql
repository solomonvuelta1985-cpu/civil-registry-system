-- Migration: 022_ra9048_permissions
-- Adds RA 9048/10172 module permissions to iscan_db
-- Run this against iscan_db (via run_migrations.php or phpMyAdmin)

-- Add RA 9048 permissions
INSERT IGNORE INTO `permissions` (`name`, `description`, `module`) VALUES
  ('ra9048_view',   'Can view RA 9048/10172 transactions',   'ra9048'),
  ('ra9048_create', 'Can create RA 9048/10172 transactions', 'ra9048'),
  ('ra9048_edit',   'Can edit RA 9048/10172 transactions',   'ra9048'),
  ('ra9048_delete', 'Can delete RA 9048/10172 transactions', 'ra9048'),
  ('ra9048_export', 'Can export RA 9048/10172 data',         'ra9048');

-- Grant all RA 9048 permissions to Admin role
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Admin', `id` FROM `permissions` WHERE `name` LIKE 'ra9048_%';

-- Grant view, create, edit to Encoder role
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`)
SELECT 'Encoder', `id` FROM `permissions` WHERE `name` IN ('ra9048_view', 'ra9048_create', 'ra9048_edit');
