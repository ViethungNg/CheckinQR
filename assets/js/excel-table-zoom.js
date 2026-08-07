/**
 * excel-table-zoom.js - Quản lý Thu Phóng Zoom In/Out và Chuyển Đổi Chế Độ Xem Bảng Excel
 */
(function () {
  'use strict';

  var ZOOM_KEY = 'pmt_excel_table_zoom';
  var VIEW_MODE_KEY = 'pmt_excel_view_mode';

  // Xóa bộ nhớ tạm zoom cũ và luôn giữ mặc định chuẩn 100% không can thiệp JS
  try {
    localStorage.removeItem(ZOOM_KEY);
  } catch(e) {}

  function getSavedZoom() {
    return 100;
  }

  function setSavedZoom(val) {
    localStorage.setItem(ZOOM_KEY, val);
  }

  // Khởi tạo các sự kiện khi DOM nạp xong
  document.addEventListener('DOMContentLoaded', function () {
    initTableZoomControls();
  });

  function initTableZoomControls() {
    var containers = document.querySelectorAll('.excel-table-container');
    if (!containers.length) return;

    var currentZoom = getSavedZoom();

    containers.forEach(function (container) {
      var wrapper = container.querySelector('.excel-zoom-wrapper');
      var toolbar = container.previousElementSibling;

      if (!toolbar || !toolbar.classList.contains('table-toolbar')) return;

      var btnZoomIn = toolbar.querySelector('.btn-zoom-in');
      var btnZoomOut = toolbar.querySelector('.btn-zoom-out');
      var btnZoomReset = toolbar.querySelector('.btn-zoom-reset');
      var badgeLevel = toolbar.querySelector('.zoom-level-badge');
      var btnViewExcel = toolbar.querySelector('.btn-view-excel');
      var btnViewCard = toolbar.querySelector('.btn-view-card');

      function applyZoom(zoomPercent) {
        currentZoom = Math.min(160, Math.max(50, zoomPercent));
        setSavedZoom(currentZoom);

        if (badgeLevel) {
          badgeLevel.textContent = currentZoom + '%';
        }

        if (wrapper) {
          var table = wrapper.querySelector('table');
          if (table) {
            // Khi zoom ở mức chuẩn 100%, giữ nguyên phông chữ native của CSS không can thiệp JS để tránh hiệu ứng khựng/giật khi bấm lọc
            if (currentZoom === 100) {
              table.style.fontSize = '';
            } else {
              var baseFontSize = 12.5;
              var newFontSize = (baseFontSize * (currentZoom / 100)).toFixed(1);
              table.style.fontSize = newFontSize + 'px';
            }
            wrapper.style.transform = 'none';
            wrapper.style.width = '100%';
          }
        }
      }

      // Đăng ký sự kiện nút Zoom In
      if (btnZoomIn) {
        btnZoomIn.addEventListener('click', function () {
          applyZoom(currentZoom + 10);
        });
      }

      // Đăng ký sự kiện nút Zoom Out
      if (btnZoomOut) {
        btnZoomOut.addEventListener('click', function () {
          applyZoom(currentZoom - 10);
        });
      }

      // Đăng ký sự kiện nút Reset
      if (btnZoomReset) {
        btnZoomReset.addEventListener('click', function () {
          applyZoom(100);
        });
      }

      // Áp dụng ngay mức Zoom mặc định
      applyZoom(currentZoom);
    });
  }

})();
