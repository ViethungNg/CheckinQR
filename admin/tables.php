<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
requireAdmin();

$db = Database::getConnection();

$message = '';
$error = '';
if (isPost()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $eventId = (int)$_POST['event_id'];
        $tableName = trim($_POST['table_name'] ?? '');
        $tableCode = trim($_POST['table_code'] ?? '');
        $capacity = (int)$_POST['capacity'];
        $location = trim($_POST['location'] ?? '');
        
        if (empty($tableName) || empty($eventId)) {
            $error = 'Vui lòng chọn sự kiện và nhập tên bàn';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO event_tables (event_id, table_name, table_code, capacity, location) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$eventId, $tableName, $tableCode, $capacity, $location]);
                $message = 'Thêm bàn thành công!';
            } catch(PDOException $e) {
                $error = 'Lỗi thêm bàn (Có thể trùng mã bàn).';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $eventId = (int)$_POST['event_id'];
        $tableName = trim($_POST['table_name'] ?? '');
        $tableCode = trim($_POST['table_code'] ?? '');
        $capacity = (int)$_POST['capacity'];
        $location = trim($_POST['location'] ?? '');
        
        try {
            $stmt = $db->prepare("UPDATE event_tables SET event_id=?, table_name=?, table_code=?, capacity=?, location=? WHERE id=?");
            $stmt->execute([$eventId, $tableName, $tableCode, $capacity, $location, $id]);
            $message = 'Cập nhật bàn thành công!';
        } catch(PDOException $e) {
            $error = 'Lỗi cập nhật bàn.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM event_tables WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Xóa bàn thành công!';
    }
}

// Lấy danh sách sự kiện để đưa vào dropdown
$events = $db->query("SELECT id, event_name FROM events ORDER BY id DESC")->fetchAll();

$tables = $db->query("
    SELECT t.*, e.event_name,
    (SELECT COUNT(*) FROM guests WHERE table_id = t.id) as current_guests,
    (SELECT COUNT(*) FROM checkins WHERE table_id = t.id) as actual_checkins
    FROM event_tables t 
    LEFT JOIN events e ON t.event_id = e.id 
    ORDER BY t.event_id DESC, t.table_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Bàn - CheckinQR</title>
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
        
        /* Modal CSS */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 450px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2>CheckinQR</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="events.php">Quản lý Sự kiện</a></li>
            <li><a href="guests.php">Khách dự kiến</a></li>
            <li><a href="checkins.php">Check-in trực tiếp</a></li>
            <li><a href="tables.php" class="active">Quản lý bàn</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Quản lý Bàn Sự kiện</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <div style="margin-bottom: 15px;">
                <button class="btn btn-primary" onclick="openAddModal()">+ Thêm bàn mới</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Tên bàn</th>
                        <th>Mã bàn</th>
                        <th>Sự kiện</th>
                        <th>Sức chứa</th>
                        <th>Đã xếp (Dự kiến)</th>
                        <th>Đã vào bàn (Thực tế)</th>
                        <th>Vị trí</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tables as $t): ?>
                    <tr>
                        <td><strong><?php echo esc($t['table_name']); ?></strong></td>
                        <td><?php echo esc($t['table_code']); ?></td>
                        <td><?php echo esc($t['event_name']); ?></td>
                        <td><?php echo esc($t['capacity']); ?> người</td>
                        <td style="color: <?php echo $t['current_guests'] > $t['capacity'] ? 'red' : '#1565c0'; ?>; font-weight: bold;">
                            <?php echo esc($t['current_guests']); ?> / <?php echo esc($t['capacity']); ?>
                        </td>
                        <td style="color: <?php echo $t['actual_checkins'] > $t['capacity'] ? 'red' : '#2e7d32'; ?>; font-weight: bold;">
                            <?php echo esc($t['actual_checkins']); ?> / <?php echo esc($t['capacity']); ?>
                        </td>
                        <td><?php echo esc($t['location'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-success" style="padding:4px 8px; font-size:0.8rem;" onclick='openEditModal(<?php echo json_encode($t); ?>)'>Sửa</button>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa bàn này? Khách trong bàn sẽ bị mất vị trí.');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.8rem;">Xóa</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div id="tableModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Thêm Bàn Mới</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="tableId" value="">
            
            <div class="form-group">
                <label>Sự kiện *</label>
                <select name="event_id" id="eventId" class="form-control" required>
                    <?php foreach($events as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo esc($e['event_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tên bàn *</label>
                <input type="text" name="table_name" id="tableName" class="form-control" required placeholder="Ví dụ: Bàn VIP">
            </div>
            <div class="form-group">
                <label>Mã bàn (Dùng import CSV)</label>
                <input type="text" name="table_code" id="tableCode" class="form-control" placeholder="Ví dụ: VIP-01">
            </div>
            <div class="form-group">
                <label>Sức chứa (người)</label>
                <input type="number" name="capacity" id="tableCapacity" class="form-control" value="10" required>
            </div>
            <div class="form-group">
                <label>Vị trí</label>
                <input type="text" name="location" id="tableLocation" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('tableModal');
    
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Bàn Mới';
        document.getElementById('formAction').value = 'add';
        document.getElementById('tableId').value = '';
        document.getElementById('tableName').value = '';
        document.getElementById('tableCode').value = '';
        document.getElementById('tableCapacity').value = '10';
        document.getElementById('tableLocation').value = '';
        modal.style.display = 'block';
    }
    
    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Sửa Bàn';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('tableId').value = data.id;
        document.getElementById('eventId').value = data.event_id;
        document.getElementById('tableName').value = data.table_name;
        document.getElementById('tableCode').value = data.table_code;
        document.getElementById('tableCapacity').value = data.capacity;
        document.getElementById('tableLocation').value = data.location;
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
    // window.onclick = function(event) {
    //     if (event.target == modal) {
    //         closeModal();
    //     }
    // }
</script>
</body>
</html>
