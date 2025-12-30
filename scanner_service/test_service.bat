@echo off
title Test Scanner Service
color 0B

echo ============================================================
echo Scanner Service Connection Test
echo ============================================================
echo.

echo Testing if scanner service is running...
echo.

REM Test the service endpoint
curl -s http://localhost:18622/scanner/test >nul 2>&1

if %errorlevel% equ 0 (
    echo [SUCCESS] Scanner service is running!
    echo.
    echo Service Details:
    curl -s http://localhost:18622/scanner/test
    echo.
    echo.
    echo Testing scanner availability...
    echo.
    curl -s http://localhost:18622/scanner/status
    echo.
    echo.
    echo [OK] All tests passed!
    echo The scanner service is ready to use.
) else (
    echo [ERROR] Scanner service is NOT running
    echo.
    echo Please start the scanner service first:
    echo 1. Double-click "start_scanner.bat"
    echo 2. Wait for "Service running" message
    echo 3. Run this test again
)

echo.
echo ============================================================
pause
