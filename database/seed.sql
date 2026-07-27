-- Dữ liệu mẫu (Seed) cho CheckinQR
-- Vui lòng chạy file schema.sql trước khi chạy file này

SET NAMES utf8mb4;

-- 1. Thêm Admin mẫu (Mật khẩu là: 123456)
-- Password hash sinh bằng: password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO `ci_users` (`username`, `password_hash`, `full_name`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin', 'active');

-- 2. Thêm Sự kiện mẫu
INSERT INTO `ci_events` (`event_name`, `event_code`, `slug`, `event_date`, `start_time`, `end_time`, `location`, `description`, `status`, `checkin_enabled`, `expected_guests`) VALUES
('Workshop Kết nối Doanh nghiệp 2026', 'WS2026', 'workshop-2026', '2026-08-15', '08:00:00', '12:00:00', 'Khách sạn Rex, TP.HCM', 'Sự kiện giao lưu và kết nối doanh nghiệp', 'active', 1, 100);

-- 3. Thêm Bàn mẫu
INSERT INTO `ci_event_tables` (`event_id`, `table_name`, `table_code`, `capacity`, `location`) VALUES
(1, 'Bàn VIP 1', 'T-VIP1', 10, 'Khu vực giữa sân khấu'),
(1, 'Bàn 01', 'T-01', 10, 'Khu vực bên trái'),
(1, 'Bàn 02', 'T-02', 10, 'Khu vực bên trái'),
(1, 'Bàn 03', 'T-03', 10, 'Khu vực bên phải'),
(1, 'Bàn 04', 'T-04', 10, 'Khu vực bên phải');

-- 4. Thêm Khách dự kiến
INSERT INTO `ci_guests` (`event_id`, `full_name`, `phone`, `normalized_phone`, `address`, `guest_group`, `organization`, `table_id`, `status`) VALUES
(1, 'Nguyễn Văn A', '0912345678', '0912345678', 'Quận 1, TP.HCM', 'Khách VIP', 'Công ty ABC', 1, 'pending'),
(1, 'Trần Thị B', '0987654321', '0987654321', 'Quận 3, TP.HCM', 'Khách mời', 'Công ty XYZ', 2, 'pending'),
(1, 'Lê Văn C', '0901222333', '0901222333', 'Bình Thạnh, TP.HCM', 'Khách mời', 'Tập đoàn DEF', 2, 'pending'),
(1, 'Phạm Thị D', '0933444555', '0933444555', 'Quận 7, TP.HCM', 'Khách tham dự', 'Đại học QG', NULL, 'pending'),
(1, 'Hoàng Văn E', '+84 944 555 666', '0944555666', 'Thủ Đức, TP.HCM', 'Khách tham dự', 'Freelancer', NULL, 'pending');

-- 5. Thêm lượt Check-in mẫu (1 matched, 1 walk_in)
-- Lượt checkin khớp với Nguyễn Văn A
INSERT INTO `ci_checkins` (`event_id`, `guest_id`, `full_name_entered`, `phone_entered`, `normalized_phone`, `address_entered`, `match_status`, `checkin_time`) VALUES
(1, 1, 'Nguyễn Văn A', '0912345678', '0912345678', 'Quận 1', 'matched', NOW());

-- Cập nhật trạng thái khách dự kiến thành matched
UPDATE `ci_guests` SET `status` = 'matched' WHERE `id` = 1;

-- Lượt checkin không khớp (Walk in)
INSERT INTO `ci_checkins` (`event_id`, `guest_id`, `full_name_entered`, `phone_entered`, `normalized_phone`, `address_entered`, `match_status`, `checkin_time`) VALUES
(1, NULL, 'Vương Trí G', '0999888777', '0999888777', 'Quận 5, TP.HCM', 'walk_in', NOW());

