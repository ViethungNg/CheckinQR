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
        <h2>CheckinQR</h2>
        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn"><span>☰</span> Menu</button>
    </div>
    
    <ul class="sidebar-nav">
        <li>
            <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
        </li>
        <?php if(isAdmin()): ?>
        <li>
            <a href="events.php" class="<?php echo $currentPage === 'events.php' ? 'active' : ''; ?>">
                🎉 Quản lý sự kiện
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="guests.php" class="<?php echo $currentPage === 'guests.php' ? 'active' : ''; ?>">
                📋 Danh sách khách dự kiến
            </a>
        </li>
        <li>
            <a href="checkins.php" class="<?php echo $currentPage === 'checkins.php' ? 'active' : ''; ?>">
                📥 Khách hàng đã checkin
            </a>
        </li>
        <li>
            <a href="tables.php" class="<?php echo $currentPage === 'tables.php' ? 'active' : ''; ?>">
                🪑 Quản lý bàn
            </a>
        </li>
        <?php if(isAdmin()): ?>
        <li>
            <a href="users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                👤 Quản lý tài khoản
            </a>
        </li>
        <?php endif; ?>
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
        <a href="logout.php" class="sidebar-logout-btn" onclick="return confirm('Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?');">
            🚪 Đăng xuất
        </a>
    </div>
</div>

<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>


