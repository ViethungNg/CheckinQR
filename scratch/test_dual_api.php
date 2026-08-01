<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

// Test Primary API
$_GET['action'] = 'check';
ob_start();
require __DIR__ . '/../api/notifications.php';
$resPrimary = ob_get_clean();
$dataPrimary = json_decode($resPrimary, true);

// Test Fallback API
ob_start();
require __DIR__ . '/../api/stats.php';
$resFallback = ob_get_clean();
$dataFallback = json_decode($resFallback, true);

echo "PRIMARY API (api/notifications.php): " . ($dataPrimary && $dataPrimary['status'] === 'success' ? 'OK (Items: ' . count($dataPrimary['checkins']) . ')' : 'FAIL') . "\n";
echo "FALLBACK API (api/stats.php): " . ($dataFallback && isset($dataFallback['recent_checkins']) ? 'OK (Items: ' . count($dataFallback['recent_checkins']) . ')' : 'FAIL') . "\n";
