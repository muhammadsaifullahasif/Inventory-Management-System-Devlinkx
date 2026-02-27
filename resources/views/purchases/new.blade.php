@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Purchase</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item">Add Purchase</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Purchases</span>
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
                <h5 class="card-title">Purchase Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('purchases.store') }}" method="post" id="purchaseForm">
                    @csrf
                    <div class="mb-4">
                        <label for="purchase_number" class="form-label">Purchase Number <span class="text-danger">*</span></label>
                        <input type="text" id="purchase_number" name="purchase_number" value="{{ old('purchase_number') }}" class="form-control @error('purchase_number') is-invalid @enderror" placeholder="Purchase Number">
                        @error('purchase_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ (old('supplier_id') == $supplier->id) ? 'selected' : '' }}>{{ (($supplier->last_name != '') ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name) }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror">
                                <option value="">Select Warehouse</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ (old('warehouse_id') == $warehouse->id) ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="purchase_note" class="form-label">Purchase Note</label>
                        <input type="text" id="purchase_note" name="purchase_note" value="{{ old('purchase_note') }}" class="form-control @error('purchase_note') is-invalid @enderror" placeholder="Optional notes">
                        @error('purchase_note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="duties_customs" class="form-label">Duties & Customs</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="duties_customs" name="duties_customs" value="{{ old('duties_customs', 0) }}" class="form-control @error('duties_customs') is-invalid @enderror" placeholder="0.00" min="0" step="0.01">
                            </div>
                            @error('duties_customs')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="freight_charges" class="form-label">Freight Charges</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="freight_charges" name="freight_charges" value="{{ old('freight_charges', 0) }}" class="form-control @error('freight_charges') is-invalid @enderror" placeholder="0.00" min="0" step="0.01">
                            </div>
                            @error('freight_charges')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3"><i class="feather-package me-2"></i>Select Products</h6>

                    <!-- Controls Row -->
                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label for="productSearch" class="form-label">Search/Filter</label>
                            <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="Filter by name, SKU, or barcode...">
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
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-light-brand btn-sm" id="selectAllBtn">Select All</button>
                                <button type="button" class="btn btn-light-brand btn-sm" id="deselectAllBtn">Deselect All</button>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end justify-content-end">
                            <div>
                                <span id="selectedCount" class="text-primary fw-bold"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Totals Summary Row -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-end gap-4 text-end">
                                <div>
                                    <small class="text-muted d-block">Items Total</small>
                                    <strong>$<span id="itemsTotal">0.00</span></strong>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Duties & Freight</small>
                                    <strong>$<span id="dutiesFreightTotal">0.00</span></strong>
                                </div>
                                <div class="border-start ps-4">
                                    <small class="text-muted d-block">Grand Total</small>
                                    <strong class="text-primary fs-5">$<span id="grandTotal">0.00</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-hover" id="productsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectPageCheckbox" title="Select all on this page">
                                                <label for="selectPageCheckbox" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                        {{-- <input type="checkbox" class="form-check-input" id="selectPageCheckbox" title="Select all on this page"> --}}
                                    </th>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Barcode</th>
                                    <th style="width: 80px;">Stock</th>
                                    <th style="width: 140px;">Rack</th>
                                    <th style="width: 100px;">Qty</th>
                                    <th style="width: 100px;">Price</th>
                                    <th style="width: 200px;">Note</th>
                                    <th style="width: 150px;">SubTotal</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                @foreach($products as $product)
                                    <tr class="product-row"
                                        data-id="{{ $product->id }}"
                                        data-name="{{ strtolower($product->name) }}"
                                        data-sku="{{ strtolower($product->sku) }}"
                                        data-barcode="{{ strtolower($product->barcode ?? '') }}">
                                        <td>
                                            {{-- <input type="checkbox" class="form-check-input product-checkbox"
                                                data-product-id="{{ $product->id }}">
                                            <input type="hidden" class="product-id-input"
                                                value="{{ $product->id }}" disabled> --}}
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox product-checkbox" id="{{ $product->id }}" data-product-id="{{ $product->id }}">
                                                    <label for="{{ $product->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="product-id-input" value="{{ $product->id }}" disabled>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="fs-12">{{ $product->sku }}</span></td>
                                        <td>{{ $product->name }}</td>
                                        <td><span class="fs-12 text-muted">{{ $product->barcode ?? 'N/A' }}</span></td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-secondary text-secondary">{{ (int)($product->product_stocks_sum_quantity ?? 0) }}</span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm rack-select" disabled>
                                                <option value="">Select Rack</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm quantity-input"
                                                value="1" min="1" disabled>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm price-input"
                                                value="0" min="0" step="0.01" disabled>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm note-input"
                                                value="" disabled>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm subtotal-input"
                                                value="0.00" readonly>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div id="paginationInfo" class="text-muted fs-12"></div>
                        </div>
                        <div class="col-md-6">
                            <nav>
                                <ul class="pagination pagination-sm justify-content-end mb-0" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="feather-save me-2"></i>Save Purchase
                        </button>
                        <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    const allRows = Array.from(document.querySelectorAll('.product-row'));
    let filteredRows = [...allRows];
    let currentPage = 1;
    let perPage = 25;
    let racks = [];

    const searchInput = document.getElementById('productSearch');
    const perPageSelect = document.getElementById('perPage');
    const selectPageCheckbox = document.getElementById('selectPageCheckbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const submitBtn = document.getElementById('submitBtn');

    updateDisplay();

    // Warehouse change - fetch racks
    $('#warehouse_id').on('change', function(){
        var warehouseId = $(this).val();
        if (warehouseId) {
            $.ajax({
                url: `{{ route('warehouses.racks', ['warehouse' => ':id']) }}`.replace(':id', warehouseId),
                type: 'GET',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    racks = data;
                    updateAllRackSelects();
                }
            });
        } else {
            racks = [];
            updateAllRackSelects();
        }
    });

    function updateAllRackSelects() {
        $('.rack-select').each(function(){
            var select = $(this);
            var currentVal = select.val();
            select.empty().append('<option value="">Select Rack</option>');
            var defaultRackId = null;
            $.each(racks, function(i, rack){
                select.append(`<option value="${rack.id}">${rack.name}</option>`);
                if (rack.is_default == 1 || rack.is_default == '1') defaultRackId = rack.id;
            });
            if (currentVal && select.find(`option[value="${currentVal}"]`).length) {
                select.val(currentVal);
            } else if (defaultRackId) {
                select.val(defaultRackId);
            }
        });
    }

    // Search/Filter
    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        filteredRows = query.length === 0 ? [...allRows] : allRows.filter(row => {
            return (row.dataset.name || '').includes(query) ||
                   (row.dataset.sku || '').includes(query) ||
                   (row.dataset.barcode || '').includes(query);
        });
        currentPage = 1;
        updateDisplay();
    });

    perPageSelect.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        updateDisplay();
    });

    // Checkbox handling
    selectPageCheckbox.addEventListener('change', function() {
        getVisibleRows().forEach(row => {
            const cb = row.querySelector('.product-checkbox');
            cb.checked = this.checked;
            toggleRowInputs(row, this.checked);
        });
        updateSelectedCount();
        calculateGrandTotal();
    });

    selectAllBtn.addEventListener('click', function() {
        filteredRows.forEach(row => {
            row.querySelector('.product-checkbox').checked = true;
            toggleRowInputs(row, true);
        });
        updateDisplay();
        calculateGrandTotal();
    });

    deselectAllBtn.addEventListener('click', function() {
        allRows.forEach(row => {
            row.querySelector('.product-checkbox').checked = false;
            toggleRowInputs(row, false);
        });
        updateDisplay();
        calculateGrandTotal();
    });

    document.getElementById('productsTableBody').addEventListener('change', function(e) {
        if (e.target.classList.contains('product-checkbox')) {
            toggleRowInputs(e.target.closest('tr'), e.target.checked);
            updateSelectedCount();
            updateSelectPageCheckbox();
            calculateGrandTotal();
        }
    });

    // Calculate subtotal on quantity/price change
    $(document).on('input', '.quantity-input, .price-input', function(){
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.quantity-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        row.find('.subtotal-input').val((qty * price).toFixed(2));
        calculateGrandTotal();
    });

    // Calculate grand total on duties/freight change
    $(document).on('input', '#duties_customs, #freight_charges', function(){
        calculateGrandTotal();
    });

    function toggleRowInputs(row, enabled) {
        const inputs = row.querySelectorAll('.rack-select, .quantity-input, .price-input, .note-input');
        inputs.forEach(input => input.disabled = !enabled);
        if (enabled) {
            var qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
            var price = parseFloat(row.querySelector('.price-input').value) || 0;
            row.querySelector('.subtotal-input').value = (qty * price).toFixed(2);
        } else {
            row.querySelector('.subtotal-input').value = '0.00';
        }
    }

    function calculateGrandTotal() {
        var itemsTotal = 0;
        document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
            var row = cb.closest('tr');
            itemsTotal += parseFloat(row.querySelector('.subtotal-input').value) || 0;
        });

        var dutiesCustoms = parseFloat(document.getElementById('duties_customs').value) || 0;
        var freightCharges = parseFloat(document.getElementById('freight_charges').value) || 0;
        var dutiesFreightTotal = dutiesCustoms + freightCharges;
        var grandTotal = itemsTotal + dutiesFreightTotal;

        document.getElementById('itemsTotal').textContent = itemsTotal.toFixed(2);
        document.getElementById('dutiesFreightTotal').textContent = dutiesFreightTotal.toFixed(2);
        document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    }

    function getVisibleRows() {
        const start = (currentPage - 1) * perPage;
        return filteredRows.slice(start, start + perPage);
    }

    function updateDisplay() {
        allRows.forEach(row => row.style.display = 'none');
        getVisibleRows().forEach(row => row.style.display = '');
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
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

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
                if (page >= 1 && page <= totalPages) { currentPage = page; updateDisplay(); }
            });
        });
    }

    function updatePaginationInfo() {
        const start = filteredRows.length > 0 ? (currentPage - 1) * perPage + 1 : 0;
        const end = Math.min(currentPage * perPage, filteredRows.length);
        let info = `Showing ${start} to ${end} of ${filteredRows.length} products`;
        if (filteredRows.length !== allRows.length) info += ` (filtered from ${allRows.length} total)`;
        document.getElementById('paginationInfo').textContent = info;
    }

    function updateSelectedCount() {
        const count = document.querySelectorAll('.product-checkbox:checked').length;
        document.getElementById('selectedCount').textContent =
            count > 0 ? `${count} product(s) selected` : 'No products selected';
        submitBtn.disabled = count === 0;
    }

    function updateSelectPageCheckbox() {
        const visible = getVisibleRows();
        const cbs = visible.map(r => r.querySelector('.product-checkbox'));
        const checked = cbs.filter(cb => cb.checked).length;
        selectPageCheckbox.checked = cbs.length > 0 && checked === cbs.length;
        selectPageCheckbox.indeterminate = checked > 0 && checked < cbs.length;
    }

    // Form submission - set proper name attributes for checked products only
    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        document.querySelectorAll('.product-id-input').forEach(el => el.removeAttribute('name'));
        document.querySelectorAll('.rack-select').forEach(el => el.removeAttribute('name'));
        document.querySelectorAll('.quantity-input').forEach(el => el.removeAttribute('name'));
        document.querySelectorAll('.price-input').forEach(el => el.removeAttribute('name'));
        document.querySelectorAll('.note-input').forEach(el => el.removeAttribute('name'));

        let index = 0;
        document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
            const row = cb.closest('tr');
            row.querySelector('.product-id-input').disabled = false;
            row.querySelector('.product-id-input').name = `products[${index}][id]`;
            row.querySelector('.rack-select').name = `products[${index}][rack]`;
            row.querySelector('.quantity-input').name = `products[${index}][quantity]`;
            row.querySelector('.price-input').name = `products[${index}][price]`;
            row.querySelector('.note-input').name = `products[${index}][note]`;
            index++;
        });

        if (index === 0) {
            e.preventDefault();
            alert('Please select at least one product.');
        }
    });
});
</script>
@endpush
