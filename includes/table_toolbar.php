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
</div>
