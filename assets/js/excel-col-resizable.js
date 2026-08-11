/**
 * Column resizing is intentionally disabled.
 * The old Excel-like behavior caused accidental blue selection/highlight states
 * and cramped table widths, especially in admin data grids.
 */
(function () {
    'use strict';

    function cleanupExcelColumnResize() {
        document.querySelectorAll('.excel-col-resizer').forEach(function (el) {
            el.remove();
        });

        document.querySelectorAll('table').forEach(function (table) {
            table.dataset.resizerInitialized = 'disabled';
            table.style.tableLayout = '';

            table.querySelectorAll('thead th').forEach(function (th) {
                th.style.width = '';
                th.style.position = '';
                th.style.overflow = '';
                th.style.textOverflow = '';
                th.style.whiteSpace = '';
                th.removeAttribute('tabindex');
            });
        });

        try {
            Object.keys(localStorage).forEach(function (key) {
                if (key.indexOf('excel_col_widths_') === 0) {
                    localStorage.removeItem(key);
                }
            });
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cleanupExcelColumnResize);
    } else {
        cleanupExcelColumnResize();
    }

    window.addEventListener('load', cleanupExcelColumnResize);
    window.addEventListener('dbRealtimeChange', function () {
        setTimeout(cleanupExcelColumnResize, 50);
    });

    window.initTableColumnResizers = cleanupExcelColumnResize;
})();
