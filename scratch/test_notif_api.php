<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Giả lập phiên đăng nhập admin
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

ob_start();
require __DIR__ . '/../api/notifications.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if ($data && isset($data['status']) && $data['status'] === 'success') {
    echo "SUCCESS: API notifications working! Max ID: " . $data['max_id'] . ", Total checkins fetched: " . count($data['checkins']);
} else {
    echo "FAILED: API returned invalid response: " . $output;
}
