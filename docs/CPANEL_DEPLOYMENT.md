# iScan — cPanel Shared Hosting Deployment Guide

**Version:** 1.0
**Prepared:** 2026-02-18
**System:** cPanel/Plesk Shared Hosting + PHP 7.4+/8.x + MySQL 5.7+/MariaDB 10+

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Step 1 — Check Hosting Requirements](#2-step-1--check-hosting-requirements)
3. [Step 2 — Upload Project Files](#3-step-2--upload-project-files)
4. [Step 3 — Create Database](#4-step-3--create-database)
5. [Step 4 — Configure Environment (.env)](#5-step-4--configure-environment-env)
6. [Step 5 — Import Database Schema](#6-step-5--import-database-schema)
7. [Step 6 — Run Setup Wizard (Recommended)](#7-step-6--run-setup-wizard-recommended)
8. [Step 7 — Set File Permissions](#8-step-7--set-file-permissions)
9. [Step 8 — Configure PHP Settings](#9-step-8--configure-php-settings)
10. [Step 9 — SSL Certificate (Optional but Recommended)](#10-step-9--ssl-certificate-optional-but-recommended)
11. [Step 10 — First Login & Test](#11-step-10--first-login--test)
12. [Step 11 — Features Not Available on Shared Hosting](#12-step-11--features-not-available-on-shared-hosting)
13. [Troubleshooting](#13-troubleshooting)
14. [Security Checklist for Public Internet](#14-security-checklist-for-public-internet)
15. [Performance Optimization](#15-performance-optimization)
16. [Upgrading to VPS (Full Features)](#16-upgrading-to-vps-full-features)
17. [Quick Reference](#17-quick-reference)

---

## 1. Prerequisites

Before starting, confirm you have:

| Item | Requirement |
|------|-------------|
| **Hosting Account** | Shared hosting with cPanel or Plesk panel |
| **PHP Version** | PHP 7.4 or higher (8.0+ recommended) |
| **Database** | MySQL 5.7+ or MariaDB 10+ |
| **Memory Limit** | At least 256MB PHP memory_limit |
| **Disk Space** | At least 500MB free space |
| **Domain/Subdomain** | Configured and pointing to your hosting |
| **FTP/SFTP Access** | Credentials for file upload |
| **cPanel Access** | Full cPanel login credentials |

### Recommended Hosting Specifications

| Setting | Minimum | Recommended |
|---------|---------|-------------|
| PHP Version | 7.4 | 8.2 |
| Memory Limit | 256MB | 512MB+ |
| Max Execution Time | 60s | 120s |
| Upload Max Filesize | 10MB | 20MB |
| Post Max Size | 15MB | 25MB |

---

## 2. Step 1 — Check Hosting Requirements

### A. Access cPanel

Log into your hosting cPanel:
```
https://yourdomain.com:2083
OR
https://yourhostname.com/cpanel
```

Enter your cPanel username and password.

---

### B. Check PHP Version

1. In cPanel, search for **"PHP Selector"** or **"Select PHP Version"**
2. Verify PHP version is **7.4 or higher**
3. If not, change to **PHP 8.0** or **PHP 8.2** (recommended)

**Alternative:** Look for **"MultiPHP Manager"** to select PHP version per domain.

---

### C. Enable Required PHP Extensions

In **PHP Selector** or **PHP Extensions**, ensure these are ENABLED:

| Extension | Purpose | Required |
|-----------|---------|----------|
| **pdo_mysql** | Database connection | ✅ Yes |
| **mysqli** | Database connection (alt) | ✅ Yes |
| **mbstring** | UTF-8 text handling | ✅ Yes |
| **fileinfo** | MIME type detection | ✅ Yes |
| **gd** | Image processing for OCR | ✅ Yes |
| **json** | JSON processing | ✅ Yes (built-in PHP 8+) |
| **zip** | Archive handling | Recommended |
| **curl** | HTTP requests | Recommended |

**How to enable:**
1. Go to cPanel > **Select PHP Version** (or **PHP Extensions**)
2. Check all required extensions
3. Click **Save** or **Apply**

---

### D. Verify PHP Settings

Check or update these PHP settings via **PHP Selector** or **php.ini**:

| Setting | Value | Location |
|---------|-------|----------|
| `upload_max_filesize` | 20M | cPanel > PHP Selector |
| `post_max_size` | 25M | cPanel > PHP Selector |
| `memory_limit` | 256M (512M better) | cPanel > PHP Selector |
| `max_execution_time` | 120 | cPanel > PHP Selector |
| `session.cookie_httponly` | 1 | Usually default |

**Note:** Some shared hosts don't allow changing all settings. If blocked, contact support.

---

## 3. Step 2 — Upload Project Files

You have three options to upload the iScan project:

---

### Option A — cPanel File Manager (Easiest)

1. In cPanel, open **File Manager**
2. Navigate to **public_html/** (or your domain's document root)
3. Create a new folder: **iscan** (or your preferred folder name)
4. Enter the **iscan** folder
5. Click **Upload** button (top toolbar)
6. Select the **iscan.zip** file from your PC
7. Wait for upload to complete
8. Click **Reload** in File Manager
9. Right-click **iscan.zip** → **Extract**
10. Delete **iscan.zip** after extraction

**Result:** Files should be at `/public_html/iscan/`

---

### Option B — FTP/SFTP (Faster for Large Files)

Use **FileZilla**, **WinSCP**, or **Cyberduck**:

**Connection Settings:**
```
Protocol:  FTP or SFTP
Host:      ftp.yourdomain.com  (or your hosting IP)
Port:      21 (FTP) or 22 (SFTP)
Username:  your-cpanel-username
Password:  your-cpanel-password
```

**Steps:**
1. Connect via FTP/SFTP
2. Navigate to **public_html/** on remote side
3. Create folder **iscan**
4. Drag and drop all iScan project files into **iscan/**
5. Wait for transfer to complete

---

### Option C — Git Clone (If SSH Available)

If your hosting provides SSH access:

```bash
ssh your-username@yourdomain.com
cd public_html
git clone https://github.com/yourusername/iscan.git
cd iscan
```

---

### Verify File Structure

After upload, your structure should look like:

```
/public_html/iscan/
├── admin/
├── api/
├── assets/
├── database/
├── docs/
├── includes/
├── logs/
├── public/
├── scanner_service/
├── uploads/
├── .env.production
├── .htaccess
├── setup_wizard.php
└── ...
```

---

## 4. Step 3 — Create Database

### A. Create Database via cPanel

1. In cPanel, go to **MySQL Databases** (or **MySQL Database Wizard**)
2. Click **Create New Database**
3. **Database Name:** Enter `iscan_db` (cPanel will add prefix: `username_iscan_db`)
4. Click **Create Database**
5. Note the full database name (e.g., `cpanel_username_iscan_db`)

---

### B. Create Database User

1. Scroll down to **MySQL Users** section
2. Click **Add New User**
3. **Username:** Enter `iscan_user`
4. **Password:** Click **Password Generator** for strong password
5. **IMPORTANT:** Copy and save the password — you'll need it for `.env`
6. Click **Create User**
7. Note the full username (e.g., `cpanel_username_iscan_user`)

---

### C. Add User to Database

1. Scroll down to **Add User to Database**
2. **User:** Select `iscan_user`
3. **Database:** Select `iscan_db`
4. Click **Add**
5. On the **Manage Privileges** page, check **ALL PRIVILEGES**
6. Click **Make Changes**

---

### D. Note Your Database Credentials

Write down these exact values:

```
DB_HOST: localhost
DB_NAME: cpanel_username_iscan_db  (the full name with prefix)
DB_USER: cpanel_username_iscan_user  (the full name with prefix)
DB_PASS: [the password you generated]
```

You'll need these for the `.env` file in the next step.

---

## 5. Step 4 — Configure Environment (.env)

### A. Copy .env.production to .env

**Via cPanel File Manager:**
1. Go to **File Manager** > **public_html/iscan/**
2. Find the file `.env.production`
3. Right-click → **Copy**
4. Name it: `.env`
5. Click **Copy File**

**Via FTP:**
1. Download `.env.production` to your PC
2. Rename it to `.env`
3. Upload `.env` back to the same folder

---

### B. Edit .env File

**Via cPanel File Manager:**
1. Right-click `.env` → **Edit**
2. Click **Edit** again to confirm

**Update these critical settings:**

```env
# Database (use the credentials from Step 3)
DB_HOST=localhost
DB_NAME=cpanel_username_iscan_db
DB_USER=cpanel_username_iscan_user
DB_PASS=paste_your_generated_password_here

# Base URL (adjust based on your domain)
BASE_URL=/iscan/

# HTTPS (keep false initially)
ENABLE_HSTS=false
```

**Base URL Examples:**
- If accessed via `https://yourdomain.com/iscan/` → use `BASE_URL=/iscan/`
- If accessed via `https://iscan.yourdomain.com/` → use `BASE_URL=/`
- If installed at root `https://yourdomain.com/` → use `BASE_URL=/`

3. Click **Save Changes**

---

### C. Verify .env Permissions

1. Right-click `.env` → **Permissions**
2. Set to **600** (owner read/write only)
3. If your host doesn't allow 600, use **644** (but 600 is more secure)

---

## 6. Step 5 — Import Database Schema

You have two options:

---

### Option A — phpMyAdmin (Manual Import)

1. In cPanel, open **phpMyAdmin**
2. In left sidebar, click your database name (e.g., `cpanel_username_iscan_db`)
3. Click **Import** tab (top menu)
4. Click **Choose File**
5. Navigate to your PC copy of the project
6. Select `database_schema.sql`
7. Scroll down and click **Go**
8. Wait for import to complete (you should see green success message)

**Import Migrations (in order):**

Repeat the import process for each migration file:

| Order | File | Location |
|-------|------|---------|
| 1 | `database_schema.sql` | Project root (already done above) |
| 2 | `002_workflow_versioning_ocr_tables.sql` | `database/migrations/` |
| 3 | `003_calendar_notes_system.sql` | `database/migrations/` |
| 4 | `004_add_citizenship_to_birth_certificates.sql` | `database/migrations/` |
| 5 | `005_add_barangay_and_time_of_birth.sql` | `database/migrations/` |
| 6 | `006_registered_devices.sql` | `database/migrations/` |
| 7 | `007_add_pdf_hash.sql` | `database/migrations/` |

**To import each migration:**
1. phpMyAdmin > Import > Choose File > Select migration file > Go
2. Verify green success message
3. Proceed to next migration

---

### Option B — Setup Wizard (Automated)

**Easier option** — use the setup wizard in the next step, which can import all migrations automatically.

---

## 7. Step 6 — Run Setup Wizard (Recommended)

The setup wizard automates database import, file permissions check, and asset download.

### A. Access Setup Wizard

Open in browser:
```
https://yourdomain.com/iscan/setup_wizard.php
```

---

### B. Follow Wizard Steps

**Screen 1: Welcome & System Check**
- Checks PHP version, extensions, memory limit
- Checks file permissions
- Shows red ❌ or green ✅ for each requirement
- **Fix any red items** before continuing

**Screen 2: Database Setup**
- Enter database credentials from Step 3
- Wizard tests connection
- If successful, click **Next**

**Screen 3: Import Database**
- Wizard auto-imports `database_schema.sql`
- Wizard auto-imports all migration files in correct order
- Shows progress and results
- Click **Next** when complete

**Screen 4: Download Assets (Optional)**
- Choose whether to download vendor assets for offline mode
- Recommended: **Skip** (use CDN) unless you need offline mode
- If you click **Download**, wizard fetches Font Awesome, Chart.js, PDF.js, etc.

**Screen 5: Security Check**
- Wizard verifies file permissions
- Shows recommendations for uploads/, logs/, .env
- Make adjustments if needed

**Screen 6: Complete**
- Shows login URL
- Shows default credentials
- **IMPORTANT:** Wizard will auto-delete itself
- Click **Finish & Delete Wizard**

---

### C. Verify Wizard Deleted

After completion, verify `setup_wizard.php` is gone:
- Try accessing `https://yourdomain.com/iscan/setup_wizard.php`
- Should show 404 error (file not found)

**If wizard still exists (security risk):**
1. cPanel File Manager > public_html/iscan/
2. Delete `setup_wizard.php` manually

---

## 8. Step 7 — Set File Permissions

### Via cPanel File Manager

1. Go to **File Manager** > **public_html/iscan/**
2. Select **uploads/** folder
3. Right-click → **Permissions**
4. Set to **755** (or **775** if 755 doesn't work)
5. Check **Recurse into subdirectories**
6. Click **Change Permissions**

Repeat for:
- **logs/** folder → **755** (or **775**)

**For .env file:**
1. Select `.env`
2. Right-click → **Permissions**
3. Set to **600** (or **644** if host doesn't allow 600)

---

### Permission Reference

| Path | Permission | Purpose |
|------|------------|---------|
| `uploads/` | 755 or 775 | Write uploaded PDFs |
| `logs/` | 755 or 775 | Write log files |
| `.env` | 600 or 644 | Protect credentials |
| `.htaccess` | 644 | Apache config |
| All other folders | 755 | Read/execute |
| All PHP files | 644 | Read only |

---

## 9. Step 8 — Configure PHP Settings

### Via cPanel PHP Selector

1. cPanel > **Select PHP Version** (or **MultiPHP INI Editor**)
2. Adjust these settings:

| Setting | Recommended Value |
|---------|-------------------|
| `upload_max_filesize` | 20M |
| `post_max_size` | 25M |
| `memory_limit` | 256M (or 512M) |
| `max_execution_time` | 120 |

3. Click **Apply** or **Save**

---

### Alternative: Create php.ini

If your host doesn't provide PHP Selector:

1. File Manager > **public_html/iscan/**
2. Create new file: **php.ini** (or **.user.ini** depending on host)
3. Add these lines:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
session.cookie_httponly = 1
date.timezone = Asia/Manila
display_errors = Off
log_errors = On
```

4. Save and wait 5 minutes for changes to take effect

**Note:** `.htaccess` already includes these settings, but cPanel might override them.

---

## 10. Step 9 — SSL Certificate (Optional but Recommended)

### Why SSL/HTTPS?

- **Encryption:** Protects data in transit (login credentials, personal information)
- **Trust:** Browser shows padlock 🔒 instead of "Not Secure" warning
- **SEO:** Google ranks HTTPS sites higher
- **Compliance:** Required for government/official sites

---

### A. Install SSL Certificate via cPanel

**Option 1: Let's Encrypt (Free SSL)**

1. cPanel > **SSL/TLS Status** or **Let's Encrypt SSL**
2. Select your domain
3. Click **Install Certificate** or **Issue**
4. Wait 30-60 seconds
5. Verify success message

**Option 2: AutoSSL (Some hosts)**

1. cPanel > **SSL/TLS Status**
2. Find your domain
3. Click **Run AutoSSL**
4. Wait for completion

**Option 3: Upload SSL Certificate**

If you purchased SSL from another provider:
1. cPanel > **SSL/TLS** > **Manage SSL Sites**
2. Paste Certificate (CRT), Private Key, Certificate Authority Bundle
3. Click **Install Certificate**

---

### B. Test HTTPS

After installing SSL:
1. Access your site via `https://yourdomain.com/iscan/`
2. Verify padlock 🔒 appears in browser address bar
3. Click padlock → should show "Connection is secure"

---

### C. Enable HTTPS Enforcement

After SSL is working:

1. **Edit .env:**
   ```env
   ENABLE_HSTS=true
   ```

2. **Update .htaccess (optional forced redirect):**

   Open `.htaccess` in File Manager, find this section:
   ```apache
   # Force HTTPS (optional - only enable after SSL is configured)
   # Uncomment these lines after installing SSL certificate:
   # RewriteCond %{HTTPS} off
   # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

   Remove the `#` symbols to enable:
   ```apache
   # Force HTTPS
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

3. Save and test — all HTTP requests should now redirect to HTTPS

---

## 11. Step 10 — First Login & Test

### A. Access the Application

Open in browser:
```
https://yourdomain.com/iscan/
```

Or if HTTP (before SSL):
```
http://yourdomain.com/iscan/
```

---

### B. Default Login Credentials

| Field | Value |
|-------|-------|
| **Username** | `admin` |
| **Password** | `admin123` |

---

### C. **CRITICAL: Change Password Immediately**

After first login:
1. Go to **Admin Panel** > **Users**
2. Find **admin** user
3. Click **Edit** (pencil icon)
4. Enter new strong password
5. Click **Save**

**Or** use the profile menu:
1. Click **admin** (top-right corner)
2. Select **Profile** or **Change Password**
3. Enter new password
4. Save

---

### D. Quick Functionality Test

| Test | Expected Result |
|------|----------------|
| **Dashboard loads** | Statistics, charts visible |
| **Birth Certificate form** | Form loads, fields visible |
| **Upload PDF** | PDF preview appears |
| **Save certificate** | Record saved, appears in table |
| **Records Viewer** | Table shows saved records |
| **Search** | Can search by name, date, registry number |
| **Reports** | Can generate certificate reports |
| **Security Logs** | Shows login attempts in Admin > Security Logs |

---

### E. Test Security Features

1. **Rate Limiting:**
   - Try logging in with wrong password 6 times
   - Should show "Too many attempts" error

2. **Session Timeout:**
   - Leave browser idle for 1 hour
   - Should auto-logout and redirect to login

3. **CSRF Protection:**
   - Already enabled, works automatically

---

## 12. Step 11 — Features Not Available on Shared Hosting

### Scanner Service (NOT SUPPORTED)

**Why it won't work:**
- Requires Python 3 + Flask web server
- Requires USB device access (Epson DS-530 II scanner)
- Requires ability to run background processes
- Shared hosting blocks all of these

**Alternatives:**
1. **Scan on local XAMPP PC** → upload PDFs to shared hosting via Records page
2. **Network Scanner** → use scan-to-email, download, upload to iScan
3. **Mobile/Flatbed Scanner** → save as PDF, upload via browser
4. **Upgrade to VPS** → see Section 16 for full scanner support

---

### Tesseract OCR (MAY NOT WORK)

**Why it might not work:**
- Requires Tesseract binary installed on server
- Most shared hosts don't include it
- Requires shell access to install (usually not allowed)

**What happens:**
- System will show friendly error: "OCR service unavailable on this server"
- Provides alternatives (see below)

**Alternatives:**
1. **Browser-based OCR** (Tesseract.js):
   - Built-in fallback option
   - Works on shared hosting
   - Slower than server-side OCR
   - Limited accuracy for poor quality scans

2. **Process on local XAMPP:**
   - Run OCR on local PC with Tesseract installed
   - Upload filled certificates to shared hosting

3. **Manual entry:**
   - Type data into forms manually
   - Most reliable for small batches

4. **Contact hosting support:**
   - Ask if Tesseract can be installed
   - Some hosts accommodate custom software

5. **Upgrade to VPS:**
   - Full root access to install Tesseract
   - See Section 16

---

### Performance Limitations

**Shared hosting typically has:**
- Lower memory limits (256MB-512MB)
- Slower CPU (shared with other users)
- Strict execution time limits (30-120 seconds)
- Database query limits

**Impact:**
- Large PDF processing may timeout
- Reports with 1000+ records may be slow
- Concurrent users may experience delays

**Optimizations:**
- Use smaller batch sizes
- Process large uploads on local XAMPP first
- Consider VPS for high-traffic deployment

---

## 13. Troubleshooting

### Problem: White Page / 500 Internal Server Error

**Cause:** PHP error or misconfiguration

**Solution:**
1. Check **PHP error log** (cPanel > **Errors** or **Error Log**)
2. Check `logs/php_errors.log` via File Manager
3. Common causes:
   - .env file missing → copy `.env.production` to `.env`
   - Wrong database credentials → edit `.env`
   - File permissions too restrictive → set folders to 755

---

### Problem: "Database connection error"

**Cause:** Wrong database credentials or database not created

**Solution:**
1. Verify database exists: cPanel > **MySQL Databases**
2. Verify user added to database
3. Check `.env` file credentials match exactly (including prefix)
4. Test connection via phpMyAdmin using same credentials

**Common mistakes:**
- Forgot cPanel username prefix (e.g., `username_iscan_db` not `iscan_db`)
- Typo in password
- User not granted ALL PRIVILEGES

---

### Problem: File upload fails

**Cause:** File size limit, permissions, or PHP settings

**Solution:**
1. Check `upload_max_filesize` in cPanel PHP Selector → increase to 20M
2. Check folder permissions: `uploads/` should be **755** or **775**
3. Check disk space quota (cPanel home page shows usage)
4. Try smaller test file (1MB) to isolate issue

---

### Problem: CSS/JS not loading (broken styling)

**Cause:** BASE_URL mismatch or CDN blocked

**Solution:**
1. Verify `BASE_URL` in `.env` matches actual URL path
   - If accessing via `/iscan/` → set `BASE_URL=/iscan/`
   - If accessing at root `/` → set `BASE_URL=/`
2. Check if CDN is accessible (firewall blocking)
3. Enable offline mode:
   - Run `download_assets.php` via browser
   - Set `OFFLINE_MODE=true` in `.env`

---

### Problem: .htaccess not working (403 errors)

**Cause:** Apache AllowOverride disabled

**Solution:**
1. Contact hosting support: "Please enable AllowOverride All for .htaccess"
2. Some hosts require .htaccess in different location
3. Check if host uses nginx instead of Apache (requires different config)

---

### Problem: "Permission denied" errors

**Cause:** File/folder permissions too strict or wrong owner

**Solution:**
1. Set folders to **755**: uploads/, logs/, assets/
2. Set files to **644**: all .php files
3. If still failing, try **775** for folders, **664** for files
4. Contact hosting support if owner is wrong

---

### Problem: Session logout loops

**Cause:** Session files not writable or path issue

**Solution:**
1. Check PHP session.save_path is writable
2. Contact hosting support to verify session configuration
3. Try clearing browser cookies/cache

---

### Problem: HTTPS redirect loop

**Cause:** Incorrect .htaccess redirect or proxy configuration

**Solution:**
1. Edit `.htaccess`, comment out HTTPS redirect:
   ```apache
   # RewriteCond %{HTTPS} off
   # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
2. If behind CloudFlare/proxy, use:
   ```apache
   RewriteCond %{HTTP:X-Forwarded-Proto} !https
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

---

### Problem: "OCR not available" message

**Expected on shared hosting** — see Section 11 for alternatives.

**To verify:**
1. SSH to host (if available): `which tesseract`
2. If returns empty, Tesseract not installed
3. Use alternatives listed in Section 11

---

### Problem: High memory usage / timeout errors

**Cause:** Large PDF processing exceeds PHP limits

**Solution:**
1. Increase `memory_limit` to 512M via cPanel PHP Selector
2. Increase `max_execution_time` to 300 seconds
3. Process large files on local XAMPP instead
4. Split large multi-page PDFs into smaller files

---

## 14. Security Checklist for Public Internet

### Critical Security Steps

- [ ] **Change default admin password** (admin/admin123 is PUBLIC KNOWLEDGE)
- [ ] **Enable HTTPS** (install SSL certificate)
- [ ] **Set ENABLE_HSTS=true** in `.env` (after SSL is working)
- [ ] **Delete setup_wizard.php** (if it still exists)
- [ ] **Verify .env is protected** (should be blocked by .htaccess)
- [ ] **Set strong database password** (16+ characters, mixed case, symbols)
- [ ] **Enable device lock** (after registering all authorized devices)
- [ ] **Review security logs regularly** (Admin > Security Logs)
- [ ] **Keep backups** (cPanel Backup Wizard or download files/database)

---

### Optional Hardening

- [ ] **IP whitelist for admin panel** (edit .htaccess to restrict /admin/)
- [ ] **Two-factor authentication** (if added as feature)
- [ ] **Regular password rotation** (set PASSWORD_EXPIRY_DAYS in .env)
- [ ] **Monitor file changes** (cPanel File Manager for unexpected modifications)
- [ ] **Enable WAF** (Web Application Firewall if host provides)
- [ ] **Use CloudFlare** (free CDN + DDoS protection + firewall)

---

### Backup Strategy

**Automated Backups (Recommended):**
1. cPanel > **Backup Wizard**
2. Set up automatic daily/weekly backups
3. Download backups to local PC monthly

**Manual Backups:**
1. **Database:** phpMyAdmin > Export > Save .sql file
2. **Files:** File Manager > Compress > Download .zip
3. Store offsite (Google Drive, external HDD)

**Restore Procedure:**
1. **Database:** phpMyAdmin > Import > Choose .sql file
2. **Files:** File Manager > Upload .zip > Extract

---

## 15. Performance Optimization

### Optimize Database

1. **Enable query cache** (if host allows)
2. **Add indexes** (already included in schema)
3. **Regular cleanup:**
   - Delete old security logs: Admin > Security Logs > Delete old entries
   - Archive old certificates if needed

---

### Enable Caching

**.htaccess caching** (already included but verify):

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

### Use CDN (Free Option)

**CloudFlare Free Plan:**
1. Sign up at cloudflare.com
2. Add your domain
3. Update nameservers at domain registrar
4. Enable "Auto Minify" for CSS, JS, HTML
5. Enable "Rocket Loader" for JS optimization
6. Use "Page Rules" to cache everything

**Benefits:**
- Faster global load times
- DDoS protection
- Free SSL certificate
- Caching reduces server load

---

### Optimize Images/PDFs

- Compress PDFs before upload (Adobe Acrobat, smallpdf.com)
- Use lower DPI for scanned documents (200-300 DPI sufficient)
- Archive old records to reduce database size

---

## 16. Upgrading to VPS (Full Features)

### When to Upgrade

Consider VPS/Cloud hosting if you need:
- **Scanner service** (Epson DS-530 II integration)
- **Server-side OCR** (Tesseract for fast text extraction)
- **High performance** (many concurrent users)
- **Large storage** (10,000+ certificates)
- **Custom software** (root access for any tool)

---

### Recommended VPS Providers

| Provider | Starting Price | Pros |
|----------|---------------|------|
| **DigitalOcean** | $6/month | Easy setup, good docs, SSD storage |
| **Linode** | $5/month | Reliable, fast network, 24/7 support |
| **Vultr** | $6/month | Global locations, hourly billing |
| **AWS Lightsail** | $5/month | Amazon infrastructure, scalable |
| **Hetzner** | €4.5/month | Very cheap, Europe-based |

**Recommended Specs:**
- **CPU:** 1-2 cores
- **RAM:** 2GB minimum (4GB recommended)
- **Storage:** 50GB SSD
- **OS:** Ubuntu 22.04 LTS or CentOS 8

---

### VPS Setup Overview

On VPS, you'll have full access to:
1. **Install Tesseract OCR** (`apt install tesseract-ocr`)
2. **Run scanner service** (Python Flask on port 18622)
3. **Configure Apache/Nginx** (full control over virtual hosts)
4. **Install security tools** (fail2ban, firewall rules)
5. **Automated backups** (cron jobs, snapshots)
6. **Custom PHP settings** (no host restrictions)

**Setup Guide:**
- See [docs/VPS_DEPLOYMENT.md](VPS_DEPLOYMENT.md) (create this guide if needed)
- Or follow Synology guide as reference (similar steps)

---

## 17. Quick Reference

### Important URLs

| URL | Purpose |
|-----|---------|
| `https://yourdomain.com/iscan/` | Main application |
| `https://yourdomain.com/iscan/setup_wizard.php` | Setup wizard (delete after use) |
| `https://yourdomain.com:2083` | cPanel login |
| `https://yourdomain.com/phpMyAdmin/` | Database management |

---

### Important File Paths (on Server)

| Path | Purpose |
|------|---------|
| `/public_html/iscan/` | Project root |
| `/public_html/iscan/.env` | Environment configuration |
| `/public_html/iscan/uploads/` | Uploaded PDF files |
| `/public_html/iscan/logs/php_errors.log` | PHP error log |
| `/public_html/iscan/.htaccess` | Apache configuration |

---

### Default Login

| Field | Value |
|-------|-------|
| URL | `https://yourdomain.com/iscan/` |
| Username | `admin` |
| Password | `admin123` |

> **⚠️ CHANGE PASSWORD IMMEDIATELY AFTER FIRST LOGIN!**

---

### Support & Documentation

| Resource | Location |
|----------|----------|
| **Full Documentation** | [docs/README.md](README.md) |
| **Security Guide** | [docs/SECURITY.md](SECURITY.md) |
| **Quick Start** | [docs/PRODUCTION_QUICK_START.md](PRODUCTION_QUICK_START.md) |
| **Synology Deployment** | [docs/SYNOLOGY_NAS_DEPLOYMENT.md](SYNOLOGY_NAS_DEPLOYMENT.md) |
| **Feature List** | [docs/COMPLETE_FEATURES_LIST.md](COMPLETE_FEATURES_LIST.md) |

---

### cPanel Quick Commands

**Access logs:**
```
cPanel > Metrics > Errors
cPanel > Metrics > Raw Access
```

**Database management:**
```
cPanel > Databases > phpMyAdmin
```

**File management:**
```
cPanel > Files > File Manager
```

**Backup:**
```
cPanel > Files > Backup Wizard
```

---

*iScan Civil Registry Records Management System — cPanel Shared Hosting Deployment Guide*
*Last Updated: 2026-02-18*
