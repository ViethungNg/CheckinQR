<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$userId = $_SESSION['admin_id'] ?? 0;
$userRole = $_SESSION['admin_role'] ?? 'staff';
$isUserAdmin = isAdmin();

// Lấy trạng thái thông báo cá nhân
$userNotifEnabled = getUserNotificationSetting($userId);

// Lấy cấu hình 3 bảng cho Admin
$configDashboard = getTableColumnsConfig('dashboard');
$configGuests    = getTableColumnsConfig('guests');
$configCheckins   = getTableColumnsConfig('checkins');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT Checkin - Cấu hình hệ thống</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <style>
        .settings-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
            max-width: 100%;
        }

        .settings-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 100%;
        }

        @media (max-width: 768px) {
            .settings-card {
                padding: 16px;
                border-radius: 12px;
            }

            .toggle-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .switch {
                align-self: flex-start;
            }

            .column-item {
                flex-wrap: wrap;
                gap: 10px;
                padding: 12px;
            }

            .column-left-group {
                flex: 1;
                min-width: 160px;
            }

            .column-controls {
                margin-left: auto;
            }

            .settings-actions {
                flex-direction: column-reverse;
                gap: 10px;
                width: 100%;
            }

            .settings-actions .btn {
                width: 100%;
                text-align: center;
            }
        }

        .settings-card-header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1.5px solid #f1f5f9;
        }

        .settings-card-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px 0;
        }

        .settings-card-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            margin: 0;
        }

        /* Toggle Switch Styling */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .toggle-info h4 {
            margin: 0 0 4px 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .toggle-info p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 56px;
            height: 30px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #d32f2f;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Tabs Styling */
        .settings-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 700;
            font-size: 0.95rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .tab-btn.active {
            color: #d32f2f;
            border-bottom-color: #d32f2f;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Sortable Columns List Styling */
        .column-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .column-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 16px;
            cursor: grab;
            transition: all 0.15s ease;
            user-select: none;
        }

        .column-item:hover {
            border-color: #94a3b8;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
        }

        .column-item.dragging {
            opacity: 0.5;
            background: #f1f5f9;
            border-style: dashed;
        }

        .column-left-group {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .drag-handle {
            color: #94a3b8;
            font-size: 1.2rem;
            cursor: grab;
        }

        .column-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #d32f2f;
            cursor: pointer;
        }

        .column-label {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .column-controls {
            display: flex;
            gap: 6px;
        }

        .btn-move {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-move:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .settings-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-hero-banner" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border-radius: 16px; padding: 24px; margin-bottom: 24px;">
            <div class="hero-text-content">
                <div class="hero-role-badge" style="background: rgba(255,255,255,0.15); color: #ffffff;">Hệ Thống PMT</div>
                <h2 style="margin: 6px 0; font-size: 1.6rem; color: #ffffff;">Cấu Hình Hệ Thống</h2>
                <p style="margin: 0; opacity: 0.85; font-size: 0.92rem;">Cài đặt thông báo cá nhân và tùy chỉnh hiển thị cột dữ liệu cho các bảng quản trị.</p>
            </div>
        </div>

        <div id="settingsAlert" style="display:none; margin-bottom: 20px;" class="alert"></div>

        <div class="settings-container">

            <!-- 2. Cấu hình Hiển thị Cột của Bảng (Chỉ dành riêng cho ADMIN) -->
            <?php if ($isUserAdmin): ?>
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3 class="settings-card-title">Cấu hình Bảng dữ liệu Toàn hệ thống (Admin)</h3>
                    <p class="settings-card-subtitle">Tùy chỉnh bật/tắt hiển thị và thứ tự vị trí các cột của 3 bảng. Khi Lưu, tất cả người dùng sẽ áp dụng cấu hình này.</p>
                </div>

                <div class="settings-tabs">
                    <button type="button" class="tab-btn active" onclick="switchTableTab('dashboard')">Dashboard</button>
                    <button type="button" class="tab-btn" onclick="switchTableTab('guests')">Danh sách khách hàng</button>
                    <button type="button" class="tab-btn" onclick="switchTableTab('checkins')">Khách đã Check-in</button>
                </div>

                <!-- Tab 1: Dashboard -->
                <div id="tab-dashboard" class="tab-content active">
                    <div class="column-list" id="list-dashboard">
                        <?php foreach ($configDashboard as $col): ?>
                            <div class="column-item" draggable="true" data-key="<?php echo esc($col['key']); ?>">
                                <div class="column-left-group">
                                    <span class="drag-handle" title="Kéo thả để đổi vị trí">☰</span>
                                    <input type="checkbox" class="column-checkbox" <?php echo !empty($col['visible']) ? 'checked' : ''; ?>>
                                    <span class="column-label"><?php echo esc($col['label']); ?></span>
                                </div>
                                <div class="column-controls">
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'up')" title="Di chuyển lên">▲ Lên</button>
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'down')" title="Di chuyển xuống">▼ Xuống</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab 2: Guests -->
                <div id="tab-guests" class="tab-content">
                    <div class="column-list" id="list-guests">
                        <?php foreach ($configGuests as $col): ?>
                            <div class="column-item" draggable="true" data-key="<?php echo esc($col['key']); ?>">
                                <div class="column-left-group">
                                    <span class="drag-handle" title="Kéo thả để đổi vị trí">☰</span>
                                    <input type="checkbox" class="column-checkbox" <?php echo !empty($col['visible']) ? 'checked' : ''; ?>>
                                    <span class="column-label"><?php echo esc($col['label']); ?></span>
                                </div>
                                <div class="column-controls">
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'up')" title="Di chuyển lên">▲ Lên</button>
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'down')" title="Di chuyển xuống">▼ Xuống</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab 3: Checkins -->
                <div id="tab-checkins" class="tab-content">
                    <div class="column-list" id="list-checkins">
                        <?php foreach ($configCheckins as $col): ?>
                            <div class="column-item" draggable="true" data-key="<?php echo esc($col['key']); ?>">
                                <div class="column-left-group">
                                    <span class="drag-handle" title="Kéo thả để đổi vị trí">☰</span>
                                    <input type="checkbox" class="column-checkbox" <?php echo !empty($col['visible']) ? 'checked' : ''; ?>>
                                    <span class="column-label"><?php echo esc($col['label']); ?></span>
                                </div>
                                <div class="column-controls">
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'up')" title="Di chuyển lên">▲ Lên</button>
                                    <button type="button" class="btn-move" onclick="moveColumnItem(this, 'down')" title="Di chuyển xuống">▼ Xuống</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="button" onclick="resetTableConfiguration()" class="btn" style="background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; padding: 12px 20px; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer;">
                        Khôi phục mặc định (Reset)
                    </button>
                    <button type="button" onclick="saveTableConfiguration()" class="btn" style="background: linear-gradient(135deg, #d32f2f, #b71c1c); color: #ffffff; border: none; padding: 12px 26px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; box-shadow: 0 4px 14px rgba(211, 47, 47, 0.3);">
                        Lưu cấu hình hệ thống
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
let currentActiveTab = 'dashboard';

function showAlert(msg, isSuccess = true) {
    const box = document.getElementById('settingsAlert');
    box.style.display = 'block';
    box.className = isSuccess ? 'alert success' : 'alert error';
    box.style.background = isSuccess ? '#f0fdf4' : '#fff5f5';
    box.style.border = isSuccess ? '1.5px solid #86efac' : '1.5px solid #fca5a5';
    box.style.color = isSuccess ? '#166534' : '#991b1b';
    box.style.padding = '14px 18px';
    box.style.borderRadius = '12px';
    box.style.fontWeight = '700';
    box.innerHTML = msg;

    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { box.style.display = 'none'; }, 4000);
}

// 1. Chuyển Tab
function switchTableTab(tabName) {
    currentActiveTab = tabName;
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.textContent.toLowerCase().includes(tabName === 'dashboard' ? 'dashboard' : (tabName === 'guests' ? 'danh sách' : 'khách đã')));
    if (activeBtn) activeBtn.classList.add('active');

    const activeContent = document.getElementById('tab-' + tabName);
    if (activeContent) activeContent.classList.add('active');
}

// 2. Di chuyển cột Up / Down
function moveColumnItem(btn, direction) {
    const item = btn.closest('.column-item');
    if (!item) return;

    if (direction === 'up') {
        const prev = item.previousElementSibling;
        if (prev) item.parentNode.insertBefore(item, prev);
    } else if (direction === 'down') {
        const next = item.nextElementSibling;
        if (next) item.parentNode.insertBefore(next, item);
    }
}

// 3. Khởi tạo Drag & Drop Kéo thả
document.querySelectorAll('.column-list').forEach(list => {
    let draggedItem = null;

    list.addEventListener('dragstart', (e) => {
        const item = e.target.closest('.column-item');
        if (item) {
            draggedItem = item;
            item.classList.add('dragging');
        }
    });

    list.addEventListener('dragend', (e) => {
        const item = e.target.closest('.column-item');
        if (item) {
            item.classList.remove('dragging');
        }
        draggedItem = null;
    });

    list.addEventListener('dragover', (e) => {
        e.preventDefault();
        const afterElement = getDragAfterElement(list, e.clientY);
        if (draggedItem) {
            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        }
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.column-item:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// 4. Bật / Tắt Thông báo cá nhân
async function toggleUserNotification(input) {
    const enabled = input.checked;

    if (enabled && 'Notification' in window) {
        if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                showAlert('Trình duyệt chưa cấp quyền Push Notification.', false);
            }
        }
    }

    try {
        const formData = new FormData();
        formData.append('action', 'save_user_notification');
        formData.append('enabled', enabled ? 1 : 0);

        const res = await fetch('../api/settings.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            showAlert('Đã cập nhật cài đặt thông báo cá nhân thành công!');
        } else {
            showAlert(data.message || 'Lỗi lưu cài đặt thông báo', false);
            input.checked = !enabled;
        }
    } catch (e) {
        showAlert('Không thể kết nối máy chủ', false);
        input.checked = !enabled;
    }
}

// 5. Lưu Cấu hình Bảng (Admin)
async function saveTableConfiguration() {
    const list = document.getElementById('list-' + currentActiveTab);
    if (!list) return;

    const items = list.querySelectorAll('.column-item');
    const columns = [];

    items.forEach(item => {
        const key = item.getAttribute('data-key');
        const checkbox = item.querySelector('.column-checkbox');
        columns.push({
            key: key,
            visible: checkbox ? (checkbox.checked ? 1 : 0) : 1
        });
    });

    try {
        const formData = new FormData();
        formData.append('action', 'save_table_config');
        formData.append('table_name', currentActiveTab);
        formData.append('columns', JSON.stringify(columns));

        const res = await fetch('../api/settings.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            showAlert('Đã lưu cấu hình bảng toàn hệ thống thành công!');
        } else {
            showAlert(data.message || 'Lỗi lưu cấu hình bảng', false);
        }
    } catch (e) {
        showAlert('Không thể kết nối máy chủ', false);
    }
}

// 6. Reset Cấu hình Bảng (Admin)
async function resetTableConfiguration() {
    if (!confirm('Bạn có chắc chắn muốn khôi phục vị trí và hiển thị cột của cả 3 bảng về mặc định ban đầu? (Cài đặt thông báo cá nhân sẽ không bị ảnh hưởng)')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'reset_table_config');

        const res = await fetch('../api/settings.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            showAlert('Đã khôi phục cấu hình mặc định cho tất cả các bảng thành công!');
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            showAlert(data.message || 'Lỗi khôi phục cấu hình', false);
        }
    } catch (e) {
        showAlert('Không thể kết nối máy chủ', false);
    }
}
</script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>

</body>
</html>
