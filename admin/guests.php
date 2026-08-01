<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

$message = '';
$error = '';
if (isPost()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'checkin_ho') {
        if (!isAdmin() && !isLeTan()) {
            if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này.']);
                exit;
            }
            $error = 'Bạn không có quyền thực hiện thao tác này.';
        } else {
            $id = (int)$_POST['id'];
            $stmtG = $db->prepare("SELECT g.*, t.table_name FROM guests g LEFT JOIN event_tables t ON g.table_id = t.id WHERE g.id = ?");
            $stmtG->execute([$id]);
            $guestToCheckin = $stmtG->fetch();
            
            if (!$guestToCheckin) {
                $errMsg = 'Khách hàng không tồn tại.';
                if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'error', 'message' => $errMsg]);
                    exit;
                }
                $error = $errMsg;
            } elseif ($guestToCheckin['status'] === 'checked_in') {
                $errMsg = 'Khách "' . esc($guestToCheckin['full_name']) . '" đã được check-in trước đó rồi.';
                if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
                    if (ob_get_length()) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'status'          => 'already_checked_in',
                        'message'         => $errMsg,
                        'guest_id'        => $id,
                        'table_name'      => esc($guestToCheckin['table_name'] ?? 'Chưa xếp bàn'),
                        'lucky_draw_code' => esc($guestToCheckin['lucky_draw_code'] ?? '-')
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $error = $errMsg;
            } else {
                $customerCode = !empty($guestToCheckin['customer_code']) ? $guestToCheckin['customer_code'] : null;
                $luckyCode    = !empty($guestToCheckin['lucky_draw_code']) ? $guestToCheckin['lucky_draw_code'] : null;
                $phoneEntered = !empty($guestToCheckin['phone']) ? $guestToCheckin['phone'] : '';
                $normPhone    = !empty($guestToCheckin['phone']) ? normalizePhone($guestToCheckin['phone']) : '';
                $orgEntered   = !empty($guestToCheckin['organization']) ? $guestToCheckin['organization'] : '';

                $db->prepare("UPDATE guests SET status = 'checked_in' WHERE id = ?")->execute([$id]);

                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

                try {
                    $stmtIns = $db->prepare("
                        INSERT INTO checkins (
                            event_id, guest_id, table_id, customer_code, lucky_draw_code,
                            full_name_entered, phone_entered, normalized_phone, address_entered,
                            match_status, checkin_method, ip_address, user_agent
                        ) VALUES (
                            ?, ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            'matched', 'manual_letan', ?, ?
                        )
                    ");
                    $stmtIns->execute([
                        $guestToCheckin['event_id'],
                        $id,
                        $guestToCheckin['table_id'],
                        $customerCode,
                        $luckyCode,
                        $guestToCheckin['full_name'],
                        $phoneEntered,
                        $normPhone,
                        $orgEntered,
                        $ip,
                        $userAgent
                    ]);
                } catch (\Throwable $e) {
                    error_log('Insert checkin_ho error: ' . $e->getMessage());
                }

                $succMsg = 'Check-in hộ khách "' . esc($guestToCheckin['full_name']) . '" thành công!';
                if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
                    if (ob_get_length()) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'status'          => 'success',
                        'message'         => $succMsg,
                        'guest_id'        => $id,
                        'table_name'      => esc($guestToCheckin['table_name'] ?? 'Chưa xếp bàn'),
                        'lucky_draw_code' => esc($luckyCode ?? '-')
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $message = $succMsg;
            }
        }
    } elseif (isAdmin()) {
        if ($action === 'add') {
            $eventId = (int)$_POST['event_id'];
            $customerCode = trim($_POST['customer_code'] ?? '');
            if ($customerCode === '') $customerCode = null;
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
            $luckyDrawCode = trim($_POST['lucky_draw_code'] ?? '');
            $organization = trim($_POST['organization'] ?? '');
            $status = $_POST['status'] ?? 'invited';
            
            $normalizedPhone = normalizePhone($phone);
            
            if (empty($fullName) || empty($eventId)) {
                $error = 'Vui lòng chọn sự kiện và nhập họ tên';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO guests (event_id, customer_code, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$eventId, $customerCode, $fullName, $phone, $normalizedPhone, $tableId, $luckyDrawCode, $organization, $status]);
                    $message = 'Thêm khách thành công!';
                } catch(PDOException $e) {
                    $error = 'Lỗi thêm khách (Có thể trùng số điện thoại hoặc trùng Mã KH).';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $eventId = (int)$_POST['event_id'];
            $customerCode = trim($_POST['customer_code'] ?? '');
            if ($customerCode === '') $customerCode = null;
            $fullName = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
            $luckyDrawCode = trim($_POST['lucky_draw_code'] ?? '');
            $organization = trim($_POST['organization'] ?? '');
            $status = $_POST['status'] ?? 'invited';
            
            $normalizedPhone = normalizePhone($phone);
            
            try {
                if (empty($tableId)) {
                    $db->prepare("UPDATE checkins SET table_id = NULL, guest_id = NULL, match_status = 'walk_in' WHERE guest_id = ?")->execute([$id]);
                    $db->prepare("DELETE FROM guests WHERE id = ?")->execute([$id]);
                    $message = 'Đã chuyển khách thành Khách phát sinh và xóa khỏi Danh sách khách hàng!';
                } else {
                    $stmt = $db->prepare("UPDATE guests SET event_id=?, customer_code=?, full_name=?, phone=?, normalized_phone=?, table_id=?, lucky_draw_code=?, organization=?, status=? WHERE id=?");
                    $stmt->execute([$eventId, $customerCode, $fullName, $phone, $normalizedPhone, $tableId, $luckyDrawCode, $organization, $status, $id]);
                    
                    // Đồng bộ dữ liệu với bảng checkins
                    if ($status !== 'checked_in') {
                        // Nếu chuyển về "Chưa tới", xóa hẳn lượt checkin trong bảng checkins
                        $db->prepare("DELETE FROM checkins WHERE guest_id = ?")->execute([$id]);
                    } else {
                        // Nếu chuyển sang "Đã checkin", tạo hoặc cập nhật lượt checkin trong bảng checkins
                        $chkCount = (int)$db->query("SELECT COUNT(*) FROM checkins WHERE guest_id = {$id}")->fetchColumn();
                        if ($chkCount === 0) {
                            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                            $stmtIns = $db->prepare("
                                INSERT INTO checkins (
                                    event_id, guest_id, table_id, customer_code, lucky_draw_code,
                                    full_name_entered, phone_entered, normalized_phone, address_entered,
                                    match_status, checkin_method, ip_address, user_agent
                                ) VALUES (
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?,
                                    'matched', 'manual_letan', ?, ?
                                )
                            ");
                            $stmtIns->execute([
                                $eventId,
                                $id,
                                $tableId,
                                null,
                                $luckyDrawCode ?: null,
                                $fullName,
                                $phone,
                                $normalizedPhone,
                                $organization,
                                $ip,
                                $userAgent
                            ]);
                        } else {
                            $db->prepare("UPDATE checkins SET table_id = ?, lucky_draw_code = ?, full_name_entered = ?, phone_entered = ?, normalized_phone = ?, address_entered = ? WHERE guest_id = ?")
                               ->execute([$tableId, $luckyDrawCode ?: null, $fullName, $phone, $normalizedPhone, $organization, $id]);
                        }
                    }

                    $message = 'Cập nhật thông tin khách thành công!';
                }
            } catch(PDOException $e) {
                $error = 'Lỗi cập nhật khách: ' . $e->getMessage();
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $db->prepare("DELETE FROM checkins WHERE guest_id = ?")->execute([$id]);
            $stmt = $db->prepare("DELETE FROM guests WHERE id=?");
            $stmt->execute([$id]);
            $message = 'Xóa khách thành công!';
        }
    } else {
        $error = 'Bạn không có quyền thực hiện thao tác này.';
    }
}

// Lấy danh sách sự kiện và bàn để đưa vào form
$events = $db->query("SELECT id, event_name FROM events ORDER BY id DESC")->fetchAll();
$tablesList = $db->query("SELECT id, table_name, table_code, event_id FROM event_tables ORDER BY sort_order ASC, id ASC")->fetchAll();

// Lấy tham số Tìm kiếm & Sắp xếp
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'id_desc';

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $normalizedSearch = normalizePhone($search);
    $searchLike = "%$search%";
    $normLike = "%$normalizedSearch%";
    $whereClauses[] = "(g.full_name LIKE ? OR g.phone LIKE ? OR g.normalized_phone LIKE ? OR g.customer_code LIKE ? OR g.lucky_draw_code LIKE ? OR t.table_name LIKE ? OR t.table_code LIKE ?)";
    $params = [$searchLike, $searchLike, $normLike, $searchLike, $searchLike, $searchLike, $searchLike];
}

if (isKinhDoanh()) {
    $whereClauses[] = "t.assigned_user_id = ?";
    $params[] = $_SESSION['admin_id'];
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

$orderSql = "ORDER BY g.id DESC";
if ($sort === 'table_asc') {
    $orderSql = "ORDER BY (CASE WHEN g.table_id IS NULL OR g.table_id = 0 THEN 1 ELSE 0 END) ASC, t.sort_order ASC, t.id ASC, g.id DESC";
} elseif ($sort === 'table_desc') {
    $orderSql = "ORDER BY (CASE WHEN g.table_id IS NULL OR g.table_id = 0 THEN 1 ELSE 0 END) ASC, t.sort_order DESC, t.id DESC, g.id DESC";
} elseif ($sort === 'code_asc') {
    $orderSql = "ORDER BY CAST(g.lucky_draw_code AS UNSIGNED) ASC, g.lucky_draw_code ASC, g.id DESC";
} elseif ($sort === 'code_desc') {
    $orderSql = "ORDER BY CAST(g.lucky_draw_code AS UNSIGNED) DESC, g.lucky_draw_code DESC, g.id DESC";
}

$stmtGuests = $db->prepare("
    SELECT g.*, e.event_name, t.table_name, t.table_code 
    FROM guests g 
    LEFT JOIN events e ON g.event_id = e.id 
    LEFT JOIN event_tables t ON g.table_id = t.id 
    {$whereSql}
    {$orderSql}
");
$stmtGuests->execute($params);
$guests = $stmtGuests->fetchAll();

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    $guestList = [];
    foreach ($guests as $g) {
        $guestList[] = [
            'id'              => (int)$g['id'],
            'event_id'        => (int)$g['event_id'],
            'customer_code'   => esc($g['customer_code'] ?? ''),
            'full_name'       => esc($g['full_name']),
            'phone'           => esc($g['phone']),
            'organization'    => esc($g['organization'] ?? ''),
            'table_id'        => $g['table_id'] ? (int)$g['table_id'] : null,
            'table_name'      => esc($g['table_name'] ?? ''),
            'table_code'      => esc($g['table_code'] ?? ''),
            'lucky_draw_code' => esc($g['lucky_draw_code'] ?? ''),
            'status'          => esc($g['status']),
        ];
    }
    
    echo json_encode([
        'status'       => 'success',
        'total_count'  => count($guestList),
        'guests'       => $guestList,
        'is_admin'     => isAdmin(),
        'csrf_token'   => generateCsrfToken(),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT - Checkin - Danh sách khách hàng</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .content-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .btn { padding: 8px 15px; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge.invited { background: #e3f2fd; color: #1565c0; }
        .badge.checked_in { background: #e8f5e9; color: #2e7d32; }
        
        /* Modal CSS */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;}
        .modal-content { background-color: #fff; margin: 2% auto; padding: 20px; border-radius: 8px; width: 550px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .sort-header { color: #d32f2f; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .sort-header:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Danh sách khách hàng (<span id="guest-count-title"><?php echo count($guests); ?></span>)</h1>
        </div>
        
        <?php if ($message): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>, 'success'); });</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>, 'error'); });</script>
        <?php endif; ?>

        <div class="content-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <?php if(isAdmin()): ?>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="openAddModal()">Thêm khách thủ công</button>
                    <a href="import.php" class="btn btn-success">Import từ Excel (.xlsx)</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thanh Lọc & Sắp Xếp Thông Minh (Live Typing Search) -->
            <form method="GET" action="" id="search-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; background: #f8f9fa; padding: 12px 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" id="search-input" name="search" value="<?php echo esc($search); ?>" placeholder="Tìm theo SĐT, Bàn, Mã dự thưởng, Họ tên..." class="form-control" oninput="liveSearchGuests(this.value)" autocomplete="off">
                </div>
                
                <div style="min-width: 220px;">
                    <select name="sort" class="form-control" onchange="this.form.submit()" style="cursor: pointer; background: #fff; font-weight: 500;">
                        <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>Mới nhất trước</option>
                        <option value="table_asc" <?php echo $sort === 'table_asc' ? 'selected' : ''; ?>>Thứ tự Bàn: Tăng dần (1 ➔ N)</option>
                        <option value="table_desc" <?php echo $sort === 'table_desc' ? 'selected' : ''; ?>>Thứ tự Bàn: Giảm dần (N ➔ 1)</option>
                        <option value="code_asc" <?php echo $sort === 'code_asc' ? 'selected' : ''; ?>>Mã dự thưởng: Tăng dần (0 ➔ 9)</option>
                        <option value="code_desc" <?php echo $sort === 'code_desc' ? 'selected' : ''; ?>>Mã dự thưởng: Giảm dần (9 ➔ 0)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 18px;">Tìm kiếm</button>
                <?php if(!empty($search) || $sort !== 'id_desc'): ?>
                    <a href="guests.php" class="btn" style="background: #757575; color: white;">Xóa lọc</a>
                <?php endif; ?>
            </form>

            <?php $guestCols = getTableColumnsConfig('guests'); ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($guestCols as $c): ?>
                                <?php if (!empty($c['visible'])): ?>
                                    <?php if ($c['key'] === 'actions' && !isAdmin() && !isLeTan()) continue; ?>
                                    <th><?php echo esc($c['label']); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="guests-table-body">
                        <?php foreach($guests as $guest): ?>
                        <tr class="<?php echo $guest['status'] === 'checked_in' ? 'row-checked-in' : ''; ?>">
                            <?php foreach ($guestCols as $c): ?>
                                <?php if (!empty($c['visible'])): ?>
                                    <?php switch($c['key']):
                                        case 'customer_code': ?>
                                            <td>
                                                <?php if (!empty($guest['customer_code'])): ?>
                                                    <strong style="color: #0284c7; font-weight:700; font-size: 0.88rem;"><?php echo esc($guest['customer_code']); ?></strong>
                                                <?php else: ?>
                                                    <span style="color: #aaa;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'full_name': ?>
                                            <td><strong><?php echo esc($guest['full_name']); ?></strong></td>
                                            <?php break;
                                        case 'phone': ?>
                                            <td><?php echo esc($guest['phone']); ?></td>
                                            <?php break;
                                        case 'organization': ?>
                                            <td><?php echo esc($guest['organization'] ?? '-'); ?></td>
                                            <?php break;
                                        case 'table_name': ?>
                                            <td>
                                                <?php if (!empty($guest['table_name'])): ?>
                                                    <span style="font-weight: 800; color: #1b5e20; background: #e8f5e9; border: 1.5px solid #81c784; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">
                                                        <?php echo esc($guest['table_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #888; font-style: italic;">Chưa xếp</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'lucky_draw_code': ?>
                                            <td>
                                                <?php if (!empty($guest['lucky_draw_code'])): ?>
                                                    <span style="font-weight: 800; color: #6a1b9a; background: #f3e5f5; border: 1.5px solid #ba68c8; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">
                                                        <?php echo esc($guest['lucky_draw_code']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #aaa;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'status': ?>
                                            <td>
                                                <span class="badge <?php echo esc($guest['status']); ?>">
                                                    <?php echo $guest['status'] === 'checked_in' ? 'Đã checkin' : 'Chưa tới'; ?>
                                                </span>
                                            </td>
                                            <?php break;
                                        case 'actions': ?>
                                            <?php if (isAdmin() || isLeTan()): ?>
                                                <td style="text-align: center;">
                                                    <div class="action-btns-wrapper" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                                        <div style="width: 105px; display: inline-flex; align-items: center;">
                                                            <?php if ($guest['status'] === 'invited'): ?>
                                                                <button type="button" class="btn btn-action-checkin" onclick='checkinHoGuest(<?php echo (int)$guest["id"]; ?>, <?php echo json_encode($guest["full_name"]); ?>)'>Check-in hộ</button>
                                                            <?php else: ?>
                                                                <span class="badge checked_in" style="margin:0; font-size: 0.78rem;">Đã checkin</span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if(isAdmin()): ?>
                                                            <button type="button" class="btn btn-action-edit" onclick='openEditModal(<?php echo json_encode($guest); ?>)'>Sửa</button>
                                                            <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa khách này?');">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $guest['id']; ?>">
                                                                <button type="submit" class="btn btn-action-delete">Xóa</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                            <?php break;
                                    endswitch; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div id="guestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Thêm Khách Mời</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="guestId" value="">
            
            <div class="form-group">
                <label>Sự kiện *</label>
                <select name="event_id" id="eventId" class="form-control" required onchange="filterTables(this.value)">
                    <option value="">-- Chọn sự kiện --</option>
                    <?php foreach($events as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo esc($e['event_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Mã khách hàng (Mã KH / Mã đối chiếu)</label>
                <input type="text" name="customer_code" id="customerCode" class="form-control" placeholder="Ví dụ: KH001, AB002... (Để trống nếu chưa có)">
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="full_name" id="fullName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>Chọn Bàn ngồi</label>
                    <select name="table_id" id="tableId" class="form-control">
                        <option value="">-- Chưa xếp bàn --</option>
                        <!-- Tables will be loaded by JS -->
                    </select>
                </div>
                <div class="form-group">
                    <label>Mã bốc thăm (nếu có)</label>
                    <input type="text" name="lucky_draw_code" id="luckyCode" class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Đơn vị công tác</label>
                <input type="text" name="organization" id="organization" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Trạng thái</label>
                <select name="status" id="status" class="form-control">
                    <option value="invited">Chưa đến (Invited)</option>
                    <option value="checked_in">Đã Check-in</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<!-- Modal Thông Báo Kết Quả Check-in Hộ -->
<div id="checkinResultModal" class="modal" style="z-index: 100000; display: none; justify-content: center; align-items: center;">
    <div class="modal-content" style="max-width: 460px; text-align: center; border-radius: 16px; padding: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);">
        <h2 id="resModalTitle" style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">Check-in Hộ Thành Công</h2>
        <div id="resModalSubtitle" style="font-size: 0.88rem; color: #64748b; margin-bottom: 16px;">Đã ghi nhận thông tin tham dự của khách hàng</div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; text-align: left; margin-bottom: 16px;">
            <div style="font-size: 0.92rem; color: #0f172a; margin-bottom: 6px;">
                <strong style="color: #475569;">Họ tên:</strong> <span id="resModalGuestName" style="font-weight: 800; color: #0f172a;"></span>
            </div>
            <div style="font-size: 0.92rem; color: #0f172a;">
                <strong style="color: #475569;">Số điện thoại:</strong> <span id="resModalGuestPhone" style="font-weight: 700;"></span>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 20px;">
            <div style="flex: 1; min-width: 140px; background: #e8f5e9; border: 2px solid #66bb6a; border-radius: 12px; padding: 12px 14px; text-align: center; box-shadow: 0 3px 8px rgba(46,125,50,0.12);">
                <div style="font-size: 0.75rem; color: #2e7d32; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Vị trí ngồi</div>
                <div id="resModalTableName" style="font-size: 1.25rem; font-weight: 800; color: #1b5e20;">Chưa xếp</div>
            </div>
            
            <div style="flex: 1; min-width: 140px; background: #f3e5f5; border: 2px solid #ab47bc; border-radius: 12px; padding: 12px 14px; text-align: center; box-shadow: 0 3px 8px rgba(123,31,162,0.12);">
                <div style="font-size: 0.75rem; color: #7b1fa2; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Mã bốc thăm</div>
                <div id="resModalLuckyCode" style="font-size: 1.25rem; font-weight: 800; color: #4a148c;">-</div>
            </div>
        </div>

        <div style="display: flex; gap: 12px; justify-content: center; width: 100%; margin-top: 16px;">
            <button type="button" class="btn btn-primary" onclick="closeCheckinResultModal()" style="flex: 1; padding: 12px; font-size: 0.95rem; font-weight: 700; border-radius: 8px; background: #2e7d32; border: none; cursor: pointer; color: white;">
                Hoàn Tất
            </button>
            <button type="button" class="btn" onclick="closeCheckinResultModal()" style="flex: 1; padding: 12px; font-size: 0.95rem; font-weight: 700; border-radius: 8px; background: #64748b; border: none; cursor: pointer; color: white;">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('guestModal');
    const allTables = <?php echo json_encode($tablesList); ?>;
    const tableSelect = document.getElementById('tableId');
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const canCheckinHo = <?php echo (isAdmin() || isLeTan()) ? 'true' : 'false'; ?>;
    const guestColsConfig = <?php echo json_encode($guestCols); ?>;
    let csrfTokenValue = '<?php echo generateCsrfToken(); ?>';
    
    window.allGuestsMap = {};
    <?php foreach($guests as $g): ?>
    window.allGuestsMap[<?php echo (int)$g['id']; ?>] = <?php echo json_encode([
        'id'              => (int)$g['id'],
        'event_id'        => (int)$g['event_id'],
        'customer_code'   => $g['customer_code'] ?? '',
        'full_name'       => $g['full_name'],
        'phone'           => $g['phone'],
        'organization'    => $g['organization'] ?? '',
        'table_id'        => $g['table_id'] ? (int)$g['table_id'] : null,
        'lucky_draw_code' => $g['lucky_draw_code'] ?? '',
        'status'          => $g['status'],
    ]); ?>;
    <?php endforeach; ?>

    function showCheckinResultPopup(data) {
        document.getElementById('resModalTitle').textContent = data.title || 'Thông báo';
        document.getElementById('resModalSubtitle').textContent = data.subtitle || '';
        document.getElementById('resModalGuestName').textContent = data.fullName || '';
        document.getElementById('resModalGuestPhone').textContent = data.phone || '';
        document.getElementById('resModalTableName').textContent = data.tableName || 'Chưa xếp';
        document.getElementById('resModalLuckyCode').textContent = data.luckyCode || '-';
        
        const modal = document.getElementById('checkinResultModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeCheckinResultModal() {
        const modal = document.getElementById('checkinResultModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    document.addEventListener('keydown', function(e) {
        const resultModal = document.getElementById('checkinResultModal');
        if (resultModal && getComputedStyle(resultModal).display !== 'none') {
            const key = e.key ? e.key.toLowerCase() : '';
            if (key === 'y' || key === 'n' || key === 'enter' || key === 'escape' || key === ' ') {
                e.preventDefault();
                closeCheckinResultModal();
            }
        }
    });

    function checkinHoGuest(guestId, guestName) {
        const guestObj = window.allGuestsMap[guestId] || {};
        const guestPhone = guestObj.phone || '';

        const executeCheckin = async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'checkin_ho');
                formData.append('id', guestId);
                formData.append('csrf_token', csrfTokenValue);
                formData.append('ajax', '1');

                const response = await fetch('guests.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const resText = await response.text();
                let result = {};
                try {
                    const cleanText = resText.trim().replace(/^\uFEFF/, '');
                    const jsonMatch = cleanText.match(/\{[\s\S]*\}/);
                    result = JSON.parse(jsonMatch ? jsonMatch[0] : cleanText);
                } catch(err) {
                    console.error('Raw checkin_ho response:', resText);
                    result = { status: 'error', message: 'Lỗi phản hồi từ máy chủ' };
                }

                if (result.status === 'success') {
                    showCheckinResultPopup({
                        title: 'Check-in Hộ Thành Công',
                        subtitle: 'Đã hoàn tất thủ tục tiếp đón cho khách hàng',
                        fullName: guestName,
                        phone: guestPhone,
                        tableName: result.table_name || 'Chưa xếp bàn',
                        luckyCode: result.lucky_draw_code || '-'
                    });
                    fetchRealtimeGuests(true);
                } else if (result.status === 'already_checked_in') {
                    showCheckinResultPopup({
                        title: 'Khách Đã Check-in Trước Đó',
                        subtitle: result.message || 'Khách hàng này đã được ghi nhận check-in.',
                        fullName: guestName,
                        phone: guestPhone,
                        tableName: result.table_name || 'Chưa xếp bàn',
                        luckyCode: result.lucky_draw_code || '-'
                    });
                    fetchRealtimeGuests(true);
                } else {
                    showCheckinResultPopup({
                        title: 'Không thể Check-in',
                        subtitle: result.message || 'Khách hàng không hợp lệ hoặc có lỗi xảy ra.',
                        fullName: guestName,
                        phone: guestPhone,
                        tableName: result.table_name || 'Chưa xếp bàn',
                        luckyCode: '-'
                    });
                    fetchRealtimeGuests(true);
                }
            } catch (e) {
                console.error('Checkin ho error:', e);
                showCheckinResultPopup({
                    title: 'Lỗi kết nối',
                    subtitle: 'Không thể kết nối đến máy chủ. Vui lòng thử lại!',
                    fullName: guestName,
                    phone: guestPhone,
                    tableName: 'Lỗi',
                    luckyCode: '-'
                });
            }
        };

        if (typeof window.showConfirmPopup === 'function') {
            window.showConfirmPopup({
                title: 'Check-in hộ khách hàng',
                message: `Bạn có chắc chắn muốn thực hiện **Check-in hộ** cho khách <strong>${guestName}</strong>?`,
                okText: 'Xác nhận Check-in',
                danger: false,
                onConfirm: executeCheckin
            });
        } else {
            executeCheckin();
        }
    }

    function filterTables(eventId, selectedTableId = null) {
        tableSelect.innerHTML = '<option value="">-- Chưa xếp bàn --</option>';
        
        allTables.forEach(t => {
            if (!eventId || t.event_id == eventId) {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.table_name;
                if (t.id == selectedTableId) opt.selected = true;
                tableSelect.appendChild(opt);
            }
        });
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Khách Mới';
        document.getElementById('formAction').value = 'add';
        document.getElementById('guestId').value = '';
        
        const eventSelect = document.getElementById('eventId');
        if (eventSelect.options.length > 1) {
            eventSelect.selectedIndex = 1;
        } else {
            eventSelect.value = '';
        }
        
        if (document.getElementById('customerCode')) document.getElementById('customerCode').value = '';
        document.getElementById('fullName').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('luckyCode').value = '';
        document.getElementById('organization').value = '';
        document.getElementById('status').value = 'invited';
        
        filterTables(eventSelect.value);
        modal.style.display = 'block';
    }
    
    function openEditModal(data) {
        if (!data) return;
        document.getElementById('modalTitle').innerText = 'Sửa Thông Tin Khách';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('guestId').value = data.id;
        document.getElementById('eventId').value = data.event_id;
        if (document.getElementById('customerCode')) document.getElementById('customerCode').value = data.customer_code || '';
        document.getElementById('fullName').value = data.full_name;
        document.getElementById('phone').value = data.phone;
        document.getElementById('luckyCode').value = data.lucky_draw_code;
        document.getElementById('organization').value = data.organization;
        document.getElementById('status').value = data.status;
        
        filterTables(data.event_id, data.table_id);
        
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
    function liveSearchGuests(val) {
        const query = (val || '').toLowerCase().trim();
        const tbody = document.getElementById('guests-table-body');
        if (!tbody) return;
        
        const rows = tbody.querySelectorAll('tr');
        let count = 0;
        
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (query === '' || text.includes(query)) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });

        const counter = document.getElementById('guest-count-title');
counter.textContent = count;
    }

    let lastGuestsHash = '';

    async function fetchRealtimeGuests(force = false) {
        try {
            const params = new URLSearchParams(window.location.search);
            params.set('ajax', '1');
            params.set('_t', Date.now());

            const response = await fetch(`guests.php?${params.toString()}`, { cache: 'no-store' });
            if (!response.ok) return;

            const textData = await response.text();
            if (!force && textData === lastGuestsHash) return;
            lastGuestsHash = textData;

            const result = JSON.parse(textData);
            if (result.status === 'success') {
                csrfTokenValue = result.csrf_token || csrfTokenValue;
                
                const counter = document.getElementById('guest-count-title');
                const searchInput = document.getElementById('search-input');
                if (counter && (!searchInput || !searchInput.value)) {
                    counter.textContent = result.total_count;
                }

                const tbody = document.getElementById('guests-table-body');
                if (!tbody) return;

                const hasActions = canCheckinHo || isAdminUser;

                if (result.guests.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${hasActions ? 8 : 7}" style="text-align:center; color:#777; padding:20px;">Không có dữ liệu khách hàng</td></tr>`;
                    return;
                }

                let html = '';
                result.guests.forEach(item => {
                    window.allGuestsMap[item.id] = {
                        id: item.id,
                        event_id: item.event_id,
                        customer_code: item.customer_code,
                        full_name: item.full_name,
                        phone: item.phone,
                        organization: item.organization,
                        table_id: item.table_id,
                        lucky_draw_code: item.lucky_draw_code,
                        status: item.status
                    };

                    const isCheckedIn = item.status === 'checked_in';
                    const rowClass = isCheckedIn ? 'row-checked-in' : '';

                    const customerCodeHtml = item.customer_code 
                        ? `<strong style="color: #0284c7; font-family: monospace; font-size: 0.88rem;">${item.customer_code}</strong>`
                        : `<span style="color: #aaa;">-</span>`;

                    const tableNameStr = item.table_name || '';
                    const tableHtml = tableNameStr 
                        ? `<span style="font-weight: 800; color: #1b5e20; background: #e8f5e9; border: 1.5px solid #81c784; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">${tableNameStr}</span>`
                        : `<span style="color: #888; font-style: italic;">Chưa xếp</span>`;

                    const luckyHtml = item.lucky_draw_code
                        ? `<span style="font-weight: 800; color: #6a1b9a; background: #f3e5f5; border: 1.5px solid #ba68c8; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">${item.lucky_draw_code}</span>`
                        : `<span style="color: #aaa;">-</span>`;

                    const statusHtml = isCheckedIn 
                        ? `<span class="badge checked_in">Đã checkin</span>`
                        : `<span class="badge invited">Chưa tới</span>`;

                    let actionsHtml = '';
                    if (hasActions) {
                        let checkinSlot = '';
                        if (isCheckedIn) {
                            checkinSlot = `<span class="badge checked_in" style="margin:0; font-size:0.78rem;">Đã checkin</span>`;
                        } else {
                            const escapedName = (item.full_name || '').replace(/'/g, "\\'");
                            checkinSlot = `<button type="button" class="btn btn-action-checkin" onclick="checkinHoGuest(${item.id}, '${escapedName}')">Check-in hộ</button>`;
                        }

                        let adminBtns = '';
                        if (isAdminUser) {
                            adminBtns = `
                                <button type="button" class="btn btn-action-edit" onclick="openEditModal(window.allGuestsMap[${item.id}])">Sửa</button>
                                <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa khách này?');">
                                    <input type="hidden" name="csrf_token" value="${csrfTokenValue}">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="${item.id}">
                                    <button type="submit" class="btn btn-action-delete">Xóa</button>
                                </form>
                            `;
                        }

                        actionsHtml = `
                            <td style="text-align: center;">
                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                    <div style="width: 105px; display: inline-flex; align-items: center;">${checkinSlot}</div>
                                    ${adminBtns}
                                </div>
                            </td>
                        `;
                    }

                    html += `<tr class="${rowClass}">`;
                    guestColsConfig.forEach(col => {
                        if (!col.visible) return;
                        switch(col.key) {
                            case 'customer_code':
                                html += `<td>${customerCodeHtml}</td>`;
                                break;
                            case 'full_name':
                                html += `<td><strong>${item.full_name}</strong></td>`;
                                break;
                            case 'phone':
                                html += `<td>${item.phone}</td>`;
                                break;
                            case 'organization':
                                html += `<td>${item.organization || '-'}</td>`;
                                break;
                            case 'table_name':
                                html += `<td>${tableHtml}</td>`;
                                break;
                            case 'lucky_draw_code':
                                html += `<td>${luckyHtml}</td>`;
                                break;
                            case 'status':
                                html += `<td>${statusHtml}</td>`;
                                break;
                            case 'actions':
                                if (hasActions) html += actionsHtml;
                                break;
                        }
                    });
                    html += `</tr>`;
                });

                tbody.innerHTML = html;

                if (searchInput && searchInput.value) {
                    liveSearchGuests(searchInput.value);
                }
            }
        } catch (e) {
            console.error('Realtime guests error:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('search-input');
        if (input) {
            ['input', 'keyup', 'change', 'search', 'paste'].forEach(evt => {
                input.addEventListener(evt, function() {
                    liveSearchGuests(this.value);
                });
            });
            if (input.value) {
                liveSearchGuests(input.value);
            }
        }
        setInterval(fetchRealtimeGuests, 3000); // Polling dự phòng

        window.addEventListener('dbRealtimeChange', () => {
            fetchRealtimeGuests(true);
        });
    });
</script>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
