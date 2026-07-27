<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();

    $filter = $_GET['filter'] ?? 'all';
    $recentCheckins = [];

    $userId = $_SESSION['admin_id'] ?? 0;

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

    if ($filter === 'not_arrived' || $filter === 'guests') {
        $whereClause = "";
        if ($filter === 'not_arrived') {
            $whereClause = isKinhDoanh() ? "WHERE g.status = 'invited' AND t.assigned_user_id = {$userId}" : "WHERE g.status = 'invited'";
        } else {
            $whereClause = isKinhDoanh() ? "WHERE t.assigned_user_id = {$userId}" : "";
        }

        $limitSql = ($filter === 'all') ? "LIMIT 10" : "";
        $stmtGuests = $db->query("
            SELECT g.*, t.table_name 
            FROM guests g 
            LEFT JOIN event_tables t ON g.table_id = t.id 
            {$whereClause}
            ORDER BY g.id DESC {$limitSql}
        ");
        while ($row = $stmtGuests->fetch()) {
            $recentCheckins[] = [
                'id'          => $row['id'],
                'full_name'   => esc($row['full_name']),
                'phone'       => esc($row['phone']),
                'table_name'  => esc($row['table_name'] ?? 'Chưa xếp bàn'),
                'time'        => $row['status'] === 'checked_in' ? '✅ Đã checkin' : '⏳ Chưa tới',
                'status'      => $row['status'],
                'status_text' => $row['status'] === 'checked_in' ? 'Đã checkin' : 'Chưa tới',
            ];
        }
    } else {
        $whereConditions = [];
        if ($filter === 'unassigned') {
            $whereConditions[] = "(c.table_id IS NULL OR c.table_id = 0)";
        } elseif ($filter === 'assigned') {
            $whereConditions[] = "(c.table_id IS NOT NULL AND c.table_id > 0)";
        } elseif ($filter === 'matched') {
            $whereConditions[] = "c.match_status = 'matched'";
        } elseif ($filter === 'walk_in') {
            $whereConditions[] = "c.match_status = 'walk_in'";
        }
        if (isKinhDoanh()) {
            $whereConditions[] = "t.assigned_user_id = {$userId}";
        }

        $whereClause = "";
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }

        $limitSql = ($filter === 'all') ? "LIMIT 10" : "";
        $recentStmt = $db->query("
            SELECT c.*, t.table_name 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            {$whereClause}
            ORDER BY c.checkin_time DESC {$limitSql}
        ");

        while ($row = $recentStmt->fetch()) {
            $recentCheckins[] = [
                'id'          => $row['id'],
                'full_name'   => esc($row['full_name_entered']),
                'phone'       => esc($row['phone_entered']),
                'table_name'  => esc($row['table_name'] ?? 'Chưa xếp bàn'),
                'time'        => date('d/m/Y H:i:s', strtotime($row['checkin_time'])),
                'status'      => esc($row['match_status']),
                'status_text' => $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh',
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'stats'           => $stats,
            'recent_checkins' => $recentCheckins
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
