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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=0.5, user-scalable=yes, viewport-fit=cover">
    <title><?php echo $event ? esc($event['event_name']) . ' - PMT Checkin' : ($showEventPicker ? 'PMT - Checkin - Chọn Sự Kiện' : 'PMT - Checkin'); ?></title>
    <link rel="icon" href="<?php echo url('img/logo pmt.png'); ?>" type="image/png">
    <?php require_once __DIR__ . '/includes/pwa_head.php'; ?>
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
</head>
<body>

<div class="container">
    <?php if ($showEventPicker): ?>
        <div class="event-header">
            <h1>Danh sách sự kiện đang diễn ra</h1>
            <div class="event-meta">Vui lòng chọn sự kiện bạn muốn mở màn hình Check-in:</div>
        </div>
        <div class="form-body">
            <div class="event-picker-list">
                <?php foreach ($activeEvents as $actEv): ?>
                    <a href="index.php?event=<?php echo urlencode($actEv['slug']); ?>" class="event-picker-card">
                        <div>
                            <div class="event-picker-title"><?php echo esc($actEv['event_name']); ?></div>
                            <div class="event-picker-meta">
                                <?php echo date('d/m/Y', strtotime($actEv['event_date'])); ?> 
                                <?php if (!empty($actEv['location'])): ?>
                                    | <?php echo esc($actEv['location']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="event-picker-arrow">Mở check-in</div>
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
            
            <form id="checkin-form" action="api/checkin.php" method="POST">
                <input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>">
                <input type="hidden" name="action" id="form-action-type" value="lookup">
                <input type="hidden" name="guest_id" id="form-guest-id" value="">
                
                <div id="form-fields">
                    <div class="form-group">
                        <label for="customer_code">Mã Khách hàng được NPP cung cấp *</label>
                        <input type="text" id="customer_code" name="customer_code" class="form-control" required placeholder="" maxlength="50" autocomplete="off">
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

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>
<script src="<?php echo url('assets/js/frontend.js?v=' . time()); ?>"></script>
</body>
</html>
