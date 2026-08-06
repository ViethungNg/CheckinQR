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
        <a href="<?php echo url('admin/index.php'); ?>" class="sidebar-brand-link" title="Về trang Dashboard tổng quan" style="display: inline-block; text-decoration: none; background: transparent !important; border: none !important; outline: none !important; -webkit-tap-highlight-color: transparent !important;">
            <img src="<?php echo url('img/logo pmt.png'); ?>" alt="Logo PMT" class="sidebar-logo-img" style="border: none !important; outline: none !important; -webkit-tap-highlight-color: transparent !important;">
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
            <a href="settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                Cấu hình hệ thống
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
                <?php 
                    $roleLabelText = getRoleLabel($adminRole); 
                    $showName = ($adminName !== $roleLabelText && $adminName !== 'Admin') ? $adminName : '';
                ?>
                <?php if (!empty($showName)): ?>
                    <div class="user-profile-name" title="<?php echo esc($showName); ?>">
                        <?php echo esc($showName); ?>
                    </div>
                <?php endif; ?>
                <div class="user-profile-role" style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <span style="font-weight:600; color:#333;"><?php echo esc($roleLabelText); ?></span>
                    <a href="javascript:void(0)" onclick="openChangePasswordModal()" style="color:#d32f2f; font-weight:700; text-decoration:underline; font-size:0.76rem; cursor:pointer;" title="Bấm để đổi mật khẩu tài khoản">
                        (Đổi mật khẩu)
                    </a>
                </div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout-btn" onclick="return confirmModal(event, 'Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');">
            Đăng xuất
        </a>
    </div>
</div>

<!-- Modal Đổi Mật Khẩu Chung Cho Tất Cả Role -->
<div id="changePasswordModal" class="modal change-pass-modal" style="display:none; z-index: 11000;">
    <div class="modal-content change-pass-content">
        <div class="modal-header change-pass-header">
            <h3 class="change-pass-title">🔑 Đổi Mật Khẩu Tài Khoản</h3>
            <span class="close-modal-btn" onclick="closeChangePasswordModal()">&times;</span>
        </div>

        <div id="changePassAlert" style="display:none;" class="change-pass-alert"></div>

        <form id="changePasswordForm" onsubmit="submitChangePassword(event)">
            <?php echo csrfField(); ?>
            <div class="form-group change-pass-group">
                <label class="change-pass-label">Mật khẩu hiện tại *</label>
                <input type="password" name="current_password" id="pass_current" class="form-control change-pass-input" placeholder="Nhập mật khẩu đang dùng..." required>
            </div>

            <div class="form-group change-pass-group">
                <label class="change-pass-label">Mật khẩu mới *</label>
                <input type="password" name="new_password" id="pass_new" class="form-control change-pass-input" placeholder="Nhập mật khẩu mới..." required>
            </div>

            <div class="form-group change-pass-group">
                <label class="change-pass-label">Xác nhận mật khẩu mới *</label>
                <input type="password" name="confirm_password" id="pass_confirm" class="form-control change-pass-input" placeholder="Nhập lại mật khẩu mới..." required>
            </div>

            <div class="change-pass-actions">
                <button type="button" class="btn btn-cancel-pass" onclick="closeChangePasswordModal()">Hủy Bỏ</button>
                <button type="submit" id="btnSubmitChangePass" class="btn btn-save-pass">Lưu Mật Khẩu</button>
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
<?php require_once __DIR__ . '/bottom_nav.php'; ?>

