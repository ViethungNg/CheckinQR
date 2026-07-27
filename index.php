<?php
require_once __DIR__ . '/config/bootstrap.php';

$slug = $_GET['event'] ?? '';

$db = Database::getConnection();

// Lấy thông tin sự kiện
$stmt = $db->prepare("SELECT * FROM events WHERE slug = ? AND status = 'active' LIMIT 1");
$stmt->execute([$slug]);
$event = $stmt->fetch();

$errorMsg = '';
if (!$event) {
    $errorMsg = 'Sự kiện không tồn tại hoặc đã bị khóa.';
} elseif ($event['checkin_enabled'] == 0) {
    $errorMsg = 'Check-in cho sự kiện này đang tạm đóng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $event ? esc($event['event_name']) : 'Sự kiện'; ?> - Check-in</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
</head>
<body>

<div class="container">
    <?php if ($errorMsg): ?>
        <div class="form-body">
            <div class="alert error" style="display:block;">
                <?php echo esc($errorMsg); ?>
            </div>
        </div>
    <?php else: ?>
        <div class="event-header">
            <h1><?php echo esc($event['event_name']); ?></h1>
            <div class="event-meta">
                Ngày: <?php echo date('d/m/Y', strtotime($event['event_date'])); ?><br>
                Địa điểm: <?php echo esc($event['location'] ?? 'Đang cập nhật'); ?>
            </div>
        </div>
        
        <div class="form-body">
            <div id="alert-message" class="alert"></div>
            
            <form id="checkin-form" action="<?php echo url('api/checkin.php'); ?>" method="POST">
                <input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>">
                
                <div id="form-fields">
                    <div class="form-group">
                        <label for="full_name">Họ và tên *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="Nhập họ tên của bạn" maxlength="150">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Số điện thoại *</label>
                        <input type="tel" id="phone" name="phone" class="form-control" required placeholder="Ví dụ: 0912345678" maxlength="30">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Địa chỉ / Đơn vị công tác</label>
                        <input type="text" id="address" name="address" class="form-control" placeholder="Nhập địa chỉ hoặc tên đơn vị" maxlength="255">
                    </div>
                </div>
                
                <button type="submit" id="btn-submit" class="btn-submit">
                    <span id="spinner" class="loading-spinner"></span>
                    <span id="btn-text">Xác nhận Check-in</span>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="<?php echo url('assets/js/frontend.js'); ?>"></script>
</body>
</html>
