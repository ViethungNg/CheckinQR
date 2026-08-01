<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

$tblId = $db->query("SELECT id FROM event_tables LIMIT 1")->fetchColumn();

// Chèn 1 khách test có xếp bàn (nếu có bàn)
$db->exec("INSERT INTO guests (event_id, customer_code, full_name, phone, normalized_phone, table_id, status) VALUES (1, 'EDIT_TEST', 'Khách Edit Test', '0911222333', '0911222333', " . ($tblId ? $tblId : "NULL") . ", 'invited')");
$id = $db->lastInsertId();

// Giả lập sửa thông tin sang "Chưa xếp bàn" (table_id = null)
$db->prepare("UPDATE guests SET table_id = NULL WHERE id = ?")->execute([$id]);

// Kiểm tra xem khách còn tồn tại trong CSDL không
$guest = $db->query("SELECT * FROM guests WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

if ($guest && empty($guest['table_id'])) {
    echo "SUCCESS: Guest updated to 'Chưa xếp bàn' without being deleted! Name: {$guest['full_name']}";
} else {
    echo "FAILED: Guest was deleted or table_id not updated!";
}

// Clean up
$db->exec("DELETE FROM guests WHERE id = {$id}");
