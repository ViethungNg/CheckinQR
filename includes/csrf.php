<?php
declare(strict_types=1);

/**
 * Sinh CSRF Token và lưu vào session
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Trả về chuỗi HTML input hidden chứa CSRF Token
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . esc($token) . '">';
}

/**
 * Xác thực CSRF Token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Kiểm tra CSRF token từ Request, nếu sai thì die
 */
function requireCsrfToken(): void {
    if (isPost()) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfToken($token)) {
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                jsonResponse(['status' => 'error', 'message' => 'Lỗi bảo mật (CSRF Token không hợp lệ)'], 403);
            }
            die('Lỗi bảo mật: Token không hợp lệ.');
        }
    }
}
