<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// 1. Giả lập phiên làm việc Admin
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 2. Chèn 1 lượt check-in thử nghiệm
$stmt = $db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, address_entered, match_status, checkin_time) VALUES (1, 'Khách Test Thông Báo Mới', '0987654321', '0987654321', 'Đại lý Test', 'matched', NOW())");
$stmt->execute();
$insertedId = $db->lastInsertId();

// 3. Gọi API notifications.php
$_GET['action'] = 'check';
ob_start();
require __DIR__ . '/../api/notifications.php';
$output = ob_get_clean();

$data = json_decode($output, true);

// 4. Dọn dẹp dữ liệu test
$db->exec("DELETE FROM checkins WHERE id = " . (int)$insertedId);

if ($data && isset($data['status']) && $data['status'] === 'success' && !empty($data['checkins'])) {
    $first = $data['checkins'][0];
    echo "TEST PASSED: API notifications.php returned status success! Max ID: {$data['max_id']} | First checkin: {$first['full_name']} (ID: {$first['id']})\n";
} else {
    echo "TEST FAILED: Invalid output from notifications.php:\n" . $output . "\n";
}
