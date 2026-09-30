# PDF Data Protection, Incremental Backup, and Disaster Recovery Plan

## 1. Purpose

This document defines the target design for protecting, validating, backing up, and recovering PDF attachments in the iSCAN Civil Registry Records Management System.

It covers:

- Duplicate-PDF detection across all users and certificate modules.
- Protection against simultaneous duplicate uploads.
- Detection of missing or corrupted PDFs on the NAS.
- Recovery when the NAS, storage volume, or server is replaced.
- Batch remapping of PDFs from a healthy external source drive.
- Incremental backup of only new or changed PDFs.
- Progress monitoring, retry, audit logs, and manual recovery workflows.

This is a design and implementation reference. It separates capabilities that already exist in the current codebase from capabilities that still need to be developed.

## 2. Executive Summary

The system can safely support this recovery model when two independent things are available:

1. A healthy database backup containing the certificate records and their stored PDF hashes.
2. At least one healthy source copy of the PDF files.

The database backup normally contains the record metadata, file paths, and `pdf_hash` values. The PDF binary files are stored on the filesystem, so a database dump alone cannot recreate the PDF contents.

The strongest recovery method is an exact SHA-256 hash match:

```text
Database pdf_hash == Source PDF SHA-256 hash
```

An exact hash match is sufficient for automatic mapping even when the source filename, folder, or external drive is different. Filename or name-only matches must never be treated as proof; they require manual review.

## 3. Current Capabilities in the Codebase

The current application already provides important building blocks:

| Capability | Current location | Current behavior |
|---|---|---|
| PDF upload validation | `includes/functions.php` | Checks upload errors, extension, MIME type, PDF header, and EOF marker. |
| SHA-256 on upload | `includes/functions.php` | Computes and stores a hash after the file is moved to storage. |
| Duplicate-PDF check | `includes/functions.php` | Checks active birth, death, marriage, and marriage-license records. |
| Birth duplicate rejection | `api/certificate_of_live_birth_save.php` | Rejects an exact hash already attached to another active record. |
| Update duplicate rejection | `api/certificate_of_live_birth_update.php` | Excludes the current record so re-uploading the same PDF to itself is allowed. |
| PDF integrity check during serving | `api/serve_pdf.php` | Recomputes the file hash and blocks a file whose hash differs from the stored hash. |
| Full integrity scan | `api/pdf_integrity_scan.php` | Reports `ok`, `corrupt`, `missing`, and `no_hash` states. |
| Individual backup restore | `api/pdf_restore.php` | Restores an available, verified PDF backup for one record. |
| Backup tracking | `pdf_backups` table | Tracks older PDF versions created during replacement. |
| Activity/security logging | Existing activity and security logging | Records administrative and integrity-related actions. |

Current limitations:

- The duplicate check is application-level and is not fully protected against two simultaneous uploads.
- `pdf_hash` indexes are non-unique and are spread across separate tables.
- A PDF that is merely present on disk but is not linked to a database record is not found by the duplicate check.
- Only active certificate records are checked by the current duplicate guard.
- Existing individual restore support is not the same as a bulk external-drive recovery/remapping tool.
- An external-drive incremental backup/synchronization job with progress monitoring is not yet implemented.

## 4. Duplicate PDF Protection

### 4.1 Hash-based identity

PDF identity must be based primarily on the SHA-256 hash of the exact file bytes, not on:

- Filename.
- Folder name.
- User account.
- Workstation.
- External-drive location.

Therefore, these files are considered the same PDF when their contents are byte-for-byte identical:

```text
C:\ExternalDriveA\AAAA.pdf
D:\ExternalDriveB\scanned-copy.pdf
NAS uploads\birth\2026\LIGAN\cert_123.pdf
```

### 4.2 Cross-user behavior

The duplicate rule is global when users connect to the same application server, database, and NAS storage. It is not scoped by `created_by` or by browser session.

Example:

1. User 1 saves Ambo Ligan and uploads `AAAA.pdf`.
2. The system stores the PDF hash on Ambo's record.
3. User 2 saves Juan Thomas and uploads the same PDF bytes.
4. The server computes the same hash.
5. The second request is rejected and identifies the existing record.

If users are working against separate offline installations or separate databases, they cannot see each other's records until the data is synchronized.

### 4.3 Scope of the current duplicate guard

The current helper checks active rows in:

- `certificate_of_live_birth`
- `certificate_of_death`
- `certificate_of_marriage`
- `application_for_marriage_license`

The current helper does not automatically search:

- Unlinked files sitting only in the uploads directory.
- Archived or deleted certificate records.
- Records with a missing `pdf_hash`.
- Every auxiliary PDF table or module unless explicitly added to the registry.

The final design should use a centralized PDF fingerprint registry so all modules can use one global uniqueness rule.

## 5. Simultaneous Upload Hardening

### 5.1 Current race condition

The current flow is approximately:

```text
Request A: check hash -> no duplicate
Request B: check hash -> no duplicate
Request A: insert certificate
Request B: insert certificate
```

Because the current check and insert are not one globally unique database operation, two nearly simultaneous requests may both pass.

### 5.2 Required solution

Add a centralized table such as `pdf_fingerprints` with a unique primary key on the SHA-256 hash:

```text
pdf_fingerprints
----------------
hash              CHAR(64) PRIMARY KEY
certificate_type  VARCHAR(...)
record_id         BIGINT
status            VARCHAR(...)
created_at        DATETIME
updated_at        DATETIME
```

The final implementation must:

1. Begin a database transaction.
2. Reserve or insert the PDF hash into the centralized table.
3. Let the database unique constraint decide which simultaneous request wins.
4. Insert or update the certificate record only for the winning reservation.
5. Return a friendly `409 Duplicate PDF` response to the losing request.
6. Roll back the reservation and remove the staged file if the certificate transaction fails.

A unique index on each existing certificate table is not enough because it would not enforce uniqueness across different tables.

### 5.3 Required concurrency test

The test suite must submit the same PDF to two certificate records at nearly the same time and verify:

```text
Expected result: exactly one success and exactly one duplicate rejection.
```

The test must also verify that the failed request leaves no orphaned file or incomplete record.

## 6. PDF Integrity and NAS Corruption

### 6.1 What is detected

When the stored NAS file changes after upload because of disk failure, bit rot, accidental modification, or an incomplete copy:

```text
Stored database hash != Current file hash
```

The system should mark the file as corrupted and block ordinary serving until recovery is completed.

The system should also detect:

- Missing files.
- Invalid PDF headers.
- Truncated files without an EOF marker.
- Records that have no stored hash.
- Backup files whose backup hash no longer matches.

### 6.2 What integrity checking cannot do

Integrity checking can prove that a file changed or disappeared. It cannot reconstruct the original PDF without a healthy source copy.

If a corrupted copy is uploaded as a new file, it may have a different hash and may not be recognized as the original. For that reason, recovery source files must be validated and compared against the original database hash whenever possible.

### 6.3 Recovery priority

If the NAS PDF is corrupt but a separate healthy backup exists:

1. Preserve the corrupt NAS file in quarantine.
2. Verify the backup header and stored backup hash.
3. Copy the backup to a staging path.
4. Verify the copied file hash.
5. Restore it to the record's expected path.
6. Run an integrity check and record the recovery event.

If the backup folder is on the same failed NAS volume, it must not be considered an independent disaster-recovery copy.

## 7. Full NAS or Server Replacement Recovery

### 7.1 Preconditions

Before recovery, confirm:

- The database backup is readable and complete.
- The backup date is known.
- The database includes `pdf_hash` values where expected.
- The application version and database schema are compatible.
- The source external drive is readable.
- The source PDFs are not themselves corrupted.
- The new server has enough storage capacity.
- The new server has correct file ownership and permissions.
- The application configuration, database credentials, PHP extensions, and upload paths are restored.

### 7.2 Recovery sequence

1. Freeze user writes and record the incident.
2. Preserve the failed NAS data as read-only evidence where possible.
3. Provision the new NAS/server.
4. Restore the database backup.
5. Apply and verify all required migrations.
6. Verify certificate row counts and hash coverage.
7. Attach the external source drive read-only.
8. Run a source inventory and PDF validation scan.
9. Create a recovery batch and a dry-run mapping report.
10. Approve exact hash matches and review uncertain matches.
11. Copy approved source PDFs to a staging area.
12. Verify each copied file's hash.
13. Move verified files into the expected uploads paths.
14. Update only the existing record's file path/hash metadata where needed.
15. Run a full integrity scan.
16. Open a sample of recovered PDFs from every certificate type.
17. Create a new verified backup before reopening user access.
18. Re-enable writes after the recovery report is accepted.

## 8. Batch PDF Recovery and Remapping

Recovery must use a dedicated administrative workflow, not the normal certificate upload form. The recovery tool must not silently create new civil registry records.

### 8.1 Batch lifecycle

Each recovery operation receives a unique `Recovery Batch ID` and follows these states:

```text
Created -> Scanning -> Preview Ready -> Approved -> Importing
        -> Verifying -> Completed
        -> Completed With Errors / Paused / Failed
```

The batch should support a dry-run mode before any production file is changed.

### 8.2 Source inventory

For every source file, record:

- Source path.
- Relative source path.
- Filename.
- File size.
- Modified timestamp.
- SHA-256 hash.
- PDF validation result.
- Page count when available.
- Optional OCR or text fingerprint.
- Batch ID.

The source drive should be treated as read-only during inventory and matching.

### 8.3 Mapping order

Use this order of confidence:

1. Exact SHA-256 match to the database `pdf_hash` — automatic.
2. Exact hash match to a verified backup record — automatic with audit entry.
3. Original relative path plus hash/metadata confirmation — admin approval.
4. Registry number, filename, names, date, page count, or OCR — manual review only.
5. No reliable candidate — unresolved.

The system must never silently map an ambiguous candidate.

### 8.4 Mapping outcomes

Each source or database file must receive one status:

- `Exact Match`
- `Imported`
- `Already Present`
- `Changed Candidate`
- `Needs Manual Review`
- `Unmatched Source`
- `Missing Source`
- `Invalid PDF`
- `Corrupt Source`
- `Duplicate Source Hash`
- `Skipped`
- `Failed`

## 9. Incremental External PDF Backup

### 9.1 Goal

The system must copy only new or changed files from the current NAS uploads area to the external backup drive. Unchanged files must be skipped.

### 9.2 Comparison data

The backup job should compare:

- Relative path.
- File size.
- Modified timestamp.
- SHA-256 hash.
- Database record ID and certificate type where available.
- Previous backup manifest.

Hash is the final content identity. Filename, path, size, and timestamp are optimization hints and must not override a hash mismatch.

### 9.3 Incremental rules

| Situation | Action |
|---|---|
| Source and destination have the same verified hash | Skip copy. |
| Source exists and destination is missing | Copy and verify. |
| Both exist but hashes differ and source is valid | Copy as a new version or approved replacement. |
| Source hash differs from the database hash | Mark source as potentially corrupt; do not overwrite a healthy backup automatically. |
| Destination has a file not known to the current system | Keep it and report as orphan; do not delete automatically. |
| Same content exists under a different destination filename | Report as hash match and apply the chosen mirror-path policy. |

### 9.4 Safe copy behavior

Every copied file should:

1. Be copied to a temporary destination name.
2. Be validated as a PDF.
3. Have its SHA-256 recomputed after copying.
4. Be finalized only when the hash matches the source hash.
5. Be recorded in the backup manifest and audit log.

Never overwrite a healthy external backup with a source file that fails the database integrity check without explicit administrator approval.

### 9.5 Full verification versus fast incremental mode

The system should support two modes:

- **Fast incremental mode:** compare the manifest and metadata first; hash new or changed candidates.
- **Full verified mode:** hash all source files and verify all backup files. Use periodically and after a storage incident.

The fast mode keeps routine backups efficient. The full mode provides stronger assurance against silent disk corruption.

## 10. Batch Processing and Progress Monitoring

The backup and recovery workflows must not depend on one long browser request. They should use persistent jobs and small processing chunks.

### 10.1 Job-level fields

Each backup or recovery job should record:

- Job/batch ID.
- Job type.
- Operator or scheduler.
- Source location.
- Destination location.
- Start time.
- Last activity time.
- End time.
- Current status.
- Total files.
- Files scanned.
- Files copied/imported.
- Files skipped.
- Files failed.
- Files needing review.
- Bytes processed.
- Bytes copied.
- Error count.
- Last error.

### 10.2 Live UI fields

The admin dashboard should show:

```text
Status: Running
Progress: 4,250 / 10,000 files
Copied: 86
Skipped: 4,120
Failed: 3
Needs review: 41
Data copied: 2.4 GB
Elapsed: 08:32
Estimated remaining: 03:10
```

The dashboard should support pause, resume, retry failed items, download report, and safely stop a job.

### 10.3 Failure isolation

Each file should have its own transaction or finalization step. A failed file must not roll back all successful files in the batch.

The batch itself should still produce a clear final state such as `Completed With Errors` rather than falsely reporting full success.

## 11. Logging and Reporting

Both successful and failed actions must be traceable.

### 11.1 Per-file log fields

- Job/batch ID.
- Source path.
- Destination path.
- Source hash.
- Destination hash after copy.
- Target certificate type.
- Target record ID.
- Registry number.
- Match method.
- Status.
- Failure reason.
- Attempt count.
- Operator.
- Timestamps.

### 11.2 Required reports

Every completed job should provide:

- Summary counts.
- Successful items.
- Skipped items.
- Failed items.
- Unmatched items.
- Ambiguous/manual-review items.
- Corrupt source items.
- Orphan destination items.
- Hash mismatch details.
- Retry history.

Example:

```text
Recovery Batch #2026-0001
Source PDFs:             10,000
Exact matches:            9,850
Imported:                 9,820
Already present:             30
Failed:                      12
Needs manual review:         8
Unmatched source files:       3
Invalid/corrupt sources:      7
```

Reports should be downloadable as CSV and JSON, with a human-readable summary for administrators.

## 12. Retry, Idempotency, and Rollback

The process must be safe to rerun.

- Retrying an already verified file must result in `Already Present` or `Skipped`, not a duplicate.
- A failed copy must leave no partially finalized destination file.
- A failed mapping must not create a new certificate record.
- Re-running a recovery batch must reuse the original mapping decision where safe.
- Every changed path/hash must have an audit entry.
- If a batch must be rolled back, rollback must be limited to files and metadata changed by that batch.
- Existing healthy records and unrelated files must not be deleted automatically.

## 13. Security and Operational Controls

Recovery and backup operations must be restricted to authorized administrators.

Required controls:

- Authentication and permission checks.
- CSRF protection for browser-triggered actions.
- Path traversal protection.
- Safe filename and path normalization.
- Read-only source scanning where practical.
- No automatic deletion of source or orphan files.
- Audit logs for start, approval, import, restore, retry, failure, and completion.
- Encryption or controlled physical access for external drives containing civil records.
- Storage capacity check before starting a copy job.
- Locking or maintenance mode during database-wide recovery.

## 14. Backup Strategy

The NAS must not be the only location containing both the database and the PDFs.

Use a 3-2-1 strategy:

- At least 3 copies of important data.
- At least 2 different storage media.
- At least 1 copy offsite or offline/immutable.

Recommended protected sets:

1. Production database and current NAS PDFs.
2. Incremental PDF backup on an external drive or separate storage device.
3. Separate offsite or offline database plus PDF backup.

The backup itself must be periodically tested by restoring a sample and performing a full recovery drill. A backup that has never been restored is not yet proven reliable.

## 15. Monitoring and Alerts

Routine scheduled jobs should alert administrators when:

- A backup job fails.
- A job completes with errors.
- The number of missing or corrupt PDFs increases.
- The external backup drive is unavailable.
- Storage space is low.
- The last successful backup is older than the allowed threshold.
- Hash mismatches occur.
- A source file is being protected from overwrite because it appears corrupt.

## 16. Implementation Phases

### Phase 1: Foundation and database hardening

- Confirm all certificate tables have `pdf_hash`.
- Backfill missing hashes where files are healthy.
- Add `pdf_hash` indexes where missing.
- Design and migrate the centralized `pdf_fingerprints` registry.
- Update create, update, archive, delete, restore, and replacement flows.
- Add concurrency tests.

### Phase 2: Recovery inventory and dry run

- Add recovery batch and per-file job tables.
- Scan an external source drive.
- Validate source PDFs and compute hashes.
- Generate exact-match and manual-review reports.
- Add an approval screen before production changes.

### Phase 3: Batch remapping and recovery

- Copy to staging.
- Verify post-copy hashes.
- Map files to existing records.
- Support pause, resume, retry, and failure isolation.
- Add CSV/JSON and human-readable reports.

### Phase 4: Incremental external backup

- Build source/destination manifest comparison.
- Copy only new or changed files.
- Protect known-good destination backups from corrupt source files.
- Add progress dashboard and scheduled execution.

### Phase 5: Disaster-recovery validation

- Restore the database to a test environment.
- Restore or remap a sample of each certificate type.
- Run integrity scans.
- Open sample PDFs.
- Record recovery time and unresolved cases.
- Repeat the drill periodically.

## 17. Acceptance Criteria

The implementation is considered ready when all of the following are true:

- Two simultaneous uploads of the same PDF result in one success and one rejection.
- The duplicate rule is global across supported certificate modules.
- A renamed but byte-identical source PDF maps by hash.
- A corrupt source file is not silently imported.
- A corrupt current NAS file is not allowed to overwrite a healthy backup automatically.
- Recovery can be previewed before it changes records.
- Each file has a final status and traceable log entry.
- Failed files can be retried without creating duplicates.
- A batch can pause and resume.
- Copy verification detects destination corruption.
- Full database restore plus a healthy external PDF source can rebuild active PDF attachments.
- Uncertain mappings require manual approval.
- A completed recovery produces a downloadable report.
- An independent restore test succeeds.

## 18. Key Principle

The system must prefer a safe unresolved result over an incorrect automatic mapping.

```text
Exact hash match      -> automatic
Strong but non-exact  -> manual review
Ambiguous/no match    -> do not import automatically
Corrupt source        -> quarantine and report
```

This protects the integrity of the civil registry records while still allowing large-scale recovery and backup operations to be completed efficiently.
