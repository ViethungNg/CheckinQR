<?php
require_once __DIR__ . '/../config/bootstrap.php';
$db = Database::getConnection();

// Giả lập 1 kịch bản có 1 dòng hợp lệ và 1 dòng bị lỗi (Mã bàn không tồn tại)
$eventId = 1;
$rowsData = [
    ['KH_VAL_1', 'Khách Hợp Lệ 1', '0912345678', 'Công ty A', 'VIP1', '101'],
    ['KH_VAL_2', 'Khách Lỗi Bàn', '0912345679', 'Công ty B', 'BAN_KHONG_TON_TAI_999', '102']
];

// Lấy danh sách bàn
$stmtTables = $db->prepare("SELECT id, table_name, table_code FROM event_tables WHERE event_id = ?");
$stmtTables->execute([$eventId]);
$tableMap = [];
while ($row = $stmtTables->fetch()) {
    if (!empty($row['table_code'])) $tableMap[strtolower(trim($row['table_code']))] = $row['id'];
    if (!empty($row['table_name'])) $tableMap[strtolower(trim($row['table_name']))] = $row['id'];
}

$validationErrors = [];
foreach ($rowsData as $rowIdx => $data) {
    $lineNum = $rowIdx + 2;
    $fullName = $data[1];
    $tableCode = $data[4];
    
    if (!empty($tableCode)) {
        if (!isset($tableMap[strtolower($tableCode)])) {
            $validationErrors[] = "Dòng {$lineNum} ('{$fullName}'): Bàn '{$tableCode}' không tồn tại trong Quản lý Bàn.";
        }
    }
}

if (!empty($validationErrors)) {
    echo "SUCCESS (All-or-Nothing Rule Works!): Cancelled import due to " . count($validationErrors) . " error:\n";
    echo implode("\n", $validationErrors);
} else {
    echo "FAILED: Validation did not catch non-existent table!";
}
