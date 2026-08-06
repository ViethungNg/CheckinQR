<?php
/**
 * Bottom Navigation Bar Partial cho Mobile Web App (PWA)
 */
$currentNavPage = basename($_SERVER['PHP_SELF']);
$isAdminUser = function_exists('isAdmin') ? isAdmin() : false;
?>
<nav class="pmt-bottom-nav" aria-label="Mobile Navigation Bar">
    <!-- Tab 1: Trang chủ -->
    <a href="<?php echo url('admin/index.php'); ?>" class="nav-item <?php echo ($currentNavPage === 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false) ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
        </div>
        <span class="nav-label">Trang chủ</span>
    </a>

    <!-- Tab 2: Danh sách khách -->
    <a href="<?php echo url('admin/guests.php'); ?>" class="nav-item <?php echo ($currentNavPage === 'guests.php') ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <span class="nav-label">Khách mời</span>
    </a>

    <!-- Tab 3: Check-in / Nút Nổi -->
    <a href="<?php echo url('admin/checkins.php'); ?>" class="nav-item nav-item-featured <?php echo ($currentNavPage === 'checkins.php') ? 'active' : ''; ?>">
        <div class="nav-icon-bg">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <span class="nav-label">Check-in</span>
    </a>

    <!-- Tab 4: Quản lý Sự kiện / Bàn -->
    <a href="<?php echo url('admin/tables.php'); ?>" class="nav-item <?php echo ($currentNavPage === 'tables.php') ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="9" x2="21" y2="9"></line>
                <line x1="9" y1="21" x2="9" y2="9"></line>
            </svg>
        </div>
        <span class="nav-label">Sơ đồ bàn</span>
    </a>

    <!-- Tab 5: Cài đặt hệ thống -->
    <a href="<?php echo url('admin/settings.php'); ?>" class="nav-item <?php echo ($currentNavPage === 'settings.php') ? 'active' : ''; ?>">
        <div class="nav-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
        </div>
        <span class="nav-label">Cài đặt</span>
    </a>
</nav>
