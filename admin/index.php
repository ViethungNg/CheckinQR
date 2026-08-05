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
    <title>PMT - Checkin - Dashboard Quản trị</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Dashboard Header Bar -->
        <div class="dash-header-bar">
            <div class="dash-page-title">
                📊 <span>Thống kê hiệu suất check-in & tham gia sự kiện</span>
            </div>
        </div>

        <!-- 6 Pastel Metric Cards Grid -->
        <div class="pastel-cards-grid-6">
            <!-- Card 1: Purple (Tổng lượt khách mời) -->
            <div class="stat-card-pastel card-pastel-purple" id="card-guests" onclick="setFilter('guests')" title="Bấm để xem tất cả khách dự kiến">
                <div class="pastel-card-title">Tổng số khách mời</div>
                <div class="pastel-card-value" id="val-guests"><?php echo $stats['guests']; ?></div>
                <div class="pastel-card-subtitle">Khách dự kiến tham gia</div>
            </div>

            <!-- Card 2: Green (Đã check-in) -->
            <div class="stat-card-pastel card-pastel-green" id="card-matched" onclick="setFilter('matched')" title="Bấm để xem khách đã check-in">
                <div class="pastel-card-title">Đã Check-in (Khớp)</div>
                <div class="pastel-card-value" id="val-checked-in"><?php echo $stats['checked_in']; ?></div>
                <div class="pastel-card-subtitle" id="val-attendance-rate-sub">Tỷ lệ có mặt: <?php echo $checkinRate; ?>%</div>
            </div>

            <!-- Card 3: Yellow (Bàn đã lấp đầy) -->
            <div class="stat-card-pastel card-pastel-yellow" onclick="scrollToFloorplan()" title="Số lượng bàn tiệc đã đạt 100% người tới">
                <div class="pastel-card-title">Bàn tiệc lấp đầy (100%)</div>
                <div class="pastel-card-value" id="val-full-tables">0 Bàn</div>
                <div class="pastel-card-subtitle">Đã đủ số người xếp bàn</div>
            </div>

            <!-- Card 4: Pink (Bàn đông khách nhất) -->
            <div class="stat-card-pastel card-pastel-pink" title="Bàn tiệc có lượt khách có mặt đông nhất">
                <div class="pastel-card-title">Bàn đông khách nhất</div>
                <div class="pastel-card-value" style="font-size: 1.25rem;" id="val-top-table">Chưa có</div>
                <div class="pastel-card-subtitle">Có lượt check-in cao nhất</div>
            </div>

            <!-- Card 5: Cyan (Khách chưa tới) -->
            <div class="stat-card-pastel card-pastel-cyan" id="card-not_arrived" onclick="setFilter('not_arrived')" title="Bấm để xem khách chưa tới">
                <div class="pastel-card-title">Khách chưa tới</div>
                <div class="pastel-card-value" id="val-not-arrived"><?php echo $stats['not_arrived']; ?></div>
                <div class="pastel-card-subtitle">Đang chờ tiếp đón tại sảnh</div>
            </div>

            <!-- Card 6: Coral (Khách phát sinh Walk-in) -->
            <div class="stat-card-pastel card-pastel-coral" id="card-walk_in" onclick="setFilter('walk_in')" title="Bấm để xem khách phát sinh">
                <div class="pastel-card-title">Khách phát sinh (Walk-in)</div>
                <div class="pastel-card-value" id="val-walk-in"><?php echo $stats['walk_in']; ?></div>
                <div class="pastel-card-subtitle">Đăng ký mới tại sự kiện</div>
            </div>
        </div>

        <!-- Combined Dashboard Charts Section -->
        <div class="dashboard-charts-grid">
            <!-- Left Chart: Column Bar Chart per Table -->
            <div class="chart-card-modern">
                <div class="chart-card-header">
                    <div class="chart-card-title">📊 Tiến độ có mặt theo từng Bàn tiệc</div>
                </div>
                <div class="chart-canvas-wrapper">
                    <canvas id="chart-table-occupancy"></canvas>
                </div>
            </div>

            <!-- Right Chart: Donut Chart combining Table Statuses -->
            <div class="chart-card-modern">
                <div class="chart-card-header">
                    <div class="chart-card-title">🍩 Trạng thái lấp đầy các Bàn tiệc</div>
                </div>
                <div class="chart-canvas-wrapper">
                    <canvas id="chart-status-distribution"></canvas>
                    <div class="donut-center-badge">
                        <div class="donut-center-pct" id="donut-center-pct"><?php echo $checkinRate; ?>%</div>
                        <div class="donut-center-label">Tỷ lệ có mặt</div>
                    </div>
                </div>
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
                        <div class="pulse-dot"></div> Real-time (2s)
                    </div>
                </div>
            </div>

            <?php $dashCols = getTableColumnsConfig('dashboard'); ?>
            <div class="table-responsive">
                <table class="modern-data-table">
                    <thead>
                        <tr>
                            <?php foreach ($dashCols as $c): ?>
                                <?php if (!empty($c['visible'])): ?>
                                    <th><?php echo esc($c['label']); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
const dashColsConfig = <?php echo json_encode($dashCols); ?>;

function setFilter(val) {
    currentFilter = val;
    
    // Highlight active card
    document.querySelectorAll('.dashboard-cards-grid .stat-card-modern').forEach(card => card.classList.remove('active-card'));
    const activeCard = document.getElementById('card-' + val);
    if (activeCard) activeCard.classList.add('active-card');
    
    updateRealtimeStats(true);
    if (window.scrollToTableSectionOnMobile) window.scrollToTableSectionOnMobile();
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
    if (window.scrollToTableSectionOnMobile) window.scrollToTableSectionOnMobile();
}

function handleSearchInput(val) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentSearch = val.trim();
        updateRealtimeStats(true);
    }, 300);
}

function scrollToFloorplan() {
    const sec = document.querySelector('.table-floorplan-section');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

let occupancyChartInstance = null;
let tableStatusDistChartInstance = null;

function renderCharts(charts) {
    if (!window.Chart) return;

    // 1. Column Bar Chart for Table Occupancy
    const occCanvas = document.getElementById('chart-table-occupancy');
    if (occCanvas && charts.table_occupancy) {
        const labels = charts.table_occupancy.map(item => item.name);
        const arrivedData = charts.table_occupancy.map(item => item.arrived);
        const totalData = charts.table_occupancy.map(item => item.total);

        if (occupancyChartInstance) {
            occupancyChartInstance.data.labels = labels;
            occupancyChartInstance.data.datasets[0].data = arrivedData;
            occupancyChartInstance.data.datasets[1].data = totalData;
            occupancyChartInstance.update();
        } else {
            const ctx = occCanvas.getContext('2d');
            occupancyChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Khách đã check-in',
                            data: arrivedData,
                            backgroundColor: '#10B981',
                            borderRadius: 6,
                            barPercentage: 0.6
                        },
                        {
                            label: 'Tổng số xếp bàn',
                            data: totalData,
                            backgroundColor: '#CBD5E1',
                            borderRadius: 6,
                            barPercentage: 0.6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
    }

    // 2. Donut Chart for Table Status Distribution (Combined with Floorplan)
    const distCanvas = document.getElementById('chart-status-distribution');
    if (distCanvas && charts.table_status_dist) {
        const dist = charts.table_status_dist;
        const labels = [
            '🟢 Bàn đủ 100% khách (' + (dist.full || 0) + ')',
            '🔵 Bàn đang đón khách (' + (dist.partial || 0) + ')',
            '🟡 Bàn chưa có khách (' + (dist.empty || 0) + ')',
            '🔴 Khách chưa xếp bàn (' + (dist.unassigned || 0) + ')'
        ];
        const values = [dist.full || 0, dist.partial || 0, dist.empty || 0, dist.unassigned || 0];

        if (tableStatusDistChartInstance) {
            tableStatusDistChartInstance.data.labels = labels;
            tableStatusDistChartInstance.data.datasets[0].data = values;
            tableStatusDistChartInstance.update();
        } else {
            const ctx = distCanvas.getContext('2d');
            tableStatusDistChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (evt, activeEls) => {
                        if (activeEls && activeEls.length > 0) {
                            scrollToFloorplan();
                        }
                    },
                    plugins: {
                        legend: { position: 'right' }
                    },
                    cutout: '70%'
                }
            });
        }
    }
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
            
            // Cập nhật số liệu trên 6 thẻ thống kê Pastel
            if (document.getElementById('val-events')) document.getElementById('val-events').textContent = data.events;
            if (document.getElementById('val-guests')) document.getElementById('val-guests').textContent = data.guests;
            if (document.getElementById('val-checked-in')) document.getElementById('val-checked-in').textContent = data.checked_in;
            if (document.getElementById('val-walk-in')) document.getElementById('val-walk-in').textContent = data.walk_in;
            if (document.getElementById('val-unassigned')) document.getElementById('val-unassigned').textContent = data.unassigned;
            if (document.getElementById('val-not-arrived')) document.getElementById('val-not-arrived').textContent = data.not_arrived;
            
            if (data.full_tables_str && document.getElementById('val-full-tables')) {
                document.getElementById('val-full-tables').textContent = data.full_tables_str;
            }
            if (data.top_table && document.getElementById('val-top-table')) {
                document.getElementById('val-top-table').textContent = data.top_table;
            }
            if (data.attendance_rate !== undefined) {
                if (document.getElementById('donut-center-pct')) document.getElementById('donut-center-pct').textContent = data.attendance_rate + '%';
                if (document.getElementById('val-attendance-rate-sub')) document.getElementById('val-attendance-rate-sub').textContent = 'Tỷ lệ có mặt: ' + data.attendance_rate + '%';
            }

            // Vẽ & Cập nhật Biểu đồ Chart.js Realtime (Cột & Tròn)
            if (result.data.charts) {
                renderCharts(result.data.charts);
            }
            
            // Render Sơ đồ trạng thái từng Bàn
            if (result.data.tables) {
                renderTableCards(result.data.tables);
                populateTableSelectOptions(result.data.tables);
            }

            // Cập nhật danh sách khách hàng / check-in
            const tbody = document.getElementById('recent-checkins-body');
            if (result.data.recent_checkins) {
                const visibleCount = dashColsConfig.filter(c => c.visible).length || 1;
                if (result.data.recent_checkins.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${visibleCount}" style="text-align:center; color:#94a3b8; padding:32px; font-size:0.95rem;">Không tìm thấy dữ liệu phù hợp với bộ lọc hiện tại</td></tr>`;
                } else {
                    let html = '';
                    result.data.recent_checkins.slice(0, 150).forEach(item => {
                        const isCheckedIn = item.status === 'checked_in' || item.status === 'matched';
                        const rowClass = isCheckedIn ? 'row-checked-in' : '';
                        
                        const customerCodeHtml = item.customer_code 
                            ? `<strong style="color: #0284c7; font-weight:700; font-size: 0.88rem;">${item.customer_code}</strong>`
                            : `<span style="color: #cbd5e1;">-</span>`;

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

                        const tableNameHtml = item.table_name && item.table_name !== 'Chưa xếp bàn'
                            ? `<span style="font-weight:700; color:#047857; background:#ecfdf5; border:1px solid #a7f3d0; padding:4px 10px; border-radius:8px; font-size:0.82rem;">${item.table_name}</span>`
                            : `<span style="color:#94a3b8; font-style:italic; font-size:0.85rem;">Chưa xếp</span>`;

                        html += `<tr class="${rowClass}">`;
                        dashColsConfig.forEach(col => {
                            if (!col.visible) return;
                            switch(col.key) {
                                case 'customer_code':
                                    html += `<td>${customerCodeHtml}</td>`;
                                    break;
                                case 'full_name':
                                    html += `<td style="font-weight:700; color:#0f172a;">${item.full_name}</td>`;
                                    break;
                                case 'phone':
                                    html += `<td style="font-weight:600; color:#475569;">${item.phone}</td>`;
                                    break;
                                case 'organization':
                                    html += `<td>${item.organization || '-'}</td>`;
                                    break;
                                case 'table_name':
                                    html += `<td>${tableNameHtml}</td>`;
                                    break;
                                case 'lucky_draw_code':
                                    const luckyCodeHtml = item.lucky_draw_code 
                                        ? `<span style="font-weight: 800; color: #6a1b9a; background: #f3e5f5; border: 1.5px solid #ba68c8; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">${item.lucky_draw_code}</span>`
                                        : `<span style="color: #cbd5e1;">-</span>`;
                                    html += `<td>${luckyCodeHtml}</td>`;
                                    break;
                                case 'checkin_time':
                                    html += `<td style="font-size:0.85rem; color:#64748b;">${item.time}</td>`;
                                    break;
                                case 'status':
                                    html += `<td><span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; ${badgeStyle}">${badgeText}</span></td>`;
                                    break;
                            }
                        });
                        html += `</tr>`;
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

// Chạy ngay khi tải trang và lắng nghe sự kiện SSE Push (0s) khi CSDL có phát sinh
updateRealtimeStats();
setInterval(updateRealtimeStats, 3000); // Polling dự phòng

window.addEventListener('dbRealtimeChange', (e) => {
    updateRealtimeStats();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        updateRealtimeStats();
    }
});
</script>

<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
