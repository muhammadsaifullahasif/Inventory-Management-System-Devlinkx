@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Slow Moving Items Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Slow Moving Items</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('reports.slow-moving-items') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">-- All Categories --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" class="form-select form-select-sm">
                                    <option value="">-- All Warehouses --</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ $warehouseId == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Min Stock</label>
                                <input type="number" name="min_stock" class="form-control form-control-sm"
                                       value="{{ $minStock }}" min="0">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Max Sales</label>
                                <input type="number" name="max_sales" class="form-control form-control-sm"
                                       value="{{ $maxSales }}" min="0">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.slow-moving-items') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Slow Moving Items</h6>
                            <h3 class="mb-0 fw-bold text-warning">{{ number_format($summary['total_items']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-trending-down"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Zero Sales Items</h6>
                            <h3 class="mb-0 fw-bold text-danger">{{ number_format($summary['zero_sales_items']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-danger text-white rounded">
                            <i class="feather-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Stock Value</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_stock_value'], 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-secondary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Period Analyzed</h6>
                            <h3 class="mb-0 fw-bold">{{ $summary['period_days'] }} days</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-secondary text-white rounded">
                            <i class="feather-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="col-12 mb-4">
        <div class="alert alert-info">
            <i class="feather-info me-2"></i>
            <strong>Slow Moving Criteria:</strong> Products with at least {{ $minStock }} units in stock and {{ $maxSales }} or fewer units sold
            between {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} and {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}.
            Consider running promotions, markdowns, or bundling these items to improve turnover.
        </div>
    </div>

    <!-- Slow Moving Items Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-trending-down me-2"></i>Slow Moving Items</h5>
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <span class="badge bg-warning text-dark">{{ $slowMovingItems->count() }} items</span>
                    <button type="button" class="btn btn-success btn-sm export-excel-btn" data-table="slowMovingStockTable" data-route="{{ route('reports.slow-moving-items.export') }}">
                        <i class="feather-download me-1"></i> Export Excel
                    </button>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        @php
                            $slowMovingStockColumns = [
                                ['key' => 'id', 'label' => '#', 'default' => true],
                                ['key' => 'image', 'label' => 'Image', 'default' => true],
                                ['key' => 'product', 'label' => 'Product', 'default' => true],
                                ['key' => 'sku', 'label' => 'SKU', 'default' => true],
                                ['key' => 'category', 'label' => 'Category', 'default' => false],
                                ['key' => 'last_purchase_quantity', 'label' => 'Last Purchase Qty', 'default' => true],
                                ['key' => 'last_purchase', 'label' => 'Last Purchase', 'default' => true],
                                ['key' => 'last_order', 'label' => 'Last Order', 'default' => true],
                                ['key' => 'sold_quantity', 'label' => 'Sold', 'default' => true],
                                ['key' => 'stock', 'label' => 'Stock', 'default' => true],
                                ['key' => 'orders', 'label' => 'Orders', 'default' => false],
                                ['key' => 'daily_rate', 'label' => 'Daily Rate', 'default' => false],
                                ['key' => 'days_of_stock', 'label' => 'Days of Stock', 'default' => false],
                                ['key' => 'turnover', 'label' => 'Turnover', 'default' => false],
                                ['key' => 'stock_value', 'label' => 'Stock Value', 'default' => false],
                            ];
                        @endphp
                        @include('partials.column-toggle', ['tableId' => 'slowMovingStockTable', 'cookieName' => 'slow_moving_stock_columns', 'columns' => $slowMovingStockColumns])
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="slowMovingStockTable">
                        <thead>
                            <tr>
                                <th data-column="id">#</th>
                                <th data-column="image">Image</th>
                                <th data-column="product" style="max-width: 200px;">Product</th>
                                <th data-column="sku">SKU</th>
                                <th data-column="category">Category</th>
                                <th data-column="last_purchase_quantity">Last Purchase Quantity</th>
                                <th data-column="last_purchase">Last Purchase</th>
                                <th data-column="last_order">Last Sale</th>
                                <th data-column="sold_quantity" class="text-end">Sold</th>
                                <th data-column="stock" class="text-end">Stock</th>
                                <th data-column="orders" class="text-end">Orders</th>
                                <th data-column="daily_rate" class="text-end">Daily Rate</th>
                                <th data-column="days_of_stock" class="text-end">Days of Stock</th>
                                <th data-column="turnover" class="text-end">Turnover</th>
                                <th data-column="stock_value" class="text-end">Stock Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($slowMovingItems as $item)
                                <tr>
                                    <td data-column="id">{{ (($slowMovingItems->currentPage() - 1) * $slowMovingItems->perPage() + $loop->iteration) }}</td>
                                    <td data-column="image">
                                        @if($item['product_image'])
                                            <img src="{{ $item['product_image'] }}" alt="{{ $item['product_name'] }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="feather-image text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td data-column="product" style="max-width: 200px; white-space: normal;">
                                        <a href="{{ route('products.show', $item['product_id']) }}" class="fw-semibold">
                                            {{ $item['product_name'] }}
                                        </a>
                                    </td>
                                    <td data-column="sku">{{ $item['product_sku'] }}</td>
                                    <td data-column="category">
                                        <span class="badge bg-soft-secondary text-secondary">{{ $item['category_name'] }}</span>
                                    </td>
                                    <td data-column="last_purchase_quantity">{{ $item['last_purchase_quantity'] }}</td>
                                    <td data-column="last_purchase">{{ \Carbon\Carbon::parse($item['last_purchase_date'])->format('M d, Y') }}</td>
                                    <td data-column="last_order">
                                        @if($item['last_sale_date'])
                                            <span title="{{ \Carbon\Carbon::parse($item['last_sale_date'])->format('M d, Y') }}">
                                                {{ \Carbon\Carbon::parse($item['last_sale_date'])->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-danger">Never</span>
                                        @endif
                                    </td>
                                    <td data-column="sold_quantity" class="text-end">
                                        @if($item['total_sold'] == 0)
                                            <span class="text-danger fw-bold">0</span>
                                        @else
                                            {{ number_format($item['total_sold']) }}
                                        @endif
                                    </td>
                                    <td data-column="stock" class="text-end">{{ number_format($item['total_stock'], 2) }}</td>
                                    <td data-column="orders" class="text-end">{{ number_format($item['order_count']) }}</td>
                                    <td data-column="daily_rate" class="text-end">
                                        <span class="text-muted">{{ $item['daily_sales_rate'] }}/day</span>
                                    </td>
                                    <td data-column="days_of_stock" class="text-end">
                                        @if($item['days_of_stock'])
                                            @if($item['days_of_stock'] > 365)
                                                <span class="text-danger fw-bold">{{ number_format($item['days_of_stock']) }} days</span>
                                            @elseif($item['days_of_stock'] > 180)
                                                <span class="text-warning">{{ number_format($item['days_of_stock']) }} days</span>
                                            @else
                                                {{ number_format($item['days_of_stock']) }} days
                                            @endif
                                        @else
                                            <span class="text-danger">Infinite</span>
                                        @endif
                                    </td>
                                    <td data-column="turnover" class="text-end">
                                        @if($item['turnover_rate'] == 0)
                                            <span class="badge bg-danger">0.00</span>
                                        @elseif($item['turnover_rate'] < 0.1)
                                            <span class="badge bg-warning text-dark">{{ number_format($item['turnover_rate'], 4) }}</span>
                                        @else
                                            {{ number_format($item['turnover_rate'], 4) }}
                                        @endif
                                    </td>
                                    <td data-column="stock_value" class="text-end fw-bold">{{ number_format($item['inventory_value'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="feather-trending-up" style="font-size: 3rem; color: #28a745;"></i>
                                        <p class="mt-3">No slow moving items found with the current criteria.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($slowMovingItems->isNotEmpty())
                            {{-- <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="fw-bold">Totals</td>
                                    <td class="text-end fw-bold">{{ number_format($slowMovingItems->sum('total_stock'), 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($slowMovingItems->sum('total_sold')) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($slowMovingItems->sum('order_count')) }}</td>
                                    <td colspan="3"></td>
                                    <td class="text-end fw-bold">{{ number_format($slowMovingItems->sum('inventory_value'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot> --}}
                        @endif
                    </table>
                </div>
            </div>
            @if($slowMovingItems->total() > 0)
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            Showing {{ $slowMovingItems->firstItem() }} to {{ $slowMovingItems->lastItem() }} of {{ $slowMovingItems->total() }} items
                        </div>
                        <div>
                            {{ $slowMovingItems->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('.export-excel-btn').on('click', function() {
            var tableId = $(this).data('table');
            var baseRoute = $(this).data('route');

            // Get visible columns by checking the column toggle checkboxes
            var visibleColumns = [];

            // First try to get from checkboxes (column toggle)
            var $checkboxes = $('.column-toggle-checkbox[data-table="' + tableId + '"]');
            if ($checkboxes.length > 0) {
                $checkboxes.each(function() {
                    if ($(this).is(':checked')) {
                        visibleColumns.push($(this).data('column'));
                    }
                });
            } else {
                // Fallback: get from table headers that are visible
                $('#' + tableId + ' thead th[data-column]').each(function() {
                    if ($(this).is(':visible')) {
                        visibleColumns.push($(this).data('column'));
                    }
                });
            }

            // Build URL with current filters and visible columns
            var url = new URL(baseRoute, window.location.origin);

            // Add current filter params
            var currentParams = new URLSearchParams(window.location.search);
            currentParams.forEach(function(value, key) {
                url.searchParams.append(key, value);
            });

            // Add visible columns
            visibleColumns.forEach(function(col) {
                url.searchParams.append('columns[]', col);
            });

            // Trigger download
            window.location.href = url.toString();
        });
    });
</script>
@endpush
