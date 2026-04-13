# iScan — Civil Registry Records Management System

## Full System Overview

---

## Table of Contents

1. [System Introduction](#1-system-introduction)
2. [Technology Stack](#2-technology-stack)
3. [System Architecture](#3-system-architecture)
4. [Certificate Types](#4-certificate-types)
5. [Public Pages](#5-public-pages)
6. [Admin Pages](#6-admin-pages)
7. [API Endpoints](#7-api-endpoints)
8. [Database Schema](#8-database-schema)
9. [Authentication & Authorization](#9-authentication--authorization)
10. [Security Features](#10-security-features)
11. [OCR Integration](#11-ocr-integration)
12. [Scanner Service](#12-scanner-service)
13. [Workflow Engine](#13-workflow-engine)
14. [PDF Management](#14-pdf-management)
15. [Search System](#15-search-system)
16. [Analytics & Reporting](#16-analytics--reporting)
17. [Batch Processing](#17-batch-processing)
18. [Calendar & Notes](#18-calendar--notes)
19. [Deployment Targets](#19-deployment-targets)
20. [File Structure](#20-file-structure)

---

## 1. System Introduction

**iScan** is a comprehensive Civil Registry Records Management System designed for digitizing, managing, and securing civil registry documents. The system handles four types of civil registry certificates — Birth, Marriage, Death, and Marriage License Applications — with full lifecycle management including data entry, OCR-assisted extraction, workflow review, PDF integrity verification, and long-term archival.

The system is built for the Municipal Civil Registrar's Office (MCRO) and supports multi-user access with role-based permissions, ensuring data integrity, audit compliance, and operational efficiency.

---

## 2. Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 7.4+ / 8.2 |
| **Database** | MySQL / MariaDB 10 |
| **Web Server** | Apache 2.4 (XAMPP / Synology Web Station) |
| **Frontend** | Vanilla JavaScript (no frameworks) |
| **Styling** | Custom CSS with CSS variables (no Bootstrap/Tailwind) |
| **Icons** | Lucide SVG icons, Font Awesome 6.4 |
| **Charts** | Chart.js 4.4 |
| **PDF Rendering** | PDF.js 3.11 |
| **OCR (Server)** | Tesseract OCR (binary, cross-platform) |
| **OCR (Browser)** | Tesseract.js v4 (client-side fallback) |
| **Notifications** | Notiflix 3.2.6 |
| **Scanner Service** | Python Flask (port 18622) |
| **Scanner Hardware** | Epson DS-530 II (via SANE) |
| **Deployment** | Synology NAS DS925+ with Cloudflare Tunnel |
| **Dependencies** | Zero Composer dependencies |

---

## 3. System Architecture

```
                    +---------------------------+
                    |     Cloudflare Tunnel      |
                    |  (iscan.cdrms.online)      |
                    +-------------+-------------+
                                  |
                    +-------------v-------------+
                    |     Apache 2.4 Server      |
                    |  (.htaccess security rules) |
                    +-------------+-------------+
                                  |
          +-----------+-----------+-----------+
          |           |           |           |
    +-----v----+ +---v-----+ +--v------+ +--v-----------+
    | Public/   | | Admin/  | | API/    | | Scanner      |
    | Pages     | | Pages   | | REST    | | Service      |
    | (19 PHP)  | | (9 PHP) | | (35+)   | | (Flask:18622)|
    +-----+----+ +---+-----+ +--+------+ +--+-----------+
          |           |           |           |
    +-----v-----------v-----------v-----------+
    |            includes/                     |
    | config.php | auth.php | security.php     |
    | functions.php | TesseractOCR.php         |
    +---------------------+--------------------+
                          |
              +-----------v-----------+
              |   MySQL / MariaDB     |
              |   iscan_db            |
              |   (15+ tables)        |
              +-----------+-----------+
                          |
              +-----------v-----------+
              |   uploads/            |
              |   birth/{year}/       |
              |   marriage/{year}/    |
              |   death/{year}/       |
              |   marriage_license/   |
              |   backups/            |
              +-----------------------+
```

### Core Includes

| File | Purpose |
|------|---------|
| `includes/config.php` | Central configuration, loads .env, defines constants, DB connection |
| `includes/env_loader.php` | Parses .env files, provides `env()` and `isDevelopment()` |
| `includes/auth.php` | Authentication helpers, permission checking, role management |
| `includes/security.php` | CSRF tokens, rate limiting, security event logging |
| `includes/security_headers.php` | HTTP security headers (CSP, HSTS, X-Frame-Options) |
| `includes/session_config.php` | Session initialization, timeout, regeneration |
| `includes/device_auth.php` | Device fingerprint registration and verification |
| `includes/functions.php` | Utility functions (sanitization, file ops, date formatting, logging) |
| `includes/asset_urls.php` | CDN/offline asset URL switching |
| `includes/TesseractOCR.php` | Server-side OCR processing class |
| `includes/sidebar_nav.php` | Sidebar navigation component |
| `includes/top_navbar.php` | Top navigation bar component |
| `includes/mobile_header.php` | Mobile-responsive header |
| `includes/form_alerts.php` | Alert/notification templates |
| `includes/form_buttons.php` | Reusable button components |

---

## 4. Certificate Types

The system manages four civil registry certificate types:

### 4.1 Certificate of Live Birth
- Child information (name, sex, date/place of birth, legitimacy, birth type, birth order)
- Mother information (name, citizenship)
- Father information (name, citizenship)
- Marriage of parents (date, place)
- Barangay and time of birth
- Registry number and date of registration

### 4.2 Certificate of Marriage
- Husband details (name, DOB, citizenship, birthplace, residence)
- Wife details (name, DOB, citizenship, birthplace, residence)
- Marriage details (date, place, nature of marriage)
- Parent information for both parties
- Registry number and date of registration

### 4.3 Certificate of Death
- Deceased details (name, DOB, sex, citizenship, occupation)
- Death information (date, place, cause)
- Parent information (father, mother)
- Spouse/children information
- Registry number and date of registration

### 4.4 Application for Marriage License
- Groom details (name, DOB, citizenship, birthplace, residence)
- Bride details (name, DOB, citizenship, birthplace, residence)
- Application date and registry number

Each certificate type supports:
- PDF document attachment with SHA-256 integrity hashing
- Three-state status management (Active / Archived / Deleted)
- Full audit trail (created_by, created_at, updated_at)
- Workflow state tracking
- OCR text extraction and storage

---

## 5. Public Pages

| Page | File | Description |
|------|------|-------------|
| **Login** | `public/login.php` | Authentication with CSRF protection, rate limiting, device lock support |
| **Birth Certificate Form** | `public/certificate_of_live_birth.php` | Data entry form for birth certificates with OCR integration |
| **Marriage Certificate Form** | `public/certificate_of_marriage.php` | Data entry form for marriage certificates |
| **Death Certificate Form** | `public/certificate_of_death.php` | Data entry form for death certificates |
| **Marriage License Form** | `public/application_for_marriage_license.php` | Data entry form for marriage license applications |
| **Birth Records** | `public/birth_records.php` | Record viewer for birth certificates |
| **Marriage Records** | `public/marriage_records.php` | Record viewer for marriage certificates |
| **Death Records** | `public/death_records.php` | Record viewer for death certificates |
| **Marriage License Records** | `public/marriage_license_records.php` | Record viewer for marriage license applications |
| **Records Viewer** | `public/records_viewer.php` | Unified template for viewing, searching, filtering, editing records |
| **Trash** | `public/trash.php` | View/restore/permanently delete soft-deleted records (Admin only) |
| **Advanced Search** | `public/advanced_search.php` | Full-text search across all certificate types |
| **Workflow Dashboard** | `public/workflow_dashboard.php` | Workflow lifecycle management with state transitions |
| **Analytics Dashboard** | `public/analytics_dashboard.php` | System-wide statistics, trends, and performance metrics |
| **Batch Upload** | `public/batch_upload.php` | Bulk processing interface for historical record digitization |
| **PDF Comparison Viewer** | `public/pdf_comparison_viewer.php` | Side-by-side form data vs PDF comparison for verification |
| **Device Blocked** | `public/device_blocked.php` | Error page for unregistered devices |
| **403 Forbidden** | `public/403.php` | Access denied error page |
| **Logout** | `public/logout.php` | Session termination handler |

---

## 6. Admin Pages

| Page | File | Description |
|------|------|-------------|
| **Dashboard** | `admin/dashboard.php` | Statistics overview, charts, monthly trends, recent activity |
| **Reports** | `admin/reports.php` | Comprehensive analytics with date/type filters, demographics |
| **Users** | `admin/users.php` | User CRUD with role assignment (Admin/Encoder/Viewer) |
| **Devices** | `admin/devices.php` | Device registration management for device-lock feature |
| **Archives** | `admin/archives.php` | Manage archived records, unarchive back to Active |
| **Security Logs** | `admin/security_logs.php` | Security event monitoring (severity, event type, IP tracking) |
| **Error Log Viewer** | `admin/error_log_viewer.php` | PHP error log viewer for debugging |
| **PDF Integrity Report** | `admin/pdf_integrity_report.php` | Run integrity scans, identify corrupt/missing PDFs |
| **PDF Backup Manager** | `admin/pdf_backup_manager.php` | Manage backup storage, verify integrity, restore from backups |

---

## 7. API Endpoints

### Certificate CRUD (12 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/certificate_of_live_birth_save.php` | POST | Create birth certificate with PDF upload and hash |
| `api/certificate_of_live_birth_update.php` | POST | Update birth certificate with version control |
| `api/certificate_of_live_birth_delete.php` | POST | Soft-delete birth certificate |
| `api/certificate_of_marriage_save.php` | POST | Create marriage certificate |
| `api/certificate_of_marriage_update.php` | POST | Update marriage certificate |
| `api/certificate_of_marriage_delete.php` | POST | Soft-delete marriage certificate |
| `api/certificate_of_death_save.php` | POST | Create death certificate |
| `api/certificate_of_death_update.php` | POST | Update death certificate |
| `api/certificate_of_death_delete.php` | POST | Soft-delete death certificate |
| `api/application_for_marriage_license_save.php` | POST | Create marriage license application |
| `api/application_for_marriage_license_update.php` | POST | Update marriage license application |
| `api/application_for_marriage_license_delete.php` | POST | Soft-delete marriage license application |

### Search & Retrieval (2 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/records_search.php` | GET | Live search with pagination, two-pass strict + fuzzy matching |
| `api/record_details.php` | GET | Fetch complete record details for modal preview |

### PDF Management (7 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/serve_pdf.php` | GET | Secure PDF serving with permission validation and audit logging |
| `api/pdf_integrity_scan.php` | POST | Full integrity scan on all PDFs (Admin) |
| `api/pdf_restore.php` | POST | Restore PDF from backup storage (Admin) |
| `api/pdf_backup_verify.php` | POST | Verify backup file integrity (Admin) |
| `api/pdf_backup_cleanup.php` | POST | Remove old/orphaned backups (Admin) |
| `api/pdf_hash_backfill.php` | POST | Compute SHA-256 hashes for legacy records (Admin) |
| `api/ocr_process.php` | POST | Server-side Tesseract OCR processing |

### Archive & Trash (3 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/archive_toggle.php` | POST | Archive/unarchive single record |
| `api/archive_bulk.php` | POST | Bulk archive multiple records |
| `api/trash_restore.php` | POST | Restore soft-deleted records from trash (Admin) |

### User Management (5 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/users_save.php` | POST | Create new user account (Admin) |
| `api/users_update.php` | POST | Update user details/role/status (Admin) |
| `api/users_delete.php` | POST | Delete user account (Admin) |
| `api/users_get.php` | GET | Fetch user details |
| `api/users_list.php` | GET | List all users with pagination |

### Workflow (1 endpoint)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/workflow_transition.php` | POST | Transition certificate between workflow states |

### Device Management (2 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/device_save.php` | POST | Register new trusted device (Admin) |
| `api/device_delete.php` | POST | Revoke device registration (Admin) |

### Calendar & Notes (2 endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/calendar_events.php` | GET/POST/PUT/DELETE | Full CRUD for calendar events |
| `api/notes.php` | GET/POST/PUT/DELETE | Full CRUD for system notes |

### Batch Operations (1 endpoint)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/batch_create.php` | POST | Initialize batch upload operations |

**Total: 35+ API endpoints**, all with CSRF validation, permission checking, and activity logging.

---

## 8. Database Schema

### Core Certificate Tables

| Table | Description | Key Fields |
|-------|-------------|------------|
| `certificate_of_live_birth` | Birth certificate records | registry_no, child name, parents, DOB, place, sex, legitimacy, birth type |
| `certificate_of_marriage` | Marriage certificate records | registry_no, husband/wife details, marriage date/place/nature |
| `certificate_of_death` | Death certificate records | registry_no, deceased details, death date/place/cause, parents |
| `application_for_marriage_license` | Marriage license applications | registry_no, groom/bride details, application date |

All certificate tables include: `pdf_filename`, `pdf_filepath`, `pdf_hash`, `status` (Active/Archived/Deleted), `created_by`, `created_at`, `updated_at`.

### Supporting Tables

| Table | Description |
|-------|-------------|
| `users` | System users (username, password hash, full_name, email, role, status, last_login) |
| `activity_logs` | Audit trail (user_id, action, details, certificate_type, ip_address, user_agent) |
| `security_logs` | Security events (event_type, severity, user_id, details, ip_address) |
| `rate_limits` | Rate limiting tracking (identifier, ip_address, timestamp) |
| `registered_devices` | Device lock registry (fingerprint_hash, device_name, status, last_seen) |
| `permissions` | Permission definitions (name, description, module) |
| `role_permissions` | Role-to-permission mapping |
| `pdf_attachments` | PDF versioning with OCR data (hash, version, ocr_text, confidence, processing_status) |
| `workflow_states` | Workflow state tracking (current_state, quality_score, verified/approved/rejected metadata) |
| `workflow_transitions` | State change audit trail (from_state, to_state, transition_type, notes) |
| `certificate_versions` | Version history for amendments (JSON snapshot, amendment_type) |
| `batch_uploads` | Batch processing tracking (batch_name, type, total_files, auto_ocr) |
| `calendar_events` | Event calendar (title, type, date, priority, soft-delete) |
| `system_notes` | System notes (title, type, content, is_pinned, soft-delete) |
| `ocr_cache` | OCR result caching (file_hash, ocr_text, structured_data, processing_time) |

### Database Migrations (13 files in `database/migrations/`)

| Migration | Purpose |
|-----------|---------|
| 001 | Add supporting tables (pdf_attachments, workflow_states, transitions, versions) |
| 002 | Add OCR cache table, workflow/versioning/OCR enhancements |
| 003 | Calendar and notes system |
| 004 | Add citizenship fields to birth certificates |
| 005 | Add barangay and time of birth |
| 006 | Registered devices table |
| 007 | PDF hash column for integrity |
| 008 | Unique registry number constraint |
| 009 | Archive permissions |
| 010 | Remove record delete permissions |
| 011 | Make dates of birth nullable |
| 012 | Add sex field to death certificates |
| 013 | PDF hash index for performance |

---

## 9. Authentication & Authorization

### Role-Based Access Control (RBAC)

| Role | Capabilities |
|------|-------------|
| **Admin** | Full system access — user management, security logs, archives, trash, device management, PDF management, all CRUD operations |
| **Encoder** | Create, edit, and view records; archive individual records (with permission); no access to trash, security logs, or user management |
| **Viewer** | Read-only access to records; no create, edit, delete, or archive capabilities |

### Granular Permissions

**Per certificate type:**
- `{type}_create` — Create new records
- `{type}_view` — View records
- `{type}_edit` — Edit existing records
- `{type}_archive` — Archive/unarchive records

**System permissions:**
- `users_view`, `users_create`, `users_edit`, `users_delete`
- Delete permissions restricted to Admin role only

### Authentication Flow

1. User submits credentials on login page
2. Rate limiting check (max 5 attempts per 5 minutes)
3. Device fingerprint verification (if device lock enabled)
4. Password verification with bcrypt
5. Session creation with user_id, role, permissions cached
6. Activity logged with IP address
7. Session regenerated every 30 minutes
8. Auto-timeout after configurable duration (default: 1 hour)

### Key Functions (`includes/auth.php`)

- `isLoggedIn()`, `getUserRole()`, `getUserId()`, `getUserFullName()`
- `hasPermission()`, `hasAnyPermission()`, `hasAllPermissions()`
- `isAdmin()`, `isEncoder()`, `isViewer()`
- `requireAuth()`, `requirePermission()`, `requireAdmin()`, `requireAdminApi()`
- `authenticateUser()`, `setUserSession()`, `logoutUser()`

---

## 10. Security Features

### CSRF Protection
- Session-based tokens using `bin2hex(random_bytes(32))`
- Timing-safe comparison via `hash_equals()`
- Support for form fields (`csrfTokenField()`) and AJAX headers (`X-CSRF-Token`)
- Enforced on all POST requests

### Rate Limiting
- IP-based tracking with configurable thresholds
- Default: 5 attempts per 5-minute window
- Account lockout: 15 minutes after max attempts
- Automatic cleanup of expired entries

### Session Security
- `cookie_httponly = 1` — prevents JavaScript access
- `use_only_cookies = 1` — URL rewriting disabled
- `cookie_secure` — HTTPS-only on production
- `cookie_samesite = Strict` — cross-site request prevention
- Regeneration every 30 minutes
- Configurable timeout (default: 3600s)

### HTTP Security Headers
- `Content-Security-Policy` — custom directives for trusted sources
- `X-Frame-Options: SAMEORIGIN` — clickjacking prevention
- `X-Content-Type-Options: nosniff` — MIME sniffing prevention
- `X-XSS-Protection: 1; mode=block` — XSS defense
- `Strict-Transport-Security` — HSTS (1 year max-age)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` — disables camera, microphone, geolocation

### Device Lock Security
- Browser fingerprinting with SHA-256 hashing
- Admin-controlled device registration
- Only registered devices can access the system
- Device activity tracking (last seen IP/timestamp)
- Revocation capability

### Input Validation & Sanitization
- `sanitize_input()` — input trimming (SQL injection prevented by prepared statements)
- `escape_html()` — `htmlspecialchars()` for output context
- MIME type validation for file uploads using `finfo`
- Path traversal protection (blocks `..` and null bytes)
- Field length validation
- Registry number format validation

### Security Logging
- Event types: LOGIN_FAILED, SUSPICIOUS_ACTIVITY, etc.
- Severity levels: LOW, MEDIUM, HIGH, CRITICAL
- IP address and user agent tracking
- Admin-accessible security log viewer with filtering
- Suspicious activity detection (10+ failed logins in 1 hour)

### .htaccess Protection
- Blocks direct access to: `.env`, `.sql`, `.sh`, `.log`, `.md`, `.gitignore`
- Blocks access to: `includes/`, `database/`, `logs/`, `scanner_service/`
- Upload limits: 20MB files, 25MB POST body
- Compression enabled (mod_deflate)
- Browser caching configured

---

## 11. OCR Integration

### Dual-Layer OCR Architecture

**Layer 1: Server-Side Tesseract (Primary)**
- Class: `includes/TesseractOCR.php`
- Auto-detects Tesseract installation path across platforms:
  - Linux: `/usr/bin/tesseract`
  - Synology: `/opt/entware/bin/tesseract`
  - Windows: `C:\Program Files\Tesseract-OCR\tesseract.exe`
- Processes PDFs via page extraction (pdftocairo/ImageMagick) then OCR
- 10-20x faster than browser-based processing
- Caches results by SHA-256 file hash in `ocr_cache` table
- Structured data extraction: child name, sex, DOB, place of birth, birth type, registry number
- API endpoint: `api/ocr_process.php`

**Layer 2: Browser-Side Tesseract.js (Fallback)**
- File: `assets/js/ocr-processor.js`
- Uses Tesseract.js v4 from CDN
- Runs 100% client-side when server OCR unavailable
- Progress callbacks during recognition
- Configurable language and PSM mode

### OCR UI Integration
- File: `assets/js/ocr-form-integration-v2.js`
- Collapsible accordion interface with:
  - Page selector for multi-page PDFs
  - "Process PDF" button
  - Extracted data table with confidence scores
  - Color-coded confidence (high/medium/low)
  - "Apply All" and "Clear" buttons
  - Auto-process on file upload (toggleable)
  - Auto-fill fields with >90% confidence (toggleable)
  - Real-time progress bar

### Supporting OCR Files
- `assets/js/ocr-server-client.js` — attempts server-side first, falls back to browser
- `assets/js/ocr-field-mapper.js` — maps OCR fields to form field IDs, date conversion
- `assets/js/ocr-page-selector.js` — multi-page PDF handling with PDF.js page preview

---

## 12. Scanner Service

### Python Flask Microservice
- File: `scanner_service/scanner_service.py`
- Hardware: Epson DS-530 II document scanner
- Port: 18622
- Uses SANE (Scanner Access Now Easy) library
- CORS enabled for browser integration

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/scanner/status` | GET | Check scanner availability, model, readiness |
| `/scanner/scan` | POST | Scan document — accepts quality, colorMode, resolution; returns PDF |
| `/scanner/test` | GET | Health check with SANE availability |

### Features
- Configurable scan quality (high), color mode (color/BW), resolution (default 300dpi)
- Auto-generated timestamped filenames
- Returns scanned document as PDF attachment
- Simulation mode when SANE unavailable (generates test PDFs)
- Start script for Synology: `scanner_service/start_scanner.sh`

---

## 13. Workflow Engine

### Six-State Lifecycle

```
  draft ──> pending_review ──> verified ──> approved ──> archived
                  |                |
                  v                v
              rejected         rejected
                  |                |
                  v                v
                draft            draft
```

### States

| State | Description |
|-------|-------------|
| `draft` | Initial state, record being prepared |
| `pending_review` | Submitted for review |
| `verified` | Reviewed and data verified |
| `approved` | Approved by authority |
| `rejected` | Rejected with reason (can reopen to draft) |
| `archived` | Final state for long-term storage |

### Transitions

| Transition | From | To |
|------------|------|----|
| `submit` | draft | pending_review |
| `verify` | pending_review | verified |
| `approve` | verified | approved |
| `reject` | pending_review / verified | rejected |
| `archive` | approved | archived |
| `reopen` | rejected | draft |

### Features
- Data quality scoring (0-100%)
- User attribution for each transition
- Rejection notes and approval comments
- Complete audit trail in `workflow_transitions` table
- Transaction-safe state updates
- Workflow dashboard with statistics and charts

---

## 14. PDF Management

### Upload & Storage
- PDFs organized by type and year: `uploads/{type}/{year}/`
- Max file size: 5MB (dev) / 20MB (NAS)
- Only PDF uploads allowed (MIME type validated)
- Unique filename generation
- Path traversal protection

### Integrity System
- SHA-256 hashing on upload (`compute_file_hash()`)
- Magic bytes validation (`%PDF-` header check)
- EOF marker verification
- Duplicate detection via hash comparison (`check_pdf_duplicate()`)
- Full archive integrity scanning via `pdf_integrity_scan.php`
- Status reporting: ok / corrupt / missing / no_hash

### Backup & Restore
- Automatic backup on PDF replacement
- Chain backup system (current file backed up before restore)
- Backup storage in `uploads/backups/`
- Integrity verification before restoration
- Configurable retention policy with cleanup
- Admin tools: PDF Integrity Report, PDF Backup Manager

### Versioning
- `pdf_attachments` table tracks versions
- `is_current_version` flag
- `replaced_by_id` linking
- Complete version history

### Secure Serving
- `api/serve_pdf.php` validates permissions before serving
- Blocks access to non-Active records
- Audit logs each PDF access
- Inline viewing support

---

## 15. Search System

### Two-Pass Search Algorithm (`api/records_search.php`)

**Pass 1 — Strict Search (AND across tokens, OR across fields):**
- Query tokenized into individual terms
- Each token must match at least one searchable field
- Example: "Juan Dela Cruz" → records where ALL of "Juan", "Dela", "Cruz" appear across name fields

**Pass 2 — Fuzzy Search (Pure OR fallback):**
- Only triggered if strict search returns 0 results
- Any token matching any field qualifies the record
- Response indicates fuzzy search was used

### Searchable Fields by Type

| Type | Fields Searched |
|------|----------------|
| Birth | registry_no, child name, father name, mother name, place of birth, barangay |
| Marriage | registry_no, husband name, wife name, place of marriage |
| Death | registry_no, deceased name, father name, mother name, place of death, occupation |

### Search Features
- Pagination (configurable per_page, 1-100, default 10)
- Status filtering (Active only by default, optional include Archived)
- Never includes Deleted records in search results
- Returns search tokens and fuzzy flag in response
- Permission-based access control

### Advanced Search (`public/advanced_search.php`)
- Cross-certificate-type searching
- Date range filtering
- Municipality/location filtering
- Workflow state filtering
- Clickable result cards

---

## 16. Analytics & Reporting

### Admin Dashboard (`admin/dashboard.php`)
- Total records by certificate type
- Monthly trend comparison (this month vs last month)
- Charts for last 6 months (Chart.js)
- Certificate type distribution
- Recent activity feed
- Year-over-year comparison
- PDF integrity issue count (last 30 days)

### Reports Page (`admin/reports.php`)
- Date range and certificate type filters
- Monthly and yearly comparisons
- Gender distribution analysis
- Citizenship breakdown
- Birth type distribution (single/twin/triplet)
- Age demographics
- Top locations and venues
- Marriage nature analysis
- Quality metrics and error rates
- User performance tracking

### Analytics Dashboard (`public/analytics_dashboard.php`)
- System-wide statistics
- Monthly trend charts
- Workflow distribution pie charts
- Top performer tables
- Quality metrics (approval rates, error rates)
- Color-coded visualizations
- Responsive charts

---

## 17. Batch Processing

### Batch Upload Interface (`public/batch_upload.php`)
- Drag-and-drop multi-file upload
- File removal before submission
- Certificate type selection per batch
- Auto-OCR configuration toggle
- Auto-validate toggle
- Real-time upload progress tracking
- Batch naming
- Active batches table with status monitoring

### Batch API (`api/batch_create.php`)
- Initializes batch upload operations
- Creates `batch_uploads` record
- Tracks total files, processing status
- Supports auto_ocr and auto_validate flags

---

## 18. Calendar & Notes

### Calendar Events
- Full CRUD via `api/calendar_events.php`
- Event properties: title, type, date, time, priority (low/medium/high), description
- Status tracking and soft-delete support
- Date range filtering
- Admin calendar view

### System Notes
- Full CRUD via `api/notes.php`
- Note properties: title, type, content, pinned status
- Filtering by status, type, pinned
- Soft-delete with deleted_at timestamp
- Created_by attribution

---

## 19. Deployment Targets

### Primary: Synology NAS DS925+
- **Public URL:** `https://iscan.cdrms.online` (via Cloudflare Tunnel)
- **Local Access:** `192.168.1.12`
- **Database:** MariaDB 10 on localhost
- **Web Server:** Apache 2.4 via Web Station
- **PHP:** 8.2 with custom ini settings
- **Auto-update:** Git pull every 1 hour via Task Scheduler
- **Offline mode:** Uses local vendor assets
- **Setup:** `setup_synology.sh` for one-time configuration

### Secondary: XAMPP Local Development
- **URL:** `http://localhost/iscan`
- **Database:** MySQL on localhost
- **Assets:** CDN mode (online)
- **Debugging:** Full error display enabled

### Supported: cPanel / Traditional Hosting
- Full deployment guide available (`docs/CPANEL_DEPLOYMENT.md`)
- Database migration tools included
- `.htaccess` compatible with shared hosting

### Configuration Files
| File | Purpose |
|------|---------|
| `.env` | Active environment configuration |
| `.env.example` | Development defaults template |
| `.env.synology` | Synology NAS production template |
| `.env.production` | Production secrets |
| `apache_synology.conf` | Synology Apache VirtualHost config |
| `php_synology.ini` | PHP 8.2 settings for Synology |
| `setup_synology.sh` | One-time Synology setup script |
| `download_assets.sh` | Download vendor libraries for offline mode |
| `NAS_DEPLOYMENT_GUIDE.md` | Step-by-step NAS deployment instructions |

---

## 20. File Structure

```
iscan/
├── public/                          # User-facing pages (19 PHP files)
│   ├── login.php                    # Authentication
│   ├── certificate_of_live_birth.php
│   ├── certificate_of_marriage.php
│   ├── certificate_of_death.php
│   ├── application_for_marriage_license.php
│   ├── birth_records.php
│   ├── marriage_records.php
│   ├── death_records.php
│   ├── marriage_license_records.php
│   ├── records_viewer.php           # Unified record viewer template
│   ├── trash.php                    # Soft-deleted records (Admin)
│   ├── advanced_search.php
│   ├── workflow_dashboard.php
│   ├── analytics_dashboard.php
│   ├── batch_upload.php
│   ├── pdf_comparison_viewer.php
│   ├── device_blocked.php
│   ├── 403.php
│   └── logout.php
│
├── admin/                           # Admin pages (9 PHP files)
│   ├── dashboard.php
│   ├── reports.php
│   ├── users.php
│   ├── devices.php
│   ├── archives.php
│   ├── security_logs.php
│   ├── error_log_viewer.php
│   ├── pdf_integrity_report.php
│   ├── pdf_backup_manager.php
│   └── api/                         # Admin API endpoints
│       ├── calendar_events.php
│       └── notes.php
│
├── api/                             # REST API endpoints (35+ PHP files)
│   ├── certificate_of_live_birth_save.php
│   ├── certificate_of_live_birth_update.php
│   ├── certificate_of_live_birth_delete.php
│   ├── certificate_of_marriage_save.php
│   ├── certificate_of_marriage_update.php
│   ├── certificate_of_marriage_delete.php
│   ├── certificate_of_death_save.php
│   ├── certificate_of_death_update.php
│   ├── certificate_of_death_delete.php
│   ├── application_for_marriage_license_save.php
│   ├── application_for_marriage_license_update.php
│   ├── application_for_marriage_license_delete.php
│   ├── records_search.php
│   ├── record_details.php
│   ├── serve_pdf.php
│   ├── ocr_process.php
│   ├── pdf_integrity_scan.php
│   ├── pdf_restore.php
│   ├── pdf_backup_verify.php
│   ├── pdf_backup_cleanup.php
│   ├── pdf_hash_backfill.php
│   ├── archive_toggle.php
│   ├── archive_bulk.php
│   ├── trash_restore.php
│   ├── workflow_transition.php
│   ├── batch_create.php
│   ├── users_save.php
│   ├── users_update.php
│   ├── users_delete.php
│   ├── users_get.php
│   ├── users_list.php
│   ├── device_save.php
│   ├── device_delete.php
│   ├── calendar_events.php
│   └── notes.php
│
├── includes/                        # Core libraries (15 PHP files)
│   ├── config.php
│   ├── env_loader.php
│   ├── auth.php
│   ├── security.php
│   ├── security_headers.php
│   ├── session_config.php
│   ├── device_auth.php
│   ├── functions.php
│   ├── asset_urls.php
│   ├── TesseractOCR.php
│   ├── sidebar_nav.php
│   ├── top_navbar.php
│   ├── mobile_header.php
│   ├── form_alerts.php
│   ├── form_buttons.php
│   ├── preloader.php
│   └── sidebar_scripts.php
│
├── assets/
│   ├── css/                         # Custom stylesheets
│   │   ├── sidebar.css
│   │   ├── certificate-forms-shared.css
│   │   ├── record-preview-modal.css
│   │   └── ocr-page-selector.css
│   ├── js/                          # Client-side JavaScript (13 files)
│   │   ├── certificate-form-handler.js
│   │   ├── certificate-skeleton-loader.js
│   │   ├── record-preview-modal.js
│   │   ├── ocr-processor.js
│   │   ├── ocr-server-client.js
│   │   ├── ocr-form-integration-v2.js
│   │   ├── ocr-field-mapper.js
│   │   ├── ocr-page-selector.js
│   │   ├── ocr-modal.js
│   │   ├── ocr-debug.js
│   │   ├── device-fingerprint.js
│   │   └── notiflix-config.js
│   ├── img/                         # Image assets (logos, GIF illustrations)
│   └── vendor/                      # Offline vendor libraries (gitignored)
│
├── database/
│   ├── migrations/                  # 13 migration files
│   ├── create_marriage_table.sql
│   ├── create_death_table.sql
│   └── application_for_marriage_license.sql
│
├── uploads/                         # PDF storage (organized by type/year)
│   ├── birth/{year}/
│   ├── marriage/{year}/
│   ├── death/{year}/
│   ├── marriage_license/{year}/
│   └── backups/
│
├── scanner_service/                 # Python Flask scanner microservice
│   ├── scanner_service.py
│   ├── start_scanner.sh
│   └── requirements.txt
│
├── logs/                            # PHP error logs
├── docs/                            # 32 documentation files
│
├── database_schema.sql              # Primary database schema
├── setup_wizard.php                 # Installation wizard
├── .env                             # Active configuration
├── .env.example                     # Development template
├── .env.synology                    # Synology production template
├── .htaccess                        # Apache security & config
├── apache_synology.conf             # Synology VirtualHost config
├── php_synology.ini                 # PHP 8.2 settings
├── setup_synology.sh                # One-time Synology setup
├── download_assets.sh               # Offline asset downloader
└── NAS_DEPLOYMENT_GUIDE.md          # Deployment guide
```

---

## Project Statistics

| Metric | Count |
|--------|-------|
| Total PHP Files | ~80+ |
| Total JavaScript Files | 13 |
| Total CSS Files | 4 |
| API Endpoints | 35+ |
| Public Pages | 19 |
| Admin Pages | 9 |
| Database Tables | 15+ |
| Database Migrations | 13 |
| Documentation Files | 32 |
| Vendor Libraries | 6 major |
| Lines of PHP Code | ~31,700+ |

---

*Document generated: April 2026*
*System Version: iScan Civil Registry Records Management System*
