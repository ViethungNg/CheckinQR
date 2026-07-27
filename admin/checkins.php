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

// Lấy danh sách lượt check-in
$whereCheckin = "";
$paramsCheckin = [];
if (isKinhDoanh()) {
    $whereCheckin = "WHERE t.assigned_user_id = ?";
    $paramsCheckin = [$_SESSION['admin_id']];
}

$stmtCheckins = $db->prepare("
    SELECT c.*, e.event_name, t.table_name, g.normalized_phone as guest_normalized_phone, g.lucky_draw_code as guest_lucky_code 
    FROM checkins c 
    LEFT JOIN events e ON c.event_id = e.id 
    LEFT JOIN event_tables t ON c.table_id = t.id 
    LEFT JOIN guests g ON c.guest_id = g.id
    {$whereCheckin}
    ORDER BY c.checkin_time DESC
");
$stmtCheckins->execute($paramsCheckin);
$checkins = $stmtCheckins->fetchAll();

// Lấy danh sách bàn để xếp cho khách phát sinh
$tablesList = $db->query("SELECT id, table_name, table_code, event_id FROM event_tables ORDER BY sort_order ASC, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khách đã checkin - CheckinQR</title>
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
<script>
    window.checkinsMap = {};
</script>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Khách đã checkin</h1>
            <span id="realtime-status" style="font-size: 0.85rem; color: #2e7d32; font-weight: 500;">
                🟢 Real-time (Mỗi 3s)
            </span>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <p style="margin-bottom: 15px; color: #666;">Danh sách hiển thị realtime khách quét QR. Bạn có thể xem chi tiết khách khớp theo SĐT hay theo Mã dự thưởng tại đây.</p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Họ Tên</th>
                            <th>SĐT</th>
                            <th>Vị trí bàn</th>
                            <th>Mã dự thưởng</th>
                            <th>Phương thức Check-in</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <?php if(!isKinhDoanh()): ?><th>Thao tác</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="checkins-table-body">
                        <?php foreach($checkins as $c): 
                            $isByLuckyCode = ($c['checkin_method'] ?? '') === 'lucky_code' || (!empty($c['guest_normalized_phone']) && $c['normalized_phone'] !== $c['guest_normalized_phone']);
                        ?>
                        <script>
                            window.checkinsMap[<?php echo (int)$c['id']; ?>] = <?php echo json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                        </script>
                        <tr class="<?php echo $c['match_status'] === 'matched' ? 'row-checked-in' : ''; ?>">
                            <td><strong><?php echo esc($c['full_name_entered']); ?></strong></td>
                            <td><?php echo esc($c['phone_entered']); ?></td>
                            <td>
                                <?php if (!empty($c['table_name'])): ?>
                                    <strong style="color: #2e7d32;"><?php echo esc($c['table_name']); ?></strong>
                                <?php else: ?>
                                    <span style="color: #888;">Chưa xếp</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['lucky_draw_code'])): ?>
                                    <span style="font-weight: bold; color: #7b1fa2; background: #f3e5f5; border: 1px solid #e1bee7; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">
                                        🎟️ <?php echo esc($c['lucky_draw_code']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #aaa;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['match_status'] === 'walk_in'): ?>
                                    <span style="background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                        🔸 Khách phát sinh
                                    </span>
                                <?php elseif ($isByLuckyCode): ?>
                                    <span style="background: #f3e5f5; color: #7b1fa2; border: 1.5px solid #ab47bc; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(123, 31, 162, 0.15);">
                                        🎟️ Khớp Mã dự thưởng
                                    </span>
                                <?php else: ?>
                                    <span style="background: #e8f5e9; color: #1b5e20; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(46, 125, 50, 0.15);">
                                        📱 Khớp SĐT
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($c['checkin_time'])); ?></td>
                            <td>
                                <span class="badge <?php echo esc($c['match_status']); ?>">
                                    <?php echo $c['match_status'] === 'matched' ? '✅ Khách hợp lệ' : '🔸 Khách phát sinh'; ?>
                                </span>
                            </td>
                            <?php if(!isKinhDoanh()): ?>
                            <td>
                                <button type="button" class="btn btn-info" onclick="openAssignModal(<?php echo (int)$c['id']; ?>)">Xếp bàn</button>
                                
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
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const isKinhDoanhUser = <?php echo isKinhDoanh() ? 'true' : 'false'; ?>;
    const csrfTokenValue = '<?php echo $_SESSION[CSRF_TOKEN_KEY] ?? ""; ?>';
    
    function openAssignModal(checkinId) {
        const c = window.checkinsMap ? window.checkinsMap[checkinId] : null;
        if (!c) {
            console.error('Checkin record not found:', checkinId);
            return;
        }

        document.getElementById('modalCheckinId').value = c.id;
        document.getElementById('modalGuestName').innerText = c.full_name_entered || c.full_name || '';
        document.getElementById('modalGuestPhone').innerText = c.phone_entered || c.phone || '';
        
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

    async function updateRealtimeCheckinsList() {
        // Nếu modal xếp bàn đang mở, tạm hoãn cập nhật để không làm phiền thao tác của người dùng
        if (modal && modal.style.display === 'block') return;

        try {
            const response = await fetch(`../api/stats.php?filter=all&table_id=all&_t=${Date.now()}`, { cache: 'no-store' });
            if (!response.ok) return;
            const result = await response.json();

            if (result.status === 'success' && result.data.recent_checkins) {
                const tbody = document.getElementById('checkins-table-body');
                if (!tbody) return;

                if (result.data.recent_checkins.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${isKinhDoanhUser ? 7 : 8}" style="text-align:center; color:#777; padding:20px;">Chưa có lượt check-in nào</td></tr>`;
                    return;
                }

                let html = '';
                result.data.recent_checkins.forEach(item => {
                    window.checkinsMap[item.id] = item;

                    const isCheckedIn = item.status === 'matched';
                    const rowClass = isCheckedIn ? 'row-checked-in' : '';
                    
                    const luckyCodeHtml = item.lucky_draw_code 
                        ? `<span style="font-weight: bold; color: #7b1fa2; background: #f3e5f5; border: 1px solid #e1bee7; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">🎟️ ${item.lucky_draw_code}</span>` 
                        : `<span style="color:#aaa;">-</span>`;

                    let methodBadge = `<span style="background: #e8f5e9; color: #1b5e20; border: 1.5px solid #81c784; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(46, 125, 50, 0.15);">📱 Khớp SĐT</span>`;
                    if (item.status === 'walk_in') {
                        methodBadge = `<span style="background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">🔸 Khách phát sinh</span>`;
                    } else if (item.is_by_code) {
                        methodBadge = `<span style="background: #f3e5f5; color: #7b1fa2; border: 1.5px solid #ab47bc; padding: 4px 10px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(123, 31, 162, 0.15);">🎟️ Khớp Mã dự thưởng</span>`;
                    }

                    const statusBadge = isCheckedIn 
                        ? `<span class="badge matched">✅ Khách hợp lệ</span>` 
                        : `<span class="badge walk_in">🔸 Khách phát sinh</span>`;

                    const tableNameHtml = item.table_name && item.table_name !== 'Chưa xếp bàn'
                        ? `<strong style="color: #2e7d32;">${item.table_name}</strong>`
                        : `<span style="color: #888;">Chưa xếp</span>`;

                    let actionsHtml = '';
                    if (!isKinhDoanhUser) {
                        actionsHtml = `
                            <td>
                                <button type="button" class="btn btn-info" onclick="openAssignModal(${item.id})">Xếp bàn</button>
                                ${isAdminUser ? `
                                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa dòng check-in này (dữ liệu test)?');">
                                    <input type="hidden" name="csrf_token" value="${csrfTokenValue}">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="${item.id}">
                                    <button type="submit" class="btn btn-danger">Xóa Test</button>
                                </form>
                                ` : ''}
                            </td>
                        `;
                    }

                    html += `
                        <tr class="${rowClass}">
                            <td><strong>${item.full_name}</strong></td>
                            <td>${item.phone}</td>
                            <td>${tableNameHtml}</td>
                            <td>${luckyCodeHtml}</td>
                            <td>${methodBadge}</td>
                            <td>${item.time}</td>
                            <td>${statusBadge}</td>
                            ${actionsHtml}
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
            }
        } catch (e) {
            console.error('Realtime checkins update error:', e);
        }
    }

    // Chạy kiểm tra mỗi 3 giây
    updateRealtimeCheckinsList();
    setInterval(updateRealtimeCheckinsList, 3000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateRealtimeCheckinsList();
        }
    });
</script>

<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
