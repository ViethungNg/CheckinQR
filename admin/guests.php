<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

$message = '';
$error = '';
if (isPost() && isAdmin()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $eventId = (int)$_POST['event_id'];
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
                $stmt = $db->prepare("INSERT INTO guests (event_id, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$eventId, $fullName, $phone, $normalizedPhone, $tableId, $luckyDrawCode, $organization, $status]);
                $message = 'Thêm khách thành công!';
            } catch(PDOException $e) {
                $error = 'Lỗi thêm khách (Có thể trùng số điện thoại trong cùng sự kiện).';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $eventId = (int)$_POST['event_id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $tableId = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;
        $luckyDrawCode = trim($_POST['lucky_draw_code'] ?? '');
        $organization = trim($_POST['organization'] ?? '');
        $status = $_POST['status'] ?? 'invited';
        
        $normalizedPhone = normalizePhone($phone);
        
        try {
            $stmt = $db->prepare("UPDATE guests SET event_id=?, full_name=?, phone=?, normalized_phone=?, table_id=?, lucky_draw_code=?, organization=?, status=? WHERE id=?");
            $stmt->execute([$eventId, $fullName, $phone, $normalizedPhone, $tableId, $luckyDrawCode, $organization, $status, $id]);
            $message = 'Cập nhật thông tin khách thành công!';
        } catch(PDOException $e) {
            $error = 'Lỗi cập nhật khách.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM guests WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Xóa khách thành công!';
    }
}
elseif (isPost() && !isAdmin()) {
    $error = 'Bạn không có quyền thực hiện thao tác này.';
}

// Lấy danh sách sự kiện và bàn để đưa vào form
$events = $db->query("SELECT id, event_name FROM events ORDER BY id DESC")->fetchAll();
$tablesList = $db->query("SELECT id, table_name, event_id FROM event_tables ORDER BY table_name ASC")->fetchAll();

// Lấy tham số Tìm kiếm & Sắp xếp
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort'] ?? 'id_desc';

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $normalizedSearch = normalizePhone($search);
    $searchLike = "%$search%";
    $normLike = "%$normalizedSearch%";
    $whereClauses[] = "(g.full_name LIKE ? OR g.phone LIKE ? OR g.normalized_phone LIKE ? OR g.lucky_draw_code LIKE ? OR t.table_name LIKE ? OR t.table_code LIKE ?)";
    $params = [$searchLike, $searchLike, $normLike, $searchLike, $searchLike, $searchLike];
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

$orderSql = "ORDER BY g.id DESC";
if ($sort === 'table_asc') {
    $orderSql = "ORDER BY t.table_name ASC, g.id DESC";
} elseif ($sort === 'table_desc') {
    $orderSql = "ORDER BY t.table_name DESC, g.id DESC";
} elseif ($sort === 'code_asc') {
    $orderSql = "ORDER BY CAST(g.lucky_draw_code AS UNSIGNED) ASC, g.lucky_draw_code ASC, g.id DESC";
} elseif ($sort === 'code_desc') {
    $orderSql = "ORDER BY CAST(g.lucky_draw_code AS UNSIGNED) DESC, g.lucky_draw_code DESC, g.id DESC";
}

$stmtGuests = $db->prepare("
    SELECT g.*, e.event_name, t.table_name 
    FROM guests g 
    LEFT JOIN events e ON g.event_id = e.id 
    LEFT JOIN event_tables t ON g.table_id = t.id 
    {$whereSql}
    {$orderSql}
");
$stmtGuests->execute($params);
$guests = $stmtGuests->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khách dự kiến - CheckinQR</title>
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
    <div class="sidebar">
        <h2>CheckinQR</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <?php if(isAdmin()): ?>
            <li><a href="events.php">Quản lý sự kiện</a></li>
            <?php endif; ?>
            <li><a href="guests.php" class="active">Danh sách khách hàng dự kiến</a></li>
            <li><a href="checkins.php">Khách hàng đã checkin</a></li>
            <?php if(isAdmin()): ?>
            <li><a href="tables.php">Quản lý bàn</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Khách dự kiến (<span id="guest-count-title"><?php echo count($guests); ?></span>)</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <?php if(isAdmin()): ?>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="openAddModal()">+ Thêm khách thủ công</button>
                    <a href="import.php" class="btn btn-success">📥 Import từ Excel (.xlsx)</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thanh Lọc & Sắp Xếp Thông Minh (Live Typing Search) -->
            <form method="GET" action="" id="search-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; background: #f8f9fa; padding: 12px 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                <div style="flex: 1; min-width: 240px; position: relative;">
                    <input type="text" id="search-input" name="search" value="<?php echo esc($search); ?>" placeholder="⚡ Gõ tới đâu tìm tới đó: SĐT, Bàn, Mã dự thưởng, Họ tên..." class="form-control" oninput="liveSearchGuests(this.value)" autocomplete="off">
                </div>
                
                <div style="min-width: 220px;">
                    <select name="sort" class="form-control" onchange="this.form.submit()" style="cursor: pointer; background: #fff; font-weight: 500;">
                        <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>🕒 Mới nhất trước</option>
                        <option value="table_asc" <?php echo $sort === 'table_asc' ? 'selected' : ''; ?>>🪑 Bàn: Tăng dần (A ➔ Z)</option>
                        <option value="table_desc" <?php echo $sort === 'table_desc' ? 'selected' : ''; ?>>🪑 Bàn: Giảm dần (Z ➔ A)</option>
                        <option value="code_asc" <?php echo $sort === 'code_asc' ? 'selected' : ''; ?>>🎁 Mã dự thưởng: Tăng dần (0 ➔ 9)</option>
                        <option value="code_desc" <?php echo $sort === 'code_desc' ? 'selected' : ''; ?>>🎁 Mã dự thưởng: Giảm dần (9 ➔ 0)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 8px 18px;">Tìm kiếm</button>
                <?php if(!empty($search) || $sort !== 'id_desc'): ?>
                    <a href="guests.php" class="btn" style="background: #757575; color: white;">Xóa lọc</a>
                <?php endif; ?>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Họ Tên</th>
                        <th>SĐT</th>
                        <th>Đơn vị</th>
                        <th>
                            <a href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort === 'table_asc' ? 'table_desc' : 'table_asc'; ?>" class="sort-header" title="Bấm để đổi chiều sắp xếp bàn">
                                Bàn <?php echo $sort === 'table_asc' ? '▲' : ($sort === 'table_desc' ? '▼' : '↕'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort === 'code_asc' ? 'code_desc' : 'code_asc'; ?>" class="sort-header" title="Bấm để đổi chiều sắp xếp Mã dự thưởng">
                                Mã dự thưởng <?php echo $sort === 'code_asc' ? '▲' : ($sort === 'code_desc' ? '▼' : '↕'); ?>
                            </a>
                        </th>
                        <th>Trạng thái</th>
                        <?php if(isAdmin()): ?><th>Thao tác</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="guests-table-body">
                <tbody>
                    <?php foreach($guests as $guest): ?>
                    <tr>
                        <td><strong><?php echo esc($guest['full_name']); ?></strong></td>
                        <td><?php echo esc($guest['phone']); ?></td>
                        <td><?php echo esc($guest['organization'] ?? '-'); ?></td>
                        <td><?php echo esc($guest['table_name'] ?? 'Chưa xếp'); ?></td>
                        <td><?php echo esc($guest['lucky_draw_code'] ?? '-'); ?></td>
                        <td><span class="badge <?php echo esc($guest['status']); ?>"><?php echo esc($guest['status']); ?></span></td>
                        <?php if(isAdmin()): ?>
                        <td>
                            <button class="btn btn-success" style="padding:4px 8px; font-size:0.8rem;" onclick='openEditModal(<?php echo json_encode($guest); ?>)'>Sửa</button>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa khách này?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $guest['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.8rem;">Xóa</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

<script>
    const modal = document.getElementById('guestModal');
    const allTables = <?php echo json_encode($tablesList); ?>;
    const tableSelect = document.getElementById('tableId');
    
    function filterTables(eventId, selectedTableId = null) {
        tableSelect.innerHTML = '<option value="">-- Chưa xếp bàn --</option>';
        if (!eventId) return;
        
        allTables.forEach(t => {
            if (t.event_id == eventId) {
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
        document.getElementById('eventId').value = '';
        document.getElementById('fullName').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('luckyCode').value = '';
        document.getElementById('organization').value = '';
        document.getElementById('status').value = 'invited';
        filterTables(null);
        modal.style.display = 'block';
    }
    
    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Sửa Thông Tin Khách';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('guestId').value = data.id;
        document.getElementById('eventId').value = data.event_id;
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
    
    // Hàm Lọc Siêu Tốc 0ms: Gõ tới đâu lọc tới đó ngay trên màn hình!
    function liveSearchGuests(val) {
        const query = val.toLowerCase().trim();
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
        if (counter) {
            counter.textContent = count;
        }
    }
</script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
