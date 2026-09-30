-- ============================================================
-- Migration 042: Database-Level PDF Fingerprint Enforcement
-- iSCAN Civil Registry Records Management System
-- ============================================================
-- These triggers keep the central fingerprint registry synchronized even
-- when a record is changed by an endpoint, an import, or an administrative
-- SQL workflow. The PRIMARY KEY on pdf_fingerprints.pdf_hash is the final
-- concurrency boundary.

DROP TRIGGER IF EXISTS trg_pdf_fp_birth_ai;
DROP TRIGGER IF EXISTS trg_pdf_fp_birth_au_add;
DROP TRIGGER IF EXISTS trg_pdf_fp_birth_au_release;
DROP TRIGGER IF EXISTS trg_pdf_fp_birth_ad;

CREATE TRIGGER trg_pdf_fp_birth_ai
AFTER INSERT ON certificate_of_live_birth
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'birth', NEW.id, NEW.registry_no, NEW.created_by
  FROM DUAL
 WHERE NEW.status = 'Active'
   AND NEW.pdf_hash IS NOT NULL
   AND NEW.pdf_hash <> '';

CREATE TRIGGER trg_pdf_fp_birth_au_add
AFTER UPDATE ON certificate_of_live_birth
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'birth', NEW.id, NEW.registry_no, NEW.updated_by
  FROM DUAL
 WHERE NEW.status = 'Active'
   AND NEW.pdf_hash IS NOT NULL
   AND NEW.pdf_hash <> ''
   AND NOT EXISTS (
       SELECT 1 FROM pdf_fingerprints
        WHERE pdf_hash = NEW.pdf_hash
          AND cert_type = 'birth'
          AND record_id = NEW.id
   );

CREATE TRIGGER trg_pdf_fp_birth_au_release
AFTER UPDATE ON certificate_of_live_birth
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'birth'
   AND record_id = NEW.id
   AND (
       NEW.status <> 'Active'
       OR NEW.pdf_hash IS NULL
       OR NEW.pdf_hash = ''
       OR OLD.pdf_hash IS NULL
       OR OLD.pdf_hash <> NEW.pdf_hash
   );

CREATE TRIGGER trg_pdf_fp_birth_ad
AFTER DELETE ON certificate_of_live_birth
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'birth' AND record_id = OLD.id;

DROP TRIGGER IF EXISTS trg_pdf_fp_death_ai;
DROP TRIGGER IF EXISTS trg_pdf_fp_death_au_add;
DROP TRIGGER IF EXISTS trg_pdf_fp_death_au_release;
DROP TRIGGER IF EXISTS trg_pdf_fp_death_ad;

CREATE TRIGGER trg_pdf_fp_death_ai
AFTER INSERT ON certificate_of_death
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'death', NEW.id, NEW.registry_no, NEW.created_by
  FROM DUAL
 WHERE NEW.status = 'Active' AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> '';

CREATE TRIGGER trg_pdf_fp_death_au_add
AFTER UPDATE ON certificate_of_death
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'death', NEW.id, NEW.registry_no, NEW.updated_by
  FROM DUAL
 WHERE NEW.status = 'Active'
   AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> ''
   AND NOT EXISTS (SELECT 1 FROM pdf_fingerprints
                    WHERE pdf_hash = NEW.pdf_hash AND cert_type = 'death' AND record_id = NEW.id);

CREATE TRIGGER trg_pdf_fp_death_au_release
AFTER UPDATE ON certificate_of_death
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'death' AND record_id = NEW.id
   AND (NEW.status <> 'Active' OR NEW.pdf_hash IS NULL OR NEW.pdf_hash = ''
        OR OLD.pdf_hash IS NULL OR OLD.pdf_hash <> NEW.pdf_hash);

CREATE TRIGGER trg_pdf_fp_death_ad
AFTER DELETE ON certificate_of_death
FOR EACH ROW
DELETE FROM pdf_fingerprints WHERE cert_type = 'death' AND record_id = OLD.id;

DROP TRIGGER IF EXISTS trg_pdf_fp_marriage_ai;
DROP TRIGGER IF EXISTS trg_pdf_fp_marriage_au_add;
DROP TRIGGER IF EXISTS trg_pdf_fp_marriage_au_release;
DROP TRIGGER IF EXISTS trg_pdf_fp_marriage_ad;

CREATE TRIGGER trg_pdf_fp_marriage_ai
AFTER INSERT ON certificate_of_marriage
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'marriage', NEW.id, NEW.registry_no, NEW.created_by
  FROM DUAL
 WHERE NEW.status = 'Active' AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> '';

CREATE TRIGGER trg_pdf_fp_marriage_au_add
AFTER UPDATE ON certificate_of_marriage
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'marriage', NEW.id, NEW.registry_no, NEW.updated_by
  FROM DUAL
 WHERE NEW.status = 'Active'
   AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> ''
   AND NOT EXISTS (SELECT 1 FROM pdf_fingerprints
                    WHERE pdf_hash = NEW.pdf_hash AND cert_type = 'marriage' AND record_id = NEW.id);

CREATE TRIGGER trg_pdf_fp_marriage_au_release
AFTER UPDATE ON certificate_of_marriage
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'marriage' AND record_id = NEW.id
   AND (NEW.status <> 'Active' OR NEW.pdf_hash IS NULL OR NEW.pdf_hash = ''
        OR OLD.pdf_hash IS NULL OR OLD.pdf_hash <> NEW.pdf_hash);

CREATE TRIGGER trg_pdf_fp_marriage_ad
AFTER DELETE ON certificate_of_marriage
FOR EACH ROW
DELETE FROM pdf_fingerprints WHERE cert_type = 'marriage' AND record_id = OLD.id;

DROP TRIGGER IF EXISTS trg_pdf_fp_license_ai;
DROP TRIGGER IF EXISTS trg_pdf_fp_license_au_add;
DROP TRIGGER IF EXISTS trg_pdf_fp_license_au_release;
DROP TRIGGER IF EXISTS trg_pdf_fp_license_ad;

CREATE TRIGGER trg_pdf_fp_license_ai
AFTER INSERT ON application_for_marriage_license
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'marriage_license', NEW.id, NEW.registry_no, NEW.created_by
  FROM DUAL
 WHERE NEW.status = 'Active' AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> '';

CREATE TRIGGER trg_pdf_fp_license_au_add
AFTER UPDATE ON application_for_marriage_license
FOR EACH ROW
INSERT INTO pdf_fingerprints (pdf_hash, cert_type, record_id, registry_no, reserved_by)
SELECT NEW.pdf_hash, 'marriage_license', NEW.id, NEW.registry_no, NEW.updated_by
  FROM DUAL
 WHERE NEW.status = 'Active'
   AND NEW.pdf_hash IS NOT NULL AND NEW.pdf_hash <> ''
   AND NOT EXISTS (SELECT 1 FROM pdf_fingerprints
                    WHERE pdf_hash = NEW.pdf_hash AND cert_type = 'marriage_license' AND record_id = NEW.id);

CREATE TRIGGER trg_pdf_fp_license_au_release
AFTER UPDATE ON application_for_marriage_license
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'marriage_license' AND record_id = NEW.id
   AND (NEW.status <> 'Active' OR NEW.pdf_hash IS NULL OR NEW.pdf_hash = ''
        OR OLD.pdf_hash IS NULL OR OLD.pdf_hash <> NEW.pdf_hash);

CREATE TRIGGER trg_pdf_fp_license_ad
AFTER DELETE ON application_for_marriage_license
FOR EACH ROW
DELETE FROM pdf_fingerprints WHERE cert_type = 'marriage_license' AND record_id = OLD.id;

