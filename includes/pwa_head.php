<?php
/**
 * Thẻ Meta & CSS/JS cho Mobile Web App Standalone (PWA) & Excel Table Zoom
 */
?>
<!-- Viewport Fit Cover cho màn hình iPhone 15 Pro Max & viền bo cong -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, minimum-scale=0.5, user-scalable=yes, viewport-fit=cover">

<!-- PWA & Mobile Web App Standalone Meta Tags -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="PMT Checkin">
<meta name="theme-color" content="#2563eb">
<link rel="manifest" href="<?php echo url('manifest.json'); ?>">
<link rel="apple-touch-icon" href="<?php echo url('img/logo pmt.png'); ?>">

<!-- Google Fonts: Google Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Google+Sans+Flex:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Stylesheet & Script PWA + Excel Table Zoom -->
<link rel="stylesheet" href="<?php echo url('assets/css/pwa-app.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/pwa-app.css'); ?>">
<link rel="stylesheet" href="<?php echo url('assets/css/excel-table-mobile.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/excel-table-mobile.css'); ?>">
<script src="<?php echo url('assets/js/pwa-standalone.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/pwa-standalone.js'); ?>" defer></script>
<script src="<?php echo url('assets/js/excel-table-zoom.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/excel-table-zoom.js'); ?>" defer></script>
<script src="<?php echo url('assets/js/excel-col-resizable.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/excel-col-resizable.js'); ?>" defer></script>
