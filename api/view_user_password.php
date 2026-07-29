<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    jsonResponse(['status' => 'error', 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
}

if (!isPost()) {
    jsonResponse(['status' => 'error', 'message' => 'Phương thức không hợp lệ!']);
}

try {
    requireCsrfToken();
} catch (\Throwable $e) {
    jsonResponse(['status' => 'error', 'message' => 'Phiên làm việc hết hạn hoặc token CSRF không hợp lệ!']);
}

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$targetUserId = (int)($_POST['target_user_id'] ?? 0);
$adminPassword = trim($_POST['admin_password'] ?? '');

if (empty($adminPassword) || $targetUserId <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'Vui lòng nhập mật khẩu Admin của bạn!']);
}

$db = Database::getConnection();

// 1. Kiểm tra mật khẩu tài khoản Admin đang đăng nhập
$stmtAdmin = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$adminId]);
$adminUser = $stmtAdmin->fetch();

if (!$adminUser) {
    jsonResponse(['status' => 'error', 'message' => 'Tài khoản Admin không tồn tại!']);
}

$isValidAdminPass = ($adminPassword === $adminUser['password_hash']) || password_verify($adminPassword, $adminUser['password_hash']);

if (!$isValidAdminPass) {
    jsonResponse(['status' => 'error', 'message' => 'Mật khẩu Admin không chính xác. Vui lòng thử lại!']);
}

// 2. Lấy mật khẩu của user được yêu cầu
$stmtTarget = $db->prepare("SELECT username, full_name, password_hash FROM users WHERE id = ? LIMIT 1");
$stmtTarget->execute([$targetUserId]);
$targetUser = $stmtTarget->fetch();

if (!$targetUser) {
    jsonResponse(['status' => 'error', 'message' => 'Tài khoản cần xem không tồn tại!']);
}

jsonResponse([
    'status' => 'success',
    'message' => 'Xác thực Admin thành công!',
    'username' => esc($targetUser['username']),
    'full_name' => esc($targetUser['full_name']),
    'password' => esc($targetUser['password_hash'])
]);
