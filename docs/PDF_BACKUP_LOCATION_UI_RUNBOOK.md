# System-Based PDF Backup Location UI

Open `admin/pdf_protection_setup.php` as an administrator. The page uses the same admin shell, hero, cards, buttons, Lucide icons, and responsive layout as the existing PDF Inventory and PDF Versions pages.

## Backup flow

1. Select an approved storage root or detected local drive.
2. Enter a single safe folder name, such as `iSCAN-PDF-Backup_2026-09-23_Batch-001`.
3. Validate the location.
4. Run a dry run first, then start the actual incremental backup after reviewing the job preview.
5. Open the PDF Protection Jobs monitor to watch progress and download the report.

## Recovery flow

1. Switch to Recovery.
2. Select the storage root and an existing backup folder.
3. Run a dry-run mapping preview.
4. Review exact matches, unmatched files, corrupt sources, and needs-review items before actual recovery.

The optional `PDF_BACKUP_ALLOWED_ROOTS` environment setting can restrict the selector to an explicit semicolon- or comma-separated allowlist, for example:

```text
PDF_BACKUP_ALLOWED_ROOTS=E:\\;F:\\iSCAN-Offline
```

When the setting is not present on Windows, the UI shows locally visible drive roots. Folder names are validated against traversal, separators, control characters, Windows reserved names, and unsafe overwrites. The server creates backup folders and launches the resumable worker in the background; no command-line action is required from the end user.
