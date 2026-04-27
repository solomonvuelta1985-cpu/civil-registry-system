-- ============================================================
-- iScan NAS Database Discrepancy Checker
-- Run this in phpMyAdmin SQL tab on your NAS (iscan_db)
-- It will report what's MISSING compared to localhost
-- ============================================================

SELECT '========================================' AS '';
SELECT '  iScan NAS vs Localhost Discrepancy Report' AS '';
SELECT '  Run Date: ' AS '', NOW() AS 'timestamp';
SELECT '========================================' AS '';

-- ============================================================
-- SECTION 1: MISSING TABLES
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 1: MISSING TABLES ---' AS '';

SELECT 'record_links' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'record_links')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'Double registration detection (PSA MC 2019-23)' AS 'Purpose';

SELECT 'petitions' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'petitions')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'RA 9048 CCE/CFN petition tracking' AS 'Purpose';

SELECT 'legal_instruments' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'legal_instruments')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'AUSF / Supplemental / Legitimation' AS 'Purpose';

SELECT 'court_decrees' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'court_decrees')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'Court orders (Adoption, Annulment, etc.)' AS 'Purpose';

SELECT 'registered_devices' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'registered_devices')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'Device fingerprint registration' AS 'Purpose';

SELECT 'security_logs' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'security_logs')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'Security event logging' AS 'Purpose';

SELECT 'rate_limits' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'rate_limits')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'Login/API rate limiting' AS 'Purpose';

SELECT 'pdf_backups' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'iscan_db' AND table_name = 'pdf_backups')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'PDF file backup tracking' AS 'Purpose';

-- Also check if RA 9048 tables exist in separate DB
SELECT 'iscan_ra9048_db (separate DB)' AS 'Table',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.schemata WHERE schema_name = 'iscan_ra9048_db')
    THEN 'EXISTS (separate DB found)' ELSE 'NOT FOUND (tables should be in iscan_db instead)' END AS 'Status',
  'Check if RA 9048 was created as separate database' AS 'Purpose';

-- ============================================================
-- SECTION 2: MISSING COLUMNS — certificate_of_death
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 2: MISSING COLUMNS — certificate_of_death ---' AS '';

SELECT 'sex' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='sex')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(Male,Female) — deceased sex' AS 'Expected Type';

SELECT 'age_unit' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='age_unit')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(years,months,days) — for infant deaths' AS 'Expected Type';

SELECT 'father_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='father_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'mother_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='mother_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'pdf_hash' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='pdf_hash')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'char(64) — SHA-256 for PDF integrity' AS 'Expected Type';

SELECT 'date_of_registration_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_registration_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na) — partial date support' AS 'Expected Type';

SELECT 'date_of_registration_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_registration_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_registration_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_registration_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'date_of_birth_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_birth_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'date_of_birth_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_birth_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'date_of_birth_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_birth_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'date_of_birth_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_birth_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

-- ============================================================
-- SECTION 3: MISSING COLUMNS — certificate_of_live_birth
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 3: MISSING COLUMNS — certificate_of_live_birth ---' AS '';

SELECT 'legitimacy_status' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='legitimacy_status')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(Legitimate,Illegitimate)' AS 'Expected Type';

SELECT 'time_of_birth' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='time_of_birth')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'TIME' AS 'Expected Type';

SELECT 'place_type' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='place_type')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100) — Hospital vs Barangay' AS 'Expected Type';

SELECT 'barangay' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='barangay')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(255) — specific barangay name' AS 'Expected Type';

SELECT 'mother_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='mother_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'father_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='father_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'pdf_hash' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='pdf_hash')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'char(64)' AS 'Expected Type';

SELECT 'date_of_registration_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='date_of_registration_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'date_of_registration_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='date_of_registration_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='date_of_registration_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='date_of_registration_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'child_date_of_birth_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='child_date_of_birth_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'child_date_of_birth_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='child_date_of_birth_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'child_date_of_birth_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='child_date_of_birth_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'child_date_of_birth_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='child_date_of_birth_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

-- ============================================================
-- SECTION 4: MISSING COLUMNS — certificate_of_marriage
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 4: MISSING COLUMNS — certificate_of_marriage ---' AS '';

SELECT 'husband_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'wife_citizenship' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_citizenship')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'varchar(100)' AS 'Expected Type';

SELECT 'nature_of_solemnization' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='nature_of_solemnization')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(Church,Civil,Other Religious Sect)' AS 'Expected Type';

SELECT 'pdf_hash' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='pdf_hash')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'char(64)' AS 'Expected Type';

SELECT 'date_of_registration_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='date_of_registration_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'date_of_registration_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='date_of_registration_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='date_of_registration_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'date_of_registration_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='date_of_registration_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'husband_date_of_birth_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_date_of_birth_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'husband_date_of_birth_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_date_of_birth_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'husband_date_of_birth_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_date_of_birth_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'husband_date_of_birth_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_date_of_birth_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'wife_date_of_birth_format' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_date_of_birth_format')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'ENUM(full,month_only,year_only,month_year,month_day,na)' AS 'Expected Type';

SELECT 'wife_date_of_birth_partial_month' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_date_of_birth_partial_month')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

SELECT 'wife_date_of_birth_partial_year' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_date_of_birth_partial_year')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'smallint(4) unsigned' AS 'Expected Type';

SELECT 'wife_date_of_birth_partial_day' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_date_of_birth_partial_day')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'tinyint(2) unsigned' AS 'Expected Type';

-- ============================================================
-- SECTION 5: MISSING COLUMNS — application_for_marriage_license
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 5: MISSING COLUMNS — application_for_marriage_license ---' AS '';

SELECT 'pdf_hash' AS 'Column',
  CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='iscan_db' AND table_name='application_for_marriage_license' AND column_name='pdf_hash')
    THEN 'EXISTS' ELSE '** MISSING **' END AS 'Status',
  'char(64)' AS 'Expected Type';

-- ============================================================
-- SECTION 6: NULLABLE / CONSTRAINT DIFFERENCES
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 6: NULLABLE / CONSTRAINT ISSUES ---' AS '';
SELECT 'Columns that should be NULLABLE but are NOT NULL on NAS:' AS '';

SELECT 'certificate_of_death.date_of_registration' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for partial dates' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_registration';

SELECT 'certificate_of_death.date_of_birth' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for unknown DOB' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_death' AND column_name='date_of_birth';

SELECT 'certificate_of_live_birth.registry_no' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for pending registrations' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='registry_no';

SELECT 'certificate_of_live_birth.date_of_registration' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for partial dates' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth' AND column_name='date_of_registration';

SELECT 'certificate_of_marriage.date_of_registration' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for partial dates' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='date_of_registration';

SELECT 'certificate_of_marriage.husband_date_of_birth' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for unknown DOB' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_date_of_birth';

SELECT 'certificate_of_marriage.wife_date_of_birth' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable) for unknown DOB' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_date_of_birth';

SELECT 'certificate_of_marriage.husband_place_of_birth' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable)' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_place_of_birth';

SELECT 'certificate_of_marriage.wife_place_of_birth' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable)' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_place_of_birth';

SELECT 'certificate_of_marriage.husband_residence' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable)' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='husband_residence';

SELECT 'certificate_of_marriage.wife_residence' AS 'Column',
  IS_NULLABLE AS 'Currently',
  'Should be YES (nullable)' AS 'Expected'
FROM information_schema.columns
WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage' AND column_name='wife_residence';

-- ============================================================
-- SECTION 7: UNIQUE KEY CHECKS
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 7: UNIQUE KEY CHECKS ---' AS '';

SELECT 'certificate_of_death.registry_no' AS 'Column',
  CASE WHEN EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema='iscan_db' AND table_name='certificate_of_death'
    AND column_name='registry_no' AND non_unique=0
  ) THEN 'HAS UNIQUE KEY' ELSE '** NO UNIQUE KEY — allows duplicate reg numbers **' END AS 'Status';

SELECT 'certificate_of_marriage.registry_no' AS 'Column',
  CASE WHEN EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema='iscan_db' AND table_name='certificate_of_marriage'
    AND column_name='registry_no' AND non_unique=0
  ) THEN 'HAS UNIQUE KEY' ELSE '** NO UNIQUE KEY — allows duplicate reg numbers **' END AS 'Status';

SELECT 'certificate_of_live_birth.registry_no' AS 'Column',
  CASE WHEN EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema='iscan_db' AND table_name='certificate_of_live_birth'
    AND column_name='registry_no' AND non_unique=0
  ) THEN 'HAS UNIQUE KEY' ELSE '** NO UNIQUE KEY — allows duplicate reg numbers **' END AS 'Status';

SELECT 'application_for_marriage_license.registry_no' AS 'Column',
  CASE WHEN EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema='iscan_db' AND table_name='application_for_marriage_license'
    AND column_name='registry_no' AND non_unique=0
  ) THEN 'HAS UNIQUE KEY' ELSE '** NO UNIQUE KEY — allows duplicate reg numbers **' END AS 'Status';

-- ============================================================
-- SECTION 8: DATA QUALITY CHECK (existing records)
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 8: DATA QUALITY — existing records ---' AS '';

SELECT 'Birth records with NULL created_by' AS 'Issue',
  COUNT(*) AS 'Count'
FROM certificate_of_live_birth WHERE created_by IS NULL;

SELECT 'Birth records with NULL child_first_name' AS 'Issue',
  COUNT(*) AS 'Count'
FROM certificate_of_live_birth WHERE child_first_name IS NULL OR child_first_name = '';

SELECT 'Birth records with NULL child_sex' AS 'Issue',
  COUNT(*) AS 'Count'
FROM certificate_of_live_birth WHERE child_sex IS NULL;

SELECT 'Marriage records with NULL created_by' AS 'Issue',
  COUNT(*) AS 'Count'
FROM certificate_of_marriage WHERE created_by IS NULL;

SELECT 'Death records with NULL created_by' AS 'Issue',
  COUNT(*) AS 'Count'
FROM certificate_of_death WHERE created_by IS NULL;

SELECT 'Activity logs with NULL user_id' AS 'Issue',
  COUNT(*) AS 'Count'
FROM activity_logs WHERE user_id IS NULL;

SELECT 'Activity logs with user_id = 0 (likely bug)' AS 'Issue',
  COUNT(*) AS 'Count'
FROM activity_logs WHERE user_id = 0;

-- ============================================================
-- SECTION 9: RECORD COUNTS SUMMARY
-- ============================================================
SELECT '' AS '';
SELECT '--- SECTION 9: RECORD COUNTS ---' AS '';

SELECT 'certificate_of_live_birth' AS 'Table', COUNT(*) AS 'Total',
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS 'Active',
  SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) AS 'Archived',
  SUM(CASE WHEN status='Deleted' THEN 1 ELSE 0 END) AS 'Deleted'
FROM certificate_of_live_birth;

SELECT 'certificate_of_marriage' AS 'Table', COUNT(*) AS 'Total',
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS 'Active',
  SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) AS 'Archived',
  SUM(CASE WHEN status='Deleted' THEN 1 ELSE 0 END) AS 'Deleted'
FROM certificate_of_marriage;

SELECT 'certificate_of_death' AS 'Table', COUNT(*) AS 'Total',
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS 'Active',
  SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) AS 'Archived',
  SUM(CASE WHEN status='Deleted' THEN 1 ELSE 0 END) AS 'Deleted'
FROM certificate_of_death;

SELECT 'application_for_marriage_license' AS 'Table', COUNT(*) AS 'Total',
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS 'Active',
  SUM(CASE WHEN status='Archived' THEN 1 ELSE 0 END) AS 'Archived',
  SUM(CASE WHEN status='Deleted' THEN 1 ELSE 0 END) AS 'Deleted'
FROM application_for_marriage_license;

SELECT 'users' AS 'Table', COUNT(*) AS 'Total',
  SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS 'Active',
  SUM(CASE WHEN status='Inactive' THEN 1 ELSE 0 END) AS 'Inactive',
  0 AS 'N/A'
FROM users;

SELECT 'activity_logs' AS 'Table', COUNT(*) AS 'Total', 0 AS 'N/A', 0 AS 'N/A', 0 AS 'N/A'
FROM activity_logs;

-- ============================================================
SELECT '' AS '';
SELECT '========================================' AS '';
SELECT '  END OF DISCREPANCY REPORT' AS '';
SELECT '  Items marked ** MISSING ** need migration' AS '';
SELECT '========================================' AS '';
