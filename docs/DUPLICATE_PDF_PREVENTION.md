# Duplicate PDF Prevention — Feature Documentation

**iScan Civil Registry Records Management System**
**Version:** 1.0 | **Date:** 2026-04-08 | **Migration:** 013

---

## Table of Contents

1. [Overview](#1-overview)
2. [Problem It Solves](#2-problem-it-solves)
3. [How It Works](#3-how-it-works)
4. [System Architecture](#4-system-architecture)
5. [Database Schema](#5-database-schema)
6. [File Reference](#6-file-reference)
7. [Setup Guide](#7-setup-guide)
8. [User Experience](#8-user-experience)
9. [Testing the Feature](#9-testing-the-feature)
10. [Troubleshooting](#10-troubleshooting)
11. [PHP Function Reference](#11-php-function-reference)

---

## 1. Overview

The **Duplicate PDF Prevention** feature stops users from accidentally attaching the **same PDF file to multiple civil registry records**. Every PDF uploaded to the system is fingerprinted with a SHA-256 hash, and before a record is saved, the system checks whether that exact fingerprint already exists in any of the 4 certificate tables.

If a duplicate is detected, the upload is **rejected with HTTP 409** and the user is shown a clear message pointing to the record that already owns the file.

### Key characteristics

- Works at **upload time** — duplicates are blocked before the database transaction begins
- Enforced **across all 4 certificate types** — birth, death, marriage, marriage license
- **Strict block** — no "override" option; legitimate duplicates are impossible in civil registry records
- Uses **SHA-256 content hashing** — renaming or re-saving a PDF does not bypass the check
- Applies to **both new uploads AND updates** — replacing a record's PDF is also checked
- **Automatic cleanup** — rejected files are deleted from disk immediately, no orphans left behind

---

## 2. Problem It Solves

### The scenario

A user (especially a new or tired one) opens a certificate form, clicks "Upload PDF," and accidentally picks the **wrong file** from their folder — for example, a PDF that's already attached to a different record. Without protection:

- The same scanned document ends up linked to two records
- The data in the form doesn't match the attached PDF
- Discovery happens months later during an audit, when it's very hard to fix

### Before vs After

| Scenario | Before | After |
|---|---|---|
| User picks wrong PDF that belongs to another record | Saved silently, corrupts data | Blocked with clear message |
| User re-uploads the SAME PDF to the SAME record (no change) | Saved, creates backup churn | Blocked (update endpoint excludes current record — see note) |
| User uploads renamed copy of existing PDF | Saved, creates duplicate | Blocked (hash is content-based, not filename-based) |
| User uploads a birth cert PDF into a death cert form | Saved, cross-type duplicate | Blocked (check spans all 4 tables) |
| Two legitimately different PDFs with similar content | Both saved | Both saved (hashes differ) |

> **Note on self-updates:** On update endpoints, the current record is excluded from the duplicate check, so re-saving a record without actually changing the file is a safe no-op. The check only triggers against **other** records.

---

## 3. How It Works

### Upload flow

```
1. User submits form with PDF attached
2. validate_file_upload() — MIME type, size, extension checks
3. upload_file() — moves file to uploads/{type}/{year}/ and computes SHA-256
4. check_pdf_duplicate() — scans all 4 tables for matching hash
   ├─ Match found → delete_file() + HTTP 409 with error message
   └─ No match → proceed to DB transaction
5. INSERT / UPDATE the record
```

### Why SHA-256

- **Content-based** — filename, metadata, or timestamp changes don't affect the hash
- **Collision-resistant** — two different PDFs will never produce the same hash in practice
- **Fast** — PHP's `hash_file('sha256', ...)` hashes a typical scan (1-5 MB) in milliseconds
- **Indexable** — stored as `CHAR(64)` for efficient B-tree lookups

### Why application-level check (not UNIQUE constraint)

A `UNIQUE` index in MySQL would also prevent duplicates, but it would fail with a cryptic `SQLSTATE[23000]: Integrity constraint violation` error. The application-level check lets us return an **actionable error message** like:

> *"This PDF is already attached to Certificate of Live Birth Registry No. 2025-00123. Please verify you selected the correct file. If this is the same document, open the existing record instead."*

The database index is still used — it's just **non-unique** so the check can scan efficiently without enforcing rejection at the DB layer.

---

## 4. System Architecture

```
┌─────────────────────────────────────────────────┐
│  public/certificate_of_live_birth.php (form)   │
│  public/certificate_of_death.php                │
│  public/certificate_of_marriage.php             │
│  public/application_for_marriage_license.php   │
└─────────────────────────┬───────────────────────┘
                          │ POST multipart/form-data
                          ▼
┌─────────────────────────────────────────────────┐
│  api/*_save.php    (new records)               │
│  api/*_update.php  (edits)                      │
│                                                 │
│  ┌───────────────────────────────────────────┐ │
│  │ 1. upload_file()                          │ │
│  │    └─ compute_file_hash() → SHA-256       │ │
│  │ 2. check_pdf_duplicate()                  │ │
│  │    ├─ SELECT across 4 cert tables         │ │
│  │    └─ returns {cert_type, id, registry_no}│ │
│  │ 3. If duplicate → delete_file() + 409     │ │
│  │ 4. Else → DB transaction                  │ │
│  └───────────────────────────────────────────┘ │
└─────────────────────────┬───────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────┐
│  MySQL: iscan_db                                │
│  ┌─────────────────────────────────────────┐   │
│  │ certificate_of_live_birth               │   │
│  │   pdf_hash CHAR(64)  [idx_pdf_hash]     │   │
│  │ certificate_of_death                    │   │
│  │   pdf_hash CHAR(64)  [idx_pdf_hash]     │   │
│  │ certificate_of_marriage                 │   │
│  │   pdf_hash CHAR(64)  [idx_pdf_hash]     │   │
│  │ application_for_marriage_license        │   │
│  │   pdf_hash CHAR(64)  [idx_pdf_hash]     │   │
│  └─────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

---

## 5. Database Schema

### Columns (added in migration 007)

All 4 certificate tables have:

```sql
pdf_hash CHAR(64) NULL
    COMMENT 'SHA-256 hash of uploaded PDF for integrity verification'
```

### Indexes (added in migration 013)

All 4 certificate tables have:

```sql
INDEX idx_pdf_hash (pdf_hash)
```

- **Non-unique** by design — uniqueness is enforced in application code for better error messages
- B-tree index → O(log n) lookup even with millions of records
- Negligible write overhead (index updated once per insert/update)

### Verify indexes exist

```sql
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'iscan_db' AND INDEX_NAME = 'idx_pdf_hash';
```

Expected output: 4 rows, one for each certificate table.

---

## 6. File Reference

### New files

| File | Purpose |
|---|---|
| [database/migrations/013_add_pdf_hash_index.sql](../database/migrations/013_add_pdf_hash_index.sql) | Creates `idx_pdf_hash` on all 4 certificate tables |

### Modified files

| File | Change |
|---|---|
| [includes/functions.php](../includes/functions.php) | Added `check_pdf_duplicate()` helper (lines 192-240) |
| [api/certificate_of_live_birth_save.php](../api/certificate_of_live_birth_save.php) | Added duplicate check after `upload_file()` |
| [api/certificate_of_live_birth_update.php](../api/certificate_of_live_birth_update.php) | Added duplicate check (excludes current record) |
| [api/certificate_of_death_save.php](../api/certificate_of_death_save.php) | Added duplicate check after `upload_file()` |
| [api/certificate_of_death_update.php](../api/certificate_of_death_update.php) | Added duplicate check (excludes current record) |
| [api/certificate_of_marriage_save.php](../api/certificate_of_marriage_save.php) | Added duplicate check after `upload_file()` |
| [api/certificate_of_marriage_update.php](../api/certificate_of_marriage_update.php) | Added duplicate check (excludes current record) |
| [api/application_for_marriage_license_save.php](../api/application_for_marriage_license_save.php) | Added duplicate check after `upload_file()` |
| [api/application_for_marriage_license_update.php](../api/application_for_marriage_license_update.php) | Added duplicate check (excludes current record) |

---

## 7. Setup Guide

### Local (XAMPP)

Already applied. If you need to re-run:

```bash
/c/xampp/mysql/bin/mysql.exe -u root iscan_db < c:/xampp/htdocs/iscan/database/migrations/013_add_pdf_hash_index.sql
```

### Synology NAS (production)

SSH into the NAS and run:

```bash
cd /volume1/iscan
mysql -u root -p iscan_db < database/migrations/013_add_pdf_hash_index.sql
```

Or via phpMyAdmin: paste the contents of `013_add_pdf_hash_index.sql` into the SQL tab of the `iscan_db` database and execute.

### No code deployment needed beyond `git pull`

The feature is entirely server-side PHP + one SQL migration. There are no new JavaScript files, no vendor dependencies, and no configuration changes.

---

## 8. User Experience

### What the user sees on duplicate detection

When a duplicate PDF is rejected, the form displays (via Notiflix alert):

> **Upload Failed**
> This PDF is already attached to Certificate of Live Birth Registry No. 2025-00123. Please verify you selected the correct file. If this is the same document, open the existing record instead.

### What to do about it

1. **Verify the file** — check that they selected the correct PDF from their folder
2. **If it IS the same document** — they should open the existing record instead of creating a new one
3. **If it's a scanning mistake** — rescan the physical document and upload the fresh file
4. **If they believe it's a false positive** — contact an admin to investigate via the PDF integrity report

### HTTP response

```
Status: 409 Conflict
Content-Type: application/json

{
  "success": false,
  "message": "This PDF is already attached to Certificate of Live Birth Registry No. 2025-00123. Please verify you selected the correct file. If this is the same document, open the existing record instead.",
  "data": null
}
```

---

## 9. Testing the Feature

### Test 1: Block duplicate upload (new record)

1. Find an existing record with a PDF attached (e.g., birth record with ID 1)
2. Copy that PDF to your Desktop
3. Open a new birth certificate form
4. Fill out the fields and attach the copied PDF
5. Click Save
6. **Expected:** Error message pointing to birth record ID 1 with its registry no

### Test 2: Block cross-type duplicate

1. Take a PDF that's attached to a birth record
2. Open a death certificate form
3. Attach the same PDF
4. Click Save
5. **Expected:** Error message pointing to the birth record

### Test 3: Allow self-update (same record, same PDF)

1. Open an existing birth record for editing
2. Re-attach the **same PDF** it already has (same file, no changes)
3. Click Save
4. **Expected:** Update succeeds — the check excludes the current record

### Test 4: Allow legitimate update (same record, new PDF)

1. Open an existing birth record for editing
2. Attach a **different PDF** that isn't attached to any other record
3. Click Save
4. **Expected:** Update succeeds — old PDF is backed up, new one is attached

### Test 5: Block update with PDF from another record

1. Open birth record A for editing
2. Attach a PDF that's already attached to birth record B
3. Click Save
4. **Expected:** Error message pointing to record B

### Test 6: Rename bypass attempt

1. Copy an existing PDF and rename it (e.g., `original.pdf` → `renamed.pdf`)
2. Upload the renamed copy
3. **Expected:** Still blocked — hash is content-based, not filename-based

---

## 10. Troubleshooting

### "I got a duplicate error but I'm sure this is a new PDF"

**Cause:** The PDF might be a byte-for-byte identical copy of an existing one (e.g., someone re-saved the same scan). Or the PDF was previously attached, then the record was deleted but the hash lookup caught a leftover.

**Fix:**
- Check if the pointed-to record still exists in the system
- If the record was soft-deleted (in trash), restore or permanently delete it
- Rescan the physical document to produce a fresh PDF

### "The check is running on every save — is it slow?"

**No.** With the `idx_pdf_hash` index, lookups across all 4 tables take <1ms even with tens of thousands of records. If you're seeing slow saves, the bottleneck is elsewhere (likely network or file upload, not the hash check).

### "Can I disable the check temporarily?"

Not via config — it's enforced in all 8 save/update endpoints. If you absolutely need to bypass it for a data migration, comment out the `if ($pdf_hash) { ... }` block in the relevant endpoint, then restore it. **Do not ship with the check disabled** — it's the primary protection against the data integrity problem it exists to solve.

### "How do I find existing duplicates in the database?"

Use this query to find any PDF hash that appears more than once across all tables:

```sql
SELECT pdf_hash, COUNT(*) as occurrences
FROM (
    SELECT pdf_hash FROM certificate_of_live_birth       WHERE pdf_hash IS NOT NULL
    UNION ALL
    SELECT pdf_hash FROM certificate_of_death            WHERE pdf_hash IS NOT NULL
    UNION ALL
    SELECT pdf_hash FROM certificate_of_marriage         WHERE pdf_hash IS NOT NULL
    UNION ALL
    SELECT pdf_hash FROM application_for_marriage_license WHERE pdf_hash IS NOT NULL
) AS all_hashes
GROUP BY pdf_hash
HAVING COUNT(*) > 1;
```

If this returns rows, you have legacy duplicates that existed **before** this feature was deployed. Review them manually and consolidate.

### "Hash is NULL on old records"

Records created before migration 007 (the one that added `pdf_hash`) won't have a hash. Run the backfill script to populate them:

```
api/pdf_hash_backfill.php
```

Once backfilled, those records will also be protected by the duplicate check.

---

## 11. PHP Function Reference

### `check_pdf_duplicate()`

Location: [includes/functions.php:192-240](../includes/functions.php#L192-L240)

```php
function check_pdf_duplicate(
    PDO $pdo,
    string $hash,
    ?string $exclude_type = null,
    ?int $exclude_id = null
): ?array
```

#### Parameters

| Name | Type | Description |
|---|---|---|
| `$pdo` | `PDO` | Active database connection |
| `$hash` | `string` | SHA-256 hex string (64 chars) to look up |
| `$exclude_type` | `?string` | Certificate type to exclude: `'birth'`, `'death'`, `'marriage'`, `'marriage_license'`, or `null` |
| `$exclude_id` | `?int` | Record ID within `$exclude_type` to exclude. Used on update to ignore the current record. |

#### Returns

- `array` with keys `cert_type`, `id`, `registry_no`, `label` if a duplicate exists
- `null` if the hash is not attached to any record

#### Example (new record save)

```php
$upload_result = upload_file($_FILES['pdf_file'], 'birth', 2026);
$pdf_hash = $upload_result['hash'];

$dup = check_pdf_duplicate($pdo, $pdf_hash);
if ($dup) {
    delete_file($upload_result['filename']);
    json_response(
        false,
        "This PDF is already attached to {$dup['label']} Registry No. {$dup['registry_no']}.",
        null,
        409
    );
}
```

#### Example (update, exclude current record)

```php
$dup = check_pdf_duplicate($pdo, $pdf_hash, 'birth', (int)$record_id);
if ($dup) {
    delete_file($pdf_filename);
    json_response(false, "Duplicate detected...", null, 409);
}
```

### Related functions

| Function | Location | Purpose |
|---|---|---|
| `compute_file_hash()` | [includes/functions.php:187](../includes/functions.php#L187) | Computes SHA-256 of a file on disk |
| `upload_file()` | [includes/functions.php:84](../includes/functions.php#L84) | Uploads PDF, returns path + hash |
| `delete_file()` | [includes/functions.php:133](../includes/functions.php#L133) | Removes file from disk (used to clean up rejected uploads) |
| `backup_pdf_file()` | [includes/functions.php:199](../includes/functions.php#L199) | Moves old PDF to backup folder on update |

---

## Version History

| Version | Date | Change |
|---|---|---|
| 1.0 | 2026-04-08 | Initial implementation — migration 013, `check_pdf_duplicate()` helper, wired into all 8 save/update endpoints |
