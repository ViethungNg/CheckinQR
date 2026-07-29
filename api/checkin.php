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
$luckyDrawCodeInput = trim($_POST['lucky_draw_code'] ?? '');

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

// 2. Tìm kiếm khách dự kiến trong danh sách (theo SĐT hoặc theo Mã trúng giải)
$stmtGuest = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND normalized_phone = ?");
$stmtGuest->execute([$eventId, $normalizedPhone]);
$guest = $stmtGuest->fetch();

$guestMatchedByCode = false;
if (!$guest && !empty($luckyDrawCodeInput)) {
    $stmtGuestByCode = $db->prepare("SELECT * FROM guests WHERE event_id = ? AND LOWER(TRIM(lucky_draw_code)) = LOWER(TRIM(?)) AND lucky_draw_code != ''");
    $stmtGuestByCode->execute([$eventId, $luckyDrawCodeInput]);
    $guest = $stmtGuestByCode->fetch();
    if ($guest) {
        $guestMatchedByCode = true;
    }
}

$confirmCodeAction = trim($_POST['confirm_code_action'] ?? '');

// 3. KIỂM TRA XEM KHÁCH / SĐT / MÃ DỰ THƯỞNG NÀY ĐÃ CHECK-IN TRƯỚC ĐÓ CHƯA?
$existingCheckin = null;

// Lấy danh sách mã dự thưởng cần kiểm tra đối chiếu (từ input người dùng & từ hồ sơ guest)
$codesToCheck = [];
if (!empty($luckyDrawCodeInput)) {
    $codesToCheck[] = strtolower(trim($luckyDrawCodeInput));
}
if ($guest && !empty($guest['lucky_draw_code'])) {
    $codesToCheck[] = strtolower(trim($guest['lucky_draw_code']));
}
$codesToCheck = array_unique(array_filter($codesToCheck));

// Xây dựng câu lệnh kiểm tra đối chiếu bảng checkins
$checkConditions = ["normalized_phone = ?"];
$paramsCheck = [$normalizedPhone];

if ($guest && !empty($guest['id'])) {
    $checkConditions[] = "guest_id = ?";
    $paramsCheck[] = (int)$guest['id'];
}

if (!empty($codesToCheck)) {
    $codePlaceholders = implode(',', array_fill(0, count($codesToCheck), '?'));
    $checkConditions[] = "(lucky_draw_code IS NOT NULL AND lucky_draw_code != '' AND LOWER(TRIM(lucky_draw_code)) IN ($codePlaceholders))";
    foreach ($codesToCheck as $cCode) {
        $paramsCheck[] = $cCode;
    }
}

$checkSql = "SELECT * FROM checkins WHERE event_id = ? AND (" . implode(" OR ", $checkConditions) . ") ORDER BY checkin_time DESC LIMIT 1";
$stmtCheck = $db->prepare($checkSql);
$stmtCheck->execute(array_merge([$eventId], $paramsCheck));
$existingCheckin = $stmtCheck->fetch();

$isGuestAlreadyCheckedIn = ($guest && $guest['status'] === 'checked_in');

// NẾU ĐÃ CHECK-IN TRƯỚC ĐÓ (Dù khớp theo SĐT, Hồ sơ Guest hay Mã dự thưởng) -> Trả về 'already_checked_in' NGAY!
if ($existingCheckin || $isGuestAlreadyCheckedIn) {
    $time = $existingCheckin ? date('H:i d/m/Y', strtotime($existingCheckin['checkin_time'])) : date('H:i d/m/Y');

    // Lấy thông tin bàn
    $tableIdForCheckin = $existingCheckin['table_id'] ?? ($guest['table_id'] ?? null);
    $tableName = null;
    if ($tableIdForCheckin) {
        $stmtT = $db->prepare("SELECT table_name FROM event_tables WHERE id = ?");
        $stmtT->execute([$tableIdForCheckin]);
        $tRow = $stmtT->fetch();
        if ($tRow) {
            $tableName = $tRow['table_name'];
        }
    }

    // Lấy mã dự thưởng hiển thị
    $finalLuckyCode = $existingCheckin['lucky_draw_code'] ?? ($guest['lucky_draw_code'] ?? $luckyDrawCodeInput);

    jsonResponse([
        'status' => 'already_checked_in', 
        'message' => 'Quý khách / Mã dự thưởng này đã check-in thành công trước đó!',
        'data' => [
            'full_name'       => esc($guest['full_name'] ?? ($existingCheckin['full_name_entered'] ?? $fullName)),
            'phone'           => esc($guest['phone'] ?? ($existingCheckin['phone_entered'] ?? $phone)),
            'table_name'      => esc($tableName ?? 'Chưa xếp bàn'),
            'lucky_draw_code' => esc($finalLuckyCode),
            'checkin_time'    => $time,
            'match_status'    => $guest ? 'matched' : ($existingCheckin['match_status'] ?? 'walk_in')
        ]
    ]);
}

// 4. Nếu chưa check-in & khớp bằng Mã dự thưởng (SĐT nhập khác CSDL) -> Hỏi xác nhận đại diện
if ($guestMatchedByCode && $guest) {
    if ($confirmCodeAction === 'reject') {
        // Khách chọn Không phải đại diện / Nhập sai -> Chuyển thành Khách phát sinh (walk_in)
        $guest = null;
        $guestMatchedByCode = false;
    } elseif ($confirmCodeAction === '') {
        // Chưa có hành động xác nhận -> Trả về Popup yêu cầu khách hàng xác nhận
        $tableName = 'Chưa xếp bàn';
        if (!empty($guest['table_id'])) {
            $stmtTable = $db->prepare("SELECT table_name FROM event_tables WHERE id = ?");
            $stmtTable->execute([$guest['table_id']]);
            $tInfo = $stmtTable->fetch();
            if ($tInfo) $tableName = $tInfo['table_name'];
        }

        $origPhone = $guest['phone'] ?? '';
        $maskedPhone = strlen($origPhone) > 6 ? substr($origPhone, 0, 4) . '***' . substr($origPhone, -3) : $origPhone;

        jsonResponse([
            'status' => 'require_confirmation',
            'message' => 'Phát hiện Mã dự thưởng trùng khớp!',
            'data' => [
                'lucky_draw_code'        => esc($guest['lucky_draw_code'] ?? $luckyDrawCodeInput),
                'original_name'          => esc($guest['full_name'] ?? 'Khách BTC'),
                'original_phone_masked'  => esc($maskedPhone),
                'entered_name'           => esc($fullName),
                'entered_phone'          => esc($phone),
                'table_name'             => esc($tableName)
            ]
        ]);
    }
}

$matchStatus = 'walk_in';
$checkinMethod = 'walk_in';
$guestId = null;
$tableId = null;

$luckyDrawCode = !empty($luckyDrawCodeInput) ? $luckyDrawCodeInput : null;
$tableName = null;

if ($guest) {
    $matchStatus = 'matched';
    $checkinMethod = $guestMatchedByCode ? 'lucky_code' : 'phone';
    $guestId = $guest['id'];
    $tableId = $guest['table_id'];
    
    // Nếu trong DB đã có mã do BTC gán thì ưu tiên dùng, nếu chưa có thì lấy mã người dùng vừa nhập
    if (!empty($guest['lucky_draw_code'])) {
        $luckyDrawCode = $guest['lucky_draw_code'];
    } elseif (!empty($luckyDrawCodeInput)) {
        $luckyDrawCode = $luckyDrawCodeInput;
        $updateLucky = $db->prepare("UPDATE guests SET lucky_draw_code = ? WHERE id = ?");
        $updateLucky->execute([$luckyDrawCodeInput, $guestId]);
    }
    
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

$insertSql = "INSERT INTO checkins (event_id, guest_id, table_id, lucky_draw_code, full_name_entered, phone_entered, normalized_phone, address_entered, match_status, checkin_method, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
    $checkinMethod,
    $ip,
    $userAgent
]);

if ($success) {
    $resMsg = ($matchStatus === 'walk_in') 
        ? 'Thông tin chưa có trong danh sách chuẩn bị trước. Vui lòng liên hệ lễ tân!' 
        : 'Check-in thành công!';

    jsonResponse([
        'status' => 'success',
        'message' => $resMsg,
        'data' => [
            'match_status'    => $matchStatus,
            'full_name'       => esc($fullName),
            'phone'           => esc($phone),
            'address'         => esc($address),
            'table_name'      => $tableName,
            'lucky_draw_code' => $luckyDrawCode
        ]
    ]);
} else {
    jsonResponse(['status' => 'error', 'message' => 'Đã xảy ra lỗi khi lưu dữ liệu. Vui lòng thử lại.']);
}
