<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();

    $filter = $_GET['filter'] ?? 'all';
    $whereClause = "";
    if ($filter === 'unassigned') {
        $whereClause = "WHERE c.table_id IS NULL OR c.table_id = 0";
    } elseif ($filter === 'assigned') {
        $whereClause = "WHERE c.table_id IS NOT NULL AND c.table_id > 0";
    }

    $stats = [
        'events'     => (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'guests'     => (int)$db->query("SELECT COUNT(*) FROM guests")->fetchColumn(),
        'checked_in' => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'matched'")->fetchColumn(),
        'walk_in'    => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE match_status = 'walk_in'")->fetchColumn(),
        'unassigned' => (int)$db->query("SELECT COUNT(*) FROM checkins WHERE table_id IS NULL OR table_id = 0")->fetchColumn(),
    ];

    $recentStmt = $db->query("
        SELECT c.*, t.table_name 
        FROM checkins c 
        LEFT JOIN event_tables t ON c.table_id = t.id 
        {$whereClause}
        ORDER BY c.checkin_time DESC LIMIT 10
    ");
    $recentCheckins = [];

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
