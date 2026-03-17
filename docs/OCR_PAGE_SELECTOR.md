# 📄 OCR Page Range Selector Feature

## ✨ What's New?

You can now select **SPECIFIC PAGES** to scan instead of processing the entire PDF! This gives you:

- **Faster processing** - Only scan the pages you need
- **Targeted extraction** - Focus on relevant pages
- **Flexible options** - All pages, page range, or custom selection

---

## 🎯 How to Use

### Step 1: Upload a PDF

Upload your PDF file as usual via the certificate form.

### Step 2: Choose Page Selection Mode

When you upload a multi-page PDF, you'll see the **Page Selector** panel with three options:

#### Option 1: All Pages (Default)
- Scans the entire document
- No configuration needed
- Best for single-page or small documents

#### Option 2: Page Range
- Enter page ranges like: `1-5` or `1-3, 8-10`
- Great for continuous sections
- Example: `1-5` scans pages 1, 2, 3, 4, 5

#### Option 3: Specific Pages
- Enter page numbers separated by commas: `1, 6, 15`
- Perfect for non-consecutive pages
- Example: `1, 6, 15` scans only pages 1, 6, and 15

### Step 3: Process PDF

Click **"Process PDF"** button. The OCR will only scan your selected pages!

---

## 📋 Usage Examples

### Example 1: Certificate on Pages 1-3
**Scenario:** You have a 10-page PDF but the birth certificate is only on pages 1-3

```
Mode: Page Range
Input: 1-3
Result: Scans only pages 1, 2, 3
```

### Example 2: Multiple Certificates
**Scenario:** You have certificates on pages 1, 6, and 15 in a large PDF

```
Mode: Specific Pages
Input: 1, 6, 15
Result: Scans only pages 1, 6, 15
```

### Example 3: Last Few Pages
**Scenario:** Certificate is on the last 2 pages of a 20-page PDF

```
Mode: Page Range
Input: 19-20
Result: Scans only pages 19, 20
```

---

## ⚡ Performance Benefits

### Without Page Selection:
- 20-page PDF → Process ALL 20 pages
- Time: ~2-3 minutes (browser) or ~20 seconds (server)

### With Page Selection:
- 20-page PDF → Select pages 1, 6
- Time: ~20 seconds (browser) or ~2 seconds (server)

**Savings: Up to 90% faster!**

---

## 🎨 Features

### Visual Page Preview (Optional)
- Click **"Show Thumbnails"** to see page previews
- Helps identify which pages to select
- First 20 pages shown (for performance)

### Smart Validation
- Invalid page numbers are automatically skipped
- Real-time feedback on selection
- Clear summary of selected pages

### Caching Still Works!
- Each page selection is cached separately
- Same pages = Instant results (0 seconds!)
- Different pages = Fresh OCR processing

---

## 🔧 Technical Details

### How It Works:

```
1. User uploads PDF
   ↓
2. Page Selector loads PDF metadata (page count)
   ↓
3. User selects pages (or defaults to all)
   ↓
4. OCR processes ONLY selected pages
   ↓
5. Results cached with page selection hash
```

### Supported Formats:

**Page Range Input:**
- Single range: `1-5`
- Multiple ranges: `1-3, 8-10, 15-20`
- Spaces are optional: `1-5` or `1 - 5`

**Custom Pages Input:**
- Comma-separated: `1, 6, 15`
- Spaces are optional: `1,6,15` or `1, 6, 15`

---

## 🐛 Troubleshooting

### "Page selector not showing"
- **Cause:** PDF has only 1 page
- **Solution:** Single page PDFs don't need page selection

### "Invalid page number"
- **Cause:** Entered page number exceeds PDF page count
- **Solution:** Check PDF page count badge and enter valid numbers

### "Extraction only shows some pages"
- **Expected:** You selected specific pages!
- **Solution:** Check "Selected Pages" summary to confirm selection

### "Still processing all pages"
- **Cause:** Browser OCR fallback doesn't support page selection yet on some systems
- **Solution:** Use server OCR (FAST mode) for full page selection support

---

## 📊 Comparison

| Feature | Before | After |
|---------|--------|-------|
| **Page Control** | All pages only | Flexible selection |
| **Speed** | Process entire PDF | Process only needed pages |
| **Flexibility** | Limited | High - ranges & custom |
| **Preview** | None | Optional thumbnails |

---

## 🚀 Setup (Already Configured)

The page selector is automatically integrated into:
- ✅ Certificate of Live Birth form
- ✅ Certificate of Marriage form

No additional setup needed!

---

## 💡 Pro Tips

1. **Use Page Range for continuous sections**
   - Example: Birth certificate spans pages 1-3

2. **Use Specific Pages for scattered documents**
   - Example: Multiple certificates in one PDF

3. **Enable "Show Thumbnails" if unsure**
   - Visual preview helps identify correct pages

4. **Cache is page-specific**
   - Same PDF + Same pages = Instant
   - Same PDF + Different pages = New OCR

---

## 📝 Files Added

1. **ocr-page-selector.js** - Page selection logic
2. **ocr-page-selector.css** - Page selector styling
3. **Updated files:**
   - ocr-processor.js - Handles page ranges
   - ocr-server-client.js - Sends page selection to server
   - TesseractOCR.php - Server-side page processing
   - ocr_process.php - API endpoint update
   - certificate_of_live_birth.php - Integration
   - certificate_of_marriage.php - Integration

---

## ✅ Ready to Use!

Upload a multi-page PDF to see the page selector in action! 🎉
