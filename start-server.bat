@echo off
title C-DATA Calibration System
echo ========================================
echo    C-DATA Calibration System
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

:: ========================================
:: Open Firewall Port 8000 for network access
:: (Limited to Private Network only for security)
:: ========================================
echo Configuring Windows Firewall...
netsh advfirewall firewall show rule name="C-DATA Laravel Server" >nul 2>nul
if %errorlevel% neq 0 (
    echo [INFO] Adding firewall rule for port 8000...
    netsh advfirewall firewall add rule name="C-DATA Laravel Server" dir=in action=allow protocol=tcp localport=8000 profile=private >nul 2>nul
    if %errorlevel% == 0 (
        echo [OK] Firewall rule added successfully
    ) else (
        echo [WARNING] Could not add firewall rule. Run as Administrator if needed.
    )
) else (
    echo [OK] Firewall rule already exists
)
echo.

cd /d "D:\Programs\C-DATA"

:: ========================================
:: Network Detection - Supports:
:: 1. Company LAN (Ethernet)
:: 2. WiFi (any WiFi network)
:: 3. USB Tethering (mobile hotspot via USB)
:: ========================================

setlocal enabledelayedexpansion
set "IP_ADDR="
set "CONNECTION_TYPE="
set "FOUND_ADAPTER=0"

echo.
echo Detecting network connection...
echo ----------------------------------------

:: ===== STEP 1: Check for WiFi connection =====
for /f "delims=" %%L in ('ipconfig') do (
    echo %%L | findstr /i "Wireless LAN adapter Wi-Fi" >nul && set "FOUND_ADAPTER=1"
    if !FOUND_ADAPTER!==1 (
        echo %%L | findstr /i "IPv4" >nul && (
            for /f "tokens=2 delims=:" %%a in ("%%L") do (
                set "TEMP_IP=%%a"
                set "TEMP_IP=!TEMP_IP: =!"
                :: Make sure WiFi IP is not VirtualBox/VMware
                echo !TEMP_IP! | findstr /b "192.168.56 192.168.244 127.0.0" >nul
                if !errorlevel!==1 (
                    set "IP_ADDR=!TEMP_IP!"
                    set "CONNECTION_TYPE=WiFi"
                    goto :found_ip
                )
            )
        )
        :: Reset if adapter is disconnected
        echo %%L | findstr /i "disconnected" >nul && set "FOUND_ADAPTER=0"
    )
)

:: ===== STEP 2: Check for Ethernet/LAN/USB Tethering =====
:: Look for any Ethernet with valid IP (excluding VirtualBox/VMware)
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do (
    set "TEMP_IP=%%a"
    set "TEMP_IP=!TEMP_IP: =!"
    
    :: Skip VirtualBox (192.168.56.x), VMware (192.168.244.x), and localhost
    echo !TEMP_IP! | findstr /b "192.168.56 192.168.244 127.0.0" >nul
    if !errorlevel!==1 (
        set "IP_ADDR=!TEMP_IP!"
        
        :: Detect connection type based on IP range
        echo !TEMP_IP! | findstr /b "172.20 172.21 172.22" >nul && set "CONNECTION_TYPE=USB Tethering (Mobile)"
        echo !TEMP_IP! | findstr /b "192.168.1 192.168.0 10." >nul && set "CONNECTION_TYPE=Company LAN"
        if "!CONNECTION_TYPE!"=="" set "CONNECTION_TYPE=Ethernet/LAN"
        
        goto :found_ip
    )
)

:found_ip
:: Pass variables out of setlocal
endlocal & set "IP_ADDR=%IP_ADDR%" & set "CONNECTION_TYPE=%CONNECTION_TYPE%"

:: Final fallback
if "%IP_ADDR%"=="" (
    echo [WARNING] No network found, using localhost only
    set "IP_ADDR=127.0.0.1"
    set "CONNECTION_TYPE=Localhost (No Network)"
)

:: Display detected connection
echo.
echo [OK] Connection Type: %CONNECTION_TYPE%
echo [OK] IP Address: %IP_ADDR%

:: Update APP_URL in .env automatically
echo.
echo Updating APP_URL in .env...
powershell -Command "(Get-Content .env) -replace '^APP_URL=.*', 'APP_URL=http://%IP_ADDR%:8000' | Set-Content .env"
echo [OK] APP_URL updated to http://%IP_ADDR%:8000

:: Clear Laravel config cache
php artisan config:clear >nul 2>&1

echo.
echo ========================================
echo    C-DATA Calibration System
echo ========================================
echo.
echo    Connection: %CONNECTION_TYPE%
echo    Server IP:  %IP_ADDR%:8000
echo.
echo    Access URL: http://%IP_ADDR%:8000/admin
echo.
echo ----------------------------------------
echo    Share this URL with others on the
echo    same network to access the system!
echo ----------------------------------------
echo.

echo Starting server...
echo.

:: Open browser after 3 seconds with detected IP
start "" cmd /c "timeout /t 3 >nul && start http://%IP_ADDR%:8000/admin"

:: Run PHP Built-in Server (works better on Windows)
php artisan serve --host=0.0.0.0 --port=8000

pause
