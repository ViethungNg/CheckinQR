/**
 * excel-table-zoom.js - Quản lý Thu Phóng Zoom In/Out và Chuyển Đổi Chế Độ Xem Bảng Excel
 */
(function () {
  'use strict';

  var ZOOM_KEY = 'pmt_excel_table_zoom';
  var VIEW_MODE_KEY = 'pmt_excel_view_mode';

  // Lấy giá trị zoom lưu từ trước hoặc mặc định 100%
  function getSavedZoom() {
    var saved = localStorage.getItem(ZOOM_KEY);
    var val = parseInt(saved, 10);
    return (val >= 50 && val <= 160) ? val : 100;
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
            // Áp dụng tỉ lệ font-size & cell padding linh hoạt theo zoomPercent
            var baseFontSize = 11;
            var newFontSize = (baseFontSize * (currentZoom / 100)).toFixed(1);
            table.style.fontSize = newFontSize + 'px';

            // Thu phóng chiều rộng toàn bảng nếu zoom out dưới 100%
            if (currentZoom < 100) {
              wrapper.style.transform = 'scale(' + (currentZoom / 100) + ')';
              wrapper.style.transformOrigin = 'top left';
              wrapper.style.width = (100 / (currentZoom / 100)) + '%';
            } else {
              wrapper.style.transform = 'none';
              wrapper.style.width = '100%';
            }
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
