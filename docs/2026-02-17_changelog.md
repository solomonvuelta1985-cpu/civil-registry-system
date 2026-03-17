# CRDMS Development Changelog — February 17, 2026

## 1. Certificate of Live Birth — Form Progress Bar (Sticky Redesign)

### What Changed
The form progress indicator was redesigned with a modern corporate UI and made sticky so it follows the user while scrolling.

### Design Behavior
- **Default (not scrolled):** White background, subtle gray border, blends with the form area
- **Scrolled (sticky):** Transitions to dark slate (`#1e293b`) background with white text and drop shadow
- The transition between states is smooth (0.3s ease)

### Progress Step States
| State | White Mode | Dark Mode (Sticky) |
|-------|-----------|-------------------|
| **Pending** | Gray text, outlined circle with number | Muted gray text, dark circle |
| **Active** | Blue text, blue filled circle, blue bottom border | White text, blue filled circle |
| **Completed** | Dark text, navy circle with checkmark, navy bottom border | Light blue text, blue circle with checkmark |

### Features
- Circular step numbers (50% border-radius) with 2px outlined border
- Dashed connectors between steps (turns solid navy when completed)
- Checkmark icon replaces the step number on completed sections
- Right-aligned completion percentage with "COMPLETE" label
- 2px overall progress bar at the bottom edge (navy fill)
- Click any step to scroll to that section (offset accounts for sticky bar height)
- IntersectionObserver detects sticky state via sentinel element
- Mobile responsive: step labels hidden on small screens, only numbers + percentage shown

### Files Modified
- `public/certificate_of_live_birth.php` — CSS styles, HTML structure, JavaScript logic

---

## 2. PDF File Security & Organization

### Problem
- All PDFs were stored in a **flat `uploads/` folder** with no subdirectories
- **No access control** — anyone could access files directly via `http://localhost/iscan/uploads/filename.pdf` without authentication
- No organization by certificate type or date

### Solution

#### 2.1 Folder Structure
PDFs are now organized by **certificate type** and **registration year**:

```
uploads/
├── .htaccess          (blocks ALL direct browser access)
├── birth/
│   ├── 1970/
│   ├── 2014/
│   └── 2026/
├── death/
│   └── {year}/
├── marriage/
│   └── 2025/
└── marriage_license/
    └── {year}/
```

**Why registration year?**
- Always available on every record type
- Consistent across all 4 certificate types
- Matches how Philippine LCR offices physically file records
- Falls back to current year if no date provided

#### 2.2 Access Control
- `uploads/.htaccess` contains `Deny from all` — blocks all direct HTTP access
- New `api/serve_pdf.php` endpoint serves PDFs securely:
  - Checks user is **authenticated** (logged in)
  - Checks user has **correct permission** (birth_view, death_view, etc.)
  - Validates path to prevent **directory traversal attacks** (blocks `../`)
  - Verifies file extension is `.pdf`
  - Double-checks with `realpath()` that file is within `uploads/` directory
  - Serves with proper HTTP headers:
    - `Content-Type: application/pdf`
    - `Content-Disposition: inline`
    - `Cache-Control: private, max-age=3600`
    - `X-Content-Type-Options: nosniff`

#### 2.3 Upload Function Updated
`upload_file()` in `includes/functions.php` now accepts:
```php
upload_file($file, $type, $year)
// $type: 'birth', 'death', 'marriage', 'marriage_license'
// $year: registration year (int), falls back to current year
```

- Validates `$type` against allowed list (prevents invalid folder names)
- Casts `$year` to integer (prevents path injection)
- Auto-creates subdirectory if it doesn't exist
- Returns **relative path** (e.g., `birth/2026/cert_xxx.pdf`) instead of full server path

#### 2.4 Database Storage
`pdf_filename` column now stores the **relative path** including subfolder:
```
Before: cert_69634429634ff8.77907074_1768113193.pdf
After:  birth/2026/cert_69634429634ff8.77907074_1768113193.pdf
```

### Files Created
| File | Purpose |
|------|---------|
| `uploads/.htaccess` | Blocks all direct HTTP access to uploads |
| `api/serve_pdf.php` | Authenticated PDF serving endpoint |
| `database/migrations/migrate_pdfs.php` | One-time migration script |

### Files Modified
| File | Change |
|------|--------|
| `includes/functions.php` | `upload_file()` now accepts `$type` + `$year`, creates subfolders |
| `api/certificate_of_live_birth_save.php` | Passes `'birth'` + registration year to `upload_file()` |
| `api/certificate_of_death_save.php` | Passes `'death'` + registration year to `upload_file()` |
| `api/certificate_of_marriage_save.php` | Replaced manual upload code with `upload_file('marriage', $year)` |
| `api/application_for_marriage_license_save.php` | Replaced manual upload code with `upload_file('marriage_license', $year)` |
| `api/certificate_of_live_birth_update.php` | Passes `'birth'` + registration year to `upload_file()` |
| `api/certificate_of_death_update.php` | Passes `'death'` + registration year to `upload_file()` |
| `api/certificate_of_marriage_update.php` | Replaced manual upload code with `upload_file('marriage', $year)` |
| `api/application_for_marriage_license_update.php` | Replaced manual upload code with `upload_file('marriage_license', $year)` |
| `assets/js/record-preview-modal.js` | PDF URLs now use `serve_pdf.php` endpoint (load, open, download) |
| `public/certificate_of_live_birth.php` | Edit mode iframe uses `serve_pdf.php` |
| `public/certificate_of_death.php` | Edit mode iframe uses `serve_pdf.php` |
| `public/certificate_of_marriage.php` | Edit mode iframe uses `serve_pdf.php` |
| `public/application_for_marriage_license.php` | Edit mode iframe uses `serve_pdf.php` |
| `public/pdf_comparison_viewer.php` | PDF.js URL uses `serve_pdf.php` |

### Migration Results
- **12 files** successfully moved into organized subfolders
- **23 records** had missing source files (old test data, already deleted)
- Database `pdf_filename` and `pdf_filepath` columns updated for all migrated records

### How to Verify
1. Visit `http://localhost/iscan/uploads/` — should return **403 Forbidden**
2. Try direct PDF URL — should return **403 Forbidden**
3. Log in and open any record — PDF should load normally via `serve_pdf.php`
4. Upload a new birth certificate — file should appear in `uploads/birth/{year}/`
5. Log out and try `serve_pdf.php` URL — should return **401 Unauthorized**
