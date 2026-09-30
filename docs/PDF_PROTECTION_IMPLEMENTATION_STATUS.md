# PDF Protection Implementation Status

## Implemented in the current worktree

- Central `pdf_fingerprints` registry with a unique SHA-256 primary key.
- Database triggers covering insert, update, status changes, and delete for birth, death, marriage, and marriage-license records.
- Trigger correction for safe hash replacement during record updates.
- Persistent `pdf_protection_jobs` and per-file job-item tables.
- Source/destination PDF manifest scanning and exact-hash comparison.
- Verified temporary-copy-then-finalize behavior for backup/recovery copies.
- CLI worker for incremental backup and recovery dry-run/import operations.
- Resumable/idempotent hash backfill script with conflict reporting.
- Admin status/report API and dashboard at `admin/pdf_protection_jobs.php`.
- Manual recovery import CLI for reviewed unmatched or ambiguous items.

## Development database verification

The local development database has been migrated through:

```text
040_pdf_fingerprint_registry.sql
041_pdf_protection_jobs.sql
042_pdf_fingerprint_triggers.sql
043_pdf_fingerprint_trigger_release_fix.sql
044_pdf_fingerprint_owner_index_fix.sql
```

The local database currently contains 31,477 fingerprint reservations. The backfill found one pre-existing duplicate hash involving two active death records; it was reported and not auto-resolved.

Regression checks passed:

- Synthetic duplicate insert rejected by the database trigger.
- Synthetic PDF hash replacement released the old hash and retained the new hash.
- Incremental backup dry-run produced progress output and job state.
- Actual five-file backup copied and verified files.
- A second five-file run marked all five files as already present.
- Recovery dry-run matched source files and reported unmatched items without changing records.

## Deployment commands

The complete standalone CLI migration runner is:

```text
php database/run_pdf_fingerprint_enforcement_complete.php
```

After migration, run:

```text
php scripts/backfill_pdf_fingerprints_resume.php
```

Review any reported conflicts before enabling production writes. The normal migration list should be updated to include migrations 040–044 in the deployment branch.

## Worker examples

Dry-run an incremental backup:

```text
php scripts/pdf_protection_worker.php --mode=backup --source=<uploads-root> --destination=<external-backup-root> --dry-run
```

Run the actual incremental backup:

```text
php scripts/pdf_protection_worker.php --mode=backup --source=<uploads-root> --destination=<external-backup-root>
```

Run a recovery preview from an external source:

```text
php scripts/pdf_protection_worker.php --mode=recovery --source=<external-source-root> --destination=<uploads-root> --dry-run
```

The worker writes persistent progress to the job tables and prints live progress to the console. The administrator dashboard reads the same job state and per-file outcomes.

