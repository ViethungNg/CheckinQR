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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT - Checkin - Quản lý bàn</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
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
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
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

        <div class="content-box">
            <?php if(isAdmin()): ?>
            <div style="margin-bottom: 15px;">
                <button class="btn btn-primary" onclick="openAddModal()">+ Thêm bàn mới</button>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table>
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
                            <td>
                                <div class="action-btns-wrapper">
                                    <button type="button" class="btn btn-action-edit" onclick='openEditModal(<?php echo json_encode($t); ?>)'>Sửa</button>
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirmModal(event, 'Bạn có chắc chắn muốn xóa bàn này? Khách trong bàn sẽ bị mất vị trí.');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-action-delete">Xóa</button>
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

    // Lắng nghe sự kiện SSE Push (0s) khi CSDL có biến động
    window.addEventListener('dbRealtimeChange', (e) => {
        const data = e.detail;
        if (data && Array.isArray(data.tables)) {
            renderTablesRows(data.tables);
        } else {
            fetchRealtimeTables();
        }
    });

    setInterval(fetchRealtimeTables, 3000); // Polling dự phòng
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchRealtimeTables();
        }
    });
</script>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
