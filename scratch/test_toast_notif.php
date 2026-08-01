<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Kiểm tra xem notifications.js có chứa showToastNotification(item) trong checkNewNotifications không
$jsContent = file_get_contents(__DIR__ . '/../assets/js/notifications.js');

if (strpos($jsContent, 'showToastNotification(item);') !== false) {
    echo "SUCCESS: showToastNotification(item) is restored in notifications.js!";
} else {
    echo "FAILED: showToastNotification(item) missing in notifications.js!";
}
