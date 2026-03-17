# 🚀 Security Quick Start

## ⚡ 60-Second Setup (Localhost)

### Step 1: Run Migration (Choose ONE method)

**Method A: Using Browser**
```
http://localhost/iscan/database/run_security_migration.php
```

**Method B: Using phpMyAdmin**
1. Go to http://localhost/phpmyadmin
2. Select `iscan_db`
3. Click "Import"
4. Choose `database/security_tables_migration.sql`
5. Click "Go"

**Method C: Using MySQL Command**
```bash
cd c:\xampp\htdocs\iscan
mysql -u root -p iscan_db < database\security_tables_migration.sql
```

### Step 2: Test
Visit http://localhost/iscan/public/login.php

**That's it! You now have:**
- ✅ CSRF Protection
- ✅ Rate Limiting (5 attempts per 5 minutes)
- ✅ Session Timeout (1 hour)
- ✅ Security Logging
- ✅ All security headers

---

## 🧪 Quick Tests

### Test 1: CSRF Protection
1. Login page now has hidden CSRF token
2. View page source: Look for `<input type="hidden" name="csrf_token"`

### Test 2: Rate Limiting
1. Try logging in with wrong password 6 times
2. After 5 attempts, you'll be locked out
3. Message shows: "Try again in X minutes"

**To unlock yourself:**
```sql
DELETE FROM rate_limits WHERE identifier LIKE 'login_%';
```

### Test 3: Session Timeout
1. Login successfully
2. Wait 1 hour (or change SESSION_TIMEOUT to 60 in .env for testing)
3. Try to navigate - should redirect to login

### Test 4: Security Logging
```sql
SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 10;
```
Should show your login attempts!

---

## 🔧 Common Adjustments

### Change Session Timeout
Create `.env` file:
```env
SESSION_TIMEOUT=7200  # 2 hours
```

### Disable CSRF (NOT recommended)
```env
ENABLE_CSRF_PROTECTION=false
```

### Disable Rate Limiting (NOT recommended)
```env
ENABLE_RATE_LIMITING=false
```

### Change Login Attempts
```env
MAX_LOGIN_ATTEMPTS=10
RATE_LIMIT_WINDOW=600  # 10 minutes
```

---

## 📊 Useful Queries

**View recent security events:**
```sql
SELECT event_type, severity, details, ip_address, created_at
FROM security_logs
ORDER BY created_at DESC
LIMIT 20;
```

**Check who's rate limited:**
```sql
SELECT identifier, ip_address, created_at
FROM rate_limits
ORDER BY created_at DESC;
```

**Clear all rate limits:**
```sql
DELETE FROM rate_limits;
```

**Failed login attempts:**
```sql
SELECT * FROM security_logs
WHERE event_type = 'LOGIN_FAILED'
ORDER BY created_at DESC;
```

---

## 🐛 Troubleshooting

### Problem: Migration fails
**Solution:** Check if tables already exist
```sql
SHOW TABLES LIKE 'rate_limits';
SHOW TABLES LIKE 'security_logs';
```

### Problem: "CSRF token validation failed"
**Solution:**
- Refresh the page
- Clear browser cookies
- Check if form has `<?php echo csrfTokenField(); ?>`

### Problem: Locked out
**Solution:**
```sql
DELETE FROM rate_limits WHERE identifier LIKE 'login_yourusername%';
```

### Problem: Session timeout too fast
**Solution:** Create `.env`:
```env
SESSION_TIMEOUT=7200  # 2 hours
```

---

## 📚 Full Documentation

- **[SECURITY.md](SECURITY.md)** - Complete security documentation
- **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)** - Deployment instructions
- **[SECURITY_IMPLEMENTATION_SUMMARY.md](SECURITY_IMPLEMENTATION_SUMMARY.md)** - What was implemented

---

## ✅ Security Checklist

### Localhost
- [x] Run migration
- [x] Test login
- [x] Verify CSRF tokens
- [x] Test rate limiting

### Production
- [ ] Create `.env` with strong passwords
- [ ] Set `APP_ENV=production`
- [ ] Enable HTTPS
- [ ] Secure file permissions
- [ ] Remove default admin
- [ ] Test all security features

---

## 🎯 What's Protected

| Feature | Status |
|---------|--------|
| SQL Injection | ✅ Protected (PDO prepared statements) |
| XSS | ✅ Protected (sanitization + CSP) |
| CSRF | ✅ Protected (token validation) |
| Brute Force | ✅ Protected (rate limiting) |
| Session Hijacking | ✅ Protected (HttpOnly, timeout) |
| Password Security | ✅ Protected (bcrypt hashing) |
| File Uploads | ✅ Protected (MIME validation) |
| Audit Trail | ✅ Complete (security_logs) |

---

**You're all set! 🎉**

For questions, check the full documentation or review security_logs table.
