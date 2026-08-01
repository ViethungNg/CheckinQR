<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// 1. Tạo lượt checkin mới
$db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (1, 'Khách Test Cuối Cùng', '0999888777', '0999888777', 'matched', NOW())")->execute();
$newId = (int)$db->lastInsertId();

// 2. Call API notifications.php
$_GET['action'] = 'check';
ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

// Dọn dẹp
$db->exec("DELETE FROM checkins WHERE id = {$newId}");

if ($data && $data['status'] === 'success' && !empty($data['checkins'])) {
    $first = $data['checkins'][0];
    echo "SUCCESS END-TO-END:\n";
    echo " - Status: {$data['status']}\n";
    echo " - Max ID: {$data['max_id']}\n";
    echo " - Latest Checkin ID: {$first['id']}\n";
    echo " - Guest Name: {$first['full_name']}\n";
    echo " - Checkin Time: {$first['time']}\n";
} else {
    echo "FAILED: Invalid response!\n";
}
