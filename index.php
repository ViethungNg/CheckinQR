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
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css?v=' . time()); ?>">
</head>
<body>

<div class="container">
    <?php if ($showEventPicker): ?>
        <div class="event-header">
            <div class="brand-logo-wrap">
                <img src="<?php echo url('img/logo pmt.png'); ?>" alt="PMT Logo" class="brand-logo">
                <span class="vip-badge">CỔNG CHECK-IN VÀO CỔNG</span>
            </div>
            <h1>Danh sách sự kiện đang diễn ra</h1>
            <div class="event-meta-text">Vui lòng chọn sự kiện bạn muốn mở màn hình Check-in:</div>
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
                        <div class="event-picker-arrow">Mở check-in &rarr;</div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($errorMsg): ?>
        <div class="event-header" style="text-align: center;">
            <div class="brand-logo-wrap" style="justify-content: center;">
                <img src="<?php echo url('img/logo pmt.png'); ?>" alt="PMT Logo" class="brand-logo">
            </div>
            <h1 style="color: #ef4444; font-size: 1.3rem;">Thông Báo Hệ Thống</h1>
        </div>
        <div class="form-body">
            <div class="alert error" style="display:block; text-align: center;">
                <?php echo esc($errorMsg); ?>
            </div>
        </div>
    <?php else: ?>
        <div class="event-header">
            <div class="brand-logo-wrap">
                <img src="<?php echo url('img/logo pmt.png'); ?>" alt="PMT Logo" class="brand-logo">
                <span class="vip-badge">
                    <span class="pulse-dot"></span> ĐANG MỞ CHECK-IN
                </span>
            </div>
            <h1><?php echo esc($event['event_name']); ?></h1>
            <div class="event-meta-chips">
                <span class="meta-chip">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <?php echo date('d/m/Y', strtotime($event['event_date'])); ?>
                </span>
                <span class="meta-chip">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <?php echo esc($event['location'] ?? 'Đang cập nhật'); ?>
                </span>
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
                        <label for="customer_code">Mã Khách Hàng hoặc SĐT được cấp *</label>
                        <div class="input-with-icon">
                            <span class="input-icon-left">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2.2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M7 8h10M7 12h10M7 16h6"></path></svg>
                            </span>
                            <input type="text" id="customer_code" name="customer_code" class="form-control-vip" required placeholder="Nhập Mã KH (Ví dụ: NPPVP, XOTC01...)" maxlength="50" autocomplete="off">
                        </div>
                    </div>
                </div>
                
                <button type="submit" id="btn-submit" class="btn-submit-vip">
                    <span id="spinner" class="loading-spinner"></span>
                    <span id="btn-text">XÁC NHẬN CHECK-IN</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>

                <div class="form-footer-tips">
                    <div class="tip-item">
                        <span class="tip-icon">⚡</span>
                        <span>Nhận diện bàn tiệc & thông tin chỗ ngồi tức thì</span>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">🎁</span>
                        <span>Cấp mã dự thưởng quay số may mắn bốc thăm</span>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="<?php echo url('assets/js/frontend.js?v=' . time()); ?>"></script>
</body>
</html>
