<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập admin đăng nhập
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 1. Chèn 1 lượt checkin mẫu mới
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Test Realtime Guest', '0999999999', '0999999999', 'walk_in', NOW())")->execute();
$newId = $db->lastInsertId();

// 2. Gọi api/notifications.php với since_id = $newId - 1
$_GET['action'] = 'check';
$_GET['since_id'] = $newId - 1;

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp dữ liệu test
$db->exec("DELETE FROM checkins WHERE id = $newId");

if ($data && !empty($data['new_items'])) {
    echo "SUCCESS: Realtime notification API detected new checkin! Item ID: " . $data['new_items'][0]['id'] . ", Name: " . $data['new_items'][0]['full_name'] . "\n";
} else {
    echo "FAILED: Realtime notification API failed to detect new checkin. Output: " . $res . "\n";
}
