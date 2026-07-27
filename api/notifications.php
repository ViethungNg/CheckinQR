<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();
    $userId = $_SESSION['admin_id'] ?? 0;
    
    $action = $_GET['action'] ?? 'check';
    
    if ($action === 'mark_read') {
        $lastId = (int)($_POST['last_id'] ?? $_GET['last_id'] ?? 0);
        if ($lastId > 0) {
            $_SESSION['last_seen_checkin_id'] = $lastId;
        } else {
            // Lấy id mới nhất đặt làm last_seen
            $whereKD = isKinhDoanh() ? "WHERE t.assigned_user_id = {$userId}" : "";
            $maxId = (int)$db->query("SELECT MAX(c.id) FROM checkins c LEFT JOIN event_tables t ON c.table_id = t.id {$whereKD}")->fetchColumn();
            $_SESSION['last_seen_checkin_id'] = $maxId;
        }
        echo json_encode(['status' => 'success', 'last_seen_id' => $_SESSION['last_seen_checkin_id'] ?? 0]);
        exit;
    }

    $clientLastId = (int)($_GET['last_id'] ?? 0);
    $lastSeenId = (int)($_SESSION['last_seen_checkin_id'] ?? 0);

    $whereConditions = [];
    if (isKinhDoanh()) {
        $whereConditions[] = "t.assigned_user_id = {$userId}";
    }

    $whereClauseMax = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    $stmtMax = $db->query("
        SELECT MAX(c.id) 
        FROM checkins c 
        LEFT JOIN event_tables t ON c.table_id = t.id 
        {$whereClauseMax}
    ");
    $maxId = (int)$stmtMax->fetchColumn();

    // Nếu vừa đăng nhập lần đầu và chưa có session last_seen_checkin_id
    if ($lastSeenId === 0 && $maxId > 0) {
        $lastSeenId = $maxId;
        $_SESSION['last_seen_checkin_id'] = $maxId;
    }

    $whereClauseList = $whereConditions;
    if ($clientLastId > 0) {
        $whereClauseList[] = "c.id > {$clientLastId}";
    }
    
    $whereSql = !empty($whereClauseList) ? "WHERE " . implode(" AND ", $whereClauseList) : "";
    
    // Nếu lấy danh sách mới theo polling clientLastId > 0
    if ($clientLastId > 0) {
        $stmtCheckins = $db->query("
            SELECT c.*, t.table_name 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            {$whereSql}
            ORDER BY c.id DESC LIMIT 15
        ");
    } else {
        // Lấy 10 lượt checkin gần nhất để hiển thị dropdown ban đầu
        $stmtCheckins = $db->query("
            SELECT c.*, t.table_name 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            {$whereClauseMax}
            ORDER BY c.id DESC LIMIT 10
        ");
    }

    $checkinsList = [];
    while ($row = $stmtCheckins->fetch()) {
        $checkinsList[] = [
            'id'          => (int)$row['id'],
            'full_name'   => esc($row['full_name_entered']),
            'phone'       => esc($row['phone_entered']),
            'table_name'  => esc($row['table_name'] ?? 'Chưa xếp bàn'),
            'time'        => date('H:i:s d/m/Y', strtotime($row['checkin_time'])),
            'status'      => esc($row['match_status']),
            'status_text' => $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh',
            'is_new'      => ((int)$row['id'] > $lastSeenId)
        ];
    }

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
        'checkins'     => $checkinsList
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
