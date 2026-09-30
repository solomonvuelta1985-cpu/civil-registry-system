# PDF Incremental Backup Runbook

Use `scripts/pdf_incremental_backup.php` for routine external-drive backups.
It scans the current PDF source and the destination manifest, compares SHA-256
hashes, and copies only new or changed content. An identical hash already
present elsewhere on the destination drive is also treated as already backed
up, even when the relative filename differs.

## Dry run

```text
php scripts/pdf_incremental_backup.php --source=<uploads-root> --destination=<external-backup-root> --dry-run
```

## Actual backup

```text
php scripts/pdf_incremental_backup.php --source=<uploads-root> --destination=<external-backup-root>
```

## What the job does

- Validates PDF structure before copying.
- Compares the source file with the database hash when the path belongs to an active certificate record.
- Refuses to overwrite a healthy backup when the NAS source appears corrupted.
- Copies through a temporary file and verifies the destination hash before finalizing.
- Preserves a previous destination version when a valid source file changes.
- Records every file in `pdf_protection_job_items`.
- Publishes progress and summary counters in `pdf_protection_jobs`.
- Can be monitored at `admin/pdf_protection_jobs.php`.

The recovery/remapping workflow remains available through:

```text
php scripts/pdf_protection_worker.php --mode=recovery --source=<external-source-root> --destination=<uploads-root> --dry-run
```

Always run a dry run first and retain the generated job report.

