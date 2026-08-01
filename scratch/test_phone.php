<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();
try {
    $stmt = $db->prepare("INSERT INTO guests (event_id, customer_code, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) VALUES (1, ?, ?, '', '', NULL, ?, ?, 'invited')");
    $stmt->execute(['TEST_BLANK_1', 'Khách Blank 1', '101', 'Cty A']);
    $stmt->execute(['TEST_BLANK_2', 'Khách Blank 2', '102', 'Cty B']);
    
    // Dọn dẹp bản ghi test
    $db->exec("DELETE FROM guests WHERE customer_code LIKE 'TEST_BLANK_%'");
    echo "SUCCESS: Multiple guests with empty/duplicate phone numbers inserted cleanly!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
