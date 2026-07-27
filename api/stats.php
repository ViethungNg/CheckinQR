<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();

    $filter = $_GET['filter'] ?? 'all';
    $recentCheckins = [];

    $stats = [
        'events'      => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'guests'      => (int)$db->query("SELECT COUNT(*) FROM guests")->fetchColumn(),
        'checked_in'  => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'matched'")->fetchColumn(),
        'walk_in'     => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'walk_in'")->fetchColumn(),
        'unassigned'  => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE table_id IS NULL OR table_id = 0")->fetchColumn(),
        'not_arrived' => (int)$db->query("SELECT COUNT(*) FROM guests WHERE status = 'invited'")->fetchColumn(),
    ];

    if ($filter === 'not_arrived') {
        $stmtNotArrived = $db->query("
            SELECT g.*, t.table_name 
            FROM guests g 
            LEFT JOIN event_tables t ON g.table_id = t.id 
            WHERE g.status = 'invited' 
            ORDER BY g.id DESC LIMIT 10
        ");
        while ($row = $stmtNotArrived->fetch()) {
            $recentCheckins[] = [
                'id'          => $row['id'],
                'full_name'   => esc($row['full_name']),
                'phone'       => esc($row['phone']),
                'table_name'  => esc($row['table_name'] ?? 'Chưa xếp bàn'),
                'time'        => '⏳ Chưa tới',
                'status'      => 'invited',
                'status_text' => 'Chưa tới',
            ];
        }
    } else {
        $whereClause = "";
        if ($filter === 'unassigned') {
            $whereClause = "WHERE c.table_id IS NULL OR c.table_id = 0";
        } elseif ($filter === 'assigned') {
            $whereClause = "WHERE c.table_id IS NOT NULL AND c.table_id > 0";
        } elseif ($filter === 'matched') {
            $whereClause = "WHERE c.match_status = 'matched'";
        } elseif ($filter === 'walk_in') {
            $whereClause = "WHERE c.match_status = 'walk_in'";
        }

        $recentStmt = $db->query("
            SELECT c.*, t.table_name 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            {$whereClause}
            ORDER BY c.checkin_time DESC LIMIT 10
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
