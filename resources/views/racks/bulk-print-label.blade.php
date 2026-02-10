@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bulk Print Rack Labels</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                        <li class="breadcrumb-item active">Bulk Print Labels</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Select Racks to Print Labels</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('racks.label.bulk-print') }}" id="bulkLabelForm">
                @csrf

                <!-- Controls Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="rackSearch">Search/Filter:</label>
                        <input type="text" id="rackSearch" class="form-control" placeholder="Filter by rack name...">
                    </div>
                    <div class="col-md-2">
                        <label for="warehouseFilter">Warehouse:</label>
                        <select id="warehouseFilter" class="form-control">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="defaultQuantity">Quantity per Rack:</label>
                        <input type="number" id="defaultQuantity" name="default_quantity" class="form-control" value="1" min="1" max="100">
                    </div>
                    <div class="col-md-1">
                        <label for="columns">Columns:</label>
                        <select id="columns" name="columns" class="form-control">
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="perPage">Per Page:</label>
                        <select id="perPage" class="form-control">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">Select All</button>
                            <button type="button" class="btn btn-outline-secondary" id="deselectAllBtn">Deselect</button>
                        </div>
                    </div>
                </div>

                <!-- Racks Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="racksTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="selectPageCheckbox" title="Select all on this page">
                                </th>
                                <th>Rack Name</th>
                                <th>Warehouse</th>
                                <th style="width: 120px;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody id="racksTableBody">
                            @foreach($racks as $rack)
                                <tr class="rack-row"
                                    data-id="{{ $rack->id }}"
                                    data-name="{{ strtolower($rack->name) }}"
                                    data-warehouse-id="{{ $rack->warehouse_id }}">
                                    <td>
                                        <input type="checkbox" class="rack-checkbox"
                                            name="racks[{{ $rack->id }}][id]"
                                            value="{{ $rack->id }}">
                                    </td>
                                    <td>{{ $rack->name }}</td>
                                    <td>{{ $rack->warehouse->name }}</td>
                                    <td>
                                        <input type="number"
                                            name="racks[{{ $rack->id }}][quantity]"
                                            class="form-control form-control-sm quantity-input"
                                            value="1" min="1" max="100" disabled>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div id="paginationInfo" class="text-muted"></div>
                        <div id="selectedCount" class="text-primary font-weight-bold mt-1"></div>
                    </div>
                    <div class="col-md-6">
                        <nav>
                            <ul class="pagination justify-content-end mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" id="printBtn" disabled>
                        <i class="fas fa-print"></i> Print Selected Labels
                    </button>
                    <a href="{{ route('racks.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Racks
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const allRows = Array.from(document.querySelectorAll('.rack-row'));
    let filteredRows = [...allRows];
    let currentPage = 1;
    let perPage = 25;

    const searchInput = document.getElementById('rackSearch');
    const warehouseFilter = document.getElementById('warehouseFilter');
    const perPageSelect = document.getElementById('perPage');
    const defaultQuantityInput = document.getElementById('defaultQuantity');
    const selectPageCheckbox = document.getElementById('selectPageCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const printBtn = document.getElementById('printBtn');

    // Initialize
    updateDisplay();

    // Search/Filter functionality
    searchInput.addEventListener('input', applyFilters);
    warehouseFilter.addEventListener('change', applyFilters);

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const warehouseId = warehouseFilter.value;

        filteredRows = allRows.filter(row => {
            const nameMatch = query.length === 0 || (row.dataset.name || '').includes(query);
            const warehouseMatch = !warehouseId || row.dataset.warehouseId === warehouseId;
            return nameMatch && warehouseMatch;
        });

        currentPage = 1;
        updateDisplay();
    }

    // Per page change
    perPageSelect.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        updateDisplay();
    });

    // Default quantity change
    defaultQuantityInput.addEventListener('change', function() {
        const newQty = parseInt(this.value) || 1;
        document.querySelectorAll('.quantity-input').forEach(input => {
            const checkbox = input.closest('tr').querySelector('.rack-checkbox');
            if (!checkbox.checked) {
                input.value = newQty;
            }
        });
    });

    // Select all on current page
    selectPageCheckbox.addEventListener('change', function() {
        const visibleRows = getVisibleRows();
        visibleRows.forEach(row => {
            const checkbox = row.querySelector('.rack-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = this.checked;
            quantityInput.disabled = !this.checked;
            if (this.checked) {
                quantityInput.value = defaultQuantityInput.value;
            }
        });
        updateSelectedCount();
    });

    // Select all filtered
    selectAllBtn.addEventListener('click', function() {
        filteredRows.forEach(row => {
            const checkbox = row.querySelector('.rack-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = true;
            quantityInput.disabled = false;
            quantityInput.value = defaultQuantityInput.value;
        });
        updateDisplay();
    });

    // Deselect all
    deselectAllBtn.addEventListener('click', function() {
        allRows.forEach(row => {
            const checkbox = row.querySelector('.rack-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = false;
            quantityInput.disabled = true;
        });
        updateDisplay();
    });

    // Individual checkbox change
    document.getElementById('racksTableBody').addEventListener('change', function(e) {
        if (e.target.classList.contains('rack-checkbox')) {
            const row = e.target.closest('tr');
            const quantityInput = row.querySelector('.quantity-input');
            quantityInput.disabled = !e.target.checked;
            if (e.target.checked) {
                quantityInput.value = defaultQuantityInput.value;
            }
            updateSelectedCount();
            updateSelectPageCheckbox();
        }
    });

    function getVisibleRows() {
        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        return filteredRows.slice(start, end);
    }

    function updateDisplay() {
        allRows.forEach(row => row.style.display = 'none');

        const visibleRows = getVisibleRows();
        visibleRows.forEach(row => row.style.display = '');

        updatePagination();
        updatePaginationInfo();
        updateSelectedCount();
        updateSelectPageCheckbox();
    }

    function updatePagination() {
        const totalPages = Math.ceil(filteredRows.length / perPage);
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';

        if (totalPages <= 1) return;

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>`;
        pagination.appendChild(prevLi);

        // Page numbers
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
            pagination.appendChild(li);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>`;
        pagination.appendChild(nextLi);

        // Add click handlers
        pagination.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (page >= 1 && page <= totalPages) {
                    currentPage = page;
                    updateDisplay();
                }
            });
        });
    }

    function updatePaginationInfo() {
        const start = filteredRows.length > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const end = Math.min(currentPage * perPage, filteredRows.length);
        const total = filteredRows.length;
        const allTotal = allRows.length;

        let info = `Showing ${start} to ${end} of ${total} racks`;
        if (total !== allTotal) {
            info += ` (filtered from ${allTotal} total)`;
        }
        document.getElementById('paginationInfo').textContent = info;
    }

    function updateSelectedCount() {
        const selectedCheckboxes = document.querySelectorAll('.rack-checkbox:checked');
        const count = selectedCheckboxes.length;

        document.getElementById('selectedCount').textContent =
            count > 0 ? `${count} rack(s) selected for printing` : 'No racks selected';

        printBtn.disabled = count === 0;
    }

    function updateSelectPageCheckbox() {
        const visibleRows = getVisibleRows();
        const visibleCheckboxes = visibleRows.map(row => row.querySelector('.rack-checkbox'));
        const checkedCount = visibleCheckboxes.filter(cb => cb.checked).length;

        selectPageCheckbox.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
        selectPageCheckbox.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    }

    // Form submission - remove unchecked racks from form data
    document.getElementById('bulkLabelForm').addEventListener('submit', function(e) {
        const uncheckedRows = document.querySelectorAll('.rack-checkbox:not(:checked)');
        uncheckedRows.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.removeAttribute('name');
            quantityInput.removeAttribute('name');
        });
    });
});
</script>
@endpush
