# iScan — Production Deployment Quick Start

**For experienced users** — Concise steps for deploying iScan to shared hosting (cPanel/Plesk).

For detailed step-by-step instructions, see [CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md).

---

## Prerequisites

✅ Shared hosting with cPanel/Plesk
✅ PHP 7.4+ (8.0+ recommended)
✅ MySQL 5.7+ or MariaDB 10+
✅ 256MB+ PHP memory_limit
✅ Domain/subdomain configured

---

## Deployment Steps

### 1. Upload Files

```
Upload to: /public_html/iscan/
Method: cPanel File Manager, FTP, or SFTP
```

---

### 2. Create Database

```
cPanel > MySQL Databases
├── Create database: iscan_db
├── Create user: iscan_user (strong password)
└── Add user to database (ALL PRIVILEGES)
Note: cPanel adds prefix (e.g., username_iscan_db)
```

---

### 3. Configure Environment

```bash
# Via cPanel File Manager or FTP
cp .env.production .env
chmod 600 .env  # or 644 if host doesn't allow 600

# Edit .env with your credentials:
DB_HOST=localhost
DB_NAME=username_iscan_db
DB_USER=username_iscan_user
DB_PASS=your_password_here
BASE_URL=/iscan/
ENABLE_HSTS=false  # Set to true after SSL
```

---

### 4. Import Database

**Option A — Setup Wizard (Recommended):**
```
Visit: https://yourdomain.com/iscan/setup_wizard.php
Follow screens: System Check → Database → Import → Complete
Wizard auto-deletes after completion
```

**Option B — Manual (phpMyAdmin):**
```
cPanel > phpMyAdmin > Import
├── database_schema.sql
├── database/migrations/002_workflow_versioning_ocr_tables.sql
├── database/migrations/003_calendar_notes_system.sql
├── database/migrations/004_add_citizenship_to_birth_certificates.sql
├── database/migrations/005_add_barangay_and_time_of_birth.sql
├── database/migrations/006_registered_devices.sql
└── database/migrations/007_add_pdf_hash.sql
```

---

### 5. Set Permissions

```bash
# Via cPanel File Manager
uploads/  → 755 (or 775)
logs/     → 755 (or 775)
.env      → 600 (or 644)
```

---

### 6. Configure PHP (Optional)

```
cPanel > PHP Selector (or MultiPHP INI Editor)

upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
```

---

### 7. SSL Certificate (Recommended)

```
cPanel > SSL/TLS Status > Install Let's Encrypt (Free)

After SSL works:
1. Edit .env: ENABLE_HSTS=true
2. Test: https://yourdomain.com/iscan/
```

---

### 8. First Login

```
URL: https://yourdomain.com/iscan/
Username: admin
Password: admin123

⚠️ IMMEDIATELY change password:
Admin Panel > Users > Edit admin > New Password
```

---

## Verification Checklist

- [ ] Dashboard loads with statistics
- [ ] Can create/edit certificates
- [ ] Can upload PDFs
- [ ] Records appear in Records Viewer
- [ ] Search works
- [ ] Security logs visible (Admin > Security Logs)
- [ ] Default admin password changed
- [ ] setup_wizard.php deleted
- [ ] HTTPS working (if SSL installed)

---

## Features NOT Available on Shared Hosting

❌ **Scanner Service** — Requires Python + USB (not supported on shared hosting)
❌ **Tesseract OCR** — May not be installed (use browser-based OCR fallback)

**Alternatives:**
- Scan on local XAMPP → upload PDFs
- Use browser-based OCR (slower but works)
- Upgrade to VPS for full features

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| White page / 500 error | Check `logs/php_errors.log`, verify `.env` exists |
| Database connection error | Verify DB credentials in `.env` match cPanel (including prefix) |
| File upload fails | Check `uploads/` permissions (755/775), increase PHP upload limits |
| CSS/JS broken | Verify `BASE_URL` in `.env`, check CDN access, try offline mode |
| HTTPS redirect loop | Comment out HTTPS redirect in `.htaccess` or fix proxy config |

---

## Important Files

```
/public_html/iscan/
├── .env                  ← Database credentials, settings
├── .env.production       ← Template (copy to .env)
├── .htaccess             ← Apache config, security rules
├── setup_wizard.php      ← Delete after first setup
├── uploads/              ← Must be writable (755/775)
├── logs/                 ← Must be writable (755/775)
└── docs/
    ├── CPANEL_DEPLOYMENT.md     ← Full deployment guide
    ├── PRODUCTION_QUICK_START.md ← This file
    └── SECURITY.md              ← Security hardening guide
```

---

## Security Checklist

- [ ] Changed default admin password
- [ ] Deleted setup_wizard.php
- [ ] SSL certificate installed
- [ ] ENABLE_HSTS=true (after SSL)
- [ ] .env permissions set to 600
- [ ] Regular backups configured
- [ ] Security logs monitored
- [ ] Device lock enabled (after registering devices)

---

## Support

- **Full Documentation:** [docs/README.md](README.md)
- **Detailed cPanel Guide:** [docs/CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md)
- **Security Guide:** [docs/SECURITY.md](SECURITY.md)
- **Features List:** [docs/COMPLETE_FEATURES_LIST.md](COMPLETE_FEATURES_LIST.md)

---

*For detailed explanations and troubleshooting, see [CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md)*
