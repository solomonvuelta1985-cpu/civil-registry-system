#!/bin/bash
# ================================================================
# iScan — Synology NAS First-Time Setup Script
# ================================================================
# Run this script via SSH after uploading files to Synology.
#
# Prerequisites (install via DSM Package Center):
#   - Web Station (Apache + PHP 8.2)
#   - MariaDB 10
#   - phpMyAdmin (optional, for DB management)
#
# Usage:
#   1. Upload this project to /volume1/web/iscan via File Station or SFTP
#   2. SSH into Synology: ssh admin@192.168.x.x
#   3. sudo bash /volume1/web/iscan/setup_synology.sh
# ================================================================

set -e

PROJECT_DIR="/volume1/web/iscan"
WEB_USER="http"        # Synology Apache/PHP runs as 'http'
WEB_GROUP="http"

# ----------------------------------------------------------------
# Color output helpers
# ----------------------------------------------------------------
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
info() { echo -e "  $1"; }

echo ""
echo "================================================================"
echo " iScan — Synology NAS Setup (DSM 7.x)"
echo "================================================================"
echo ""

# ----------------------------------------------------------------
# 1. Verify project directory exists
# ----------------------------------------------------------------
if [ ! -d "$PROJECT_DIR" ]; then
    echo -e "${RED}[ERROR]${NC} Project directory not found: $PROJECT_DIR"
    echo "Upload the project files first, then re-run this script."
    exit 1
fi
ok "Project directory found: $PROJECT_DIR"

# ----------------------------------------------------------------
# 2. Set file permissions
# ----------------------------------------------------------------
echo ""
echo "[Step 1/6] Setting file permissions..."
find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
find "$PROJECT_DIR" -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x "$PROJECT_DIR/setup_synology.sh"
chmod +x "$PROJECT_DIR/download_assets.sh"
[ -f "$PROJECT_DIR/scanner_service/start_scanner.sh" ] && \
    chmod +x "$PROJECT_DIR/scanner_service/start_scanner.sh"

# Writable by web server
chmod -R 775 "$PROJECT_DIR/uploads"
chmod -R 775 "$PROJECT_DIR/logs"
chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/uploads"
chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/logs"

ok "File permissions set."

# ----------------------------------------------------------------
# 3. Create logs directory if missing
# ----------------------------------------------------------------
echo ""
echo "[Step 2/6] Checking directories..."
mkdir -p "$PROJECT_DIR/logs"
mkdir -p "$PROJECT_DIR/uploads/birth"
mkdir -p "$PROJECT_DIR/uploads/death"
mkdir -p "$PROJECT_DIR/uploads/marriage"
mkdir -p "$PROJECT_DIR/uploads/marriage_license"
chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/logs"
chown -R "$WEB_USER:$WEB_GROUP" "$PROJECT_DIR/uploads"
ok "Directories ready."

# ----------------------------------------------------------------
# 4. Create .env from Synology template
# ----------------------------------------------------------------
echo ""
echo "[Step 3/6] Configuring environment..."
if [ ! -f "$PROJECT_DIR/.env" ]; then
    if [ -f "$PROJECT_DIR/.env.synology" ]; then
        cp "$PROJECT_DIR/.env.synology" "$PROJECT_DIR/.env"
        ok "Created .env from .env.synology"
        warn "IMPORTANT: Edit $PROJECT_DIR/.env with your actual DB credentials before proceeding!"
    else
        warn ".env.synology not found. Copy .env.example manually and edit it."
    fi
else
    ok ".env already exists — not overwritten."
fi

# ----------------------------------------------------------------
# 5. Download vendor assets for offline use
# ----------------------------------------------------------------
echo ""
echo "[Step 4/6] Downloading offline vendor assets..."
if [ -f "$PROJECT_DIR/download_assets.sh" ]; then
    bash "$PROJECT_DIR/download_assets.sh"
else
    warn "download_assets.sh not found. Skipping offline assets."
fi

# ----------------------------------------------------------------
# 6. Check Tesseract OCR
# ----------------------------------------------------------------
echo ""
echo "[Step 5/6] Checking Tesseract OCR..."
if command -v tesseract &>/dev/null; then
    ok "Tesseract found: $(tesseract --version 2>&1 | head -1)"
else
    warn "Tesseract OCR not found."
    info "Install via Entware (SynoCommunity):"
    info "  opkg update && opkg install tesseract-ocr tesseract-ocr-eng"
    info "Or install via Docker if Entware is unavailable."
fi

# ----------------------------------------------------------------
# 7. Check Python (for scanner service)
# ----------------------------------------------------------------
echo ""
echo "[Step 6/6] Checking Python (Scanner Service)..."
if command -v python3 &>/dev/null; then
    ok "Python3 found: $(python3 --version)"
    info "To start scanner: bash $PROJECT_DIR/scanner_service/start_scanner.sh"
    info "To stop scanner:  bash $PROJECT_DIR/scanner_service/start_scanner.sh stop"
else
    warn "Python3 not found. Install via Entware: opkg install python3"
fi

# ----------------------------------------------------------------
# Database Setup Instructions
# ----------------------------------------------------------------
echo ""
echo "================================================================"
echo " DATABASE SETUP (run manually in phpMyAdmin or via SSH)"
echo "================================================================"
echo ""
echo "1. Create database and user:"
echo "   mysql -u root -p"
echo "   CREATE DATABASE iscan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "   CREATE USER 'iscan_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';"
echo "   GRANT ALL PRIVILEGES ON iscan_db.* TO 'iscan_user'@'localhost';"
echo "   FLUSH PRIVILEGES;"
echo "   EXIT;"
echo ""
echo "2. Import base schema:"
echo "   mysql -u root -p iscan_db < $PROJECT_DIR/database_schema.sql"
echo ""
echo "3. Run migrations in order:"
echo "   mysql -u root -p iscan_db < $PROJECT_DIR/database/migrations/002_workflow_versioning_ocr_tables.sql"
echo "   mysql -u root -p iscan_db < $PROJECT_DIR/database/migrations/003_calendar_notes_system.sql"
echo "   mysql -u root -p iscan_db < $PROJECT_DIR/database/migrations/004_add_citizenship_to_birth_certificates.sql"
echo "   mysql -u root -p iscan_db < $PROJECT_DIR/database/migrations/005_add_barangay_and_time_of_birth.sql"
echo ""
echo "4. Update .env with your DB credentials:"
echo "   nano $PROJECT_DIR/.env"
echo ""
echo "================================================================"
echo " WEB STATION SETUP (DSM GUI)"
echo "================================================================"
echo ""
echo "1. Open DSM > Web Station > Web Service Portal"
echo "2. Create a new portal:"
echo "   - Port: 80"
echo "   - Document root: /volume1/web"
echo "   - PHP: PHP 8.2"
echo "   - Enable .htaccess (AllowOverride All)"
echo "3. Import php_synology.ini settings into the PHP 8.2 profile"
echo "   (DSM > Web Station > PHP Settings > Edit)"
echo ""
echo "================================================================"
echo " ACCESS THE APP"
echo "================================================================"
echo ""
echo "  URL: http://$(hostname -I | awk '{print $1}')/iscan/"
echo "  Login: admin / admin123  (CHANGE THIS IMMEDIATELY)"
echo ""
echo "================================================================"
echo " Setup complete!"
echo "================================================================"
