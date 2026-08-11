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
    
    try {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $role     = $_POST['role'] ?? 'letan';
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
            $role     = $_POST['role'] ?? 'letan';
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
    } catch (\Throwable $e) {
        $error = 'Đã xảy ra lỗi khi xử lý CSDL: ' . $e->getMessage();
    }
}

// Lấy danh sách tài khoản
$usersList = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

// Thống kê vai trò
$countTotal = count($usersList);
$countAdmin = 0;
$countLeTan = 0;
$countKD    = 0;

foreach ($usersList as $u) {
    if (in_array($u['role'], ['admin', 'super_admin'])) $countAdmin++;
    elseif ($u['role'] === 'kinhdoanh') $countKD++;
    else $countLeTan++;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=0.5, user-scalable=yes, viewport-fit=cover">
    <title>PMT - Checkin - Quản lý tài khoản Admin</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin-polish.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-polish.css'); ?>">
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header h1 { font-size: 1.8rem; font-weight: 700; color: #111; }
        
        /* Stats Grid */
        .user-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: #fff; border-radius: 10px; padding: 18px 20px; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.03); transition: all 0.2s; cursor: pointer; user-select: none; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-card.active { border-width: 2px !important; background: #fafafa !important; box-shadow: 0 4px 14px rgba(0,0,0,0.12) !important; transform: translateY(-2px); }
        .stat-card h4 { font-size: 0.85rem; text-transform: uppercase; color: #666; margin-bottom: 8px; font-weight: 600; }
        .stat-card .val { font-size: 1.8rem; font-weight: 800; color: #222; }

        /* Modern Table & Filters */
        .content-box { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #eef2f5; }
        .search-toolbar { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 20px; background: #fafafa; padding: 12px 16px; border-radius: 8px; border: 1px solid #eee; }
        .search-box { flex: 1; min-width: 260px; position: relative; }
        .search-input { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; outline: none; transition: border 0.2s; }
        .search-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(211,47,47,0.1); }

        /* Table Aesthetics */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        th { font-weight: 600; color: #555; background: #fcfcfc; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.5px; }
        tbody tr:hover { background-color: #fbfbfb; }

        /* User Avatar Initials */
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #d32f2f, #ef5350); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.95rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2); flex-shrink: 0; }
        .user-info-name { font-weight: 600; color: #222; font-size: 0.95rem; }
        .user-info-sub { font-size: 0.8rem; color: #777; }

        /* Role Badges */
        .badge-role { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px; }
        .role-admin { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .role-letan, .role-staff { background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; }
        .role-kinhdoanh { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        
        /* Status Badges */
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 5px; }
        .status-active { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-inactive { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: var(--primary-color); color: #fff; box-shadow: 0 2px 6px rgba(211,47,47,0.2); }
        .btn-primary:hover { background: #b71c1c; transform: translateY(-1px); }
        .btn-success { background: #2e7d32; color: #fff; }
        .btn-success:hover { background: #1b5e20; }
        .btn-danger { background: #c62828; color: #fff; }
        .btn-danger:hover { background: #b71c1c; }

        /* Modal Design */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.55); backdrop-filter: blur(3px); }
        .modal-content { background-color: #fff; margin: 4% auto; padding: 26px; border-radius: 12px; width: 480px; max-width: 92%; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.8); animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 12px; }
        .modal-header h3 { font-size: 1.3rem; font-weight: 700; color: #222; }
        .close { font-size: 26px; font-weight: bold; cursor: pointer; color: #999; transition: color 0.2s; }
        .close:hover { color: #d32f2f; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: #444; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; outline: none; transition: border 0.2s; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(211,47,47,0.1); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert.error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>Quản lý Tài khoản Đăng nhập</h1>
        </div>
        
        <?php if ($message): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>, 'success'); });</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>, 'error'); });</script>
        <?php endif; ?>

        <!-- Stats Bar with Interactive Filtering -->
        <div class="user-stats-grid">
            <div class="stat-card active" id="card-filter-all" onclick="filterUserRole('all', this)" title="Xem tất cả tài khoản">
                <h4>TỔNG TÀI KHOẢN</h4>
                <div class="val" id="stat-count-total"><?php echo $countTotal; ?></div>
            </div>
            <div class="stat-card" id="card-filter-admin" style="border-left: 4px solid #1565c0;" onclick="filterUserRole('admin', this)" title="Lọc tài khoản Admin">
                <h4 style="color: #1565c0;">QUẢN TRỊ VIÊN (ADMIN)</h4>
                <div class="val" style="color: #1565c0;"><?php echo $countAdmin; ?></div>
            </div>
            <div class="stat-card" id="card-filter-letan" style="border-left: 4px solid #7b1fa2;" onclick="filterUserRole('letan', this)" title="Lọc tài khoản Lễ tân">
                <h4 style="color: #7b1fa2;">LỄ TÂN (LE TAN)</h4>
                <div class="val" style="color: #7b1fa2;"><?php echo $countLeTan; ?></div>
            </div>
            <div class="stat-card" id="card-filter-kinhdoanh" style="border-left: 4px solid #e65100;" onclick="filterUserRole('kinhdoanh', this)" title="Lọc tài khoản Kinh doanh">
                <h4 style="color: #e65100;">KINH DOANH (SALES)</h4>
                <div class="val" style="color: #e65100;"><?php echo $countKD; ?></div>
            </div>
        </div>

        <div class="content-box">
            <!-- Search & Actions Bar -->
            <div class="search-toolbar">
                <div class="search-box" style="position: relative;">
                    <input type="text" id="user-search" class="search-input" placeholder="Tìm theo Username, Họ tên, Vai trò..." oninput="liveFilterUsers(this.value)" autocomplete="off" style="padding-right: 65px;">
                    <span class="kbd-badge hide-mobile" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">Ctrl K</span>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    Tạo tài khoản mới
                </button>
            </div>

            <div class="table-responsive mobile-card-container">
                <table class="mobile-card-table">
                    <thead>
                        <tr>
                            <th>Tài khoản</th>
                            <th>Họ và tên</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Lần đăng nhập cuối</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach($usersList as $u): 
                            $firstChar = mb_strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8'));
                        ?>
                        <tr data-role="<?php echo esc($u['role']); ?>">
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo esc($firstChar); ?></div>
                                    <div>
                                        <div class="user-info-name">@<?php echo esc($u['username']); ?></div>
                                        <div class="user-info-sub">ID: #<?php echo $u['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><strong><?php echo esc($u['full_name']); ?></strong></td>
                            <td>
                                <span class="badge-role role-<?php echo esc($u['role']); ?>">
                                    <?php echo getRoleLabel($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $u['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $u['status'] === 'active' ? 'Hoạt động' : 'Đã khóa'; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.88rem; color: #555;">
                                    <?php echo !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : 'Chưa đăng nhập'; ?>
                                </span>
                            </td>
                            <td class="col-actions" data-label="Thao tác">
                                <div class="action-btns-wrapper">
                                    <button class="btn btn-action-edit" style="padding: 5px 10px; font-size: 0.82rem;" onclick='openEditModal(<?php echo json_encode($u); ?>)'>Sửa</button>
                                    <button class="btn" style="padding: 5px 10px; font-size: 0.82rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; cursor: pointer;" onclick="openVerifyAdminPassModal(<?php echo $u['id']; ?>, '<?php echo esc($u['username']); ?>')">Xem MK</button>
                                    <?php if ($u['id'] !== (int)$_SESSION['admin_id']): ?>
                                    <form action="" method="POST" style="display:flex; flex:1 1 0; min-width:0; width:100%; margin:0;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa tài khoản này?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-action-danger" style="padding: 5px 10px; font-size: 0.82rem; width: 100%;">Xóa</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                <input type="text" name="username" id="username" class="form-control" placeholder="Ví dụ: nhanvien1" required>
            </div>

            <div class="form-group">
                <label>Mật khẩu <span id="passNote" style="font-weight: normal; color: #d32f2f;">*</span></label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Nhập mật khẩu...">
            </div>

            <div class="form-group">
                <label>Họ và tên người dùng *</label>
                <input type="text" name="full_name" id="fullName" class="form-control" placeholder="Ví dụ: Nguyễn Văn A" required>
            </div>
            
            <div class="form-group">
                <label>Phân quyền vai trò *</label>
                <select name="role" id="role" class="form-control" required style="cursor: pointer; background: #fff;">
                    <option value="admin">Admin (Quản trị viên - Toàn quyền hệ thống)</option>
                    <option value="letan">Lễ tân (Xem check-in thực tế & Xếp bàn cho khách)</option>
                    <option value="kinhdoanh">Kinh doanh (Theo dõi danh sách khách thuộc bàn phụ trách)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Trạng thái tài khoản *</label>
                <select name="status" id="status" class="form-control" required style="cursor: pointer; background: #fff;">
                    <option value="active">Đang hoạt động (Cho phép đăng nhập)</option>
                    <option value="inactive">Đã khóa (Không cho đăng nhập)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px; padding: 12px; justify-content: center; font-size: 1rem; border-radius: 8px;">
                Lưu Thông Tin Tài Khoản
            </button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('userModal');
    let currentRoleFilter = 'all';
    
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
        document.getElementById('role').value = 'letan';
        document.getElementById('status').value = 'active';
        
        modal.style.display = 'block';
    }
    
    function openEditModal(u) {
        document.getElementById('modalTitle').innerText = 'Sửa Tài Khoản: @' + u.username;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('userId').value = u.id;
        
        const userInput = document.getElementById('username');
        userInput.value = u.username;
        userInput.readOnly = true;
        
        const passInput = document.getElementById('password');
        passInput.value = '';
        passInput.required = false;
        document.getElementById('passNote').innerText = '(Bỏ trống nếu giữ nguyên mật khẩu cũ)';
        
        document.getElementById('fullName').value = u.full_name;
        document.getElementById('role').value = u.role;
        document.getElementById('status').value = u.status;
        
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }

    function filterUserRole(role, cardEl) {
        currentRoleFilter = role;
        
        document.querySelectorAll('.user-stats-grid .stat-card').forEach(card => {
            card.classList.remove('active');
        });

        if (cardEl) {
            cardEl.classList.add('active');
        }

        applyUserFilters();
        if (window.scrollToTableSectionOnMobile) window.scrollToTableSectionOnMobile();
    }

    function liveFilterUsers(query) {
        applyUserFilters();
    }

    function applyUserFilters() {
        const searchInput = document.getElementById('user-search');
        const query = searchInput ? (searchInput.value || '').toLowerCase().trim() : '';
        const rows = document.querySelectorAll('#users-table-body tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowRole = row.getAttribute('data-role') || '';
            const rowText = row.innerText.toLowerCase();

            const matchesRole = (currentRoleFilter === 'all') || 
                (currentRoleFilter === 'admin' && (rowRole === 'admin' || rowRole === 'super_admin')) ||
                (currentRoleFilter === 'letan' && (rowRole === 'letan' || rowRole === 'staff')) ||
                (currentRoleFilter === 'kinhdoanh' && rowRole === 'kinhdoanh');

            const matchesSearch = query === '' || rowText.includes(query);

            if (matchesRole && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const statTotal = document.getElementById('stat-count-total');
        if (statTotal) {
            statTotal.textContent = visibleCount;
        }
    }
</script>

<!-- Modal Xác Nhận Mật Khẩu Admin Để Xem Mật Khẩu User -->
<div id="verifyAdminPassModal" class="modal" style="display:none; z-index:12000;">
    <div class="modal-content" style="max-width: 420px; border-radius: 12px; padding: 22px;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:12px; margin-bottom:16px;">
            <h3 style="font-size:1.15rem; font-weight:700; color:#222; margin:0;">Xác Nhận Quyền Admin</h3>
            <span class="close" onclick="closeVerifyAdminPassModal()" style="font-size:24px; cursor:pointer; color:#888;">&times;</span>
        </div>

        <div id="verifyPassAlert" style="display:none; padding:10px 14px; border-radius:8px; font-size:0.9rem; margin-bottom:14px;"></div>

        <form id="verifyAdminPassForm" onsubmit="submitVerifyAdminPass(event)">
            <?php echo csrfField(); ?>
            <input type="hidden" id="target_user_id" name="target_user_id" value="">
            
            <p style="font-size:0.92rem; color:#444; margin-bottom:14px; line-height:1.4;">
                Vui lòng nhập chính xác <strong>Mật khẩu Admin của bạn</strong> để xem mật khẩu tài khoản <strong id="target_username_display" style="color:#d32f2f;"></strong>:
            </p>

            <div class="form-group" style="margin-bottom:18px;">
                <input type="password" id="admin_verify_password" name="admin_password" class="form-control" placeholder="Nhập mật khẩu Admin..." required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeVerifyAdminPassModal()" style="background:#e2e8f0; color:#475569; padding:9px 16px; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Hủy</button>
                <button type="submit" id="btnSubmitVerifyPass" class="btn btn-primary" style="background:#0284c7; color:#fff; padding:9px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Xem Mật Khẩu</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openVerifyAdminPassModal(targetUserId, targetUsername) {
        document.getElementById('target_user_id').value = targetUserId;
        document.getElementById('target_username_display').textContent = '@' + targetUsername;
        document.getElementById('admin_verify_password').value = '';
        const alertBox = document.getElementById('verifyPassAlert');
        if (alertBox) alertBox.style.display = 'none';
        document.getElementById('verifyAdminPassModal').style.display = 'block';
    }

    function closeVerifyAdminPassModal() {
        document.getElementById('verifyAdminPassModal').style.display = 'none';
    }

    async function submitVerifyAdminPass(e) {
        e.preventDefault();
        const form = e.target;
        const alertBox = document.getElementById('verifyPassAlert');
        const submitBtn = document.getElementById('btnSubmitVerifyPass');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang kiểm tra...';

        try {
            const formData = new FormData(form);
            const response = await fetch('../api/view_user_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            alertBox.style.display = 'block';
            if (result.status === 'success') {
                alertBox.style.background = '#e8f5e9';
                alertBox.style.color = '#1b5e20';
                alertBox.style.border = '1.5px solid #81c784';
                alertBox.innerHTML = `
                    <div style="font-weight:bold; margin-bottom:4px;">Xác nhận Admin thành công!</div>
                    <div>Tài khoản: <strong>@${result.username}</strong> (${result.full_name})</div>
                    <div style="margin-top:6px; font-size:1.05rem;">Mật khẩu: <strong style="color:#d32f2f; background:#fff; padding:2px 8px; border-radius:4px; border:1px solid #ffcdd2;">${result.password}</strong></div>
                `;
            } else {
                alertBox.style.background = '#ffebee';
                alertBox.style.color = '#c62828';
                alertBox.style.border = '1px solid #ffcdd2';
                alertBox.innerHTML = result.message || 'Mật khẩu Admin không chính xác!';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#ffebee';
            alertBox.style.color = '#c62828';
            alertBox.style.border = '1px solid #ffcdd2';
            alertBox.innerHTML = 'Đã xảy ra lỗi khi xác thực máy chủ.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Xem Mật Khẩu';
        }
    }
</script>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
