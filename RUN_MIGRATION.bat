@echo off
echo ============================================
echo Running Database Migration
echo Adding Child Columns to Birth Certificates
echo ============================================
echo.

cd /d C:\xampp\mysql\bin
mysql -u root -e "source C:/xampp/htdocs/iscan/database/migrations/add_child_columns_to_birth_certificates.sql"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ============================================
    echo Migration completed successfully!
    echo ============================================
) else (
    echo.
    echo ============================================
    echo Migration failed! Please check the error above.
    echo ============================================
)

echo.
pause
