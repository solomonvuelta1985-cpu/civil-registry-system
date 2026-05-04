# RA 9048/10172 Module — Feature Toggle

The RA 9048/10172 transactions module (Petition, Legal Instrument, Court Decree)
is gated behind a single feature flag so it can be paused without deleting
code, data, or templates.

**Current default: DISABLED.** The sidebar entry is hidden, all RA 9048 pages
return 403, and all RA 9048 APIs return 503.

---

## How the toggle works

A single PHP constant controls everything:

```php
define('RA9048_FEATURE_ENABLED', env('RA9048_FEATURE_ENABLED', false));
```

Defined in [includes/config.php](../includes/config.php). The default is
`false`, so the module stays paused even if `.env` does not mention it.

The flag is enforced in three places:

| Layer | File | Behavior when disabled |
|---|---|---|
| Sidebar UI | [includes/sidebar_nav.php](../includes/sidebar_nav.php) | Entire RA 9048 menu section hidden |
| Pages + APIs | [includes/config_ra9048.php](../includes/config_ra9048.php) | 403 (HTML) or 503 (JSON) before any handler runs |
| Document server | [api/serve_ra9048_doc.php](../api/serve_ra9048_doc.php) | 503 plaintext |

Because every RA 9048 entry point includes `config_ra9048.php`, the guard there
covers all 6 public pages and 14 API endpoints with one check.

---

## Files affected by the toggle

### Sidebar (1 file)
- `includes/sidebar_nav.php` — RA 9048/10172 section wrapped in
  `<?php if (defined('RA9048_FEATURE_ENABLED') && RA9048_FEATURE_ENABLED): ?>`

### Public pages (6 files, all guarded via `config_ra9048.php`)
- `public/ra9048/index.php` — landing page (3 transaction cards)
- `public/ra9048/petition.php` — petition form (CCE/CFN)
- `public/ra9048/legal_instrument.php` — AUSF / Supplemental / Legitimation
- `public/ra9048/court_decree.php` — court decree registration
- `public/ra9048/records.php` — records listing
- `public/ra9048/export.php` — CSV/XLS export

### API endpoints (14 files, all guarded via `config_ra9048.php`)
- `api/ra9048/petition_save.php`
- `api/ra9048/petition_update.php`
- `api/ra9048/petition_delete.php`
- `api/ra9048/check_petition_number.php`
- `api/ra9048/legal_instrument_save.php`
- `api/ra9048/legal_instrument_update.php`
- `api/ra9048/legal_instrument_delete.php`
- `api/ra9048/court_decree_save.php`
- `api/ra9048/court_decree_update.php`
- `api/ra9048/court_decree_delete.php`
- `api/ra9048/records_search.php`
- `api/ra9048/lookup_owner.php`
- `api/ra9048/list_documents.php`
- `api/ra9048/generate_document.php`

(`api/ra9048/_petition_helpers.php` is a function library with no entry point —
no guard needed; its callers are guarded.)

### Document server (1 file, guarded directly)
- `api/serve_ra9048_doc.php` — includes `config.php` (not `config_ra9048.php`)
  so it has its own inline guard.

### Untouched (intentional)
- `database/migrations/021_*.sql`, `022_*.sql`, `023_*.sql`, `024_*.sql` — schema
  remains; no data is destroyed.
- `documents/templates/*.docx|.pptx` — DOCX templates remain in place.
- `assets/css/ra9048*.css`, `assets/js/ra9048*.js` — frontend assets remain
  available but are loaded only by guarded pages, so disabled pages never
  reach the client.
- `includes/DocxTemplateProcessor.php` — generic helper, not RA 9048-specific.
- `includes/config_ra9048.php` itself — the guard is added inside it; the
  helper functions (`ra9048_citation`, `ra9048_requires_publication`) and the
  `$pdo_ra` alias still load only when the flag is on.

---

## How to re-enable

1. Open the appropriate environment file:
   - **Local / XAMPP**: `.env` in project root (create from `.env.example` if missing)
   - **Synology NAS**: `.env` on the server (template in `.env.synology`)
2. Add or set:
   ```ini
   RA9048_FEATURE_ENABLED=true
   ```
3. No restart needed for PHP-FPM/Apache — the next request picks it up.
   (Synology Web Station may cache opcache; flush if stale.)
4. Verify:
   - Sidebar shows the RA 9048/10172 section.
   - Visiting `/iscan/public/ra9048/index.php` loads the landing page (not 403).
   - `curl /iscan/api/ra9048/records_search.php` returns a normal JSON response
     (not the `RA9048_FEATURE_DISABLED` payload).

## How to pause again

Set `RA9048_FEATURE_ENABLED=false` in `.env` (or remove the line — `false` is
the default). All gates re-engage immediately.

---

## Disable response shapes

Pages (HTML, status 403):
```html
<h2>RA 9048/10172 module is temporarily disabled</h2>
<p>This feature has been paused by the administrator. Please check back later.</p>
```

APIs (JSON, status 503):
```json
{
  "success": false,
  "message": "RA 9048/10172 module is temporarily disabled.",
  "code": "RA9048_FEATURE_DISABLED"
}
```

Document server (plaintext, status 503):
```
RA 9048/10172 module is temporarily disabled.
```

Frontend code that calls these APIs should detect HTTP 503 + the
`RA9048_FEATURE_DISABLED` code and surface a friendly notice rather than a
generic error toast.

---

## Why a feature flag instead of deleting code?

- The DB schema (4 migrations, 5 tables) and DOCX templates represent
  significant work and would be expensive to reconstruct.
- A flag makes the pause reversible by editing one line in `.env` — no code
  changes, no redeploy, no merge conflicts when the module is reactivated.
- Production data (existing petitions/legal instruments/court decrees) stays
  intact while the module is hidden.

## Future development notes

- If a new RA 9048 entry point is added, include `includes/config_ra9048.php`
  at the top — the guard is automatic.
- If a new RA 9048 entry point cannot include `config_ra9048.php` (e.g. it
  needs a different bootstrap), copy the inline guard pattern from
  `api/serve_ra9048_doc.php`.
- Module documentation: [RA9048_MODULE.md](RA9048_MODULE.md).
