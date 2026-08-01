<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['admin_id'] ?? 0;
if (empty($userId)) {
    jsonResponse(['status' => 'error', 'message' => 'Bạn chưa đăng nhập'], 401);
}

$db = Database::getConnection();
$action = $_REQUEST['action'] ?? '';

if ($action === 'get_user_notification') {
    $enabled = getUserNotificationSetting($userId);
    jsonResponse(['status' => 'success', 'enabled' => $enabled]);
}

if ($action === 'save_user_notification') {
    if (!isPost()) jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
    
    $enabled = !empty($_POST['enabled']) ? 1 : 0;
    $stmt = $db->prepare("
        INSERT INTO user_settings (user_id, setting_key, setting_value) 
        VALUES (?, 'notifications_enabled', ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$userId, (string)$enabled, (string)$enabled]);

    jsonResponse(['status' => 'success', 'message' => 'Đã lưu cài đặt thông báo cá nhân', 'enabled' => (bool)$enabled]);
}

// Các action cấu hình bảng toàn hệ thống dành riêng cho ADMIN
if (!isAdmin()) {
    jsonResponse(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này'], 403);
}

if ($action === 'save_table_config') {
    if (!isPost()) jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);

    $tableName = trim($_POST['table_name'] ?? '');
    $rawColumns = $_POST['columns'] ?? [];

    if (!in_array($tableName, ['dashboard', 'guests', 'checkins'])) {
        jsonResponse(['status' => 'error', 'message' => 'Bảng không hợp lệ']);
    }

    if (is_string($rawColumns)) {
        $columns = json_decode($rawColumns, true);
    } else {
        $columns = (array)$rawColumns;
    }

    if (!is_array($columns) || empty($columns)) {
        jsonResponse(['status' => 'error', 'message' => 'Dữ liệu cột không hợp lệ']);
    }

    $cleanConfig = [];
    foreach ($columns as $col) {
        if (!empty($col['key'])) {
            $cleanConfig[] = [
                'key'     => trim($col['key']),
                'visible' => !empty($col['visible'])
            ];
        }
    }

    $jsonConfig = json_encode($cleanConfig, JSON_UNESCAPED_UNICODE);
    $settingKey = 'table_config_' . $tableName;

    $stmt = $db->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$settingKey, $jsonConfig, $jsonConfig]);

    jsonResponse(['status' => 'success', 'message' => 'Đã lưu cấu hình bảng toàn hệ thống!']);
}

if ($action === 'reset_table_config') {
    if (!isPost()) jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);

    // Reset cả 3 bảng về mặc định trong system_settings (không ảnh hưởng user_settings)
    $db->exec("DELETE FROM system_settings WHERE setting_key LIKE 'table_config_%'");

    jsonResponse(['status' => 'success', 'message' => 'Đã khôi phục cấu hình mặc định cho tất cả các bảng!']);
}

jsonResponse(['status' => 'error', 'message' => 'Hành động không hợp lệ']);
