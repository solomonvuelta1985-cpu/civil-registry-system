-- Migration: 024_merge_ra9048_into_iscan_db
--
-- Consolidates the RA 9048 / RA 10172 tables into the main iscan_db database
-- and removes the now-empty iscan_ra9048_db.
--
-- WHY:
--   The original design used a separate database (iscan_ra9048_db) for RA 9048
--   tables. In practice that buys nothing (we have one app user, one backup
--   target, no cross-DB FKs) and costs us:
--     - cross-database joins for the COLB lookup
--     - separate Hyper Backup selection on the Synology NAS (easy to miss)
--     - the phpMyAdmin "wrong DB selected" footgun (which already bit us once)
--
-- WHEN TO RUN:
--   Only if your `petitions`, `legal_instruments`, `court_decrees`,
--   `petition_corrections`, `petition_supporting_docs` tables are CURRENTLY in
--   iscan_db (not in iscan_ra9048_db). The verifier scripts/verify_023_migration.php
--   will tell you where they are.
--
--   If they are already in iscan_db (our case after the phpMyAdmin slip), this
--   migration just drops the empty iscan_ra9048_db.
--
--   If they are still in iscan_ra9048_db, uncomment the RENAME block below to
--   move them first.

-- =====================================================================
-- (Optional) Move tables from iscan_ra9048_db -> iscan_db.
-- Uncomment this block ONLY if the tables are still in iscan_ra9048_db.
-- =====================================================================
-- RENAME TABLE
--   `iscan_ra9048_db`.`petitions`                TO `iscan_db`.`petitions`,
--   `iscan_ra9048_db`.`legal_instruments`        TO `iscan_db`.`legal_instruments`,
--   `iscan_ra9048_db`.`court_decrees`            TO `iscan_db`.`court_decrees`,
--   `iscan_ra9048_db`.`petition_corrections`     TO `iscan_db`.`petition_corrections`,
--   `iscan_ra9048_db`.`petition_supporting_docs` TO `iscan_db`.`petition_supporting_docs`;

-- =====================================================================
-- Drop the now-empty separate database.
-- Safe: we already verified iscan_ra9048_db has no tables.
-- =====================================================================
DROP DATABASE IF EXISTS `iscan_ra9048_db`;

-- =====================================================================
-- Sanity check: confirm the 5 RA9048 tables now exist in iscan_db.
-- (Will throw if any table is missing — useful as a tripwire.)
-- =====================================================================
USE `iscan_db`;

SELECT
    'petitions'                AS table_name, COUNT(*) AS row_count FROM `petitions`
UNION ALL SELECT
    'legal_instruments',                      COUNT(*)              FROM `legal_instruments`
UNION ALL SELECT
    'court_decrees',                          COUNT(*)              FROM `court_decrees`
UNION ALL SELECT
    'petition_corrections',                   COUNT(*)              FROM `petition_corrections`
UNION ALL SELECT
    'petition_supporting_docs',               COUNT(*)              FROM `petition_supporting_docs`;
