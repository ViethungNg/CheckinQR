<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

$message = '';
$error = '';

if (isPost() && !isKinhDoanh()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        if (!isAdmin()) {
            $error = 'Chỉ Quản trị viên (Admin) mới có quyền xóa lượt check-in!';
        } else {
            $id = (int)$_POST['id'];
            
            // Lấy thông tin checkin trước khi xóa
            $stmtCheck = $db->prepare("SELECT * FROM checkins WHERE id = ?");
            $stmtCheck->execute([$id]);
            $checkin = $stmtCheck->fetch();
            
            if ($checkin) {
                // Nếu lượt check-in này có khớp với 1 khách dự kiến, cập nhật lại trạng thái khách đó về 'invited'
                if ($checkin['guest_id']) {
                    // Kiểm tra xem khách đó còn lượt checkin nào khác không
                    $otherCheckins = $db->prepare("SELECT COUNT(*) FROM checkins WHERE guest_id = ? AND id != ?");
                    $otherCheckins->execute([$checkin['guest_id'], $id]);
                    if ($otherCheckins->fetchColumn() == 0) {
                        $resetGuest = $db->prepare("UPDATE guests SET status = 'invited' WHERE id = ?");
                        $resetGuest->execute([$checkin['guest_id']]);
                    }
                }
                
                // Xóa bản ghi checkin
                $stmtDel = $db->prepare("DELETE FROM checkins WHERE id = ?");
                $stmtDel->execute([$id]);
                $message = 'Đã xóa lượt check-in (dữ liệu test) thành công!';
            }
        }
    } elseif ($action === 'assign_table') {
        $checkinId = (int)$_POST['checkin_id'];
        $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        
        $stmtCheck = $db->prepare("SELECT * FROM checkins WHERE id = ?");
        $stmtCheck->execute([$checkinId]);
        $checkin = $stmtCheck->fetch();
        
        if ($checkin) {
            if ($tableId === null) {
                // Hủy xếp bàn: Đặt table_id = NULL
                if (!empty($checkin['guest_id'])) {
                    $stmtG = $db->prepare("SELECT * FROM guests WHERE id = ?");
                    $stmtG->execute([$checkin['guest_id']]);
                    $gRow = $stmtG->fetch();
                    
                    // Nếu là khách phát sinh được thêm tự động (không có mã bốc thăm/đơn vị ban đầu)
                    if ($gRow && empty($gRow['lucky_draw_code']) && empty($gRow['organization'])) {
                        // Xóa khách này ra khỏi danh sách Khách hàng dự kiến (guests)
                        $delGuest = $db->prepare("DELETE FROM guests WHERE id = ?");
                        $delGuest->execute([$checkin['guest_id']]);
                        
                        // Đặt checkin về trạng thái Khách phát sinh (walk_in) ban đầu
                        $resetCheckin = $db->prepare("UPDATE checkins SET table_id = NULL, guest_id = NULL, match_status = 'walk_in' WHERE id = ?");
                        $resetCheckin->execute([$checkinId]);
                    } else {
                        // Nếu là khách dự kiến chính thức của BTC: giữ lại trong danh sách nhưng bỏ xếp bàn
                        $updateGuest = $db->prepare("UPDATE guests SET table_id = NULL WHERE id = ?");
                        $updateGuest->execute([$checkin['guest_id']]);
                        
                        $updateCheckin = $db->prepare("UPDATE checkins SET table_id = NULL WHERE id = ?");
                        $updateCheckin->execute([$checkinId]);
                    }
                } else {
                    // Trả về trạng thái Khách phát sinh (walk_in)
                    $resetCheckin = $db->prepare("UPDATE checkins SET table_id = NULL, match_status = 'walk_in' WHERE id = ?");
                    $resetCheckin->execute([$checkinId]);
                }
                
                $message = 'Đã hủy xếp bàn, xóa khách khỏi danh sách dự kiến và trả về trạng thái Khách phát sinh!';
            } else {
                // Xếp bàn cụ thể
                $updateCheckin = $db->prepare("UPDATE checkins SET table_id = ? WHERE id = ?");
                $updateCheckin->execute([$tableId, $checkinId]);
                
                if (!empty($checkin['guest_id'])) {
                    $updateGuest = $db->prepare("UPDATE guests SET table_id = ? WHERE id = ?");
                    $updateGuest->execute([$tableId, $checkin['guest_id']]);
                } else {
                    // Nếu chưa có (khách phát sinh), tự động thêm vào guests và đổi match_status thành matched
                    $stmtAddGuest = $db->prepare("INSERT INTO guests (event_id, full_name, phone, normalized_phone, table_id, status) VALUES (?, ?, ?, ?, ?, 'checked_in')");
                    $stmtAddGuest->execute([
                        $checkin['event_id'],
                        $checkin['full_name_entered'],
                        $checkin['phone_entered'],
                        $checkin['normalized_phone'],
                        $tableId
                    ]);
                    $newGuestId = $db->lastInsertId();
                    
                    $linkStmt = $db->prepare("UPDATE checkins SET guest_id = ?, match_status = 'matched' WHERE id = ?");
                    $linkStmt->execute([$newGuestId, $checkinId]);
                }
                
                $message = 'Đã xếp bàn và đồng bộ dữ liệu thành công!';
            }
        }
    }
}

// Lấy danh sách lượt check-in
$whereCheckin = "";
$paramsCheckin = [];
if (isKinhDoanh()) {
    $whereCheckin = "WHERE t.assigned_user_id = ?";
    $paramsCheckin = [$_SESSION['admin_id']];
}

$stmtCheckins = $db->prepare("
    SELECT c.*, e.event_name, t.table_name 
    FROM checkins c 
    LEFT JOIN events e ON c.event_id = e.id 
    LEFT JOIN event_tables t ON c.table_id = t.id 
    {$whereCheckin}
    ORDER BY c.checkin_time DESC
");
$stmtCheckins->execute($paramsCheckin);
$checkins = $stmtCheckins->fetchAll();

// Lấy danh sách bàn để xếp cho khách phát sinh
$tablesList = $db->query("SELECT id, table_name, table_code, event_id FROM event_tables ORDER BY table_code ASC, table_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in trực tiếp - CheckinQR</title>
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); padding: 20px; }
        .sidebar h2 { color: var(--primary-color); margin-bottom: 30px; font-size: 1.5rem; text-align: center; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 10px; }
        .sidebar ul li a { display: block; padding: 10px 15px; color: #555; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #fce4e4; color: var(--primary-color); font-weight: 600; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
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
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .close { font-size: 24px; font-weight: bold; cursor: pointer; color: #aaa; }
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
            <h1>Lượt Check-in thực tế</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <p style="margin-bottom: 15px; color: #666;">Danh sách hiển thị realtime khách quét QR. Bạn có thể xóa bản ghi Test hoặc xếp bàn cho khách phát sinh tại đây.</p>
            <table>
                <thead>
                    <tr>
                        <th>Họ Tên</th>
                        <th>SĐT</th>
                        <th>Vị trí bàn</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                        <?php if(!isKinhDoanh()): ?><th>Thao tác</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($checkins as $c): ?>
                    <tr>
                        <td><strong><?php echo esc($c['full_name_entered']); ?></strong></td>
                        <td><?php echo esc($c['phone_entered']); ?></td>
                        <td>
                            <?php if (!empty($c['table_name'])): ?>
                                <strong style="color: #2e7d32;"><?php echo esc($c['table_name']); ?></strong>
                            <?php else: ?>
                                <span style="color: #888;">Chưa xếp</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($c['checkin_time'])); ?></td>
                        <td>
                            <span class="badge <?php echo esc($c['match_status']); ?>">
                                <?php echo $c['match_status'] === 'matched' ? 'Khách hợp lệ' : 'Khách phát sinh'; ?>
                            </span>
                        </td>
                        <?php if(!isKinhDoanh()): ?>
                        <td>
                            <button class="btn btn-info" onclick='openAssignModal(<?php echo json_encode($c); ?>)'>Xếp bàn</button>
                            
                            <?php if(isAdmin()): ?>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa dòng check-in này (dữ liệu test)?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn btn-danger">Xóa Test</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
    const modal = document.getElementById('assignModal');
    const allTables = <?php echo json_encode($tablesList); ?>;
    
    function openAssignModal(c) {
        document.getElementById('modalCheckinId').value = c.id;
        document.getElementById('modalGuestName').innerText = c.full_name_entered;
        document.getElementById('modalGuestPhone').innerText = c.phone_entered;
        
        const select = document.getElementById('modalTableSelect');
        select.innerHTML = '<option value="">-- Chưa xếp bàn --</option>';
        
        allTables.forEach(t => {
            if (t.event_id == c.event_id) {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.table_code ? `${t.table_code} (${t.table_name})` : t.table_name;
                if (t.id == c.table_id) opt.selected = true;
                select.appendChild(opt);
            }
        });
        
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
</script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
