<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

echo "PHP time(): " . time() . " (" . date('Y-m-d H:i:s') . ")\n";
$row = $db->query("SELECT NOW() as db_now, checkin_time FROM checkins ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "DB NOW(): " . $row['db_now'] . " (ts: " . strtotime($row['db_now']) . ")\n";
    echo "Last Checkin: " . $row['checkin_time'] . " (ts: " . strtotime($row['checkin_time']) . ")\n";
    echo "Diff from PHP time: " . (time() - strtotime($row['checkin_time'])) . " seconds\n";
}
