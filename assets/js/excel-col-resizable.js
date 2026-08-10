/**
 * Excel-Style Interactive Column Resizer for HTML Tables
 * Features: 60 FPS Smooth Dragging, Visual Edge Resizer, LocalStorage Width Persistence
 */

(function () {
    'use strict';

    function initTableColumnResizers() {
        const tables = document.querySelectorAll('table');
        
        tables.forEach((table, tableIdx) => {
            if (table.dataset.resizerInitialized === 'true') return;
            table.dataset.resizerInitialized = 'true';

            const storageKey = 'excel_col_widths_' + (table.id || 'tbl_' + tableIdx + '_' + location.pathname.replace(/[^a-zA-Z0-9]/g, '_'));
            const savedWidths = JSON.parse(localStorage.getItem(storageKey) || '{}');

            const headerRow = table.querySelector('thead tr');
            if (!headerRow) return;

            const cols = headerRow.children;
            const numCols = cols.length;

            table.style.tableLayout = 'fixed';

            for (let i = 0; i < numCols; i++) {
                const th = cols[i];
                th.style.position = 'relative';
                th.style.overflow = 'hidden';
                th.style.textOverflow = 'ellipsis';
                th.style.whiteSpace = 'nowrap';

                // Restore saved width if exists
                if (savedWidths[i]) {
                    th.style.width = savedWidths[i] + 'px';
                } else if (!th.style.width) {
                    const currentWidth = th.offsetWidth || 120;
                    th.style.width = Math.max(currentWidth, 60) + 'px';
                }

                // Avoid resizer on last column
                if (i === numCols - 1) continue;

                // Create resizer bar element
                const resizer = document.createElement('div');
                resizer.className = 'excel-col-resizer';
                th.appendChild(resizer);

                createResizableColumn(th, resizer, i, storageKey);
            }
        });
    }

    function createResizableColumn(th, resizer, index, storageKey) {
        let x = 0;
        let w = 0;

        const mouseDownHandler = function (e) {
            e.preventDefault();
            e.stopPropagation();

            x = e.clientX;
            w = th.offsetWidth;

            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            document.addEventListener('mousemove', mouseMoveHandler);
            document.addEventListener('mouseup', mouseUpHandler);
        };

        const mouseMoveHandler = function (e) {
            const dx = e.clientX - x;
            const newWidth = Math.max(40, w + dx);
            th.style.width = newWidth + 'px';
        };

        const mouseUpHandler = function () {
            resizer.classList.remove('resizing');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            document.removeEventListener('mousemove', mouseMoveHandler);
            document.removeEventListener('mouseup', mouseUpHandler);

            // Save new column widths to LocalStorage
            saveTableColumnWidths(th.closest('table'), storageKey);
        };

        resizer.addEventListener('mousedown', mouseDownHandler);
    }

    function saveTableColumnWidths(table, storageKey) {
        if (!table || !storageKey) return;
        const cols = table.querySelectorAll('thead tr > th');
        const widths = {};
        cols.forEach((th, idx) => {
            widths[idx] = th.offsetWidth;
        });
        try {
            localStorage.setItem(storageKey, JSON.stringify(widths));
        } catch (e) {
            console.error('Save column widths error:', e);
        }
    }

    // Initialize on DOM ready & dynamic updates
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableColumnResizers);
    } else {
        initTableColumnResizers();
    }

    window.addEventListener('load', initTableColumnResizers);
    window.addEventListener('dbRealtimeChange', () => setTimeout(initTableColumnResizers, 200));

    // Observe dynamic table insertions
    const observer = new MutationObserver(() => {
        initTableColumnResizers();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    window.initTableColumnResizers = initTableColumnResizers;
})();
