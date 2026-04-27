-- ============================================================
-- Migration 023: Fix nullable columns + backfill created_by
-- Safe to run on NAS production (iscan_db)
-- Paste this in phpMyAdmin SQL tab and click Go
-- ============================================================

-- 1. Make marriage columns nullable
ALTER TABLE `certificate_of_marriage`
  MODIFY COLUMN `husband_place_of_birth` VARCHAR(255) NULL,
  MODIFY COLUMN `wife_place_of_birth` VARCHAR(255) NULL,
  MODIFY COLUMN `husband_residence` TEXT NULL,
  MODIFY COLUMN `wife_residence` TEXT NULL;

-- 2. Backfill NULL created_by (assign to System Administrator)
UPDATE `certificate_of_live_birth` SET `created_by` = 1 WHERE `created_by` IS NULL;
UPDATE `certificate_of_death` SET `created_by` = 1 WHERE `created_by` IS NULL;

-- 3. Fix activity logs with NULL user_id (set to admin)
UPDATE `activity_logs` SET `user_id` = 1 WHERE `user_id` IS NULL;

-- 4. Fix activity logs with user_id = 0 (invalid)
UPDATE `activity_logs` SET `user_id` = 1 WHERE `user_id` = 0;

-- 5. Verify
SELECT 'marriage nullable fix' AS 'Check',
  SUM(CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END) AS 'Fixed',
  SUM(CASE WHEN IS_NULLABLE = 'NO' THEN 1 ELSE 0 END) AS 'Still NOT NULL'
FROM information_schema.columns
WHERE table_schema = 'iscan_db'
  AND table_name = 'certificate_of_marriage'
  AND column_name IN ('husband_place_of_birth','wife_place_of_birth','husband_residence','wife_residence');

SELECT 'created_by still NULL' AS 'Check',
  (SELECT COUNT(*) FROM certificate_of_live_birth WHERE created_by IS NULL) AS 'Birth',
  (SELECT COUNT(*) FROM certificate_of_death WHERE created_by IS NULL) AS 'Death',
  (SELECT COUNT(*) FROM activity_logs WHERE user_id IS NULL OR user_id = 0) AS 'Activity Logs';
