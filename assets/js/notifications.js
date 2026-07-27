/**
 * Global Real-time QR Check-in Notification System
 */

(function () {
    let clientLastCheckinId = 0;
    let audioCtx = null;

    // Khởi tạo AudioContext khi người dùng tương tác với trang
    function initAudio() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    // Phát âm thanh thông báo "Ting!" nhẹ nhàng khi có lượt check-in mới
    function playNotifChime() {
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            
            // Oscillator 1 (Nốt D5: 587.33Hz)
            const osc1 = audioCtx.createOscillator();
            const gain1 = audioCtx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // Nốt A5: 880Hz
            gain1.gain.setValueAtTime(0.3, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);

            osc1.connect(gain1);
            gain1.connect(audioCtx.destination);
            osc1.start(now);
            osc1.stop(now + 0.5);
        } catch (e) {
            console.warn('Audio playback not allowed or failed:', e);
        }
    }

    // Hiển thị Toast thông báo nổi trên góc màn hình
    function showToastNotification(item) {
        let container = document.getElementById('globalToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'globalToastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const isWalkIn = item.status === 'walk_in';
        const toast = document.createElement('div');
        toast.className = `toast-item ${isWalkIn ? 'walk_in' : ''}`;

        toast.innerHTML = `
            <div class="toast-icon">${isWalkIn ? '🔸' : '✅'}</div>
            <div class="toast-content">
                <div class="toast-title">
                    <span>${isWalkIn ? 'Khách phát sinh vừa quét QR!' : 'Khách dự kiến đã Check-in!'}</span>
                    <button class="toast-close" onclick="this.closest('.toast-item').remove()">×</button>
                </div>
                <div class="toast-body">
                    <strong>👤 ${item.full_name}</strong> (${item.phone})<br>
                    🪑 Bàn: <strong>${item.table_name}</strong> | ⏰ ${item.time.split(' ')[0]}
                </div>
            </div>
        `;

        container.appendChild(toast);

        // Animation slide-in
        setTimeout(() => toast.classList.add('show'), 50);

        // Tự động đóng sau 6 giây
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }

    // Cập nhật danh sách trong Dropdown Menu
    function renderNotifDropdown(checkins, unreadCount) {
        const badge = document.getElementById('notifBadgeCount');
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        const listContainer = document.getElementById('notifDropdownList');
        if (!listContainer) return;

        if (!checkins || checkins.length === 0) {
            listContainer.innerHTML = `<div class="notif-empty-state">Chưa có thông báo quét QR mới</div>`;
            return;
        }

        let html = '';
        checkins.forEach(item => {
            const isWalkIn = item.status === 'walk_in';
            html += `
                <div class="notif-item ${item.is_new ? 'unread' : ''}" onclick="window.location.href='checkins.php'">
                    <div class="notif-item-title">
                        <span>${isWalkIn ? '🔸 Khách phát sinh' : '✅ Khách hợp lệ'}</span>
                        <span style="font-size:0.75rem; font-weight:normal; color:#888;">${item.time.split(' ')[0]}</span>
                    </div>
                    <div class="notif-item-desc">
                        <strong>${item.full_name}</strong> - ${item.phone}<br>
                        Bàn: <strong>${item.table_name}</strong>
                    </div>
                </div>
            `;
        });
        listContainer.innerHTML = html;
    }

    // Kiểm tra thông báo realtime từ API
    async function checkNewNotifications() {
        try {
            const res = await fetch(`../api/notifications.php?action=check&last_id=${clientLastCheckinId}`);
            if (!res.ok) return;
            const data = await res.json();

            if (data.status === 'success') {
                const newMaxId = data.max_id;
                
                // Nếu đây là lần kiểm tra đầu tiên khi load trang
                if (clientLastCheckinId === 0) {
                    clientLastCheckinId = newMaxId;
                    renderNotifDropdown(data.checkins, data.unread_count);
                    return;
                }

                // Nếu phát hiện có lượt checkin mới (max_id lớn hơn trước đó)
                if (newMaxId > clientLastCheckinId) {
                    // Lọc ra các bản ghi mới thực sự
                    const newItems = data.checkins.filter(item => item.id > clientLastCheckinId);

                    if (newItems.length > 0) {
                        playNotifChime();
                        newItems.forEach(item => showToastNotification(item));
                    }

                    clientLastCheckinId = newMaxId;
                    renderNotifDropdown(data.checkins, data.unread_count);
                } else {
                    renderNotifDropdown(data.checkins, data.unread_count);
                }
            }
        } catch (err) {
            console.error('Notification check error:', err);
        }
    }

    // Đánh dấu tất cả thông báo là đã đọc
    window.markAllNotifsRead = async function () {
        try {
            await fetch(`../api/notifications.php?action=mark_read&last_id=${clientLastCheckinId}`, { method: 'POST' });
            const badge = document.getElementById('notifBadgeCount');
            if (badge) badge.style.display = 'none';

            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        } catch (e) {
            console.error(e);
        }
    };

    // Bật/tắt menu dropdown thông báo
    window.toggleNotifDropdown = function (e) {
        if (e) e.stopPropagation();
        initAudio(); // Đảm bảo audio được kích hoạt
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            const isVisible = dropdown.style.display === 'flex';
            dropdown.style.display = isVisible ? 'none' : 'flex';
        }
    };

    // Đóng dropdown khi click ra ngoài
    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('notifDropdown');
        const bellBtn = document.getElementById('notifBellBtn');
        if (dropdown && dropdown.style.display === 'flex') {
            if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        }
    });

    // Cho phép kích hoạt âm thanh bằng bất kỳ thao tác click nào trên trang
    document.addEventListener('click', initAudio, { once: true });

    // Khởi chạy polling kiểm tra mỗi 3 giây
    document.addEventListener('DOMContentLoaded', () => {
        checkNewNotifications();
        setInterval(checkNewNotifications, 3000);
    });
})();
