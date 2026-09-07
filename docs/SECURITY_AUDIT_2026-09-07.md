# CRDMS / iScan security audit — 7 September 2026

**Assessment: do not describe the current deployment as secure or ready for unrestricted internet access.** Critical vulnerabilities exist in the reviewed code, and sensitive-file exposure was confirmed on the NAS website. This review establishes vulnerabilities and exposure; it does not establish whether someone previously accessed or altered records.

Reviewed local revision: `46f29db6c3a41be242ea6da7a73dc0645d42e6a1`.

## Remediation amendment — 7 September 2026

The working tree now contains remediation for the application-level findings:
shared session rotation/revalidation, fail-closed rate limiting, CSRF coverage,
permission checks on certificate/workflow/batch/OCR actions, private upload/PDF
authorization, path canonicalization, export formula and sort protection,
legacy dashboard access gates, stored-XSS context fixes, setup/diagnostic
lockdown, scanner origin/token controls, PDF.js scripting disabled for every
document load, safer autosave scoping, RA 9048 permission gates, and corrected
advanced-search/analytics coverage for death records. PHP and
JavaScript syntax checks pass after these changes. The original observations
below describe the pre-remediation revision and remain useful evidence for the
deployment checklist.

The current CDN PDF.js URL remains on the legacy global-script line and should
be replaced with a maintained legacy build during the next frontend asset
deployment; the `isEvalSupported: false` and `enableScripting: false` guards
are the immediate control. The NAS still requires deployment, secret rotation, database migration review,
dependency installation, and an authenticated end-to-end verification. Until
those steps are completed, the online system must not be represented as fully
remediated.

No application fixes, database changes, account changes, deployment changes, or malicious requests against the NAS were performed during the original audit. Local behavior probes used synthetic data and a database double. OCR shell commands were intercepted, never executed. Do not deploy the temporary audit fixtures.

## Scope and evidence

- Inventoried **337 project files**, excluding Git object internals and temporary review artifacts.
- Automated text inspection covered **293 files / 4,065,606 bytes**, including PHP, JavaScript, Python, SQL, deployment files, configuration files, and documentation. Pattern matches were reviewed as candidates, not automatically classified as vulnerabilities.
- Checked syntax of **all 157 PHP files**: 156 passed; the setup wizard failed.
- Checked syntax of **all 18 JavaScript files**: all passed. Syntax success does not establish security.
- Built a guard inventory for **115 PHP files under `api/`, `admin/`, and `public/`**, including shared templates and wrappers. Followed their authentication, authorization, CSRF, query, file, and rendering paths.
- Inspected shared security/session helpers, all certificate save/update guards, record and PDF services, exports, archive/trash, user/device administration, notes/calendar variants, workflow/batch/OCR, RA 9048 controls, scanner service, deployment protections, and dependency declarations.
- Performed limited unauthenticated HEAD/GET checks against the known NAS address. No password guessing, production SQL-injection requests, uploaded attack PDFs, record edits, or stress testing were performed. Record contents and secret values are omitted from this report.
- Inventoried the 21 PDFs, images, and Office files. Their binary contents were not subjected to malware analysis. Installed NAS packages, operating-system configuration, database grants, storage encryption, backup restoration, and complete access logs were not available for verification.

Evidence artifacts remain locally under `tmp/security-audit/`, which is Git-ignored and has a deny-access `.htaccess`. The local browser test used a temporary blank browser tab, not an authenticated production session.

## Findings requiring action before a security assurance

| ID | Severity | Finding | Evidence level |
| --- | --- | --- | --- |
| C1 | Critical | Unauthenticated OCR input reaches operating-system shell commands | Code and isolated command-interception test; endpoint reachable on NAS |
| C2 | Critical | Public Git repository objects and configuration files expose sensitive deployment material | Confirmed on NAS; known committed database-dump object returned HTTP 200 |
| H1 | High | Five record-related pages do not require login or record permissions | Code; public NAS routes reachable without login |
| H2 | High | SQL injection through the export sort parameter | Code and isolated query-capture test |
| H3 | High | Stored JavaScript injection in dashboard notes | Actual rendering function tested in an isolated browser |
| H4 | High | Certificate writes and workflow actions omit required authorization | Code; eight certificate APIs passed a permissionless Viewer into business logic in local probes |
| H5 | High | General PDF endpoint bypasses backup/module permissions and record status | Synthetic backup returned to a Viewer with no permissions |
| H6 | High | Missing CSRF checks on multiple state-changing endpoints | Code; shared helper also accepts PUT without a token |
| H7 | High | Existing sessions survive account/role changes; login does not rotate session ID | Code and isolated session test |
| H8 | High | PDF.js version includes a published script-execution vulnerability | Declared version and Mozilla advisory; NAS loaded asset version not independently inventoried |

### C1 — Unauthenticated OCR command injection

Evidence: `api/ocr_process.php:10`, `:46`, `:57`; `includes/TesseractOCR.php:182`, `:183`, `:184`, `:196`, `:206`, `:222`.

The OCR API loads configuration and the OCR class without requiring authentication, a certificate permission, CSRF validation, or rate limiting. It JSON-decodes `selected_pages` but only checks that the result is an array. Its elements are not restricted to bounded positive integers.

The OCR class interpolates each page value into image/output filenames. Those filenames are then inserted into shell commands inside double quotes. Formatting the separate page-number arguments with `%d` does not sanitize the filenames. On a Linux NAS, shell substitution inside a double-quoted argument can execute attacker-controlled commands with the web-server account's privileges.

An isolated test called the actual page-extraction method in a namespace with `exec()` replaced by a recording function. A synthetic nonnumeric page marker containing shell syntax reached both generated conversion commands unchanged. **Two commands were intercepted; zero commands executed.** The NAS endpoint responded to an unauthenticated HEAD request with HTTP 500, rather than an authentication rejection. No exploit was sent to the NAS. Actual production execution also depends on its deployed code and whether PHP process execution is enabled.

Impact could include theft or modification of any files/database material accessible to the web process, installation of a backdoor, and denial of service. The 10 MB upload cap does not prevent command injection or bound the requested page count.

Fix: immediately restrict or disable this endpoint at the server/edge until patched. Enforce login, appropriate permission, POST and CSRF; validate every page as an integer within the actual document range; bound pages, runtime, memory and concurrency. Prefer process execution with separate arguments and no shell, or correctly escape every shell argument. Use server-generated filenames independent of page input. Run conversion under a restricted account/container. Test invalid page strings, excessive arrays, wrong types, and valid PDFs before restoring access.

### C2 — Public repository and configuration exposure

Evidence: `.htaccess:8`, `:18`; `apache_synology.conf:37`; tracked `.env.production`, `.env.synology`, and `public/iscan_db (1).sql`.

The deny pattern covers files ending in `.env`, but misses `.env.production` and `.env.synology`. There is no rule denying `.git/`. Disabling directory listing does not prevent retrieval of a known filename.

NAS checks confirmed:

| Resource | Result | Verification |
| --- | --- | --- |
| Configuration file `.env.production` | HTTP 200 | Decoded response is an environment file; values not recorded |
| Configuration file `.env.synology` | HTTP 200 | Decoded response is an environment file; values not recorded |
| Git HEAD | HTTP 200 | Decoded response matches Git HEAD format |
| Git index and config | HTTP 200 | HEAD requests only |
| Known Git object for the committed database dump | HTTP 200, Content-Length 16038 | HEAD only; production dump contents not downloaded |
| Direct SQL-dump URL | HTTP 403 | This protection does not protect its Git object |
| Active `.env` path | HTTP 403 | Positive control |
| `includes/config.php` | HTTP 403 | Positive control |
| Deliberately nonexistent ordinary file | HTTP 404 | Distinguishes actual file responses from a catch-all success page |

The local committed SQL dump is 77,605 bytes and contains inserted civil registry, user, activity, notes, and configuration rows, including a bcrypt password-hash marker. Whether every row represents a real person was not determined; it is not a schema-only migration. Both exposed configuration responses had a nonempty sensitive setting. Whether those values are active NAS credentials was not tested.

Fix: immediately deny all `.git` paths and all `.env*` files at both origin and edge. Deploy an application release without repository metadata, database dumps, local logs, setup tools, or development artifacts. Remove the dump from deployable files; coordinate any repository-history cleanup after preserving evidence. Rotate any active credentials found in exposed files or history, invalidate relevant sessions, and review retained origin/edge logs. Do not assume a 403 on the `.sql` URL means the data is protected.

### H1 — Record-related pages bypass login

Evidence: `public/advanced_search.php:7`, `public/analytics_dashboard.php:7`, `public/workflow_dashboard.php:7`, `public/batch_upload.php:7`, `public/pdf_comparison_viewer.php:8`.

These files start a session or include configuration but do not require login or record permissions. The comparison viewer retrieves a record by ID with `SELECT *`; advanced search queries names, registry numbers, dates, and locations. Its queries do not exclude Deleted/Archived records. Workflow and batch pages also contain testing defaults for an administrator identity.

All five routes returned HTTP 200 without authentication on the NAS. GET checks identified the expected advanced-search, analytics, batch, and missing-ID comparison responses. Workflow returned HTTP 200 but its content was not classified as the expected interface by the probe. No record IDs were enumerated and no search for real people was performed.

Fix: apply the common authenticated session bootstrap before any query, enforce the relevant record permissions, remove testing identities, and restrict each query to permitted types/statuses. Gate or remove unfinished legacy modules. An unlinked page remains accessible by URL.

### H2 — Export sort parameter is injectable SQL

Evidence: `public/export.php:195`, `:199`; `includes/functions.php:10`.

The request's `sort_by` value is passed through `sanitize_input()`, which only trims strings, then inserted directly into `ORDER BY`. The use of a prepared statement does not protect SQL fragments already concatenated into the query. Other record lists have sort allowlists; this export endpoint does not.

The isolated probe supplied a harmless SQL expression as the sort parameter. The actual export code passed that expression unchanged into the database double's query. No live database query or time-delay payload was used. The endpoint requires login and record-view permission, so this is an authenticated injection path. Impact depends on database privileges and query behavior; subquery-based inference and expensive expressions are possible without stacked statements.

Fix: map a small set of permitted sort keys to literal columns for each record type. Reject or default unknown keys. Keep direction restricted to `ASC`/`DESC`. Retest each export format and its filters.

### H3 — Stored script injection in dashboard notes

Evidence: `admin/dashboard.php:3660`, `:4430`, `:4461`, `:4468`; related event sinks at `:3623` and `:4351`; `api/notes.php:85` and the corresponding `admin/api/notes.php` create/update handlers.

The notes APIs accept a user-supplied note type without an enum allowlist. Dashboard rendering inserts that type into `innerHTML` without HTML escaping. Note/event titles are also placed inside quoted JavaScript in inline `onclick` attributes. The `escapeHtml()` helper escapes HTML text, but does not make a value safe inside a JavaScript string or HTML attribute.

The actual `renderAllNotes()` and `escapeHtml()` functions were extracted unchanged and executed in an isolated blank browser tab. A synthetic note type executed a harmless local flag assignment when rendered. A synthetic note title executed another flag assignment when its Delete button was clicked. No production note was created and no data was read or sent anywhere.

Impact: a low-privilege account able to create a note can cause code to run with the browser privileges of another user viewing it, potentially an administrator. HttpOnly cookies do not prevent injected scripts from making authenticated requests. Ordinary apostrophes in titles can also break the inline handler.

Fix: render user content with text nodes; validate note/event types on the server; replace inline handlers with event listeners and separately stored IDs. Inspect similar patterns in user administration and record actions. Add a restrictive CSP after eliminating incompatible inline execution; CSP supplements the escaping fix.

### H4 — Authorization is missing from write APIs

Evidence: the save/update pairs for `certificate_of_live_birth`, `certificate_of_marriage`, `certificate_of_death`, and `application_for_marriage_license` under `api/`, near lines 15–18; `api/workflow_transition.php:24`, `:39`; `api/batch_create.php:20`.

The four certificate forms check create/edit permissions, but their eight API endpoints only require authentication and a CSRF token. UI restrictions do not prevent an authenticated Viewer from submitting directly to the API. Local probes with a Viewer and an empty permission set reached field validation or record lookup in all eight endpoints, instead of receiving a permission denial. No valid record was written.

Workflow transition checks a session and the allowed state transition but not whether the caller is authorized to verify, approve, reject, or archive that certificate. Batch creation likewise lacks create-permission checks. The parallel notes/calendar APIs allow any authenticated user to update/delete other users' entries without an ownership or role check.

RA 9048 pages, exports, document generation, and many write/read APIs also rely on login without the module permissions defined in migrations. This is currently mitigated on the checked NAS by its disabled feature flag: the RA records API returned HTTP 503. Fix permissions before enabling it. Record-link details omit view-permission checks; record linking checks the primary type's link permission without independently authorizing both records/types.

Fix: enforce operation-specific permissions inside every endpoint, including resource ownership or both records' permissions where appropriate. Define approval roles explicitly. Test Anonymous, Viewer, Encoder, restricted users, and Admin with valid input so failures cannot be hidden by validation errors.

### H5 — General PDF service bypasses resource authorization

Evidence: `api/serve_pdf.php:58`, `:86`, `:119`, `:125`, `:131`, `:162`; compare admin enforcement in `api/pdf_backup_serve.php:18`.

The general PDF endpoint derives type from the first path segment and only checks permission if the type exists in its map. An unrecognized directory, including `backups`, skips that check entirely. It also does not require a matching accessible database record before serving bytes: a missing hash or an excluded Deleted row does not stop delivery. Legacy files are assigned a type heuristically.

An isolated test used the unchanged endpoint and a synthetic `uploads/backups/audit.pdf`. A Viewer with no permissions received the PDF with HTTP 200. This bypasses the separate backup endpoint's Admin check. The test did not use or enumerate real document paths.

Fix: authorize a record ID/type through a strict allowlist, resolve its stored file path, and verify permitted status before reading. Reject unknown directories and unmatched records. Keep backup access exclusively behind Admin authorization. Apply the same rules to RA documents, enforce a directory-separator boundary in canonical-path checks, and use an appropriate confidential-document cache policy.

### H6 — CSRF coverage is incomplete

Evidence: `includes/security.php:44`; `api/users_save.php`, `api/users_update.php`, `api/users_delete.php`, `api/archive_toggle.php`, `api/archive_bulk.php`, `api/workflow_transition.php`, `api/batch_create.php`, both `api/` and `admin/api/` notes/calendar implementations, and `admin/error_log_viewer.php:45`.

These state-changing handlers do not enforce CSRF tokens. The shared helper only validates POST, so calling it alone would not protect PUT/DELETE. A local test confirmed that PUT without a token returns through the helper. Some administrative scan/reconciliation handlers also omit a strict method check.

SameSite=Lax reduces many cross-site requests, but is not a substitute for explicit CSRF checks and does not cover all same-site/subdomain scenarios. JSON endpoints do not consistently require a JSON content type, and several vulnerable handlers accept ordinary form POSTs.

Fix: require and validate a token for every unsafe method; support the application's JSON/header transport; reject unsupported methods and content types. Include log clearing, archives, user changes, workflow and duplicate API variants in tests. Keep GET/HEAD free of state changes.

### H7 — Sessions do not follow account revocation or login boundaries

Evidence: `includes/auth.php:13`, `:48`, `:72`, `:364`; `includes/session_config.php:15`; `api/users_update.php:100`, `api/users_delete.php:70`.

`isLoggedIn()` trusts a session user ID. Role/active-account status is not revalidated against the current user row, and Admin is accepted directly from the cached session role. Deactivating a user, changing their password, or demoting their role does not invalidate existing sessions. Permission cache refresh compares only the number of permissions, so replacing one permission with another can leave revoked access cached. Device approval is checked at login rather than being bound to every authenticated session.

`setUserSession()` does not regenerate the PHP session ID or CSRF token. The comment saying rotation happens at login/logout is not backed by a `session_regenerate_id()` call anywhere in the reviewed application. The isolated login test preserved both values. This creates a session-fixation risk if an attacker can establish a known session in the victim's browser; that prerequisite was not tested on the NAS.

Fix: regenerate the ID and CSRF token after successful authentication; enable strict session-ID handling; invalidate sessions on password, role, account, and relevant device changes. Use a per-user session version or revalidate the account on requests. Refresh permissions by version/content, not count. Route APIs through the same expiry/maintenance checks.

### H8 — Vulnerable PDF.js dependency

Evidence: `includes/asset_urls.php:36`; `assets/js/record-preview-modal.js:758`, `assets/js/double-reg-comparison-modal.js:693`, `assets/js/ocr-page-selector.js:97`, `assets/js/ocr-processor.js:161`, and `public/pdf_comparison_viewer.php:653`.

The configured CDN version is PDF.js **3.11.174**. Mozilla lists versions through 4.1.392 as affected by **CVE-2024-4367**, allowing a malicious PDF to execute JavaScript when `isEvalSupported` retains its default true value. The reviewed loading calls do not set it false. Many affected pages do not invoke the shared CSP helper. [Mozilla advisory](https://github.com/mozilla/pdf.js/security/advisories/GHSA-wgrm-67xf-hhpq).

Fix: upgrade to a maintained release patched for applicable current advisories and test the viewer/OCR APIs and worker together. Mozilla identifies 4.2.67 as the fix for this specific CVE; that historical minimum is not a claim that it is the best current release. Setting `isEvalSupported: false` at every loading site is the advisory's interim mitigation for this CVE. A PDF signature and SHA-256 hash do not establish that its contents are safe to render. The NAS's exact served local/vendor asset bytes were not inventoried.

## Additional security and reliability findings

1. **Medium — Password and rate-limit policy mismatch.** `api/users_save.php:49` and `api/users_update.php:66` accept six-character passwords and bypass the stronger configured/shared policy. `includes/security.php:132` permits login attempts if rate-limit storage fails. Login limits are keyed to username plus origin-visible IP, not an independent account-wide lockout. Verify the trusted proxy/IP configuration and enforce a consistent password and throttling policy. Whether default/weak passwords are currently used was not tested.

2. **Medium — Security-event logging arguments are reversed.** `logSecurityEvent()` expects `(event, severity, details, user_id)` at `includes/security.php:153`. Device handlers, device-login branches, PDF integrity/restore, and reconciliation often pass `(event, severity, user_id, details)`. A local recording-database test confirmed an array reaches the `user_id` parameter and the numeric user ID replaces details. Real database logging can fail or misattribute events. `api/workflow_transition.php:139` also calls `log_activity()` with the old argument order. Correct and test audit records before claiming complete accountability.

3. **Medium — Browser drafts retain civil registry data after logout.** `assets/js/certificate-form-handler.js:971` and `:1045` store nearly every form field, including hidden CSRF fields, in localStorage under a certificate-type-only key. `public/logout.php` does not clear it. Another account on the same browser can be offered the previous account's draft; restored stale CSRF tokens can also break saving. Use user/session-scoped drafts, expiry and logout cleanup, and never persist/restore CSRF fields.

4. **Medium, deployment-dependent — Scanner service trusts arbitrary browser origins.** `scanner_service/scanner_service.py:22` enables global default CORS; the scan endpoint at `:123` has no pairing secret, origin restriction, or authenticated user authorization. It binds to localhost, which limits direct network reachability, but requests from browser pages/local processes may still reach it depending on browser private-network restrictions. Resolution is unbounded. Use explicit approved origins plus a per-installation pairing secret or authenticated local bridge, bounded inputs and timeouts. [Flask-CORS default behavior](https://flask-cors.readthedocs.io/en/latest/api.html).

5. **Medium — Maintenance/setup tools remain in deployable source.** `setup_death_table.php` runs SQL without authentication; `download_assets.php` writes vendor files without authentication; `test_tesseract.php` exposes process diagnostics. Root rules do not block them. Some document-generation helper scripts also lack a CLI guard. These potentially state-changing production URLs were deliberately not requested. Restrict tools to CLI/admin-controlled installation and exclude them from the release. The setup wizard currently has a parse error, so its latent installation-access problem must not be treated as an active working exploit; secure its access before fixing/enabling it.

6. **Medium — Spreadsheet formulas are not neutralized in CSV exports.** `public/export.php:280–287` writes record values straight through `fputcsv()`. CSV quoting does not stop spreadsheet interpretation of leading formula characters. Treat exported text as literal text, including whitespace/control-character edge cases. Test with harmless formulas; no spreadsheet execution was attempted.

7. **Medium — Backup deletion can discard the database entry when disk deletion fails.** `api/pdf_backup_bulk_delete.php:49–66` adds IDs to the deletion list even if `unlink()` fails. This can leave orphaned files without usable management metadata. Only remove rows after successful deletion or an explicitly reconciled already-missing file; report and retain failures.

8. **Dependency inventory is incomplete at runtime.** The scanner requirements pin Flask 3.0.0, flask-cors 4.0.0, Pillow 10.1.0, img2pdf 0.5.1, reportlab 4.0.7, and python-sane 2.9.1. Transitive versions are not locked in this repository and installed NAS versions were not available. Pillow 10.2.0 fixed CVE-2023-50447; no call to the affected `ImageMath.eval()` API was found in this scanner, so a reachable exploit from that advisory is not claimed. Refresh and audit the installed dependency set. [Pillow release/security notes](https://pillow.readthedocs.io/en/stable/releasenotes/10.2.0.html).

9. **Confirmed presentation/operational bugs.** `setup_wizard.php:498` contains a malformed closing PHP tag, causing the parse failure reported at line 507. Advanced search reads type/workflow filters without applying them and only searches birth/marriage. Analytics totals only birth/marriage. The OCR endpoint caps files at 10 MB despite the broader 25 MB upload configuration. The NAS returned HTTP 500 for a nonexistent path under `uploads/`; its local protection file uses legacy `Deny from all`. Investigate origin logs and Apache compatibility; this response alone does not prove the underlying cause or a file leak. Local vendor files are absent, so this checkout cannot substantiate a complete offline claim. NAS vendor availability is unverified.

## Controls that are present, with limits

Password hashing/verification, prepared parameters in many queries, CSRF checks on certificate writes, Admin enforcement on certificate deletion and several administration pages, filename/type/MIME checks, PDF signatures/hashes, session cookie flags, and record permission checks in the main search/view flows are present. NAS login responded over HTTPS with CSP, and the dashboard redirected an anonymous request to login. The active `.env`, direct SQL URL, and includes path were denied in the checks.

These controls do not compensate for an unguarded endpoint, injected SQL fragment, shell command, or public Git object. The RA feature flag currently blocks its routes but is not a replacement for permissions. The standard local `.env` is a development configuration with device lock disabled and an empty database password; those local values do not establish the active NAS settings.

This review does not verify NAS encryption at rest, off-device backups, restoration time, ransomware resilience, administrator MFA, operating-system patch level, database least privilege, or historical compromise. File hashes detect some changes relative to a stored reference; they neither encrypt records nor prevent a sufficiently privileged attacker from replacing both a file and its reference hash.

## Immediate response and retest order

1. Restrict public access to approved personnel while preserving current logs and evidence. Apply protection at the NAS/origin or Cloudflare access layer; the application's maintenance setting alone does not cover the unguarded legacy/OCR routes.
2. Block repository/configuration exposure and the unsafe OCR endpoint. Verify sensitive paths now return 403/404, including Git objects, without breaking approved login and assets.
3. Determine which exposed secrets/data are real/current; rotate affected credentials and invalidate sessions. Review origin/edge access logs for repository/configuration access and suspicious OCR/export requests. Preserve evidence before purging history or logs.
4. Fix SQL injection, stored script injection, authorization, PDF access, CSRF and session invalidation. Patch PDF.js and review installed dependencies.
5. Retest on an isolated copy with synthetic records and real database schema, then verify deployed controls. Test every role against every operation with valid input, anonymous access to all old routes, unknown PDF directories/deleted records, permission revocation, login rotation, and safe invalid-input handling.
6. Verify backups by restoring an isolated copy; confirm restricted database/web-server privileges and origin/edge configuration. Rehearse Thursday's presentation with synthetic records if the production security work is not complete.

**Suggested presentation answer after remediation and retesting:** “The system uses controlled accounts, permissions and protected document access, and we review and test those controls. No system can be promised immune to attack. We maintain backups, updates, monitoring and a response process.” Only describe those operational controls as established after they have been verified.

**Current truthful answer:** "Application remediation is present in the reviewed checkout. The NAS deployment, secret rotation, and authenticated retest are still pending, so we are not claiming full security assurance yet."
