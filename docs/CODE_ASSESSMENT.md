# iScan Project — Honest Code Assessment

> **Date**: 2026-03-17
> **Scope**: Full codebase review of the iScan Civil Registry Records Management System
> **Context**: Vibe-coded PHP/MySQL project assessed for code quality, security, and production readiness

---

## TABLE OF CONTENTS

- [Strengths](#strengths)
- [Weaknesses](#weaknesses)
  - [Critical Security Issues](#critical-security-issues)
  - [Data Integrity Issues](#data-integrity-issues)
  - [Performance Issues](#performance-issues)
  - [Code Quality Issues](#code-quality-issues)
  - [Frontend Issues](#frontend-issues)
- [Vibe Coding Observations](#vibe-coding-observations)
- [Unused Helper Functions](#unused-helper-functions)
- [Priority Fix Table](#priority-fix-table)
- [Score Breakdown](#score-breakdown)
- [Recommendations for Future Development](#recommendations-for-future-development)

---

## STRENGTHS

### 1. Security Foundations Are Solid
- **Prepared statements everywhere** — all SQL uses PDO with named parameters (`:registry_no`, etc.). Zero SQL injection risk. This is the #1 thing most beginners get wrong, and this project got it right.
- **CSRF protection** implemented with `hash_equals()` (timing-safe comparison) in `includes/security.php`
- **Rate limiting** on login to prevent brute force attacks
- **Security event logging** with severity levels (CRITICAL, HIGH, MEDIUM, LOW)
- **Device fingerprint lock** — an advanced security feature most professional apps don't even have (`includes/device_auth.php`)
- **PDF validation** checks magic bytes AND EOF markers — not just file extension (`includes/functions.php`)

### 2. Good Architectural Instincts
- **Separation of concerns** — config, functions, security, auth are properly split into `includes/`
- **API pattern** — form pages (`public/`) are separate from save/update endpoints (`api/`), not spaghetti PHP
- **Environment-based config** — `.env` loader with `isDevelopment()` toggle, not hardcoded credentials
- **PDO configured correctly** — `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false` (the holy trinity of safe PDO setup) in `includes/config.php`
- **Database transactions** — save endpoints use `beginTransaction()` / `commit()` / `rollBack()`
- **File cleanup on failure** — if DB insert fails, uploaded PDF gets deleted

### 3. Good Database Design
- Proper indexes on searchable columns (registry_no, names, dates)
- `utf8mb4` charset (handles emojis and special characters correctly)
- Soft delete via `status` ENUM instead of actually deleting records
- Database views for reporting (`vw_active_certificates`, `vw_certificate_statistics`)
- Migration files for schema changes (`database/migrations/`)

### 4. Well-Structured Frontend JavaScript
- Class-based `CertificateFormHandler` with clean initialization pattern
- Real-time validation on blur with ARIA attributes for accessibility
- Auto-save and before-unload protection to prevent data loss
- Graceful fallback when Notiflix library isn't loaded
- Double-submission prevention

---

## WEAKNESSES

### Critical Security Issues

#### 1. Authentication Missing on API Endpoints
**Severity: CRITICAL**

| File | Auth Status |
|------|-------------|
| `api/certificate_of_death_save.php` | No auth check at all |
| `api/certificate_of_death_update.php` | No auth check at all |
| `api/certificate_of_marriage_save.php` | Auth check **commented out** |
| `api/certificate_of_marriage_update.php` | Auth check **commented out** |
| `api/certificate_of_live_birth_save.php` | No auth check (relies on form page) |
| `api/certificate_of_live_birth_update.php` | No auth check (relies on form page) |

**Impact**: Anyone who knows the API URL can submit fake certificates directly via tools like cURL or Postman — no login required.

**Fix**: Add `requireAuth()` at the top of every API endpoint:
```php
require_once '../includes/auth.php';
requireAuth();
```

#### 2. Admin Dashboard Has No Authentication
**Severity: CRITICAL**

In `admin/dashboard.php`:
```php
// Optional: Check if user is authenticated
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../public/login.php');
//     exit;
// }
```

The admin dashboard — the central hub of the system — can be accessed by anyone navigating to `/admin/dashboard.php`. Helper functions `requireAuth()` and `requireAdmin()` exist in `includes/auth.php` but are not used here.

**Fix**: Replace the commented block with:
```php
requireAdmin();
```

#### 3. No CSRF Protection on Certificate API Endpoints
**Severity: CRITICAL**

The `requireCSRFToken()` function exists in `includes/security.php` but is only enforced on the login form. None of the certificate save/update APIs validate CSRF tokens.

**Impact**: Cross-site request forgery attacks could submit fake certificates on behalf of logged-in users.

**Fix**: Add `requireCSRFToken()` to all POST-handling API endpoints.

---

### Data Integrity Issues

#### 4. `sanitize_input()` Corrupts Data with `htmlspecialchars()` on Input
**Severity: HIGH**

In `includes/functions.php`:
```php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
```

This encodes HTML entities BEFORE saving to the database:
- User types: `O'Brien` → Stored as: `O&#039;Brien`
- User types: `AT&T` → Stored as: `AT&amp;T`

**Double-encoding problem**: When displayed with `htmlspecialchars()` again (as every secure template should), the user sees `O&#039;Brien` or `AT&amp;amp;T`.

In a **civil registry system** where legal names must be accurate, this is a data integrity problem.

**Fix**: Remove `htmlspecialchars()` from `sanitize_input()`. The prepared statements already prevent SQL injection. Apply `htmlspecialchars()` only when **outputting** data to HTML.

```php
function sanitize_input($data) {
    if ($data === null) return null;
    if (is_array($data)) return array_map('sanitize_input', $data);
    return trim($data);
}

// Use this when displaying in HTML
function escape_html($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}
```

#### 5. `registry_no` Has No UNIQUE Constraint
**Severity: HIGH**

In `database_schema.sql`:
```sql
registry_no VARCHAR(100) NULL,
...
INDEX idx_registry_no (registry_no),
```

The column has an INDEX (for search speed) but no UNIQUE constraint. Two records can have the same registry number — a serious data integrity risk for a civil registry system.

**Fix**: Add unique constraint per certificate table:
```sql
ALTER TABLE certificate_of_live_birth ADD UNIQUE KEY uniq_registry_no (registry_no);
ALTER TABLE certificate_of_death ADD UNIQUE KEY uniq_registry_no (registry_no);
ALTER TABLE certificate_of_marriage ADD UNIQUE KEY uniq_registry_no (registry_no);
```

#### 6. Date Parsing Trusts `strtotime()` Blindly
**Severity: HIGH**

```php
$date_of_registration = date('Y-m-d', strtotime($date_of_registration));
```

If someone submits garbage like `"not-a-date"`, `strtotime()` returns `false`, and `date('Y-m-d', false)` returns `"1970-01-01"`. The record silently saves with a wrong date.

A `validate_date()` function exists in `includes/functions.php` but is **never called**.

**Fix**: Validate dates before conversion:
```php
if (!validate_date($date_of_registration, 'Y-m-d')) {
    $errors[] = "Invalid date of registration format.";
}
```

#### 7. No Input Length Validation
**Severity: MEDIUM**

None of the save endpoints validate that input strings fit within database column limits. For example, `child_first_name` is `VARCHAR(100)` but no PHP code checks `strlen() <= 100`. MySQL will silently truncate or throw an error depending on strict mode.

The HTML-encoding issue (point 4) makes this worse — `O'Brien` (7 chars) becomes `O&#039;Brien` (15 chars), doubling the effective length.

---

### Performance Issues

#### 8. Dashboard Fires 40+ SQL Queries Per Page Load
**Severity: HIGH**

`admin/dashboard.php` runs separate queries for every statistic:
- 4 queries for total counts (birth, marriage, death, license)
- 4 queries for this month's counts
- 4 queries for last month's counts
- 24 queries inside the chart loop (6 months × 4 certificate types)
- 4 queries for recent activities
- 3+ queries for security stats

**Total: ~40+ queries per dashboard load.** On a Synology NAS, this will be noticeably slow.

A `vw_certificate_statistics` database view already exists but the dashboard **doesn't use it**.

**Fix**: Combine into 2-3 queries using `SUM(CASE WHEN...)`:
```sql
SELECT
    SUM(CASE WHEN cert_type = 'birth' THEN 1 ELSE 0 END) AS total_births,
    SUM(CASE WHEN cert_type = 'birth' AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS this_month_births,
    ...
FROM (
    SELECT 'birth' AS cert_type, created_at FROM certificate_of_live_birth WHERE status = 'Active'
    UNION ALL
    SELECT 'marriage', created_at FROM certificate_of_marriage WHERE status = 'Active'
    UNION ALL
    ...
) combined;
```

#### 9. `hasPermission()` Hits the Database on Every Call
**Severity: HIGH**

In `includes/auth.php`, every `hasPermission()` call runs:
```sql
SELECT COUNT(*) FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
WHERE rp.role = :role AND p.name = :permission
```

The sidebar alone calls this ~15 times (once per menu item). That's **15 extra queries per page load** just for navigation.

**Fix**: Load all permissions once into `$_SESSION` at login:
```php
// In setUserSession() after login:
$perms = getRolePermissions($user['role']);
$_SESSION['permissions'] = array_column($perms, 'name');

// In hasPermission():
function hasPermission($permission_name) {
    if (!isLoggedIn()) return false;
    if (getUserRole() === 'Admin') return true;
    return in_array($permission_name, $_SESSION['permissions'] ?? []);
}
```

---

### Code Quality Issues

#### 10. Massive Code Duplication Across API Files
**Severity: HIGH**

The 4 save files and 4 update files are **~80% identical**:
- Same structure: sanitize → validate → upload PDF → begin transaction → insert/update → commit → respond
- Same error handling pattern copy-pasted
- Same file upload logic repeated

**The cost**: When you need to change how saves work (e.g., add audit trail), you must update 8 files and hope you don't miss one.

**Fix**: Create a single `CertificateSaveHandler` class:
```php
class CertificateSaveHandler {
    private $pdo;
    private $config;

    public function __construct($pdo, array $config) {
        $this->pdo = $pdo;
        $this->config = $config; // table, fields, validation rules
    }

    public function save(array $postData, array $files): array { ... }
    public function update(int $recordId, array $postData, array $files): array { ... }
}
```

#### 11. Inconsistent Response Patterns
**Severity: MEDIUM**

| File | Response Method |
|------|----------------|
| `certificate_of_live_birth_save.php` | `json_response()` helper |
| `certificate_of_death_save.php` | `json_response()` helper |
| `certificate_of_marriage_save.php` | Raw `echo json_encode()` |

Different files were clearly generated in different AI sessions with different patterns.

**Fix**: Use `json_response()` consistently everywhere.

#### 12. Two Conflicting Activity Log Functions
**Severity: MEDIUM**

| Function | Location | Parameters | Columns Used |
|----------|----------|------------|--------------|
| `log_activity()` | `includes/functions.php` | `$pdo, $action, $details, $user_id` | `user_id, action, details` |
| `logActivity()` | `includes/auth.php` | `$action, $module, $record_id, $details` | `user_id, action, module, record_id, details, ip_address` |

Both write to `activity_logs` but expect different columns. One is likely leaving NULL values or silently failing.

**Fix**: Keep one, delete the other. The `auth.php` version is more complete (includes `module`, `record_id`, `ip_address`).

#### 13. Global `$pdo` Used Everywhere
**Severity: LOW**

`$pdo` is created in `config.php` as a global variable. Functions in `security.php`, `device_auth.php`, and `auth.php` all use `global $pdo`. This makes testing impossible and creates hidden dependencies.

**Fix** (long-term): Pass `$pdo` as a parameter or use a simple service container.

#### 14. Debug Code Left in Production Files
**Severity: MEDIUM**

In `api/certificate_of_live_birth_save.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("POST Data: " . print_r($_POST, true));
error_log("FILES Data: " . print_r($_FILES, true));
```

In `includes/config.php`:
```php
die("Database connection error: " . $e->getMessage());
```

**Impact**: Logs sensitive form data, exposes database error details to users.

**Fix**: Remove all `print_r($_POST)` logging, use the centralized error display toggle from `config.php`.

---

### Frontend Issues

#### 15. Console.log Debugging Left in Production JS
**Severity: LOW**

`assets/js/certificate-form-handler.js` has extensive debug logging:
```js
console.log('=== submitForm called ===');
console.log('addNew:', addNew);
console.log('isSubmitting:', this.isSubmitting);
console.log('✅ Form validation passed');
```

**Fix**: Remove all `console.log` calls, or wrap them in a debug flag:
```js
const DEBUG = false;
if (DEBUG) console.log('...');
```

#### 16. CSS File Growing Unwieldy
**Severity: LOW**

`assets/css/certificate-forms-shared.css` has grown to **1200+ lines** with no build step or minification. Likely contains dead CSS from redesigned features.

**Fix**: Run a CSS audit, split by component if needed.

---

### Other Issues

#### 17. PDF Backup Happens After Transaction Commits
**Severity: MEDIUM**

In `api/certificate_of_live_birth_update.php`, the old PDF backup happens **after** `$pdo->commit()`. If the backup fails (disk full, permission error), the database already points to the new file and the old PDF is lost forever.

**Fix**: Move the file backup before `commit()`, or at minimum, log a warning if backup fails.

#### 18. SQL Pattern Fragility in `records_search.php`
**Severity: LOW** (safe today, fragile for future)

```php
$columns_str = implode(', ', $config['columns']);
$query = "SELECT {$columns_str} FROM {$config['table']}...";
```

Table and column names are interpolated directly into SQL. Values come from a hardcoded config array (safe today), but the pattern is fragile if anyone adds user input to that config in the future.

---

## VIBE CODING OBSERVATIONS

### What the AI Did Well
- Consistent file structure and naming conventions
- Good boilerplate (PDO setup, error handling skeleton, JSON responses)
- Security features were clearly prompted well (CSRF, rate limiting, device lock)
- Clean HTML/CSS separation with shared stylesheets
- Class-based JavaScript with good UX patterns

### Common Vibe Coding Traps Present
1. **Copy-paste multiplication** — AI generates full files rather than reusable abstractions. Result: 8 nearly identical API files instead of 1 reusable handler.
2. **Feature creep without integration** — Helper functions exist but aren't wired up to the code that needs them.
3. **Inconsistency across sessions** — Different files generated in different conversations use different patterns (some use `json_response()`, others use raw `echo json_encode()`).
4. **Commented-out code left behind** — Auth checks commented out, debug logging left in production code.
5. **Each piece works in isolation, but pieces don't talk to each other** — Security is implemented but not enforced everywhere. Validation functions are written but never called.

---

## UNUSED HELPER FUNCTIONS

These functions exist but are **never called** anywhere in the codebase:

| Function | Location | Purpose |
|----------|----------|---------|
| `requireAuth()` | `includes/auth.php` | Enforce login — not used on dashboard or API endpoints |
| `requireAdmin()` | `includes/auth.php` | Enforce admin role — not used on admin pages |
| `validate_date()` | `includes/functions.php` | Validate date format — never called before `strtotime()` |
| `validate_registry_number()` | `includes/functions.php` | Validate registry number — never called in save endpoints |
| `validateInput()` | `includes/security.php` | Type-safe input validation — never called anywhere |
| `requireCSRFToken()` | `includes/security.php` | Enforce CSRF on POST — only used on login, not API saves |
| `vw_certificate_statistics` | `database_schema.sql` | Stats view — dashboard queries manually instead |

---

## PRIORITY FIX TABLE

| # | Priority | Issue | Files Affected | Effort |
|---|----------|-------|----------------|--------|
| 1 | **CRITICAL** | Add auth checks to ALL API endpoints | 8 API files | 30 min |
| 2 | **CRITICAL** | Add auth to admin dashboard | `admin/dashboard.php` | 5 min |
| 3 | **CRITICAL** | Add CSRF validation to API endpoints | 8 API files | 30 min |
| 4 | **HIGH** | Fix `sanitize_input()` — remove `htmlspecialchars` from input | `includes/functions.php` + all templates | 2-3 hours |
| 5 | **HIGH** | Add UNIQUE constraint on `registry_no` | DB migration | 15 min |
| 6 | **HIGH** | Use `validate_date()` before `strtotime()` | 8 API files | 30 min |
| 7 | **HIGH** | Optimize dashboard queries (40+ → 2-3) | `admin/dashboard.php` | 1-2 hours |
| 8 | **HIGH** | Cache permissions in `$_SESSION` | `includes/auth.php` | 30 min |
| 9 | **MEDIUM** | Unify activity logging functions | `functions.php`, `auth.php` | 1 hour |
| 10 | **MEDIUM** | Remove debug `error_log` of POST/FILES data | `api/certificate_of_live_birth_save.php` | 15 min |
| 11 | **MEDIUM** | Use `json_response()` consistently | `api/certificate_of_marriage_save.php` + others | 30 min |
| 12 | **MEDIUM** | Move PDF backup before transaction commit | 4 update API files | 30 min |
| 13 | **MEDIUM** | Add input length validation | 8 API files | 1 hour |
| 14 | **MEDIUM** | Remove console.log from production JS | `assets/js/certificate-form-handler.js` | 15 min |
| 15 | **LOW** | Consolidate 8 API files into reusable handler | `api/` + new `includes/CertificateSaveHandler.php` | 4-6 hours |
| 16 | **LOW** | Replace `global $pdo` with dependency injection | All `includes/` files | 4-6 hours |
| 17 | **LOW** | CSS audit and cleanup | `assets/css/certificate-forms-shared.css` | 1-2 hours |

**Quick wins (under 1 hour total)**: Items 1, 2, 3, 10, 14 — fixes the most critical security issues.

---

## SCORE BREAKDOWN

| Area | Score | Notes |
|------|-------|-------|
| **Security foundations** | 7/10 | Great prepared statements, CSRF, rate limiting. But auth missing on critical endpoints. |
| **Architecture** | 6/10 | Good separation of concerns. Hurt by duplication and inconsistency. |
| **Database design** | 7/10 | Solid schema, indexes, soft deletes. Missing UNIQUE constraints. |
| **Data integrity** | 4/10 | `htmlspecialchars` on input corrupts data. No length validation. Blind `strtotime`. No unique registry_no. |
| **Performance** | 4/10 | 40+ queries per dashboard load. Permission checks hit DB on every call. |
| **Code quality** | 5/10 | Good in isolation, but massive duplication and drift across sessions. |
| **Frontend JS** | 7/10 | Clean class structure, good UX patterns. Debug logs left in. |
| **Production readiness** | 4/10 | Debug logging, commented-out auth, no minification, display_errors in some files. |

**Overall: 5.5/10**

The bones are good. The problems are all fixable. But the deeper you look, the more you see the classic vibe coding pattern: **each feature works in isolation, but the pieces don't talk to each other**. Helper functions exist but aren't used. Security is implemented but not enforced everywhere. Validation functions are written but never called.

---

## RECOMMENDATIONS FOR FUTURE DEVELOPMENT

### 1. Before Every AI Session, Provide Context
Give the AI a brief of existing patterns:
> "We use `json_response()` for API responses, `requireAuth()` for auth checks, `requireCSRFToken()` for CSRF, and `sanitize_input()` for input cleaning. Use these existing functions — don't create new ones."

### 2. Use a CLAUDE.md File
Create a `CLAUDE.md` at the project root that documents:
- Existing helper functions and when to use them
- Coding conventions (response format, auth pattern, error handling)
- Things NOT to do (don't use `echo json_encode`, don't hardcode `error_reporting`)

### 3. Review AI Output for Integration
After each AI session, check:
- Does it use existing helpers or create new ones?
- Does it follow the same auth/CSRF/response pattern as other files?
- Does it duplicate code that already exists elsewhere?

### 4. Fix Critical Issues First
Start with items 1-3 from the priority table (auth + CSRF). These take ~1 hour total and close the biggest security gaps.

### 5. Consider a Pre-Deployment Checklist
Before deploying to Synology NAS:
- [ ] All API endpoints have `requireAuth()`
- [ ] All POST endpoints have `requireCSRFToken()`
- [ ] All admin pages have `requireAdmin()`
- [ ] No `display_errors = 1` in production
- [ ] No `console.log` in JavaScript
- [ ] No `print_r($_POST)` in error logs
- [ ] `.env.production` is NOT committed to git
