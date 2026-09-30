# Resumable PDF Protection Runbook

Use `scripts/pdf_protection_resumable_worker.php` for large recovery or backup batches. It builds an idempotent inventory in `pdf_protection_job_items`, processes only queued files, records attempts and final status, and can continue after a process restart.

## Preview and approval

```text
php scripts/pdf_protection_resumable_worker.php --mode=recovery --source=<external-source-root> --destination=<uploads-root> --dry-run
```

The command returns a job ID and leaves the job in `preview_ready`. An administrator may approve it through `api/pdf_protection_job_control.php` with the `approve` action, then execute the same job with `--job-id=N` without `--dry-run`.

## Chunked execution

```text
php scripts/pdf_protection_resumable_worker.php --mode=recovery --source=<external-source-root> --destination=<uploads-root> --limit=100
php scripts/pdf_protection_resumable_worker.php --job-id=N --mode=recovery --source=<external-source-root> --destination=<uploads-root> --limit=100
```

The first command creates the job. Later commands reuse its inventory and do not create duplicate job items. The worker uses a database named lock so two workers cannot process the same job at once.

## Pause, resume, cancel, and retry

The admin-only POST endpoint `api/pdf_protection_job_control.php` accepts:

- `pause`: stops at the next file boundary;
- `resume`: places a paused job back in the queue;
- `cancel`: stops the batch safely at the next boundary;
- `retry`: requeues failed, invalid, or corrupt-source items; and
- `approve`: authorizes a dry-run preview for actual execution.

After `resume` or `retry`, run the worker again with the same `job_id`. No PDF is imported twice because each item has a persistent status and verified hash.

## Downloadable reports

Administrators can download the complete job report, including every source hash, mapping decision, attempt count, error, and final status:

```text
/api/pdf_protection_job_report.php?job_id=N&format=csv
/api/pdf_protection_job_report.php?job_id=N&format=json
```

Always retain the report with the recovery batch record and review `needs_review`, `unmatched`, `invalid_pdf`, `corrupt_source`, and `failed` items before reopening production writes.
