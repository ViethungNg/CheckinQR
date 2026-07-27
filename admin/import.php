<?php
require_once __DIR__ . '/../config/bootstrap.php';
requireLogin();
requireAdmin();

$db = Database::getConnection();

$message = '';
$error = '';
$results = [];

if (isPost() && isset($_FILES['csv_file'])) {
    requireCsrfToken();
    $eventId = (int)$_POST['event_id'];
    
    if (empty($eventId)) {
        $error = 'Vui lòng chọn sự kiện để import.';
    } elseif ($_FILES['csv_file']['error'] == 0) {
        $fileName = $_FILES['csv_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $error = 'Vui lòng chọn file định dạng .csv';
        } else {
            $file = fopen($_FILES['csv_file']['tmp_name'], "r");
            
            // Đọc header (bỏ qua dòng đầu)
            $header = fgetcsv($file);
            
            $successCount = 0;
            $failCount = 0;
            
            // Lấy trước danh sách bàn của sự kiện này (để lookup table_id)
            // Lưu dưới dạng: 'table_code' => id
            $stmtTables = $db->prepare("SELECT id, table_code FROM event_tables WHERE event_id = ? AND table_code != ''");
            $stmtTables->execute([$eventId]);
            $tableMap = [];
            while ($row = $stmtTables->fetch()) {
                $tableMap[strtolower(trim($row['table_code']))] = $row['id'];
            }
            
            $stmtInsert = $db->prepare("INSERT INTO guests (event_id, full_name, phone, normalized_phone, table_id, lucky_draw_code, organization, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'invited')");
            
            while (($data = fgetcsv($file)) !== FALSE) {
                // Giả sử Cấu trúc: 0: Họ tên, 1: SĐT, 2: Mã Bàn, 3: Mã quay giải, 4: Đơn vị
                $fullName = trim($data[0] ?? '');
                $phone = trim($data[1] ?? '');
                $tableCode = trim($data[2] ?? '');
                $luckyCode = trim($data[3] ?? '');
                $org = trim($data[4] ?? '');
                
                if (empty($fullName)) {
                    $failCount++;
                    continue;
                }
                
                $normalizedPhone = normalizePhone($phone);
                
                // Lookup Table ID
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
                    $failCount++; // Có thể do trùng SĐT
                }
            }
            
            fclose($file);
            $message = "Đã Import xong! Thành công: $successCount khách, Thất bại/Trùng/Bỏ qua: $failCount khách.";
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
    <title>Import Khách mời - CheckinQR</title>
    <!-- CSS dùng chung đơn giản -->
    <style>
        :root { --primary-color: #d32f2f; --sidebar-width: 250px; --bg-color: #f4f6f8; --text-color: #333; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); padding: 20px; }
        .sidebar h2 { color: var(--primary-color); margin-bottom: 30px; font-size: 1.5rem; text-align: center; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 10px; }
        .sidebar ul li a { display: block; padding: 10px 15px; color: #555; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #fce4e4; color: var(--primary-color); font-weight: 600; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
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
    <div class="sidebar">
        <h2>CheckinQR</h2>
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="events.php">Quản lý Sự kiện</a></li>
            <li><a href="guests.php" class="active">Khách dự kiến</a></li>
            <li><a href="checkins.php">Check-in trực tiếp</a></li>
            <li><a href="tables.php">Quản lý bàn</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Import Khách bằng File CSV</h1>
        </div>
        
        <?php if($message): ?><div class="alert success"><?php echo esc($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?php echo esc($error); ?></div><?php endif; ?>

        <div class="content-box">
            <a href="guests.php" style="color: #666; text-decoration: none; margin-bottom: 20px; display: inline-block;">&larr; Quay lại danh sách khách</a>
            
            <div class="alert info">
                <strong>Hướng dẫn:</strong><br>
                1. Tạo file Excel với cấu trúc đúng 5 cột theo thứ tự sau (không để trống dòng tiêu đề):<br>
                2. Lưu file ở định dạng <b>CSV (Comma delimited)</b> (Bấm Save As -> Chọn định dạng CSV).<br>
                3. Đảm bảo "Mã Bàn" phải khớp chính xác với "Mã Bàn" bạn đã tạo trong phần <i>Quản lý Bàn</i>.
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
                        <td>VIP-01</td>
                        <td>12345</td>
                        <td>Công ty ABC</td>
                    </tr>
                    <tr>
                        <td>Trần Thị B</td>
                        <td>0912345678</td>
                        <td>THUONG-02</td>
                        <td></td>
                        <td></td>
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
                    <label>Chọn File CSV *</label>
                    <input type="file" name="csv_file" accept=".csv" required style="padding: 10px 0;">
                </div>
                
                <button type="submit" class="btn btn-primary" onclick="return confirm('Bạn có chắc chắn muốn Import danh sách này? Quá trình không thể hoàn tác.')">Bắt đầu Import</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
