/**
 * Admin Mobile UX Helper
 * - Tự động tạo Nút ☰ Menu đẳng cấp trên Mobile
 * - Tự động bọc Bảng dữ liệu trong khung vuốt ngang mượt mà + Hiện gợi ý "Vuốt sang trái 👈"
 */
document.addEventListener('DOMContentLoaded', function() {
    // 1. Xử lý Sidebar Mobile Menu Toggle
    const sidebar = document.querySelector('.sidebar');
    let toggleBtn = document.getElementById('mobileMenuBtn');
    
    if (sidebar) {
        if (!toggleBtn) {
            const sidebarBrand = sidebar.querySelector('.sidebar-brand') || sidebar.querySelector('h2');
            if (sidebarBrand) {
                toggleBtn = document.createElement('button');
                toggleBtn.className = 'mobile-menu-btn';
                toggleBtn.id = 'mobileMenuBtn';
                toggleBtn.type = 'button';
                toggleBtn.innerHTML = '<span>☰</span> Menu';
                sidebarBrand.appendChild(toggleBtn);
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = sidebar.classList.toggle('mobile-open');
                toggleBtn.innerHTML = isOpen ? '<span>✕</span> Đóng' : '<span>☰</span> Menu';
            });

            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    toggleBtn.innerHTML = '<span>☰</span> Menu';
                }
            });
        }
    }

    // 2. Xử lý Bọc Bảng và Gợi Ý Vuốt Ngang cho Mobile
    if (window.innerWidth <= 768) {
        const tables = document.querySelectorAll('table');
        tables.forEach(function(table) {
            // Nếu chưa được bọc trong table-responsive
            if (!table.parentElement.classList.contains('table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                
                // Thêm gợi ý vuốt sang trái
                const hint = document.createElement('div');
                hint.className = 'mobile-swipe-hint';
                hint.style.cssText = 'font-size: 0.78rem; color: #666; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; font-weight: 500;';
                hint.innerHTML = '👈 <span>Vuốt sang trái để xem toàn bộ cột & thao tác</span>';

                table.parentNode.insertBefore(hint, table);
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
});

/**
 * Tự động cuộn xuống khu vực bảng dữ liệu trên Mobile & Tablet khi chọn bộ lọc
 */
window.scrollToTableSectionOnMobile = function() {
    if (window.innerWidth <= 1024) {
        setTimeout(function() {
            const target = document.querySelector('.dashboard-table-card') || 
                           document.querySelector('.content-box') || 
                           document.querySelector('.table-responsive') || 
                           document.querySelector('table');
            if (target) {
                const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - 70;
                window.scrollTo({ top: Math.max(0, targetPosition), behavior: 'smooth' });
            }
        }, 80);
    }
};
