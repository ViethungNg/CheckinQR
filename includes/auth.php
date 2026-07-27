<?php
declare(strict_types=1);

/**
 * Đăng nhập người dùng admin
 */
function loginAdmin(string $username, string $password): bool {
    $db = Database::getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && $password === $user['password_hash']) {
        // Tránh Session Fixation
        session_regenerate_id(true);
        
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role'];
        
        // Cập nhật last login
        $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        return true;
    }
    
    return false;
}

/**
 * Đăng xuất admin
 */
function logoutAdmin(): void {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_role']);
    session_destroy();
}

/**
 * Kiểm tra xem admin đã đăng nhập chưa
 */
function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']);
}

/**
 * Bắt buộc đăng nhập, nếu chưa đăng nhập sẽ chuyển hướng về trang login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(url('/admin/login.php'));
    }
}

/**
 * Kiểm tra xem người dùng hiện tại có phải admin/super_admin không
 */
function isAdmin(): bool {
    $role = $_SESSION['admin_role'] ?? 'staff';
    return in_array($role, ['admin', 'super_admin']);
}

/**
 * Bắt buộc quyền admin mới được truy cập, nếu không sẽ báo lỗi
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        die('Bạn không có quyền truy cập trang này. Vui lòng liên hệ Quản trị viên.');
    }
}
