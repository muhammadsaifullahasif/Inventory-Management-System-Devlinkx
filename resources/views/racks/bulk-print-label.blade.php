@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bulk Print Rack Labels</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                <li class="breadcrumb-item">Bulk Print Labels</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('racks.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Racks</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-printer me-2"></i>Select Racks to Print Labels</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('racks.label.bulk-print') }}" id="bulkLabelForm">
                    @csrf

                    <!-- Controls Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="rackSearch" class="form-label">Search/Filter</label>
                            <input type="text" id="rackSearch" class="form-control form-control-sm" placeholder="Filter by rack name...">
                        </div>
                        <div class="col-md-2">
                            <label for="warehouseFilter" class="form-label">Warehouse</label>
                            <select id="warehouseFilter" class="form-select form-select-sm">
                                <option value="">All Warehouses</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="defaultQuantity" class="form-label">Quantity per Rack</label>
                            <input type="number" id="defaultQuantity" name="default_quantity" class="form-control form-control-sm" value="1" min="1" max="100">
                        </div>
                        <div class="col-md-1">
                            <label for="columns" class="form-label">Columns</label>
                            <select id="columns" name="columns" class="form-select form-select-sm">
                                <option value="2" selected>2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="perPage" class="form-label">Per Page</label>
                            <select id="perPage" class="form-select form-select-sm">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-light-brand btn-sm" id="selectAllBtn">Select All</button>
                                <button type="button" class="btn btn-light-brand btn-sm" id="deselectAllBtn">Deselect</button>
                            </div>
                        </div>
                    </div>

                    <!-- Racks Table -->
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="racksTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectPageCheckbox" title="Select all on this page">
                                                <label for="selectPageCheckbox" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                        {{-- <input type="checkbox" class="form-check-input" id="selectPageCheckbox" title="Select all on this page"> --}}
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
                                            {{-- <input type="checkbox" class="form-check-input rack-checkbox"
                                                name="racks[{{ $rack->id }}][id]"
                                                value="{{ $rack->id }}"> --}}

                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="racks[{{ $rack->id }}][id]" value="{{ $rack->id }}" class="custom-control-input checkbox rack-checkbox" id="rack_{{ $rack->id }}" data-rack-id="{{ $rack->id }}">
                                                    <label for="rack_{{ $rack->id }}" class="custom-control-label"></label>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="fw-semibold">{{ $rack->name }}</span></td>
                                        <td><span class="badge bg-soft-secondary text-secondary">{{ $rack->warehouse->name }}</span></td>
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
                            <div id="paginationInfo" class="text-muted fs-12"></div>
                            <div id="selectedCount" class="text-primary fw-semibold mt-1"></div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination justify-content-end mb-0" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="printBtn" disabled>
                            <i class="feather-printer me-2"></i>Print Selected Labels
                        </button>
                    </div>
                </form>
            </div>
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

    updateDisplay();

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

    perPageSelect.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        updateDisplay();
    });

    defaultQuantityInput.addEventListener('change', function() {
        const newQty = parseInt(this.value) || 1;
        document.querySelectorAll('.quantity-input').forEach(input => {
            const checkbox = input.closest('tr').querySelector('.rack-checkbox');
            if (!checkbox.checked) {
                input.value = newQty;
            }
        });
    });

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

    deselectAllBtn.addEventListener('click', function() {
        allRows.forEach(row => {
            const checkbox = row.querySelector('.rack-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = false;
            quantityInput.disabled = true;
        });
        updateDisplay();
    });

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

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>`;
        pagination.appendChild(prevLi);

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

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>`;
        pagination.appendChild(nextLi);

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
            count > 0 ? `${count} rack(s) selected for printing` : 'No rack selected';

        printBtn.disabled = count === 0;
    }

    function updateSelectPageCheckbox() {
        const visibleRows = getVisibleRows();
        const visibleCheckboxes = visibleRows.map(row => row.querySelector('.rack-checkbox'));
        const checkedCount = visibleCheckboxes.filter(cb => cb.checked).length;

        selectPageCheckbox.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
        selectPageCheckbox.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    }

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
