<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
requireAdmin();

$db = Database::getConnection();

// Xử lý Form POST
$message = '';
$error = '';
if (isPost()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $eventName = trim($_POST['event_name'] ?? '');
        $eventCode = trim($_POST['event_code'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $eventDate = $_POST['event_date'] ?? date('Y-m-d');
        $location = trim($_POST['location'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $checkinEnabled = isset($_POST['checkin_enabled']) ? 1 : 0;
        
        if (empty($eventName) || empty($slug)) {
            $error = 'Vui lòng nhập Tên và Slug sự kiện';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO events (event_name, event_code, slug, event_date, location, status, checkin_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$eventName, $eventCode, $slug, $eventDate, $location, $status, $checkinEnabled]);
                $message = 'Thêm sự kiện thành công!';
            } catch(PDOException $e) {
                $error = 'Lỗi thêm sự kiện (Có thể trùng Slug hoặc Mã sự kiện).';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $eventName = trim($_POST['event_name'] ?? '');
        $eventCode = trim($_POST['event_code'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $eventDate = $_POST['event_date'] ?? date('Y-m-d');
        $location = trim($_POST['location'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $checkinEnabled = isset($_POST['checkin_enabled']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE events SET event_name=?, event_code=?, slug=?, event_date=?, location=?, status=?, checkin_enabled=? WHERE id=?");
            $stmt->execute([$eventName, $eventCode, $slug, $eventDate, $location, $status, $checkinEnabled, $id]);
            $message = 'Cập nhật sự kiện thành công!';
        } catch(PDOException $e) {
            $error = 'Lỗi cập nhật sự kiện.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM events WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Xóa sự kiện thành công!';
    }
}

// Lấy danh sách sự kiện
$events = $db->query("SELECT * FROM events ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sự kiện - CheckinQR</title>
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
        .badge.active { background: #e8f5e9; color: #2e7d32; }
        
        /* Modal CSS */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 500px; max-width: 90%; }
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
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Danh sách Sự kiện</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <div style="margin-bottom: 15px;">
                <button class="btn btn-primary" onclick="openAddModal()">+ Thêm sự kiện mới</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tên sự kiện</th>
                            <th>Mã</th>
                            <th>Ngày tổ chức</th>
                            <th>Địa điểm</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                            <th>Link/QR Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $event): ?>
                        <tr>
                            <td><strong><?php echo esc($event['event_name']); ?></strong></td>
                            <td><?php echo esc($event['event_code']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($event['event_date'])); ?></td>
                            <td><?php echo esc($event['location'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo esc($event['status']); ?>">
                                    <?php echo $event['status'] === 'active' ? '🟢 Đang diễn ra' : '🔴 Đã kết thúc'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-success" style="padding:4px 8px; font-size:0.8rem;" onclick='openEditModal(<?php echo json_encode($event); ?>)'>Sửa</button>
                                <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa sự kiện này? Toàn bộ khách và lịch sử check-in sẽ bị xóa theo!');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.8rem;">Xóa</button>
                                </form>
                            </td>
                            <td>
                                <a href="<?php echo url('?event=' . esc($event['slug'])); ?>" target="_blank" class="btn btn-success" style="padding:4px 8px; font-size:0.8rem;">Form</a>
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode(url('?event=' . esc($event['slug']))); ?>" target="_blank" class="btn btn-primary" style="padding:4px 8px; font-size:0.8rem;">QR Code</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Thêm Sự Kiện</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="eventId" value="">
            
            <div class="form-group">
                <label>Tên sự kiện *</label>
                <input type="text" name="event_name" id="eventName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Mã sự kiện</label>
                <input type="text" name="event_code" id="eventCode" class="form-control">
            </div>
            <div class="form-group">
                <label>Slug (Đường dẫn không dấu) *</label>
                <input type="text" name="slug" id="eventSlug" class="form-control" required placeholder="vi-du: workshop-2026">
            </div>
            <div class="form-group">
                <label>Ngày tổ chức</label>
                <input type="date" name="event_date" id="eventDate" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Địa điểm tổ chức</label>
                <input type="text" name="location" id="eventLocation" class="form-control" placeholder="Ví dụ: Hội trường Công ty Hòa Vinh">
            </div>
            <div class="form-group">
                <label>Trạng thái</label>
                <select name="status" id="eventStatus" class="form-control">
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="checkin_enabled" id="checkinEnabled" value="1" checked> 
                    Cho phép Check-in (Mở form)
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('eventModal');
    
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Sự Kiện';
        document.getElementById('formAction').value = 'add';
        document.getElementById('eventId').value = '';
        document.getElementById('eventName').value = '';
        document.getElementById('eventCode').value = '';
        document.getElementById('eventSlug').value = '';
        document.getElementById('eventDate').value = '';
        document.getElementById('eventLocation').value = '';
        document.getElementById('eventStatus').value = 'active';
        document.getElementById('checkinEnabled').checked = true;
        modal.style.display = 'block';
    }
    
    function openEditModal(eventData) {
        document.getElementById('modalTitle').innerText = 'Sửa Sự Kiện';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('eventId').value = eventData.id;
        document.getElementById('eventName').value = eventData.event_name;
        document.getElementById('eventCode').value = eventData.event_code;
        document.getElementById('eventSlug').value = eventData.slug;
        document.getElementById('eventDate').value = eventData.event_date;
        document.getElementById('eventLocation').value = eventData.location || '';
        document.getElementById('eventStatus').value = eventData.status;
        document.getElementById('checkinEnabled').checked = eventData.checkin_enabled == 1;
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
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
