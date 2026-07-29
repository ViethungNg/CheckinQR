<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Vui lòng đăng nhập để thực hiện thao tác này!']);
}

if (!isPost()) {
    jsonResponse(['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ!']);
}

try {
    requireCsrfToken();
} catch (\Throwable $e) {
    jsonResponse(['status' => 'error', 'message' => 'Phiên làm việc hết hạn hoặc token CSRF không hợp lệ!']);
}

$userId = (int)($_SESSION['admin_id'] ?? 0);
$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    jsonResponse(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ tất cả các trường mật khẩu!']);
}

if ($newPassword !== $confirmPassword) {
    jsonResponse(['status' => 'error', 'message' => 'Mật khẩu mới và xác nhận mật khẩu không khớp nhau!']);
}

if (strlen($newPassword) < 4) {
    jsonResponse(['status' => 'error', 'message' => 'Mật khẩu mới phải có ít nhất 4 ký tự!']);
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['status' => 'error', 'message' => 'Tài khoản không tồn tại trên hệ thống!']);
}

// Kiểm tra mật khẩu hiện tại (hỗ trợ cả chuỗi trực tiếp và password_hash)
$isValidCurrent = ($currentPassword === $user['password_hash']) || password_verify($currentPassword, $user['password_hash']);

if (!$isValidCurrent) {
    jsonResponse(['status' => 'error', 'message' => 'Mật khẩu hiện tại không chính xác. Vui lòng kiểm tra lại!']);
}

// Cập nhật mật khẩu mới
$updateStmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$newPassword, $userId]);

jsonResponse([
    'status' => 'success',
    'message' => 'Đổi mật khẩu thành công!'
]);
