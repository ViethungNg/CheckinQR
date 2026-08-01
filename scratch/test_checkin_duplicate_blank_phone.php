<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$eventId = 1;
// Thực hiện chèn 2 lượt checkin liên tiếp có SĐT rỗng
try {
    $stmt = $db->prepare("INSERT INTO checkins (event_id, full_name_entered, phone_entered, normalized_phone, match_status, checkin_time) VALUES (?, ?, ?, ?, 'walk_in', NOW())");
    $stmt->execute([$eventId, 'Khách Rỗng SĐT 1', '', '']);
    $id1 = $db->lastInsertId();

    $stmt->execute([$eventId, 'Khách Rỗng SĐT 2', '', '']);
    $id2 = $db->lastInsertId();

    echo "SUCCESS: Checkin 2 guests with blank phone succeeded! IDs: $id1, $id2\n";

    // Clean up test data
    $db->exec("DELETE FROM checkins WHERE id IN ($id1, $id2)");
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
