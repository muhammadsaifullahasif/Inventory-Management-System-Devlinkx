@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bulk Print Barcodes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                        <li class="breadcrumb-item active">Bulk Print Barcodes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Select Products and Quantities</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.barcode.bulk-print') }}" id="bulkBarcodeForm">
                @csrf

                <!-- Search and Add Products -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="productSearch">Search Product:</label>
                        <input type="text" id="productSearch" class="form-control" placeholder="Search by name, SKU, or barcode...">
                        <div id="searchResults" class="list-group position-absolute w-100" style="z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="defaultQuantity">Default Quantity:</label>
                        <input type="number" id="defaultQuantity" class="form-control" value="21" min="1" max="100">
                    </div>
                    <div class="col-md-3">
                        <label for="columns">Columns per Row:</label>
                        <select id="columns" name="columns" class="form-control">
                            <option value="2">2 Columns</option>
                            <option value="3" selected>3 Columns</option>
                            <option value="4">4 Columns</option>
                            <option value="5">5 Columns</option>
                        </select>
                    </div>
                </div>

                <!-- Selected Products Table -->
                <div class="table-responsive">
                    <table class="table table-bordered" id="selectedProductsTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th style="width: 150px;">Quantity</th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="selectedProducts">
                            <tr id="noProductsRow">
                                <td colspan="6" class="text-center text-muted">No products selected. Search and add products above.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" id="printBtn" disabled>
                        <i class="fas fa-print"></i> Print Barcodes
                    </button>
                    <button type="button" class="btn btn-secondary" id="clearAllBtn">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Add: All Products -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Add Products</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover">
                    <thead class="thead-light" style="position: sticky; top: 0;">
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="quickAddProducts">
                        @foreach($products as $product)
                            <tr id="quick-add-row-{{ $product->id }}">
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>{{ $product->barcode }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary add-product-btn"
                                        data-id="{{ $product->id }}"
                                        data-name="{{ $product->name }}"
                                        data-sku="{{ $product->sku }}"
                                        data-barcode="{{ $product->barcode }}">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedProducts = {};
    let productCounter = 0;

    // Search functionality
    const searchInput = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`/products/search/${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => {
                    searchResults.innerHTML = '';
                    products.forEach(product => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${product.name}</strong> - SKU: ${product.sku} - Barcode: ${product.barcode || 'N/A'}`;
                        item.addEventListener('click', (e) => {
                            e.preventDefault();
                            addProduct(product.id, product.name, product.sku, product.barcode);
                            searchInput.value = '';
                            searchResults.innerHTML = '';
                        });
                        searchResults.appendChild(item);
                    });
                });
        }, 300);
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.innerHTML = '';
        }
    });

    // Add product buttons
    document.querySelectorAll('.add-product-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const sku = this.dataset.sku;
            const barcode = this.dataset.barcode;
            addProduct(id, name, sku, barcode);
        });
    });

    function addProduct(id, name, sku, barcode) {
        if (selectedProducts[id]) {
            alert('Product already added!');
            return;
        }

        const defaultQty = document.getElementById('defaultQuantity').value || 20;
        selectedProducts[id] = { name, sku, barcode, quantity: defaultQty };
        productCounter++;

        const noProductsRow = document.getElementById('noProductsRow');
        if (noProductsRow) noProductsRow.remove();

        const tbody = document.getElementById('selectedProducts');
        const row = document.createElement('tr');
        row.id = `product-row-${id}`;
        row.innerHTML = `
            <td>${productCounter}</td>
            <td>${name}</td>
            <td>${sku}</td>
            <td>${barcode || 'N/A'}</td>
            <td>
                <input type="hidden" name="products[${id}][id]" value="${id}">
                <input type="number" name="products[${id}][quantity]" class="form-control form-control-sm" value="${defaultQty}" min="1" max="100">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-product-btn" data-id="${id}">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);

        // Add remove event listener
        row.querySelector('.remove-product-btn').addEventListener('click', function() {
            removeProduct(this.dataset.id);
        });

        // Hide the product from Quick Add table
        const quickAddRow = document.getElementById(`quick-add-row-${id}`);
        if (quickAddRow) quickAddRow.style.display = 'none';

        updatePrintButton();
    }

    function removeProduct(id) {
        delete selectedProducts[id];
        const row = document.getElementById(`product-row-${id}`);
        if (row) row.remove();

        // Show the product back in Quick Add table
        const quickAddRow = document.getElementById(`quick-add-row-${id}`);
        if (quickAddRow) quickAddRow.style.display = '';

        if (Object.keys(selectedProducts).length === 0) {
            const tbody = document.getElementById('selectedProducts');
            tbody.innerHTML = '<tr id="noProductsRow"><td colspan="6" class="text-center text-muted">No products selected. Search and add products above.</td></tr>';
            productCounter = 0;
        }

        updatePrintButton();
    }

    function updatePrintButton() {
        const printBtn = document.getElementById('printBtn');
        printBtn.disabled = Object.keys(selectedProducts).length === 0;
    }

    // Clear all button
    document.getElementById('clearAllBtn').addEventListener('click', function() {
        // Show all products back in Quick Add table
        Object.keys(selectedProducts).forEach(id => {
            const quickAddRow = document.getElementById(`quick-add-row-${id}`);
            if (quickAddRow) quickAddRow.style.display = '';
        });

        selectedProducts = {};
        productCounter = 0;
        document.getElementById('selectedProducts').innerHTML =
            '<tr id="noProductsRow"><td colspan="6" class="text-center text-muted">No products selected. Search and add products above.</td></tr>';
        updatePrintButton();
    });
});
</script>
@endpush
