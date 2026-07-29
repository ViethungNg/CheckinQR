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

    $filter = $_GET['filter'] ?? 'all';
    $tableId = $_GET['table_id'] ?? 'all';
    $searchKeyword = trim($_GET['search'] ?? '');
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

    // Lấy danh sách các bàn + thống kê số khách đã tới / chưa tới theo từng bàn
    $tablesSummary = [];
    $whereTableKD = isKinhDoanh() ? "WHERE t.assigned_user_id = {$userId}" : "";
    $stmtTables = $db->query("
        SELECT t.id, t.table_name, t.table_code, t.capacity, u.full_name as assigned_user_name,
            (SELECT COUNT(*) FROM guests WHERE table_id = t.id) as total_guests,
            (SELECT COUNT(*) FROM guests WHERE table_id = t.id AND status = 'checked_in') as arrived_guests
        FROM event_tables t
        LEFT JOIN users u ON t.assigned_user_id = u.id
        {$whereTableKD}
        ORDER BY t.sort_order ASC, t.id ASC
    ");
    while ($t = $stmtTables->fetch()) {
        $tablesSummary[] = [
            'id'                 => (int)$t['id'],
            'table_name'         => esc($t['table_name']),
            'table_code'         => esc($t['table_code']),
            'capacity'           => (int)$t['capacity'],
            'assigned_user_name' => esc($t['assigned_user_name'] ?? 'Chưa phân công'),
            'total_guests'       => (int)$t['total_guests'],
            'arrived_guests'     => (int)$t['arrived_guests'],
            'not_arrived_guests' => (int)$t['total_guests'] - (int)$t['arrived_guests'],
        ];
    }

    // Xử lý bộ lọc bàn
    $tableFilterCondition = "";
    if ($tableId !== 'all' && $tableId !== '') {
        if ($tableId === 'unassigned') {
            $tableFilterCondition = "(g.table_id IS NULL OR g.table_id = 0)";
        } else {
            $cleanTableId = (int)$tableId;
            $tableFilterCondition = "g.table_id = {$cleanTableId}";
        }
    }

    if ($filter === 'not_arrived' || $filter === 'guests' || ($tableId !== 'all' && $tableId !== '')) {
        $whereConditions = [];
        $params = [];

        if ($filter === 'not_arrived') {
            $whereConditions[] = "g.status = 'invited'";
        } elseif ($filter === 'matched') {
            $whereConditions[] = "g.status = 'checked_in'";
        }

        if (isKinhDoanh()) {
            $whereConditions[] = "t.assigned_user_id = {$userId}";
        }

        if ($tableFilterCondition !== '') {
            $whereConditions[] = $tableFilterCondition;
        }

        if ($searchKeyword !== '') {
            $whereConditions[] = "(g.full_name LIKE ? OR g.phone LIKE ? OR t.table_name LIKE ?)";
            $likeStr = '%' . $searchKeyword . '%';
            $params = [$likeStr, $likeStr, $likeStr];
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        $limitSql = "LIMIT 150";
        $stmtGuests = $db->prepare("
            SELECT g.*, t.table_name 
            FROM guests g 
            LEFT JOIN event_tables t ON g.table_id = t.id 
            {$whereClause}
            ORDER BY g.id DESC {$limitSql}
        ");
        $stmtGuests->execute($params);

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
        $params = [];

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

        if ($searchKeyword !== '') {
            $whereConditions[] = "(c.full_name_entered LIKE ? OR c.phone_entered LIKE ? OR t.table_name LIKE ?)";
            $likeStr = '%' . $searchKeyword . '%';
            $params = [$likeStr, $likeStr, $likeStr];
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        $limitSql = "LIMIT 150";
        $recentStmt = $db->prepare("
            SELECT c.*, t.table_name, g.normalized_phone as guest_normalized_phone 
            FROM checkins c 
            LEFT JOIN event_tables t ON c.table_id = t.id 
            LEFT JOIN guests g ON c.guest_id = g.id
            {$whereClause}
            ORDER BY c.checkin_time DESC {$limitSql}
        ");
        $recentStmt->execute($params);

        while ($row = $recentStmt->fetch()) {
            $isByLuckyCode = ($row['checkin_method'] ?? '') === 'lucky_code' || (!empty($row['guest_normalized_phone']) && $row['normalized_phone'] !== $row['guest_normalized_phone']);
            $methodText = '📱 Khớp SĐT';
            if ($row['match_status'] === 'walk_in') {
                $methodText = '🔸 Khách phát sinh';
            } elseif ($isByLuckyCode) {
                $methodText = '🎟️ Khớp Mã dự thưởng';
            }

            $recentCheckins[] = [
                'id'                  => (int)$row['id'],
                'event_id'            => (int)($row['event_id'] ?? 0),
                'table_id'            => $row['table_id'] ? (int)$row['table_id'] : null,
                'guest_id'            => $row['guest_id'] ? (int)$row['guest_id'] : null,
                'lucky_draw_code'     => esc($row['lucky_draw_code'] ?? ''),
                'full_name'           => esc($row['full_name_entered']),
                'phone'               => esc($row['phone_entered']),
                'table_name'          => esc($row['table_name'] ?? 'Chưa xếp bàn'),
                'time'                => date('d/m/Y H:i:s', strtotime($row['checkin_time'])),
                'status'              => esc($row['match_status']),
                'status_text'         => $row['match_status'] === 'matched' ? 'Hợp lệ' : 'Phát sinh',
                'checkin_method_text' => $methodText,
                'is_by_code'          => $isByLuckyCode
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'stats'           => $stats,
            'tables'          => $tablesSummary,
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
