<?php
/**
 * Sidebar Partial Component with User Profile & Fixed Styling
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'staff';
$firstChar = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));
?>
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <a href="<?php echo url('admin/index.php'); ?>" class="sidebar-brand-link" title="Về trang Dashboard tổng quan" style="display: inline-block; text-decoration: none; background: transparent !important; border: none !important;">
            <img src="<?php echo url('img/logo pmt.png'); ?>" alt="Logo PMT" class="sidebar-logo-img">
        </a>
        <div class="mobile-brand-controls">
            <?php $roleLabelText = getRoleLabel($adminRole); ?>
            <span class="mobile-user-greeting" title="<?php echo esc($roleLabelText); ?>"><?php echo esc($roleLabelText); ?></span>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn"><span>☰</span> Menu</button>
        </div>
    </div>
    
    <ul class="sidebar-nav">
        <li>
            <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                Dashboard
            </a>
        </li>
        <?php if(isAdmin()): ?>
        <li>
            <a href="events.php" class="<?php echo $currentPage === 'events.php' ? 'active' : ''; ?>">
                Quản lý sự kiện
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="guests.php" class="<?php echo $currentPage === 'guests.php' ? 'active' : ''; ?>">
                Danh sách khách hàng
            </a>
        </li>
        <li>
            <a href="checkins.php" class="<?php echo $currentPage === 'checkins.php' ? 'active' : ''; ?>">
                Khách đã checkin
            </a>
        </li>
        <li>
            <a href="tables.php" class="<?php echo $currentPage === 'tables.php' ? 'active' : ''; ?>">
                Quản lý bàn
            </a>
        </li>
        <?php if(isAdmin()): ?>
        <li>
            <a href="users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                Quản lý tài khoản
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="javascript:void(0)" onclick="openChangePasswordModal()">
                Đổi mật khẩu
            </a>
        </li>
    </ul>

    <!-- User Profile & Logout Box inside Left Sidebar -->
    <div class="sidebar-user-box">
        <div class="user-profile-info">
            <div class="user-avatar-circle">
                <?php echo esc($firstChar); ?>
            </div>
            <div class="user-profile-meta">
                <div class="user-profile-name" title="<?php echo esc($adminName); ?>">
                    <?php echo esc($adminName); ?>
                </div>
                <div class="user-profile-role">
                    <?php echo getRoleLabel($adminRole); ?>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 6px; width: 100%; margin-top: 8px;">
            <button type="button" class="sidebar-logout-btn" style="flex: 1; background: #555; text-align: center; cursor: pointer; border: none;" onclick="openChangePasswordModal()">
                Đổi mật khẩu
            </button>
            <a href="logout.php" class="sidebar-logout-btn" style="flex: 1; text-align: center;" onclick="return confirmModal(event, 'Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');">
                Đăng xuất
            </a>
        </div>
    </div>
</div>

<!-- Modal Đổi Mật Khẩu Chung Cho Tất Cả Role -->
<div id="changePasswordModal" class="modal" style="display:none; z-index: 11000;">
    <div class="modal-content" style="max-width: 420px; border-radius: 12px; padding: 24px;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:12px; margin-bottom:16px;">
            <h3 style="font-size:1.15rem; font-weight:700; color:#222; margin:0;">Đổi Mật Khẩu Tài Khoản</h3>
            <span class="close" onclick="closeChangePasswordModal()" style="font-size:24px; cursor:pointer; color:#888;">&times;</span>
        </div>

        <div id="changePassAlert" style="display:none; padding:10px 14px; border-radius:8px; font-size:0.9rem; margin-bottom:14px;"></div>

        <form id="changePasswordForm" onsubmit="submitChangePassword(event)">
            <?php echo csrfField(); ?>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color: #333;">Mật khẩu hiện tại *</label>
                <input type="password" name="current_password" id="pass_current" class="form-control" placeholder="Nhập mật khẩu đang dùng..." required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color: #333;">Mật khẩu mới *</label>
                <input type="password" name="new_password" id="pass_new" class="form-control" placeholder="Nhập mật khẩu mới..." required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div class="form-group" style="margin-bottom:18px;">
                <label style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color: #333;">Xác nhận mật khẩu mới *</label>
                <input type="password" name="confirm_password" id="pass_confirm" class="form-control" placeholder="Nhập lại mật khẩu mới..." required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeChangePasswordModal()" style="background:#e2e8f0; color:#475569; padding:9px 16px; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Hủy Bỏ</button>
                <button type="submit" id="btnSubmitChangePass" class="btn btn-primary" style="background:#d32f2f; color:#fff; padding:9px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Lưu Mật Khẩu</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openChangePasswordModal() {
        const modal = document.getElementById('changePasswordModal');
        if (!modal) return;
        document.getElementById('changePasswordForm').reset();
        const alertBox = document.getElementById('changePassAlert');
        if (alertBox) alertBox.style.display = 'none';
        modal.style.display = 'block';
    }

    function closeChangePasswordModal() {
        const modal = document.getElementById('changePasswordModal');
        if (modal) modal.style.display = 'none';
    }

    async function submitChangePassword(e) {
        e.preventDefault();
        const form = e.target;
        const alertBox = document.getElementById('changePassAlert');
        const submitBtn = document.getElementById('btnSubmitChangePass');

        const currentPass = document.getElementById('pass_current').value;
        const newPass = document.getElementById('pass_new').value;
        const confirmPass = document.getElementById('pass_confirm').value;

        if (newPass !== confirmPass) {
            alertBox.style.background = '#ffebee';
            alertBox.style.color = '#c62828';
            alertBox.style.border = '1px solid #ffcdd2';
            alertBox.innerHTML = 'Mật khẩu mới và xác nhận mật khẩu không trùng khớp!';
            alertBox.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang xử lý...';

        try {
            const formData = new FormData(form);
            const response = await fetch('../api/change_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            alertBox.style.display = 'block';
            if (result.status === 'success') {
                alertBox.style.background = '#e8f5e9';
                alertBox.style.color = '#2e7d32';
                alertBox.style.border = '1px solid #c8e6c9';
                alertBox.innerHTML = result.message;
                form.reset();
                setTimeout(closeChangePasswordModal, 1500);
            } else {
                alertBox.style.background = '#ffebee';
                alertBox.style.color = '#c62828';
                alertBox.style.border = '1px solid #ffcdd2';
                alertBox.innerHTML = result.message || 'Đổi mật khẩu thất bại!';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#ffebee';
            alertBox.style.color = '#c62828';
            alertBox.style.border = '1px solid #ffcdd2';
            alertBox.innerHTML = 'Đã xảy ra lỗi kết nối máy chủ.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Lưu Mật Khẩu';
        }
    }
</script>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>


