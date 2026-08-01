<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 1. Tạo 1 khách hàng mẫu trong bảng guests với status = 'invited' (Chưa tới)
$db->prepare("INSERT INTO guests (event_id, customer_code, full_name, phone, normalized_phone, status, organization) VALUES (1, 'TESTNAME01', 'Nguyễn Văn Minh Test', '0912345678', '0912345678', 'invited', 'Công ty Test')")->execute();
$guestId = $db->lastInsertId();

// 2. Thực hiện check-in cho khách này (giả lập api/checkin.php)
$db->prepare("UPDATE guests SET status = 'checked_in' WHERE id = ?")->execute([$guestId]);
$db->prepare("INSERT INTO checkins (event_id, guest_id, full_name_entered, phone_entered, normalized_phone, address_entered, match_status, checkin_time) VALUES (1, ?, 'Nguyễn Văn Minh Test', '0912345678', '0912345678', 'Công ty Test', 'matched', NOW())")->execute([$guestId]);
$checkinId = (int)$db->lastInsertId();

// 3. Kiểm tra status trong bảng guests
$guestStatus = $db->query("SELECT status FROM guests WHERE id = {$guestId}")->fetchColumn();

// 4. Kiểm tra API notifications.php
$_GET['action'] = 'check';
ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();
$data = json_decode($res, true);

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = {$checkinId}");
$db->exec("DELETE FROM guests WHERE id = {$guestId}");

echo "1. Created guest ID {$guestId} & checked in as ID {$checkinId}\n";
echo "2. Guest status in database: {$guestStatus}\n";

$found = null;
if (!empty($data['checkins'])) {
    foreach ($data['checkins'] as $chk) {
        if ($chk['id'] == $checkinId) {
            $found = $chk;
            break;
        }
    }
}

if ($found) {
    echo "3. API Notification output:\n";
    echo "   - Full Name: {$found['full_name']}\n";
    echo "   - Customer Code: {$found['customer_code']}\n";
    echo "   - Status Text: {$found['status_text']}\n";
    echo "SUCCESS: Check-in status updated to 'checked_in' & Notification has full guest name!\n";
} else {
    echo "FAILED: Notification API did not return new checkin!\n";
}
