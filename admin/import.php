<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
requireAdmin();

require_once __DIR__ . '/../includes/xlsx_reader.php';

// Xử lý Xuất File Mẫu Excel chuẩn (.xlsx)
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $headers = ['Họ và tên', 'Số điện thoại', 'Mã bàn', 'Mã bốc thăm', 'Đơn vị công tác'];
    $sampleData = [
        ['Nguyễn Văn A', '0987654321', 'VIP1', '101', 'Công ty Hòa Vinh'],
        ['Trần Thị B', '0912345678', 'TB02', '102', 'Tập đoàn Vĩnh Phú']
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
            if (!empty($rowsData)) {
                array_shift($rowsData); // Bỏ qua dòng tiêu đề
            }
            
            $successCount = 0;
            $failCount = 0;
            
            // Lấy danh sách bàn
            $stmtTables = $db->prepare("SELECT id, table_code FROM event_tables WHERE event_id = ? AND table_code != ''");
            $stmtTables->execute([$eventId]);
            $tableMap = [];
            while ($row = $stmtTables->fetch()) {
                $tableMap[strtolower(trim($row['table_code']))] = $row['id'];
            }
            
            $stmtInsert = $db->prepare("INSERT INTO guests (event_id, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'invited')");
            
            foreach ($rowsData as $data) {
                $fullName  = trim($data[0] ?? '');
                $phone     = trim($data[1] ?? '');
                $tableCode = trim($data[2] ?? '');
                $luckyCode = trim($data[3] ?? '');
                $org       = trim($data[4] ?? '');
                
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
                    $stmtInsert->execute([$eventId, $fullName, $phone, $normalizedPhone, $tableId, $luckyCode, $org]);
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
        
        table.example-table { width: 100%; max-width: 800px; border-collapse: collapse; margin-top: 10px; background: #fafafa; }
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
                2. Điền thông tin danh sách khách mời vào file Excel (định dạng chuẩn <b>.xlsx</b>).<br>
                3. Đảm bảo cột "Mã Bàn" phải khớp chính xác với "Mã Bàn" bạn đã tạo trong phần <i>Quản lý Bàn</i>.
            </div>
            
            <table class="example-table" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Cột A: Họ và tên</th>
                        <th>Cột B: SĐT</th>
                        <th>Cột C: Mã Bàn</th>
                        <th>Cột D: Mã Quay thưởng</th>
                        <th>Cột E: Đơn vị</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Nguyễn Văn A</td>
                        <td>0987654321</td>
                        <td>VIP1</td>
                        <td>101</td>
                        <td>Công ty Hòa Vinh</td>
                    </tr>
                    <tr>
                        <td>Trần Thị B</td>
                        <td>0912345678</td>
                        <td>TB02</td>
                        <td>102</td>
                        <td>Tập đoàn Vĩnh Phú</td>
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
