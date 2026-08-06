<?php
/**
 * Thẻ Meta & CSS/JS cho Mobile Web App Standalone (PWA) & Excel Table Zoom
 */
?>
<!-- PWA & Mobile Web App Standalone Meta Tags -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="PMT Checkin">
<meta name="theme-color" content="#2563eb">
<link rel="manifest" href="<?php echo url('manifest.json'); ?>">
<link rel="apple-touch-icon" href="<?php echo url('img/logo pmt.png'); ?>">

<!-- Stylesheet & Script PWA + Excel Table Zoom -->
<link rel="stylesheet" href="<?php echo url('assets/css/pwa-app.css'); ?>">
<link rel="stylesheet" href="<?php echo url('assets/css/excel-table-mobile.css'); ?>">
<script src="<?php echo url('assets/js/pwa-standalone.js'); ?>" defer></script>
<script src="<?php echo url('assets/js/excel-table-zoom.js'); ?>" defer></script>
