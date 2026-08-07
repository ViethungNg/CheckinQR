/**
 * pwa-standalone.js - Xử lý nâng cấp trải nghiệm Mobile Web App (PWA Standalone)
 * Đảm bảo ứng dụng chạy toàn màn hình, không bị nảy sang Safari và tự động quản lý thanh điều hướng đáy.
 */

(function () {
  'use strict';

  // 1. Đăng ký Service Worker cho PWA
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function (reg) {
          console.log('[PWA] Service Worker registered successfully with scope:', reg.scope);
        })
        .catch(function (err) {
          console.warn('[PWA] Service Worker registration failed:', err);
        });
    });
  }

  // 2. Giữ App trong chế độ Standalone trên iOS (Ngăn bấm link bị nảy ra trình duyệt Safari)
  var isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

  if (isStandalone) {
    document.addEventListener('click', function (e) {
      var anchor = e.target.closest('a');
      if (!anchor) return;

      var href = anchor.getAttribute('href');
      var target = anchor.getAttribute('target');

      // Nếu là link mở tab mới, javascript:, tel:, mailto: hoặc anchor nội bộ (#) thì bỏ qua
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:') || target === '_blank') {
        return;
      }

      // Kiểm tra tên miền nội bộ
      var destination = new URL(anchor.href, window.location.origin);
      if (destination.origin === window.location.origin) {
        e.preventDefault();
        window.location.href = anchor.href;
      }
    }, false);
  }

  // 3. Highlight tab đang active ở Bottom Navigation Bar
  document.addEventListener('DOMContentLoaded', function () {
    var currentPath = window.location.pathname;
    var navItems = document.querySelectorAll('.pmt-bottom-nav .nav-item');

    navItems.forEach(function (item) {
      var href = item.getAttribute('href');
      if (!href) return;

      var navUrl = new URL(href, window.location.origin);
      if (currentPath === navUrl.pathname || (currentPath.endsWith('/') && navUrl.pathname.includes('index.php'))) {
        item.classList.add('active');
      } else {
        var cleanCurrent = currentPath.split('/').pop();
        var cleanNav = href.split('/').pop().split('?')[0];
        if (cleanCurrent && cleanNav && cleanCurrent === cleanNav) {
          item.classList.add('active');
        }
      }

      // Phản hồi cảm ứng tức thì (Instant 0ms Feedback) khi chạm/click tab navigation
      item.addEventListener('click', function () {
        navItems.forEach(function (nav) { nav.classList.remove('active'); });
        item.classList.add('active');
      });
    });
  });

})();
