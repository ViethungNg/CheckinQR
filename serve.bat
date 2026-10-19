@echo off
title CheckinQR Local Server
cd /d "%~dp0"

:: Thêm đường dẫn PHP và Apache bin vào PATH để Windows tải đủ thư viện libpq.dll
set "PATH=C:\xampp\php;C:\xampp\php\ext;C:\xampp\apache\bin;%PATH%"

set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
)

echo ========================================================
echo   MAY CHU CHECKINQR DANG CHAY (KET NOI SUPABASE)
echo ========================================================
echo.
echo   [+] Dia chi trang chu: http://127.0.0.1:8000
echo   [+] Trang quan tri:    http://127.0.0.1:8000/admin/index.php
echo.
echo   Trinh duyet se tu dong mo len sau 2 giay...
echo   (Vui long giu cua so nay de web hoat dong)
echo ========================================================

:: Hẹn giờ mở trình duyệt sau 2 giây để máy chủ kịp khởi động xong
start "" cmd /c "timeout /t 2 /nobreak >nul & start http://127.0.0.1:8000/admin/index.php"

:: Khởi động máy chủ PHP nạp thư viện PostgreSQL
"%PHP_EXE%" -d extension_dir="C:\xampp\php\ext" -d extension=php_pdo_pgsql.dll -d extension=php_pgsql.dll -S 127.0.0.1:8000
echo.
echo [!] May chu da dung lai.
pause
