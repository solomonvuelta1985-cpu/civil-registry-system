# iScan - Certificate of Live Birth Management System

## 📋 Overview

**iScan** is a comprehensive web-based Civil Registry Records Management System designed to digitize and manage Certificate of Live Birth records. The system allows users to scan PDF certificates and extract metadata into a structured database for easy retrieval and management.

---

## ✨ Features

- **Two-Column Layout**: Entry form on the left, PDF preview on the right
- **Comprehensive Data Entry**: Capture all essential birth certificate information
- **PDF Upload & Preview**: Upload and preview PDF certificates in real-time
- **Database Integration**: Store all records in MySQL database
- **Clean, Professional Design**: Following flat design principles with excellent UX
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **CRUD Operations**: Create, Read, Update, and Delete records
- **Validation**: Client-side and server-side validation
- **Activity Logging**: Track all system activities
- **Statistics Dashboard**: View key metrics and statistics

---

## 🗂️ Folder Structure

```
iscan/
│
├── 📁 public/                              # Public-facing files
│   ├── 📄 certificate_of_live_birth.html   # Static HTML form
│   ├── 📄 certificate_of_live_birth.php    # PHP-enabled form (with edit mode)
│   └── 📁 assets/                          # CSS, JS, Images
│
├── 📁 includes/                            # Backend includes
│   ├── ⚙️ config.php                      # Database configuration (PDO)
│   ├── ⚙️ functions.php                   # Helper functions
│   ├── 🔐 auth.php                        # Authentication (optional)
│   └── 📄 header.php/footer.php          # Common templates
│
├── 📁 api/                                 # API endpoints
│   ├── 📄 certificate_of_live_birth_save.php      # Save new record
│   ├── 📄 certificate_of_live_birth_update.php    # Update existing record
│   └── 📄 certificate_of_live_birth_delete.php    # Delete record
│
├── 📁 admin/                               # Admin dashboard
│   └── 📄 dashboard.php                   # Main dashboard
│
├── 📁 templates/                           # HTML templates
│   └── 📄 layout.html                     # Base layout
│
├── 📁 uploads/                             # Uploaded PDF files
│   └── (generated PDF files)
│
├── 📄 database_schema.sql                 # Database schema
└── 📄 README.md                           # This file
```

---

## 🚀 Installation Guide

### Prerequisites

- **Web Server**: Apache (XAMPP, WAMP, LAMP, or similar)
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Browser**: Modern browser (Chrome, Firefox, Safari, Edge)

### Step 1: Setup Database

1. Open **phpMyAdmin** or MySQL command line
2. Import the database schema:
   ```bash
   mysql -u root -p < database_schema.sql
   ```
   Or manually run the SQL file in phpMyAdmin

3. The schema will create:
   - Database: `iscan_db`
   - Tables: `certificate_of_live_birth`, `users`, `activity_logs`
   - Views: `vw_active_certificates`, `vw_certificate_statistics`
   - Default admin user (username: `admin`, password: `admin123`)

### Step 2: Configure Database Connection

1. Open `includes/config.php`
2. Update database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'iscan_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

### Step 3: Set File Permissions

Ensure the `uploads/` folder has write permissions:

**On Linux/Mac:**
```bash
chmod 755 uploads/
```

**On Windows (XAMPP):**
- Right-click `uploads` folder → Properties → Security
- Give "Full Control" to the web server user

### Step 4: Access the Application

Open your browser and navigate to:

- **Dashboard**: `http://localhost/iscan/admin/dashboard.php`
- **Entry Form (HTML)**: `http://localhost/iscan/public/certificate_of_live_birth.html`
- **Entry Form (PHP)**: `http://localhost/iscan/public/certificate_of_live_birth.php`

---

## 📝 Form Fields

### Registry Information
- **Registry Number** (Required): Unique identifier for the certificate
- **Date of Registration** (Required): Date and time of registration

### Birth Information
- **Type of Birth** (Required): Single, Twin, Triplets, Quadruplets, Other
- **Birth Order**: 1st, 2nd, 3rd, 4th, 5th, 6th, 7th, Other

### Mother's Maiden Name (Required)
- First Name
- Middle Name
- Last Name

### Father's Name
- First Name
- Middle Name
- Last Name

### Marriage Information
- Date of Marriage
- Place of Marriage

### PDF Certificate (Required)
- Upload scanned PDF certificate (Max 10MB)

---

## 🎨 Design Specifications

### Color Palette

| Color Name    | Hex Code | Usage                |
|---------------|----------|----------------------|
| Primary Blue  | #0d6efd  | Primary actions      |
| Success Green | #198754  | Positive actions     |
| Info Cyan     | #0dcaf0  | View, information    |
| Warning Yellow| #ffc107  | Edit, caution        |
| Danger Red    | #dc3545  | Delete, danger       |
| Secondary Gray| #6c757d  | Archive, secondary   |
| Off-White     | #f5f5f5  | Page background      |
| White         | #ffffff  | Cards, backgrounds   |

### Typography

- **Font Family**: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif
- **Page Title**: 1.5rem - 1.75rem, Semi-Bold (600)
- **Section Title**: 1.1rem - 1.25rem, Semi-Bold (600)
- **Body Text**: 0.875rem - 0.9rem, Regular (400)

### Design Principles

- **Flat Design**: No gradients, solid colors, minimal shadows
- **Clean & Professional**: Enterprise-appropriate aesthetics
- **Accessible**: WCAG 2.1 AA/AAA compliant
- **Responsive**: Mobile-first approach

---

## 🔧 API Endpoints

### 1. Save Certificate
- **Endpoint**: `api/certificate_of_live_birth_save.php`
- **Method**: POST
- **Content-Type**: multipart/form-data
- **Response**: JSON

**Example Response:**
```json
{
  "success": true,
  "message": "Certificate of Live Birth saved successfully! Registry No: REG-2025-00001",
  "data": {
    "id": 1,
    "registry_no": "REG-2025-00001"
  }
}
```

### 2. Update Certificate
- **Endpoint**: `api/certificate_of_live_birth_update.php`
- **Method**: POST
- **Content-Type**: multipart/form-data
- **Required**: `record_id`
- **Response**: JSON

### 3. Delete Certificate
- **Endpoint**: `api/certificate_of_live_birth_delete.php`
- **Method**: POST
- **Parameters**:
  - `record_id`: ID of the record to delete
  - `delete_type`: 'soft' (default) or 'hard'
- **Response**: JSON

**Soft Delete**: Changes status to 'Deleted' (record remains in database)
**Hard Delete**: Permanently removes record and associated PDF file

---

## 💾 Database Schema

### certificate_of_live_birth Table

| Field                  | Type         | Description                    |
|------------------------|--------------|--------------------------------|
| id                     | INT (PK)     | Auto-increment primary key     |
| registry_no            | VARCHAR(100) | Unique registry number         |
| date_of_registration   | DATETIME     | Registration date and time     |
| type_of_birth          | ENUM         | Single/Twin/Triplets/etc.      |
| type_of_birth_other    | VARCHAR(100) | Custom birth type              |
| birth_order            | ENUM         | 1st/2nd/3rd/etc.               |
| birth_order_other      | VARCHAR(50)  | Custom birth order             |
| mother_first_name      | VARCHAR(100) | Mother's first name            |
| mother_middle_name     | VARCHAR(100) | Mother's middle name           |
| mother_last_name       | VARCHAR(100) | Mother's last name             |
| father_first_name      | VARCHAR(100) | Father's first name            |
| father_middle_name     | VARCHAR(100) | Father's middle name           |
| father_last_name       | VARCHAR(100) | Father's last name             |
| date_of_marriage       | DATE         | Parents' marriage date         |
| place_of_marriage      | VARCHAR(255) | Marriage location              |
| pdf_filename           | VARCHAR(255) | Uploaded PDF filename          |
| pdf_filepath           | VARCHAR(500) | Full path to PDF file          |
| status                 | ENUM         | Active/Archived/Deleted        |
| created_at             | TIMESTAMP    | Record creation timestamp      |
| updated_at             | TIMESTAMP    | Last update timestamp          |
| created_by             | INT          | User who created record        |
| updated_by             | INT          | User who last updated record   |

---

## 🔒 Security Features

1. **Input Sanitization**: All user inputs are sanitized using `htmlspecialchars()`
2. **SQL Injection Prevention**: Using PDO prepared statements
3. **File Upload Validation**:
   - File type validation (PDF only)
   - File size limit (10MB)
   - MIME type verification
4. **XSS Protection**: Output escaping with `htmlspecialchars()`
5. **CSRF Protection**: Can be implemented using session tokens
6. **Error Logging**: All errors logged, user-friendly messages displayed

---

## 📊 Usage Guide

### Adding a New Record

1. Navigate to the entry form
2. Fill in all required fields (marked with *)
3. Upload PDF certificate
4. Click "Save Record" or "Save & Add New"
5. View success message and record details

### Editing a Record

1. Go to Dashboard
2. Click "Edit" button on the desired record
3. Modify fields as needed
4. Upload new PDF (optional - leave empty to keep existing)
5. Click "Update Record"

### Viewing Records

1. Access the Dashboard
2. View statistics at the top
3. Browse records in the table
4. Use search and filter options

### Deleting a Record

**Soft Delete** (Recommended):
- Record status changes to 'Deleted'
- Record can be restored later
- PDF file is retained

**Hard Delete** (Permanent):
- Record is permanently removed
- PDF file is deleted
- Cannot be undone

---

## 🐛 Troubleshooting

### PDF Upload Fails
- **Check**: File size is under 10MB
- **Check**: File is a valid PDF
- **Check**: `uploads/` folder has write permissions
- **Check**: PHP `upload_max_filesize` and `post_max_size` in php.ini

### Database Connection Error
- **Check**: Database credentials in `config.php`
- **Check**: MySQL service is running
- **Check**: Database `iscan_db` exists

### PDF Preview Not Showing
- **Check**: Browser supports iframe PDF preview
- **Check**: PDF file exists in `uploads/` folder
- **Check**: File path is correct in database

### Form Validation Errors
- **Check**: All required fields are filled
- **Check**: Registry number is unique
- **Check**: Date formats are correct

---

## 🔄 Backup & Maintenance

### Database Backup

**Export database:**
```bash
mysqldump -u root -p iscan_db > backup_$(date +%Y%m%d).sql
```

### File Backup

Regularly backup the `uploads/` folder containing PDF certificates.

### Log Maintenance

Review and clean `activity_logs` table periodically:
```sql
DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

---

## 📞 Support

For issues, questions, or feature requests:
- Check the documentation first
- Review error logs in browser console and server logs
- Contact system administrator

---

## 📜 License

This project is developed for Civil Registry Records Management.
All rights reserved.

---

## 🎊 Credits

**Developed By**: iScan Development Team
**Design System**: Based on Annex 2 Design Specifications
**Version**: 1.0.0
**Last Updated**: December 2025

---

## 🚧 Future Enhancements

- [ ] Advanced search and filtering
- [ ] Batch PDF upload
- [ ] OCR integration for automatic data extraction
- [ ] Export to Excel/CSV
- [ ] Print certificates
- [ ] User role management
- [ ] Audit trail
- [ ] Email notifications
- [ ] Barcode/QR code generation
- [ ] API documentation (Swagger)

---

**Ready for Production!** 🎉

The system is now complete and ready for deployment. Follow the installation guide to get started.
