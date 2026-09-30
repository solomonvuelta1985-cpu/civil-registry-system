-- ============================================================
-- Migration 043: Correct PDF fingerprint release trigger behavior
-- ============================================================
-- When an active record replaces its PDF, keep the new reservation and
-- release only the old hash. When a record leaves Active status, release
-- both old and new hash values that may be associated with that record.

USE iscan_db;

DROP TRIGGER IF EXISTS trg_pdf_fp_birth_au_release;
CREATE TRIGGER trg_pdf_fp_birth_au_release
AFTER UPDATE ON certificate_of_live_birth
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'birth' AND record_id = NEW.id
   AND (
       (NEW.status <> 'Active' AND (pdf_hash = OLD.pdf_hash OR pdf_hash = NEW.pdf_hash))
       OR
       (NEW.status = 'Active' AND OLD.pdf_hash IS NOT NULL
        AND OLD.pdf_hash <> NEW.pdf_hash AND pdf_hash = OLD.pdf_hash)
   );

DROP TRIGGER IF EXISTS trg_pdf_fp_death_au_release;
CREATE TRIGGER trg_pdf_fp_death_au_release
AFTER UPDATE ON certificate_of_death
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'death' AND record_id = NEW.id
   AND (
       (NEW.status <> 'Active' AND (pdf_hash = OLD.pdf_hash OR pdf_hash = NEW.pdf_hash))
       OR
       (NEW.status = 'Active' AND OLD.pdf_hash IS NOT NULL
        AND OLD.pdf_hash <> NEW.pdf_hash AND pdf_hash = OLD.pdf_hash)
   );

DROP TRIGGER IF EXISTS trg_pdf_fp_marriage_au_release;
CREATE TRIGGER trg_pdf_fp_marriage_au_release
AFTER UPDATE ON certificate_of_marriage
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'marriage' AND record_id = NEW.id
   AND (
       (NEW.status <> 'Active' AND (pdf_hash = OLD.pdf_hash OR pdf_hash = NEW.pdf_hash))
       OR
       (NEW.status = 'Active' AND OLD.pdf_hash IS NOT NULL
        AND OLD.pdf_hash <> NEW.pdf_hash AND pdf_hash = OLD.pdf_hash)
   );

DROP TRIGGER IF EXISTS trg_pdf_fp_license_au_release;
CREATE TRIGGER trg_pdf_fp_license_au_release
AFTER UPDATE ON application_for_marriage_license
FOR EACH ROW
DELETE FROM pdf_fingerprints
 WHERE cert_type = 'marriage_license' AND record_id = NEW.id
   AND (
       (NEW.status <> 'Active' AND (pdf_hash = OLD.pdf_hash OR pdf_hash = NEW.pdf_hash))
       OR
       (NEW.status = 'Active' AND OLD.pdf_hash IS NOT NULL
        AND OLD.pdf_hash <> NEW.pdf_hash AND pdf_hash = OLD.pdf_hash)
   );

