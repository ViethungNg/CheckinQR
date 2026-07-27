<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

// Chỉ Admin mới được truy cập trang Quản lý tài khoản
if (!isAdmin()) {
    redirect(url('/admin/index.php'));
}

$db = Database::getConnection();

$message = '';
$error = '';

if (isPost()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'staff';
        $status   = $_POST['status'] ?? 'active';
        
        if (empty($username) || empty($password) || empty($fullName)) {
            $error = 'Vui lòng điền đầy đủ Tên đăng nhập, Mật khẩu và Họ tên!';
        } else {
            // Kiểm tra username trùng lặp
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác!';
            } else {
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $password, $fullName, $role, $status]);
                $message = 'Tạo tài khoản mới thành công!';
            }
        }
    } elseif ($action === 'edit') {
        $id       = (int)$_POST['id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'staff';
        $status   = $_POST['status'] ?? 'active';
        $password = trim($_POST['password'] ?? '');
        
        if (empty($fullName)) {
            $error = 'Họ tên không được để trống!';
        } else {
            if (!empty($password)) {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, role = ?, status = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$fullName, $role, $status, $password, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$fullName, $role, $status, $id]);
            }
            $message = 'Cập nhật tài khoản thành công!';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Không cho phép tự xóa tài khoản của chính mình
        if ($id === (int)$_SESSION['admin_id']) {
            $error = 'Bạn không thể tự xóa tài khoản đang đăng nhập của chính mình!';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Xóa tài khoản thành công!';
        }
    }
}

// Lấy danh sách tài khoản
$usersList = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tài khoản Admin - CheckinQR</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .badge-role { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 0.85rem; display: inline-block; }
        .role-admin { background: #e3f2fd; color: #1565c0; }
        .role-staff { background: #f3e5f5; color: #7b1fa2; }
        
        .badge-status { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 0.85rem; display: inline-block; }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-inactive { background: #ffebee; color: #c62828; }
        
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
            <li><a href="events.php">Quản lý sự kiện</a></li>
            <li><a href="guests.php">Danh sách khách hàng dự kiến</a></li>
            <li><a href="checkins.php">Khách hàng đã checkin</a></li>
            <li><a href="tables.php">Quản lý bàn</a></li>
            <li><a href="users.php" class="active">Quản lý tài khoản</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Quản lý Tài khoản Đăng nhập</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <div style="margin-bottom: 15px;">
                <button class="btn btn-primary" onclick="openAddModal()">+ Tạo tài khoản mới</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Họ và tên</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Đăng nhập cuối</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usersList as $u): ?>
                    <tr>
                        <td>#<?php echo $u['id']; ?></td>
                        <td><strong><?php echo esc($u['username']); ?></strong></td>
                        <td><?php echo esc($u['full_name']); ?></td>
                        <td>
                            <span class="badge-role role-<?php echo esc($u['role']); ?>">
                                <?php echo getRoleLabel($u['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status <?php echo $u['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $u['status'] === 'active' ? '● Đang hoạt động' : '○ Đã khóa'; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : 'Chưa đăng nhập'; ?>
                        </td>
                        <td>
                            <button class="btn btn-success" style="padding:4px 8px; font-size:0.8rem;" onclick='openEditModal(<?php echo json_encode($u); ?>)'>Sửa</button>
                            <?php if ($u['id'] !== (int)$_SESSION['admin_id']): ?>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.8rem;">Xóa</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa Tài khoản -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tạo Tài Khoản Mới</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form action="" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId" value="">
            
            <div class="form-group">
                <label>Tên đăng nhập *</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Mật khẩu <span id="passNote" style="font-weight: normal; color: #666;">*</span></label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Nhập mật khẩu...">
            </div>

            <div class="form-group">
                <label>Họ và tên *</label>
                <input type="text" name="full_name" id="fullName" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Vai trò *</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="admin">👑 Admin (Quản trị viên - Toàn quyền)</option>
                    <option value="letan">👤 Lễ tân (Xem check-in & Xếp bàn tại sự kiện)</option>
                    <option value="kinhdoanh">💼 Kinh doanh (Xem khách hàng thuộc bàn mình phụ trách)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Trạng thái *</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active">● Đang hoạt động (cho phép đăng nhập)</option>
                    <option value="inactive">○ Đã khóa (không cho đăng nhập)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Lưu Tài Khoản</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('userModal');
    
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Tạo Tài Khoản Mới';
        document.getElementById('formAction').value = 'add';
        document.getElementById('userId').value = '';
        
        const userInput = document.getElementById('username');
        userInput.value = '';
        userInput.readOnly = false;
        
        const passInput = document.getElementById('password');
        passInput.value = '';
        passInput.required = true;
        document.getElementById('passNote').innerText = '*';
        
        document.getElementById('fullName').value = '';
        document.getElementById('role').value = 'staff';
        document.getElementById('status').value = 'active';
        
        modal.style.display = 'block';
    }
    
    function openEditModal(u) {
        document.getElementById('modalTitle').innerText = 'Sửa Tài Khoản: ' + u.username;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('userId').value = u.id;
        
        const userInput = document.getElementById('username');
        userInput.value = u.username;
        userInput.readOnly = true;
        
        const passInput = document.getElementById('password');
        passInput.value = '';
        passInput.required = false;
        document.getElementById('passNote').innerText = '(Để trống nếu không muốn đổi mật khẩu)';
        
        document.getElementById('fullName').value = u.full_name;
        document.getElementById('role').value = u.role;
        document.getElementById('status').value = u.status;
        
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
</script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
