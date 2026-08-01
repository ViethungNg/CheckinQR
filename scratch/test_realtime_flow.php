<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// Get max ID before checkin
$maxBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM checkins")->fetchColumn();

// Perform checkin
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Realtime Flow Test', '0909090909', '0909090909', 'matched', NOW())")->execute();
$newId = $db->lastInsertId();

$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Clean up
$db->exec("DELETE FROM checkins WHERE id = $newId");

if ($data && isset($data['max_id']) && $data['max_id'] == $newId) {
    $newItems = array_filter($data['checkins'], function($c) use ($maxBefore) {
        return $c['id'] > $maxBefore;
    });
    echo "SUCCESS: Checkin flow verified! New Max ID: {$data['max_id']}, New items count: " . count($newItems) . "\n";
    foreach ($newItems as $item) {
        echo "Item -> ID: {$item['id']} | Name: {$item['full_name']} | Time: {$item['time']}\n";
    }
} else {
    echo "FAILED: Notification check did not register new checkin!\n";
}
