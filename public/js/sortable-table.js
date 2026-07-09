/**
 * Generic click-to-sort for report tables.
 * Add class="sortable-table" to any <table> with a <thead>/<tbody>.
 * Add class="no-sort" to a <th> to exclude that column.
 */
(function () {
    function parseCellValue(text) {
        text = (text || '').trim();
        if (text === '' || text === '-') {
            return { type: 'empty', value: '' };
        }

        var cleaned = text.replace(/[$,%]/g, '').trim();
        var negative = false;
        if (/^\(.*\)$/.test(cleaned)) {
            negative = true;
            cleaned = cleaned.slice(1, -1).trim();
        }

        if (/^-?\d+(\.\d+)?$/.test(cleaned)) {
            var num = parseFloat(cleaned);
            if (negative) num = -Math.abs(num);
            return { type: 'number', value: num };
        }

        if (/\d{4}/.test(text)) {
            var dateVal = Date.parse(text);
            if (!isNaN(dateVal)) {
                return { type: 'date', value: dateVal };
            }
        }

        return { type: 'string', value: text.toLowerCase() };
    }

    function getCellText(row, index) {
        var cell = row.cells[index];
        if (!cell) return '';
        return cell.textContent.trim();
    }

    function compareRows(a, b, index, dir) {
        var va = parseCellValue(getCellText(a, index));
        var vb = parseCellValue(getCellText(b, index));

        if (va.type === 'empty' && vb.type === 'empty') return 0;
        if (va.type === 'empty') return 1;
        if (vb.type === 'empty') return -1;

        var result;
        if (va.type === 'number' && vb.type === 'number') {
            result = va.value - vb.value;
        } else if (va.type === 'date' && vb.type === 'date') {
            result = va.value - vb.value;
        } else {
            result = String(va.value).localeCompare(String(vb.value), undefined, { numeric: true, sensitivity: 'base' });
        }

        return dir === 'asc' ? result : -result;
    }

    function initTable(table) {
        var thead = table.tHead;
        var tbody = table.tBodies[0];
        if (!thead || !tbody) return;

        var headerRow = thead.rows[thead.rows.length - 1];
        if (!headerRow) return;

        Array.prototype.forEach.call(headerRow.cells, function (th, index) {
            if (th.classList.contains('no-sort') || !th.textContent.trim()) return;

            th.classList.add('sortable-th');
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';

            var icon = document.createElement('span');
            icon.className = 'sort-icon';
            icon.style.marginLeft = '4px';
            icon.style.opacity = '0.4';
            icon.style.fontSize = '10px';
            icon.textContent = '↕';
            th.appendChild(icon);

            th.addEventListener('click', function () {
                var newDir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';

                Array.prototype.forEach.call(headerRow.cells, function (h) {
                    h.removeAttribute('data-sort-dir');
                    var hIcon = h.querySelector('.sort-icon');
                    if (hIcon) {
                        hIcon.textContent = '↕';
                        hIcon.style.opacity = '0.4';
                    }
                });

                th.setAttribute('data-sort-dir', newDir);
                icon.textContent = newDir === 'asc' ? '▲' : '▼';
                icon.style.opacity = '1';

                var rows = Array.prototype.slice.call(tbody.querySelectorAll(':scope > tr'));
                rows.sort(function (a, b) {
                    return compareRows(a, b, index, newDir);
                });
                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table.sortable-table').forEach(initTable);
    });
})();
