@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bulk Print Barcodes</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Bulk Print Barcodes</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="javascript:void(0)" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('products.import') }}" class="btn btn-light-brand">
                        <i class="feather-upload me-2"></i>
                        <span>Import</span>
                    </a>
                    @can('add products')
                    <a href="{{ route('products.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Product</span>
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="javascript:void(0)" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Select Products to Print Barcodes</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.barcode.bulk-print') }}" id="bulkBarcodeForm">
                @csrf

                <!-- Controls Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="productSearch">Search/Filter:</label>
                        <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="Filter by name, SKU, or barcode...">
                    </div>
                    <div class="col-md-2">
                        <label for="defaultQuantity">Quantity per Product:</label>
                        <input type="number" id="defaultQuantity" name="default_quantity" class="form-control form-control-sm" value="1" min="1" max="100">
                    </div>
                    <div class="col-md-2">
                        <label for="columns">Columns per Row:</label>
                        <select id="columns" name="columns" class="form-select form-select-sm">
                            <option value="2">2 Columns</option>
                            <option value="3" selected>3 Columns</option>
                            <option value="4">4 Columns</option>
                            <option value="5">5 Columns</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="perPage">Products per Page:</label>
                        <select id="perPage" class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllBtn">Select All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">Deselect</button>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="productsTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50px;">
                                    <div class="btn-group mb-1">
                                        <div class="custom-control custom-checkbox ms-1">
                                            <input type="checkbox" class="custom-control-input" id="selectPageCheckbox" title="Select all on this page">
                                            <label for="selectPageCheckbox" class="custom-control-label"></label>
                                        </div>
                                    </div>
                                    {{-- <input type="checkbox" id="selectPageCheckbox" title="Select all on this page"> --}}
                                </th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th style="width: 120px;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            @foreach($products as $product)
                                <tr class="product-row"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ strtolower($product->name) }}"
                                    data-sku="{{ strtolower($product->sku) }}"
                                    data-barcode="{{ strtolower($product->barcode) }}">
                                    <td>
                                        {{-- <input type="checkbox" class="product-checkbox"
                                            name="products[{{ $product->id }}][id]"
                                            value="{{ $product->id }}"> --}}
                                        
                                        <div class="item-checkbox ms-1">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input checkbox product-checkbox" id="product_{{ $product->id }}" name="products[{{ $product->id }}][id]" value="{{ $product->id }}" data-product-id="{{ $product->id }}">
                                                <label for="product_{{ $product->id }}" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->barcode }}</td>
                                    <td>
                                        <input type="number"
                                            name="products[{{ $product->id }}][quantity]"
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
                            <ul class="pagination pagination-sm justify-content-end mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary" id="printBtn" disabled>
                        <i class="feather-printer me-2"></i>Print Selected Barcodes
                    </button>
                    <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const allRows = Array.from(document.querySelectorAll('.product-row'));
    let filteredRows = [...allRows];
    let currentPage = 1;
    let perPage = 25;

    const searchInput = document.getElementById('productSearch');
    const perPageSelect = document.getElementById('perPage');
    const defaultQuantityInput = document.getElementById('defaultQuantity');
    const selectPageCheckbox = document.getElementById('selectPageCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const printBtn = document.getElementById('printBtn');

    // Initialize
    updateDisplay();

    // Search/Filter functionality
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();

        if (query.length === 0) {
            filteredRows = [...allRows];
        } else {
            filteredRows = allRows.filter(row => {
                const name = row.dataset.name || '';
                const sku = row.dataset.sku || '';
                const barcode = row.dataset.barcode || '';
                return name.includes(query) || sku.includes(query) || barcode.includes(query);
            });
        }

        currentPage = 1;
        updateDisplay();
    });

    // Per page change
    perPageSelect.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        updateDisplay();
    });

    // Default quantity change - update all unchecked quantity inputs
    defaultQuantityInput.addEventListener('change', function() {
        const newQty = parseInt(this.value) || 1;
        document.querySelectorAll('.quantity-input').forEach(input => {
            const checkbox = input.closest('tr').querySelector('.product-checkbox');
            if (!checkbox.checked) {
                input.value = newQty;
            }
        });
    });

    // Select all on current page
    selectPageCheckbox.addEventListener('change', function() {
        const visibleRows = getVisibleRows();
        visibleRows.forEach(row => {
            const checkbox = row.querySelector('.product-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = this.checked;
            quantityInput.disabled = !this.checked;
            if (this.checked) {
                quantityInput.value = defaultQuantityInput.value;
            }
        });
        updateSelectedCount();
    });

    // Select all filtered products
    selectAllBtn.addEventListener('click', function() {
        filteredRows.forEach(row => {
            const checkbox = row.querySelector('.product-checkbox');
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
            const checkbox = row.querySelector('.product-checkbox');
            const quantityInput = row.querySelector('.quantity-input');
            checkbox.checked = false;
            quantityInput.disabled = true;
        });
        updateDisplay();
    });

    // Individual checkbox change
    document.getElementById('productsTableBody').addEventListener('change', function(e) {
        if (e.target.classList.contains('product-checkbox')) {
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
        // Hide all rows first
        allRows.forEach(row => row.style.display = 'none');

        // Show only filtered rows for current page
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

        let info = `Showing ${start} to ${end} of ${total} products`;
        if (total !== allTotal) {
            info += ` (filtered from ${allTotal} total)`;
        }
        document.getElementById('paginationInfo').textContent = info;
    }

    function updateSelectedCount() {
        const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
        const count = selectedCheckboxes.length;

        document.getElementById('selectedCount').textContent =
            count > 0 ? `${count} product(s) selected for printing` : 'No products selected';

        printBtn.disabled = count === 0;
    }

    function updateSelectPageCheckbox() {
        const visibleRows = getVisibleRows();
        const visibleCheckboxes = visibleRows.map(row => row.querySelector('.product-checkbox'));
        const checkedCount = visibleCheckboxes.filter(cb => cb.checked).length;

        selectPageCheckbox.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
        selectPageCheckbox.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    }

    // Form submission - remove unchecked products from form data
    document.getElementById('bulkBarcodeForm').addEventListener('submit', function(e) {
        const uncheckedRows = document.querySelectorAll('.product-checkbox:not(:checked)');
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
