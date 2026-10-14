# iScan NAS Deployment & Update Guide

## Read this before updating

The Git checkout and the website files are in different folders:

| Purpose | NAS path |
|---|---|
| Git checkout used to receive GitHub changes | `/volume1/iscan` |
| Live Web Station application | `/volume1/iscan/release_blue_candidate_20260930` |
| Backups | `/volume1/iscan/backups` |

Updating `/volume1/iscan` with `git pull` **does not by itself update the website**. The live folder is not a Git checkout and has had NAS-specific changes. Deployment must copy only reviewed files into the live folder, after checking that their current contents are understood.

The NAS checkout may also contain unrelated untracked data such as Synology `#recycle` contents, old release folders, and backups. The helper leaves those files alone. It still stops for tracked local edits and for any untracked path that conflicts with an incoming file.

The DSM task **iScan Auto Update** currently contains an old script (`git reset --hard`, recursive `chown`, recursive `chmod`). That script is unsafe and does not deploy to the live folder. Keep the task disabled until the new helper below is pushed, bootstrapped, and passes its first NAS run. Do not paste the `sudo sh -s <<'NAS_UPDATE'` example previously discussed; it is not the Task Scheduler command.

### One-time setup required before the first deployment

The live folder has NAS-specific files and has previously differed from the local/Git version. The helper handles this safely per changed file: it deploys a file only if the live version matches the previous Git version, or already matches the new Git version. If a changed live file matches neither, it stops before writing any live files. This avoids needing to overwrite or normalize unrelated NAS-only files.

## Routine update: local XAMPP to the NAS

### 1. Finish and test the change locally

In PowerShell, from the project folder:

```powershell
Set-Location C:\xampp\htdocs\iscan
git status --short
```

Test the changed feature in XAMPP. Review the files shown by `git status`. Stage **only** files that belong to this update, including any new files the feature needs. Git marks staged new files as `A` in the name-status list. Do not use `git add -A` when unrelated changes or `.claude` deletions are present.

For the first install of the NAS deploy helper, stage these files explicitly, plus any feature files you intentionally want in the same update:

```powershell
git add scripts/deploy_nas_release.py scripts/test_deploy_nas_release.py NAS_DEPLOYMENT_GUIDE.md
```

If this commit also includes a feature, add its exact paths with another `git add` command. Do not copy a placeholder path.

```powershell
git diff --cached --name-status
git diff --cached --check
```

Review the staged diff. Confirm it contains no `.env`, uploads, logs, database dumps, temporary files, or unrelated deletions. Then commit and push:

```powershell
git commit -m "Describe the update"
git push origin main
```

If `git status --short` still shows unrelated work, leave it unstaged. Do not reset or discard it just to make the deployment proceed.

### 2. Connect to the NAS

From the office network:

```powershell
ssh mcrobaggao@192.168.1.12
```

From outside the office, if the configured Cloudflare SSH access is available:

```powershell
ssh mcrobaggao@ssh.cdrms.online
```

### 3. Run the read-only NAS preflight

These commands inspect the Git checkout and fetch the latest branch reference. They do not change live website files:

```sh
REPO=/volume1/iscan
LIVE=/volume1/iscan/release_blue_candidate_20260930

sudo git -c safe.directory="$REPO" -C "$REPO" branch --show-current
sudo git -c safe.directory="$REPO" -C "$REPO" status --short --untracked-files=no -- . ':(exclude)release_blue_candidate_20260930'
sudo test -f "$LIVE/.env" && echo "LIVE_ENV_OK"
if sudo test -f "$LIVE/public/index.php" || sudo test -f "$LIVE/index.php"; then
    echo "LIVE_APP_OK"
else
    echo "STOP: neither supported index.php entrypoint exists"
    exit 1
fi
sudo git -c safe.directory="$REPO" -C "$REPO" fetch origin main
sudo git -c safe.directory="$REPO" -C "$REPO" log -1 --oneline origin/main
```

Stop if the branch is not `main`, the status command shows tracked changes, either live-path check fails, or `fetch` fails. Do not run `git reset --hard` to clear a stop condition. The deployment helper separately checks that untracked files do not collide with incoming paths.

### 4. Deploy only the reviewed update

Use `scripts/deploy_nas_release.py`. It fetches `origin/main`, compares only changed tracked files with the live release, backs up affected files, applies safe changes, verifies them, then fast-forwards the NAS Git checkout. It supports new files and deletions. The helper and its test file stay in the NAS Git checkout; they are not copied into the live website.

The first time, after the helper is committed and pushed, bootstrap it from the NAS SSH shell. Check for tracked local changes (untracked NAS data is handled safely by the helper):

```sh
REPO=/volume1/iscan
sudo git -c safe.directory="$REPO" -C "$REPO" status --short --untracked-files=no -- . ':(exclude)release_blue_candidate_20260930'
```

If clean, fetch the helper from GitHub into a temporary file and run it as root. It will deploy pending code changes as well as install the helper into the Git checkout only after deployment succeeds:

```sh
sudo git -c safe.directory="$REPO" -C "$REPO" fetch origin main
sudo git -c safe.directory="$REPO" -C "$REPO" show origin/main:scripts/deploy_nas_release.py > /tmp/iscan_deploy_nas_release.py
sudo python3 /tmp/iscan_deploy_nas_release.py
```

Proceed only if the output ends with `ISCAN_NAS_DEPLOY_OK`. `STOP` means a preflight check failed before live files were changed; keep the task disabled and review the named file. `DEPLOYMENT_NEEDS_REVIEW` means the apply phase began: use the printed backup path and inspect the live files before retrying. The helper creates a timestamped code backup in `/volume1/iscan/backups/` before applying changes. It never changes `.env`, uploads, logs, storage, or temporary files. It does not run migrations.

After the first successful run, future updates use the installed helper. Do not use a full-directory `rsync`, `git reset --hard`, or recursive ownership/permission commands to get past a mismatch.

**New files:** include them in the staged diff, commit, and push. During deployment, create only their required parent directories and copy the new file if that exact live path is absent. If a file with the same name already exists, deploy only if its contents already match the pushed version; otherwise stop and review it. The same rule applies to new assets, PHP files, and migration files. A new migration file is copied as code but is never executed automatically.

### 5. Handle database migrations separately

Only run a migration when the update explicitly includes a new migration and its instructions have been reviewed. Before a production migration, take a database backup and verify the target database. Never run an old catch-up migration just because this guide mentions it, and never put a database password in a command, guide, or committed file.

After the migration (if one is required), verify the expected columns/tables and test the affected workflow using a non-production test record where possible.

### 6. Verify the deployed feature

Open `https://iscan.cdrms.online` and test the changed feature. Check the browser console and the relevant PHP error log. Confirm login, saving/editing, generated documents, and role restrictions that are affected by the change. Keep the backup until verification passes.

## DSM Task Scheduler

After the first helper run succeeds, replace the old Task Settings script with this command (keep the task user as `root`):

```sh
cd /volume1/iscan
python3 /volume1/iscan/scripts/deploy_nas_release.py
```

The helper itself runs `git fetch`, deploys only the changed files, and backs them up. You may keep the existing hourly schedule if you want updates to deploy automatically after pushing tested code, or run the task manually after a push. Do not add `sudo` to the Task Settings script when the task user is already `root`. Do not put a large heredoc in the Task Settings box.

## Backups

Application-code backups belong under `/volume1/iscan/backups/`. The live `.env`, uploads, and logs contain installation-specific data and are handled separately from code deployment. Database backups are separate from application-file backups.

## Troubleshooting rules

- **NAS Git checkout has changes:** stop and inspect them; do not reset them.
- **Live file differs from expected baseline:** stop, save the diff/report, and reconcile that file before deployment.
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
