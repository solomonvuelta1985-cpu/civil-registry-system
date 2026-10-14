# iScan NAS Deployment & Update Guide

## Read this before updating

The Git checkout and the website files are in different folders:

| Purpose | NAS path |
|---|---|
| Git checkout used to receive GitHub changes | `/volume1/iscan` |
| Live Web Station application | `/volume1/iscan/release_blue_candidate_20260930` |
| Backups | `/volume1/iscan/backups` |

Updating `/volume1/iscan` with `git pull` **does not by itself update the website**. The live folder is not a Git checkout and has had NAS-specific changes. Deployment must copy only reviewed files into the live folder, after checking that their current contents are understood.

The NAS checkout may also contain NAS-specific file changes, untracked data such as Synology `#recycle` contents, old release folders, and backups. The helper leaves them alone when their paths do not overlap the incoming update. It stops before deployment if a local path conflicts with an incoming file. A matching untracked source file under `docker/` is saved in `/volume1/iscan/backups/` and adopted by Git; other differing collisions still stop. `.env.example`, `.env.production`, `.env.synology`, `database_schema.sql`, `database/create_marriage_table.sql`, the already-applied `database/migrations/050_marriage_civil_status.sql`, `docs/RA9048_MODULE.md`, `scripts/test_template_files.php`, files under `docker/` and `tests/`, and the helper/guide paths stay in the Git checkout and are not copied into the live website. Keep applied migration files stable; create a new migration file for later schema changes. The helper can reconcile three specifically reviewed NAS variants (the branding comment/newline, the marriage civil-status options refactor, and formatting in the migration runner) only when the entire normalized result matches the pushed target; each replacement is backed up with the rest of the live files. Any other differences still stop preflight. When a commit deletes the tracked `.env.production` or `.env.synology` template, the helper saves a mode-600 copy under backups before updating the checkout. The actual `.env` and all other `.env.*` paths remain blocked. The single tracked dump `public/iscan_db (1).sql` may only be deleted, never added or updated; if the live copy matches the old Git version, the helper backs it up before deleting it. Other non-migration SQL files remain blocked. The helper ignores executable-bit drift caused by NAS-wide permission commands; it does not change permissions recursively.

The DSM task **iScan Auto Update** currently contains an old script (`git reset --hard`, recursive `chown`, recursive `chmod`). That script is unsafe and does not deploy to the live folder. Keep the task disabled for the manual deployment workflow in this guide. Do not paste the `sudo sh -s <<'NAS_UPDATE'` example previously discussed; it is not the Task Scheduler command.

### One-time setup required before the first deployment

The live folder has NAS-specific files and has previously differed from the local/Git version. The helper handles this safely per changed file: it deploys a file only if the live version matches the previous Git version, or already matches the new Git version. If a changed live file matches neither, it stops before writing any live files. This avoids needing to overwrite or normalize unrelated NAS-only files.

## Routine update: local XAMPP to the NAS

### 1. Open a terminal on the PC and test locally

On the Windows PC, open **PowerShell** from the Start menu, or open **Terminal → New Terminal** in VS Code. These first commands run on the PC, not on the NAS. Paste this block and press Enter:

```powershell
cd C:\xampp\htdocs\iscan
git branch --show-current
git status --short
```

Confirm the branch command prints `main`. Test the changed feature in XAMPP (usually at `http://localhost/iscan`). Review the paths printed by `git status --short`. Stage **only** the files for this update, including any new files it needs. Do not stage unrelated changes such as `.claude` deletions. New files must be named explicitly. Do not use `git add -A`.

### 2. Stage, commit, and push from the PC

Replace the example paths below with the exact paths from your `git status --short`, then paste the command and press Enter:

```powershell
git add public/certificate_of_live_birth.php assets/js/certificate-form-handler.js
```

The paths above are only an example—do not run them unless those are the files you changed. If you changed the deployment helper or this guide, include those exact paths too. Then inspect what is staged:

```powershell
git diff --cached --name-status
git diff --cached --check
```

Confirm the staged list contains only this update. `A` means a new file, `M` means modified, and `D` means deleted. If an unrelated file was staged by mistake, unstage only that path with `git restore --staged path\to\file`; this keeps its local contents. When the staged list is correct and `git diff --cached --check` prints no errors, commit and push:

```powershell
git commit -m "Describe the update"
git push origin main
```

Wait for `git push` to finish successfully before moving to the NAS. If `git status --short` still shows unrelated work, leave it unstaged. Do not reset or discard it just to make the deployment proceed.

### 3. Open the NAS SSH terminal

From the PC, open PowerShell and connect to the NAS. Use the office address while on the office network:

```powershell
ssh mcrobaggao@192.168.1.12
```

When outside the office, use the configured Cloudflare SSH address if it is available:

```powershell
ssh mcrobaggao@ssh.cdrms.online
```

Wait until the SSH login finishes and the prompt looks like `mcrobaggao@MCRO_NAS:~$`. The next command goes into this NAS SSH terminal, not the local XAMPP terminal.

### 4. Run the one-line NAS deployment command

Use `scripts/deploy_nas_release.py`. It fetches `origin/main`, compares only changed tracked files with the live release, backs up affected files, applies safe changes, verifies them, then fast-forwards the NAS Git checkout. It supports new files and deletions. The helper and its test file stay in the NAS Git checkout; they are not copied into the live website. If the release includes a database migration, first create and verify the database backup described in Section 5, then return here and deploy the code.

After local testing, commit and push the intended files to `origin/main`. Then run this same one-line command from the NAS SSH shell for each update. It fetches the newest helper, runs its preflight, backs up affected live files, deploys changed and new files, verifies the result, and advances the NAS Git checkout only after success. There is no need to delete or clean NAS-specific checkout files:

```sh
sudo sh -c 'R=/volume1/iscan; git -c safe.directory="$R" -C "$R" fetch origin main && git -c safe.directory="$R" -C "$R" show origin/main:scripts/deploy_nas_release.py > /tmp/iscan_deploy_nas_release.py && python3 /tmp/iscan_deploy_nas_release.py'
```

Paste that whole line at the NAS prompt and press Enter. When `sudo` asks for `Password:`, type the NAS sudo password and press Enter; the terminal will not display the password characters. Wait for the command to finish. Proceed only if the output ends with `ISCAN_NAS_DEPLOY_OK`. `STOP` means a preflight check failed before live website files were changed; send the complete stop output for review. Do not reset or overwrite conflicts. `DEPLOYMENT_NEEDS_REVIEW` means the apply phase began: use the printed backup path and inspect the live files before retrying. The helper creates a timestamped code backup in `/volume1/iscan/backups/` before applying live changes. It never copies environment files into the live release, changes uploads, logs, storage, or temporary files, or runs migrations. If the update includes a new `database/migrations/NNN_*.sql` file, follow Section 5 and apply that exact migration after deployment, before using the feature.

Use this same command for future updates; it fetches the latest helper before deploying. Do not use a full-directory `rsync`, `git reset --hard`, or recursive ownership/permission commands to get past a mismatch.

**New files:** include them in the staged diff, commit, and push. During deployment, create only their required parent directories and copy the new file if that exact live path is absent. If a file with the same name already exists, deploy only if its contents already match the pushed version; otherwise stop and review it. The same rule applies to new assets, PHP files, and migration files. A new migration file is copied as code but is never executed automatically.

### 5. Apply a database migration when the release needs one

A release needs a migration when it adds or changes database tables, columns, indexes, constraints, or other schema. PHP, JavaScript, and CSS changes that use the existing schema do not need one. The deployment helper copies migration files but never executes them. Check the pushed file list and the helper output `DATABASE_MIGRATIONS_REVIEW_REQUIRED=...`; use only the exact migration filename named there.

**Before the Section 4 deployment command, if the release includes a migration, create and verify a database backup:**

1. Sign in to iScan as an Admin and open **Maintenance → Database Backups** (`https://iscan.cdrms.online/admin/database_backups.php`).
2. Select an approved backup location and start a database backup.
3. Wait until its status is `completed`, then click **Verify** for that backup. Continue only when verification reports `VALID`. Keep the dump and manifest until the release is verified.

After the backup is verified, return to Section 4 and deploy the release. Do not use the new feature until its migration is applied.

After deployment, check the exact migration against the live database. Replace the example filename below with the filename printed by the deployment helper:

```sh
sudo /usr/local/bin/php82 /volume1/iscan/release_blue_candidate_20260930/database/apply_single_migration.php --check 053_crf_remarks_templates.sql
```

The check must print `DATABASE=iscan_db` and either `MIGRATION_STATUS=PENDING` or `MIGRATION_STATUS=ALREADY_APPLIED`. If the database name is different, or the command prints `STOP` or `ERROR`, stop and review before proceeding. If it says `ALREADY_APPLIED`, do not apply it again.

If it says `PENDING`, and the verified database backup is ready, apply **only that migration**:

```sh
sudo /usr/local/bin/php82 /volume1/iscan/release_blue_candidate_20260930/database/apply_single_migration.php --apply 053_crf_remarks_templates.sql
```

Expect `MIGRATION_STATUS=APPLIED`. Run the `--check` command again; it should then report `MIGRATION_STATUS=ALREADY_APPLIED`. Finally, test the affected iScan workflow with a non-production test record where possible.

`database/apply_single_migration.php` is CLI-only and accepts one numbered migration filename. **Do not run `database/run_migrations.php` to apply a single change:** it is a legacy batch runner containing older migrations that may not be recorded in the NAS migration table. Do not rerun a failed migration blindly; MySQL DDL can partially apply, so inspect the schema and migration record first. Keep applied migration files unchanged and create a new numbered migration for each later schema change. Never put a database password in a command, guide, or committed file.

### 6. Verify the deployed feature

Open `https://iscan.cdrms.online` and test the changed feature. Check the browser console and the relevant PHP error log. Confirm login, saving/editing, generated documents, and role restrictions that are affected by the change. Keep the backup until verification passes.

## DSM Task Scheduler

For the manual workflow, leave **iScan Auto Update** disabled; no Task Scheduler change is needed. If automatic deployment is intentionally configured later, first replace the old script with this command and keep the task user as `root`:

```sh
cd /volume1/iscan
python3 /volume1/iscan/scripts/deploy_nas_release.py
```

The helper itself runs `git fetch`, deploys only the changed files, and backs them up. Keep the scheduler disabled for the manual workflow. If automatic deployment is chosen later, enable it only after replacing the old script and testing the new task. Do not add `sudo` to the Task Settings script when the task user is already `root`. Do not put a large heredoc in the Task Settings box.

## Backups

Application-code backups belong under `/volume1/iscan/backups/`. The live `.env`, uploads, and logs contain installation-specific data and are handled separately from code deployment. Database backups are separate from application-file backups.

## Troubleshooting rules

- **NAS Git checkout has changes:** stop and inspect them; do not reset them.
- **Live file differs from expected baseline:** stop, save the complete conflict report, and reconcile all listed files before deployment.
- **Website still shows old code after `git pull`:** the Git checkout changed but the live release was not deployed; verify the deployed file and cache/versioned asset URLs.
- **Permission error:** inspect the affected file's owner, group, and mode, then make a targeted correction. Do not run `chmod -R 755` or `chown -R` over `/volume1/iscan`.
- **Migration error:** stop; do not repeatedly rerun it without checking the migration's transaction/idempotency behavior and database state.

## Connection details

- Public website: `https://iscan.cdrms.online`
- GitHub branch: `main`
- NAS Git checkout: `/volume1/iscan`
- Live Web Station release: `/volume1/iscan/release_blue_candidate_20260930`
- Backups: `/volume1/iscan/backups`

Do not store database passwords or other secrets in this guide.
