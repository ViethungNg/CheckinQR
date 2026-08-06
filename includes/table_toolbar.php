<?php
/**
 * Component Thanh Công Cụ Bảng Dữ Liệu (Excel Table Toolbar)
 * Cung cấp nút Thu Phóng (Zoom in / Zoom out / Reset) và hiển thị thông tin bảng.
 */
$title = $tableTitle ?? 'Danh Sách Bảng Tính Excel';
?>
<div class="table-toolbar">
    <div class="table-toolbar-left">
        <span style="color: #334155; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; font-size: 12px;">
            📊 <?php echo esc($title); ?>
        </span>
    </div>
    <div class="table-toolbar-right">
        <span style="font-size: 11px; color: #64748b; font-weight: 500; margin-right: 2px;">Thu phóng:</span>
        <div class="zoom-btn-group">
            <button type="button" class="zoom-btn btn-zoom-out" title="Thu nhỏ để nhìn tổng thể toàn bộ cột (Zoom Out)">🔍−</button>
            <span class="zoom-level-badge">100%</span>
            <button type="button" class="zoom-btn btn-zoom-in" title="Phóng to để đọc chi tiết (Zoom In)">🔍+</button>
            <button type="button" class="zoom-btn btn-zoom-reset" title="Đặt lại tỉ lệ chuẩn 100%">🔄</button>
        </div>
    </div>
</div>
