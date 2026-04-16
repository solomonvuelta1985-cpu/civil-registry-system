# Upload Folder Reorganization

## Overview

PDF certificate files are now organized into a **year + last-name** folder structure instead of the previous flat year-only structure. This prevents mis-filing caused by incorrect registration dates and makes it easier to browse files by family name on the NAS file system.

---

## Folder Structure

### Previous Structure (before this change)

```
uploads/{type}/{year}/{filename}.pdf
```

Example: `uploads/birth/2026/cert_abc_1234567.pdf`

**Problems:**
- Year was derived solely from `date_of_registration`, which could be mistyped
- Registry `2014-1324` could end up in folder `1324/` if the registration date was wrong
- Registry `88-114` (meaning 1988) had no 2-digit year expansion
- Thousands of files could accumulate in a single year folder

### New Structure

```
# Case A — year is derivable (from subject event date or registry number)
uploads/{type}/{YEAR}/{LAST_NAME}/{filename}.pdf

# Case B — no year can be derived from anywhere
uploads/{type}/{LAST_NAME}/{filename}.pdf
```

**Examples:**
| Scenario | Path |
|----------|------|
| Birth, DOB=2014-03-15, last=Delos Santos | `uploads/birth/2014/DELOS_SANTOS/cert_xxx.pdf` |
| Death, registry=88-114, last=Reyes | `uploads/death/1988/REYES/cert_xxx.pdf` |
| Birth, no DOB, no registry, last=Tan | `uploads/birth/TAN/cert_xxx.pdf` |
| Birth, DOB=2014, last name blank | `uploads/birth/2014/UNKNOWN/cert_xxx.pdf` |
| No year, no last name | `uploads/birth/UNKNOWN/cert_xxx.pdf` |

---

## Year Selection Priority

The year for the folder is determined using this priority chain (highest wins):

| Priority | Condition | Year Source | Result |
|----------|-----------|-------------|--------|
| 1 | Both event date AND registry exist | **Event date year** | Case A |
| 2 | Event date exists, no registry | **Event date year** | Case A |
| 3 | Registry exists, no event date | **Registry number prefix** | Case A |
| 4 | Both missing | No year folder | Case B |

**"Event date"** is the subject's date for each certificate type:

| Certificate Type | Event Date Column | Last Name Column |
|-----------------|-------------------|------------------|
| Live Birth | `child_date_of_birth` | `child_last_name` |
| Death | `date_of_death` | `deceased_last_name` |
| Marriage | `date_of_marriage` | `husband_last_name` |
| Marriage License | `date_of_application` | `groom_last_name` |

---

## 2-Digit Year Expansion

For legacy registry numbers like `88-114` or `95-0032`, the system expands the 2-digit year prefix:

- Let `pivot = current 2-digit year` (e.g., in 2026, pivot = 26)
- If `YY > pivot` → `19YY` (e.g., `88` → `1988`, `95` → `1995`, `27` → `1927`)
- If `YY <= pivot` → `20YY` (e.g., `14` → `2014`, `26` → `2026`)

This pivot slides forward automatically each year. Modern records always use 4-digit years in the registry number (e.g., `2014-1324`), so this only applies to older legacy records.

---

## Last Name Normalization

The `folder_safe_last_name()` function normalizes last names for use as folder names:

| Input | Output |
|-------|--------|
| `Delos Santos` | `DELOS_SANTOS` |
| `Cruz Jr.` | `CRUZ_JR` |
| `O'Brien` | `OBRIEN` |
| `DE LA CRUZ` | `DE_LA_CRUZ` |
| ` ` (blank) | `UNKNOWN` |
| `null` | `UNKNOWN` |

Rules:
1. Uppercase the entire string
2. Replace spaces with underscores
3. Remove all non-alphanumeric/underscore characters (periods, apostrophes, etc.)
4. Trim leading/trailing underscores
5. If result is empty, use `UNKNOWN`

---

## Helper Functions

All helper functions are in `includes/functions.php`:

### `registry_folder_year(?string $registry_no): ?int`

Parses the year prefix from a registry number. Returns `null` if unparseable.

```php
registry_folder_year('2014-1324');  // 2014
registry_folder_year('88-114');     // 1988
registry_folder_year('14-001');     // 2014
registry_folder_year(null);         // null
registry_folder_year('');           // null
registry_folder_year('ABC-123');    // null
```

### `year_from_date(?string $date): ?int`

Extracts a 4-digit year from a date string. Returns `null` for empty/invalid input.

```php
year_from_date('2014-03-15');  // 2014
year_from_date('03/15/2014');  // 2014
year_from_date('');            // null
year_from_date(null);          // null
```

### `folder_safe_last_name(?string $last_name): string`

Normalizes a last name into a filesystem-safe folder name. Always returns a non-empty string.

```php
folder_safe_last_name('Delos Santos');  // 'DELOS_SANTOS'
folder_safe_last_name('Cruz Jr.');      // 'CRUZ_JR'
folder_safe_last_name(null);            // 'UNKNOWN'
folder_safe_last_name('');              // 'UNKNOWN'
```

### `upload_sub_dir(string $type, ?int $year, string $last_name_folder): string`

Builds the relative sub-directory path.

```php
upload_sub_dir('birth', 2014, 'DELOS_SANTOS');  // 'birth/2014/DELOS_SANTOS/'
upload_sub_dir('death', null, 'REYES');          // 'death/REYES/'
```

### `upload_file($file, $type, $year, $last_name_folder)`

Extended to accept an optional 4th parameter `$last_name_folder`. When provided, uses the new year + last-name scheme. When omitted, falls back to the legacy `{type}/{year}/` behavior for backward compatibility.

---

## Affected Endpoints

All 8 save/update API endpoints were updated:

| Endpoint | Year Source | Last Name Source |
|----------|------------|------------------|
| `api/certificate_of_live_birth_save.php` | `child_date_of_birth` > `registry_no` | `child_last_name` |
| `api/certificate_of_live_birth_update.php` | `child_date_of_birth` > `registry_no` | `child_last_name` |
| `api/certificate_of_death_save.php` | `date_of_death` > `registry_no` | `deceased_last_name` |
| `api/certificate_of_death_update.php` | `date_of_death` > `registry_no` | `deceased_last_name` |
| `api/certificate_of_marriage_save.php` | `date_of_marriage` > `registry_no` | `husband_last_name` |
| `api/certificate_of_marriage_update.php` | `date_of_marriage` > `registry_no` | `husband_last_name` |
| `api/application_for_marriage_license_save.php` | `date_of_application` > `registry_no` | `groom_last_name` |
| `api/application_for_marriage_license_update.php` | `date_of_application` > `registry_no` | `groom_last_name` |

---

## Reorganization Tool

### Purpose

Moves existing PDF files from the old folder structure into the new year + last-name structure, and updates the database (`pdf_filename` and `pdf_filepath` columns) to match.

### Two Interfaces

#### 1. CLI Script

Location: `scripts/reorganize_uploads_by_registry.php`

**Dry Run** (preview only, no changes):
```bash
php scripts/reorganize_uploads_by_registry.php --dry-run --token=YOUR_TOKEN
```

**Apply** (actually move files + update DB):
```bash
php scripts/reorganize_uploads_by_registry.php --apply --token=YOUR_TOKEN
```

On the NAS, run as the `http` user:
```bash
sudo -u http php /volume1/iscan/scripts/reorganize_uploads_by_registry.php --dry-run --token=YOUR_TOKEN
```

**Token guard:** The `--token` value must match the `REORG_TOKEN` environment variable set in `.env`. This prevents accidental execution.

**Logs:** Every run saves a detailed log to `scripts/logs/reorganize_YYYYMMDD_HHMMSS.log`.

#### 2. Admin Web Page

Location: `admin/reorganize_uploads.php`

- Accessible only to **Admin** users (uses `requireAdmin()`)
- CSRF-protected
- **Dry Run** button: shows what would be moved
- **Apply Changes** button: requires typing `I UNDERSTAND` to confirm
- Displays results inline with summary stats and a scrollable log output

### Safety Guarantees

| Concern | Protection |
|---------|-----------|
| **Duplication** | Files are **moved** (renamed), not copied. Only one copy ever exists. |
| **Overwriting** | If a file already exists at the destination, logged as `COLLISION` and skipped. |
| **Missing files** | If the source file doesn't exist on disk, logged as `MISSING` and skipped (DB not updated). |
| **Re-running** | Completely safe. Already-correct files are skipped. Only files still in the wrong place are moved. |
| **Old folders** | Never deleted automatically. Empty folders are left for manual cleanup after verification. |
| **Accidental web trigger** | Apply requires typed confirmation (`I UNDERSTAND`). |
| **Accidental CLI trigger** | Requires matching `--token` value from `.env`. |
| **Database integrity** | Each row is updated individually. If `rename()` fails, the DB row is not updated. |

### What the Script Does Per Row

```
1. Read row from certificate table (id, registry_no, event_date, last_name, pdf_filename)
2. Compute correct year: year_from_date(event_date) ?? registry_folder_year(registry_no)
3. Compute last name folder: folder_safe_last_name(last_name)
4. Build target path: {type}/{year}/{LAST_NAME}/filename.pdf  or  {type}/{LAST_NAME}/filename.pdf
5. If current path == target path → SKIP (already correct)
6. If source file missing on disk → SKIP + log MISSING
7. If target file already exists → SKIP + log COLLISION
8. [Apply mode only] mkdir -p target dir, rename file, UPDATE db row
```

### Summary Output

After each run, the tool displays:

```
=== Summary (DRY-RUN) ===
Total rows with PDF : 150
Already correct     : 120
To move / Moved     : 25
Missing on disk     : 3
Collision at target : 1
Errors              : 1
```

---

## Deployment Steps (NAS Production)

### Prerequisites

- SSH access to the NAS
- `REORG_TOKEN` set in `/volume1/iscan/.env`

### Step-by-Step

#### 1. Add REORG_TOKEN to .env (one-time)

```bash
sudo sh -c 'echo "REORG_TOKEN=reorg2026" >> /volume1/iscan/.env'
```

#### 2. Pull the code

```bash
sudo chown -R mcrobaggao:users /volume1/iscan && \
git -C /volume1/iscan reset --hard HEAD && \
git -C /volume1/iscan pull origin main && \
sudo chown -R http:http /volume1/iscan && \
sudo chmod -R 755 /volume1/iscan
```

#### 3. Dry Run

**CLI:**
```bash
sudo -u http php /volume1/iscan/scripts/reorganize_uploads_by_registry.php --dry-run --token=reorg2026
```

**Or Web:** Go to `https://iscan.cdrms.online/admin/reorganize_uploads.php` → click **Dry Run**.

Review the output. Verify the moves make sense.

#### 4. Apply

**CLI:**
```bash
sudo -u http php /volume1/iscan/scripts/reorganize_uploads_by_registry.php --apply --token=reorg2026
```

**Or Web:** Type `I UNDERSTAND` → click **Apply Changes**.

#### 5. Fix Permissions

```bash
sudo chown -R http:http /volume1/iscan/uploads
sudo chmod -R 755 /volume1/iscan/uploads
```

#### 6. Verify

Open 3-5 records in the web UI that were previously mis-filed. Confirm the PDF viewer loads them correctly.

#### 7. Cleanup (optional)

Remove empty old folders after verification:

```bash
# Preview empty folders
find /volume1/iscan/uploads -type d -empty -print

# Delete empty folders (only after verification!)
find /volume1/iscan/uploads -type d -empty -delete
```

---

## Backward Compatibility

| Component | Impact |
|-----------|--------|
| `upload_file()` | Backward compatible. If `$last_name_folder` is omitted, falls back to legacy `{type}/{year}/` behavior. |
| `delete_file()` | No change needed. Accepts relative paths, works with deeper nesting. |
| `serve_pdf.php` | No change needed. Extracts cert type from first path segment (`$parts[0]`), which is unchanged. Path validation regex allows uppercase letters and underscores. |
| `pdf_integrity_scan.php` | No change needed. Uses `UPLOAD_DIR . $pdf_filename` which resolves correctly. |
| `pdf_backup_verify.php` | No change needed. Same relative path resolution. |
| Database | No schema change. `pdf_filename VARCHAR(255)` stores the new relative path (e.g., `birth/2014/DELOS_SANTOS/cert_xxx.pdf`). |

---

## Files Changed

| File | Change |
|------|--------|
| `includes/functions.php` | Added `registry_folder_year()`, `year_from_date()`, `folder_safe_last_name()`, `upload_sub_dir()`. Extended `upload_file()` with optional 4th parameter. |
| `api/certificate_of_live_birth_save.php` | Uses new year + last-name folder logic. |
| `api/certificate_of_live_birth_update.php` | Uses new year + last-name folder logic. |
| `api/certificate_of_death_save.php` | Uses new year + last-name folder logic. |
| `api/certificate_of_death_update.php` | Uses new year + last-name folder logic. |
| `api/certificate_of_marriage_save.php` | Uses new year + last-name folder logic. |
| `api/certificate_of_marriage_update.php` | Uses new year + last-name folder logic. |
| `api/application_for_marriage_license_save.php` | Uses new year + last-name folder logic. |
| `api/application_for_marriage_license_update.php` | Uses new year + last-name folder logic. |
| `api/serve_pdf.php` | Updated comment to reflect new path format. |
| `includes/sidebar_nav.php` | Added "Reorganize Uploads" link under Maintenance section (admin-only). |
| `includes/reorganize_uploads.php` | **New.** Shared core logic for the reorganization tool. |
| `scripts/reorganize_uploads_by_registry.php` | **New.** CLI entry point for reorganization. |
| `admin/reorganize_uploads.php` | **New.** Admin web page for reorganization with dry-run/apply. |

---

## Troubleshooting

### PDF not loading after reorganization

The `pdf_filename` in the database may not have been updated. Check the database:

```sql
SELECT id, registry_no, pdf_filename FROM certificate_of_live_birth WHERE id = <ID>;
```

The `pdf_filename` should match the actual file path relative to `uploads/`. If it still shows the old path, re-run the reorganization tool.

### "MISSING" files in the log

The file referenced in the database doesn't exist on disk at the expected old path. Possible causes:
- File was manually moved or deleted
- File was already moved by a previous reorganization run but the DB wasn't updated (unlikely — the script does both atomically)

Check if the file exists at the new target path. If so, manually update the DB row.

### "COLLISION" files in the log

A different file already exists at the target path. This could happen if two records somehow reference different files that would land in the same folder with the same generated filename (extremely unlikely due to `uniqid()` + `time()` naming).

Investigate manually — check both files and decide which to keep.

### Permission denied errors

After running the reorganization, fix upload directory ownership:

```bash
sudo chown -R http:http /volume1/iscan/uploads
sudo chmod -R 755 /volume1/iscan/uploads
```

### Token mismatch

Ensure the `REORG_TOKEN` in `.env` matches the `--token` value exactly:

```bash
grep REORG_TOKEN /volume1/iscan/.env
```
