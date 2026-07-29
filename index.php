<?php
require_once __DIR__ . '/config/bootstrap.php';

$slug = trim($_GET['event'] ?? '');
$db = Database::getConnection();

$event = null;
$errorMsg = '';
$showEventPicker = false;
$activeEvents = [];

if ($slug !== '') {
    // Tìm sự kiện theo Slug
    $stmt = $db->prepare("SELECT * FROM events WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$slug]);
    $event = $stmt->fetch();

    if (!$event) {
        $errorMsg = 'Sự kiện không tồn tại hoặc đã bị khóa.';
    } elseif ($event['checkin_enabled'] == 0) {
        $errorMsg = 'Check-in cho sự kiện này đang tạm đóng.';
    }
} else {
    // Không truyền slug: Tự động tìm danh sách các sự kiện đang active
    $stmt = $db->query("SELECT * FROM events WHERE status = 'active' ORDER BY id DESC");
    $activeEvents = $stmt->fetchAll();

    if (count($activeEvents) === 1) {
        // Đúng 1 sự kiện active -> Tự chọn luôn
        $event = $activeEvents[0];
        if ($event['checkin_enabled'] == 0) {
            $errorMsg = 'Check-in cho sự kiện "' . esc($event['event_name']) . '" đang tạm đóng.';
        }
    } elseif (count($activeEvents) > 1) {
        // Nhiều hơn 1 sự kiện active -> Hiển thị màn hình chọn sự kiện
        $showEventPicker = true;
    } else {
        $errorMsg = 'Hiện chưa có sự kiện nào đang diễn ra. Vui lòng liên hệ Quản trị viên.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $event ? esc($event['event_name']) . ' - PMT Checkin' : ($showEventPicker ? 'PMT - Checkin - Chọn Sự Kiện' : 'PMT - Checkin'); ?></title>
    <link rel="icon" href="<?php echo url('img/logo pmt.png'); ?>" type="image/png">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <style>
        .event-picker-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }
        .event-picker-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            text-decoration: none;
            color: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .event-picker-card:hover {
            border-color: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(211, 47, 47, 0.12);
        }
        .event-picker-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .event-picker-meta {
            font-size: 0.85rem;
            color: #64748b;
        }
        .event-picker-arrow {
            font-size: 1.2rem;
            font-weight: bold;
            color: #d32f2f;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($showEventPicker): ?>
        <div class="event-header">
            <h1>🎉 Danh Sách Sự Kiện Đang Diễn Ra</h1>
            <div class="event-meta">Vui lòng chọn sự kiện bạn muốn mở màn hình Check-in:</div>
        </div>
        <div class="form-body">
            <div class="event-picker-list">
                <?php foreach ($activeEvents as $actEv): ?>
                    <a href="index.php?event=<?php echo urlencode($actEv['slug']); ?>" class="event-picker-card">
                        <div>
                            <div class="event-picker-title"><?php echo esc($actEv['event_name']); ?></div>
                            <div class="event-picker-meta">
                                📅 <?php echo date('d/m/Y', strtotime($actEv['event_date'])); ?> 
                                <?php if (!empty($actEv['location'])): ?>
                                    | 📍 <?php echo esc($actEv['location']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="event-picker-arrow">Quét QR ➔</div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($errorMsg): ?>
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
                        <label for="address">Địa chỉ/ Tên cửa hàng</label>
                        <input type="text" id="address" name="address" class="form-control" placeholder="Nhập địa chỉ hoặc tên cửa hàng" maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="lucky_draw_code">Mã trúng giải (nếu nhớ)</label>
                        <input type="text" id="lucky_draw_code" name="lucky_draw_code" class="form-control" placeholder="Nhập mã trúng giải (nếu có)" maxlength="50">
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

<script src="<?php echo url('assets/js/frontend.js?v=' . time()); ?>"></script>
</body>
</html>
