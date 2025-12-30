# OCR Smart Extraction - Supported Fields

The OCR system now extracts ALL the following fields from Philippine Birth Certificate PDFs:

## ✅ Currently Extracted Fields

### Child Information
1. **Registry Number** - Certificate registry number (top of form)
2. **Child's First Name** - Extracted from Section 1. NAME
3. **Child's Middle Name** - Extracted from Section 1. NAME
4. **Child's Last Name** - Extracted from Section 1. NAME
5. **Child's Sex** - Extracted from Section 2. SEX (MALE/FEMALE)
6. **Child's Date of Birth** - Extracted from Section 3. DATE OF BIRTH
   - Format: Automatically converts "17 OCTOBER 1999" to "1999-10-17" for HTML date inputs
7. **Child's Place of Birth** - Extracted from Section 4. PLACE OF BIRTH

### Birth Details
8. **Type of Birth** - Extracted from Section 5a. TYPE OF BIRTH
   - Values: SINGLE, TWIN, TRIPLETS, QUADRUPLETS, etc.
9. **Birth Order** - Extracted from Section 5c. BIRTH ORDER
   - Values: First, Second, Third, etc.

### Mother Information (Section 7. MAIDEN NAME)
10. **Mother's First Name**
11. **Mother's Middle Name**
12. **Mother's Last Name**

### Father Information (Section 14. NAME)
13. **Father's First Name**
14. **Father's Middle Name**
15. **Father's Last Name**

## 🎯 How It Works

1. **Upload PDF** - User uploads a scanned birth certificate PDF
2. **Auto-Process** - OCR automatically processes the PDF using Tesseract.js
3. **Smart Extraction** - Intelligent pattern matching extracts data from specific form sections
4. **Field Mapping** - Extracted data is mapped to correct form field IDs
5. **Date Conversion** - Dates are converted from "MONTH DD, YYYY" to "YYYY-MM-DD"
6. **Auto-Fill** - Click "Apply All" or individual "Use This" buttons to fill form fields

## 📋 Extraction Patterns

### Philippine Birth Certificate Format
```
1. NAME          (First)    (Middle)    (Last)
   RICHMOND                  ROSETE

2. SEX (Male / Female)    3. DATE OF    (Day) (Month)     (Year)
   MALE                      BIRTH       17    OCTOBER     1999

4. PLACE OF BIRTH (Hospital/Clinic/Institution)
   House No., St., Barangay

5a. TYPE OF BIRTH         5b. IF MULTIPLE BIRTH    5c. BIRTH ORDER
    SINGLE                    (First, Second, etc.)

7. MAIDEN NAME    (First)    (Middle)    (Last)
   [Mother's name]

14. NAME          (First)    (Middle)    (Last)
    [Father's name]
```

## 🔧 Smart Features

- **Blank Field Handling** - Gracefully skips blank/empty fields
- **Label Filtering** - Ignores form labels like "(First)", "(Middle)", "(Last)"
- **Multi-format Dates** - Handles various date formats and converts to HTML date format
- **Confidence Scores** - Shows confidence percentage for each extracted field
- **Visual Feedback** - Fields flash green when auto-filled
- **Selective Filling** - Fill all at once or one field at a time

## 🚀 Future Enhancements

Additional fields that could be added:
- Weight at birth
- Mother's citizenship, religion, occupation, age
- Father's citizenship, religion, occupation, age
- Marriage details of parents
- Attendant information
- Registration details

## 📝 Notes

- OCR accuracy depends on PDF quality (300+ DPI recommended)
- Scanned documents work better than photos
- Clear, high-contrast text yields best results
- The system handles both completely filled and partially filled certificates
