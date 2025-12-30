@echo off
title iScan Scanner Service - DS-530 II
color 0A

echo ============================================================
echo iScan Scanner Service - Epson DS-530 II
echo ============================================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python is not installed or not in PATH
    echo Please install Python 3.8 or higher from python.org
    echo.
    pause
    exit /b 1
)

echo [OK] Python is installed
echo.

REM Check if virtual environment exists
if not exist "venv" (
    echo Creating virtual environment...
    python -m venv venv
    echo [OK] Virtual environment created
    echo.
)

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat
echo.

REM Check if requirements are installed
pip show flask >nul 2>&1
if %errorlevel% neq 0 (
    echo Installing required packages...
    echo This may take a few minutes on first run...
    echo.
    pip install -r requirements.txt
    echo.
    echo [OK] All packages installed
    echo.
)

REM Start the scanner service
echo Starting scanner service...
echo Service will be available at: http://localhost:18622
echo.
echo Keep this window open while using the scanner
echo Press Ctrl+C to stop the service
echo.
echo ============================================================
echo.

python scanner_service.py

REM If the service stops, pause to show any error messages
pause
