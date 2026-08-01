<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập admin đăng nhập
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// Insert checkin
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Checkin Vừa Điền Form', '0912999888', '0912999888', 'matched', NOW())")->execute();
$newId = $db->lastInsertId();

$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = $newId");

if ($data && !empty($data['checkins'])) {
    $item = $data['checkins'][0];
    echo "SUCCESS: API checkin notification verified! Name: {$item['full_name']} | created_at_ts: {$item['created_at_ts']} | ID: {$item['id']}\n";
} else {
    echo "FAILED: Checkin item not returned by API!\n";
}
