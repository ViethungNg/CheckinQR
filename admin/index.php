<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

// Xử lý thống kê cho dashboard theo vai trò
$userId = $_SESSION['admin_id'] ?? 0;
if (isKinhDoanh()) {
    $stats = [
        'events'      => 0,
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CheckinQR</title>
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --primary-color: #d32f2f;
            --sidebar-width: 250px;
            --bg-color: #f4f6f8;
            --text-color: #333;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); padding: 20px; }
        .sidebar h2 { color: var(--primary-color); margin-bottom: 30px; font-size: 1.5rem; text-align: center; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 10px; }
        .sidebar ul li a { display: block; padding: 10px 15px; color: #555; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #fce4e4; color: var(--primary-color); font-weight: 600; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .btn-logout { background: #f44336; color: white; border: none; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; }
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; border-top: 4px solid var(--primary-color); cursor: pointer; transition: all 0.2s ease; user-select: none; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
        .card.active-card { background: #fff5f5; box-shadow: 0 0 0 2px var(--primary-color); }
        .card h3 { color: #777; font-size: 1rem; margin-bottom: 10px; font-weight: 500; }
        .card .value { font-size: 2.2rem; font-weight: bold; color: #333; }
        
        .table-overview-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .table-overview-card:hover {
            border-color: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(211,47,47,0.12);
        }
        .table-overview-card.active-table {
            border: 2px solid #d32f2f;
            background: #fff8f8;
            box-shadow: 0 4px 14px rgba(211,47,47,0.18);
        }

        .recent-section { margin-top: 25px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .recent-section h3 { margin-bottom: 15px; color: var(--primary-color); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge.matched { background: #e8f5e9; color: #2e7d32; }
        .badge.walk_in { background: #fff3e0; color: #ef6c00; }
        .badge.invited { background: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="header">
            <h1>Dashboard <?php echo isKinhDoanh() ? 'Kinh Doanh (Bàn Phụ Trách)' : 'Tổng quan'; ?></h1>
        </div>
        
        <!-- Stats Cards Grid -->
        <div class="dashboard-cards">
            <?php if(!isKinhDoanh()): ?>
            <div class="card active-card" id="card-all" onclick="setFilter('all')" title="Bấm để lọc tất cả lượt check-in">
                <h3>Tổng Sự kiện</h3>
                <div class="value" id="val-events"><?php echo $stats['events']; ?></div>
            </div>
            <?php endif; ?>
            <div class="card <?php echo isKinhDoanh() ? 'active-card' : ''; ?>" id="card-guests" onclick="setFilter('guests')" title="Bấm để lọc tất cả khách dự kiến">
                <h3>Khách dự kiến <?php echo isKinhDoanh() ? '(Phụ trách)' : ''; ?></h3>
                <div class="value" id="val-guests"><?php echo $stats['guests']; ?></div>
            </div>
            <div class="card" id="card-matched" onclick="setFilter('matched')" title="Bấm để lọc khách đã Check-in hợp lệ">
                <h3>Đã Check-in (Khớp)</h3>
                <div class="value" id="val-checked-in" style="color: #2e7d32;"><?php echo $stats['checked_in']; ?></div>
            </div>
            <?php if(!isKinhDoanh()): ?>
            <div class="card" id="card-walk_in" onclick="setFilter('walk_in')" title="Bấm để lọc khách phát sinh">
                <h3>Khách phát sinh (Walk-in)</h3>
                <div class="value" id="val-walk-in" style="color: #ef6c00;"><?php echo $stats['walk_in']; ?></div>
            </div>
            <div class="card" id="card-unassigned" style="border-top-color: #c62828;" onclick="setFilter('unassigned')" title="Bấm để lọc khách chưa xếp bàn">
                <h3>Chưa xếp bàn</h3>
                <div class="value" id="val-unassigned" style="color: #c62828;"><?php echo $stats['unassigned']; ?></div>
            </div>
            <?php endif; ?>
            <div class="card" id="card-not_arrived" style="border-top-color: #1976d2;" onclick="setFilter('not_arrived')" title="Bấm để lọc khách dự kiến chưa tới">
                <h3>Khách chưa tới</h3>
                <div class="value" id="val-not-arrived" style="color: #1976d2;"><?php echo $stats['not_arrived']; ?></div>
            </div>
        </div>

        <!-- Sơ đồ trạng thái Khách theo Bàn (Real-time) - Hiển thị cho Admin, Lễ Tân và Kinh Doanh (bàn phụ trách) -->
        <div class="content-box" style="margin-top: 25px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0; color: #d32f2f; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <span>🪑</span> Sơ đồ trạng thái Khách theo Bàn (Real-time) <?php echo isKinhDoanh() ? '(Bàn Phụ Trách)' : ''; ?>
                </h3>
                <span style="font-size: 0.85rem; color: #666;">Bấm vào thẻ Bàn bên dưới để lọc xem danh sách khách của bàn đó</span>
            </div>
            <div id="tables-cards-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                <!-- Rendered dynamically by JavaScript -->
            </div>
        </div>

        <!-- Table & Guest Details Section -->
        <div class="recent-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 12px;">
                <h3 style="margin-bottom: 0;">Khách hàng & Lượt check-in bàn phụ trách</h3>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <select id="table-select-filter" onchange="setTableFilter(this.value)" style="padding: 7px 12px; border-radius: 6px; font-size: 0.88rem; border: 1.5px solid #d32f2f; background: #fff; color: #333; font-weight: bold; cursor: pointer; outline: none;">
                        <option value="all">🔍 Tất cả các Bàn</option>
                    </select>
                    <span id="realtime-status" style="font-size: 0.85rem; color: #2e7d32; font-weight: 500;">
                        🟢 Real-time (Mỗi 3s)
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Khách nhập</th>
                            <th>SĐT</th>
                            <th>Bàn</th>
                            <th>Hình thức Check-in</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
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

function setFilter(val) {
    currentFilter = val;
    
    // Highlight Card active
    document.querySelectorAll('.dashboard-cards .card').forEach(card => card.classList.remove('active-card'));
    const activeCard = document.getElementById('card-' + val);
    if (activeCard) activeCard.classList.add('active-card');
    
    updateRealtimeStats();
}

function setTableFilter(tableId) {
    selectedTableId = tableId;
    
    // Update select dropdown
    const select = document.getElementById('table-select-filter');
    if (select && select.value !== tableId) {
        select.value = tableId;
    }
    
    // Highlight table card
    document.querySelectorAll('.table-overview-card').forEach(card => card.classList.remove('active-table'));
    const activeTableCard = document.getElementById('table-card-' + tableId);
    if (activeTableCard) {
        activeTableCard.classList.add('active-table');
    }
    
    updateRealtimeStats();
}

let lastIndexDataHash = '';

async function updateRealtimeStats() {
    try {
        const response = await fetch(`../api/stats.php?filter=${encodeURIComponent(currentFilter)}&table_id=${encodeURIComponent(selectedTableId)}&_t=${Date.now()}`, { cache: 'no-store' });
        if (!response.ok) return;
        const textData = await response.text();

        if (textData === lastIndexDataHash) return;
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
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#777; padding:20px;">Không tìm thấy dữ liệu phù hợp với bộ lọc bàn hiện tại</td></tr>`;
                } else {
                    let html = '';
                    result.data.recent_checkins.slice(0, 150).forEach(item => {
                        const isCheckedIn = item.status === 'checked_in' || item.status === 'matched';
                        const rowClass = isCheckedIn ? 'row-checked-in' : '';
                        let badgeText = item.status_text;
                        if (isCheckedIn) badgeText = '✅ Đã checkin';
                        else if (item.status === 'walk_in') badgeText = '🔸 Phát sinh';
                        else if (item.status === 'invited') badgeText = '⏳ Chưa tới';

                        let methodBadge = `<span style="font-size:0.78rem; font-weight:bold; padding:3px 8px; border-radius:12px; background:#e8f5e9; color:#1b5e20; border:1px solid #c8e6c9;">📱 Khớp SĐT</span>`;
                        if (item.status === 'walk_in') {
                            methodBadge = `<span style="font-size:0.78rem; font-weight:bold; padding:3px 8px; border-radius:12px; background:#fff3e0; color:#ef6c00; border:1px solid #ffcc80;">🔸 Khách phát sinh</span>`;
                        } else if (item.is_by_code) {
                            methodBadge = `<span style="font-size:0.78rem; font-weight:bold; padding:3px 8px; border-radius:12px; background:#f3e5f5; color:#7b1fa2; border:1px solid #ab47bc;">🎟️ Khớp Mã dự thưởng</span>`;
                        } else if (item.status === 'invited') {
                            methodBadge = `<span style="font-size:0.78rem; color:#888;">-</span>`;
                        }

                        html += `
                            <tr class="${rowClass}">
                                <td>${item.full_name}</td>
                                <td>${item.phone}</td>
                                <td><strong>${item.table_name}</strong></td>
                                <td>${methodBadge}</td>
                                <td>${item.time}</td>
                                <td>
                                    <span class="badge ${item.status}">${badgeText}</span>
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
        <div class="table-overview-card ${isAllActive}" id="table-card-all" onclick="setTableFilter('all')">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <strong style="font-size:0.95rem; color:#333;">🔍 Tất cả các Bàn</strong>
            </div>
            <div style="font-size:0.8rem; color:#666;">Bấm để xem danh sách tổng hợp</div>
        </div>
    `;
    
    if (!tables || tables.length === 0) {
        html += `
            <div style="grid-column: 1 / -1; padding: 12px 15px; background: #fff3e0; color: #e65100; border-radius: 8px; font-size: 0.85rem; border: 1px dashed #ffb74d;">
                ⚠️ Hiện chưa có bàn nào được phân công phụ trách.
            </div>
        `;
    } else {
        tables.forEach(t => {
            const isActive = String(selectedTableId) === String(t.id) ? 'active-table' : '';
            const pct = t.total_guests > 0 ? Math.round((t.arrived_guests / t.total_guests) * 100) : 0;
            
            html += `
                <div class="table-overview-card ${isActive}" id="table-card-${t.id}" onclick="setTableFilter('${t.id}')">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <strong style="font-size:0.95rem; color:#d32f2f;">🪑 ${t.table_name}</strong>
                        <span style="font-size:0.78rem; background:#ffebee; color:#d32f2f; padding:2px 6px; border-radius:4px; font-weight:bold;">${t.arrived_guests}/${t.total_guests}</span>
                    </div>
                    <div style="font-size:0.78rem; color:#666; margin-bottom:6px;">
                        💼 ${t.assigned_user_name}
                    </div>
                    <div style="display:flex; gap:6px; font-size:0.75rem; font-weight:600; margin-bottom:6px;">
                        <span style="color:#2e7d32;">✅ ${t.arrived_guests} Đã tới</span>
                        <span style="color:#1565c0;">⏳ ${t.not_arrived_guests} Chưa tới</span>
                    </div>
                    <div style="background:#e0e0e0; height:6px; border-radius:3px; overflow:hidden;">
                        <div style="background:#2e7d32; width:${pct}%; height:100%; transition:width 0.3s ease;"></div>
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
    
    let options = `<option value="all">🔍 Tất cả các Bàn</option>`;
    if (tables && tables.length > 0) {
        tables.forEach(t => {
            options += `<option value="${t.id}">🪑 ${t.table_name} (${t.arrived_guests}/${t.total_guests} đã tới)</option>`;
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
