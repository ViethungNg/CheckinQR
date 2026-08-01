<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập admin đăng nhập
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 1. Chèn 1 lượt checkin mẫu vừa diễn ra 2 giây trước
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Test Popup Local', '0988777666', '0988777666', 'matched', NOW())")->execute();
$newId = $db->lastInsertId();

// 2. Gọi api/notifications.php?action=check (giả lập lần quét đầu tiên khi mở trang)
$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp dữ liệu test
$db->exec("DELETE FROM checkins WHERE id = $newId");

if ($data && !empty($data['checkins'])) {
    $firstItem = $data['checkins'][0];
    $secAgo = time() - $firstItem['created_at_ts'];
    echo "SUCCESS: Recent checkin detected! Name: {$firstItem['full_name']}, Seconds ago: {$secAgo}s (Will trigger Toast Popup automatically!)\n";
} else {
    echo "FAILED: No checkin detected!\n";
}
