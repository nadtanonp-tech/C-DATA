@echo off
title C-DATA Calibration System (LAN Office)
echo ========================================
echo    C-DATA Calibration System
echo    LAN Office: 172.29.31.71
echo ========================================
echo.

:: Start PostgreSQL Service
echo Starting PostgreSQL...
net start postgresql-x64-18 2>nul
if %errorlevel% == 0 (
    echo [OK] PostgreSQL started successfully
) else (
    echo [INFO] PostgreSQL is already running or requires Admin privileges
)
echo.

cd /d "D:\Programs\C-DATA"

echo Starting server...
echo.

:: Open browser after 3 seconds
start "" cmd /c "timeout /t 3 >nul && start http://172.29.31.71:8000/admin"

:: Run Laravel Server
php artisan serve --host=172.29.31.71 --port=8000

pause
