# Marriage Certificate Management System

Complete system for managing marriage certificates with create, read, update, and delete (CRUD) functionality.

## 📁 Files Created

### 1. Database
- **`database/create_marriage_table.sql`** - SQL schema for marriage certificates table
- **`database/run_marriage_migration.php`** - Migration script to create the table

### 2. Frontend Pages
- **`public/certificate_of_marriage.php`** - Form for creating/editing marriage certificates
- **`public/marriage_records.php`** - Records listing with search, edit, and delete

### 3. API Endpoints
- **`api/certificate_of_marriage_save.php`** - Save new marriage certificate
- **`api/certificate_of_marriage_update.php`** - Update existing certificate
- **`api/certificate_of_marriage_delete.php`** - Soft delete certificate

## 🚀 Installation Steps

### Step 1: Create Database Table

Run the migration script by accessing it in your browser:

```
http://localhost/iscan/database/run_marriage_migration.php
```

Or run via command line:

```bash
php database/run_marriage_migration.php
```

You should see:
```
✓ Success! The certificate_of_marriage table has been created successfully.
```

### Step 2: Verify File Permissions

Ensure the `uploads/` directory exists and is writable:

```bash
# On Linux/Mac
chmod 755 uploads/

# On Windows (XAMPP)
# The directory should already have proper permissions
```

### Step 3: Access the System

Navigate to the marriage certificate form:
```
http://localhost/iscan/public/certificate_of_marriage.php
```

Navigate to the records page:
```
http://localhost/iscan/public/marriage_records.php
```

## 📋 Database Schema

### `certificate_of_marriage` Table

**Registry Information:**
- `registry_no` - Registry number (optional)
- `date_of_registration` - Registration date (required)

**Husband's Information:**
- `husband_first_name`, `husband_middle_name`, `husband_last_name`
- `husband_date_of_birth`, `husband_place_of_birth`
- `husband_residence`
- `husband_father_name`, `husband_father_residence`
- `husband_mother_name`, `husband_mother_residence`

**Wife's Information:**
- `wife_first_name`, `wife_middle_name`, `wife_last_name`
- `wife_date_of_birth`, `wife_place_of_birth`
- `wife_residence`
- `wife_father_name`, `wife_father_residence`
- `wife_mother_name`, `wife_mother_residence`

**Marriage Information:**
- `date_of_marriage` (required)
- `place_of_marriage` (required)

**PDF & Metadata:**
- `pdf_filename`, `pdf_filepath`
- `status` (Active/Archived/Deleted)
- `created_at`, `updated_at`
- `created_by`, `updated_by`

## 🎯 Features

### Form Page (`certificate_of_marriage.php`)
- ✅ Responsive two-column layout (form + PDF preview)
- ✅ Required field validation
- ✅ PDF file upload with preview
- ✅ Scanner integration support (DS-530 II)
- ✅ Auto-save current date for registration
- ✅ Edit mode support
- ✅ Save & Add New functionality
- ✅ Toggleable PDF preview column
- ✅ Mobile-responsive design

### Records Page (`marriage_records.php`)
- ✅ Paginated table view (10 records per page)
- ✅ Search functionality (registry no, names, dates, places)
- ✅ View PDF button
- ✅ Edit button
- ✅ Delete button (soft delete)
- ✅ Responsive table design
- ✅ Clean pagination controls

### API Endpoints
- ✅ File type validation (PDF only)
- ✅ File size validation (10MB max)
- ✅ Unique filename generation
- ✅ Secure file upload handling
- ✅ SQL injection protection
- ✅ Error handling and logging
- ✅ JSON response format

## 📝 Usage Guide

### Creating a New Marriage Certificate

1. Navigate to **Certificates > Marriage Certificates** in the sidebar
2. Fill in the required fields (marked with *)
3. Upload the PDF certificate
4. Click **Save Record** or **Save & Add New**
5. The system will validate and save the record

### Viewing Records

1. Navigate to **Management > Marriage Records**
2. Browse all active marriage certificates
3. Use the search bar to filter records
4. Use pagination to navigate through pages

### Editing a Record

1. Click the **Edit** button (pencil icon) on any record
2. Update the information as needed
3. Optionally upload a new PDF (keeps existing if not uploaded)
4. Click **Update Record**

### Deleting a Record

1. Click the **Delete** button (trash icon) on any record
2. Confirm the deletion in the popup
3. The record will be soft-deleted (status = 'Deleted')
4. Soft-deleted records are hidden but preserved in the database

### Viewing PDF Certificate

1. Click the **View PDF** button (document icon) on any record
2. The PDF will open in a new browser tab

## 🔒 Security Features

- ✅ Input sanitization
- ✅ Prepared statements (SQL injection protection)
- ✅ File type validation
- ✅ File size limits
- ✅ Unique filename generation
- ✅ Soft delete (data preservation)
- ✅ User tracking (created_by, updated_by)

## 🎨 Design Features

- Clean, professional UI matching birth certificate system
- Responsive sidebar navigation
- Collapsible sidebar for more workspace
- Mobile-friendly responsive design
- Professional color scheme
- Smooth animations and transitions
- Icon-based actions for better UX
- Loading states and error handling

## 🔧 Customization

### Change Records Per Page

Edit `marriage_records.php` line 18:
```php
$records_per_page = 10; // Change to your preferred number
```

### Change Upload Directory

Edit API files, change:
```php
$upload_dir = '../uploads/'; // Change to your preferred directory
```

### Change Max File Size

Edit API files, change:
```php
$max_size = 10 * 1024 * 1024; // 10MB - adjust as needed
```

## 📊 Statistics

**Total Files:** 7
**Lines of Code:** ~2,500+
**Features:** 15+
**Database Fields:** 25+

## 🐛 Troubleshooting

### "Failed to upload PDF file"
- Check that `uploads/` directory exists
- Verify directory permissions (755)
- Ensure PHP upload limits are sufficient

### "Database error occurred"
- Verify database connection in `config.php`
- Check if table exists (run migration)
- Review error logs

### "Record not found"
- Record may have been deleted
- Check database status field
- Verify record ID is correct

## 📞 Support

For issues or questions:
1. Check error logs in browser console
2. Review PHP error logs
3. Verify database connection
4. Check file permissions

## 🎉 Success!

Your marriage certificate management system is now fully functional with:
- ✅ Create new certificates
- ✅ View all records
- ✅ Search records
- ✅ Edit certificates
- ✅ Delete certificates
- ✅ PDF upload & preview
- ✅ Full mobile support

Happy managing! 🎊
