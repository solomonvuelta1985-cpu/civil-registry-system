# iScan — Synology NAS Deployment Guide

**Version:** 1.0
**Prepared:** 2026-02-17
**System:** Synology DSM 7.x + Web Station + PHP 8.2 + MariaDB 10

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Step 1 — Install Synology Packages](#2-step-1--install-synology-packages)
3. [Step 2 — Transfer Project Files](#3-step-2--transfer-project-files)
4. [Step 3 — Configure Web Station](#4-step-3--configure-web-station)
5. [Step 4 — Configure PHP 8.2](#5-step-4--configure-php-82)
6. [Step 5 — Set Up the Database](#6-step-5--set-up-the-database)
7. [Step 6 — Run the Setup Script (SSH)](#7-step-6--run-the-setup-script-ssh)
8. [Step 7 — Configure Environment (.env)](#8-step-7--configure-environment-env)
9. [Step 8 — First Login & Test](#9-step-8--first-login--test)
10. [Step 9 — Tesseract OCR Setup](#10-step-9--tesseract-ocr-setup)
11. [Step 10 — Scanner Service Setup (Epson DS-530 II)](#11-step-10--scanner-service-setup-epson-ds-530-ii)
12. [Troubleshooting](#12-troubleshooting)
13. [Quick Reference](#13-quick-reference)

---

## 1. Prerequisites

Before starting, confirm you have:

| Item | Requirement |
|------|-------------|
| Synology NAS | DSM 7.0 or later |
| RAM | At least 2 GB (4 GB recommended) |
| Storage | At least 10 GB free on the volume |
| Network | NAS connected to your LAN router |
| Admin access | DSM admin account |
| SSH enabled | DSM > Control Panel > Terminal & SNMP > Enable SSH |
| Project files | This project (iScan) — copied from XAMPP PC |

---

## 2. Step 1 — Install Synology Packages

Open **DSM > Package Center** and install these packages in order:

### Required Packages

| Package | Purpose |
|---------|---------|
| **Web Station** | Runs Apache web server + PHP |
| **MariaDB 10** | Database server (MySQL-compatible) |
| **phpMyAdmin** | Database management GUI (optional but recommended) |

### How to Install:
1. Open DSM in browser: `http://your-nas-ip:5000`
2. Go to **Package Center**
3. Search each package name and click **Install**
4. Wait for all installations to complete

### Enable SSH (for setup script):
1. Go to **Control Panel > Terminal & SNMP**
2. Check **Enable SSH service**
3. Port: `22` (default)
4. Click **Apply**

---

## 3. Step 2 — Transfer Project Files

Copy the iScan project from your Windows PC to the Synology NAS.

### Option A — Using File Station (GUI, Easiest)

1. Open **DSM > File Station**
2. Navigate to **web** folder (this is the web root)
3. Click **Upload > Upload Folder**
4. Select the entire `iscan` folder from your PC
5. Wait for upload to complete
6. Result: `/volume1/web/iscan/`

### Option B — Using SFTP (Faster for Large Files)

Use **WinSCP** or **FileZilla** on Windows:

```
Protocol:  SFTP
Host:      192.168.x.x   (your NAS IP)
Port:      22
Username:  admin          (your DSM admin username)
Password:  (your DSM admin password)
```

Navigate to `/volume1/web/` on the NAS side, then drag and drop the `iscan` folder.

### Option C — Using Windows Network Share

1. On Synology: **Control Panel > File Services > SMB** — enable SMB
2. On Windows: Open File Explorer, type `\\YOUR-NAS-IP\web` in the address bar
3. Copy the `iscan` folder there

### Verify Transfer:
After transfer, the structure should be:
```
/volume1/web/
└── iscan/
    ├── admin/
    ├── api/
    ├── assets/
    ├── database/
    ├── includes/
    ├── public/
    ├── scanner_service/
    ├── uploads/
    ├── .env.synology
    ├── setup_synology.sh
    └── ...
```

---

## 4. Step 3 — Configure Web Station

### Create a Virtual Host / Service Portal

1. Open **DSM > Web Station**
2. Go to **Web Service Portal** tab
3. Click **Create > Create Virtual Host** (or edit the default portal)

**Settings:**
| Setting | Value |
|---------|-------|
| Port | `80` (HTTP) |
| Document Root | `/volume1/web` |
| PHP | PHP 8.2 (select from dropdown) |
| HTTP Backend Server | Apache 2.4 |

4. Click **Create / Save**

> **Note:** If you only see "Default Server" (no virtual host option), just edit the default server and set PHP to 8.2.

### Enable .htaccess Support

This is critical — without it, the root `.htaccess` file will not work:

1. In Web Station, go to **PHP Settings**
2. Select your PHP 8.2 profile → click **Edit**
3. Find **Apache configuration**
4. Make sure **AllowOverride All** is selected or enabled
5. Save

---

## 5. Step 4 — Configure PHP 8.2

### Apply iScan PHP Settings

1. Go to **DSM > Web Station > PHP Settings**
2. Select the **PHP 8.2** profile → click **Edit**
3. Find the **Additional .ini settings** text box
4. Open the file `php_synology.ini` from your project and **copy its contents**
5. Paste into the text box
6. Click **Save**

The key settings applied are:
```ini
upload_max_filesize = 20M    ; Allow large PDF uploads
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
session.cookie_httponly = 1
date.timezone = Asia/Manila
display_errors = Off
```

### Enable Required PHP Extensions

In the same PHP 8.2 profile editor, make sure these extensions are **checked/enabled**:
- `pdo_mysql` — database connection (required)
- `mbstring` — UTF-8 text handling (required)
- `fileinfo` — MIME type detection (required)
- `gd` — image processing for OCR (required)
- `json` — built-in PHP 8.x (already enabled)

---

## 6. Step 5 — Set Up the Database

### Create the Database and User

1. Open **phpMyAdmin**: `http://your-nas-ip/phpMyAdmin/`
2. Login with root credentials (set during MariaDB installation)
3. Click **SQL** tab and run these commands:

```sql
-- Create the database
CREATE DATABASE iscan_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create a dedicated user (replace 'YOUR_STRONG_PASSWORD')
CREATE USER 'iscan_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

-- Grant permissions
GRANT ALL PRIVILEGES ON iscan_db.* TO 'iscan_user'@'localhost';
FLUSH PRIVILEGES;
```

> **Important:** Write down the password you set — you will need it for `.env` in Step 7.

### Import the Database Schema

Still in phpMyAdmin:

1. Click on **iscan_db** in the left sidebar (select the database)
2. Click the **Import** tab
3. Click **Choose File**
4. Select `database_schema.sql` from your project (on your PC or navigate via NAS)
5. Click **Go / Import**

### Run All Migrations (in order)

Repeat the Import process for each migration file:

| Order | File | Location |
|-------|------|---------|
| 1 | `database_schema.sql` | Project root |
| 2 | `002_workflow_versioning_ocr_tables.sql` | `database/migrations/` |
| 3 | `003_calendar_notes_system.sql` | `database/migrations/` |
| 4 | `004_add_citizenship_to_birth_certificates.sql` | `database/migrations/` |
| 5 | `005_add_barangay_and_time_of_birth.sql` | `database/migrations/` |

> **Tip:** You can also run migrations via SSH (see Step 6).

---

## 7. Step 6 — Run the Setup Script (SSH)

This script automatically sets file permissions, creates your `.env`, and downloads offline assets.

### Connect via SSH

**On Windows:** Use **PuTTY** or Windows Terminal (PowerShell):

```powershell
ssh admin@192.168.x.x
```

Enter your DSM admin password when prompted.

### Run the Setup Script

```bash
sudo bash /volume1/web/iscan/setup_synology.sh
```

What the script does automatically:
- Sets correct file permissions (755 for dirs, 644 for files)
- Makes `uploads/` and `logs/` writable by the web server
- Creates `.env` from `.env.synology` template
- Downloads all offline vendor assets (Font Awesome, Chart.js, Notiflix, PDF.js, Lucide, Tesseract.js) to `assets/vendor/`
- Checks if Tesseract OCR is installed
- Checks if Python 3 is available for scanner service
- Prints database setup instructions

### Run Migrations via SSH (Alternative to phpMyAdmin)

```bash
mysql -u root -p iscan_db < /volume1/web/iscan/database_schema.sql
mysql -u root -p iscan_db < /volume1/web/iscan/database/migrations/002_workflow_versioning_ocr_tables.sql
mysql -u root -p iscan_db < /volume1/web/iscan/database/migrations/003_calendar_notes_system.sql
mysql -u root -p iscan_db < /volume1/web/iscan/database/migrations/004_add_citizenship_to_birth_certificates.sql
mysql -u root -p iscan_db < /volume1/web/iscan/database/migrations/005_add_barangay_and_time_of_birth.sql
```

---

## 8. Step 7 — Configure Environment (.env)

Edit the `.env` file that was created by the setup script:

```bash
nano /volume1/web/iscan/.env
```

**Minimum required changes:**

```env
APP_ENV=production

# Database — update with your actual password
DB_HOST=localhost
DB_NAME=iscan_db
DB_USER=iscan_user
DB_PASS=YOUR_STRONG_PASSWORD     ← change this

# Base URL
# Use /iscan/ if the app is at http://nas-ip/iscan/
# Use / if the app is at the root of a virtual host
BASE_URL=/iscan/

# Offline mode — keep true so it works without internet
OFFLINE_MODE=true
```

Save the file: press `Ctrl+X`, then `Y`, then `Enter`.

### Verify the .env is correct:

```bash
cat /volume1/web/iscan/.env
```

---

## 9. Step 8 — First Login & Test

Open a browser and navigate to:

```
http://YOUR-NAS-IP/iscan/
```

Or if the NAS hostname is configured:
```
http://NAS-HOSTNAME/iscan/
```

### Default Login Credentials:

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

> **CRITICAL: Change the admin password immediately after first login!**
> Go to: Admin Panel > Users > Edit admin user > Change Password

### Quick Functionality Test:

| Test | Expected Result |
|------|----------------|
| Login page loads | Form appears with logo |
| Login with admin/admin123 | Redirects to dashboard |
| Dashboard shows | Stats and charts visible |
| Open "Birth Certificate" form | Form loads with PDF preview panel |
| Upload a test PDF | PDF appears in the preview panel |
| Records Viewer | Table shows uploaded records |

### Test Offline Mode:

1. Disconnect the NAS from the internet (or unplug the WAN cable from your router)
2. Reload the app in browser
3. All pages should still load correctly (icons, notifications, charts, PDF viewer)

---

## 10. Step 9 — Tesseract OCR Setup

OCR allows the system to extract text from scanned PDF certificates.

### Check if Already Installed:

```bash
which tesseract
tesseract --version
```

If it shows a version (e.g., `tesseract 4.1.1`), OCR is ready. Skip to "Test OCR."

### Install Tesseract via Entware (SynoCommunity):

Entware is a package manager for Synology. If not installed:

1. Visit [SynoCommunity](https://synocommunity.com/) and add their package source to DSM
2. Or install Entware via SSH:

```bash
# Install Entware bootstrap (check SynoCommunity for latest method)
# Then install Tesseract:
opkg update
opkg install tesseract-ocr
opkg install tesseract-ocr-eng    # English language data
```

### Install Tesseract via Docker (Alternative):

If Entware is not available, use Docker:

1. Install **Docker** package in Synology Package Center
2. Pull the Tesseract image:

```bash
docker pull tesseractshadow/tesseract4re
```

3. Update `includes/TesseractOCR.php` to call Tesseract via Docker instead of direct binary.

### Test OCR:

After Tesseract is installed, test it via SSH:

```bash
tesseract --version
tesseract /volume1/web/iscan/test.pdf output -l eng txt
```

Then test via the web app:
1. Open a certificate form (Birth/Death/Marriage)
2. Upload a scanned PDF
3. Click the **OCR / Extract** button
4. Verify text fields are populated from the PDF

---

## 11. Step 10 — Scanner Service Setup (Epson DS-530 II)

This optional service allows direct scanning from the Epson DS-530 II scanner.

### Connect the Scanner:

1. Connect the Epson DS-530 II to the Synology NAS via USB
2. Check if Synology detects it:

```bash
lsusb
# Should show: Seiko Epson Corporation DS-530 II
```

### Install Python 3 and Dependencies:

```bash
# Check Python
python3 --version

# If not installed, use Entware:
opkg install python3 python3-pip

# Install scanner service dependencies:
cd /volume1/web/iscan/scanner_service/
pip3 install -r requirements.txt
```

### Start the Scanner Service:

```bash
bash /volume1/web/iscan/scanner_service/start_scanner.sh
```

### Check Scanner Service Status:

```bash
bash /volume1/web/iscan/scanner_service/start_scanner.sh status
```

Or test via browser/curl:
```bash
curl http://localhost:18622/scanner/status
```

Expected response:
```json
{"status": "ready", "scanner": "Epson DS-530 II"}
```

### Stop the Scanner Service:

```bash
bash /volume1/web/iscan/scanner_service/start_scanner.sh stop
```

### Auto-Start on NAS Reboot (Optional):

To make the scanner service start automatically when the NAS reboots:

1. Go to **DSM > Control Panel > Task Scheduler**
2. Click **Create > Triggered Task > User-defined script**
3. Settings:
   - **Task name:** iScan Scanner Service
   - **Event:** Boot-up
   - **User:** root
   - **Script:**
     ```bash
     bash /volume1/web/iscan/scanner_service/start_scanner.sh
     ```
4. Click **OK**

---

## 12. Troubleshooting

### Problem: White page / 500 Internal Server Error

**Check PHP error log:**
```bash
tail -50 /volume1/web/iscan/logs/php_errors.log
```

**Common causes:**
- `.env` file missing or wrong credentials → re-check Step 7
- Database not created → re-run Step 5
- File permissions wrong → re-run `sudo bash setup_synology.sh`

---

### Problem: "Database connection error"

```bash
# Test MySQL connection:
mysql -u iscan_user -p iscan_db
# Enter the password from your .env file
# If it fails, the user/password is wrong
```

Fix: Re-create the DB user with the correct password (Step 5), then update `.env` (Step 7).

---

### Problem: App loads but icons/styles are broken

The vendor assets may not have been downloaded. Run:

```bash
bash /volume1/web/iscan/download_assets.sh
```

Then verify files exist:
```bash
ls /volume1/web/iscan/assets/vendor/
```

Also verify `OFFLINE_MODE=true` is set in `.env`.

---

### Problem: PDF upload fails

Check:
1. `uploads/` directory is writable:
   ```bash
   ls -la /volume1/web/iscan/uploads/
   # Should show writable by 'http' user
   ```
   Fix: `sudo chmod -R 775 /volume1/web/iscan/uploads/`

2. PHP upload limit — verify `php_synology.ini` settings were applied (Step 4).

3. File too large — default limit is 20MB. Increase `upload_max_filesize` if needed.

---

### Problem: .htaccess not working (403 Forbidden on directories)

Apache's AllowOverride must be enabled. In Web Station:
1. Edit your PHP 8.2 profile
2. Look for Apache settings
3. Ensure `AllowOverride All` is set

Or check Apache config:
```bash
grep -r "AllowOverride" /etc/httpd/
```

---

### Problem: OCR button does nothing / fails

1. Check Tesseract is installed: `which tesseract`
2. Check PHP error log for OCR errors
3. Verify `tesseract` is in the PATH accessible by the web server user

---

### Problem: Scanner service not connecting

1. Check if it's running: `bash scanner_service/start_scanner.sh status`
2. Check scanner logs: `bash scanner_service/start_scanner.sh logs`
3. Check if port 18622 is in use: `netstat -tlnp | grep 18622`
4. Check USB connection: `lsusb | grep Epson`

---

### Problem: Can't access app from other devices on LAN

1. Check Synology firewall: **DSM > Control Panel > Security > Firewall**
   - Add a rule to allow port 80 from all source IPs
2. Verify the NAS IP on your router (should be static/reserved)
3. Try accessing via IP directly: `http://192.168.x.x/iscan/`

---

## 13. Quick Reference

### Important URLs

| URL | Purpose |
|-----|---------|
| `http://NAS-IP/iscan/` | Main application |
| `http://NAS-IP:5000` | DSM admin interface |
| `http://NAS-IP/phpMyAdmin/` | Database management |
| `http://NAS-IP:18622/scanner/status` | Scanner service status |

### Important File Paths (on Synology)

| Path | Purpose |
|------|---------|
| `/volume1/web/iscan/` | Project root |
| `/volume1/web/iscan/.env` | Environment configuration |
| `/volume1/web/iscan/uploads/` | Uploaded PDF files |
| `/volume1/web/iscan/logs/php_errors.log` | PHP error log |
| `/volume1/web/iscan/assets/vendor/` | Offline vendor assets |
| `/volume1/web/iscan/scanner_service/scanner.log` | Scanner service log |

### Key SSH Commands

```bash
# Check PHP errors
tail -f /volume1/web/iscan/logs/php_errors.log

# Re-run setup
sudo bash /volume1/web/iscan/setup_synology.sh

# Re-download vendor assets
bash /volume1/web/iscan/download_assets.sh

# Edit environment config
nano /volume1/web/iscan/.env

# Start scanner
bash /volume1/web/iscan/scanner_service/start_scanner.sh

# Stop scanner
bash /volume1/web/iscan/scanner_service/start_scanner.sh stop

# Import DB schema
mysql -u root -p iscan_db < /volume1/web/iscan/database_schema.sql

# Check disk space
df -h /volume1
```

### Default Login

| Field | Value |
|-------|-------|
| URL | `http://NAS-IP/iscan/` |
| Username | `admin` |
| Password | `admin123` |

> **Change password immediately after first login!**

---

*iScan Civil Registry Records Management System — Synology NAS Deployment Guide*
*Generated: 2026-02-17*
