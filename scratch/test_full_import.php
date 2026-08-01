<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Kiểm tra sự kiện
$eventId = (int)$db->query("SELECT id FROM events ORDER BY id DESC LIMIT 1")->fetchColumn();
if (!$eventId) {
    die("No event found");
}

// Chèn thử 1 bản ghi với Đơn vị / Đại lý
$stmtInsert = $db->prepare("
    INSERT INTO guests 
    (event_id, customer_code, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) 
    VALUES (?, ?, ?, ?, ?, NULL, ?, ?, 'invited')
");

$stmtInsert->execute([$eventId, 'KH_ORG_TEST', 'Khách Org Test', '0999888777', '0999888777', '999', 'Đại lý Vĩnh Phúc']);

// Kiểm tra lại trong DB
$checkStmt = $db->prepare("SELECT * FROM guests WHERE customer_code = 'KH_ORG_TEST'");
$checkStmt->execute();
$guest = $checkStmt->fetch(PDO::FETCH_ASSOC);

print_r($guest);

// Dọn dẹp
$db->exec("DELETE FROM guests WHERE customer_code = 'KH_ORG_TEST'");
echo "\nTEST PASSED: organization field saved properly as '{$guest['organization']}'!";
