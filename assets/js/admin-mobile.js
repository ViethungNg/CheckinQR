/**
 * Admin Mobile UX Helper
 * - Tự động tạo & gán sự kiện Nút ☰ Menu trên Mobile
 * - Tự động bọc Bảng dữ liệu trong khung vuốt ngang mượt mà + Hiện gợi ý "Vuốt sang trái 👈"
 */
function initAdminMobileUX() {
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

        if (toggleBtn && !toggleBtn._mobileBound) {
            toggleBtn._mobileBound = true;
            
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

    // 2. Xử lý Bọc Bảng 100% Khung hình cho Mobile
    if (window.innerWidth <= 768) {
        const tables = document.querySelectorAll('table');
        tables.forEach(function(table) {
            if (!table.parentElement.classList.contains('table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminMobileUX);
} else {
    initAdminMobileUX();
}

/**
 * Tự động cuộn xuống khu vực bảng dữ liệu trên tất cả thiết bị khi chọn bộ lọc
 */
window.scrollToTableSectionOnMobile = function() {
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
};
