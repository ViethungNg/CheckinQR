<?php
declare(strict_types=1);

/**
 * Escape string để chống XSS
 */
function esc(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Trả về JSON response cho API
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Điều hướng trang
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Lấy URL tuyệt đối
 */
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Chuẩn hóa số điện thoại
 * - Xóa khoảng trắng, dấu chấm, gạch ngang
 * - Chuyển +84 về đầu số 0
 * - Chỉ giữ lại chữ số
 */
function normalizePhone(string $phone): string {
    // Chỉ giữ lại số và dấu + ở đầu
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // Đổi +84 thành 0
    if (strpos($phone, '+84') === 0) {
        $phone = '0' . substr($phone, 3);
    }
    
    // Nếu bắt đầu bằng 84 nhưng không có +, đổi thành 0
    if (strpos($phone, '84') === 0 && strlen($phone) > 10) {
        $phone = '0' . substr($phone, 2);
    }

    return $phone;
}

/**
 * Kiểm tra xem request có phải là POST không
 */
function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}
