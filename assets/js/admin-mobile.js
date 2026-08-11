/**
 * Admin Mobile UX Helper
 * - Tự động tạo & gán sự kiện Nút ☰ Menu trên Mobile
 * - Tự động bọc Bảng dữ liệu trong khung vuốt ngang mượt mà + Giữ nguyên vị trí vuốt khi sắp xếp/lọc
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

    // 2. Xử lý Bọc Bảng 100% Khung hình cho Mobile (CHỈ bọc khi bảng chưa nằm trong khung cuộn)
    if (window.innerWidth <= 768) {
        const tables = document.querySelectorAll('table');
        tables.forEach(function(table) {
            if (!table.closest('.table-responsive, .excel-table-container')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }
}

function getVisibleAdminSearchInput() {
    const selectors = [
        '#tab-content-config.active #table-search-input',
        '#tab-content-floorplan.active #dashboard-search-input',
        '#checkin-search-input',
        '#search-input',
        '#dashboard-search-input',
        '#table-search-input',
        '#event-search-input'
    ];

    for (const selector of selectors) {
        const input = document.querySelector(selector);
        if (!input) continue;
        const rect = input.getBoundingClientRect();
        const style = window.getComputedStyle(input);
        if (rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none') {
            return input;
        }
    }

    return null;
}

function focusAdminSearchInput() {
    const input = getVisibleAdminSearchInput();
    if (!input) return false;

    input.scrollIntoView({ behavior: 'smooth', block: window.innerWidth <= 768 ? 'start' : 'center' });
    setTimeout(function() {
        input.focus({ preventScroll: true });
        if (typeof input.select === 'function') input.select();
    }, 80);
    return true;
}

function prepareMobileDataCards() {
    const tables = document.querySelectorAll('.excel-table-container table.excel-table');

    tables.forEach(function(table) {
        table.classList.add('mobile-card-table');
        const container = table.closest('.excel-table-container, .table-responsive');
        if (container) {
            container.classList.add('mobile-card-container');
        }

        const headers = Array.from(table.querySelectorAll('thead th')).map(function(th) {
            return (th.innerText || th.textContent || '').replace(/[↕▲▼↑↓]/g, '').replace(/\s+/g, ' ').trim();
        });

        table.querySelectorAll('tbody tr').forEach(function(row) {
            Array.from(row.children).forEach(function(cell, idx) {
                let label = headers[idx] || '';
                if (cell.dataset.label) {
                    cell.dataset.label = cell.dataset.label.replace(/[↕▲▼↑↓]/g, '').trim();
                } else if (label) {
                    cell.dataset.label = label;
                }

                const normalized = label.toLowerCase();
                if (normalized.includes('họ và tên') || normalized.includes('tên bàn')) {
                    cell.classList.add('col-card-title');
                }
                if (normalized.includes('mã kh')) {
                    cell.classList.add('col-customer_code');
                }
                if (normalized.includes('đơn vị') || normalized.includes('đại lý')) {
                    cell.classList.add('col-organization');
                }
                if (normalized.includes('thao tác') || cell.querySelector('.action-btns-wrapper, .btn-action-primary, .btn-action-checkin, .btn-action-edit')) {
                    cell.classList.add('col-actions');
                }
            });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initAdminMobileUX();
        prepareMobileDataCards();
    });
} else {
    initAdminMobileUX();
    prepareMobileDataCards();
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

/**
 * Lưu vị trí vuốt ngang (.excel-table-container / .table-responsive) và vị trí cuộn dọc trước khi sắp xếp / lọc
 */
window.saveAdminTableScrollPosition = function() {
    try {
        sessionStorage.setItem('pmt_scroll_y', window.scrollY || window.pageYOffset || 0);

        let scrollX = 0;
        const containers = document.querySelectorAll('.excel-table-container, .table-responsive, .excel-zoom-wrapper');
        containers.forEach(function(c) {
            if (c.scrollLeft > scrollX) {
                scrollX = c.scrollLeft;
            }
        });

        if (scrollX === 0) {
            const firstContainer = document.querySelector('.excel-table-container, .table-responsive');
            if (firstContainer) {
                scrollX = firstContainer.scrollLeft || 0;
            }
        }

        sessionStorage.setItem('pmt_scroll_x', scrollX);
    } catch(e) {}
};

/**
 * Tự động khôi phục chính xác vị trí đang vuốt ngang (kể cả cột 4, 5...) & cuộn dọc sau khi trang nạp xong
 */
window.restoreAdminTableScrollPosition = function() {
    try {
        const savedX = sessionStorage.getItem('pmt_scroll_x');
        const savedY = sessionStorage.getItem('pmt_scroll_y');

        if (savedX !== null && savedX !== '') {
            const targetX = parseInt(savedX, 10);
            const applyX = function() {
                const containers = document.querySelectorAll('.excel-table-container, .table-responsive, .excel-zoom-wrapper');
                containers.forEach(function(c) {
                    c.scrollLeft = targetX;
                });
            };

            applyX();
            if (window.requestAnimationFrame) requestAnimationFrame(applyX);
            setTimeout(applyX, 50);
            setTimeout(applyX, 150);
            setTimeout(applyX, 350);
        }

        if (savedY !== null && savedY !== '') {
            const targetY = parseInt(savedY, 10);
            const applyY = function() {
                window.scrollTo(0, targetY);
            };

            applyY();
            setTimeout(applyY, 50);
            setTimeout(applyY, 150);
        }
    } catch(e) {}
};

/**
 * Hàm toàn cục sắp xếp dữ liệu bảng, tự động bảo lưu vị trí đang vuốt cột 4, 5... trên Mobile
 */
window.sortTableByKey = function(sortVal) {
    window.saveAdminTableScrollPosition();
    if (window.showGlobalSpinner) {
        window.showGlobalSpinner('Đang sắp xếp...');
    }
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortVal);
    window.location.href = url.toString();
};

// Gán tự động trước khi trang reload/unload
window.addEventListener('beforeunload', function() {
    window.saveAdminTableScrollPosition();
});

document.addEventListener('DOMContentLoaded', function() {
    window.restoreAdminTableScrollPosition();
    prepareMobileDataCards();
});

window.addEventListener('load', function() {
    window.restoreAdminTableScrollPosition();
    prepareMobileDataCards();
});

window.addEventListener('dbRealtimeChange', function() {
    setTimeout(prepareMobileDataCards, 80);
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key && e.key.toLowerCase() === 'k') {
        if (focusAdminSearchInput()) {
            e.preventDefault();
            e.stopPropagation();
        }
    } else if (e.key === 'Escape') {
        const activeEl = document.activeElement;
        if (activeEl && activeEl.matches('#dashboard-search-input, #search-input, #table-search-input')) {
            activeEl.blur();
        }
    }
}, true);

window.focusAdminSearchInput = focusAdminSearchInput;

/**
 * Cho phép lăn con trỏ chuột (Mouse Wheel) cuộn trang dọc mượt mà 100% khi rê chuột trên bảng dữ liệu trong giả lập Mobile (Chrome DevTools Mobile Emulator)
 */
document.addEventListener('wheel', function (e) {
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX) && e.deltaY !== 0) {
        const container = e.target.closest('.excel-table-container, .table-responsive, .excel-zoom-wrapper');
        if (container) {
            window.scrollBy({
                top: e.deltaY,
                behavior: 'instant'
            });
        }
    }
}, { passive: true });

const mobileCardObserver = new MutationObserver(function() {
    if (window.innerWidth <= 768) {
        prepareMobileDataCards();
    }
});

if (document.body) {
    mobileCardObserver.observe(document.body, { childList: true, subtree: true });
}

window.prepareMobileDataCards = prepareMobileDataCards;
