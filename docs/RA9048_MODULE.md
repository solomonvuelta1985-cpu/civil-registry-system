                                                            # RA 9048 / RA 10172 Module — Implementation Documentation

> **Purpose:** Reference doc for resuming work on the petition module without re-reading the entire chat history. Covers what's built, what's pending, key decisions, file map, and the resume playbook.
>
> **Last updated:** 2026-04-28
> **Author of changes:** Claude Code (Opus 4.7) working with the Baggao LCRO admin
> **Project:** iScan (Civil Registry Document Management System) at `c:\xampp\htdocs\iscan`

---

## Table of Contents

1. [Module Purpose & Legal Background](#1-module-purpose--legal-background)
2. [Current Status (Phase Tracker)](#2-current-status-phase-tracker)
3. [Architecture Overview](#3-architecture-overview)
4. [Database Schema](#4-database-schema)
5. [File Map (What Lives Where)](#5-file-map-what-lives-where)
6. [Key Decisions & Rationale](#6-key-decisions--rationale)
7. [Document Generation Pipeline](#7-document-generation-pipeline)
8. [Template System](#8-template-system)
9. [Placeholder Reference](#9-placeholder-reference)
10. [Phase 4b — Remaining Templates](#10-phase-4b--remaining-templates)
11. [Common Issues & Fixes](#11-common-issues--fixes)
12. [Resume Playbook](#12-resume-playbook)
13. [Reference URLs (Manual Testing)](#13-reference-urls-manual-testing)

---

## 1. Module Purpose & Legal Background

### What this module does

Automates the office paperwork for petitions filed at the Local Civil Registrar's Office (LCRO) of Baggao, Cagayan under:

- **Republic Act 9048** — Correction of Clerical Error / Change of First Name without judicial order
- **Republic Act 10172** — Amends RA 9048 to also cover corrections to **day/month of birth** and **sex**

The admin verifies the client's documents face-to-face, encodes the petition data into the system, clicks **"Verify & File"**, and the system auto-generates these `.docx` files:

| Document | When generated |
|---|---|
| **Petition** (CCE Form 1.1 or CFN Form 4.1) | Always |
| **Order for Publication** | CFN + CCE-RA10172 only |
| **Public Notice** (newspaper publication) | CFN + CCE-RA10172 only |
| **Certificate of Posting** | Always |
| **Certification of Proof of Filing** | Always |

### Three petition subtypes

The form's "Petition Type" radio drives all downstream behavior:

| Subtype | Legal basis | Posting | Publication | Notes |
|---|---|---|---|---|
| `CCE_minor` | RA 9048 | ✅ 10 working days | ❌ | Misspellings, missing letters, typos |
| `CCE_10172` | RA 9048 as amended by RA 10172 | ✅ 10 working days | ✅ 2 consecutive weeks | Sex, day/month of birth. Sex correction also requires Medical Certification from gov't physician |
| `CFN` | RA 9048 | ✅ 10 working days | ✅ 2 consecutive weeks | Change of first name |

### Workflow

1. Client brings documents to LCRO
2. Admin verifies via face-to-face interview
3. Admin opens [`/public/ra9048/petition.php`](../public/ra9048/petition.php) → fills out the form (~30 fields including corrections grid + supporting docs grid)
4. Admin clicks **Verify & File**
5. System saves the petition record + child rows
6. System auto-generates the relevant DOCX files
7. Success panel shows download links
8. Admin can later regenerate or download from the records page

### Office constants (Baggao LCRO)

Defined in [`includes/config_ra9048.php`](../includes/config_ra9048.php):

```php
LCRO_OFFICE_NAME         = 'OFFICE OF THE CIVIL REGISTRAR'
LCRO_OFFICE_MUNICIPALITY = 'BAGGAO'
LCRO_OFFICE_PROVINCE     = 'CAGAYAN'
LCRO_OFFICE_ADDRESS      = 'Ground Floor, Executive Building, Zone 4, San Jose, Baggao, Cagayan, 3506'
LCRO_OFFICE_EMAIL        = 'mcrbaggao@gmail.com'
LCRO_MCR_FULL_NAME       = 'ATANACIO G. TUNGPALAN'
LCRO_MCR_TITLE           = 'Municipal Civil Registrar'
```

Update these constants when the MCR changes.

---

## 2. Current Status (Phase Tracker)

### Phase 1 — Foundation ✅ **COMPLETE**
- [x] Migration `023_ra9048_workflow_fields.sql` (30+ new columns + 2 child tables)
- [x] Templates moved from `documents/` → `documents/templates/`
- [x] Lightweight `DocxTemplateProcessor` built (replaces PHPWord vendoring)
- [x] LCRO office constants + `ra9048_citation()` helper added

### Phase 2 — Form Rewrite ✅ **COMPLETE**
- [x] Full rewrite of [`public/ra9048/petition.php`](../public/ra9048/petition.php) (9 sections, ~30 new fields)
- [x] [`api/ra9048/_petition_helpers.php`](../api/ra9048/_petition_helpers.php) — shared extract/validate/persist helpers
- [x] Save and update APIs handle all new fields + child rows
- [x] [`assets/js/ra9048-petition-form.js`](../assets/js/ra9048-petition-form.js) — subtype toggling, fee/citation auto-fill, dynamic grids, opposition deadline auto-calc
- [x] [`assets/css/ra9048-petition-form.css`](../assets/css/ra9048-petition-form.css)

### Phase 3 — Lookup & Validation ✅ **COMPLETE**
- [x] [`api/ra9048/lookup_owner.php`](../api/ra9048/lookup_owner.php) — searches existing COLB records by name/registry
- [x] [`api/ra9048/check_petition_number.php`](../api/ra9048/check_petition_number.php) — uniqueness check
- [x] Form JS wires both APIs (debounced search modal, live duplicate-number check)

### Phase 4a — Generator + Records Integration ✅ **COMPLETE**
- [x] [`api/ra9048/generate_document.php`](../api/ra9048/generate_document.php) — orchestrator
- [x] [`api/ra9048/list_documents.php`](../api/ra9048/list_documents.php) — returns generated files for a petition
- [x] [`api/serve_ra9048_doc.php`](../api/serve_ra9048_doc.php) — secure DOCX serve endpoint
- [x] [`assets/js/ra9048-verify-and-file.js`](../assets/js/ra9048-verify-and-file.js) — post-save pipeline
- [x] Records page: subtype/status badges, Files popover, Download/Regenerate buttons
- [x] Edit-mode toolbar: Download + Regenerate buttons

### Phase 4b — Templates 🟡 **IN PROGRESS**

| # | Template | Status | Notes |
|---|---|---|---|
| 1 | **Petition (CCE)** | ✅ Built from scratch via [`scripts/build_template_petition_cce.php`](../scripts/build_template_petition_cce.php) | v2 with subtle colors, CRG action section, Payment table |
| 2 | **Petition (CFN)** | ⬜ Not built | Will reuse Template 1 design + add CFN-specific grounds section |
| 3 | **Order for Publication** | ⬜ Not built | Smaller doc, court-style order |
| 4 | **Certificate of Posting** | ⬜ Not built | Includes posting period table |
| 5 | **Certification of Proof of Filing** | ⬜ Not built | Has FROM/TO grid like petition |
| 6 | **RA 10172 Public Notice** | ⬜ Skipped | Originally `.pptx`. Will defer or recreate as Word later |

### Bonus work completed (out of original plan)

✅ **Database merge** — RA 9048 tables moved from separate `iscan_ra9048_db` to main `iscan_db`. `$pdo_ra` aliased to `$pdo` for backward compat. See [migration 024](../database/migrations/024_merge_ra9048_into_iscan_db.sql).

✅ **Multiple block markers** — Engine now supports `${corrections_block}` appearing twice in one document (used in petition's main grid + ACTION TAKEN grid).

✅ **Inline FROM/TO scalars** — `${first_value_from}`, `${first_value_to}`, `${first_description}`, `${first_nature_label}` for templates that reference corrections inline (Order for Publication body text).

---

## 3. Architecture Overview

### Tech stack
- **PHP 8.2** (XAMPP local; Synology PHP 8.2 production)
- **MariaDB 10.5** (XAMPP local; Synology MariaDB 10.5 production)
- **Vanilla JS** (no frameworks)
- **No Composer** — project convention; everything's vendored or built-in
- **DOCX generation** — pure PHP using `ZipArchive` (no PHPWord, no other libraries)

### Module boundaries

```
┌──────────────────────────────────────────────────────────────┐
│  iScan Main App (existing — NOT touched by this module)      │
│  - Birth/Marriage/Death/Marriage License records             │
│  - Auth, sessions, file uploads, OCR                         │
│  - $pdo connection to iscan_db                                │
└──────────────────────────────────────────────────────────────┘
                            ▲
                            │  Reads certificate_of_live_birth
                            │  for COLB lookup ($pdo)
                            │
┌──────────────────────────────────────────────────────────────┐
│  RA 9048 Module                                              │
│                                                              │
│  Frontend                                                    │
│  ├── public/ra9048/index.php          Landing (3 cards)      │
│  ├── public/ra9048/petition.php       Form (CCE/CFN)         │
│  ├── public/ra9048/legal_instrument.php  (NOT MODIFIED)      │
│  ├── public/ra9048/court_decree.php   (NOT MODIFIED)         │
│  └── public/ra9048/records.php        Tabbed records list    │
│                                                              │
│  Backend                                                     │
│  ├── api/ra9048/petition_save.php     │                      │
│  ├── api/ra9048/petition_update.php   │ (Phase 2 rewritten)  │
│  ├── api/ra9048/petition_delete.php   │                      │
│  ├── api/ra9048/_petition_helpers.php │ Shared payload code  │
│  ├── api/ra9048/lookup_owner.php       Phase 3               │
│  ├── api/ra9048/check_petition_number.php  Phase 3           │
│  ├── api/ra9048/generate_document.php  Phase 4a              │
│  ├── api/ra9048/list_documents.php     Phase 4a              │
│  └── api/serve_ra9048_doc.php          Phase 4a (secure DOCX serve)
│                                                              │
│  Templates                                                   │
│  └── documents/templates/*.docx       Filled by engine       │
│                                                              │
│  Generated Output                                            │
│  └── uploads/ra9048/generated/petition_{id}/*.docx           │
└──────────────────────────────────────────────────────────────┘
```

---

## 4. Database Schema

All tables live in `iscan_db` (after the merge in migration 024). The legacy `iscan_ra9048_db` was dropped.

### `petitions` (main table — 51 columns total after migration 023)

Original columns (from migration 021):
- `id` (PK), `petition_type` (ENUM CCE/CFN), `date_of_filing`, `document_owner_names`, `petitioner_names`, `document_type` (ENUM COLB/COM/COD), `petition_of`, `special_law`, `fee_amount`, `remarks`, `pdf_filename`, `pdf_filepath`, `pdf_hash`, `status`, `created_by`, `updated_by`, timestamps

Added in migration 023:
- **Identity:** `petition_number` (UNIQUE INDEX), `petition_subtype` (ENUM `CCE_minor`/`CCE_10172`/`CFN`)
- **Petitioner:** `petitioner_nationality`, `petitioner_address`, `petitioner_id_type`, `petitioner_id_number`, `is_self_petition`, `relation_to_owner`
- **Document owner:** `owner_dob`, `owner_birthplace_city`, `owner_birthplace_province`, `owner_birthplace_country`, `registry_number`, `father_full_name`, `mother_full_name`
- **CFN-specific:** `cfn_ground` (ENUM `difficult`/`habitual`/`ridicule`/`confusion`), `cfn_ground_detail`
- **Notarization:** `notarized_at`
- **Posting:** `posting_start_date`, `posting_end_date`, `posting_location` (default `MUNICIPAL HALL BULLETIN BOARD`), `posting_cert_issued_at`
- **Publication:** `order_date`, `publication_date_1`, `publication_date_2`, `publication_newspaper`, `publication_place`, `opposition_deadline`
- **Payment & decision:** `receipt_number`, `payment_date`, `certification_issued_at`, `decision_date`
- **Workflow status:** `status_workflow` (ENUM `Filed`/`Posted`/`Published`/`Decided`/`Endorsed`)

### `petition_corrections` (NEW child table)

```sql
id, petition_id (FK), item_no, nature (ENUM CCE/CFN),
description, value_from, value_to, created_at
```

### `petition_supporting_docs` (NEW child table)

```sql
id, petition_id (FK), item_no, doc_label, created_at
```

### Legacy tables (untouched, still functional)

- `legal_instruments` — AUSF/Supplemental/Legitimation (no document automation)
- `court_decrees` — Adoption/Annulment/etc. (court-issued, LCRO only registers)

---

## 5. File Map (What Lives Where)

### New files created during this work

```
api/ra9048/
├── _petition_helpers.php          Phase 2 — shared extract/validate/persist
├── check_petition_number.php      Phase 3 — uniqueness check
├── generate_document.php          Phase 4a — DOCX generator orchestrator
├── list_documents.php             Phase 4a — list generated docs for petition
└── lookup_owner.php               Phase 3 — search existing COLB records

api/
└── serve_ra9048_doc.php           Phase 4a — secure DOCX serve

assets/js/
├── ra9048-petition-form.js        Phase 2 — form interactivity
└── ra9048-verify-and-file.js      Phase 4a — post-save pipeline

assets/css/
└── ra9048-petition-form.css       Phase 2 — form sections styling

includes/
└── DocxTemplateProcessor.php      Phase 1 — DOCX template engine (~150 LOC)

database/migrations/
├── 023_ra9048_workflow_fields.sql  Phase 1 — workflow columns + child tables
└── 024_merge_ra9048_into_iscan_db.sql  Bonus — DB merge cleanup

scripts/
├── build_template_petition_cce.php  Phase 4b — generates petition_cce.docx
├── verify_023_migration.php       Diagnostic — confirms migration applied
└── test_template_files.php        Diagnostic — lists templates + generated files

documents/templates/
├── petition_cce.docx              Phase 4b — clean CCE template (built)
├── RA 9048 (CFN) 2.docx           Original — still used for CFN subtype
├── RA 9048 petition (birth) 2.docx  Original — kept as reference
├── publication(CFN).docx          Original — for Order for Publication
├── cert of posting.docx           User converted from .doc
├── certification.docx             User converted from .doc
└── RA 10172 publication.pptx      Original — needs conversion or deferral

docs/
└── RA9048_MODULE.md               This file
```

### Modified files

```
api/ra9048/
├── petition_save.php              Phase 2 — handles all new fields + child rows
├── petition_update.php            Phase 2 — same
└── records_search.php             Phase 4a — returns petition_subtype, petition_number, status_workflow

includes/
└── config_ra9048.php              Phase 1+bonus — LCRO constants, helpers, $pdo_ra alias

public/ra9048/
├── petition.php                   Phase 2/4a — full rewrite + Verify & File pipeline
└── records.php                    Phase 4a — Files popover, Download/Regenerate buttons

assets/css/
└── ra9048.css                     Phase 4a — popover styles, badge colors, Download hover

database/migrations/
├── 021_ra9048_database.sql        Bonus — header updated to use iscan_db
```

### Files NOT touched (out of scope per user)

- [`public/ra9048/legal_instrument.php`](../public/ra9048/legal_instrument.php) (AUSF/Supplemental/Legitimation form)
- [`public/ra9048/court_decree.php`](../public/ra9048/court_decree.php)
- API endpoints for legal instruments and court decrees
- Any non-RA9048 file (birth/marriage/death/auth/etc.)

---

## 6. Key Decisions & Rationale

### D1: Why no PHPWord?
PHPWord 1.x requires Composer + the `phpoffice/math` dependency (~5 MB of files). The project convention is no Composer, plus offline-NAS deployment via `download_assets.sh`. For our use case (template fill + table-row clone), PHP's built-in `ZipArchive` plus string replacement is enough. Built `DocxTemplateProcessor` as ~150 lines of pure PHP. **No external dependencies.**

### D2: Why one database, not two?
Original design used a separate `iscan_ra9048_db`. Reverted because:
- MySQL doesn't allow cross-database FOREIGN KEY constraints
- Cross-database joins are slower, especially on Synology MariaDB
- Synology Hyper Backup picks DBs individually — easy to forget the second one
- A phpMyAdmin gotcha already bit us once (tables created in wrong DB)
- Single-DB grants/permissions/backups are simpler

`$pdo_ra` aliased to `$pdo` so existing code keeps working.

### D3: Manual petition number entry (not auto-sequence)
Admin types the number; form locks the `CCE-`/`CFN-` prefix based on subtype radio. Stored fully-composed (e.g. `CCE-0130-2025`) with a UNIQUE INDEX. Form validates uniqueness via `check_petition_number.php` on blur.

**Why manual:** matches existing office practice (numbers are pre-printed in a logbook by year).

### D4: Why build templates from scratch instead of editing originals?
User pivoted mid-Phase-4b. Editing the originals required Word work and was error-prone (a placeholder got pasted in the wrong location, breaking the verification section). Building from scratch via PHP:
- Originals stay safe as reference
- Placeholders are pre-wired, no human Find-Replace mistakes
- Subtle accent colors and consistent formatting baked in
- Re-runnable: `php scripts/build_template_petition_cce.php` any time
- User said: "JUST CREATE, I WILL PROVIDE THE HEADER AND FOOTER LATER ON" — so templates are body-only, no header/footer (user will add in Word).

### D5: Block-marker pattern for table-row cloning
Templates contain ONE row with `${corrections_block}` plus other field placeholders (`${item_no}`, `${value_from}`, etc.). The engine:
1. Finds the marker `${corrections_block}`
2. Walks outward to find the enclosing `<w:tr>...</w:tr>`
3. Clones that row once per data entry
4. In each clone: removes the marker, substitutes other field placeholders
5. Replaces the original template row with all clones

Supports the marker appearing **multiple times** in one document (e.g., petition has 2 corrections grids — main + ACTION TAKEN). Each occurrence expands independently with the same data.

### D6: Subtle color treatment (Phase 4b v2)
User asked for "subtle colors" + "more formal" design. Applied:
- **Section headings:** navy `#1F3A8A`
- **Table header rows:** light blue-gray `#E5EAF5` background, navy text
- **Table cell borders:** softened from black to slate `#94A3B8`
- **"For MCR/CRG use only" labels:** muted slate `#64748B`
- **Decorative dividers:** navy double-line between sections

Not a redesign — just tasteful accents on a Times New Roman 11pt body.

---

## 7. Document Generation Pipeline

### End-to-end flow (Verify & File)

```
1. Admin clicks "Verify & File" on petition.php
   ↓
2. CertificateFormHandler POSTs to petition_save.php (or petition_update.php)
   ↓
3. ra9048-verify-and-file.js wraps window.fetch:
   - Detects success response
   - Suppresses default 3-second redirect
   - Shows "Generating documents…" overlay
   ↓
4. POST to api/ra9048/generate_document.php with petition_id + doc_type=all
   ↓
5. Generator:
   - Loads petition + petition_corrections + petition_supporting_docs from $pdo
   - Builds value bag (~50 scalars + 3 row-clone arrays) via ra9048_build_template_values()
   - For each doc_type ∈ [petition, order_publication, public_notice, cert_posting, cert_filing]:
     - Skip if subtype doesn't apply
     - Skip if template extension isn't .docx
     - Resolve template file (template_for_subtype map for petition; static template for others)
     - Instantiate DocxTemplateProcessor(template_path)
     - setValues(scalar_bag)
     - cloneRowAndSetValues('corrections_block', corrections_rows)
     - cloneRowAndSetValues('supporting_block', supporting_rows)
     - cloneRowAndSetValues('public_notice_block', public_notice_rows)
     - saveAs(uploads/ra9048/generated/petition_{id}/{stem}_{id}.docx)
   - Returns JSON { generated: [...], skipped: [...] }
   ↓
6. ra9048-verify-and-file.js shows success panel:
   - List of generated docs with download links
   - "Stay on this page" / "Continue to Records" buttons
   ↓
7. Admin clicks Download → fetches DOCX via api/serve_ra9048_doc.php
```

### Docs popover flow (records page)

```
1. Click "Files" button on a petition row
   ↓
2. Popover opens with "Loading…"
   ↓
3. GET api/ra9048/list_documents.php?petition_id={id}
   ↓
4. Backend globs uploads/ra9048/generated/petition_{id}/*.docx
   ↓
5. Returns array of { doc_type, label, filename, url }
   ↓
6. Popover renders clickable download links
```

### Regenerate flow

```
1. Click "Regenerate" button on a petition row (or in edit toolbar)
   ↓
2. Notiflix.Confirm dialog
   ↓
3. POST api/ra9048/generate_document.php?doc_type=all (overwrites existing files)
   ↓
4. Notiflix.Notify success: "Generated N file(s)."
```

### Download (direct) flow

```
1. Click Download button on a petition row (or in edit toolbar)
   ↓
2. GET api/ra9048/list_documents.php to find the petition .docx URL
   ↓
3. If found: window.location.href = url (browser downloads)
   ↓
4. If NOT found: Confirm dialog "Generate now?" → on yes, runs generate + auto-downloads
```

---

## 8. Template System

### How `DocxTemplateProcessor` works

A `.docx` file is a ZIP archive. The visible text lives in `word/document.xml`. The processor:

1. Copies template to a temp file
2. Reads `word/document.xml`
3. Normalizes split placeholders (Word sometimes splits `${name}` across multiple `<w:r>` runs — the regex collapses them back)
4. `setValue` / `setValues`: simple string replacement of `${field}` tokens
5. `cloneRowAndSetValues`: finds the marker's enclosing `<w:tr>`, clones the row N times, substitutes per-row fields, replaces the original
6. Saves the modified XML back into the ZIP under the same path
7. Outputs the populated DOCX

### Template builder pattern (Phase 4b)

To create a new template, write a one-time PHP script in `scripts/build_template_X.php` that:

1. Builds the `<w:body>` content as a string using helpers:
   - `p($content, $opts)` — paragraph (string or array of run-specs)
   - `run($text, $opts)` — formatted text run
   - `table($rows, $colWidths, $opts)` — table with optional header row
   - `spacer()` — empty paragraph

2. Wraps it in the OOXML envelope:
   ```xml
   <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
   <w:document xmlns:w="...">
     <w:body>
       {body}
       <w:sectPr>...</w:sectPr>
     </w:body>
   </w:document>
   ```

3. Packs into a ZIP with the minimum required parts:
   - `[Content_Types].xml`
   - `_rels/.rels`
   - `word/document.xml`

4. Outputs to `documents/templates/{name}.docx`

The CCE petition builder is the reference: [`scripts/build_template_petition_cce.php`](../scripts/build_template_petition_cce.php).

### Subtle color palette in use

```
Navy / accent       : #1F3A8A
Header bg (table)   : #E5EAF5
Border (table)      : #94A3B8
Muted label         : #64748B
Body text           : default (black)
Page bg             : default (white)
```

---

## 9. Placeholder Reference

The generator passes this value bag to every template. Use these inside `${...}` in template content.

### Office constants (from `LCRO_*` defines)
- `${office_name}`, `${office_municipality}`, `${office_province}`, `${office_address}`, `${office_email}`
- `${mcr_full_name}`, `${mcr_title}`

### Citation (per subtype, via `ra9048_citation()`)
- `${law}` — e.g. "R.A. 9048" or "R.A. 9048 as amended by R.A. 10172"
- `${irr}` — e.g. "Administrative Order No. 1, series of 2012"
- `${mc}` — Memorandum Circular (only for RA 10172)
- `${nature_label}` — "CHANGE OF FIRST NAME" or "CORRECTION OF CLERICAL ERROR"

### Petition identity
- `${petition_number}`, `${petition_subtype}`, `${petition_type}`
- `${date_of_filing}`, `${fee_amount}`
- `${document_type}` (full label like "CERTIFICATE OF LIVE BIRTH"), `${document_type_code}` (COLB/COM/COD)
- `${petition_of}`, `${remarks}`

### Petitioner
- `${petitioner_names}`, `${petitioner_nationality}`, `${petitioner_address}`
- `${petitioner_id_type}`, `${petitioner_id_number}`
- `${is_self_petition_label}`, `${relation_to_owner}`

### Document owner
- `${document_owner_names}`, `${owner_dob}`
- `${owner_birthplace_city}`, `${owner_birthplace_province}`, `${owner_birthplace_country}`
- `${registry_number}`
- `${father_full_name}`, `${mother_full_name}`

### CFN-specific
- `${cfn_ground}` (full label like "The first name is extremely difficult to write or pronounce")
- `${cfn_ground_detail}`

### Notarization & payment
- `${notarized_at}` (full date), `${notarized_day}` (e.g. "15th"), `${notarized_month_year}` (e.g. "JULY, 2025")
- `${receipt_number}`, `${payment_date}`

### Posting
- `${posting_start_date}`, `${posting_end_date}`, `${posting_location}`
- `${posting_cert_issued_at}`

### Publication
- `${order_date}`, `${order_day}`, `${order_month_year}`
- `${publication_date_1}`, `${publication_date_2}`, `${publication_dates_combined}`
- `${publication_newspaper}`, `${publication_place}`
- `${opposition_deadline}`

### Today / issuance
- `${today_date}`, `${today_day}`, `${today_month_year}`
- `${certification_issued_at}` (defaults to today)

### Inline FROM/TO scalars (for templates without a table for corrections)
- `${first_description}`, `${first_value_from}`, `${first_value_to}`, `${first_nature_label}`
  These reflect the FIRST correction row in the petition.

### Block markers (for table-row cloning)
- `${corrections_block}` — clone this row per correction, fields available inside the row:
  - `${item_no}`, `${nature}`, `${nature_label}`, `${description}`, `${value_from}`, `${value_to}`
- `${supporting_block}` — clone per supporting doc:
  - `${item_no}`, `${doc_label}`
- `${public_notice_block}` — exactly 2 rows (CFN summary + CCE summary):
  - `${nature_label}`, `${value_from}`, `${value_to}` (`-.-` placeholder when empty)

---

## 10. Phase 4b — Remaining Templates

### Template 2 — Petition (CFN Form 4.1)

**Reuse Template 1 design.** Differences:
- Title: "PETITION FOR CHANGE OF FIRST NAME IN THE CERTIFICATE OF LIVE BIRTH"
- Add CFN grounds checkboxes: (a) difficult, (b) habitual, (c) ridicule, (d) confusion
- Use `${cfn_ground}` (full sentence) and `${cfn_ground_detail}`
- FROM/TO grid is single row (just first name)
- Same VERIFICATION + ACTION TAKEN + CRG + Payment sections

**To build:** copy [`scripts/build_template_petition_cce.php`](../scripts/build_template_petition_cce.php) → adapt → save as `scripts/build_template_petition_cfn.php` → run → produces `documents/templates/petition_cfn.docx` → update `generate_document.php` `template_for_subtype['CFN']` to point at it.

### Template 3 — Order for Publication (CFN/CCE-RA10172 only)

**Court-style order issued by MCR ordering newspaper publication.**

Body content (verbatim from extracted original):
```
Republic of the Philippines
LOCAL CIVIL REGISTRY OFFICE
Province of ${office_province}
Municipality of ${office_municipality}

IN RE: PETITION FOR THE CHANGE OF FIRST NAME IN THE CERTIFICATE OF LIVE BIRTH
${document_owner_names}
${petitioner_names}
Petitioner    vs    ${petition_number}

The Municipal Civil Registrar
${office_municipality}, ${office_province}

ORDER

A verified petition having been filed by the petitioner, ${petitioner_names}
seeking for the change of first name in the Certificate of Live Birth of
${document_owner_names} from "${first_value_from}" to "${first_value_to}".

Finding said petition to be sufficient in form and substance, the same is
given due course. All interested parties must appear and file their opposition,
if any to show-cause why the petition should not be granted.

Let this Order be published in a newspaper of general circulation pursuant to
Rule 9 paragraph 2 of ${law} at least once a week for two (2) consecutive weeks
at the expense of the petitioner.

So ordered.

Given this ${order_day} day of ${order_month_year} at ${office_municipality}, ${office_province}.

${mcr_full_name}
${mcr_title}
```

### Template 4 — Certificate of Posting

Layout (verbatim):
```
CERTIFICATE OF POSTING

This is to certify that ${petition_number} dated ${date_of_filing} filed by:

[ Table:
  Name of Petitioner    | ${petitioner_names}
  Type/Nature of Petition | ${nature_label}
  Type of Document      | ${document_type}
  Document Owner/s      | ${document_owner_names}
  Registry Number       | ${registry_number}
]

has been posted with the information below:

[ Table:
  PERIOD OF POSTING                          | PLACE OF POSTING
  FROM ${posting_start_date}                 | ${posting_location}
  TO   ${posting_end_date}
]

in compliance to Administrative Order No. 1, series of 2012 and Memorandum
Circular No. 2013-1.

Issued at the LCRO of ${office_municipality}, ${office_province} this ${posting_cert_issued_at}.

${mcr_full_name}
${mcr_title}
```

### Template 5 — Certification of Proof of Filing

Layout (verbatim):
```
CERTIFICATION OF PROOF OF FILING

TO WHOM IT MAY CONCERN:

THIS IS TO CERTIFY THAT ${petitioner_names} of ${petitioner_address}
has filed a petition with the following information:

[ Table:
  Type of Petition         | ${nature_label}
  Petition Number          | ${petition_number}
  Date of Filing           | ${date_of_filing}
  Receipt Number           | ${receipt_number}
  Date of Payment          | ${payment_date}
  Amount Paid              | ${fee_amount}
  Type of Document         | ${document_type}
  Registry Number          | ${registry_number}
  Name of Document Owner   | ${document_owner_names}
]

CHANGE OF FIRST NAME AND ERROR/S SOUGHT TO BE CORRECTED:

[ Corrections grid (uses ${corrections_block}):
  FROM                  | TO
  ${value_from}         | ${value_to}    ← cloned per correction; description in left col
]

This certification is issued upon the request of ${petitioner_names}.

Issued this ${today_day} day of ${today_month_year} at ${office_municipality}, ${office_province}.

${mcr_full_name}
${mcr_title}
```

### Template 6 — RA 10172 Public Notice (deferred)

Originally a `.pptx` slide. Either:
- (a) Recreate as `.docx` using the same builder pattern → bigger task because of the 2-row CFN+CCE grid and the parents' names line.
- (b) Skip auto-generation; admin prepares manually using the existing `.pptx` as reference.

User decision pending.

---

## 11. Common Issues & Fixes

### Issue: `${placeholder}` shows as literal text in the output

**Cause:** Word split the placeholder across multiple `<w:r>` runs (happens when the user typed it character-by-character or applied formatting mid-typing).

**Fix:** The engine has a `normalizeSplitPlaceholders()` regex that handles most cases. If a placeholder still leaks through, retype it fresh in plain text in the template, or rebuild the template via the `build_template_*.php` script.

### Issue: Corrections grid shows `${item_no}` instead of `1`

**Cause:** Either the row wasn't enclosed correctly, or `${item_no}` ended up outside the cloned row.

**Fix:** Ensure `${corrections_block}` is in the SAME row as `${item_no}`. The engine clones the entire row, so all placeholders within that row get substituted per data entry.

### Issue: Two corrections grids in one document — only the first fills

**FIXED in current code.** The engine's `cloneRowAndSetValues` now uses a `while` loop that processes each occurrence of `${corrections_block}` independently. (Was a `strpos` + single replacement before.)

### Issue: `Table 'iscan_ra9048_db.petitions' doesn't exist`

**Cause:** Database merge migration 024 not applied yet (the tables now live in `iscan_db`).

**Fix:** Run [`scripts/verify_023_migration.php`](../scripts/verify_023_migration.php) — it will tell you which database has the tables and what columns are present.

### Issue: `SQLSTATE[HY093]: Invalid parameter number` (lookup_owner)

**FIXED.** Was caused by reusing the same named PDO placeholder in multiple positions of one query while `ATTR_EMULATE_PREPARES = false`. Each placeholder is now unique (`:reg_q` for WHERE, `:reg_q_ord` for ORDER BY; per-column placeholders for token matches).

### Issue: Files popover empty / Download does nothing

**Cause:** URLs in `list_documents.php` were `../../api/...` (relative), and resolved differently depending on which page invoked them.

**FIXED.** URLs now use `BASE_URL` constant (e.g. `/iscan/`) for absolute paths. Works regardless of caller location.

### Issue: 3-second auto-redirect after save kicks in too fast

**Cause:** `CertificateFormHandler` (shared across all forms) does `setTimeout(redirect, 3000)` unconditionally on success.

**FIXED.** [`ra9048-verify-and-file.js`](../assets/js/ra9048-verify-and-file.js) wraps `window.fetch` and patches both `Notiflix.Report.success` and `setTimeout` for one tick to suppress the default redirect, then runs the document-generation flow + shows its own success panel with a "Continue to Records" button.

---

## 12. Resume Playbook

If you're picking this up after a context loss, here's how to get oriented quickly:

### Step 1: Check phase status
Read [Section 2](#2-current-status-phase-tracker) above. As of last update, **Template 1 (CCE petition) v2 is built** and the user was about to test it visually.

### Step 2: Verify the system works end-to-end

```bash
# 1. Check migrations are applied
php c:/xampp/htdocs/iscan/scripts/verify_023_migration.php

# 2. Check templates exist
php c:/xampp/htdocs/iscan/scripts/test_template_files.php

# 3. Visit in browser:
http://localhost/iscan/public/ra9048/records.php
```

If verifier says "all expected new columns present" and templates exist, system is ready.

### Step 3: Find where you left off

Check the most recent generated docs:
```
c:/xampp/htdocs/iscan/uploads/ra9048/generated/
```

If `petition_X.docx` exists for any petition_id, the pipeline works.

Check the documentation TODO state — last tracked items were typically the most recent template build.

### Step 4: Resume work

Most likely next task: **Build Template 2 (CFN petition)**. Steps:

1. Copy [`scripts/build_template_petition_cce.php`](../scripts/build_template_petition_cce.php) to `scripts/build_template_petition_cfn.php`
2. Modify the title section:
   - "PETITION FOR CHANGE OF FIRST NAME IN THE CERTIFICATE OF LIVE BIRTH"
3. Replace the corrections grid with the CFN-specific FROM/TO (just one row for first name)
4. Add CFN grounds section with checkboxes (a)/(b)/(c)/(d) using `${cfn_ground}`
5. Update output filename: `petition_cfn.docx`
6. Run: `php scripts/build_template_petition_cfn.php`
7. Update [`api/ra9048/generate_document.php`](../api/ra9048/generate_document.php) — change `template_for_subtype['CFN']` from `'RA 9048 (CFN) 2.docx'` to `'petition_cfn.docx'`
8. Test by creating a CFN petition and clicking Verify & File

### Step 5: Continue with Templates 3, 4, 5 in any order

Each follows the same pattern — write a `build_template_*.php`, run, update generator's filename mapping, test.

---

## 13. Reference URLs (Manual Testing)

Replace `localhost` with your dev hostname if different.

### Pages
- **Module landing:** http://localhost/iscan/public/ra9048/index.php
- **New petition:** http://localhost/iscan/public/ra9048/petition.php
- **Records list (petition tab):** http://localhost/iscan/public/ra9048/records.php?type=petition

### Diagnostic scripts (CLI or browser)
- **Migration verifier:** http://localhost/iscan/scripts/verify_023_migration.php
- **Template/generated-doc inspector:** http://localhost/iscan/scripts/test_template_files.php
- **Template builders:**
  - `php scripts/build_template_petition_cce.php` (already used)
  - `php scripts/build_template_petition_cfn.php` (TODO)
  - `php scripts/build_template_order_publication.php` (TODO)
  - `php scripts/build_template_cert_posting.php` (TODO)
  - `php scripts/build_template_cert_filing.php` (TODO)

### Direct file URLs (for testing the serve endpoint)
- **Petition #1 download:** http://localhost/iscan/api/serve_ra9048_doc.php?file=ra9048/generated/petition_1/petition_1.docx
- **List petition #1 docs:** http://localhost/iscan/api/ra9048/list_documents.php?petition_id=1

### API endpoints (require POST + CSRF)
- `POST /iscan/api/ra9048/petition_save.php` — create new
- `POST /iscan/api/ra9048/petition_update.php` — update existing
- `POST /iscan/api/ra9048/petition_delete.php` — soft delete
- `POST /iscan/api/ra9048/generate_document.php` — generate DOCX bundle
- `GET  /iscan/api/ra9048/lookup_owner.php?q=...` — search COLB records
- `GET  /iscan/api/ra9048/check_petition_number.php?number=...` — uniqueness check
- `GET  /iscan/api/ra9048/list_documents.php?petition_id=...` — list generated files
- `GET  /iscan/api/ra9048/records_search.php?type=petition&page=1` — paginated search

---

## Appendix A: Activation/Deployment Notes

### XAMPP (local dev)

System uses MariaDB via PHP 8.2's bundled `mysqli`/`pdo_mysql`. No additional configuration. The `iscan_db` database needs to have all migrations 001 through 024 applied.

### Synology NAS production

After the database merge, deployment is simpler:
- One database (`iscan_db`)
- One MariaDB grant
- One Hyper Backup task
- `.env.synology` doesn't need a `RA9048_DB_NAME` line (was never added)

Templates in `documents/templates/` need to be copied as part of the deploy. They're plain DOCX files, ~3-4 KB each.

Generated DOCX files end up under `uploads/ra9048/generated/petition_{id}/`. This folder grows over time. A periodic cleanup task (delete older than 1 year) is a future improvement, not currently implemented.

### File permissions

- `documents/templates/` — readable by Apache user
- `uploads/ra9048/generated/` — writable by Apache user (created automatically on first generation)

---

## Appendix B: User Preferences (auto-memory)

Captured during this session:
- Wants system to work on BOTH XAMPP/localhost AND Synology NAS (backward compatible)
- Prefers concise responses with markdown formatting
- Prefers lighter, less compacted form UI
- "JUST CREATE, I WILL PROVIDE THE HEADER AND FOOTER LATER ON" (Phase 4b decision — body-only templates)
- "DON'T MODIFY THE LEGAL TEXT, KEEP AS IT IS, BUT DESIGN IT MORE FORMAL" (template aesthetic guidance)
- Wants subtle colors (not heavy/decorative) in templates

These are also persisted in `c:/Users/MDRRMO/.claude/projects/c--xampp-htdocs-iscan/memory/`.

---

**End of documentation. Last updated: 2026-04-28.**
