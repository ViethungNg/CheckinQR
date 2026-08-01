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

/**
 * Lấy cấu hình cột mặc định cho 3 bảng
 */
function getDefaultTableColumns(string $tableName): array {
    switch ($tableName) {
        case 'dashboard':
            return [
                ['key' => 'customer_code',   'label' => 'Mã KH',            'visible' => true],
                ['key' => 'full_name',       'label' => 'Họ và tên',        'visible' => true],
                ['key' => 'phone',           'label' => 'Số điện thoại',    'visible' => true],
                ['key' => 'organization',    'label' => 'Đơn vị / Đại lý',  'visible' => true],
                ['key' => 'table_name',      'label' => 'Bàn ngồi',         'visible' => true],
                ['key' => 'lucky_draw_code', 'label' => 'Mã trúng thưởng',  'visible' => true],
                ['key' => 'checkin_time',    'label' => 'Thời gian checkin','visible' => true],
            ];
        case 'guests':
            return [
                ['key' => 'customer_code',   'label' => 'Mã KH',            'visible' => true],
                ['key' => 'full_name',       'label' => 'Họ và tên',        'visible' => true],
                ['key' => 'phone',           'label' => 'Số điện thoại',    'visible' => true],
                ['key' => 'organization',    'label' => 'Đơn vị / Đại lý',  'visible' => true],
                ['key' => 'table_name',      'label' => 'Bàn ngồi',         'visible' => true],
                ['key' => 'lucky_draw_code', 'label' => 'Mã trúng thưởng',  'visible' => true],
                ['key' => 'status',          'label' => 'Trạng thái',       'visible' => true],
                ['key' => 'actions',         'label' => 'Thao tác',         'visible' => true],
            ];
        case 'checkins':
            return [
                ['key' => 'customer_code',   'label' => 'Mã KH',            'visible' => true],
                ['key' => 'full_name',       'label' => 'Họ và tên',        'visible' => true],
                ['key' => 'phone',           'label' => 'Số điện thoại',    'visible' => true],
                ['key' => 'organization',    'label' => 'Đơn vị / Đại lý',  'visible' => true],
                ['key' => 'table_name',      'label' => 'Bàn ngồi',         'visible' => true],
                ['key' => 'lucky_draw_code', 'label' => 'Mã trúng thưởng',  'visible' => true],
                ['key' => 'checkin_time',    'label' => 'Thời gian checkin','visible' => true],
                ['key' => 'method',          'label' => 'Phương thức',      'visible' => true],
                ['key' => 'actions',         'label' => 'Thao tác',         'visible' => true],
            ];
        default:
            return [];
    }
}

/**
 * Lấy cấu hình cột bảng hiện tại từ CSDL (gồm thứ tự và trạng thái ẩn/hiện)
 */
function getTableColumnsConfig(string $tableName): array {
    $defaults = getDefaultTableColumns($tableName);
    if (empty($defaults)) return [];

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute(['table_config_' . $tableName]);
        $row = $stmt->fetch();

        if ($row && !empty($row['setting_value'])) {
            $saved = json_decode($row['setting_value'], true);
            if (is_array($saved) && !empty($saved)) {
                // Map nhãn gốc để phòng trường hợp đổi nhãn
                $defaultKeysMap = [];
                foreach ($defaults as $d) {
                    $defaultKeysMap[$d['key']] = $d['label'];
                }

                $result = [];
                $savedKeys = [];

                foreach ($saved as $item) {
                    if (isset($item['key']) && isset($defaultKeysMap[$item['key']])) {
                        $result[] = [
                            'key'     => $item['key'],
                            'label'   => $defaultKeysMap[$item['key']],
                            'visible' => !empty($item['visible'])
                        ];
                        $savedKeys[$item['key']] = true;
                    }
                }

                // Bổ sung các cột mới nếu mặc định có mà trong saved chưa có
                foreach ($defaults as $d) {
                    if (!isset($savedKeys[$d['key']])) {
                        $result[] = $d;
                    }
                }

                return $result;
            }
        }
    } catch (\Throwable $e) {}

    return $defaults;
}

/**
 * Lấy cài đặt bật/tắt thông báo cá nhân của User
 */
function getUserNotificationSetting(int $userId): bool {
    return true; // Luôn luôn bật thông báo cho tất cả người dùng
}

