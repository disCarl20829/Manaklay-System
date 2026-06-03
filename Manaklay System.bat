@echo off
title Manaklay System - Cloudflare Tunnel (Stable Fixed Mode)
setlocal

echo ==========================================
echo   MANAKLAY SYSTEM STARTER (STABLE MODE)
echo ==========================================

:: CONFIG
set "XAMPP_PATH=C:\xampp"
set "PROJECT_URL=http://localhost/manaklay-system/"
set "CLOUDFLARED=C:\Users\Administrator\Downloads\cloudflared.exe"

echo.
echo [1/3] Checking Apache...

tasklist /FI "IMAGENAME eq httpd.exe" | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo Starting Apache...
    start "" "%XAMPP_PATH%\apache_start.bat"
) else (
    echo Apache already running
)

echo.
echo [2/3] Checking MySQL...

tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    echo Starting MySQL...
    start "" "%XAMPP_PATH%\mysql_start.bat"
) else (
    echo MySQL already running
)

timeout /t 5 >nul

echo.
echo [3/3] Starting Cloudflare Tunnel...
echo ==========================================

start "" "%CLOUDFLARED%" tunnel --url "%PROJECT_URL%"

echo.
echo ==========================================
echo CLOUDFLARE TUNNEL ACTIVE
echo ==========================================
echo DO NOT OPEN LOCALHOST
echo USE ONLY THE CLOUDFLARE LINK SHOWN ABOVE
echo ==========================================

pause