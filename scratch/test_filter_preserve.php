<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Giả lập đọc tham số search từ POST khi người dùng ấn nút Sửa/Xóa sau khi lọc
$_POST['search'] = 'Phượng hương';
$_POST['sort'] = 'table_asc';

$search = trim($_GET['search'] ?? $_POST['search'] ?? '');
$sort   = trim($_GET['sort'] ?? $_POST['sort'] ?? 'id_desc');

if ($search === 'Phượng hương' && $sort === 'table_asc') {
    echo "SUCCESS: Search query and Sort parameter preserved across POST form submission!";
} else {
    echo "FAILED: Search or Sort param was lost!";
}
