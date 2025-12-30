# How to Add OCR to Existing Forms

This guide shows you how to add the OCR Assistant to your existing certificate forms **WITHOUT** changing any of the existing HTML or PHP code.

## Features

- ✅ **Non-Invasive**: Just add 3 script tags to your existing form
- ✅ **Optional**: Users can choose to use it or ignore it completely
- ✅ **Auto-Detects**: Automatically processes PDFs when uploaded
- ✅ **Smart Suggestions**: Shows extracted data with confidence scores
- ✅ **User Control**: Users decide which fields to auto-fill
- ✅ **No Form Changes**: Your existing form fields remain unchanged

## Installation Steps

### Step 1: Add Required Libraries (Bottom of your HTML file, before `</body>`)

```html
<!-- PDF.js for PDF to Image conversion -->
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script>
    // Configure PDF.js worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
</script>

<!-- Tesseract.js for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>

<!-- Our OCR Processor -->
<script src="../assets/js/ocr-processor.js"></script>

<!-- OCR Form Integration -->
<script src="../assets/js/ocr-form-integration.js"></script>
```

### Step 2: That's It!

Seriously, that's all you need to do. The OCR panel will automatically appear on your form and start working.

## Example: Add to Birth Certificate Form

Edit `public/certificate_of_live_birth.php` and add this **before the closing `</body>` tag**:

```html
<!-- EXISTING FORM CODE ABOVE (don't change anything) -->

    </form>
</div>

<!-- ADD OCR FEATURE - Just these 4 script tags -->
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
</script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
<script src="../assets/js/ocr-processor.js"></script>
<script src="../assets/js/ocr-form-integration.js"></script>

</body>
</html>
```

## What Gets Added to the Page

The OCR integration will inject a beautiful purple panel on your page with:

1. **Status Display** - Shows what the OCR is currently doing
2. **Progress Bar** - Real-time processing progress
3. **Action Buttons**:
   - 📄 Process PDF - Manually trigger OCR
   - ✅ Apply All - Apply all high-confidence suggestions
   - ❌ Clear - Remove all suggestions
4. **Suggestions List** - Shows all extracted data with confidence scores
5. **Settings**:
   - Auto-process on file select
   - Auto-fill high confidence fields

## How It Works (User Perspective)

1. User uploads PDF file (as usual)
2. OCR panel shows "Processing PDF..."
3. After a few seconds, extracted data appears with confidence scores:
   - 🟢 Green (80%+) = High confidence
   - 🟡 Yellow (50-79%) = Medium confidence
   - 🔴 Red (<50%) = Low confidence
4. User can:
   - Click "Apply" next to individual fields
   - Click "Apply All" to auto-fill everything above 75% confidence
   - Manually edit any field as normal
   - Ignore OCR completely and type manually

## Customization Options

You can customize the OCR behavior by modifying the initialization:

```javascript
// Add this after loading the scripts
<script>
    // Wait for page load
    document.addEventListener('DOMContentLoaded', () => {
        window.ocrForm = new OCRFormIntegration({
            formId: 'certificateForm',        // Your form ID
            fileInputId: 'pdf_file',           // Your file input ID
            autoProcess: true,                 // Auto-process on upload?
            autoFill: false,                   // Auto-fill high confidence?
            confidenceThreshold: 75            // Minimum confidence to apply
        });
    });
</script>
```

## Field Mapping

The OCR automatically looks for these fields in your form:

### Birth Certificate Fields:
- `registry_no`
- `date_of_registration`
- `child_first_name`
- `child_middle_name`
- `child_last_name`
- `child_date_of_birth`
- `child_place_of_birth`
- `mother_first_name`
- `mother_middle_name`
- `mother_last_name`
- `father_first_name`
- `father_middle_name`
- `father_last_name`

### Marriage Certificate Fields:
- `registry_no`
- `date_of_registration`
- `date_of_marriage`
- `place_of_marriage`
- `husband_first_name`
- `husband_middle_name`
- `husband_last_name`
- `wife_first_name`
- `wife_middle_name`
- `wife_last_name`

## Troubleshooting

### OCR Panel Doesn't Appear
- Check browser console for JavaScript errors
- Ensure all 4 script tags are added
- Verify script paths are correct

### OCR Process Button is Disabled
- Make sure you've selected a PDF file first
- Check that the file input has id="pdf_file"

### Low Accuracy Results
- Ensure PDF is clear and high quality
- Scanned at minimum 300 DPI
- Text is not handwritten (OCR works best with printed text)
- Try processing individual pages

### Performance Issues
- OCR processing takes 3-10 seconds per page
- Multi-page PDFs take longer
- Processing happens in background, doesn't block the form

## Technical Notes

- Uses Tesseract.js 4.x (runs in browser, no server needed)
- PDF.js converts PDF to images
- All processing happens client-side (private/secure)
- No data sent to external servers
- Works offline after initial library load
- Supports English language by default
- Can be extended to support other languages

## Advanced: Add to All Forms

Create a reusable component in `includes/ocr_scripts.php`:

```php
<?php
function include_ocr_scripts() {
    ?>
    <!-- OCR Feature -->
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <script src="../assets/js/ocr-processor.js"></script>
    <script src="../assets/js/ocr-form-integration.js"></script>
    <?php
}
?>
```

Then in your forms:

```php
<!-- Before </body> -->
<?php include_once '../includes/ocr_scripts.php'; include_ocr_scripts(); ?>
</body>
```

## Privacy & Security

- ✅ All OCR processing happens in the user's browser
- ✅ No PDF data sent to external servers
- ✅ No third-party API calls
- ✅ Completely offline-capable (after initial load)
- ✅ GDPR compliant (no data transmission)

## Future Enhancements

Planned features:
- Multi-language support (Tagalog, other Filipino languages)
- Handwriting recognition
- Server-side OCR option (Google Vision API, AWS Textract)
- OCR result caching in database
- Batch OCR processing
- Field-specific OCR training

## Support

For issues or questions:
1. Check browser console for errors
2. Verify all scripts are loaded (Network tab in DevTools)
3. Test with a clear, high-quality PDF
4. Ensure form field IDs match the expected names

---

**Remember**: The OCR feature is completely optional. Users can still fill forms manually exactly as before. It's just an assistant to speed up data entry!
