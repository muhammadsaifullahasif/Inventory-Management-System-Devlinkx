@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bulk Update Products</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Bulk Update Products</li>
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

    {{-- @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif --}}

    {{-- Filter Card --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('products.bulk-update.form') }}" method="GET" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search (Name / SKU / Barcode)</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SKU, Barcode..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Brand</label>
                                <select name="brand_id" class="form-select form-select-sm">
                                    <option value="">All Brands</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}"
                                            {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                                            {{ $brand->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('products.bulk-update.form') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Form --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mr-3">Products</h5>
                <span class="badge badge-secondary mr-auto" id="totalCount">{{ $products->count() }} product(s)</span>

                <div class="card-tools d-flex align-items-center">
                    <input type="text" id="inlineSearch" class="form-control form-control-sm mr-2"
                        placeholder="Quick filter..." style="width:200px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="selectAllBtn">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">Deselect All</button>
                </div>
            </div>

            <div class="card-body">
                <form action="{{ route('products.bulk-update') }}" method="POST" id="bulkUpdateForm">
                    @csrf

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" id="productsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectPageCheckbox" title="Select all on this page">
                                                <label for="selectPageCheckbox" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                        {{-- <input type="checkbox" id="selectPageCheckbox" title="Select / deselect all visible"> --}}
                                    </th>
                                    <th style="width:200px;">Product</th>
                                    <th style="width:130px;">SKU</th>
                                    <th style="width:130px;">Barcode</th>
                                    <th style="width:100px;">Weight (lbs)</th>
                                    <th style="width:100px;">Length (in)</th>
                                    <th style="width:100px;">Width (in)</th>
                                    <th style="width:100px;">Height (in)</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                @forelse($products as $product)
                                    @php
                                        $meta = $productsMeta[$product->id] ?? [];
                                    @endphp
                                    <tr class="product-row"
                                        data-id="{{ $product->id }}"
                                        data-name="{{ strtolower($product->name) }}"
                                        data-sku="{{ strtolower($product->sku) }}"
                                        data-barcode="{{ strtolower($product->barcode) }}">

                                        <td class="text-center align-middle">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox product-checkbox" id="{{ $product->id }}" data-product-id="{{ $product->id }}">
                                                    <label for="{{ $product->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="product-id-input" value="{{ $product->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="product-checkbox" value="{{ $product->id }}"> --}}
                                        </td>

                                        <td class="align-middle">
                                            <span class="font-weight-bold">{{ $product->name }}</span>
                                            <br>
                                            <small class="text-muted">{{ $product->category->name ?? '—' }}</small>
                                        </td>

                                        <td>
                                            <input type="text"
                                                name="products[{{ $product->id }}][id]"
                                                value="{{ $product->id }}"
                                                hidden>
                                            <input type="text"
                                                name="products[{{ $product->id }}][sku]"
                                                value="{{ $product->sku }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="SKU"
                                                disabled>
                                        </td>

                                        <td>
                                            <input type="text"
                                                name="products[{{ $product->id }}][barcode]"
                                                value="{{ $product->barcode }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="Barcode"
                                                disabled>
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="products[{{ $product->id }}][weight]"
                                                value="{{ $meta['weight'] ?? '' }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="0.00"
                                                step="0.01" min="0"
                                                disabled>
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="products[{{ $product->id }}][length]"
                                                value="{{ $meta['length'] ?? '' }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="0.00"
                                                step="0.01" min="0"
                                                disabled>
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="products[{{ $product->id }}][width]"
                                                value="{{ $meta['width'] ?? '' }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="0.00"
                                                step="0.01" min="0"
                                                disabled>
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="products[{{ $product->id }}][height]"
                                                value="{{ $meta['height'] ?? '' }}"
                                                class="form-control form-control-sm row-input"
                                                placeholder="0.00"
                                                step="0.01" min="0"
                                                disabled>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="emptyRow">
                                        <td colspan="8" class="text-center py-4 text-muted">No products found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- <div class="card-footer d-flex align-items-center">
                        <span class="text-muted small mr-auto" id="selectedCount">0 product(s) selected</span>
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm mr-2">
                            <i class="fas fa-arrow-left mr-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-success btn-sm" id="saveBtn" disabled>
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                    </div> --}}

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
                        <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                            <i class="feather-save me-2"></i>Save Changes
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const allRows        = Array.from(document.querySelectorAll('.product-row'));
    const tbody          = document.getElementById('productsTableBody');
    const selectPageCb   = document.getElementById('selectPageCheckbox');
    const selectAllBtn   = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const saveBtn        = document.getElementById('saveBtn');
    const selectedCount  = document.getElementById('selectedCount');
    const inlineSearch   = document.getElementById('inlineSearch');

    // ── Inline quick-filter ──────────────────────────────────────────────────
    inlineSearch.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let visible = 0;
        allRows.forEach(row => {
            const match = !q
                || row.dataset.name.includes(q)
                || row.dataset.sku.includes(q)
                || row.dataset.barcode.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        document.getElementById('totalCount').textContent = visible + ' product(s)';
        syncPageCheckbox();
    });

    // ── Select/Deselect All (visible) ────────────────────────────────────────
    selectPageCb.addEventListener('change', function () {
        visibleRows().forEach(row => toggleRow(row, this.checked));
        updateCounts();
    });

    selectAllBtn.addEventListener('click', function () {
        visibleRows().forEach(row => toggleRow(row, true));
        syncPageCheckbox();
        updateCounts();
    });

    deselectAllBtn.addEventListener('click', function () {
        allRows.forEach(row => toggleRow(row, false));
        syncPageCheckbox();
        updateCounts();
    });

    // ── Individual checkbox ──────────────────────────────────────────────────
    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('product-checkbox')) {
            toggleRow(e.target.closest('tr'), e.target.checked);
            syncPageCheckbox();
            updateCounts();
        }
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function visibleRows() {
        return allRows.filter(r => r.style.display !== 'none');
    }

    function toggleRow(row, checked) {
        const cb = row.querySelector('.product-checkbox');
        cb.checked = checked;
        row.querySelectorAll('.row-input').forEach(input => {
            input.disabled = !checked;
        });
        row.classList.toggle('table-active', checked);
    }

    function syncPageCheckbox() {
        const vis     = visibleRows();
        const checked = vis.filter(r => r.querySelector('.product-checkbox').checked);
        selectPageCb.checked       = vis.length > 0 && checked.length === vis.length;
        selectPageCb.indeterminate = checked.length > 0 && checked.length < vis.length;
    }

    function updateCounts() {
        const n = document.querySelectorAll('.product-checkbox:checked').length;
        selectedCount.textContent = n + ' product(s) selected';
        saveBtn.disabled = n === 0;
    }

    // ── Form submit — strip unchecked rows so they don't submit ──────────────
    document.getElementById('bulkUpdateForm').addEventListener('submit', function (e) {
        const unchecked = document.querySelectorAll('.product-checkbox:not(:checked)');
        unchecked.forEach(cb => {
            const row = cb.closest('tr');
            // Remove name attributes so fields are excluded from POST data
            row.querySelectorAll('[name]').forEach(el => el.removeAttribute('name'));
        });

        if (document.querySelectorAll('.product-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('Please select at least one product to update.');
        }
    });
});
</script>
@endpush
