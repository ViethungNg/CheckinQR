<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Chèn 1 lượt checkin test
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách All Roles Notification Test', '0977889900', '0977889900', 'matched', NOW())")->execute();
$newId = $db->lastInsertId();

$_GET['action'] = 'check';

$rolesToTest = [
    'admin' => 1,
    'letan' => 3,
    'kinhdoanh' => 2
];

$results = [];

foreach ($rolesToTest as $roleName => $uid) {
    $_SESSION['admin_id'] = $uid;
    $_SESSION['admin_role'] = $roleName;

    ob_start();
    require __DIR__ . '/../api/notifications.php';
    $res = ob_get_clean();

    $data = json_decode($res, true);
    if ($data && !empty($data['checkins']) && $data['checkins'][0]['id'] == $newId) {
        $results[$roleName] = "OK - Received checkin ID {$newId} ({$data['checkins'][0]['full_name']})";
    } else {
        $results[$roleName] = "FAIL - Did not receive checkin";
    }
}

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = $newId");

echo "=== REALTIME NOTIFICATION ACCESS BY ROLE ===\n";
foreach ($results as $role => $status) {
    echo strtoupper($role) . ": " . $status . "\n";
}
