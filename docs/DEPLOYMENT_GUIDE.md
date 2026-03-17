# Deployment Guide - iSCAN Security Features
## Quick Setup for Localhost and Production

---

## 🚀 Quick Start (Localhost)

### Step 1: Run Database Migration

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select `iscan_db` database
3. Click "Import" tab
4. Import file: `database/security_tables_migration.sql`
5. Click "Go"

**OR** via MySQL command line:
```bash
cd c:\xampp\htdocs\iscan
mysql -u root -p iscan_db < database\security_tables_migration.sql
```

### Step 2: Test Your Application

**That's it!** Your application now has:
- ✅ CSRF Protection
- ✅ Rate Limiting (5 attempts per 5 minutes)
- ✅ Session Timeout (1 hour)
- ✅ Security Logging
- ✅ All security headers
- ✅ Enhanced validation

**No configuration needed for localhost!** The system uses smart defaults.

### Step 3: Test Security Features

1. **Test CSRF Protection:**
   - Forms now have hidden CSRF tokens
   - Try submitting without token (should fail)

2. **Test Rate Limiting:**
   - Try logging in with wrong password 6 times
   - Should get locked out after 5 attempts
   - Wait 5 minutes or clear: `DELETE FROM rate_limits;`

3. **Test Session Timeout:**
   - Login and wait 1 hour
   - Try to navigate - should redirect to login

---

## 🏭 Production Deployment

### Step 1: Create Environment File

```bash
copy .env.example .env
```

### Step 2: Edit .env with Production Values

```env
# IMPORTANT: Change these values!
APP_ENV=production

DB_HOST=localhost
DB_NAME=iscan_db
DB_USER=your_production_user
DB_PASS=your_strong_password_here

# Security settings (adjust as needed)
SESSION_TIMEOUT=3600
MAX_LOGIN_ATTEMPTS=5
MIN_PASSWORD_LENGTH=8
```

### Step 3: Secure Database

```sql
-- Create dedicated database user
CREATE USER 'iscan_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON iscan_db.* TO 'iscan_user'@'localhost';
FLUSH PRIVILEGES;

-- Remove default admin (after creating new admin user)
DELETE FROM users WHERE username = 'admin' AND id = 1;
```

### Step 4: Set File Permissions

```bash
# Make .env read-only
chmod 600 .env

# Secure directories
chmod 755 uploads/
chmod 755 logs/

# Make application files read-only
chmod 644 *.php
chmod 644 includes/*.php
```

### Step 5: Enable HTTPS

1. Install SSL certificate
2. Configure Apache/Nginx for HTTPS
3. System will auto-detect and enable:
   - Secure cookies
   - HSTS headers
   - HTTPS enforcement

### Step 6: Test Everything

- [ ] Login works
- [ ] CSRF tokens present in forms
- [ ] Rate limiting blocks after 5 attempts
- [ ] Session timeout works
- [ ] File uploads work
- [ ] All security headers present
- [ ] HTTPS redirects working

---

## 🔧 Configuration Options

### Disable Features (Not Recommended)

If you need to disable security features (development only):

**Disable CSRF Protection:**
```env
ENABLE_CSRF_PROTECTION=false
```

**Disable Rate Limiting:**
```env
ENABLE_RATE_LIMITING=false
```

**Increase Session Timeout:**
```env
SESSION_TIMEOUT=7200  # 2 hours
```

**Relax Password Requirements:**
```env
MIN_PASSWORD_LENGTH=6
REQUIRE_PASSWORD_COMPLEXITY=false
```

---

## 📊 Monitoring

### Check Security Logs

```sql
-- Recent security events
SELECT event_type, severity, details, ip_address, created_at
FROM security_logs
ORDER BY created_at DESC
LIMIT 50;

-- Failed login attempts
SELECT * FROM security_logs
WHERE event_type = 'LOGIN_FAILED'
ORDER BY created_at DESC;

-- Rate limit violations
SELECT * FROM security_logs
WHERE event_type = 'RATE_LIMIT_EXCEEDED'
ORDER BY created_at DESC;
```

### Check Rate Limits

```sql
-- Current rate limits
SELECT identifier, ip_address, created_at
FROM rate_limits
ORDER BY created_at DESC;

-- Clear all rate limits (if needed)
DELETE FROM rate_limits;
```

---

## 🐛 Troubleshooting

### Problem: "CSRF token validation failed"

**Fix:**
```php
// Ensure form has CSRF token
<?php echo csrfTokenField(); ?>

// OR for AJAX, add meta tag to page:
<?php echo csrfTokenMeta(); ?>
```

### Problem: Locked out after failed logins

**Fix:**
```sql
-- Clear rate limits for specific user
DELETE FROM rate_limits WHERE identifier LIKE 'login_username%';

-- OR clear all
DELETE FROM rate_limits;
```

### Problem: Session timeout too short

**Fix:**
```env
# Increase in .env
SESSION_TIMEOUT=7200  # 2 hours
```

### Problem: Can't upload files

**Fix:**
```env
# Increase limit in .env
MAX_FILE_SIZE=10485760  # 10MB

# Check uploads/ folder permissions
chmod 755 uploads/
```

---

## 🔒 Security Checklist

### For Localhost (Development)
- [x] Run security migration
- [x] Test basic features
- [ ] Review security logs periodically

### For Production
- [ ] Create `.env` file with strong passwords
- [ ] Set `APP_ENV=production`
- [ ] Create dedicated database user
- [ ] Remove default admin account
- [ ] Enable HTTPS/SSL
- [ ] Set proper file permissions
- [ ] Test all security features
- [ ] Setup monitoring/logging
- [ ] Configure automated backups
- [ ] Review security logs weekly

---

## 📞 Need Help?

1. Check [SECURITY.md](SECURITY.md) for detailed documentation
2. Review security logs: `SELECT * FROM security_logs ORDER BY created_at DESC;`
3. Check error logs: `/logs/php_errors.log`
4. Verify `.env` configuration
5. Test with `APP_ENV=development` to see detailed errors

---

## 🎯 What's Protected

| Feature | Localhost | Production |
|---------|-----------|------------|
| SQL Injection | ✅ Protected | ✅ Protected |
| XSS | ✅ Protected | ✅ Protected |
| CSRF | ✅ Protected | ✅ Protected |
| Rate Limiting | ✅ Enabled | ✅ Enabled |
| Session Security | ✅ Enabled | ✅ Enhanced |
| Password Hashing | ✅ Bcrypt | ✅ Bcrypt |
| Input Validation | ✅ Enabled | ✅ Enabled |
| Security Headers | ✅ Basic | ✅ Full (with HTTPS) |
| Audit Logging | ✅ Enabled | ✅ Enabled |
| File Upload Security | ✅ Enabled | ✅ Enabled |

---

**You're all set! Your application is now secured with industry-standard security practices.**

**Version:** 1.0.0
**Last Updated:** January 18, 2026
