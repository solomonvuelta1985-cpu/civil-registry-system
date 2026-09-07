# iScan NAS Deployment & Update Guide

## System Info
| Item | Value |
|---|---|
| NAS Model | Synology DS925+ |
| Local URL | http://192.168.1.12:8080 |
| Public URL | https://iscan.cdrms.online |
| NAS Username | mcrobaggao |
| NAS IP | 192.168.1.12 (verify in DSM before use) |
| DB Name | iscan_db |
| DB User | root (prefer a dedicated least-privilege application user) |
| DB Password | Never store in this guide; enter it only at the prompt |

---

## Updating iScan Code (From Your PC)

### Step 1 — Push changes from PC
Open terminal in `C:\xampp\htdocs\iscan` and run:
```powershell
git status --short
git add -A
git diff --cached --name-status
# Confirm that .env, logs/, tmp/, uploads/, and database dumps are not staged.
git commit -m "security: remediate audit findings and harden deployment"
git push origin main
```

---

### Step 2 — Pull on NAS (immediate update)

#### If you are IN the office (local network):
Open PowerShell and run:
```bash
ssh mcrobaggao@192.168.1.12
```

#### If you are at HOME or REMOTE (via Cloudflare Tunnel):

> **One-time setup (already done):** `cloudflared` is installed and SSH config is set.
> If on a new PC, install cloudflared first:
> ```powershell
> winget install Cloudflare.cloudflared
> ```
> Then add this to `C:\Users\<you>\.ssh\config`:
> ```
> Host ssh.cdrms.online
>     ProxyCommand C:\PROGRA~2\cloudflared\cloudflared.exe access ssh --hostname ssh.cdrms.online
> ```

Open PowerShell and run:
```powershell
ssh mcrobaggao@ssh.cdrms.online
```
Enter your NAS password when prompted.

---

### Step 3 — Run update command on NAS
Before updating, make a rollback archive and confirm that the NAS checkout has no local changes. Do not use `git reset --hard` unless you have deliberately backed up and approved the changes it would destroy.

Once connected (either local or remote), run:
```bash
set -eu
cd /volume1/iscan
sudo mkdir -p /volume1/backups/iscan-pre-update
sudo tar --exclude='.git' --exclude='uploads' -czf "/volume1/backups/iscan-pre-update/iscan_$(date +%Y%m%d_%H%M%S).tgz" .
git status --short
if [ -n "$(git status --porcelain)" ]; then
  echo "NAS checkout has local changes; stop and review before pulling."
  exit 1
fi
git pull --ff-only origin main
```

Do not apply recursive ownership or `chmod -R 755` commands blindly. Preserve the Web Station owner/group required by this NAS, keep `.env` readable only by the service account, and verify permissions after the update.

> **Note:** Pause the automatic update task while performing this controlled deployment. Do not rely on an unattended pull for this security release; re-enable it only after the post-deployment checks pass.

### Step 4 — Run database migration (one-time catch-up)
After pulling the latest code, run the catch-up migration to add all missing columns:
```bash
mysql -u root -p iscan_db < /volume1/iscan/database/migrations/019_nas_catchup_all_missing_columns.sql
```
Enter the DB password at the prompt. Never place it in this guide, shell history, or a committed file. This migration is safe to re-run (uses `IF NOT EXISTS`). Run any additional migrations listed in `docs/NAS_SECURITY_REMEDIATION.md`.

---

## Manual Database Backup

SSH into NAS and run:
```bash
ssh mcrobaggao@192.168.1.12
umask 077
mysqldump -u root -p iscan_db > /volume1/backups/iscan_db_$(date +%Y%m%d_%H%M%S).sql
```
Enter the DB password when prompted. Do not record it in this guide or in shell history.

---

## Automated Tasks (Already Configured in DSM Task Scheduler)

| Task | Schedule | What it does |
|---|---|---|
| iScan DB Backup | Daily 2:00 AM | Backs up database to /volume1/backups/ |
| iScan Auto Update | Every 1 hour | Pulls latest code from GitHub; pause during controlled deployments and re-enable only after verification |

---

## Cloudflare Tunnel
- Tunnel name: **MCRO-NAS-iScan**
- Route: `iscan.cdrms.online` → `http://localhost:8080`
- Managed at: dash.cloudflare.com → Zero Trust → Networks → Tunnels

---

## Mapped Network Drive
- Drive: **Z:**
- Path: `\\192.168.1.12\iscan`
- Use for direct file access without SSH

---

## GitHub Repository
- URL: https://github.com/solomonvuelta1985-cpu/civil-registry-system
- Branch: `main`

---

## Troubleshooting

### 403 Error on website
```bash
ssh mcrobaggao@192.168.1.12
ls -ld /volume1/iscan /volume1/iscan/.env
sudo -u http test -r /volume1/iscan/public/index.php
```
Do not fix a 403 with `chmod -R 755`. Check the Web Station document-root, virtual-host, owner/group, and Apache error log first. Apply only the minimum targeted permission change after confirming the required service account.

### Git pull permission error
```bash
sudo chown -R mcrobaggao:users /volume1/iscan/.git
git config --global --add safe.directory /volume1/iscan
```

### Check if Apache is running
```bash
synopkg status Apache2.4
```

### Restart Apache
```bash
synopkg stop Apache2.4
synopkg start Apache2.4
```

### Check Apache error log
```bash
tail -30 /var/packages/WebStation/var/log/apache24_error_log
```

### Post-deployment security checks
Run these checks before allowing real records to be used:

```bash
# The application must be reachable only over HTTPS in production.
curl -I https://iscan.cdrms.online/

# These paths must return 403 or 404, never application data.
curl -I https://iscan.cdrms.online/.git/config
curl -I https://iscan.cdrms.online/.env
curl -I https://iscan.cdrms.online/public/iscan_db%20%281%29.sql
curl -I https://iscan.cdrms.online/uploads/
```

Then test with a non-admin account: login, role restrictions, CSRF-protected save/update/delete actions, PDF authorization, session expiry, logout, backup restore/delete controls, and scanner access. Use synthetic records until these tests pass.



In office → ssh mcrobaggao@192.168.1.12
At home/remote → ssh mcrobaggao@ssh.cdrms.online (via Cloudflare tunnel)
One-time setup instructions for new PCs
Step 3 with the full update command to run after connecting
