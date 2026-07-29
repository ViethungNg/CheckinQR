<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

// Xử lý thống kê cho dashboard theo vai trò
$userId = $_SESSION['admin_id'] ?? 0;
$adminRole = $_SESSION['admin_role'] ?? 'staff';

if (isKinhDoanh()) {
    $stats = [
        'events'      => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'guests'      => (int)$db->query("SELECT COUNT(*) FROM guests g JOIN event_tables t ON g.table_id = t.id WHERE t.assigned_user_id = {$userId}")->fetchColumn(),
        'checked_in'  => (int)$db->query("SELECT COUNT(*) FROM checkins c JOIN event_tables t ON c.table_id = t.id WHERE c.match_status = 'matched' AND t.assigned_user_id = {$userId}")->fetchColumn(),
        'walk_in'     => 0,
        'unassigned'  => 0,
        'not_arrived' => (int)$db->query("SELECT COUNT(*) FROM guests g JOIN event_tables t ON g.table_id = t.id WHERE g.status = 'invited' AND t.assigned_user_id = {$userId}")->fetchColumn(),
    ];
} else {
    $stats = [
        'events'      => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'guests'      => (int)$db->query("SELECT COUNT(*) FROM guests")->fetchColumn(),
        'checked_in'  => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'matched'")->fetchColumn(),
        'walk_in'     => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'walk_in'")->fetchColumn(),
        'unassigned'  => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE table_id IS NULL OR table_id = 0")->fetchColumn(),
        'not_arrived' => (int)$db->query("SELECT COUNT(*) FROM guests WHERE status = 'invited'")->fetchColumn(),
    ];
}

// Tỉ lệ % checkin toàn bộ
$checkinRate = $stats['guests'] > 0 ? round(($stats['checked_in'] / $stats['guests']) * 100) : 0;

// Lấy link Màn Hình Quét QR theo sự kiện đang Active
$activeEventStmt = $db->query("SELECT slug FROM events WHERE status = 'active' ORDER BY id DESC");
$activeEvents = $activeEventStmt->fetchAll();
$qrCheckinUrl = '../index.php';
if (count($activeEvents) === 1) {
    $qrCheckinUrl = '../index.php?event=' . urlencode($activeEvents[0]['slug']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CheckinQR</title>
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Role-Specific Hero Banner -->
        <?php if (isAdmin()): ?>
            <div class="dashboard-hero-banner hero-role-admin">
                <div class="hero-text-content">
                    <div class="hero-role-badge">Admin / Quản trị viên</div>
                    <h2>Xin chào, <?php echo esc($_SESSION['admin_name'] ?? 'Admin'); ?>!</h2>
                    <p>Tổng quan hệ thống điều hành check-in sự kiện real-time & quản lý bàn tiệc.</p>
                </div>
                <div class="hero-quick-actions">
                    <a href="events.php" class="btn-hero-action btn-hero-primary">Quản lý Sự kiện</a>
                    <a href="guests.php" class="btn-hero-action btn-hero-outline">Danh sách Khách</a>
                    <a href="<?php echo $qrCheckinUrl; ?>" target="_blank" class="btn-hero-action btn-hero-outline">Màn Hình Quét QR</a>
                </div>
            </div>
        <?php elseif (isLeTan()): ?>
            <div class="dashboard-hero-banner hero-role-letan">
                <div class="hero-text-content">
                    <div class="hero-role-badge">Nhân viên Lễ Tân</div>
                    <h2>Chào mừng Lễ Tân, <?php echo esc($_SESSION['admin_name'] ?? 'Lễ Tân'); ?>!</h2>
                    <p>Trung tâm tiếp đón khách hàng & vận hành check-in tốc độ cao tại sảnh.</p>
                </div>
                <div class="hero-quick-actions">
                    <a href="<?php echo $qrCheckinUrl; ?>" target="_blank" class="btn-hero-action btn-hero-primary">Quét QR Khách</a>
                    <a href="guests.php" class="btn-hero-action btn-hero-outline">Tìm Khách Hàng</a>
                    <a href="checkins.php" class="btn-hero-action btn-hero-outline">Lịch sử Check-in</a>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-hero-banner hero-role-kinhdoanh">
                <div class="hero-text-content">
                    <div class="hero-role-badge">Nhân viên Kinh Doanh</div>
                    <h2>Bàn Phụ Trách - <?php echo esc($_SESSION['admin_name'] ?? 'Kinh Doanh'); ?></h2>
                    <p>Theo dõi tiến độ khách dự tiệc & thông tin các bàn bạn trực tiếp phụ trách.</p>
                </div>
                <div class="hero-quick-actions">
                    <a href="guests.php" class="btn-hero-action btn-hero-primary">Khách Mời Của Tôi</a>
                    <a href="tables.php" class="btn-hero-action btn-hero-outline">Danh Sách Bàn Phụ Trách</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Modern Stats Cards Grid -->
        <div class="dashboard-cards-grid">
            <?php if (!isKinhDoanh()): ?>
            <div class="stat-card-modern card-accent-events active-card" id="card-all" onclick="setFilter('all')" title="Bấm để xem tất cả">
                <div class="stat-card-header">
                    <span class="stat-card-title">Tổng Sự Kiện</span>
                </div>
                <div class="stat-card-value" id="val-events"><?php echo $stats['events']; ?></div>
                <div class="stat-card-subtitle">Đang diễn ra</div>
            </div>
            <?php endif; ?>

            <div class="stat-card-modern card-accent-guests <?php echo isKinhDoanh() ? 'active-card' : ''; ?>" id="card-guests" onclick="setFilter('guests')" title="Bấm để lọc tất cả khách dự kiến">
                <div class="stat-card-header">
                    <span class="stat-card-title">Khách Dự Kiến <?php echo isKinhDoanh() ? '(Phụ trách)' : ''; ?></span>
                </div>
                <div class="stat-card-value" id="val-guests"><?php echo $stats['guests']; ?></div>
                <div class="stat-card-subtitle">Danh sách mới nhất</div>
            </div>

            <div class="stat-card-modern card-accent-matched" id="card-matched" onclick="setFilter('matched')" title="Bấm để lọc khách đã Check-in hợp lệ">
                <div class="stat-card-header">
                    <span class="stat-card-title">Đã Check-in (Khớp)</span>
                </div>
                <div class="stat-card-value" style="color: #10b981;" id="val-checked-in"><?php echo $stats['checked_in']; ?></div>
                <div class="stat-card-subtitle">Hợp lệ thành công</div>
            </div>

            <?php if (!isKinhDoanh()): ?>
            <div class="stat-card-modern card-accent-walkin" id="card-walk_in" onclick="setFilter('walk_in')" title="Bấm để lọc khách phát sinh">
                <div class="stat-card-header">
                    <span class="stat-card-title">Khách Phát Sinh</span>
                </div>
                <div class="stat-card-value" style="color: #f59e0b;" id="val-walk-in"><?php echo $stats['walk_in']; ?></div>
                <div class="stat-card-subtitle">Walk-in tại sảnh</div>
            </div>

            <div class="stat-card-modern card-accent-unassigned" id="card-unassigned" onclick="setFilter('unassigned')" title="Bấm để lọc khách chưa xếp bàn">
                <div class="stat-card-header">
                    <span class="stat-card-title">Chưa Xếp Bàn</span>
                </div>
                <div class="stat-card-value" style="color: #ef4444;" id="val-unassigned"><?php echo $stats['unassigned']; ?></div>
                <div class="stat-card-subtitle">Cần phân chỗ</div>
            </div>
            <?php endif; ?>

            <div class="stat-card-modern card-accent-notarrived" id="card-not_arrived" onclick="setFilter('not_arrived')" title="Bấm để lọc khách dự kiến chưa tới">
                <div class="stat-card-header">
                    <span class="stat-card-title">Khách Chưa Tới</span>
                </div>
                <div class="stat-card-value" style="color: #6366f1;" id="val-not-arrived"><?php echo $stats['not_arrived']; ?></div>
                <div class="stat-card-subtitle">Đang chờ tiếp đón</div>
            </div>
        </div>

        <!-- Real-Time Floorplan Table Cards Grid -->
        <div class="table-floorplan-section">
            <div class="floorplan-header">
                <div class="floorplan-title">
                    Sơ Đồ Trạng Thái Bàn (Real-time) <?php echo isKinhDoanh() ? '(Bàn Phụ Trách)' : ''; ?>
                </div>
                <div class="floorplan-hint">
                    Bấm vào thẻ Bàn để xem danh sách chi tiết
                </div>
            </div>
            <div id="tables-cards-container" class="tables-grid-cards">
                <!-- Dynamically rendered via Javascript -->
            </div>
        </div>

        <!-- Smart Filter Toolbar & Guest Checkin Table Section -->
        <div class="dashboard-table-card">
            <div class="table-filter-toolbar">
                <div class="table-toolbar-left">
                    <div class="dashboard-search-box">
                        <input type="text" id="dashboard-search-input" placeholder="Tìm theo tên, SĐT hoặc tên bàn..." oninput="handleSearchInput(this.value)">
                    </div>
                </div>
                <div class="table-toolbar-right">
                    <select id="table-select-filter" class="table-select-custom" onchange="setTableFilter(this.value)">
                        <option value="all">Tất cả các Bàn</option>
                    </select>
                    <div class="realtime-pill-badge">
                        <div class="pulse-dot"></div> Real-time (3s)
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-data-table">
                    <thead>
                        <tr>
                            <th>Khách Nhập</th>
                            <th>Số Điện Thoại</th>
                            <th>Bàn Tiệc</th>
                            <th>Hình Thức Check-in</th>
                            <th>Thời Gian</th>
                            <th>Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody id="recent-checkins-body">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilter = '<?php echo isKinhDoanh() ? "guests" : "all"; ?>';
let selectedTableId = 'all';
let currentSearch = '';
let searchDebounceTimer = null;
let lastIndexDataHash = '';

function setFilter(val) {
    currentFilter = val;
    
    // Highlight active card
    document.querySelectorAll('.dashboard-cards-grid .stat-card-modern').forEach(card => card.classList.remove('active-card'));
    const activeCard = document.getElementById('card-' + val);
    if (activeCard) activeCard.classList.add('active-card');
    
    updateRealtimeStats(true);
}

function setTableFilter(tableId) {
    selectedTableId = tableId;
    
    // Update select element
    const select = document.getElementById('table-select-filter');
    if (select && select.value !== tableId) {
        select.value = tableId;
    }
    
    // Highlight table card
    document.querySelectorAll('.table-card-v2').forEach(card => card.classList.remove('active-table'));
    const activeTableCard = document.getElementById('table-card-' + tableId);
    if (activeTableCard) {
        activeTableCard.classList.add('active-table');
    }
    
    updateRealtimeStats();
}

function handleSearchInput(val) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentSearch = val.trim();
        updateRealtimeStats(true);
    }, 300);
}

async function updateRealtimeStats(forceRefresh = false) {
    try {
        const queryUrl = `../api/stats.php?filter=${encodeURIComponent(currentFilter)}&table_id=${encodeURIComponent(selectedTableId)}&search=${encodeURIComponent(currentSearch)}&_t=${Date.now()}`;
        const response = await fetch(queryUrl, { cache: 'no-store' });
        if (!response.ok) return;
        const textData = await response.text();

        if (!forceRefresh && textData === lastIndexDataHash) return;
        lastIndexDataHash = textData;

        const result = JSON.parse(textData);
        
        if (result.status === 'success') {
            const data = result.data.stats;
            
            // Cập nhật số liệu trên thẻ thống kê
            if (document.getElementById('val-events')) document.getElementById('val-events').textContent = data.events;
            if (document.getElementById('val-guests')) document.getElementById('val-guests').textContent = data.guests;
            if (document.getElementById('val-checked-in')) document.getElementById('val-checked-in').textContent = data.checked_in;
            if (document.getElementById('val-walk-in')) document.getElementById('val-walk-in').textContent = data.walk_in;
            if (document.getElementById('val-unassigned')) document.getElementById('val-unassigned').textContent = data.unassigned;
            if (document.getElementById('val-not-arrived')) document.getElementById('val-not-arrived').textContent = data.not_arrived;
            
            // Render Sơ đồ trạng thái từng Bàn
            if (result.data.tables) {
                renderTableCards(result.data.tables);
                populateTableSelectOptions(result.data.tables);
            }

            // Cập nhật danh sách khách hàng / check-in
            const tbody = document.getElementById('recent-checkins-body');
            if (result.data.recent_checkins) {
                if (result.data.recent_checkins.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:32px; font-size:0.95rem;">Không tìm thấy dữ liệu phù hợp với bộ lọc hiện tại</td></tr>`;
                } else {
                    let html = '';
                    result.data.recent_checkins.slice(0, 150).forEach(item => {
                        const isCheckedIn = item.status === 'checked_in' || item.status === 'matched';
                        const rowClass = isCheckedIn ? 'row-checked-in' : '';
                        
                        let badgeText = item.status_text;
                        let badgeStyle = 'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;';
                        
                        if (isCheckedIn) {
                            badgeText = 'Đã checkin';
                            badgeStyle = 'background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;';
                        } else if (item.status === 'walk_in') {
                            badgeText = 'Khách phát sinh';
                            badgeStyle = 'background:#fef3c7; color:#b45309; border:1px solid #fde68a;';
                        } else if (item.status === 'invited') {
                            badgeText = 'Chưa tới';
                            badgeStyle = 'background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe;';
                        }

                        let methodBadge = `<span style="font-size:0.78rem; font-weight:700; padding:3px 10px; border-radius:12px; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">Khớp SĐT</span>`;
                        if (item.status === 'walk_in') {
                            methodBadge = `<span style="font-size:0.78rem; font-weight:700; padding:3px 10px; border-radius:12px; background:#fffbeb; color:#b45309; border:1px solid #fde68a;">Khách phát sinh</span>`;
                        } else if (item.is_by_code) {
                            methodBadge = `<span style="font-size:0.78rem; font-weight:700; padding:3px 10px; border-radius:12px; background:#f3e8ff; color:#6b21a8; border:1px solid #e9d5ff;">Khớp Mã dự thưởng</span>`;
                        } else if (item.status === 'invited') {
                            methodBadge = `<span style="font-size:0.78rem; color:#94a3b8;">-</span>`;
                        }

                        const tableNameHtml = item.table_name && item.table_name !== 'Chưa xếp bàn'
                            ? `<span style="font-weight:700; color:#047857; background:#ecfdf5; border:1px solid #a7f3d0; padding:4px 10px; border-radius:8px; font-size:0.82rem;">${item.table_name}</span>`
                            : `<span style="color:#94a3b8; font-style:italic; font-size:0.85rem;">Chưa xếp</span>`;

                        html += `
                            <tr class="${rowClass}">
                                <td style="font-weight:700; color:#0f172a;">${item.full_name}</td>
                                <td style="font-weight:600; color:#475569;">${item.phone}</td>
                                <td>${tableNameHtml}</td>
                                <td>${methodBadge}</td>
                                <td style="font-size:0.85rem; color:#64748b;">${item.time}</td>
                                <td>
                                    <span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; ${badgeStyle}">${badgeText}</span>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            }
        }
    } catch (e) {
        console.error('Realtime update error:', e);
    }
}

function renderTableCards(tables) {
    const container = document.getElementById('tables-cards-container');
    if (!container) return;
    
    let html = '';
    
    // Thẻ Tất cả bàn
    const isAllActive = selectedTableId === 'all' ? 'active-table' : '';
    html += `
        <div class="table-card-v2 ${isAllActive}" id="table-card-all" onclick="setTableFilter('all')">
            <div class="table-card-top">
                <span class="table-name-badge">Tất cả các Bàn</span>
            </div>
            <div style="font-size:0.78rem; color:#64748b;">Bấm để xem tổng hợp tất cả vị trí</div>
        </div>
    `;
    
    if (!tables || tables.length === 0) {
        html += `
            <div style="grid-column: 1 / -1; padding: 14px; background: #fffbeb; color: #b45309; border-radius: 10px; font-size: 0.88rem; border: 1px dashed #fde68a; font-weight: 600;">
                Hiện chưa có bàn nào được phân công phụ trách.
            </div>
        `;
    } else {
        tables.forEach(t => {
            const isActive = String(selectedTableId) === String(t.id) ? 'active-table' : '';
            const pct = t.total_guests > 0 ? Math.round((t.arrived_guests / t.total_guests) * 100) : 0;
            
            html += `
                <div class="table-card-v2 ${isActive}" id="table-card-${t.id}" onclick="setTableFilter('${t.id}')">
                    <div class="table-card-top">
                        <span class="table-name-badge">${t.table_name}</span>
                        <span class="table-ratio-badge">${t.arrived_guests}/${t.total_guests}</span>
                    </div>
                    <div class="table-staff-info">
                        Phụ trách: ${t.assigned_user_name}
                    </div>
                    <div class="table-stats-row">
                        <span style="color:#10b981;">${t.arrived_guests} Đã tới</span>
                        <span style="color:#6366f1;">${t.not_arrived_guests} Chưa tới</span>
                    </div>
                    <div class="table-progress-bg">
                        <div class="table-progress-fill" style="width:${pct}%;"></div>
                    </div>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

function populateTableSelectOptions(tables) {
    const select = document.getElementById('table-select-filter');
    if (!select || select.dataset.loaded === 'true') return;
    
    let options = `<option value="all">Tất cả các Bàn</option>`;
    if (tables && tables.length > 0) {
        tables.forEach(t => {
            options += `<option value="${t.id}">${t.table_name} (${t.arrived_guests}/${t.total_guests} đã tới)</option>`;
        });
    }
    
    select.innerHTML = options;
    select.value = selectedTableId;
    select.dataset.loaded = 'true';
}

// Chạy ngay khi tải trang và lặp lại mỗi 3 giây
updateRealtimeStats();
setInterval(updateRealtimeStats, 3000);

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        updateRealtimeStats();
    }
});
</script>

<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
