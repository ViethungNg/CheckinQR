<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

$message = '';
$error = '';
if (isPost()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isAdmin()) {
        $id = (int)$_POST['id'];
        
        // Lấy thông tin checkin trước khi xóa
        $stmtC = $db->prepare("SELECT * FROM checkins WHERE id = ?");
        $stmtC->execute([$id]);
        $checkin = $stmtC->fetch();
        
        if ($checkin) {
            // Nếu lượt checkin này khớp với khách dự kiến, cập nhật lại trạng thái khách đó về 'invited'
            if (!empty($checkin['guest_id'])) {
                $db->prepare("UPDATE guests SET status = 'invited' WHERE id = ?")->execute([$checkin['guest_id']]);
            }
            
            $stmt = $db->prepare("DELETE FROM checkins WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Đã xóa lượt check-in thành công!';
        }
    } elseif ($action === 'assign_table' && !isKinhDoanh()) {
        $checkinId = (int)$_POST['checkin_id'];
        $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        
        // Lấy thông tin check-in
        $stmtC = $db->prepare("SELECT * FROM checkins WHERE id = ?");
        $stmtC->execute([$checkinId]);
        $c = $stmtC->fetch();
        
        if ($c) {
            if (empty($tableId)) {
                // Nếu Vị trí bàn = Chưa xếp hoặc rỗng:
                // 1. Chuyển lượt checkin này thành Khách phát sinh (walk_in) & rỗng bàn
                $gId = $c['guest_id'];
                $db->prepare("UPDATE checkins SET table_id = NULL, guest_id = NULL, match_status = 'walk_in' WHERE id = ?")->execute([$checkinId]);
                
                // 2. Xóa thông tin người đó khỏi Danh sách khách hàng (guests)
                if (!empty($gId)) {
                    $db->prepare("DELETE FROM guests WHERE id = ?")->execute([$gId]);
                }
                $message = 'Đã chuyển thành Khách phát sinh và xóa khỏi Danh sách khách hàng!';
            } else {
                // 1. Cập nhật bàn cho checkin này
                $stmtUp = $db->prepare("UPDATE checkins SET table_id = ?, match_status = 'matched' WHERE id = ?");
                $stmtUp->execute([$tableId, $checkinId]);
                
                // 2. Nếu check-in này đã gắn với guest_id thì cập nhật table_id cho guest đó luôn
                if (!empty($c['guest_id'])) {
                    $stmtUpG = $db->prepare("UPDATE guests SET table_id = ?, status = 'checked_in' WHERE id = ?");
                    $stmtUpG->execute([$tableId, $c['guest_id']]);
                    $message = 'Đã xếp bàn thành công cho khách!';
                } else {
                    // Nếu đây là khách phát sinh (walk_in), kiểm tra xem có SĐT trùng trong danh sách không
                    $stmtUpG = $db->prepare("SELECT id FROM guests WHERE event_id = ? AND normalized_phone = ?");
                    $stmtUpG->execute([$c['event_id'], $c['normalized_phone']]);
                    $gExist = $stmtUpG->fetch();
                    
                    if ($gExist) {
                        $db->prepare("UPDATE checkins SET guest_id = ?, match_status = 'matched' WHERE id = ?")->execute([$gExist['id'], $checkinId]);
                        $db->prepare("UPDATE guests SET status = 'checked_in', table_id = ? WHERE id = ?")->execute([$tableId, $gExist['id']]);
                    } else {
                        // Tạo mới 1 bản ghi khách dự kiến cho khách phát sinh này để tiện quản lý sau này
                        $stmtInsG = $db->prepare("INSERT INTO guests (event_id, full_name, phone, normalized_phone, table_id, status, notes) VALUES (?, ?, ?, ?, ?, 'checked_in', 'Khách phát sinh được xếp bàn trực tiếp')");
                        $stmtInsG->execute([$c['event_id'], $c['full_name_entered'], $c['phone_entered'], $c['normalized_phone'], $tableId]);
                        $newGuestId = $db->lastInsertId();
                        
                        $db->prepare("UPDATE checkins SET guest_id = ?, match_status = 'matched' WHERE id = ?")->execute([$newGuestId, $checkinId]);
                    }
                    $message = 'Đã xếp bàn thành công cho khách phát sinh!';
                }
            }
        }
    }
}

// Lấy danh sách lượt check-in kèm lọc & tìm kiếm
$search = trim($_GET['search'] ?? '');
$matchStatus = trim($_GET['match_status'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'time_desc');

$whereConditions = [];
$paramsCheckin = [];

if (isKinhDoanh()) {
    $whereConditions[] = "t.assigned_user_id = ?";
    $paramsCheckin[] = $_SESSION['admin_id'];
}

if ($matchStatus === 'matched') {
    $whereConditions[] = "c.match_status = 'matched'";
} elseif ($matchStatus === 'walk_in') {
    $whereConditions[] = "c.match_status = 'walk_in'";
}

if ($search !== '') {
    $whereConditions[] = "(c.full_name_entered LIKE ? OR c.phone_entered LIKE ? OR t.table_name LIKE ? OR c.lucky_draw_code LIKE ?)";
    $likeStr = '%' . $search . '%';
    $paramsCheckin[] = $likeStr;
    $paramsCheckin[] = $likeStr;
    $paramsCheckin[] = $likeStr;
    $paramsCheckin[] = $likeStr;
}

$whereCheckin = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

$orderSql = "ORDER BY c.checkin_time DESC";
if ($sort === 'table_asc') {
    $orderSql = "ORDER BY t.table_name ASC, c.id DESC";
} elseif ($sort === 'table_desc') {
    $orderSql = "ORDER BY t.table_name DESC, c.id DESC";
} elseif ($sort === 'code_asc') {
    $orderSql = "ORDER BY c.lucky_draw_code ASC, c.id DESC";
} elseif ($sort === 'code_desc') {
    $orderSql = "ORDER BY c.lucky_draw_code DESC, c.id DESC";
}

$stmtCheckins = $db->prepare("
    SELECT c.*, e.event_name, t.table_name, 
           g.full_name as guest_full_name, g.phone as guest_phone, g.organization as guest_organization,
           g.lucky_draw_code as guest_lucky_code, g.notes as guest_notes, g.normalized_phone as guest_normalized_phone,
           g.customer_code as guest_customer_code
    FROM checkins c 
    LEFT JOIN events e ON c.event_id = e.id 
    LEFT JOIN event_tables t ON c.table_id = t.id 
    LEFT JOIN guests g ON c.guest_id = g.id
    {$whereCheckin}
    {$orderSql}
    LIMIT 200
");
$stmtCheckins->execute($paramsCheckin);
$checkins = $stmtCheckins->fetchAll();

// Chuẩn bị Map chi tiết cho Javascript so sánh
$checkinsMapData = [];
foreach ($checkins as $cItem) {
    $isByCode = ($cItem['checkin_method'] ?? '') === 'lucky_code' || (!empty($cItem['guest_normalized_phone']) && $cItem['normalized_phone'] !== $cItem['guest_normalized_phone']);
    $checkinsMapData[$cItem['id']] = [
        'id'                 => (int)$cItem['id'],
        'event_id'           => (int)($cItem['event_id'] ?? 0),
        'table_id'           => $cItem['table_id'] ? (int)$cItem['table_id'] : null,
        'guest_id'           => $cItem['guest_id'] ? (int)$cItem['guest_id'] : null,
        'full_name'          => $cItem['full_name_entered'],
        'phone'              => $cItem['phone_entered'],
        'address_entered'    => $cItem['address_entered'] ?? '',
        'lucky_draw_code'    => $cItem['lucky_draw_code'] ?? '',
        'table_name'         => $cItem['table_name'] ?? 'Chưa xếp bàn',
        'checkin_time'       => date('d/m/Y H:i:s', strtotime($cItem['checkin_time'])),
        'match_status'       => $cItem['match_status'],
        'checkin_method'     => $cItem['checkin_method'],
        'is_by_code'         => $isByCode,
        'guest_full_name'    => $cItem['guest_full_name'] ?? '',
        'guest_phone'        => $cItem['guest_phone'] ?? '',
        'guest_organization' => $cItem['guest_organization'] ?? '',
        'guest_lucky_code'   => $cItem['guest_lucky_code'] ?? '',
        'guest_notes'        => $cItem['guest_notes'] ?? ''
    ];
}

// Lấy danh sách bàn để xếp cho khách phát sinh
$tablesList = $db->query("SELECT id, table_name, table_code, event_id FROM event_tables ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT - Checkin - Lịch sử Check-in</title>
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
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; border: none; font-size: 0.85rem; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .btn-info { background: #0288d1; color: white; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge.matched { background: #e8f5e9; color: #2e7d32; }
        .badge.walk_in { background: #fff3e0; color: #ef6c00; }
        
        .alert { padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; }
        
        /* Modal CSS */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: #fff; margin: 8% auto; padding: 24px; border-radius: 8px; width: 450px; max-width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .close { font-size: 26px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Khách đã checkin</h1>
            <span id="realtime-status" style="font-size: 0.85rem; color: #2e7d32; font-weight: 500;">
                🟢 Real-time (Mỗi 2s)
            </span>
        </div>
        
        <?php if ($message): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>, 'success'); });</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>, 'error'); });</script>
        <?php endif; ?>

        <div class="content-box">
            <p style="margin-bottom: 15px; color: #666;">Danh sách hiển thị realtime khách quét QR. Bạn có thể tìm kiếm theo SĐT, Mã dự thưởng, Họ tên hoặc Bàn tiệc.</p>
            
            <!-- Thanh Lọc & Sắp Xếp Thông Minh (Live Typing Search) -->
            <form method="GET" action="" id="checkin-search-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; background: #f8f9fa; padding: 12px 15px; border-radius: 10px; border: 1px solid #e0e0e0;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" id="checkin-search-input" name="search" value="<?php echo esc($search); ?>" placeholder="Tìm theo Họ tên, SĐT, Bàn tiệc, Mã dự thưởng..." class="form-control" oninput="liveSearchCheckins(this.value)" autocomplete="off">
                </div>
                
                <div style="min-width: 170px;">
                    <select id="checkin-match-filter" name="match_status" class="form-control" onchange="applyCheckinFilter()" style="cursor: pointer; background: #fff; font-weight: 500;">
                        <option value="all" <?php echo $matchStatus === 'all' ? 'selected' : ''; ?>>Tất cả hình thức</option>
                        <option value="matched" <?php echo $matchStatus === 'matched' ? 'selected' : ''; ?>>Khách hợp lệ (Khớp)</option>
                        <option value="walk_in" <?php echo $matchStatus === 'walk_in' ? 'selected' : ''; ?>>Khách phát sinh (Walk-in)</option>
                    </select>
                </div>

                <div style="min-width: 190px;">
                    <select id="checkin-sort-filter" name="sort" class="form-control" onchange="applyCheckinFilter()" style="cursor: pointer; background: #fff; font-weight: 500;">
                        <option value="time_desc" <?php echo $sort === 'time_desc' ? 'selected' : ''; ?>>Mới nhất trước</option>
                        <option value="table_asc" <?php echo $sort === 'table_asc' ? 'selected' : ''; ?>>Thứ tự Bàn: Tăng dần (1 ➔ N)</option>
                        <option value="table_desc" <?php echo $sort === 'table_desc' ? 'selected' : ''; ?>>Thứ tự Bàn: Giảm dần (N ➔ 1)</option>
                        <option value="code_asc" <?php echo $sort === 'code_asc' ? 'selected' : ''; ?>>Mã dự thưởng: Tăng dần (0 ➔ 9)</option>
                        <option value="code_desc" <?php echo $sort === 'code_desc' ? 'selected' : ''; ?>>Mã dự thưởng: Giảm dần (9 ➔ 0)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 18px;">Tìm kiếm</button>
                <?php if(!empty($search) || $matchStatus !== 'all' || $sort !== 'time_desc'): ?>
                    <a href="checkins.php" class="btn" style="background: #757575; color: white;">Xóa lọc</a>
                <?php endif; ?>
            </form>

            <?php $checkinCols = getTableColumnsConfig('checkins'); ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($checkinCols as $c): ?>
                                <?php if (!empty($c['visible'])): ?>
                                    <?php if ($c['key'] === 'actions' && isKinhDoanh()) continue; ?>
                                    <th><?php echo esc($c['label']); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="checkins-table-body">
                        <?php foreach($checkins as $c): 
                            $isByLuckyCode = ($c['checkin_method'] ?? '') === 'lucky_code' || (!empty($c['guest_normalized_phone']) && $c['normalized_phone'] !== $c['guest_normalized_phone']);
                            $custCode = !empty($c['customer_code']) ? $c['customer_code'] : ($c['guest_customer_code'] ?? '');
                        ?>
                        <tr id="checkin-row-<?php echo (int)$c['id']; ?>" class="<?php echo $c['match_status'] === 'matched' ? 'row-checked-in' : ''; ?>">
                            <?php foreach ($checkinCols as $col): ?>
                                <?php if (!empty($col['visible'])): ?>
                                    <?php switch($col['key']):
                                        case 'customer_code': ?>
                                            <td>
                                                <?php if (!empty($custCode)): ?>
                                                    <strong style="color: #0284c7; font-weight:700; font-size: 0.88rem;"><?php echo esc($custCode); ?></strong>
                                                <?php else: ?>
                                                    <span style="color: #aaa;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'full_name': ?>
                                            <td><strong><?php echo esc($c['full_name_entered']); ?></strong></td>
                                            <?php break;
                                        case 'phone': ?>
                                            <td><?php echo esc($c['phone_entered']); ?></td>
                                            <?php break;
                                        case 'organization': ?>
                                            <td><?php echo esc($c['address_entered'] ?? '-'); ?></td>
                                            <?php break;
                                        case 'table_name': ?>
                                            <td>
                                                <?php if (!empty($c['table_name'])): ?>
                                                    <span style="font-weight: 800; color: #1b5e20; background: #e8f5e9; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 8px; font-size: 0.85rem; white-space: nowrap; display: inline-flex; align-items: center;">
                                                        <?php echo esc($c['table_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #888; font-style: italic;">Chưa xếp</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'lucky_draw_code': ?>
                                            <td>
                                                <?php if (!empty($c['lucky_draw_code'])): ?>
                                                    <span style="font-weight: 800; color: #6a1b9a; background: #f3e5f5; border: 1.5px solid #ba68c8; padding: 4px 10px; border-radius: 8px; font-size: 0.85rem;">
                                                        <?php echo esc($c['lucky_draw_code']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #aaa;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'checkin_time': ?>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($c['checkin_time'])); ?></td>
                                            <?php break;
                                        case 'method': ?>
                                            <td>
                                                <?php if ($c['match_status'] === 'walk_in'): ?>
                                                    <span style="background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 0.8rem;">
                                                        Khách phát sinh
                                                    </span>
                                                <?php elseif ($isByLuckyCode): ?>
                                                    <span style="background: #f3e5f5; color: #7b1fa2; border: 1.5px solid #ab47bc; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem;">
                                                        Khớp Mã dự thưởng
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background: #e8f5e9; color: #1b5e20; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem;">
                                                        Khớp SĐT
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php break;
                                        case 'status': ?>
                                            <td>
                                                <span class="badge <?php echo esc($c['match_status']); ?>">
                                                    <?php echo $c['match_status'] === 'matched' ? 'Khách hợp lệ' : 'Khách phát sinh'; ?>
                                                </span>
                                            </td>
                                            <?php break;
                                        case 'actions': ?>
                                            <?php if(!isKinhDoanh()): ?>
                                                <td style="text-align: center;">
                                                    <div class="action-btns-wrapper">
                                                        <button type="button" class="btn btn-action-primary" onclick="openCompareModal(<?php echo (int)$c['id']; ?>)">Đối chiếu</button>
                                                        <?php if(isAdmin()): ?>
                                                            <button type="button" class="btn btn-action-assign" onclick="openAssignModal(<?php echo (int)$c['id']; ?>)">Xếp bàn</button>
                                                            <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa dòng check-in này (dữ liệu test)?');">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                                <button type="submit" class="btn btn-action-danger">Xóa Test</button>
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

<!-- Modal Đối Chiếu Thông Tin Khách Check-in vs Danh Sách BTC -->
<div id="compareModal" class="modal">
    <div class="modal-content" style="width: 720px; max-width: 95%;">
        <div class="modal-header" style="border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
            <div>
                <h3 style="font-size: 1.15rem; color: #0f172a; font-weight: 700;">
                    Đối Chiếu Thông Tin Khách Check-in
                </h3>
                <div style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">So sánh chi tiết dữ liệu khách tự điền khi quét QR với Hồ sơ vé mời của BTC</div>
            </div>
            <span class="close" onclick="closeCompareModal()">&times;</span>
        </div>
        
        <div class="modal-body" style="padding-top: 15px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <!-- Cột Khách Điền Thực Tế -->
                <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 16px;">
                    <div style="font-weight: 800; color: #166534; font-size: 0.88rem; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dcfce7; padding-bottom: 8px; gap: 8px;">
                        <span>KHÁCH ĐIỀN THỰC TẾ</span>
                        <span id="cmp-method-badge" style="font-size:0.75rem; font-weight:700; white-space:nowrap;"></span>
                    </div>
                    <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Họ tên điền:</strong> <span id="cmp-entered-name" style="font-weight:700; color:#0f172a;"></span></div>
                    <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>SĐT điền:</strong> <span id="cmp-entered-phone"></span></div>
                    <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Mã dự thưởng điền:</strong> <span id="cmp-entered-code"></span></div>
                    <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Địa chỉ / Đơn vị:</strong> <span id="cmp-entered-address"></span></div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 8px;"><strong>Thời gian:</strong> <span id="cmp-time"></span></div>
                </div>

                <!-- Cột Hồ Sơ Khách Mời BTC -->
                <div style="background: #faf5ff; border: 1.5px solid #e9d5ff; border-radius: 12px; padding: 16px;">
                    <div style="font-weight: 800; color: #6b21a8; font-size: 0.88rem; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3e8ff; padding-bottom: 8px; gap: 8px;">
                        <span>HỒ SƠ BTC MỜI GỐC</span>
                        <span id="cmp-match-badge" style="font-size:0.75rem; font-weight:700; white-space:nowrap;"></span>
                    </div>
                    <div id="cmp-guest-matched-box">
                        <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Họ tên BTC:</strong> <span id="cmp-btc-name" style="font-weight:700; color:#0f172a;"></span></div>
                        <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>SĐT BTC:</strong> <span id="cmp-btc-phone"></span></div>
                        <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Mã dự thưởng BTC:</strong> <span id="cmp-btc-code"></span></div>
                        <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Đơn vị BTC:</strong> <span id="cmp-btc-org"></span></div>
                        <div style="margin-bottom: 8px; font-size: 0.9rem;"><strong>Vị trí bàn tiệc:</strong> <span id="cmp-btc-table" style="font-weight:800; color:#1b5e20;"></span></div>
                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 10px; border-top: 1px dashed #e9d5ff; padding-top: 8px;"><strong>Ghi chú BTC:</strong> <span id="cmp-btc-notes"></span></div>
                    </div>
                    <div id="cmp-guest-unmatched-box" style="display:none; padding: 15px; background: #fff7ed; border: 1px dashed #fdba74; border-radius: 8px; color: #c2410c; font-size: 0.88rem;">
                        Chưa ghép được với Hồ sơ vé mời nào (Khách vãng lai / Khách phát sinh).
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid #e2e8f0; margin-top: 10px;">
            <a id="cmp-guest-list-link" href="#" class="btn btn-action-edit" style="text-decoration: none; padding: 8px 16px; font-weight: 600; align-items: center; border-radius: 8px;">
                Mở Trong Danh Sách Khách Hàng
            </a>
            <button type="button" id="cmp-btn-assign" class="btn btn-action-assign" style="padding: 8px 16px; font-weight: 600; border-radius: 8px;">
                Xếp / Đổi Bàn
            </button>
            <button type="button" class="btn" style="background: #64748b; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 600;" onclick="closeCompareModal()">
                Đóng
            </button>
        </div>
    </div>
</div>

<!-- Modal Xếp Bàn -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Xếp Bàn Cho Khách</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign_table">
            <input type="hidden" name="checkin_id" id="modalCheckinId" value="">
            
            <p style="margin-bottom: 15px;">Khách: <strong id="modalGuestName"></strong> (<span id="modalGuestPhone"></span>)</p>
            
            <div class="form-group">
                <label>Chọn Bàn</label>
                <select name="table_id" id="modalTableSelect" class="form-control">
                    <option value="">-- Chưa xếp bàn --</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Lưu & Duyệt Khách</button>
        </form>
    </div>
</div>

<script>
    window.allCheckinsMap = <?php echo json_encode($checkinsMapData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const allTables = <?php echo json_encode($tablesList); ?>;
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const isKinhDoanhUser = <?php echo isKinhDoanh() ? 'true' : 'false'; ?>;
    const csrfTokenValue = '<?php echo $_SESSION['csrf_token'] ?? ""; ?>';

    function openAssignModal(id) {
        const c = (window.allCheckinsMap && window.allCheckinsMap[id]) ? window.allCheckinsMap[id] : null;
        if (!c) {
            console.error('Checkin record not found:', id);
            return;
        }

        document.getElementById('modalCheckinId').value = c.id;
        document.getElementById('modalGuestName').textContent = c.full_name || '';
        document.getElementById('modalGuestPhone').textContent = c.phone || '';
        
        const select = document.getElementById('modalTableSelect');
        select.innerHTML = '<option value="">-- Chưa xếp bàn --</option>';
        
        if (Array.isArray(allTables)) {
            allTables.forEach(t => {
                if (!c.event_id || c.event_id == 0 || !t.event_id || t.event_id == c.event_id) {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.table_code ? `${t.table_code} (${t.table_name})` : t.table_name;
                    if (c.table_id && t.id == c.table_id) opt.selected = true;
                    select.appendChild(opt);
                }
            });
        }
        
        const modalEl = document.getElementById('assignModal');
        if (modalEl) modalEl.style.display = 'block';
    }

    function closeModal() {
        const modalEl = document.getElementById('assignModal');
        if (modalEl) modalEl.style.display = 'none';
    }

    function openCompareModal(id) {
        const c = (window.allCheckinsMap && window.allCheckinsMap[id]) ? window.allCheckinsMap[id] : null;
        if (!c) {
            console.error('Checkin record not found:', id);
            return;
        }

        // Điền thông tin Khách Điền Thực Tế
        document.getElementById('cmp-entered-name').textContent = c.full_name || '-';
        document.getElementById('cmp-entered-phone').textContent = c.phone || '-';
        document.getElementById('cmp-entered-code').textContent = c.lucky_draw_code || '-';
        document.getElementById('cmp-entered-address').textContent = c.address_entered || '-';
        document.getElementById('cmp-time').textContent = c.checkin_time || '-';

        let methodBadgeHtml = `<span style="background: #e8f5e9; color: #1b5e20; border: 1.5px solid #81c784; padding: 3px 8px; border-radius: 12px;">Khớp SĐT</span>`;
        if (c.match_status === 'walk_in') {
            methodBadgeHtml = `<span style="background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; padding: 3px 8px; border-radius: 12px;">Khách phát sinh</span>`;
        } else if (c.is_by_code) {
            methodBadgeHtml = `<span style="background: #f3e5f5; color: #7b1fa2; border: 1.5px solid #ab47bc; padding: 3px 8px; border-radius: 12px;">Khớp Mã dự thưởng</span>`;
        }
        document.getElementById('cmp-method-badge').innerHTML = methodBadgeHtml;

        // Điền Hồ sơ BTC
        const btcBox = document.getElementById('cmp-guest-matched-box');
        const unmatchedBox = document.getElementById('cmp-guest-unmatched-box');
        const matchBadge = document.getElementById('cmp-match-badge');

        if (c.guest_id && c.guest_full_name) {
            btcBox.style.display = 'block';
            unmatchedBox.style.display = 'none';
            matchBadge.innerHTML = `<span style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:3px 8px; border-radius:12px;">Đã ghép hồ sơ</span>`;

            document.getElementById('cmp-btc-name').textContent = c.guest_full_name;
            document.getElementById('cmp-btc-phone').textContent = c.guest_phone || '-';
            document.getElementById('cmp-btc-code').textContent = c.guest_lucky_code || '-';
            document.getElementById('cmp-btc-org').textContent = c.guest_organization || '-';
            document.getElementById('cmp-btc-table').textContent = c.table_name || 'Chưa xếp';
            document.getElementById('cmp-btc-notes').textContent = c.guest_notes || 'Không có ghi chú';

            // Link tới trang guests.php
            const searchKeyword = encodeURIComponent(c.guest_phone || c.guest_full_name || c.guest_lucky_code);
            document.getElementById('cmp-guest-list-link').href = `guests.php?search=${searchKeyword}`;
        } else {
            btcBox.style.display = 'none';
            unmatchedBox.style.display = 'block';
            matchBadge.innerHTML = `<span style="background:#ffedd5; color:#c2410c; border:1px solid #fed7aa; padding:3px 8px; border-radius:12px;">Khách phát sinh</span>`;

            const searchKeyword = encodeURIComponent(c.phone || c.full_name);
            document.getElementById('cmp-guest-list-link').href = `guests.php?search=${searchKeyword}`;
        }

        const btnAssign = document.getElementById('cmp-btn-assign');
        if (btnAssign) {
            btnAssign.onclick = function() {
                closeCompareModal();
                openAssignModal(c.id);
            };
            btnAssign.style.display = isKinhDoanhUser ? 'none' : 'inline-flex';
        }

        const modalEl = document.getElementById('compareModal');
        if (modalEl) modalEl.style.display = 'block';
    }

    function closeCompareModal() {
        const modalEl = document.getElementById('compareModal');
        if (modalEl) modalEl.style.display = 'none';
    }

    window.addEventListener('click', function(e) {
        const modalAssign = document.getElementById('assignModal');
        const modalCompare = document.getElementById('compareModal');
        if (modalAssign && e.target === modalAssign) {
            modalAssign.style.display = 'none';
        }
        if (modalCompare && e.target === modalCompare) {
            modalCompare.style.display = 'none';
        }
    });

    let lastCheckinsDataHash = '';
    let liveSearchCheckinTimer = null;
    let currentCheckinSearchVal = '<?php echo esc($search); ?>';
    let currentCheckinMatchStatus = '<?php echo esc($matchStatus); ?>';
    let currentCheckinSort = '<?php echo esc($sort); ?>';
    const checkinColsConfig = <?php echo json_encode($checkinCols); ?>;

    function liveSearchCheckins(val) {
        clearTimeout(liveSearchCheckinTimer);
        liveSearchCheckinTimer = setTimeout(() => {
            currentCheckinSearchVal = val.trim();
            updateRealtimeCheckinsList(true);
        }, 250);
    }

    function applyCheckinFilter() {
        const matchSelect = document.getElementById('checkin-match-filter');
        const sortSelect = document.getElementById('checkin-sort-filter');
        
        if (matchSelect) currentCheckinMatchStatus = matchSelect.value;
        if (sortSelect) currentCheckinSort = sortSelect.value;
        
        updateRealtimeCheckinsList(true);
        if (window.scrollToTableSectionOnMobile) window.scrollToTableSectionOnMobile();
    }

    async function updateRealtimeCheckinsList(forceRefresh = false) {
        const modalEl = document.getElementById('assignModal');
        if (modalEl && getComputedStyle(modalEl).display !== 'none') return;

        try {
            const queryUrl = `../api/stats.php?filter=${encodeURIComponent(currentCheckinMatchStatus)}&table_id=all&search=${encodeURIComponent(currentCheckinSearchVal)}&sort=${encodeURIComponent(currentCheckinSort)}&_t=${Date.now()}`;
            const response = await fetch(queryUrl, { cache: 'no-store' });
            if (!response.ok) return;
            const textData = await response.text();

            if (!forceRefresh && textData === lastCheckinsDataHash) return;
            lastCheckinsDataHash = textData;

            const result = JSON.parse(textData);

            if (result.status === 'success' && result.data.recent_checkins) {
                const tbody = document.getElementById('checkins-table-body');
                if (!tbody) return;

                const visibleCount = checkinColsConfig.filter(c => c.visible).length || 1;
                if (result.data.recent_checkins.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${visibleCount}" style="text-align:center; color:#777; padding:20px;">Không tìm thấy lượt check-in nào phù hợp với bộ lọc</td></tr>`;
                    return;
                }

                let html = '';
                result.data.recent_checkins.forEach(item => {
                    window.allCheckinsMap[item.id] = {
                        id: item.id,
                        event_id: item.event_id || 0,
                        table_id: item.table_id || null,
                        guest_id: item.guest_id || null,
                        customer_code: item.customer_code || '',
                        full_name: item.full_name,
                        phone: item.phone,
                        address_entered: item.address_entered || '',
                        lucky_draw_code: item.lucky_draw_code || '',
                        table_name: item.table_name || 'Chưa xếp bàn',
                        checkin_time: item.time,
                        match_status: item.status,
                        is_by_code: item.is_by_code,
                        guest_full_name: item.guest_full_name || '',
                        guest_phone: item.guest_phone || '',
                        guest_organization: item.guest_organization || '',
                        guest_lucky_code: item.guest_lucky_code || '',
                        guest_notes: item.guest_notes || ''
                    };

                    const isCheckedIn = item.status === 'matched';
                    const rowClass = isCheckedIn ? 'row-checked-in' : '';
                    
                    const customerCodeHtml = item.customer_code 
                        ? `<strong style="color: #0284c7; font-weight:700; font-size: 0.88rem;">${item.customer_code}</strong>` 
                        : `<span style="color: #aaa;">-</span>`;

                    const luckyCodeHtml = item.lucky_draw_code 
                        ? `<span style="font-weight: 700; color: #6a1b9a; background: #f3e5f5; border: 1px solid #ba68c8; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">${item.lucky_draw_code}</span>` 
                        : `<span style="color:#aaa;">-</span>`;

                    let methodBadge = `<span style="background: #e8f5e9; color: #1b5e20; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem;">Khớp SĐT</span>`;
                    if (item.status === 'walk_in') {
                        methodBadge = `<span style="background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 0.8rem;">Khách phát sinh</span>`;
                    } else if (item.is_by_code) {
                        methodBadge = `<span style="background: #f3e5f5; color: #7b1fa2; border: 1.5px solid #ab47bc; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem;">Khớp Mã dự thưởng</span>`;
                    }

                    const statusBadge = isCheckedIn 
                        ? `<span class="badge matched">Khách hợp lệ</span>` 
                        : `<span class="badge walk_in">Khách phát sinh</span>`;

                    const tableNameHtml = item.table_name && item.table_name !== 'Chưa xếp bàn'
                        ? `<span style="font-weight: 800; color: #1b5e20; background: #e8f5e9; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 8px; font-size: 0.85rem; white-space: nowrap; display: inline-flex; align-items: center;">${item.table_name}</span>`
                        : `<span style="color: #888; font-style: italic;">Chưa xếp</span>`;

                    let actionsHtml = '';
                    if (!isKinhDoanhUser) {
                        actionsHtml = `
                            <td style="text-align: center;">
                                <div class="action-btns-wrapper">
                                    <button type="button" class="btn btn-action-primary" onclick="openCompareModal(${item.id})">Đối chiếu</button>
                                    <button type="button" class="btn btn-action-assign" onclick="openAssignModal(${item.id})">Xếp bàn</button>
                                    ${isAdminUser ? `
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa dòng check-in này (dữ liệu test)?');">
                                        <input type="hidden" name="csrf_token" value="${csrfTokenValue}">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="${item.id}">
                                        <button type="submit" class="btn btn-action-danger">Xóa Test</button>
                                    </form>
                                    ` : ''}
                                </div>
                            </td>
                        `;
                    }

                    html += `<tr id="checkin-row-${item.id}" class="${rowClass}">`;
                    checkinColsConfig.forEach(col => {
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
                                html += `<td>${item.address_entered || '-'}</td>`;
                                break;
                            case 'table_name':
                                html += `<td>${tableNameHtml}</td>`;
                                break;
                            case 'lucky_draw_code':
                                html += `<td>${luckyCodeHtml}</td>`;
                                break;
                            case 'checkin_time':
                                html += `<td>${item.time}</td>`;
                                break;
                            case 'method':
                                html += `<td>${methodBadge}</td>`;
                                break;
                            case 'status':
                                html += `<td>${statusBadge}</td>`;
                                break;
                            case 'actions':
                                if (!isKinhDoanhUser) html += actionsHtml;
                                break;
                        }
                    });
                    html += `</tr>`;
                });

                tbody.innerHTML = html;
                applyCheckinHighlightFromUrl();
            }
        } catch (e) {
            console.error('Realtime checkins update error:', e);
        }
    }

    let isHighlightAttached = false;
    function applyCheckinHighlightFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');
        if (!highlightId) return;

        const row = document.getElementById(`checkin-row-${highlightId}`);
        if (row) {
            document.querySelectorAll('.row-highlighted').forEach(el => el.classList.remove('row-highlighted'));
            row.classList.add('row-highlighted');
            
            try {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch(e) {}

            if (!isHighlightAttached) {
                isHighlightAttached = true;
                const clearHighlight = function(e) {
                    row.classList.remove('row-highlighted');
                    document.querySelectorAll('.row-highlighted').forEach(el => el.classList.remove('row-highlighted'));
                    const cleanUrl = new URL(window.location);
                    cleanUrl.searchParams.delete('highlight');
                    window.history.replaceState({}, '', cleanUrl);
                    document.removeEventListener('click', clearHighlight);
                    isHighlightAttached = false;
                };

                setTimeout(() => {
                    document.addEventListener('click', clearHighlight, { once: true });
                }, 400);
            }
        }
    }

    // Lắng nghe sự kiện SSE Push (0s) khi CSDL có phát sinh
    updateRealtimeCheckinsList();
    setInterval(updateRealtimeCheckinsList, 3000); // Polling dự phòng

    window.addEventListener('dbRealtimeChange', () => {
        updateRealtimeCheckinsList();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateRealtimeCheckinsList();
        }
    });

    document.addEventListener('DOMContentLoaded', applyCheckinHighlightFromUrl);
</script>

<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
