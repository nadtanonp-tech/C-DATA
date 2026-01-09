@echo off
title C-DATA Calibration System
echo ========================================
echo    C-DATA Calibration System
echo ========================================
echo.
echo กำลังเริ่มต้นเซิร์ฟเวอร์...
echo.

cd /d "D:\Programs\C-DATA"

:: เปิดเบราว์เซอร์หลังจาก 3 วินาที
start "" cmd /c "timeout /t 3 >nul && start http://localhost:8000/admin"

:: รัน Laravel Server
php artisan serve

pause
