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

echo "STATUS: " . ($data['status'] ?? 'none') . "\n";
echo "MAX ID: " . ($data['max_id'] ?? 'none') . "\n";
echo "CHECKINS COUNT: " . count($data['checkins'] ?? []) . "\n";
if (!empty($data['checkins'])) {
    foreach ($data['checkins'] as $i => $item) {
        echo " - [{$i}] ID: {$item['id']} | Name: {$item['full_name']} | Time: {$item['time']}\n";
    }
}
