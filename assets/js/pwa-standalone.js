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

  // 3. Highlight tab active & Phản hồi cảm ứng 0ms siêu tốc ở Bottom Navigation Bar
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

      // Hàm chuyển hướng tức thì 0ms (Xóa bỏ 300ms tap delay trên mobile + Hiện Spinner xoay tròn)
      var handleFastNav = function (e) {
        if (item._navTriggered) return;
        item._navTriggered = true;

        navItems.forEach(function (nav) { nav.classList.remove('active'); });
        item.classList.add('active');

        var targetUrl = item.href || href;
        if (targetUrl && targetUrl !== window.location.href) {
          if (window.showGlobalSpinner) {
            window.showGlobalSpinner('Đang nạp dữ liệu...');
          }
          window.location.href = targetUrl;
        }

        setTimeout(function() { item._navTriggered = false; }, 500);
      };

      item.addEventListener('touchstart', handleFastNav, { passive: true });
      item.addEventListener('click', handleFastNav);
    });
  });

  // 4. Quản lý Hiệu ứng Loading Spinner xoay tròn toàn cục
  function createGlobalSpinner() {
    if (document.getElementById('pmtGlobalSpinner')) return;
    
    var spinnerEl = document.createElement('div');
    spinnerEl.id = 'pmtGlobalSpinner';
    spinnerEl.innerHTML = '<div class="pmt-spinner-card">' +
                          '  <div class="pmt-spinner-ring"></div>' +
                          '  <div class="pmt-spinner-text">Đang tải...</div>' +
                          '</div>';
    document.body.appendChild(spinnerEl);
  }

  window.showGlobalSpinner = function (msg) {
    createGlobalSpinner();
    var spinner = document.getElementById('pmtGlobalSpinner');
    if (spinner) {
      var textEl = spinner.querySelector('.pmt-spinner-text');
      if (textEl) textEl.textContent = msg || 'Đang tải...';
      spinner.classList.add('show');
    }
  };

  window.hideGlobalSpinner = function () {
    var spinner = document.getElementById('pmtGlobalSpinner');
    if (spinner) {
      spinner.classList.remove('show');
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    createGlobalSpinner();
    window.hideGlobalSpinner();

    // Attach global spinner to ALL internal link clicks (sidebar nav, bottom nav, page links)
    document.addEventListener('click', function (e) {
      var anchor = e.target.closest('a[href]');
      if (!anchor) return;

      var href = anchor.getAttribute('href');
      var target = anchor.getAttribute('target');

      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:') || target === '_blank') {
        return;
      }

      try {
        var dest = new URL(anchor.href, window.location.origin);
        if (dest.origin === window.location.origin) {
          if (dest.pathname !== window.location.pathname || dest.search !== window.location.search) {
            if (window.showGlobalSpinner) {
              window.showGlobalSpinner('Đang nạp dữ liệu...');
            }
          }
        }
      } catch (err) {}
    }, true);

    var filterForms = document.querySelectorAll('form');
    filterForms.forEach(function (form) {
      form.addEventListener('submit', function () {
        if (window.showGlobalSpinner) window.showGlobalSpinner('Đang xử lý...');
      });
    });
  });

  window.addEventListener('pageshow', function () {
    window.hideGlobalSpinner();
  });

  // 5. System-wide Ctrl + K Shortcut to auto-focus Search/Filter inputs
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      
      // Find the primary search/filter input on current page
      var filterInput = document.querySelector('#dashboard-search-input, #search-input, #checkin-search-input, #table-search-input, #event-search-input, #user-search, input[name="search"], input[type="text"][placeholder*="Tìm"], input[type="search"]');
      
      if (filterInput) {
        filterInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        filterInput.focus();
        if (typeof filterInput.select === 'function') filterInput.select();
      }
    } else if (e.key === 'Escape') {
      var activeEl = document.activeElement;
      if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'SELECT')) {
        activeEl.blur();
      }
    }
  // 6. Disable double-tap auto-zoom on mobile while preserving native 2-finger pinch zoom
  var lastTouchEnd = 0;
  document.addEventListener('touchend', function (e) {
    var now = Date.now();
    if (now - lastTouchEnd <= 300) {
      var target = e.target;
      if (target && !target.closest('input, textarea, select')) {
        e.preventDefault();
      }
    }
    lastTouchEnd = now;
  }, false);

})();
