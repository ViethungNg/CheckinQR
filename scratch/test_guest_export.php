<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập admin đăng nhập
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

$_GET['action'] = 'export_excel';
$_GET['search'] = '';
$_GET['sort'] = 'id_desc';

// Đổi hàm downloadXlsxFile trong bộ nhớ để test xem dữ liệu có xuất ra chuẩn không
require_once __DIR__ . '/../includes/xlsx_reader.php';

$stmtGuests = $db->query("SELECT g.*, e.event_name, t.table_name, t.table_code FROM guests g LEFT JOIN events e ON g.event_id = e.id LEFT JOIN event_tables t ON g.table_id = t.id ORDER BY g.id DESC LIMIT 10");
$guests = $stmtGuests->fetchAll();

echo "SUCCESS: Export query executed cleanly! Total guests fetched for export: " . count($guests) . "\n";
foreach (array_slice($guests, 0, 3) as $idx => $g) {
    echo ($idx + 1) . ". Code: " . ($g['customer_code'] ?? 'N/A') . " | Name: " . $g['full_name'] . " | Table: " . ($g['table_name'] ?? 'Chưa xếp bàn') . "\n";
}
