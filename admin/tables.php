<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

$db = Database::getConnection();

$message = '';
$error = '';
if (isPost() && isAdmin()) {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $eventId = (int)$_POST['event_id'];
        $tableName = trim($_POST['table_name'] ?? '');
        $tableCode = trim($_POST['table_code'] ?? '');
        $capacity = (int)$_POST['capacity'];
        $location = trim($_POST['location'] ?? '');
        $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        
        if (empty($tableName) || empty($eventId)) {
            $error = 'Vui lòng chọn sự kiện và nhập tên bàn';
        } else {
            try {
                if ($sortOrder <= 0) {
                    $stmtMax = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM event_tables WHERE event_id = ?");
                    $stmtMax->execute([$eventId]);
                    $sortOrder = (int)$stmtMax->fetchColumn();
                }

                $stmt = $db->prepare("INSERT INTO event_tables (event_id, table_name, table_code, capacity, location, assigned_user_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$eventId, $tableName, $tableCode, $capacity, $location, $assignedUserId, $sortOrder]);
                $message = 'Thêm bàn thành công!';
            } catch(PDOException $e) {
                $error = 'Lỗi thêm bàn (Có thể trùng mã bàn).';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $eventId = (int)$_POST['event_id'];
        $tableName = trim($_POST['table_name'] ?? '');
        $tableCode = trim($_POST['table_code'] ?? '');
        $capacity = (int)$_POST['capacity'];
        $location = trim($_POST['location'] ?? '');
        $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        
        try {
            $stmt = $db->prepare("UPDATE event_tables SET event_id=?, table_name=?, table_code=?, capacity=?, location=?, assigned_user_id=?, sort_order=? WHERE id=?");
            $stmt->execute([$eventId, $tableName, $tableCode, $capacity, $location, $assignedUserId, $sortOrder, $id]);
            $message = 'Cập nhật bàn thành công!';
        } catch(PDOException $e) {
            $error = 'Lỗi cập nhật bàn.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM event_tables WHERE id=?");
        $stmt->execute([$id]);
        $message = 'Xóa bàn thành công!';
    }
}

// Lấy danh sách sự kiện
$events = $db->query("SELECT id, event_name FROM events ORDER BY id DESC")->fetchAll();

// Lấy danh sách tài khoản để phân công phụ trách
$salesUsers = $db->query("SELECT id, full_name, username, role FROM users WHERE status = 'active' ORDER BY role ASC, full_name ASC")->fetchAll();

// Trích xuất danh sách bàn (nếu Kinh doanh -> chỉ lấy bàn mình phụ trách)
$whereTables = "";
$paramsTables = [];
if (isKinhDoanh()) {
    $whereTables = "WHERE t.assigned_user_id = ?";
    $paramsTables = [$_SESSION['admin_id']];
}

$stmtTables = $db->prepare("
    SELECT t.*, e.event_name, u.full_name as assigned_user_name, u.username as assigned_username,
    (SELECT COUNT(*) FROM guests WHERE table_id = t.id) as current_guests,
    (SELECT COUNT(*) FROM checkins WHERE table_id = t.id) as actual_checkins
    FROM event_tables t 
    LEFT JOIN events e ON t.event_id = e.id 
    LEFT JOIN users u ON t.assigned_user_id = u.id
    {$whereTables}
    ORDER BY t.sort_order ASC, t.id ASC
");
$stmtTables->execute($paramsTables);
$tables = $stmtTables->fetchAll();

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'tables'  => $tables
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=0.5, user-scalable=yes, viewport-fit=cover">
    <title>PMT - Checkin - Quản lý bàn sự kiện</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin-polish.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-polish.css'); ?>">
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header h1 { font-size: 1.8rem; }
        .content-box { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary-color); color: #fff; }
        .btn-success { background: #388e3c; color: #fff; }
        .btn-danger { background: #d32f2f; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: 600; color: #555; background: #fafafa; }
        
        /* Modal CSS */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border-radius: 8px; width: 480px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; }

        /* 2-Tab Switcher Navigation */
        .tables-tabs-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .tables-tab-btn {
            padding: 11px 22px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tables-tab-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .tables-tab-btn.active {
            background: #ffffff;
            color: #d32f2f;
            border-color: #cbd5e1;
            border-top: 3px solid #d32f2f;
            border-bottom: 2px solid #ffffff;
            margin-bottom: -2px;
            box-shadow: 0 -3px 8px rgba(0,0,0,0.04);
        }

        .tables-tab-content {
            display: none;
        }

        .tables-tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
            <h1>Quản lý Bàn Sự kiện <?php echo isKinhDoanh() ? '(Đang phụ trách)' : ''; ?></h1>
            <span id="realtime-status" style="font-size: 0.85rem; color: #2e7d32; font-weight: 500;">
                🟢 Real-time (Mỗi 2s)
            </span>
        </div>
        
        <?php if ($message): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>, 'success'); });</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>, 'error'); });</script>
        <?php endif; ?>

        <!-- Tab Navigation Bar -->
        <div class="tables-tabs-nav">
            <button type="button" class="tables-tab-btn active" id="tab-btn-floorplan" onclick="switchTablesTab('floorplan')">
                📊 Sơ Đồ Trạng Thái Bàn & Bộ Lọc
            </button>
            <button type="button" class="tables-tab-btn" id="tab-btn-config" onclick="switchTablesTab('config')">
                ⚙️ Cấu Hình Thông Tin Bàn
            </button>
        </div>

        <!-- TAB 1: Sơ Đồ Trạng Thái Bàn & Bộ Lọc Thống Kê Realtime -->
        <div id="tab-content-floorplan" class="tables-tab-content active">
            <!-- Real-time Floorplan Grid Cards -->
            <div class="table-floorplan-section" style="margin-bottom: 20px;">
                <div class="floorplan-header">
                    <div class="floorplan-title">
                        Sơ Đồ Trạng Thái Bàn (Real-time) <?php echo isKinhDoanh() ? '(Bàn Phụ Trách)' : ''; ?>
                    </div>
                    <div class="floorplan-hint">
                        Bấm vào thẻ Bàn để lọc xem danh sách chi tiết
                    </div>
                </div>
                <div id="tables-cards-container" class="tables-grid-cards">
                    <!-- Dynamically rendered via Javascript -->
                </div>
            </div>

            <!-- Smart Filter Toolbar & Guest Checkin Table Section -->
            <div class="dashboard-table-card">
                <div class="table-filter-toolbar admin-filter-bar">
                    <div class="table-toolbar-left">
                        <div class="dashboard-search-box" style="position: relative;">
                            <input type="text" id="dashboard-search-input" placeholder="Tìm theo tên, SĐT, đơn vị hoặc tên bàn..." oninput="handleSearchInput(this.value)" style="padding-right: 65px;" autocomplete="off">
                            <span class="kbd-badge hide-mobile" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">Ctrl K</span>
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

                <?php 
                $dashCols = getTableColumnsConfig('dashboard'); 
                $tableTitle = 'Bảng Thống Kê Realtime Bàn Tiệc & Khách Mời';
                require __DIR__ . '/../includes/table_toolbar.php';
                ?>
                <div class="table-responsive excel-table-container">
                    <div class="excel-zoom-wrapper">
                        <table class="excel-table modern-data-table">
                        <thead>
                            <tr>
                                <?php foreach ($dashCols as $c): ?>
                                    <?php if (!empty($c['visible'])): ?>
                                         <th class="col-<?php echo esc($c['key']); ?>"><?php echo str_replace(' / ', ' /<br>', esc($c['label'])); ?></th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="recent-checkins-body">
                            <!-- Loaded dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>

        <!-- TAB 2: Cấu Hình Thông Tin Bàn (CRUD Table - Ảnh 1) -->
        <div id="tab-content-config" class="tables-tab-content">
            <div class="content-box">
                <div class="admin-filter-bar tables-config-filter-bar" style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                    <?php if(isAdmin()): ?>
                        <button class="btn btn-primary btn-inline-header" onclick="openAddModal()">+ Thêm bàn mới</button>
                    <?php endif; ?>
                    <div style="position: relative; flex: 1; max-width: 320px;">
                        <input type="text" id="table-search-input" placeholder="Tìm theo tên bàn, mã bàn, người phụ trách..." class="form-control" style="padding-right: 65px;" oninput="filterTablesDOM(this.value)">
                        <span class="kbd-badge hide-mobile" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">Ctrl K</span>
                    </div>
                </div>
                <?php 
                $tableTitle = 'Sơ Đồ Bàn Tiệc & Sức Chứa (' . count($tables) . ')';
                require __DIR__ . '/../includes/table_toolbar.php';
                ?>
                <div class="table-responsive excel-table-container mobile-card-container">
                    <div class="excel-zoom-wrapper">
                        <table class="excel-table mobile-card-table">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">Thứ tự</th>
                                <th>Tên bàn</th>
                                <th>Mã bàn</th>
                                <th>Sự kiện</th>
                                <th>Người phụ trách</th>
                                <th>Sức chứa</th>
                                <th>Đã xếp (Dự kiến)</th>
                                <th>Đã vào bàn (Thực tế)</th>
                                <th>Vị trí</th>
                                <?php if(isAdmin()): ?><th>Thao tác</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablesTableBody">
                            <?php foreach($tables as $t): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <span style="font-weight: bold; color: #d32f2f; background: #ffebee; padding: 3px 10px; border-radius: 6px;">
                                        <?php echo esc($t['sort_order']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo esc($t['table_name']); ?></strong></td>
                                <td><?php echo esc($t['table_code']); ?></td>
                                <td><?php echo esc($t['event_name']); ?></td>
                                <td>
                                    <?php if(!empty($t['assigned_user_name'])): ?>
                                        <span style="background: #fff3e0; color: #e65100; padding: 3px 8px; border-radius: 4px; font-weight: 500;">
                                            💼 <?php echo esc($t['assigned_user_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #aaa;">Chưa phân công</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc($t['capacity']); ?> người</td>
                                <td style="color: <?php echo $t['current_guests'] > $t['capacity'] ? 'red' : '#1565c0'; ?>; font-weight: bold;">
                                    <?php echo esc($t['current_guests']); ?> / <?php echo esc($t['capacity']); ?>
                                </td>
                                <td style="color: <?php echo $t['actual_checkins'] > $t['capacity'] ? 'red' : '#2e7d32'; ?>; font-weight: bold;">
                                    <?php echo esc($t['actual_checkins']); ?> / <?php echo esc($t['capacity']); ?>
                                </td>
                                <td><?php echo esc($t['location'] ?? '-'); ?></td>
                                <?php if(isAdmin()): ?>
                                <td class="col-actions" data-label="Thao tác">
                                    <div class="action-btns-wrapper">
                                        <button type="button" class="btn btn-action-edit" onclick='openEditModal(<?php echo json_encode($t); ?>)'>Sửa</button>
                                        <form action="" method="POST" style="display:flex; flex:1 1 0; min-width:0; width:100%; margin:0;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa bàn này? Khách trong bàn sẽ bị mất vị trí.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn btn-action-delete" style="width:100%;">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div id="tableModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Thêm Bàn Mới</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="tableId" value="">
            
            <div class="form-group">
                <label>Sự kiện *</label>
                <select name="event_id" id="eventId" class="form-control" required>
                    <?php foreach($events as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo esc($e['event_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Thứ tự ưu tiên sắp xếp (Số nhỏ đứng trước: 1, 2, 3...)</label>
                <input type="number" name="sort_order" id="tableSortOrder" class="form-control" value="0" placeholder="Nhập số thứ tự (ví dụ: 1, 2, 3...)">
            </div>

            <div class="form-group">
                <label>Người phụ trách (Kinh doanh)</label>
                <select name="assigned_user_id" id="assignedUserId" class="form-control">
                    <option value="">-- Chưa phân công --</option>
                    <?php foreach($salesUsers as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo $u['role'] === 'kinhdoanh' ? '💼' : '👤'; ?> <?php echo esc($u['full_name']); ?> (<?php echo esc($u['username']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tên bàn *</label>
                <input type="text" name="table_name" id="tableName" class="form-control" required placeholder="Ví dụ: Bàn VIP 1">
            </div>

            <div class="form-group">
                <label>Mã bàn (Dùng import Excel)</label>
                <input type="text" name="table_code" id="tableCode" class="form-control" placeholder="Ví dụ: BAN1">
            </div>

            <div class="form-group">
                <label>Sức chứa (người)</label>
                <input type="number" name="capacity" id="tableCapacity" class="form-control" value="10" required>
            </div>

            <div class="form-group">
                <label>Vị trí</label>
                <input type="text" name="location" id="tableLocation" class="form-control" placeholder="Ví dụ: Gần sân khấu">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<script>
    let currentFilter = 'all';
    let selectedTableId = 'all';
    let currentSearch = '';
    let searchDebounceTimer = null;
    let lastIndexDataHash = '';
    const dashColsConfig = <?php echo json_encode($dashCols); ?>;

    // Switch Tables Tab
    function switchTablesTab(tabName) {
        const btnFloorplan = document.getElementById('tab-btn-floorplan');
        const btnConfig = document.getElementById('tab-btn-config');
        const contentFloorplan = document.getElementById('tab-content-floorplan');
        const contentConfig = document.getElementById('tab-content-config');

        if (tabName === 'config') {
            btnFloorplan.classList.remove('active');
            btnConfig.classList.add('active');
            contentFloorplan.classList.remove('active');
            contentConfig.classList.add('active');
            contentFloorplan.style.display = 'none';
            contentConfig.style.display = 'block';
            location.hash = '#config';
        } else {
            btnConfig.classList.remove('active');
            btnFloorplan.classList.add('active');
            contentConfig.classList.remove('active');
            contentFloorplan.classList.add('active');
            contentConfig.style.display = 'none';
            contentFloorplan.style.display = 'block';
            location.hash = '#floorplan';
        }

        if (window.initTableColumnResizers) {
            setTimeout(window.initTableColumnResizers, 100);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (location.hash === '#config') {
            switchTablesTab('config');
        } else {
            switchTablesTab('floorplan');
        }
    });

    // Realtime Floorplan & Table Stats Fetcher
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
                if (result.data.tables) {
                    renderTableCards(result.data.tables);
                    populateTableSelectOptions(result.data.tables);
                }

                const searchInput = document.getElementById('dashboard-search-input');
                const isUserTyping = searchInput && document.activeElement === searchInput;

                const tbody = document.getElementById('recent-checkins-body');
                if (tbody && result.data.recent_checkins && (!isUserTyping || forceRefresh)) {
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
                                        html += `<td class="col-customer_code">${customerCodeHtml}</td>`;
                                        break;
                                    case 'full_name':
                                        html += `<td class="col-full_name" style="font-weight:700; color:#0f172a;">${item.full_name}</td>`;
                                        break;
                                    case 'phone':
                                        html += `<td class="col-phone" style="font-weight:600; color:#475569;">${item.phone}</td>`;
                                        break;
                                    case 'organization':
                                        html += `<td class="col-organization">${item.organization || '-'}</td>`;
                                        break;
                                    case 'table_name':
                                        html += `<td class="col-table_name">${tableNameHtml}</td>`;
                                        break;
                                    case 'lucky_draw_code':
                                        const luckyCodeHtml = item.lucky_draw_code 
                                            ? `<span style="font-weight: 800; color: #6a1b9a; background: #f3e5f5; border: 1.5px solid #ba68c8; padding: 3px 8px; border-radius: 6px; font-size: 0.85rem;">${item.lucky_draw_code}</span>`
                                            : `<span style="color: #cbd5e1;">-</span>`;
                                        html += `<td class="col-lucky_draw_code">${luckyCodeHtml}</td>`;
                                        break;
                                    case 'checkin_time':
                                        html += `<td class="col-checkin_time" style="font-size:0.85rem; color:#64748b;">${item.time}</td>`;
                                        break;
                                    case 'status':
                                        html += `<td class="col-status"><span style="display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; ${badgeStyle}">${badgeText}</span></td>`;
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
                const rawStaff = (t.assigned_user_name || '').trim();
                const staffText = (!rawStaff || rawStaff === 'Chưa phân công') ? '' : rawStaff;
                const staffHtml = staffText 
                    ? `<span class="staff-prefix">Phụ trách: </span><span class="staff-name">${staffText}</span>` 
                    : '&nbsp;';

                html += `
                    <div class="table-card-v2 ${isActive}" id="table-card-${t.id}" onclick="setTableFilter('${t.id}')">
                        <div class="table-card-top">
                            <span class="table-name-badge">${t.table_name}</span>
                            <span class="table-ratio-badge">${t.arrived_guests}/${t.total_guests}</span>
                        </div>
                        <div class="table-staff-info">
                            ${staffHtml}
                        </div>
                        <div class="table-stats-row">
                            <span style="color:#10b981;">${t.arrived_guests} Đã tới</span>
                            <span style="color:#6366f1;">${t.not_arrived_guests} Chưa tới</span>
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

    function setTableFilter(tableId) {
        selectedTableId = tableId;
        const select = document.getElementById('table-select-filter');
        if (select && select.value !== tableId) {
            select.value = tableId;
        }
        document.querySelectorAll('.table-card-v2').forEach(card => card.classList.remove('active-table'));
        const activeTableCard = document.getElementById('table-card-' + tableId);
        if (activeTableCard) {
            activeTableCard.classList.add('active-table');
        }
        updateRealtimeStats(true);
    }

    function handleSearchInput(val) {
        filterDashboardTableDOM(val);
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(async () => {
            currentSearch = val.trim();
            await updateRealtimeStats(true);
            filterDashboardTableDOM(val);
        }, 400);
    }

    function filterDashboardTableDOM(query) {
        const q = (query || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#recent-checkins-body tr');
        rows.forEach(tr => {
            const text = tr.textContent.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Modal CRUD Bàn (Tab 2)
    const modal = document.getElementById('tableModal');
    let latestTablesData = <?php echo json_encode($tables); ?>;

    function getSuggestedSortOrder(eventId) {
        let maxOrder = 0;
        if (Array.isArray(latestTablesData)) {
            latestTablesData.forEach(t => {
                if (!eventId || parseInt(t.event_id) === parseInt(eventId)) {
                    const val = parseInt(t.sort_order) || 0;
                    if (val > maxOrder) maxOrder = val;
                }
            });
        }
        return maxOrder + 1;
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Bàn Mới';
        document.getElementById('formAction').value = 'add';
        document.getElementById('tableId').value = '';
        
        const selectedEventId = document.getElementById('eventId')?.value;
        document.getElementById('tableSortOrder').value = getSuggestedSortOrder(selectedEventId);

        document.getElementById('assignedUserId').value = '';
        document.getElementById('tableName').value = '';
        document.getElementById('tableCode').value = '';
        document.getElementById('tableCapacity').value = '10';
        document.getElementById('tableLocation').value = '';
        modal.style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const eventSelect = document.getElementById('eventId');
        if (eventSelect) {
            eventSelect.addEventListener('change', function() {
                if (document.getElementById('formAction')?.value === 'add') {
                    document.getElementById('tableSortOrder').value = getSuggestedSortOrder(this.value);
                }
            });
        }
    });
    
    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Sửa Bàn: ' + data.table_name;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('tableId').value = data.id;
        document.getElementById('eventId').value = data.event_id;
        document.getElementById('tableSortOrder').value = data.sort_order || '0';
        document.getElementById('assignedUserId').value = data.assigned_user_id || '';
        document.getElementById('tableName').value = data.table_name;
        document.getElementById('tableCode').value = data.table_code || '';
        document.getElementById('tableCapacity').value = data.capacity || '10';
        document.getElementById('tableLocation').value = data.location || '';
        modal.style.display = 'block';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }

    let isFetchingTables = false;
    const isAdminUser = <?php echo isAdmin() ? 'true' : 'false'; ?>;

    async function fetchRealtimeTables() {
        if (isFetchingTables) return;
        if (modal && getComputedStyle(modal).display !== 'none') return;

        isFetchingTables = true;
        try {
            const response = await fetch('tables.php?ajax=1', { cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            
            if (data && data.success && Array.isArray(data.tables)) {
                renderTablesRows(data.tables);
            }
        } catch (e) {
            console.error('Lỗi fetch realtime tables:', e);
        } finally {
            isFetchingTables = false;
        }
    }

    function escapeHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderTablesRows(tables) {
        latestTablesData = tables;
        const tbody = document.getElementById('tablesTableBody');
        if (!tbody) return;

        let html = '';
        tables.forEach(t => {
            const currentGuests = parseInt(t.current_guests) || 0;
            const actualCheckins = parseInt(t.actual_checkins) || 0;
            const capacity = parseInt(t.capacity) || 10;
            const locationText = t.location ? escapeHtml(t.location) : '-';

            let userBadge = '<span style="color: #aaa;">Chưa phân công</span>';
            if (t.assigned_user_name) {
                userBadge = `<span style="background: #fff3e0; color: #e65100; padding: 3px 8px; border-radius: 4px; font-weight: 500;">💼 ${escapeHtml(t.assigned_user_name)}</span>`;
            }

            const currentGuestsColor = currentGuests > capacity ? 'red' : '#1565c0';
            const actualCheckinsColor = actualCheckins > capacity ? 'red' : '#2e7d32';

            const jsonString = JSON.stringify(t).replace(/'/g, "&apos;");

            let actionTd = '';
            if (isAdminUser) {
                actionTd = `
                    <td>
                        <div class="action-btns-wrapper">
                            <button type="button" class="btn btn-action-edit" onclick='openEditModal(${jsonString})'>Sửa</button>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa bàn này? Khách trong bàn sẽ bị mất vị trí.');">
                                <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]')?.value || ''}">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="${t.id}">
                                <button type="submit" class="btn btn-action-delete">Xóa</button>
                            </form>
                        </div>
                    </td>
                `;
            }

            html += `
                <tr>
                    <td style="text-align: center;">
                        <span style="font-weight: bold; color: #d32f2f; background: #ffebee; padding: 3px 10px; border-radius: 6px;">
                            ${t.sort_order}
                        </span>
                    </td>
                    <td><strong>${escapeHtml(t.table_name)}</strong></td>
                    <td>${escapeHtml(t.table_code || '')}</td>
                    <td>${escapeHtml(t.event_name || '')}</td>
                    <td>${userBadge}</td>
                    <td>${capacity} người</td>
                    <td style="color: ${currentGuestsColor}; font-weight: bold;">
                        ${currentGuests} / ${capacity}
                    </td>
                    <td style="color: ${actualCheckinsColor}; font-weight: bold;">
                        ${actualCheckins} / ${capacity}
                    </td>
                    <td>${locationText}</td>
                    ${actionTd}
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function filterTablesDOM(query) {
        const q = (query || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#tablesTableBody tr');
        rows.forEach(tr => {
            const text = tr.textContent.toLowerCase();
            tr.style.display = text.includes(q) ? '' : 'none';
        });
    }

    // Initialize Realtime Polling
    updateRealtimeStats();
    setInterval(() => {
        updateRealtimeStats();
        fetchRealtimeTables();
    }, 3000);

    window.addEventListener('dbRealtimeChange', () => {
        updateRealtimeStats();
        fetchRealtimeTables();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateRealtimeStats();
            fetchRealtimeTables();
        }
    });

    // Shortcut Ctrl + K to focus search box
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            const configTabActive = document.getElementById('tab-btn-config')?.classList.contains('active');
            const targetInputId = configTabActive ? 'table-search-input' : 'dashboard-search-input';
            const searchInput = document.getElementById(targetInputId);
            if (searchInput) {
                searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                searchInput.focus();
                if (typeof searchInput.select === 'function') searchInput.select();
            }
        } else if (e.key === 'Escape') {
            const activeEl = document.activeElement;
            if (activeEl && (activeEl.id === 'dashboard-search-input' || activeEl.id === 'table-search-input')) {
                activeEl.blur();
            }
        }
    });
</script>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
