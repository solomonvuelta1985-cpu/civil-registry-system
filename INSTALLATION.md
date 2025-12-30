# Quick Installation Guide - iScan

## 🚀 5-Minute Setup

Follow these simple steps to get iScan up and running on your local machine.

---

## Step 1: Start XAMPP

1. Open **XAMPP Control Panel**
2. Start **Apache** module
3. Start **MySQL** module
4. Ensure both are running (green indicators)

---

## Step 2: Create Database

### Option A: Using phpMyAdmin (Recommended)

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** in the left sidebar
3. Database name: `iscan_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click **"Create"**
6. Select `iscan_db` from the left sidebar
7. Click **"Import"** tab
8. Click **"Choose File"** and select `database_schema.sql`
9. Scroll down and click **"Go"**
10. Wait for success message

### Option B: Using MySQL Command Line

```bash
mysql -u root -p
CREATE DATABASE iscan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iscan_db;
SOURCE C:/xampp/htdocs/iscan/database_schema.sql;
EXIT;
```

---

## Step 3: Verify Installation

### Check Database Tables

In phpMyAdmin, you should see these tables in `iscan_db`:
- ✅ certificate_of_live_birth
- ✅ users
- ✅ activity_logs

### Check Views

- ✅ vw_active_certificates
- ✅ vw_certificate_statistics

---

## Step 4: Configure Application (Optional)

Only needed if your database credentials differ from defaults:

1. Open `includes/config.php`
2. Update these lines if needed:
   ```php
   define('DB_HOST', 'localhost');     // Usually 'localhost'
   define('DB_NAME', 'iscan_db');      // Database name
   define('DB_USER', 'root');          // Usually 'root' for XAMPP
   define('DB_PASS', '');              // Usually empty for XAMPP
   ```
3. Save the file

---

## Step 5: Set Folder Permissions

### Windows (XAMPP)

The default XAMPP setup usually has correct permissions. If you encounter upload issues:

1. Right-click `uploads/` folder
2. Properties → Security tab
3. Click "Edit"
4. Select "Users" or "Everyone"
5. Check "Full Control"
6. Click "Apply" → "OK"

### Linux/Mac

```bash
cd /path/to/iscan
chmod 755 uploads/
```

---

## Step 6: Access the Application

Open your browser and navigate to:

### 🎯 Main Entry Points

| Page | URL | Description |
|------|-----|-------------|
| **Dashboard** | `http://localhost/iscan/admin/dashboard.php` | Main dashboard with statistics |
| **Entry Form (PHP)** | `http://localhost/iscan/public/certificate_of_live_birth.php` | PHP form with edit capability |
| **Entry Form (HTML)** | `http://localhost/iscan/public/certificate_of_live_birth.html` | Static HTML form |

---

## Step 7: Test the System

### Create Your First Record

1. Go to: `http://localhost/iscan/public/certificate_of_live_birth.php`
2. Fill in the form with test data:
   - **Registry Number**: REG-2025-00001
   - **Date of Registration**: (auto-filled with current date/time)
   - **Type of Birth**: Single
   - **Mother's Name**: Maria Santos Cruz
   - **Father's Name**: Juan Reyes Dela Cruz
3. Upload a sample PDF file (any PDF under 10MB)
4. Click **"Save Record"**
5. You should see a success message

### View Records

1. Go to: `http://localhost/iscan/admin/dashboard.php`
2. You should see:
   - Statistics showing 1 total record
   - Your test record in the table
   - Edit button to modify the record

---

## ✅ Installation Complete!

If you can see the dashboard and create/view records, your installation is successful!

---

## 🐛 Troubleshooting

### Issue: "Database connection failed"

**Solution:**
- Check if MySQL is running in XAMPP
- Verify database credentials in `config.php`
- Ensure database `iscan_db` exists

### Issue: "Permission denied" when uploading PDF

**Solution:**
- Check folder permissions on `uploads/` directory
- On Windows: Give "Full Control" to Users
- On Linux/Mac: Run `chmod 755 uploads/`

### Issue: PDF preview not showing

**Solution:**
- Use a modern browser (Chrome, Firefox, Edge)
- Check if PDF file was actually uploaded to `uploads/` folder
- Try a different PDF file

### Issue: "Registry number already exists"

**Solution:**
- Each registry number must be unique
- Use a different registry number
- Or edit/delete the existing record with that number

### Issue: Page not found (404 Error)

**Solution:**
- Check that you're using the correct URL
- Verify all files are in `C:\xampp\htdocs\iscan\`
- Make sure Apache is running in XAMPP

---

## 🔧 System Requirements

### Minimum Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Apache**: 2.4 or higher
- **RAM**: 2GB
- **Disk Space**: 100MB (plus space for PDF files)
- **Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+

### Recommended Requirements
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **RAM**: 4GB or more
- **SSD**: For faster database operations

---

## 📞 Getting Help

If you encounter issues:

1. **Check the logs**:
   - Browser Console (F12 → Console tab)
   - Apache Error Log (`xampp/apache/logs/error.log`)
   - PHP Error Log (check php.ini for location)

2. **Review the README.md**:
   - Full documentation available in `README.md`
   - Includes detailed troubleshooting section

3. **Common Files to Check**:
   - `includes/config.php` - Database configuration
   - `uploads/` - PDF file storage
   - `database_schema.sql` - Database structure

---

## 🎉 Next Steps

After successful installation:

1. **Customize the system**:
   - Update site name in `config.php`
   - Modify form fields if needed
   - Add your organization's logo

2. **Add users** (optional):
   - Default admin: username `admin`, password `admin123`
   - Change the password immediately!
   - Add more users via SQL or create a user management page

3. **Configure backups**:
   - Set up automated database backups
   - Plan for PDF file backups
   - See README.md for backup commands

4. **Security hardening** (for production):
   - Change database password
   - Enable authentication (`includes/auth.php`)
   - Configure SSL certificate
   - Restrict file permissions
   - Enable error logging (disable display_errors)

---

## 📚 Additional Resources

- **Full Documentation**: `README.md`
- **Database Schema**: `database_schema.sql`
- **API Documentation**: See README.md → API Endpoints section

---

**Installation Complete!** 🎊

You're now ready to start managing Certificate of Live Birth records with iScan!

---

**Need Help?** Contact your system administrator or refer to the comprehensive `README.md` file.
