@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Purchase Import Preview</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.import') }}">Purchase Import</a></li>
                <li class="breadcrumb-item">Purchase Import Preview</li>
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
                    @can('add purchases')
                    <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Purchase</span>
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

@push('styles')
    <style>
        .purchase-card {
            border-left: 4px solid #456BDF;
        }
        .purchase-card .card-header {
            background-color: #f8f9fa;
        }
        .table thead tr th, .table tbody tr td {
            padding: .5rem !important;
            vertical-align: middle;
        }
        .product-table {
            font-size: 0.875rem;
        }
        .error-row {
            background-color: #fff3cd !important;
        }
        .error-badge {
            font-size: 0.7rem;
        }
    </style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger mb-3">
            <h6 class="alert-heading fw-bold mb-2"><i class="feather-alert-circle me-2"></i>Validation Errors</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (isset($importErrors) && count($importErrors) > 0)
        <div class="alert alert-warning mb-3">
            <h6 class="alert-heading fw-bold mb-2"><i class="feather-alert-triangle me-2"></i>Import Warnings</h6>
            <ul class="mb-0 ps-3">
                @foreach ($importErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('purchases.import.store') }}" method="POST" id="importForm">
        @csrf

        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="feather-package me-2"></i>
                        {{ count($purchases) }} Purchase(s) to Import
                    </h6>
                    <div>
                        <span class="badge bg-primary fs-12">Total Products: {{ collect($purchases)->sum(fn($p) => count($p['products'])) }}</span>
                    </div>
                </div>
            </div>
        </div>

        @foreach ($purchases as $index => $purchase)
            <div class="card purchase-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">
                            <i class="feather-file-text me-2"></i>
                            Purchase #{{ $index + 1 }}: <strong>{{ $purchase['purchase_number'] }}</strong>
                        </h6>
                        <small class="text-muted">
                            Supplier: <strong>{{ $purchase['supplier_name'] ?? 'N/A' }}</strong> |
                            Warehouse: <strong>{{ $purchase['warehouse_name'] ?? 'N/A' }}</strong>
                        </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-soft-info text-info">{{ count($purchase['products']) }} Product(s)</span>
                        <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger remove-purchase-btn" data-index="{{ $index }}">
                            <i class="feather-trash-2"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Hidden fields for purchase info -->
                    <input type="hidden" name="purchases[{{ $index }}][purchase_number]" value="{{ $purchase['purchase_number'] }}">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Purchase Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" value="{{ $purchase['purchase_number'] }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="purchases[{{ $index }}][supplier_id]" class="form-select form-select-sm" required>
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ ($purchase['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->last_name ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name }}
                                    </option>
                                @endforeach
                            </select>
                            @if (empty($purchase['supplier_id']))
                                <small class="text-danger">Supplier not found: {{ $purchase['supplier_name'] ?? 'N/A' }}</small>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select name="purchases[{{ $index }}][warehouse_id]" class="form-select form-select-sm warehouse-select" data-purchase-index="{{ $index }}" required>
                                <option value="">Select Warehouse</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ ($purchase['warehouse_id'] ?? '') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if (empty($purchase['warehouse_id']))
                                <small class="text-danger">Warehouse not found: {{ $purchase['warehouse_name'] ?? 'N/A' }}</small>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Purchase Note</label>
                            <input type="text" name="purchases[{{ $index }}][purchase_note]" class="form-control form-control-sm" value="{{ $purchase['purchase_note'] ?? '' }}" placeholder="Optional notes">
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover product-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th style="width: 120px;">SKU</th>
                                    <th>Product Name</th>
                                    <th style="width: 140px;">Rack</th>
                                    <th style="width: 100px;">Quantity</th>
                                    <th style="width: 100px;">Price</th>
                                    <th style="width: 150px;">Note</th>
                                    <th style="width: 100px;">Subtotal</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody class="products-tbody" data-purchase-index="{{ $index }}">
                                @foreach ($purchase['products'] as $pIndex => $product)
                                    <tr class="product-row {{ empty($product['product_id']) ? 'error-row' : '' }}">
                                        <td>{{ $pIndex + 1 }}</td>
                                        <td>
                                            <span class="fw-semibold">{{ $product['sku'] }}</span>
                                            <input type="hidden" name="purchases[{{ $index }}][products][{{ $pIndex }}][product_id]" value="{{ $product['product_id'] ?? '' }}">
                                            <input type="hidden" name="purchases[{{ $index }}][products][{{ $pIndex }}][sku]" value="{{ $product['sku'] }}">
                                            @if (empty($product['product_id']))
                                                <br><span class="badge bg-danger error-badge">SKU not found</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $product['product_name'] ?? 'Unknown Product' }}
                                        </td>
                                        <td>
                                            <select name="purchases[{{ $index }}][products][{{ $pIndex }}][rack_id]" class="form-select form-select-sm rack-select">
                                                <option value="">Select Rack</option>
                                                @if (isset($purchase['warehouse_id']) && isset($racks[$purchase['warehouse_id']]))
                                                    @foreach ($racks[$purchase['warehouse_id']] as $rack)
                                                        <option value="{{ $rack->id }}" {{ (isset($product['rack_id']) && $product['rack_id'] == $rack->id) ? 'selected' : ((!isset($product['rack_id']) && ($rack->is_default ?? false)) ? 'selected' : '') }}>{{ $rack->name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="purchases[{{ $index }}][products][{{ $pIndex }}][quantity]" class="form-control form-control-sm quantity-input" value="{{ $product['quantity'] ?? 1 }}" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" name="purchases[{{ $index }}][products][{{ $pIndex }}][price]" class="form-control form-control-sm price-input" value="{{ $product['price'] ?? 0 }}" min="0" step="0.01" required>
                                        </td>
                                        <td>
                                            <input type="text" name="purchases[{{ $index }}][products][{{ $pIndex }}][note]" class="form-control form-control-sm" value="{{ $product['note'] ?? '' }}" placeholder="Note">
                                        </td>
                                        <td>
                                            <span class="subtotal fw-semibold">{{ number_format(($product['quantity'] ?? 1) * ($product['price'] ?? 0), 2) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="javascript:void(0)" class="text-danger remove-product-btn">
                                                <i class="feather-x-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Purchase Total:</td>
                                    <td colspan="2">
                                        <span class="purchase-total fw-bold text-primary">
                                            ${{ number_format(collect($purchase['products'])->sum(fn($p) => ($p['quantity'] ?? 1) * ($p['price'] ?? 0)), 2) }}
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        @if (count($purchases) > 0)
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fs-5 fw-bold">Grand Total: <span class="text-primary" id="grandTotal">$0.00</span></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="feather-upload me-2"></i>Import Purchases
                            </button>
                            <a href="{{ route('purchases.import') }}" class="btn btn-light-brand">
                                <i class="feather-arrow-left me-2"></i>Back
                            </a>
                            <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info">
                <i class="feather-info me-2"></i>No valid purchases found in the CSV file.
                <a href="{{ route('purchases.import') }}" class="alert-link">Try again</a>
            </div>
        @endif
    </form>
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    // Calculate totals on page load
    calculateAllTotals();

    // Remove product row
    $(document).on('click', '.remove-product-btn', function(){
        var row = $(this).closest('tr');
        var tbody = row.closest('tbody');
        row.remove();

        // Re-index remaining rows
        reindexProducts(tbody);
        calculatePurchaseTotal(tbody.closest('.card'));
        calculateGrandTotal();
    });

    // Remove entire purchase
    $(document).on('click', '.remove-purchase-btn', function(){
        $(this).closest('.purchase-card').remove();
        calculateGrandTotal();

        // Check if any purchases left
        if ($('.purchase-card').length === 0) {
            location.reload();
        }
    });

    // Calculate subtotal on quantity/price change
    $(document).on('input', '.quantity-input, .price-input', function(){
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.quantity-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        row.find('.subtotal').text((qty * price).toFixed(2));

        calculatePurchaseTotal(row.closest('.card'));
        calculateGrandTotal();
    });

    // Warehouse change - update rack options
    $(document).on('change', '.warehouse-select', function(){
        var warehouseId = $(this).val();
        var purchaseIndex = $(this).data('purchase-index');
        var card = $(this).closest('.card');
        var rackSelects = card.find('.rack-select');

        if (warehouseId) {
            $.ajax({
                url: `{{ route('warehouses.racks', ['warehouse' => ':id']) }}`.replace(':id', warehouseId),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    rackSelects.each(function(){
                        var select = $(this);
                        var currentVal = select.val();
                        select.empty().append('<option value="">Select Rack</option>');
                        var defaultRackId = null;
                        $.each(data, function(i, rack){
                            select.append(`<option value="${rack.id}">${rack.name}</option>`);
                            if (rack.is_default == 1 || rack.is_default == '1') defaultRackId = rack.id;
                        });
                        if (defaultRackId) {
                            select.val(defaultRackId);
                        }
                    });
                }
            });
        } else {
            rackSelects.empty().append('<option value="">Select Rack</option>');
        }
    });

    function reindexProducts(tbody) {
        var purchaseIndex = tbody.data('purchase-index');
        tbody.find('tr').each(function(index){
            $(this).find('td:first').text(index + 1);
            $(this).find('input, select').each(function(){
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[products\]\[\d+\]/, `[products][${index}]`);
                    $(this).attr('name', newName);
                }
            });
        });
    }

    function calculatePurchaseTotal(card) {
        var total = 0;
        card.find('.subtotal').each(function(){
            total += parseFloat($(this).text()) || 0;
        });
        card.find('.purchase-total').text('$' + total.toFixed(2));
    }

    function calculateGrandTotal() {
        var grandTotal = 0;
        $('.purchase-total').each(function(){
            var val = $(this).text().replace('$', '').replace(',', '');
            grandTotal += parseFloat(val) || 0;
        });
        $('#grandTotal').text('$' + grandTotal.toFixed(2));
    }

    function calculateAllTotals() {
        $('.purchase-card').each(function(){
            calculatePurchaseTotal($(this));
        });
        calculateGrandTotal();
    }
});
</script>
@endpush
