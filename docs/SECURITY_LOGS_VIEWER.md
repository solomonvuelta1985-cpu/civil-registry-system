# Security Logs Viewer - Admin Feature

## 🎯 Overview

A comprehensive security monitoring dashboard for administrators to view, filter, and analyze all security events in the iSCAN system.

---

## ✨ Features

### 📊 Real-Time Statistics
- **Total Events Count** - Overall number of security events
- **Severity Breakdown** - Events categorized by LOW, MEDIUM, HIGH, CRITICAL
- **Color-Coded Stats** - Visual indicators for quick assessment

### 🚨 Security Alerts
- **Failed Login Attempts** - Shows recent failed logins from the last 24 hours
- **IP Address Tracking** - Identifies suspicious IPs with multiple failed attempts
- **Real-Time Warnings** - Highlights potential security threats

### 🔍 Advanced Filtering
- **Severity Filter** - Filter by LOW, MEDIUM, HIGH, CRITICAL
- **Event Type Filter** - Filter by specific events (LOGIN_SUCCESS, LOGIN_FAILED, etc.)
- **Search Function** - Search by IP address or event details
- **Date Range** - Filter events by date range (from/to)
- **Configurable Page Size** - Show 25, 50, 100, or 250 events per page

### 📋 Event Log Display
- **Timestamp** - Exact date and time of each event
- **Event Type** - Clear event categorization
- **Severity Badge** - Color-coded severity indicators
- **User Information** - Shows username and full name if authenticated
- **IP Address** - Tracks source IP for each event
- **Details** - Full event description

### 🗂️ Pagination
- **Smart Pagination** - Navigate through large log files easily
- **First/Last Navigation** - Jump to beginning or end
- **Page Range Display** - Shows nearby page numbers
- **Preserves Filters** - Maintains filter settings during pagination

---

## 🎨 Visual Design

### Color Coding
- **LOW (Green)** - #d4edda - Normal operations
- **MEDIUM (Yellow)** - #fff3cd - Security warnings
- **HIGH (Orange)** - #fff4e5 - Serious issues
- **CRITICAL (Red)** - #f8d7da - Critical threats

### Modern UI
- Clean, professional interface
- Responsive design
- Gradient header with purple theme
- Hover effects on interactive elements
- Card-based layout
- Professional typography

---

## 📍 Access

### URL
```
http://localhost/iscan/admin/security_logs.php
```

### Navigation
- **Sidebar Menu** → System → Security Logs
- **Icon:** Shield with alert symbol

### Permissions
- **Admin Only** - Requires admin role to access
- Automatically checks user permissions
- Redirects unauthorized users to 403 page

---

## 🎯 Use Cases

### 1. Monitor Failed Login Attempts
**Purpose:** Identify brute-force attacks or unauthorized access attempts

**How to:**
1. Go to Security Logs page
2. Check "Recent Failed Login Attempts" section
3. Review IP addresses with multiple failures
4. Take action if suspicious patterns detected

**Example Alert:**
```
⚠️ 8 failed attempts from IP: 192.168.1.100
(Last: January 18, 2026 2:30 PM)
```

### 2. Track User Login Activity
**Purpose:** Audit successful logins and user access patterns

**How to:**
1. Select Event Type: "LOGIN_SUCCESS"
2. Choose date range
3. Review user login times and IPs
4. Verify legitimate access patterns

### 3. Investigate Security Incidents
**Purpose:** Research specific security events or timeframes

**How to:**
1. Set Date From/To for incident period
2. Filter by Severity: HIGH or CRITICAL
3. Review all events in timeframe
4. Export findings if needed

### 4. Identify CSRF Attacks
**Purpose:** Detect potential CSRF attack attempts

**How to:**
1. Filter Event Type: "CSRF_VALIDATION_FAILED"
2. Check IP addresses
3. Review details for patterns
4. Block malicious IPs if needed

### 5. Monitor Rate Limit Violations
**Purpose:** Track users/IPs hitting rate limits

**How to:**
1. Filter Event Type: "RATE_LIMIT_EXCEEDED"
2. Review frequency and IPs
3. Adjust rate limits if needed
4. Block persistent offenders

---

## 📊 Event Types Logged

| Event Type | Severity | Description |
|------------|----------|-------------|
| `LOGIN_SUCCESS` | LOW | Successful user login |
| `LOGIN_FAILED` | MEDIUM | Failed login attempt |
| `CSRF_VALIDATION_FAILED` | MEDIUM | CSRF token mismatch |
| `RATE_LIMIT_EXCEEDED` | MEDIUM | Too many requests |
| `SUSPICIOUS_ACTIVITY` | HIGH | Potential attack detected |
| `UNAUTHORIZED_ACCESS` | HIGH | Access without permission |
| Custom Events | Varies | Additional security events |

---

## 🔧 Technical Details

### Database Query
- Uses prepared statements (SQL injection safe)
- Efficient indexing on event_type, severity, created_at
- Joins with users table for username display
- Supports complex WHERE clauses for filtering

### Performance
- Pagination limits database load
- Indexed columns for fast queries
- Configurable page sizes
- Efficient COUNT queries for statistics

### Security
- Admin-only access enforcement
- Input sanitization on all filters
- No sensitive data exposure
- Prepared statement protection

---

## 💡 Tips & Best Practices

### Daily Monitoring
✅ Check failed login attempts daily
✅ Review CRITICAL severity events immediately
✅ Look for unusual IP patterns
✅ Monitor rate limit violations

### Weekly Review
✅ Analyze login patterns
✅ Check for new event types
✅ Review severity distribution
✅ Clean old logs if needed (via database)

### Security Response
✅ Investigate CRITICAL events immediately
✅ Block IPs with excessive failed logins
✅ Monitor for CSRF patterns
✅ Document security incidents

### Filter Best Practices
✅ Start broad, then narrow filters
✅ Use date range for performance
✅ Combine filters for specific searches
✅ Clear filters to see full picture

---

## 📈 Statistics Explained

### Total Events
- Shows count of all security events
- Reflects system security activity level
- Higher = More active monitoring

### Severity Counts
- **LOW** - Normal operations (most common)
- **MEDIUM** - Worth reviewing but not urgent
- **HIGH** - Requires attention soon
- **CRITICAL** - Immediate action needed

### Failed Login Alerts
- Shows last 24 hours only
- Groups by IP address
- Sorts by attempt count (highest first)
- Includes timestamp of last attempt

---

## 🎬 Quick Start Guide

### View All Recent Events
1. Navigate to **System → Security Logs**
2. Events displayed newest first
3. Scroll through paginated results

### Find Failed Logins Today
1. Set **Date From** to today's date
2. Select **Event Type**: LOGIN_FAILED
3. Click **Apply Filters**

### Monitor Critical Events
1. Select **Severity**: CRITICAL
2. Click **Apply Filters**
3. Review all critical events

### Search by IP Address
1. Enter IP in **Search** field
2. Click **Apply Filters**
3. See all events from that IP

---

## 🛠️ Customization Options

### Modify Per Page Options
Edit [security_logs.php](admin/security_logs.php) line ~19:
```php
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
```

### Add More Event Types
Add to filter dropdown by modifying the event_types query

### Customize Colors
Edit the CSS severity classes:
```css
.severity-LOW { color: #28a745; }
.severity-MEDIUM { color: #ffc107; }
.severity-HIGH { color: #fd7e14; }
.severity-CRITICAL { color: #dc3545; }
```

### Adjust Failed Login Alert
Change the time window (currently 24 hours):
```php
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```

---

## 🐛 Troubleshooting

### No Events Showing
**Solution:**
- Verify security_logs table exists: `SHOW TABLES LIKE 'security_logs';`
- Check if migration ran successfully
- Try logging in to generate events

### Filters Not Working
**Solution:**
- Clear all filters and retry
- Check date format (YYYY-MM-DD)
- Verify event type exists in database

### Page Loads Slowly
**Solution:**
- Reduce per page count to 25 or 50
- Use date range to limit results
- Add more specific filters

### Permission Denied
**Solution:**
- Ensure logged in as Admin
- Check user role in database
- Verify `requireAdmin()` is not blocking

---

## 📱 Responsive Design

- ✅ Desktop optimized (1600px max width)
- ✅ Tablet compatible
- ✅ Mobile friendly (grid adjusts automatically)
- ✅ Touch-friendly buttons and links

---

## 🔗 Related Features

- **[User Management](admin/users.php)** - Manage user accounts
- **[Error Log Viewer](admin/error_log_viewer.php)** - View PHP errors
- **[Dashboard](admin/dashboard.php)** - System overview
- **[Activity Logs](database)** - General activity tracking

---

## 🎓 For Developers

### Add Custom Security Event
```php
logSecurityEvent(
    'CUSTOM_EVENT_TYPE',
    'MEDIUM',
    'Description of what happened',
    $user_id // optional
);
```

### Query Security Logs Directly
```sql
SELECT * FROM security_logs
WHERE event_type = 'LOGIN_FAILED'
AND severity = 'HIGH'
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```

### Clear Old Logs (Maintenance)
```sql
-- Delete logs older than 90 days
DELETE FROM security_logs
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## ✅ What You Get

✨ **Real-time security monitoring**
✨ **Failed login tracking**
✨ **Advanced filtering capabilities**
✨ **Severity-based alerts**
✨ **IP address tracking**
✨ **User activity logging**
✨ **Professional UI/UX**
✨ **Responsive design**
✨ **Admin-only access**
✨ **Fast database queries**

---

## 🚀 Next Steps

1. ✅ **Access the page:** http://localhost/iscan/admin/security_logs.php
2. ✅ **Review current events:** Check what's been logged
3. ✅ **Test filtering:** Try different filter combinations
4. ✅ **Monitor daily:** Make it part of your admin routine
5. ✅ **Respond to threats:** Take action on suspicious activity

---

**Created:** January 18, 2026
**Version:** 1.0.0
**Status:** ✅ Production Ready
**Access Level:** Admin Only
