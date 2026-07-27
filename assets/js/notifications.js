/**
 * Global Real-time QR Check-in Notification System (Top Corner Header Bell)
 */

(function () {
    let clientLastCheckinId = 0;
    let audioCtx = null;

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

    function playNotifChime() {
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            const osc1 = audioCtx.createOscillator();
            const gain1 = audioCtx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12);
            gain1.gain.setValueAtTime(0.3, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);

            osc1.connect(gain1);
            gain1.connect(audioCtx.destination);
            osc1.start(now);
            osc1.stop(now + 0.5);
        } catch (e) {}
    }

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
        setTimeout(() => toast.classList.add('show'), 50);

        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }

    // Tự động gắn Nút chuông thông báo vào góc phải thanh Tiêu đề (.header)
    function injectHeaderNotifBell() {
        if (document.getElementById('headerNotifBox')) return;

        const notifBox = document.createElement('div');
        notifBox.id = 'headerNotifBox';
        notifBox.className = 'header-notif-box';

        notifBox.innerHTML = `
            <button type="button" class="notif-bell-btn-icon" id="notifBellBtn" onclick="toggleNotifDropdown(event)" title="Thông báo quét QR">
                🔔
                <span class="notif-badge-count" id="notifBadgeCount" style="display:none;">0</span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dropdown-header">
                    <span>🔔 Thông báo quét QR gần đây</span>
                    <button type="button" class="notif-mark-read-btn" onclick="markAllNotifsRead(event)">Đã đọc tất cả</button>
                </div>
                <div class="notif-dropdown-list" id="notifDropdownList">
                    <div class="notif-empty-state">Đang tải thông báo...</div>
                </div>
            </div>
        `;

        const header = document.querySelector('.header');
        if (header) {
            header.appendChild(notifBox);
        } else {
            notifBox.style.position = 'fixed';
            notifBox.style.top = '15px';
            notifBox.style.right = '20px';
            notifBox.style.zIndex = '9999';
            document.body.appendChild(notifBox);
        }
    }

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
            listContainer.innerHTML = `<div class="notif-empty-state">Chưa có lượt quét QR nào</div>`;
            return;
        }

        let html = '';
        checkins.forEach(item => {
            const isWalkIn = item.status === 'walk_in';
            html += `
                <div class="notif-item ${item.is_new ? 'unread' : ''}" onclick="window.location.href='checkins.php'">
                    <div class="notif-item-title">
                        <span>${isWalkIn ? '🔸 Khách phát sinh' : '✅ Khách hợp lệ'}</span>
                        <span class="notif-item-time">${item.time.split(' ')[0]}</span>
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

    async function checkNewNotifications() {
        try {
            const res = await fetch(`../api/notifications.php?action=check`);
            if (!res.ok) return;
            const data = await res.json();

            if (data.status === 'success') {
                const newMaxId = data.max_id;

                if (clientLastCheckinId === 0) {
                    clientLastCheckinId = newMaxId;
                    renderNotifDropdown(data.checkins, data.unread_count);
                    return;
                }

                if (newMaxId > clientLastCheckinId) {
                    const newItems = data.checkins.filter(item => item.id > clientLastCheckinId);

                    if (newItems.length > 0) {
                        playNotifChime();
                        newItems.forEach(item => showToastNotification(item));
                    }
                    clientLastCheckinId = newMaxId;
                }

                renderNotifDropdown(data.checkins, data.unread_count);
            }
        } catch (err) {
            console.error('Notification check error:', err);
        }
    }

    window.markAllNotifsRead = async function (e) {
        if (e) e.stopPropagation();
        try {
            await fetch(`../api/notifications.php?action=mark_read&last_id=${clientLastCheckinId}`, { method: 'POST' });
            const badge = document.getElementById('notifBadgeCount');
            if (badge) badge.style.display = 'none';
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        } catch (err) {
            console.error(err);
        }
    };

    window.toggleNotifDropdown = function (e) {
        if (e) e.stopPropagation();
        initAudio();
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            const isVisible = getComputedStyle(dropdown).display !== 'none';
            dropdown.style.display = isVisible ? 'none' : 'flex';
        }
    };

    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('notifDropdown');
        const bellBtn = document.getElementById('notifBellBtn');
        if (dropdown && getComputedStyle(dropdown).display !== 'none') {
            if (!dropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
                dropdown.style.display = 'none';
            }
        }
    });

    document.addEventListener('click', initAudio, { once: true });

    document.addEventListener('DOMContentLoaded', () => {
        injectHeaderNotifBell();
        checkNewNotifications();
        setInterval(checkNewNotifications, 3000);
    });
})();
