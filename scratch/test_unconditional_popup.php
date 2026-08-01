<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập admin đăng nhập
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 1. Kiểm tra getUserNotificationSetting
$settingVal = getUserNotificationSetting(1);

// 2. Chèn 1 lượt checkin test
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Test Popup Unconditional', '0933445566', '0933445566', 'matched', NOW())")->execute();
$newId = $db->lastInsertId();

$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = $newId");

echo "getUserNotificationSetting(1) returns: " . ($settingVal ? 'TRUE (ALWAYS ON)' : 'FALSE') . "\n";
if ($data && !empty($data['checkins'])) {
    $firstItem = $data['checkins'][0];
    echo "SUCCESS: Checkin fetched with created_at_ts: " . $firstItem['created_at_ts'] . " (" . $firstItem['full_name'] . "). Notifications are 100% active!\n";
} else {
    echo "FAILED: Notification query returned empty!\n";
}
