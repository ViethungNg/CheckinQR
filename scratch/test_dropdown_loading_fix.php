<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

$_GET['action'] = 'check';

ob_start();
require __DIR__ . '/../api/notifications.php';
$res = ob_get_clean();

$data = json_decode($res, true);

if ($data && isset($data['status']) && $data['status'] === 'success') {
    echo "SUCCESS: API notifications endpoint returns valid JSON!\n";
    echo "Max ID: " . ($data['max_id'] ?? 0) . "\n";
    echo "Unread Count: " . ($data['unread_count'] ?? 0) . "\n";
    echo "Checkins Count: " . count($data['checkins'] ?? []) . "\n";
} else {
    echo "FAILED: API response invalid!\n";
}
