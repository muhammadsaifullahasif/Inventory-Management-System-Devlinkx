{{-- Column Toggle Dropdown --}}
{{-- Usage: @include('partials.column-toggle', ['tableId' => 'ordersTable', 'cookieName' => 'orders_columns', 'columns' => $columns]) --}}
{{-- $columns should be an array like: [['key' => 'id', 'label' => 'ID', 'default' => true], ...] --}}

@php
    $uniqueId = $tableId ?? 'dataTable';
@endphp

<div class="dropdown d-inline-block column-toggle-dropdown">
    <a href="javascript:void(0);" class="btn btn-light-brand btn-sm" data-bs-toggle="dropdown" data-bs-display="static" id="columnToggleBtn_{{ $uniqueId }}">
        <i class="feather-columns me-1"></i> Columns <i class="feather-chevron-down ms-1 fs-11"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-lg-end p-3 column-toggle-menu" id="columnToggleMenu_{{ $uniqueId }}" style="min-width: 220px; max-height: 400px; overflow-y: auto;">
        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
            <span class="fw-semibold small">Show/Hide Columns</span>
            <a href="javascript:void(0);" class="text-primary small column-reset-btn" data-table="{{ $tableId ?? 'dataTable' }}" data-cookie="{{ $cookieName ?? 'table_columns' }}">
                Reset
            </a>
        </div>
        @foreach($columns as $column)
            <div class="form-check mb-1">
                <input class="form-check-input column-toggle-checkbox"
                       type="checkbox"
                       id="col_{{ $uniqueId }}_{{ $column['key'] }}"
                       data-column="{{ $column['key'] }}"
                       data-table="{{ $tableId ?? 'dataTable' }}"
                       data-cookie="{{ $cookieName ?? 'table_columns' }}"
                       data-default="{{ $column['default'] ?? true ? 'true' : 'false' }}"
                       {{ $column['default'] ?? true ? 'checked' : '' }}>
                <label class="form-check-label small" for="col_{{ $uniqueId }}_{{ $column['key'] }}">
                    {{ $column['label'] }}
                </label>
            </div>
        @endforeach
    </div>
</div>

@once
@push('scripts')
<script>
$(document).ready(function() {
    // Column toggle dropdown - manual toggle
    $(document).on('click', '.column-toggle-dropdown > a', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $dropdown = $(this).parent();
        var $menu = $dropdown.find('.column-toggle-menu');

        // Close other column toggle dropdowns
        $('.column-toggle-menu.show').not($menu).removeClass('show');

        // Toggle this menu
        $menu.toggleClass('show');
    });

    // Close column toggle dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.column-toggle-dropdown').length) {
            $('.column-toggle-menu.show').removeClass('show');
        }
    });

    // Prevent dropdown from closing when clicking inside (on checkboxes)
    $(document).on('click', '.column-toggle-menu', function(e) {
        e.stopPropagation();
    });

    // Cookie helper functions
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value)) + expires + '; path=/';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                try {
                    return JSON.parse(decodeURIComponent(c.substring(nameEQ.length)));
                } catch (e) {
                    return null;
                }
            }
        }
        return null;
    }

    // Apply column visibility from cookies on page load (or defaults if no cookie)
    function applyColumnVisibility(tableId, cookieName) {
        var savedColumns = getCookie(cookieName);

        $('.column-toggle-checkbox[data-table="' + tableId + '"]').each(function() {
            var columnKey = $(this).data('column');
            var isVisible;

            if (savedColumns && savedColumns.hasOwnProperty(columnKey)) {
                // Use saved preference from cookie
                isVisible = savedColumns[columnKey];
            } else {
                // Use default value from data attribute
                isVisible = $(this).data('default') === true || $(this).data('default') === 'true';
            }

            $(this).prop('checked', isVisible);
            toggleColumn(tableId, columnKey, isVisible);
        });
    }

    // Toggle column visibility
    function toggleColumn(tableId, columnKey, isVisible) {
        var $table = $('#' + tableId);
        var columnIndex = $table.find('thead th[data-column="' + columnKey + '"]').index();

        if (columnIndex >= 0) {
            if (isVisible) {
                $table.find('thead th').eq(columnIndex).show();
                $table.find('tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).show();
                });
            } else {
                $table.find('thead th').eq(columnIndex).hide();
                $table.find('tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).hide();
                });
            }
        }
    }

    // Save column visibility to cookie
    function saveColumnVisibility(tableId, cookieName) {
        var columns = {};
        $('.column-toggle-checkbox[data-table="' + tableId + '"]').each(function() {
            columns[$(this).data('column')] = $(this).is(':checked');
        });
        setCookie(cookieName, columns, 365); // Save for 1 year
    }

    // Handle checkbox change
    $(document).on('change', '.column-toggle-checkbox', function() {
        var columnKey = $(this).data('column');
        var tableId = $(this).data('table');
        var cookieName = $(this).data('cookie');
        var isVisible = $(this).is(':checked');

        toggleColumn(tableId, columnKey, isVisible);
        saveColumnVisibility(tableId, cookieName);
    });

    // Handle reset button
    $(document).on('click', '.column-reset-btn', function() {
        var tableId = $(this).data('table');
        var cookieName = $(this).data('cookie');

        // Check all checkboxes and show all columns
        $('.column-toggle-checkbox[data-table="' + tableId + '"]').each(function() {
            $(this).prop('checked', true);
            var columnKey = $(this).data('column');
            toggleColumn(tableId, columnKey, true);
        });

        // Clear the cookie
        setCookie(cookieName, {}, -1);

        // Save default state (all visible)
        saveColumnVisibility(tableId, cookieName);
    });

    // Initialize all tables with column toggles
    $('.column-toggle-checkbox').each(function() {
        var tableId = $(this).data('table');
        var cookieName = $(this).data('cookie');

        // Only initialize once per table
        if (!$('#' + tableId).data('columns-initialized')) {
            applyColumnVisibility(tableId, cookieName);
            $('#' + tableId).data('columns-initialized', true);
        }
    });
});
</script>
@endpush
@endonce
