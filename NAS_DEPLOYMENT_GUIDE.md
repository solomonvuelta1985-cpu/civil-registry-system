# iScan NAS Deployment & Update Guide

## System Info
| Item | Value |
|---|---|
| NAS Model | Synology DS925+ |
| Local URL | http://192.168.1.12:8080 |
| Public URL | https://iscan.cdrms.online |
| NAS Username | mcrobaggao |
| NAS IP | 192.168.1.12 |
| DB Name | iscan_db |
| DB User | root |

---

## Updating iScan Code (From Your PC)

### Step 1 — Push changes from PC
Open terminal in `C:\xampp\htdocs\iscan` and run:
```bash
git add .
git commit -m "describe what you changed"
git push
```

---

### Step 2 — Pull on NAS (immediate update)

#### If you are IN the office (local network):
Open PowerShell and run:
```bash
ssh mcrobaggao@192.168.1.2
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
Once connected (either local or remote), run:
```bash
sudo chown -R mcrobaggao:users /volume1/iscan && git -C /volume1/iscan reset --hard HEAD && git -C /volume1/iscan pull origin main && sudo chown -R http:http /volume1/iscan && sudo chmod -R 755 /volume1/iscan
```

> **Note:** If you don't run Steps 2-3, the NAS will auto-pull within 1 hour via Task Scheduler.

### Step 4 — Run database migration (one-time catch-up)
After pulling the latest code, run the catch-up migration to add all missing columns:
```bash
mysql -u root -p iscan_db < /volume1/iscan/database/migrations/019_nas_catchup_all_missing_columns.sql
```
Enter DB password when prompted. This migration is safe to re-run (uses `IF NOT EXISTS`).

---

## Manual Database Backup

SSH into NAS and run:
```bash
ssh mcrobaggao@192.168.1.12
mysqldump -u root -p iscan_db > /volume1/backups/iscan_db_$(date +%Y%m%d).sql
```
Enter DB password when prompted: `iScan@NAS2026!`

---

## Automated Tasks (Already Configured in DSM Task Scheduler)

| Task | Schedule | What it does |
|---|---|---|
| iScan DB Backup | Daily 2:00 AM | Backs up database to /volume1/backups/ |
| iScan Auto Update | Every 1 hour | Pulls latest code from GitHub |

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
sudo chown -R http:http /volume1/iscan
sudo chmod -R 755 /volume1/iscan
```

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



In office → ssh mcrobaggao@192.168.1.12
At home/remote → ssh mcrobaggao@ssh.cdrms.online (via Cloudflare tunnel)
One-time setup instructions for new PCs
Step 3 with the full update command to run after connecting