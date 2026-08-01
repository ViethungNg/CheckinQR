<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

try {
    $db = Database::getConnection();
    $userId = $_SESSION['admin_id'] ?? 0;
    
    $action = $_GET['action'] ?? 'check';
    
    if ($action === 'mark_read') {
        $lastId = (int)($_POST['last_id'] ?? $_GET['last_id'] ?? 0);
        if ($lastId <= 0) {
            $whereKD = isKinhDoanh() ? "WHERE t.assigned_user_id = {$userId}" : "";
            $lastId = (int)$db->query("SELECT COALESCE(MAX(c.id), 0) FROM checkins c LEFT JOIN event_tables t ON c.table_id = t.id {$whereKD}")->fetchColumn();
        }
        $_SESSION['last_seen_checkin_id'] = $lastId;
        echo json_encode(['status' => 'success', 'last_seen_id' => $lastId]);
        exit;
    }

    $clientSinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : null;
    $lastSeenId = (int)($_SESSION['last_seen_checkin_id'] ?? 0);

    $whereConditions = [];
    if (isKinhDoanh()) {
        $whereConditions[] = "(t.assigned_user_id = {$userId} OR c.table_id IS NULL OR c.match_status = 'walk_in')";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Max ID hiện tại trong hệ thống
    $stmtMax = $db->query("
        SELECT COALESCE(MAX(c.id), 0) 
        FROM checkins c 
        LEFT JOIN event_tables t ON c.table_id = t.id 
        {$whereClause}
    ");
    $maxId = (int)$stmtMax->fetchColumn();

    // Lần đầu vào phiên làm việc
    if ($lastSeenId === 0 && $maxId > 0) {
        $lastSeenId = $maxId;
        $_SESSION['last_seen_checkin_id'] = $maxId;
    }

    // 1. Luôn lấy 15 lượt checkin mới nhất để hiển thị nội dung trong Dropdown
    $stmtCheckins = $db->query("
        SELECT c.*, 
               t.table_name,
               COALESCE(NULLIF(c.customer_code, ''), g.customer_code, '') as final_customer_code,
               COALESCE(NULLIF(g.organization, ''), NULLIF(c.address_entered, ''), '') as final_organization,
               COALESCE(NULLIF(c.lucky_draw_code, ''), g.lucky_draw_code, '') as final_lucky_code
        FROM checkins c 
        LEFT JOIN event_tables t ON c.table_id = t.id 
        LEFT JOIN guests g ON c.guest_id = g.id
        {$whereClause}
        ORDER BY c.id DESC LIMIT 15
    ");

    $checkinsList = [];
    while ($row = $stmtCheckins->fetch()) {
        $checkinId = (int)$row['id'];
        $checkinsList[] = [
            'id'              => $checkinId,
            'customer_code'   => esc($row['final_customer_code']),
            'organization'    => esc($row['final_organization']),
            'full_name'       => esc($row['full_name_entered']),
            'phone'           => esc($row['phone_entered']),
            'table_name'      => esc($row['table_name'] ?? 'Chưa xếp bàn'),
            'lucky_draw_code' => esc($row['final_lucky_code']),
            'time'            => date('H:i:s d/m/Y', strtotime($row['checkin_time'])),
            'created_at_ts'   => strtotime($row['checkin_time']),
            'status'          => esc($row['match_status']),
            'status_text'     => $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh',
            'is_new'          => ($lastSeenId > 0 && $checkinId > $lastSeenId)
        ];
    }

    // 2. Danh sách các lượt checkin thực sự MỚI kể từ clientSinceId
    $newItems = [];
    if ($clientSinceId !== null && $clientSinceId > 0 && $maxId > $clientSinceId) {
        $whereNew = $whereConditions;
        $whereNew[] = "c.id > {$clientSinceId}";
        $clauseNew = "WHERE " . implode(" AND ", $whereNew);
        $stmtNew = $db->query("
            SELECT c.*, 
                   t.table_name,
                   COALESCE(NULLIF(c.customer_code, ''), g.customer_code, '') as final_customer_code,
                   COALESCE(NULLIF(g.organization, ''), NULLIF(c.address_entered, ''), '') as final_organization,
                   COALESCE(NULLIF(c.lucky_draw_code, ''), g.lucky_draw_code, '') as final_lucky_code
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            LEFT JOIN guests g ON c.guest_id = g.id
            {$clauseNew}
            ORDER BY c.id ASC
        ");
        while ($row = $stmtNew->fetch()) {
            $newItems[] = [
                'id'              => (int)$row['id'],
                'customer_code'   => esc($row['final_customer_code']),
                'organization'    => esc($row['final_organization']),
                'full_name'       => esc($row['full_name_entered']),
                'phone'           => esc($row['phone_entered']),
                'table_name'      => esc($row['table_name'] ?? 'Chưa xếp bàn'),
                'lucky_draw_code' => esc($row['final_lucky_code']),
                'time'            => date('H:i:s d/m/Y', strtotime($row['checkin_time'])),
                'status'          => esc($row['match_status']),
                'status_text'     => $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh'
            ];
        }
    }

    // 3. Số lượng lượt checkin chưa đọc
    $unreadCount = 0;
    if ($lastSeenId > 0) {
        $unreadWhere = $whereConditions;
        $unreadWhere[] = "c.id > {$lastSeenId}";
        $unreadClause = "WHERE " . implode(" AND ", $unreadWhere);
        $stmtUnread = $db->query("
            SELECT COUNT(*) 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            {$unreadClause}
        ");
        $unreadCount = (int)$stmtUnread->fetchColumn();
    }

    echo json_encode([
        'status'       => 'success',
        'max_id'       => $maxId,
        'unread_count' => $unreadCount,
        'checkins'     => $checkinsList,
        'new_items'    => $newItems
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
