<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['admin_id'] ?? 0;
$userRole = $_SESSION['admin_role'] ?? 'staff';

// Gỡ phong tỏa session file trong PHP để tránh nghẽn luồng kết nối (CRITICAL for non-blocking PHP SSE)
session_write_close();

// Cấu hình Header cho Server-Sent Events (SSE)
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-transform, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$db = Database::getConnection();

/**
 * Hàm tính Hash trạng thái cơ sở dữ liệu (Database Mutation State Hash)
 */
function getDbStateHash($db) {
    try {
        $maxCheckinId    = $db->query("SELECT COALESCE(MAX(id), 0) FROM checkins")->fetchColumn();
        $checkinCount    = $db->query("SELECT COUNT(*) FROM checkins")->fetchColumn();
        $guestCount      = $db->query("SELECT COUNT(*) FROM guests")->fetchColumn();
        $tableCount      = $db->query("SELECT COUNT(*) FROM event_tables")->fetchColumn();
        $checkedInCount  = $db->query("SELECT COUNT(*) FROM guests WHERE status = 'checked_in'")->fetchColumn();
        $sumGuestIds     = $db->query("SELECT COALESCE(SUM(id), 0) FROM guests WHERE status = 'checked_in'")->fetchColumn();
        
        return md5("{$maxCheckinId}-{$checkinCount}-{$guestCount}-{$tableCount}-{$checkedInCount}-{$sumGuestIds}");
    } catch (\Throwable $e) {
        return md5((string)time());
    }
}

/**
 * Lấy snapshot dữ liệu mới nhất đẩy về client khi CSDL có phát sinh thay đổi
 */
function getDbSnapshot($db, $userId, $userRole) {
    // 1. Thống kê Dashboard
    if ($userRole === 'kinhdoanh') {
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
    
    // 2. Danh sách checkin mới nhất
    $stmtNotif = $db->query("SELECT id, full_name, phone, table_name, match_status, DATE_FORMAT(created_at, '%H:%i:%s') as checkin_time FROM checkins ORDER BY id DESC LIMIT 20");
    $recentCheckins = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

    // 3. Danh sách bàn tiệc
    $whereTables = $userRole === 'kinhdoanh' ? "WHERE t.assigned_user_id = {$userId}" : "";
    $stmtTables = $db->query("
        SELECT t.*, e.event_name, u.full_name as assigned_user_name,
        (SELECT COUNT(*) FROM guests WHERE table_id = t.id) as current_guests,
        (SELECT COUNT(*) FROM checkins WHERE table_id = t.id) as actual_checkins
        FROM event_tables t 
        LEFT JOIN events e ON t.event_id = e.id 
        LEFT JOIN users u ON t.assigned_user_id = u.id
        {$whereTables}
        ORDER BY t.sort_order ASC, t.id ASC
    ");
    $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

    return [
        'stats'           => $stats,
        'recent_checkins' => $recentCheckins,
        'tables'          => $tables,
        'timestamp'       => time()
    ];
}

// Thiết lập thời gian tự động kết nối lại nếu ngắt mạng (1000ms = 1s)
echo "retry: 1000\n\n";
@ob_flush();
@flush();

$lastHash = '';
$maxIterations = 60; // Chạy tối đa 30s cho 1 luồng HTTP rồi kết nối lại để đảm bảo tính ổn định trên Web Server Apache/XAMPP
$i = 0;

while ($i < $maxIterations) {
    if (connection_aborted()) {
        break;
    }

    $currentHash = getDbStateHash($db);
    if ($currentHash !== $lastHash) {
        $lastHash = $currentHash;
        $snapshot = getDbSnapshot($db, $userId, $userRole);
        $payload = [
            'type' => 'db_change',
            'hash' => $currentHash,
            'data' => $snapshot
        ];
        echo "data: " . json_encode($payload) . "\n\n";
        @ob_flush();
        @flush();
    } else {
        // Gửi Heartbeat mỗi 5s để giữ kết nối sống
        if ($i % 10 === 0) {
            echo ": heartbeat\n\n";
            @ob_flush();
            @flush();
        }
    }

    // Kiểm tra biến động CSDL mỗi 500ms (0.5 giây)
    usleep(500000);
    $i++;
}
