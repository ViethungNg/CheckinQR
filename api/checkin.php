<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!isPost()) {
    jsonResponse(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ']);
}

$eventId = (int)($_POST['event_id'] ?? 0);
$customerCodeInput = trim($_POST['customer_code'] ?? '');
$action = trim($_POST['action'] ?? 'lookup');
$guestIdInput = (int)($_POST['guest_id'] ?? 0);

$db = Database::getConnection();

// 1. Tự động lấy Sự kiện active nếu event_id không truyền hoặc <= 0
$event = null;
if ($eventId > 0) {
    $stmtEvent = $db->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $stmtEvent->execute([$eventId]);
    $event = $stmtEvent->fetch();
}

if (!$event) {
    $stmtEvent = $db->query("SELECT * FROM events WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $event = $stmtEvent->fetch();
}

if ($event) {
    $eventId = (int)$event['id'];
}

// 2. Tìm kiếm khách hàng duy nhất theo Mã KH (customer_code)
$guest = null;
if ($action === 'confirm_checkin' && $guestIdInput > 0) {
    $stmtGuest = $db->prepare("
        SELECT g.*, t.table_name 
        FROM guests g 
        LEFT JOIN event_tables t ON g.table_id = t.id 
        WHERE g.id = ?
        LIMIT 1
    ");
    $stmtGuest->execute([$guestIdInput]);
    $guest = $stmtGuest->fetch();
}

if (!$guest && !empty($customerCodeInput)) {
    $stmtGuest = $db->prepare("
        SELECT g.*, t.table_name 
        FROM guests g 
        LEFT JOIN event_tables t ON g.table_id = t.id 
        WHERE g.customer_code IS NOT NULL 
          AND g.customer_code != '' 
          AND LOWER(TRIM(g.customer_code)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmtGuest->execute([$customerCodeInput]);
    $guest = $stmtGuest->fetch();
}

// 3. Nếu KHÔNG TÌM THẤY mã khách hàng trong DB
if (!$guest) {
    jsonResponse([
        'status'  => 'not_found',
        'message' => 'Mã khách hàng không tồn tại trong hệ thống. Vui lòng kiểm tra lại mã do NPP cung cấp!'
    ]);
}

$guestId = (int)$guest['id'];
if (!empty($guest['event_id'])) {
    $eventId = (int)$guest['event_id'];
}

// 4. Kiểm tra xem khách này đã check-in trước đó hay chưa
$stmtChk = $db->prepare("SELECT * FROM checkins WHERE guest_id = ? ORDER BY id DESC LIMIT 1");
$stmtChk->execute([$guestId]);
$existingCheckin = $stmtChk->fetch();

if ($guest['status'] === 'checked_in' || $existingCheckin) {
    $time = $existingCheckin ? date('H:i d/m/Y', strtotime($existingCheckin['checkin_time'])) : date('H:i d/m/Y');
    jsonResponse([
        'status'  => 'already_checked_in',
        'message' => 'Quý khách đã check-in trước đó!',
        'data'    => [
            'guest_id'        => $guestId,
            'customer_code'   => esc(!empty($guest['customer_code']) ? $guest['customer_code'] : $customerCodeInput),
            'full_name'       => esc($guest['full_name']),
            'organization'    => esc(!empty($guest['organization']) ? $guest['organization'] : 'Đại lý / Khách mời'),
            'table_name'      => esc($guest['table_name'] ?? 'Chưa xếp bàn'),
            'lucky_draw_code' => esc($guest['lucky_draw_code'] ?? ''),
            'checkin_time'    => $time
        ]
    ]);
}

// 5. Nếu chưa check-in và đang ở bước TRA CỨU -> Yêu cầu KH xác nhận thông tin
if ($action === 'lookup') {
    jsonResponse([
        'status'  => 'require_guest_confirmation',
        'message' => 'Vui lòng xác nhận thông tin của bạn',
        'data'    => [
            'guest_id'        => $guestId,
            'customer_code'   => esc(!empty($guest['customer_code']) ? $guest['customer_code'] : $customerCodeInput),
            'full_name'       => esc($guest['full_name']),
            'organization'    => esc(!empty($guest['organization']) ? $guest['organization'] : 'Đại lý / Khách mời'),
            'phone'           => esc($guest['phone'] ?? ''),
            'table_name'      => esc($guest['table_name'] ?? 'Chưa xếp bàn'),
            'lucky_draw_code' => esc($guest['lucky_draw_code'] ?? '')
        ]
    ]);
}

// 6. Khách hàng bấm nút "Đúng thông tin của tôi - Xác nhận Check-in" ($action === 'confirm_checkin')
$updateGuest = $db->prepare("UPDATE guests SET status = 'checked_in' WHERE id = ?");
$updateGuest->execute([$guestId]);

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

$stmtInsert = $db->prepare("
    INSERT INTO checkins (event_id, guest_id, table_id, lucky_draw_code, full_name_entered, phone_entered, normalized_phone, address_entered, match_status, checkin_method, ip_address, user_agent) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'matched', 'customer_code', ?, ?)
");
$stmtInsert->execute([
    $eventId,
    $guestId,
    $guest['table_id'],
    $guest['lucky_draw_code'],
    $guest['full_name'],
    $guest['phone'],
    $guest['normalized_phone'],
    $guest['organization'],
    $ip,
    $userAgent
]);

jsonResponse([
    'status'  => 'success',
    'message' => 'Check-in thành công!',
    'data'    => [
        'guest_id'        => $guestId,
        'customer_code'   => esc(!empty($guest['customer_code']) ? $guest['customer_code'] : $customerCodeInput),
        'full_name'       => esc($guest['full_name']),
        'organization'    => esc(!empty($guest['organization']) ? $guest['organization'] : 'Đại lý / Khách mời'),
        'table_name'      => esc($guest['table_name'] ?? 'Chưa xếp bàn'),
        'lucky_draw_code' => esc($guest['lucky_draw_code'] ?? '')
    ]
]);
