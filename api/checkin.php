<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!isPost()) {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

// Bỏ qua CSRF Token cho API khách gọi (vì khách không có session đăng nhập)
// Nhưng có thể áp dụng Rate Limiting hoặc xác minh dữ liệu nghiêm ngặt

$eventId = (int)($_POST['event_id'] ?? 0);
$fullName = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($eventId) || empty($fullName) || empty($phone)) {
    jsonResponse(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ Họ tên và Số điện thoại']);
}

$normalizedPhone = normalizePhone($phone);
if (strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15) {
    jsonResponse(['status' => 'error', 'message' => 'Số điện thoại không hợp lệ']);
}

$db = Database::getConnection();

// 1. Kiểm tra sự kiện
$stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND status = 'active' AND checkin_enabled = 1");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    jsonResponse(['status' => 'error', 'message' => 'Sự kiện không hợp lệ hoặc đã đóng check-in']);
}

// 2. Kiểm tra khách đã check-in chưa?
$stmtCheck = $db->prepare("SELECT * FROM checkins WHERE event_id = ? AND normalized_phone = ? ORDER BY checkin_time DESC LIMIT 1");
$stmtCheck->execute([$eventId, $normalizedPhone]);
$existingCheckin = $stmtCheck->fetch();

if ($existingCheckin) {
    $time = date('H:i d/m/Y', strtotime($existingCheckin['checkin_time']));
    
    // Lấy thông tin bàn
    $tableName = null;
    if ($existingCheckin['table_id']) {
        $stmtT = $db->prepare("SELECT table_name FROM event_tables WHERE id = ?");
        $stmtT->execute([$existingCheckin['table_id']]);
        $tRow = $stmtT->fetch();
        if ($tRow) {
            $tableName = $tRow['table_name'];
        }
    }

    // Lấy mã quay thưởng
    $luckyCode = $existingCheckin['lucky_draw_code'];
    if (empty($luckyCode) && $existingCheckin['guest_id']) {
        $stmtG = $db->prepare("SELECT lucky_draw_code FROM guests WHERE id = ?");
        $stmtG->execute([$existingCheckin['guest_id']]);
        $gRow = $stmtG->fetch();
        if ($gRow) {
            $luckyCode = $gRow['lucky_draw_code'];
        }
    }

    jsonResponse([
        'status' => 'already_checked_in', 
        'message' => 'Bạn đã check-in thành công trước đó!',
        'data' => [
            'full_name' => esc($existingCheckin['full_name_entered']),
            'phone' => esc($existingCheckin['phone_entered']),
            'table_name' => esc($tableName ?? 'Chưa xếp bàn'),
            'lucky_draw_code' => esc($luckyCode ?? ('#CKI-' . substr($existingCheckin['normalized_phone'], -4))),
            'checkin_time' => $time,
            'match_status' => $existingCheckin['match_status']
        ]
    ]);
}

// 3. Đối chiếu danh sách khách dự kiến
$stmtGuest = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND normalized_phone = ?");
$stmtGuest->execute([$eventId, $normalizedPhone]);
$guest = $stmtGuest->fetch();

$matchStatus = 'walk_in';
$guestId = null;
$tableId = null;

$luckyDrawCode = null;
$tableName = null;

if ($guest) {
    $matchStatus = 'matched';
    $guestId = $guest['id'];
    $tableId = $guest['table_id'];
    $luckyDrawCode = $guest['lucky_draw_code'];
    
    // Lấy tên bàn
    if ($tableId) {
        $stmtTable = $db->prepare("SELECT table_name FROM event_tables WHERE id = ?");
        $stmtTable->execute([$tableId]);
        $tableInfo = $stmtTable->fetch();
        if ($tableInfo) {
            $tableName = $tableInfo['table_name'];
        }
    }
    
    // Cập nhật trạng thái khách dự kiến
    $updateGuest = $db->prepare("UPDATE guests SET status = 'checked_in' WHERE id = ?");
    $updateGuest->execute([$guestId]);
}

// 4. Lưu lượt checkin
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

$insertSql = "INSERT INTO checkins (event_id, guest_id, table_id, lucky_draw_code, full_name_entered, phone_entered, normalized_phone, address_entered, match_status, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmtInsert = $db->prepare($insertSql);
$success = $stmtInsert->execute([
    $eventId,
    $guestId,
    $tableId,
    $luckyDrawCode,
    $fullName,
    $phone,
    $normalizedPhone,
    $address,
    $matchStatus,
    $ip,
    $userAgent
]);

if ($success) {
    jsonResponse([
        'status' => 'success',
        'message' => 'Check-in thành công!',
        'data' => [
            'match_status' => $matchStatus,
            'table_name' => $tableName,
            'lucky_draw_code' => $luckyDrawCode
        ]
    ]);
} else {
    jsonResponse(['status' => 'error', 'message' => 'Đã xảy ra lỗi khi lưu dữ liệu. Vui lòng thử lại.']);
}
