@echo off
title May Chu CheckinQR
cd /d "%~dp0"

:: Thêm đường dẫn PHP và Apache bin vào biến môi trường để nạp đủ thư viện PostgreSQL
set "PATH=C:\xampp\php;C:\xampp\apache\bin;%PATH%"

:: Kích hoạt chế độ đa luồng (Multi-workers) cho PHP Server để không bị nghẽn mạng
set "PHP_CLI_SERVER_WORKERS=4"

set "PHP_CMD=C:\xampp\php\php.exe"
if not exist "%PHP_CMD%" set "PHP_CMD=php"


cls
echo ================================================================
echo        DANG KHOI DONG HE THONG CHECKINQR (SUPABASE CLOUD)
echo ================================================================
echo.
echo   [*] May chu dang khoi tao...
echo   [*] Trinh duyet web se TU DONG MO LEN sau 2 giay!
echo.
echo   ==============================================================
echo   [!] LUU Y QUAN TRONG DE TRANG WEB KHONG BI LOI:
echo   - CUA SO DEN NAY CHINH LA MAY CHU CUA WEB.
echo   - BAN CHI THU NHO CUA SO XUONG, KHONG BAM NUT (X) DE DONG!
echo   - Neu dong cua so nay, web se bi loi "Refused to connect".
echo   ==============================================================
echo.

:: Tự động mở trình duyệt sau 2 giây (đảm bảo máy chủ đã sẵn sàng tiếp nhận)
start "" cmd /c "timeout /t 2 /nobreak >nul & start http://127.0.0.1:8000/admin/index.php"

:: Khởi chạy máy chủ PHP nạp extension PostgreSQL
"%PHP_CMD%" -d extension_dir="C:\xampp\php\ext" -d extension=pdo_pgsql -d extension=pgsql -S 127.0.0.1:8000

echo.
echo [!] May chu da dung.
pause
