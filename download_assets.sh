#!/bin/bash
# ================================================================
# iScan — Download Vendor Assets for Offline Use
# ================================================================
# Run this script ONCE on the Synology NAS (via SSH) to download
# all CDN libraries so the app works without internet access.
#
# Usage:
#   cd /volume1/web/iscan
#   bash download_assets.sh
# ================================================================

set -e

VENDOR_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/assets/vendor"

echo "=== iScan Vendor Asset Downloader ==="
echo "Saving to: $VENDOR_DIR"
echo ""

# Create directories
mkdir -p "$VENDOR_DIR/fontawesome/css"
mkdir -p "$VENDOR_DIR/fontawesome/webfonts"
mkdir -p "$VENDOR_DIR/chartjs"
mkdir -p "$VENDOR_DIR/notiflix"
mkdir -p "$VENDOR_DIR/pdfjs/build"
mkdir -p "$VENDOR_DIR/lucide"

# ----------------------------------------------------------------
# Font Awesome 6.4.0
# ----------------------------------------------------------------
echo "[1/7] Downloading Font Awesome 6.4.0..."
curl -fsSL "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" \
    -o "$VENDOR_DIR/fontawesome/css/all.min.css"

# Patch CSS to point to local webfonts
sed -i 's|https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/||g' \
    "$VENDOR_DIR/fontawesome/css/all.min.css"

for FONT in fa-solid-900 fa-regular-400 fa-brands-400 fa-light-300 fa-thin-100 fa-duotone-900 fa-v4compatibility; do
    for EXT in woff2 ttf; do
        URL="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/${FONT}.${EXT}"
        FILE="$VENDOR_DIR/fontawesome/webfonts/${FONT}.${EXT}"
        if [ ! -f "$FILE" ]; then
            curl -fsSL "$URL" -o "$FILE" 2>/dev/null || true
        fi
    done
done
echo "  Font Awesome done."

# ----------------------------------------------------------------
# Chart.js 4.4.0
# ----------------------------------------------------------------
echo "[2/7] Downloading Chart.js 4.4.0..."
curl -fsSL "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" \
    -o "$VENDOR_DIR/chartjs/chart.umd.min.js"
echo "  Chart.js done."

# ----------------------------------------------------------------
# Notiflix 3.2.6
# ----------------------------------------------------------------
echo "[3/7] Downloading Notiflix 3.2.6..."
curl -fsSL "https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css" \
    -o "$VENDOR_DIR/notiflix/notiflix-3.2.6.min.css"
curl -fsSL "https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js" \
    -o "$VENDOR_DIR/notiflix/notiflix-3.2.6.min.js"
echo "  Notiflix done."

# ----------------------------------------------------------------
# PDF.js 3.11.174
# ----------------------------------------------------------------
echo "[4/7] Downloading PDF.js 3.11.174..."
curl -fsSL "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" \
    -o "$VENDOR_DIR/pdfjs/build/pdf.min.js"
curl -fsSL "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js" \
    -o "$VENDOR_DIR/pdfjs/build/pdf.worker.min.js"
echo "  PDF.js done."

# ----------------------------------------------------------------
# Lucide Icons
# ----------------------------------------------------------------
echo "[5/7] Downloading Lucide icons..."
curl -fsSL "https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" \
    -o "$VENDOR_DIR/lucide/lucide.min.js"
echo "  Lucide done."

# ----------------------------------------------------------------
# Tesseract.js 4 (browser-side OCR fallback)
# ----------------------------------------------------------------
echo "[6/7] Downloading Tesseract.js 4..."
mkdir -p "$VENDOR_DIR/tesseractjs"
curl -fsSL "https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js" \
    -o "$VENDOR_DIR/tesseractjs/tesseract.min.js" 2>/dev/null || echo "  (Tesseract.js skipped — optional)"
echo "  Tesseract.js done."

# ----------------------------------------------------------------
# Bootstrap 5 (check if used)
# ----------------------------------------------------------------
echo "[7/7] Done!"

echo ""
echo "================================================================"
echo "All vendor assets saved to: $VENDOR_DIR"
echo "Set OFFLINE_MODE=true in your .env to use these local assets."
echo "================================================================"
