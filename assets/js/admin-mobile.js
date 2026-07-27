/**
 * Admin Mobile Navigation & Table Responsive Helper
 */
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    const sidebarH2 = sidebar.querySelector('h2');
    if (!sidebarH2) return;

    // Tạo nút Menu ☰ bấm mở/đóng
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'mobile-menu-btn';
    toggleBtn.type = 'button';
    toggleBtn.innerHTML = '☰ Menu';

    sidebarH2.appendChild(toggleBtn);

    // Sự kiện bấm mở/đóng Menu
    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('mobile-open');
        toggleBtn.innerHTML = sidebar.classList.contains('mobile-open') ? '✕ Đóng' : '☰ Menu';
    });

    // Đóng menu khi click ra ngoài
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            toggleBtn.innerHTML = '☰ Menu';
        }
    });
});
