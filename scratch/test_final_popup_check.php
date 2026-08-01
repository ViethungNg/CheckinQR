<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập kinh doanh đăng nhập
$_SESSION['admin_id'] = 2;
$_SESSION['admin_role'] = 'kinhdoanh';

// 1. Chèn 1 lượt checkin walk_in (Khách phát sinh)
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Phát Sinh Local Test', '0911223344', '0911223344', 'walk_in', NOW())")->execute();
$newId = $db->lastInsertId();

$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = $newId");

if ($data && !empty($data['checkins'])) {
    $firstItem = $data['checkins'][0];
    echo "SUCCESS: Kinh Doanh account received notification for Walk-in checkin! ID: {$firstItem['id']}, Name: {$firstItem['full_name']}\n";
} else {
    echo "FAILED: Kinh Doanh account did not receive notification!\n";
}
