<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

// Xử lý thống kê cho dashboard
$stats = [
    'events' => 0,
    'guests' => 0,
    'checked_in' => 0,
    'walk_in' => 0
];

$stats['events'] = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stats['guests'] = $db->query("SELECT COUNT(*) FROM guests")->fetchColumn();
$stats['checked_in'] = $db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'matched'")->fetchColumn();
$stats['walk_in'] = $db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'walk_in'")->fetchColumn();

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
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; border-top: 4px solid var(--primary-color); }
        .card h3 { color: #777; font-size: 1rem; margin-bottom: 10px; font-weight: 500; }
        .card .value { font-size: 2.2rem; font-weight: bold; color: #333; }
        
        .recent-section { margin-top: 30px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .recent-section h3 { margin-bottom: 15px; color: var(--primary-color); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge.matched { background: #e8f5e9; color: #2e7d32; }
        .badge.walk_in { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <h2>CheckinQR</h2>
        <ul>
            <li><a href="index.php" class="active">Dashboard</a></li>
            <?php if(isAdmin()): ?>
            <li><a href="events.php">Quản lý sự kiện</a></li>
            <?php endif; ?>
            <li><a href="guests.php">Danh sách khách hàng dự kiến</a></li>
            <li><a href="checkins.php">Khách hàng đã checkin</a></li>
            <?php if(isAdmin()): ?>
            <li><a href="tables.php">Quản lý bàn</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Tổng quan 3.0</h1>
            <div class="user-info">
                <span>Xin chào, <strong><?php echo esc($_SESSION['admin_name']); ?></strong></span>
                <a href="logout.php" class="btn-logout">Đăng xuất</a>
            </div>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <h3>Tổng Sự kiện</h3>
                <div class="value" id="val-events"><?php echo $stats['events']; ?></div>
            </div>
            <div class="card">
                <h3>Khách dự kiến</h3>
                <div class="value" id="val-guests"><?php echo $stats['guests']; ?></div>
            </div>
            <div class="card">
                <h3>Đã Check-in (Khớp)</h3>
                <div class="value" id="val-checked-in" style="color: #2e7d32;"><?php echo $stats['checked_in']; ?></div>
            </div>
            <div class="card">
                <h3>Khách phát sinh (Walk-in)</h3>
                <div class="value" id="val-walk-in" style="color: #ef6c00;"><?php echo $stats['walk_in']; ?></div>
            </div>
            <div class="card" style="border-top-color: #c62828;">
                <h3>Chưa xếp bàn</h3>
                <div class="value" id="val-unassigned" style="color: #c62828;"><?php echo $db->query("SELECT COUNT(*) FROM checkins WHERE table_id IS NULL OR table_id = 0")->fetchColumn(); ?></div>
            </div>
        </div>

        <div class="recent-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin-bottom: 0;">Lượt check-in mới nhất</h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <select id="table-filter" onchange="updateFilter(this.value)" style="padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; border: 1px solid #d32f2f; background: #fff; color: #333; font-weight: bold; cursor: pointer;">
                        <option value="all">🔍 Tất cả lượt check-in</option>
                        <option value="unassigned">⚠️ Chỉ hiện Chưa xếp bàn</option>
                        <option value="assigned">✅ Chỉ hiện Đã xếp bàn</option>
                    </select>
                    <span id="realtime-status" style="font-size: 0.85rem; color: #2e7d32; font-weight: 500;">
                        🟢 Real-time (Mỗi 3s)
                    </span>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Khách nhập</th>
                        <th>SĐT</th>
                        <th>Bàn</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="recent-checkins-body">
                    <?php
                    $recentStmt = $db->query("
                        SELECT c.*, t.table_name 
                        FROM checkins c 
                        LEFT JOIN event_tables t ON c.table_id = t.id 
                        ORDER BY c.checkin_time DESC LIMIT 5
                    ");
                    while($row = $recentStmt->fetch()):
                    ?>
                    <tr>
                        <td><?php echo esc($row['full_name_entered']); ?></td>
                        <td><?php echo esc($row['phone_entered']); ?></td>
                        <td><strong><?php echo esc($row['table_name'] ?? 'Chưa xếp bàn'); ?></strong></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($row['checkin_time'])); ?></td>
                        <td>
                            <span class="badge <?php echo esc($row['match_status']); ?>">
                                <?php echo $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentFilter = 'all';

function updateFilter(val) {
    currentFilter = val;
    updateRealtimeStats();
}

async function updateRealtimeStats() {
    try {
        const response = await fetch('../api/stats.php?filter=' + currentFilter);
        if (!response.ok) return;
        const result = await response.json();
        
        if (result.status === 'success') {
            const data = result.data.stats;
            
            // Cập nhật số liệu
            document.getElementById('val-events').textContent = data.events;
            document.getElementById('val-guests').textContent = data.guests;
            document.getElementById('val-checked-in').textContent = data.checked_in;
            document.getElementById('val-walk-in').textContent = data.walk_in;
            if (document.getElementById('val-unassigned')) {
                document.getElementById('val-unassigned').textContent = data.unassigned;
            }
            
            // Cập nhật danh sách check-in
            const tbody = document.getElementById('recent-checkins-body');
            if (result.data.recent_checkins) {
                if (result.data.recent_checkins.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#777; padding:20px;">Không tìm thấy dữ liệu phù hợp với bộ lọc</td></tr>`;
                } else {
                    let html = '';
                    result.data.recent_checkins.slice(0, 5).forEach(item => {
                        html += `
                            <tr>
                                <td>${item.full_name}</td>
                                <td>${item.phone}</td>
                                <td><strong>${item.table_name}</strong></td>
                                <td>${item.time}</td>
                                <td>
                                    <span class="badge ${item.status}">${item.status_text}</span>
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

// Chạy tự động cập nhật mỗi 3 giây (3000ms)
setInterval(updateRealtimeStats, 3000);
</script>

<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
