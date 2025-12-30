================================================================================
iScan Scanner Service - DS-530 II Integration
================================================================================

This folder contains the scanner service for integrating the Epson DS-530 II
document scanner with the iScan Civil Registry System.

================================================================================
QUICK START (5 Minutes Setup)
================================================================================

1. Install Python (if not already installed)
   - Download Python 3.8 or higher from: https://www.python.org/downloads/
   - During installation, CHECK the box "Add Python to PATH"
   - Restart your computer after installation

2. Install Scanner Drivers
   - Install Epson DS-530 II drivers from Epson website
   - Make sure the scanner is connected via USB
   - Test that scanner works with Windows (scan a test page)

3. Start the Scanner Service
   - Double-click "start_scanner.bat"
   - Wait for it to install packages (first time only - takes 2-3 minutes)
   - You should see: "Service running on: http://localhost:18622"
   - KEEP THIS WINDOW OPEN while using the scanner

4. Use the Scanner in iScan
   - Open the Certificate of Live Birth form
   - Click the green "Scan Document" button
   - The scanner will scan and automatically attach the PDF

================================================================================
TECHNICAL DETAILS
================================================================================

Service Port: 18622
Service URL: http://localhost:18622

Available Endpoints:
- GET  /scanner/status - Check if scanner is ready
- POST /scanner/scan   - Perform scan and get PDF
- GET  /scanner/test   - Test if service is running

Scan Settings:
- Resolution: 300 DPI
- Color Mode: Color
- Format: PDF
- Quality: High

================================================================================
TROUBLESHOOTING
================================================================================

Problem: "Python is not installed or not in PATH"
Solution: Install Python and make sure to check "Add Python to PATH" during
          installation. Restart computer after installation.

Problem: "Scanner not detected"
Solution:
  - Make sure DS-530 II is connected via USB and powered on
  - Install latest Epson DS-530 II drivers
  - Test scanner with Windows scanning app first
  - Restart the scanner service

Problem: "Module 'sane' not found" (Windows)
Solution: The service will run in simulation mode. For actual scanning on
          Windows, you may need to install third-party SANE drivers or use
          Windows WIA instead. Contact support for Windows-specific drivers.

Problem: Service won't start
Solution:
  - Make sure no other program is using port 18622
  - Check Windows Firewall isn't blocking Python
  - Run start_scanner.bat as Administrator

Problem: Scan button shows error in browser
Solution:
  - Make sure scanner service is running (start_scanner.bat window is open)
  - Check service is accessible: Open http://localhost:18622/scanner/test
    in your browser - you should see a JSON response
  - Restart the scanner service

================================================================================
FILE DESCRIPTIONS
================================================================================

scanner_service.py   - Main scanner service (Python Flask application)
requirements.txt     - Python package dependencies
start_scanner.bat    - Easy launcher for Windows
README.txt          - This file

================================================================================
SYSTEM REQUIREMENTS
================================================================================

- Windows 10 or higher
- Python 3.8 or higher
- Epson DS-530 II scanner with drivers installed
- USB connection to scanner
- 100 MB free disk space
- Active internet connection (first-time setup only)

================================================================================
DAILY USAGE
================================================================================

Every time you want to use the scanner:
1. Make sure scanner is connected and powered on
2. Double-click "start_scanner.bat"
3. Wait for "Service running" message
4. Use iScan normally - the scan button will work
5. When done, close the scanner service window (Ctrl+C)

================================================================================
ADVANCED CONFIGURATION
================================================================================

To change scanner settings, edit scanner_service.py:

Line 21: SCANNER_DPI = 300        (change resolution)
Line 22: SCANNER_MODE = "Color"   (change to "Gray" or "Lineart")

To change the service port, edit line 174:
app.run(host='localhost', port=18622, debug=False)

After changes, restart the scanner service.

================================================================================
SUPPORT
================================================================================

For issues or questions, contact your system administrator.

Scanner Service Version: 1.0
Last Updated: 2025-12-22

================================================================================
