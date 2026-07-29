/**
 * Global Real-time QR Check-in Notification System with Dual Audio Engine
 */

(function () {
    let clientLastCheckinId = 0;
    let audioCtx = null;
    let cachedWavUri = null;

    // 1. Tạo âm thanh WAV "Ding-Dong" chuẩn trong bộ nhớ JS
    function getChimeWavUri() {
        if (cachedWavUri) return cachedWavUri;
        try {
            const sampleRate = 22050;
            const duration = 0.45;
            const numSamples = Math.floor(sampleRate * duration);
            const buffer = new Uint8Array(44 + numSamples);

            function writeString(offset, str) {
                for (let i = 0; i < str.length; i++) buffer[offset + i] = str.charCodeAt(i);
            }
            function writeUint32(offset, val) {
                buffer[offset] = val & 0xff;
                buffer[offset + 1] = (val >> 8) & 0xff;
                buffer[offset + 2] = (val >> 16) & 0xff;
                buffer[offset + 3] = (val >> 24) & 0xff;
            }
            function writeUint16(offset, val) {
                buffer[offset] = val & 0xff;
                buffer[offset + 1] = (val >> 8) & 0xff;
            }

            writeString(0, 'RIFF');
            writeUint32(4, 36 + numSamples);
            writeString(8, 'WAVE');
            writeString(12, 'fmt ');
            writeUint32(16, 16);
            writeUint16(20, 1);
            writeUint16(22, 1);
            writeUint32(24, sampleRate);
            writeUint32(28, sampleRate);
            writeUint16(32, 1);
            writeUint16(34, 8);
            writeString(36, 'data');
            writeUint32(40, numSamples);

            for (let i = 0; i < numSamples; i++) {
                const t = i / sampleRate;
                let freq = 783.99; // Nốt G5
                if (t > 0.12) freq = 1046.50; // Nốt C6

                const envelope = Math.max(0, 1 - (t / duration) * 1.5);
                const sampleVal = Math.sin(2 * Math.PI * freq * t) * envelope * 0.5;
                buffer[44 + i] = Math.floor((sampleVal + 1) * 127.5);
            }

            let binary = '';
            for (let i = 0; i < buffer.length; i++) binary += String.fromCharCode(buffer[i]);
            cachedWavUri = 'data:audio/wav;base64,' + btoa(binary);
            return cachedWavUri;
        } catch (e) {
            return null;
        }
    }

    // 2. Kích hoạt và mở khóa AudioContext trình duyệt
    function unlockAudio() {
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

    // 3. Hàm phát âm thanh kép (HTML5 Audio + Web Audio Synth)
    window.playNotifChime = function () {
        unlockAudio();

        // Engine 1: HTML5 Audio với WAV Data URI
        try {
            const wavUri = getChimeWavUri();
            if (wavUri) {
                const audio = new Audio(wavUri);
                audio.volume = 0.9;
                audio.play().catch(() => {});
            }
        } catch (e) {}

        // Engine 2: Web Audio API Oscillator
        try {
            if (audioCtx) {
                const now = audioCtx.currentTime;
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(783.99, now); // G5
                osc.frequency.exponentialRampToValueAtTime(1046.50, now + 0.12); // C6
                gain.gain.setValueAtTime(0.4, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now);
                osc.stop(now + 0.45);
            }
        } catch (e) {}
    };

    const shownToastIds = new Set();
    let isCheckingNotifications = false;

    function showToastNotification(item) {
        if (!item || !item.id) return;
        if (shownToastIds.has(item.id)) return; // Khóa chống trùng Toast theo ID
        shownToastIds.add(item.id);

        let container = document.getElementById('globalToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'globalToastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        // Kiểm tra xem đã có phần tử DOM Toast của ID này chưa
        if (container.querySelector(`[data-toast-id="${item.id}"]`)) return;

        const isWalkIn = item.status === 'walk_in';
        const toast = document.createElement('div');
        toast.className = `toast-item ${isWalkIn ? 'walk_in' : 'matched'}`;
        toast.dataset.toastId = item.id;

        const timeDisplay = item.time ? item.time.split(' ')[0] : '';

        toast.innerHTML = `
            <div class="toast-icon">${isWalkIn ? '🔸' : '✅'}</div>
            <div class="toast-content">
                <div class="toast-title">
                    <span>${isWalkIn ? 'Khách phát sinh vừa quét QR!' : 'Khách hợp lệ đã Check-in!'}</span>
                    <button class="toast-close" onclick="this.closest('.toast-item').remove()">×</button>
                </div>
                <div class="toast-body">
                    <strong>👤 ${item.full_name}</strong> (${item.phone})<br>
                    🪑 Bàn: <strong>${item.table_name}</strong> ${timeDisplay ? '| ⏰ ' + timeDisplay : ''}
                </div>
            </div>
        `;

        toast.style.cursor = 'pointer';
        toast.onclick = function(e) {
            if (e.target.classList.contains('toast-close')) return;
            window.location.href = `checkins.php?highlight=${item.id}`;
        };

        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);

        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }

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
                    <span>Thông báo quét QR mới nhất</span>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button type="button" class="notif-test-sound-btn" onclick="playNotifChime(); event.stopPropagation();" title="Thử phát chuông thông báo">Thử loa</button>
                        <button type="button" class="notif-mark-read-btn" onclick="markAllNotifsRead(event)">Đã đọc</button>
                    </div>
                </div>
                <div class="notif-dropdown-list" id="notifDropdownList">
                    <div class="notif-empty-state">Đang tải thông báo...</div>
                </div>
            </div>
        `;

        const mobileControls = document.querySelector('.mobile-brand-controls');
        const header = document.querySelector('.header');

        if (mobileControls && window.innerWidth <= 768) {
            const menuBtn = document.getElementById('mobileMenuBtn');
            if (menuBtn) {
                mobileControls.insertBefore(notifBox, menuBtn);
            } else {
                mobileControls.appendChild(notifBox);
            }
        } else if (header) {
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
            const statusClass = isWalkIn ? 'status-walkin' : 'status-matched';
            const badgeClass = isWalkIn ? 'badge-walkin' : 'badge-matched';
            const statusLabel = isWalkIn ? 'Khách phát sinh' : 'Khách hợp lệ';
            const timeDisplay = item.time ? item.time.split(' ')[0] : '';

            html += `
                <div class="notif-item ${statusClass} ${item.is_new ? 'unread' : ''}" onclick="window.location.href='checkins.php?highlight=${item.id}'">
                    <div class="notif-item-title">
                        <span class="notif-status-badge ${badgeClass}">${statusLabel}</span>
                        <span class="notif-item-time">${timeDisplay}</span>
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

    function triggerNativeNotification(item) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const isWalkIn = item.status === 'walk_in';
            const title = isWalkIn ? '🔸 Khách phát sinh vừa quét QR!' : '✅ Khách đã Check-in thành công!';
            const body = `Họ tên: ${item.full_name}\nSĐT: ${item.phone}\nBàn: ${item.table_name}`;
            try {
                new Notification(title, {
                    body: body,
                    icon: '../img/logo pmt.png',
                    badge: '../img/logo pmt.png',
                    vibrate: [200, 100, 200]
                });
            } catch (e) {}
        }
    }

    async function checkNewNotifications() {
        if (isCheckingNotifications) return;
        isCheckingNotifications = true;

        try {
            const res = await fetch(`../api/notifications.php?action=check&_t=${Date.now()}`, { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();

            if (data.status === 'success') {
                const newMaxId = data.max_id;

                if (clientLastCheckinId === 0) {
                    clientLastCheckinId = newMaxId;
                    if (data.checkins) {
                        data.checkins.forEach(item => shownToastIds.add(item.id));
                    }
                    renderNotifDropdown(data.checkins, data.unread_count);
                    return;
                }

                if (newMaxId > clientLastCheckinId) {
                    const oldLastId = clientLastCheckinId;
                    clientLastCheckinId = newMaxId;

                    const newItems = (data.checkins || []).filter(item => item.id > oldLastId && !shownToastIds.has(item.id));

                    if (newItems.length > 0) {
                        playNotifChime();
                        newItems.forEach(item => {
                            showToastNotification(item);
                            triggerNativeNotification(item);
                            shownToastIds.add(item.id);
                        });
                    }
                }

                renderNotifDropdown(data.checkins, data.unread_count);
            }
        } catch (err) {
            console.error('Notification check error:', err);
        } finally {
            isCheckingNotifications = false;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkNewNotifications();
        }
    });

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
        unlockAudio();
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

    function requestBrowserNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                Notification.requestPermission();
            } catch(e){}
        }
    }

    // Mở khóa âm thanh trình duyệt khi người dùng tương tác
    ['click', 'touchstart', 'keydown', 'scroll', 'mousemove'].forEach(evt => {
        document.addEventListener(evt, () => {
            unlockAudio();
        }, { once: true });
    });

    // ===== GLOBAL CUSTOM CONFIRMATION POPUP SYSTEM =====
    function injectConfirmModalHTML() {
        if (document.getElementById('globalConfirmModal')) return;

        const overlay = document.createElement('div');
        overlay.id = 'globalConfirmModal';
        overlay.className = 'confirm-modal-overlay';
        overlay.innerHTML = `
            <div class="confirm-modal-box">
                <div class="confirm-modal-icon" id="confirmModalIcon" style="display:none;"></div>
                <div class="confirm-modal-title" id="confirmModalTitle">Xác nhận thao tác</div>
                <div class="confirm-modal-message" id="confirmModalMessage">Bạn có chắc chắn muốn thực hiện thao tác này?</div>
                <div class="confirm-modal-actions">
                    <button type="button" class="btn-confirm-cancel" id="confirmModalCancelBtn">Hủy bỏ</button>
                    <button type="button" class="btn-confirm-submit" id="confirmModalOkBtn">Xác nhận</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    let pendingConfirmAction = null;

    window.showConfirmPopup = function(options) {
        injectConfirmModalHTML();
        const overlay = document.getElementById('globalConfirmModal');
        const iconEl = document.getElementById('confirmModalIcon');
        const titleEl = document.getElementById('confirmModalTitle');
        const msgEl = document.getElementById('confirmModalMessage');
        const okBtn = document.getElementById('confirmModalOkBtn');
        const cancelBtn = document.getElementById('confirmModalCancelBtn');

        if (options.icon) {
            iconEl.textContent = options.icon;
            iconEl.style.display = 'block';
        } else {
            iconEl.style.display = 'none';
        }
        
        titleEl.textContent = options.title || 'Xác nhận thao tác';
        msgEl.innerHTML = options.message || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
        
        const okLabel = options.okText || 'Xác nhận';
        const cancelLabel = options.cancelText || 'Hủy bỏ';
        
        okBtn.textContent = okLabel;
        cancelBtn.textContent = cancelLabel;
        
        okBtn.style.background = options.danger === false ? '#2e7d32' : '#d32f2f';

        pendingConfirmAction = options.onConfirm || null;

        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);

        function close() {
            overlay.classList.remove('active');
            setTimeout(() => { overlay.style.display = 'none'; }, 250);
        }

        cancelBtn.onclick = function() {
            close();
        };

        overlay.onclick = function(e) {
            if (e.target === overlay) close();
        };

        okBtn.onclick = function() {
            close();
            if (typeof pendingConfirmAction === 'function') {
                pendingConfirmAction();
            }
        };
    };

    // Global Keyboard Listener for Y / N / Enter / Escape Hotkeys
    document.addEventListener('keydown', function(e) {
        const overlay = document.getElementById('globalConfirmModal');
        if (overlay && (overlay.style.display === 'flex' || overlay.classList.contains('active'))) {
            const key = e.key ? e.key.toLowerCase() : '';
            if (key === 'y' || key === 'enter') {
                e.preventDefault();
                e.stopPropagation();
                const okBtn = document.getElementById('confirmModalOkBtn');
                if (okBtn) okBtn.click();
            } else if (key === 'n' || key === 'escape') {
                e.preventDefault();
                e.stopPropagation();
                const cancelBtn = document.getElementById('confirmModalCancelBtn');
                if (cancelBtn) cancelBtn.click();
            }
        }
    });

    window.confirmModal = function(evt, message, options = {}) {
        if (evt) evt.preventDefault();
        const target = evt ? (evt.currentTarget || evt.target) : null;

        window.showConfirmPopup({
            message: message,
            title: options.title || 'Xác nhận thao tác',
            icon: options.icon || '⚠️',
            danger: options.danger !== false,
            onConfirm: () => {
                if (!target) return;
                if (target.tagName === 'FORM') {
                    target.submit();
                } else if (target.tagName === 'A') {
                    window.location.href = target.href;
                } else if (target.form) {
                    target.form.submit();
                }
            }
        });
        return false;
    };

    let lastKnownCheckinId = 0;

    function processSSERecentCheckins(checkins) {
        if (!checkins || checkins.length === 0) return;
        const latestId = parseInt(checkins[0].id) || 0;
        
        if (lastKnownCheckinId > 0 && latestId > lastKnownCheckinId) {
            playNotifChime();
            const newCheckins = checkins.filter(c => parseInt(c.id) > lastKnownCheckinId);
            newCheckins.forEach(c => {
                showToastNotification({
                    id: c.id,
                    full_name: c.full_name || 'Khách mời',
                    phone: c.phone || '',
                    table_name: c.table_name || 'Chưa xếp bàn',
                    status: c.match_status || 'matched',
                    time: c.checkin_time || ''
                });
            });
        }
        lastKnownCheckinId = latestId;

        const readIds = getReadCheckinIds();
        const unreadCount = checkins.filter(c => !readIds.includes(parseInt(c.id))).length;

        const formatted = checkins.map(c => ({
            id: c.id,
            name: c.full_name || 'Khách mời',
            phone: c.phone || '',
            table: c.table_name || 'Chưa xếp bàn',
            status: c.match_status || 'matched',
            time: c.checkin_time || '',
            is_new: !readIds.includes(parseInt(c.id))
        }));

        renderNotifDropdown(formatted, unreadCount);
    }

    function initRealtimeSSE() {
        if (!window.EventSource) return;

        const currentPath = window.location.pathname;
        let ssePath = 'api/sse.php';
        if (currentPath.includes('/admin/')) {
            ssePath = '../api/sse.php';
        }

        try {
            const sseSource = new EventSource(ssePath);

            sseSource.onmessage = function(event) {
                if (!event.data) return;
                try {
                    const payload = JSON.parse(event.data);
                    if (payload && payload.type === 'db_change' && payload.data) {
                        if (Array.isArray(payload.data.recent_checkins)) {
                            processSSERecentCheckins(payload.data.recent_checkins);
                        }
                        window.dispatchEvent(new CustomEvent('dbRealtimeChange', { detail: payload.data }));
                    }
                } catch(e) {
                    console.error('SSE JSON parse error:', e);
                }
            };
        } catch(e) {
            console.error('SSE init error:', e);
        }
    }

    // Global Toast Notification Manager (Không dùng icon, chống trùng lặp thông báo)
    const shownToastHashes = new Set();

    window.showAppToast = function(message, type = 'success', title = '') {
        if (!message) return;
        const msgHash = `${type}_${message.trim()}`;
        if (shownToastHashes.has(msgHash)) return;
        shownToastHashes.add(msgHash);
        setTimeout(() => shownToastHashes.delete(msgHash), 3000);

        let container = document.getElementById('appToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'appToastContainer';
            container.className = 'app-toast-container';
            document.body.appendChild(container);
        }

        let defaultTitle = 'Thao tác thành công';
        let toastClass = 'toast-success';

        const lower = message.toLowerCase();

        // 1. UƯ TIÊN KIỂM TRA LỖI TRƯỚC TIÊN -> POPUP ĐỎ
        if (type === 'error' || lower.includes('lỗi') || lower.includes('thất bại') || lower.includes('không thể')) {
            defaultTitle = 'Thông báo lỗi';
            toastClass = 'toast-error';
        } 
        // 2. CÁC THAO TÁC THÀNH CÔNG -> POPUP XANH LÁ
        else if (type === 'edit' || lower.includes('sửa') || lower.includes('cập nhật')) {
            defaultTitle = 'Cập nhật thành công';
            toastClass = 'toast-success';
        } else if (type === 'delete' || lower.includes('xóa')) {
            defaultTitle = 'Đã xóa thành công';
            toastClass = 'toast-success';
        } else if (type === 'add' || lower.includes('thêm')) {
            defaultTitle = 'Thêm mới thành công';
            toastClass = 'toast-success';
        }

        if (!title) title = defaultTitle;

        const item = document.createElement('div');
        item.className = `app-toast-item ${toastClass}`;
        item.innerHTML = `
            <div class="app-toast-content">
                <div class="app-toast-title">${title}</div>
                <div class="app-toast-message">${message}</div>
            </div>
            <button type="button" class="app-toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(item);

        setTimeout(() => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(-10px)';
            setTimeout(() => item.remove(), 300);
        }, 4000);
    };

    // Auto Intercept PHP Alert Boxes & Scroll to Bottom on Add
    function initAlertAndAutoScrollInterceptor() {
        const alerts = document.querySelectorAll('.alert.success, .alert.error, .alert.info, .alert.danger');
        let isAddAction = false;

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('added') === '1' || urlParams.get('scroll') === 'bottom') {
            isAddAction = true;
        }

        alerts.forEach(alert => {
            const text = alert.textContent.trim();
            if (!text) return;

            const lower = text.toLowerCase();
            let type = 'success';

            // Kiểm tra lỗi trước tiên để tránh bị đè thành thông báo sửa/cập nhật
            if (alert.classList.contains('error') || alert.classList.contains('danger') || lower.includes('lỗi')) {
                type = 'error';
            } else if (lower.includes('thêm')) {
                isAddAction = true;
                type = 'add';
            } else if (lower.includes('sửa') || lower.includes('cập nhật')) {
                type = 'edit';
            } else if (lower.includes('xóa')) {
                type = 'delete';
            }

            window.showAppToast(text, type);
            // Ẩn thẻ thông báo inline trên trang theo đúng yêu cầu người dùng (chỉ hiển thị Popup Toast)
            alert.style.display = 'none';
        });

        if (isAddAction) {
            setTimeout(scrollToTableBottom, 400);
        }
    }

    function scrollToTableBottom() {
        const tableContainers = document.querySelectorAll('.table-responsive');
        tableContainers.forEach(container => {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth'
            });
        });

        const lastRows = document.querySelectorAll('.table-responsive table tbody tr:last-child');
        lastRows.forEach(row => {
            row.classList.add('row-new-highlight');
            setTimeout(() => row.classList.remove('row-new-highlight'), 3000);
        });
    }

    window.scrollToTableBottom = scrollToTableBottom;

    document.addEventListener('DOMContentLoaded', () => {
        injectHeaderNotifBell();
        injectConfirmModalHTML();
        checkNewNotifications();
        initRealtimeSSE();
        initAlertAndAutoScrollInterceptor();
        setInterval(checkNewNotifications, 3000);
    });
})();
