# NAS security remediation handoff

The code changes in this working tree protect the application only after the
same revision is installed on the Synology site. Use a maintenance window and
keep a verified database/file backup before replacing the web files.

1. Build a clean release from Git. Do not copy `.env`, `.env.*`, `public/*.sql`,
   `tmp/`, `logs/`, `.git/`, or uploaded files. The NAS environment file must
   be created directly on the NAS with permissions readable by PHP only.
2. Rotate every credential that appeared in an old environment file or dump:
   database password, SMTP/API keys, tunnel/access tokens, scanner pairing
   token, and any administrator password. Removing a file does not revoke a
   previously exposed secret.
3. Configure production values: `APP_ENV=production`, the public `BASE_URL`,
   `TRUSTED_PROXY_IPS` containing only the local reverse-proxy address,
   `REQUIRE_PASSWORD_COMPLEXITY=true`, `ENABLE_RATE_LIMITING=true`, and
   `SETUP_WIZARD_ENABLED=false`. Keep RA 9048 disabled until its permissions
   and document storage are reviewed.
4. Run the existing migration set in numeric order, including the archive and
   marriage-license permissions (`009_add_archive_permissions.sql`,
   `017_add_marriage_license_permissions.sql`), partial-date/schema catch-up
   migrations (`014`–`019`), and the RA 9048 permission/workflow migrations
   (`022`–`024`) if that module will be enabled. Review the SQL output; never
   run migrations against a production database dump copied into the public
   web root.
5. Set Apache `AllowOverride All` (or apply the rules in
   `apache_synology.conf`). Confirm that `.env.production`, `.git/HEAD`, SQL
   files, and `/uploads/...` return 403, while the login page returns normally.
6. Install the pinned scanner requirements from
   `scanner_service/requirements.txt` in an isolated environment. Set
   `ISCAN_SCANNER_TOKEN` and `ISCAN_ALLOWED_ORIGINS` out-of-band. Leave scanner
   simulation disabled in production.
   Replace the legacy CDN PDF.js files with a maintained compatible legacy
   build during the frontend deployment, and keep the `isEvalSupported: false`
   and `enableScripting: false` options at every document load. Test the PDF.js
   worker and viewer together after the replacement.
7. Log in as an Admin and verify: login rotates the session cookie; a Viewer
   cannot create/update records, transition workflow, use OCR, export, or read
   another record's PDF; missing/invalid CSRF tokens return 403; inactive users
   and changed roles are rejected on their next request.
8. Verify backups and restore a test PDF through the authorized endpoint. Check
   that a missing or mismatched hash fails closed and that a failed file delete
   remains listed for review.

The online address and the local checkout are separate systems. A clean local
test cannot prove that the NAS has received these changes, rotated its secrets,
or restricted its database and backup permissions.
