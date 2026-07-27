<?php
declare(strict_types=1);

// Định nghĩa đường dẫn gốc
define('ROOT_PATH', dirname(__DIR__));

// Cấu hình thư mục lưu session riêng cho dự án để tránh lỗi Permission Denied ở C:\xampp\tmp
$sessionSavePath = ROOT_PATH . '/storage/sessions';
if (!is_dir($sessionSavePath)) {
    @mkdir($sessionSavePath, 0777, true);
}
if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
    session_save_path($sessionSavePath);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load các file cấu hình và core
require_once ROOT_PATH . '/config/app.php';

// Load biến môi trường
loadEnv(ROOT_PATH . '/.env');

// Set timezone
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

// Bật tắt debug
if (env('APP_DEBUG', 'false') === true || env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Load các thư viện/hàm chung
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';

// Cấu hình Base URL
$appUrl = env('APP_URL', 'http://localhost/CheckinQR');
define('BASE_URL', rtrim($appUrl, '/'));

// Tự động bổ sung cột assigned_user_id và sort_order vào bảng event_tables nếu chưa có và mở rộng cột role trong users
try {
    $dbInstance = Database::getConnection();
    $checkCol = $dbInstance->query("SHOW COLUMNS FROM event_tables LIKE 'assigned_user_id'")->fetch();
    if (!$checkCol) {
        $dbInstance->exec("ALTER TABLE event_tables ADD COLUMN assigned_user_id INT NULL DEFAULT NULL AFTER event_id");
    }
    
    $checkSortCol = $dbInstance->query("SHOW COLUMNS FROM event_tables LIKE 'sort_order'")->fetch();
    if (!$checkSortCol) {
        $dbInstance->exec("ALTER TABLE event_tables ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER location");
    }

    // Mở rộng role sang VARCHAR(50)
    $dbInstance->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'letan'");
} catch (\Throwable $e) {}
