<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
requireAdmin();

require_once __DIR__ . '/../includes/xlsx_reader.php';

// Xử lý Xuất File Mẫu Excel chuẩn (.xlsx)
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $headers = ['Mã KH', 'Họ và tên', 'Số điện thoại', 'Đơn vị / Đại lý', 'Bàn ngồi', 'Mã trúng thưởng'];
    $sampleData = [
        ['KH001', 'Nguyễn Văn A', '0987654321', 'Công ty Hòa Vinh', 'VIP1', '101'],
        ['KH002', 'Trần Thị B', '0912345678', 'Tập đoàn Vĩnh Phú', 'TB02', '102']
    ];
    downloadXlsxFile('Mau_Danh_Sach_Khach_Hang.xlsx', $headers, $sampleData);
}

$db = Database::getConnection();

$message = '';
$error = '';
$results = [];

if (isPost() && (isset($_FILES['excel_file']) || isset($_FILES['csv_file']))) {
    requireCsrfToken();
    $eventId = (int)$_POST['event_id'];
    $uploadedFile = $_FILES['excel_file'] ?? $_FILES['csv_file'];
    
    if (empty($eventId)) {
        $error = 'Vui lòng chọn sự kiện để import.';
    } elseif ($uploadedFile['error'] == 0) {
        $fileName = $uploadedFile['name'];
        $tmpPath = $uploadedFile['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($ext !== 'xlsx') {
            $error = 'Vui lòng chọn file Excel định dạng chuẩn (.xlsx).';
        } else {
            $rowsData = parseXlsxFile($tmpPath);
            
            // Cấu hình chỉ số cột mặc định (Khớp 100% thứ tự bảng Danh sách khách hàng)
            // Cột A (0): Mã KH, Cột B (1): Họ và tên, Cột C (2): SĐT, Cột D (3): Đơn vị / Đại lý, Cột E (4): Bàn ngồi, Cột F (5): Mã trúng thưởng
            $colMap = [
                'customer_code' => 0,
                'full_name'     => 1,
                'phone'         => 2,
                'organization'  => 3,
                'table_code'    => 4,
                'lucky_code'    => 5
            ];

            if (!empty($rowsData)) {
                $headerRow = array_shift($rowsData); // Bỏ qua dòng tiêu đề
                
                // Smart Header Mapping: Nhận diện tự động chuẩn xác bằng cách khử dấu tiếng Việt
                if (!empty($headerRow) && is_array($headerRow)) {
                    $foundMap = [];
                    foreach ($headerRow as $idx => $headerText) {
                        $rawH = (string)$headerText;
                        // Khử dấu tiếng Việt
                        $h = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", "a", strtolower($rawH));
                        $h = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", "e", $h);
                        $h = preg_replace("/(ì|í|ị|ỉ|ĩ)/", "i", $h);
                        $h = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", "o", $h);
                        $h = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", "u", $h);
                        $h = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", "y", $h);
                        $h = preg_replace("/(đ)/", "d", $h);
                        $h = trim($h);

                        if (empty($h)) continue;

                        // 1. Khớp Đơn vị / Đại lý / Công ty / Nơi công tác trước (tránh 'Tên đơn vị' bị khớp nhầm vào Họ và tên)
                        if (strpos($h, 'don vi') !== false || strpos($h, 'dai ly') !== false || strpos($h, 'cong ty') !== false || strpos($h, 'noi cong tac') !== false || strpos($h, 'org') !== false || strpos($h, 'agency') !== false) {
                            $foundMap['organization'] = $idx;
                        } elseif (strpos($h, 'ban') !== false || strpos($h, 'table') !== false) {
                            $foundMap['table_code'] = $idx;
                        } elseif (strpos($h, 'thuong') !== false || strpos($h, 'boc tham') !== false || strpos($h, 'quay') !== false || strpos($h, 'lucky') !== false) {
                            $foundMap['lucky_code'] = $idx;
                        } elseif (strpos($h, 'ma kh') !== false || strpos($h, 'doi chieu') !== false || strpos($h, 'ma khach') !== false || strpos($h, 'code') !== false) {
                            $foundMap['customer_code'] = $idx;
                        } elseif (strpos($h, 'sdt') !== false || strpos($h, 'dien thoai') !== false || strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) {
                            $foundMap['phone'] = $idx;
                        } elseif (strpos($h, 'ho') !== false || strpos($h, 'ten') !== false || strpos($h, 'name') !== false) {
                            $foundMap['full_name'] = $idx;
                        }
                    }
                    if (isset($foundMap['full_name'])) {
                        $colMap = array_merge($colMap, $foundMap);
                    }
                }
            }
            
            $successCount = 0;
            $failCount = 0;
            
            // Lấy danh sách bàn (khớp cả Mã bàn lẫn Tên bàn)
            $stmtTables = $db->prepare("SELECT id, table_name, table_code FROM event_tables WHERE event_id = ?");
            $stmtTables->execute([$eventId]);
            $tableMap = [];
            while ($row = $stmtTables->fetch()) {
                if (!empty($row['table_code'])) {
                    $tableMap[strtolower(trim($row['table_code']))] = $row['id'];
                }
                if (!empty($row['table_name'])) {
                    $tableMap[strtolower(trim($row['table_name']))] = $row['id'];
                }
            }
            
            $stmtInsert = $db->prepare("
                INSERT INTO guests 
                (event_id, customer_code, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'invited')
            ");
            
            foreach ($rowsData as $data) {
                $customerCode = isset($colMap['customer_code']) ? trim($data[$colMap['customer_code']] ?? '') : '';
                $fullName     = isset($colMap['full_name']) ? trim($data[$colMap['full_name']] ?? '') : '';
                $phone        = isset($colMap['phone']) ? trim($data[$colMap['phone']] ?? '') : '';
                $org          = isset($colMap['organization']) ? trim($data[$colMap['organization']] ?? '') : '';
                $tableCode    = isset($colMap['table_code']) ? trim($data[$colMap['table_code']] ?? '') : '';
                $luckyCode    = isset($colMap['lucky_code']) ? trim($data[$colMap['lucky_code']] ?? '') : '';
                
                if (empty($fullName)) {
                    $failCount++;
                    continue;
                }
                
                $normalizedPhone = normalizePhone($phone);
                
                $tableId = null;
                if (!empty($tableCode)) {
                    $codeLower = strtolower($tableCode);
                    if (isset($tableMap[$codeLower])) {
                        $tableId = $tableMap[$codeLower];
                    }
                }
                
                try {
                    $stmtInsert->execute([$eventId, $customerCode, $fullName, $phone, $normalizedPhone, $tableId, $luckyCode, $org]);
                    $successCount++;
                } catch(PDOException $e) {
                    $failCount++;
                }
            }
            
            $message = "Đã Import xong từ file Excel! Thành công: $successCount khách, Thất bại/Trùng/Bỏ qua: $failCount khách.";
        }
    } else {
        $error = 'Lỗi upload file.';
    }
}

// Lấy danh sách sự kiện
$events = $db->query("SELECT id, event_name FROM events ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT - Checkin - Import từ Excel</title>
    <link rel="icon" href="../img/logo pmt.png" type="image/png">
    <link rel="stylesheet" href="../assets/css/admin-responsive.css?v=<?php echo time(); ?>">
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .content-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; max-width: 400px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        .btn { padding: 10px 20px; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; border: none; font-size: 1rem; font-weight: bold; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: #4caf50; color: white; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border-left: 5px solid #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; border-left: 5px solid #c62828; }
        .alert.info { background: #e3f2fd; color: #1565c0; border-left: 5px solid #1565c0; }
        
        table.example-table { width: 100%; max-width: 950px; border-collapse: collapse; margin-top: 10px; background: #fafafa; }
        table.example-table th, table.example-table td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        table.example-table th { background: #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Import Khách bằng File Excel (.xlsx)</h1>
        </div>
        
        <?php if ($message): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>, 'success'); });</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <script>document.addEventListener('DOMContentLoaded', function() { window.showAppToast && window.showAppToast(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>, 'error'); });</script>
        <?php endif; ?>

        <div class="content-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <a href="guests.php" style="color: #666; text-decoration: none; font-weight: 500;">&larr; Quay lại danh sách khách</a>
                <a href="import.php?action=download_template" class="btn btn-success" style="background: #2e7d32; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: bold; padding: 10px 18px; border-radius: 8px;">
                    📥 Tải File Mẫu Excel (.xlsx)
                </a>
            </div>
            
            <div class="alert info">
                <strong>Hướng dẫn:</strong><br>
                1. Bấm nút <b>"Tải File Mẫu Excel (.xlsx)"</b> phía trên để tải file Excel chuẩn về máy.<br>
                2. Điền thông tin vào file Excel đúng theo thứ tự các cột của bảng <i>Danh sách khách hàng</i>: <b>Mã KH &rarr; Họ và tên &rarr; SĐT &rarr; Đơn vị / Đại lý &rarr; Bàn ngồi &rarr; Mã trúng thưởng</b>.<br>
                3. Đảm bảo cột "Bàn ngồi" phải khớp chính xác với "Mã Bàn" bạn đã tạo trong phần <i>Quản lý Bàn</i>.
            </div>
            
            <table class="example-table" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Cột A: Mã KH</th>
                        <th>Cột B: Họ và tên</th>
                        <th>Cột C: SĐT</th>
                        <th>Cột D: Đơn vị / Đại lý</th>
                        <th>Cột E: Bàn ngồi</th>
                        <th>Cột F: Mã trúng thưởng</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong style="color: #0284c7;">KH001</strong></td>
                        <td>Nguyễn Văn A</td>
                        <td>0987654321</td>
                        <td>Công ty Hòa Vinh</td>
                        <td>VIP1</td>
                        <td>101</td>
                    </tr>
                    <tr>
                        <td><strong style="color: #0284c7;">KH002</strong></td>
                        <td>Trần Thị B</td>
                        <td>0912345678</td>
                        <td>Tập đoàn Vĩnh Phú</td>
                        <td>TB02</td>
                        <td>102</td>
                    </tr>
                </tbody>
            </table>

            <form method="POST" action="" enctype="multipart/form-data" style="border: 1px dashed #ccc; padding: 20px; border-radius: 8px; max-width: 600px; background: #fff;">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label>Sự kiện áp dụng *</label>
                    <select name="event_id" class="form-control" required style="max-width: 100%;">
                        <option value="">-- Chọn sự kiện --</option>
                        <?php foreach($events as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo esc($e['event_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Chọn File Excel (.xlsx) *</label>
                    <input type="file" name="excel_file" accept=".xlsx" required style="padding: 10px 0;">
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 10px;" onclick="return confirmModal(event, 'Bạn có chắc chắn muốn Import danh sách này? Quá trình không thể hoàn tác.');">Bắt đầu Import</button>
            </form>
        </div>
    </div>
</div>
<script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>
<script src="../assets/js/admin-mobile.js?v=<?php echo time(); ?>"></script>
</body>
</html>
