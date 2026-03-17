# Security Implementation Guide
## iSCAN - Civil Registry Document Management System

This document outlines all security features implemented in the system and how to configure them for both development (localhost) and production environments.

---

## Table of Contents

1. [Security Features Implemented](#security-features-implemented)
2. [Installation & Setup](#installation--setup)
3. [Environment Configuration](#environment-configuration)
4. [Security Features Details](#security-features-details)
5. [Production Deployment](#production-deployment)
6. [Security Best Practices](#security-best-practices)
7. [Troubleshooting](#troubleshooting)

---

## Security Features Implemented

### ✅ Core Security Features

1. **SQL Injection Protection**
   - All database queries use PDO prepared statements
   - Parameter binding for all user inputs
   - No dynamic SQL concatenation

2. **Cross-Site Scripting (XSS) Prevention**
   - Input sanitization using `htmlspecialchars()` with `ENT_QUOTES`
   - Output encoding in all HTML contexts
   - Content Security Policy (CSP) headers

3. **Cross-Site Request Forgery (CSRF) Protection**
   - CSRF tokens for all POST requests
   - Token validation on forms and AJAX requests
   - Automatic token regeneration

4. **Password Security**
   - Bcrypt hashing using `password_hash()` with `PASSWORD_DEFAULT`
   - Minimum 8-character password requirement (configurable)
   - Password strength checking
   - Secure password verification with `password_verify()`

5. **Session Management**
   - HttpOnly cookies (prevents JavaScript access)
   - Secure flag for HTTPS (auto-detected)
   - SameSite=Strict cookie policy
   - Configurable session timeout (default: 1 hour)
   - Automatic session regeneration every 30 minutes
   - Session expiration with auto-logout

6. **Rate Limiting**
   - Login attempt limiting (default: 5 attempts per 5 minutes)
   - IP-based tracking
   - Automatic lockout with countdown timer
   - Configurable time windows

7. **Role-Based Access Control (RBAC)**
   - Three roles: Admin, Encoder, Viewer
   - Granular permission system
   - Permission checking on all endpoints
   - Admin-only functions

8. **Audit Logging**
   - Activity logs for all user actions
   - Security event logging
   - IP address and user agent tracking
   - Timestamp tracking for all actions

9. **File Upload Security**
   - File type validation (PDF only by default)
   - MIME type verification using `finfo_*`
   - File size limits (default: 5MB)
   - Unique filename generation
   - Upload directory permissions

10. **Security Headers**
    - X-Frame-Options: SAMEORIGIN
    - X-XSS-Protection: 1; mode=block
    - X-Content-Type-Options: nosniff
    - Referrer-Policy: strict-origin-when-cross-origin
    - Content-Security-Policy (configurable)
    - Strict-Transport-Security (HTTPS only)
    - Permissions-Policy

11. **Environment-Based Configuration**
    - Support for .env files
    - Separate development/production configs
    - Sensitive data not in version control
    - Easy configuration management

12. **Enhanced Input Validation**
    - Type-specific validation (username, email, date, integer, etc.)
    - Regex pattern matching
    - Enum value validation
    - Range checking for numbers

---

## Installation & Setup

### Step 1: Run Database Migration

Execute the security tables migration to add required tables:

```bash
# Navigate to your database management tool (phpMyAdmin, MySQL Workbench, etc.)
# Run the SQL file:
```

**Using MySQL Command Line:**
```bash
cd c:\xampp\htdocs\iscan
mysql -u root -p iscan_db < database\security_tables_migration.sql
```

**Using phpMyAdmin:**
1. Go to http://localhost/phpmyadmin
2. Select `iscan_db` database
3. Click "Import" tab
4. Choose file: `database\security_tables_migration.sql`
5. Click "Go"

This will create:
- `rate_limits` table
- `security_logs` table
- Additional columns in `users` table

### Step 2: Update .gitignore

Ensure your `.gitignore` file includes:

```
.env
/logs/*.log
/uploads/*
!.gitkeep
```

### Step 3: Configure Environment

#### For Localhost Development (Default)

**No configuration needed!** The system will work out of the box with default values:
- Database: localhost, root, (no password), iscan_db
- CSRF Protection: Enabled
- Rate Limiting: Enabled
- Session Timeout: 1 hour
- All security features enabled but optimized for development

#### For Production Deployment

1. **Copy the example environment file:**
```bash
copy .env.example .env
```

2. **Edit `.env` file with production values:**

```env
# Set to production mode
APP_ENV=production

# Database credentials (CHANGE THESE!)
DB_HOST=localhost
DB_NAME=iscan_db
DB_USER=your_db_user
DB_PASS=your_strong_password_here

# Security settings
SESSION_TIMEOUT=3600
ENABLE_CSRF_PROTECTION=true
ENABLE_RATE_LIMITING=true
MAX_LOGIN_ATTEMPTS=5
RATE_LIMIT_WINDOW=300
ACCOUNT_LOCKOUT_DURATION=900

# Password policy
MIN_PASSWORD_LENGTH=8
REQUIRE_PASSWORD_COMPLEXITY=false
PASSWORD_EXPIRY_DAYS=90

# File uploads
MAX_FILE_SIZE=5242880
ALLOWED_FILE_TYPES=pdf

# Security headers
ENABLE_HSTS=true
ENABLE_CSP=true
```

---

## Environment Configuration

### Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | development | Environment mode: `development` or `production` |
| `DB_HOST` | localhost | Database host |
| `DB_NAME` | iscan_db | Database name |
| `DB_USER` | root | Database username |
| `DB_PASS` | (empty) | Database password |
| `SESSION_TIMEOUT` | 3600 | Session timeout in seconds (3600 = 1 hour) |
| `ENABLE_CSRF_PROTECTION` | true | Enable CSRF token validation |
| `ENABLE_RATE_LIMITING` | true | Enable rate limiting |
| `MAX_LOGIN_ATTEMPTS` | 5 | Maximum login attempts before lockout |
| `RATE_LIMIT_WINDOW` | 300 | Rate limit time window (300 = 5 minutes) |
| `ACCOUNT_LOCKOUT_DURATION` | 900 | Account lockout duration (900 = 15 minutes) |
| `MIN_PASSWORD_LENGTH` | 8 | Minimum password length |
| `REQUIRE_PASSWORD_COMPLEXITY` | false | Require complex passwords |
| `PASSWORD_EXPIRY_DAYS` | 90 | Days before password expires (0 = never) |
| `MAX_FILE_SIZE` | 5242880 | Max file upload size in bytes (5MB) |
| `ALLOWED_FILE_TYPES` | pdf | Allowed file extensions |
| `ENABLE_HSTS` | true | Enable HSTS header (HTTPS only) |
| `ENABLE_CSP` | true | Enable Content Security Policy |
| `TIMEZONE` | Asia/Manila | Application timezone |

### Development vs Production

**Development Mode (`APP_ENV=development`):**
- Displays detailed errors
- Less strict security (for localhost testing)
- HTTPS not enforced
- Detailed logging

**Production Mode (`APP_ENV=production`):**
- Hides error details from users
- Enforces all security features
- HTTPS recommended
- Streamlined logging

---

## Security Features Details

### 1. CSRF Protection

**How it works:**
- A unique token is generated per session
- Token is embedded in all forms
- Token is validated on POST requests

**Usage in Forms:**
```php
<form method="POST" action="">
    <?php echo csrfTokenField(); ?>
    <!-- form fields -->
</form>
```

**Usage in AJAX:**
```javascript
// Add to page header
<?php echo csrfTokenMeta(); ?>

// In AJAX request
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: formData
});
```

**Disable for specific pages:**
Set `ENABLE_CSRF_PROTECTION=false` in `.env` (not recommended)

### 2. Rate Limiting

**How it works:**
- Tracks login attempts per username + IP address
- Blocks requests after MAX_LOGIN_ATTEMPTS
- Automatically unlocks after RATE_LIMIT_WINDOW seconds

**Default settings:**
- 5 attempts allowed
- 5-minute lockout window
- Automatic cleanup of old entries

**Customization:**
```php
// In your code
$result = checkRateLimit($identifier, $max_attempts, $time_window);
if (!$result['allowed']) {
    echo $result['message']; // "Too many attempts. Try again in X minutes."
}

// Clear after successful action
clearRateLimit($identifier);
```

### 3. Session Timeout

**How it works:**
- Tracks last activity timestamp
- Automatically logs out after SESSION_TIMEOUT seconds of inactivity
- Regenerates session ID every 30 minutes

**User experience:**
- After timeout, user is redirected to login with message
- Clean session termination
- No data leakage

### 4. Security Logging

**Events logged:**
- LOGIN_SUCCESS / LOGIN_FAILED
- CSRF_VALIDATION_FAILED
- RATE_LIMIT_EXCEEDED
- SUSPICIOUS_ACTIVITY
- All user actions via activity_logs

**Log levels:**
- LOW: Normal operations
- MEDIUM: Security warnings
- HIGH: Serious security issues
- CRITICAL: System-level threats

**Viewing logs:**
Access via Admin panel → Error Log Viewer (admin only)

### 5. Password Policy

**Default requirements:**
- Minimum 8 characters
- No dictionary/common passwords
- Bcrypt hashing

**Optional complexity requirements:**
Enable `REQUIRE_PASSWORD_COMPLEXITY=true` for:
- Uppercase letters
- Lowercase letters
- Numbers
- Special characters

**Password strength indicator:**
```php
$strength = checkPasswordStrength($password);
// Returns: ['score' => 0-6, 'level' => 'weak|medium|strong', 'feedback' => [...]]
```

### 6. Input Validation

**Built-in validators:**
```php
// Username validation
$result = validateInput($username, 'username');

// Email validation
$result = validateInput($email, 'email');

// Password validation
$result = validateInput($password, 'password', ['min_length' => 8]);

// Integer with range
$result = validateInput($age, 'integer', ['min' => 18, 'max' => 100]);

// Date validation
$result = validateInput($date, 'date', ['format' => 'Y-m-d']);

// Enum validation
$result = validateInput($role, 'enum', ['allowed' => ['Admin', 'Encoder', 'Viewer']]);

if (!$result['valid']) {
    echo $result['error'];
} else {
    $clean_value = $result['value'];
}
```

---

## Production Deployment

### Pre-Deployment Checklist

- [ ] Create `.env` file with production values
- [ ] Set `APP_ENV=production`
- [ ] Use strong database password
- [ ] Run security migrations
- [ ] Configure HTTPS/SSL certificate
- [ ] Test all features in staging
- [ ] Review all default admin accounts
- [ ] Set proper file permissions
- [ ] Configure backups
- [ ] Enable error logging
- [ ] Test session timeout
- [ ] Verify CSRF protection
- [ ] Test rate limiting

### File Permissions

```bash
# Recommended permissions for production

# Application files (read-only)
chmod 644 *.php
chmod 644 includes/*.php
chmod 644 api/*.php

# Directories
chmod 755 includes/
chmod 755 api/
chmod 755 public/
chmod 755 admin/

# Writable directories
chmod 755 uploads/
chmod 755 logs/

# Protect sensitive files
chmod 600 .env
chmod 600 includes/config.php
```

### Apache Configuration (.htaccess)

Create `.htaccess` in project root:

```apache
# Security Headers
<IfModule mod_headers.c>
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Protect sensitive files
<FilesMatch "^\.env$">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch "^(composer\.json|composer\.lock|\.git|\.gitignore)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable directory listing
Options -Indexes

# Force HTTPS (if available)
# Uncomment for production with SSL
# <IfModule mod_rewrite.c>
#     RewriteEngine On
#     RewriteCond %{HTTPS} off
#     RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
# </IfModule>
```

### Database Security

1. **Create dedicated database user:**
```sql
CREATE USER 'iscan_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON iscan_db.* TO 'iscan_user'@'localhost';
FLUSH PRIVILEGES;
```

2. **Update `.env`:**
```env
DB_USER=iscan_user
DB_PASS=strong_password_here
```

3. **Remove default admin account** (after creating new admin):
```sql
DELETE FROM users WHERE username = 'admin' AND id = 1;
```

### HTTPS Configuration

**For production, HTTPS is mandatory!**

1. Obtain SSL certificate (Let's Encrypt, commercial CA)
2. Configure Apache/Nginx for HTTPS
3. Update `.env`:
```env
BASE_URL=https://yourdomain.com/iscan/
```

4. System will auto-detect HTTPS and:
   - Set secure cookies
   - Enable HSTS header
   - Enforce secure connections

---

## Security Best Practices

### 1. Regular Updates

- [ ] Update PHP to latest stable version
- [ ] Update MySQL/MariaDB regularly
- [ ] Review and update dependencies
- [ ] Monitor security advisories

### 2. Monitoring

- [ ] Review security_logs table weekly
- [ ] Monitor failed login attempts
- [ ] Check for suspicious IP addresses
- [ ] Review activity_logs for anomalies

### 3. Backup Strategy

- [ ] Daily database backups
- [ ] Weekly file system backups
- [ ] Off-site backup storage
- [ ] Test restore procedures

### 4. Access Control

- [ ] Use principle of least privilege
- [ ] Regular user access audits
- [ ] Disable inactive accounts
- [ ] Strong password enforcement

### 5. Compliance

- [ ] Data privacy compliance (if applicable)
- [ ] Audit trail retention
- [ ] Secure file storage
- [ ] Access logging

---

## Troubleshooting

### Issue: "CSRF token validation failed"

**Solution:**
- Ensure form includes `<?php echo csrfTokenField(); ?>`
- Check if session is active
- Verify ENABLE_CSRF_PROTECTION is true
- Try refreshing the page

### Issue: "Too many login attempts"

**Solution:**
- Wait for the lockout period (default: 5 minutes)
- Check rate_limits table for stuck entries
- Adjust MAX_LOGIN_ATTEMPTS if needed
- Clear rate limit manually:
```sql
DELETE FROM rate_limits WHERE identifier = 'login_username_ipaddress';
```

### Issue: Session keeps timing out

**Solution:**
- Increase SESSION_TIMEOUT in .env
- Check server clock is synchronized
- Verify session files have write permissions
- Check session.gc_maxlifetime in php.ini

### Issue: Can't upload files

**Solution:**
- Check MAX_FILE_SIZE in .env
- Verify uploads/ directory permissions (755)
- Check php.ini: upload_max_filesize, post_max_size
- Ensure ALLOWED_FILE_TYPES includes desired type

### Issue: Database connection error

**Solution:**
- Verify .env database credentials
- Check MySQL service is running
- Test database connection manually
- Check DB_HOST (localhost vs 127.0.0.1)

### Issue: Security headers not working

**Solution:**
- Check if mod_headers is enabled (Apache)
- Verify setSecurityHeaders() is called
- Review browser console for CSP violations
- Adjust CSP policy if needed

---

## Security Audit Log

### Viewing Security Logs

**Via Database:**
```sql
SELECT * FROM security_logs
ORDER BY created_at DESC
LIMIT 100;
```

**Via Admin Panel:**
Admin → Error Log Viewer (filters available)

### Common Security Events

| Event Type | Severity | Description |
|------------|----------|-------------|
| LOGIN_SUCCESS | LOW | Successful login |
| LOGIN_FAILED | MEDIUM | Failed login attempt |
| CSRF_VALIDATION_FAILED | MEDIUM | CSRF token mismatch |
| RATE_LIMIT_EXCEEDED | MEDIUM | Too many requests |
| SUSPICIOUS_ACTIVITY | HIGH | Potential attack detected |
| UNAUTHORIZED_ACCESS | HIGH | Access without permission |

---

## Contact & Support

For security issues or concerns:
- **DO NOT** open public GitHub issues for security vulnerabilities
- Contact system administrator directly
- Review logs for detailed error information
- Check documentation before reporting

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-01-18 | Initial security implementation |

---

**Last Updated:** January 18, 2026
**Security Level:** Production-Ready
**Compliance:** Basic security best practices implemented
