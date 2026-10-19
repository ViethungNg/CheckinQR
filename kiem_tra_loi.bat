@echo off
chcp 65001 >nul
title Kiem Tra Ket Noi CheckinQR
cd /d "%~dp0"

echo ================================================================
echo           DANG KIEM TRA HE THONG VA KET NOI SUPABASE
echo ================================================================
echo.

set "PATH=C:\xampp\php;C:\xampp\apache\bin;%PATH%"
set "PHP_CMD=C:\xampp\php\php.exe"
if not exist "%PHP_CMD%" set "PHP_CMD=php"

echo [1/3] Kiem tra phien ban PHP... > ket_qua.txt
"%PHP_CMD%" -v >> ket_qua.txt 2>&1

echo. >> ket_qua.txt
echo [2/3] Kiem tra driver pdo_pgsql... >> ket_qua.txt
"%PHP_CMD%" -d extension_dir="C:\xampp\php\ext" -d extension=pdo_pgsql -m | findstr /i "pgsql" >> ket_qua.txt 2>&1

echo. >> ket_qua.txt
echo [3/3] Kiem tra ket noi Database Supabase... >> ket_qua.txt
"%PHP_CMD%" -d extension_dir="C:\xampp\php\ext" -d extension=pdo_pgsql -d extension=pgsql -r "require 'config/bootstrap.php'; try { $db = Database::getConnection(); echo '>>> KET NOI SUPABASE THANH CONG! <<<'; } catch (Throwable $e) { echo '>>> LOI: ' . $e->getMessage(); }" >> ket_qua.txt 2>&1

type ket_qua.txt
echo.
echo ================================================================
echo Da luu chi tiet vao file ket_qua.txt trong thu muc du an.
echo ================================================================
pause
