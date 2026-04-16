@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Frequently Ordered Items Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Frequently Ordered Items</li>
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
                    <form action="{{ route('reports.frequently-ordered-items') }}" method="GET">
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
                                <label class="form-label">Sales Channel</label>
                                <select name="channel_id" class="form-select form-select-sm">
                                    <option value="">-- All Channels --</option>
                                    @foreach($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ $channelId == $channel->id ? 'selected' : '' }}>
                                            {{ $channel->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Channel</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Limit</label>
                                <select name="limit" class="form-select form-select-sm">
                                    <option value="25" {{ $limit == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100</option>
                                    <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.frequently-ordered-items') }}" class="btn btn-light-brand btn-sm">
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
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Top Products</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_items']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-primary text-white rounded">
                            <i class="feather-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Qty Sold</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_quantity_sold']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-success text-white rounded">
                            <i class="feather-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Revenue</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_revenue'], 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Orders</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_orders']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-file-text"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-secondary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Period</h6>
                            <h3 class="mb-0 fw-bold">{{ $summary['period_days'] }} days</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-secondary text-white rounded">
                            <i class="feather-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4">
            <div class="card bg-soft-dark">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Avg Daily Items</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['avg_daily_items'], 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-dark text-white rounded">
                            <i class="feather-activity"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($groupBy === 'category' && $groupedData->isNotEmpty())
        <!-- Grouped by Category -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-grid me-2"></i>Sales by Category</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Products</th>
                                    <th class="text-end">Total Quantity</th>
                                    <th class="text-end">Total Revenue</th>
                                    <th class="text-end">% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupedData as $group)
                                    <tr>
                                        <td><strong>{{ $group['name'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary">{{ $group['item_count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($group['total_quantity']) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($group['total_revenue'], 2) }}</td>
                                        <td class="text-end">
                                            @php
                                                $percentage = $summary['total_revenue'] > 0
                                                    ? ($group['total_revenue'] / $summary['total_revenue']) * 100
                                                    : 0;
                                            @endphp
                                            <div class="progress" style="height: 6px; width: 80px; display: inline-block;">
                                                <div class="progress-bar bg-success" style="width: {{ min($percentage, 100) }}%"></div>
                                            </div>
                                            <span class="ms-2">{{ number_format($percentage, 1) }}%</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($groupBy === 'channel' && $groupedData->isNotEmpty())
        <!-- Grouped by Channel -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-share-2 me-2"></i>Sales by Channel</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Channel</th>
                                    <th class="text-center">Unique Products</th>
                                    <th class="text-end">Total Quantity</th>
                                    <th class="text-end">Total Orders</th>
                                    <th class="text-end">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupedData as $channel)
                                    <tr>
                                        <td><strong>{{ $channel['name'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary">{{ $channel['unique_products'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($channel['total_quantity']) }}</td>
                                        <td class="text-end">{{ number_format($channel['order_count']) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($channel['total_revenue'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Frequently Ordered Items Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-trending-up me-2"></i>Top Selling Products</h5>
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <span class="badge bg-success">{{ $frequentItems->count() }} products</span>
                    <button type="button" class="btn btn-success btn-sm export-excel-btn" data-table="frequentlyOrderStockTable" data-route="{{ route('reports.frequently-ordered-items.export') }}">
                        <i class="feather-download me-1"></i> Export Excel
                    </button>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        @php
                            $frequentlyOrderStockColumns = [
                                ['key' => 'id', 'label' => '#', 'default' => true],
                                ['key' => 'image', 'label' => 'Image', 'default' => true],
                                ['key' => 'product', 'label' => 'Product', 'default' => true],
                                ['key' => 'category', 'label' => 'Category', 'default' => false],
                                ['key' => 'last_purchase_quantity', 'label' => 'Last Purchase Qty', 'default' => true],
                                ['key' => 'last_purchase', 'label' => 'Last Purchase', 'default' => true],
                                ['key' => 'last_order', 'label' => 'Last Order', 'default' => true],
                                ['key' => 'sold_quantity', 'label' => 'Sold', 'default' => true],
                                ['key' => 'stock', 'label' => 'Stock', 'default' => true],
                                ['key' => 'orders', 'label' => 'Orders', 'default' => false],
                                ['key' => 'revenue', 'label' => 'Revenue', 'default' => false],
                                ['key' => 'average_price', 'label' => 'Avg Price', 'default' => false],
                                ['key' => 'average_order', 'label' => 'Avg/Order', 'default' => false],
                                ['key' => 'days_of_stock', 'label' => 'Days of Stock', 'default' => false],
                            ];
                        @endphp
                        @include('partials.column-toggle', ['tableId' => 'frequentlyOrderStockTable', 'cookieName' => 'frequently_order_stock_columns', 'columns' => $frequentlyOrderStockColumns])
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="frequentlyOrderStockTable">
                        <thead>
                            <tr>
                                <th data-column="id" style="width: 40px;">#</th>
                                <th data-column="image">Image</th>
                                <th data-column="product" style="max-width: 200px;">Product</th>
                                <th data-column="category">Category</th>
                                <th data-column="last_purchase_quantity">Last Purchase Qty</th>
                                <th data-column="last_purchase">Last Purchase</th>
                                <th data-column="last_order">Last Order</th>
                                <th data-column="sold_quantity" class="text-end">Qty Sold</th>
                                <th data-column="stock" class="text-end">Current Stock</th>
                                <th data-column="orders" class="text-end">Orders</th>
                                <th data-column="revenue" class="text-end">Revenue</th>
                                <th data-column="average_price" class="text-end">Avg Price</th>
                                <th data-column="average_order" class="text-end">Avg/Order</th>
                                <th data-column="days_of_stock" class="text-end">Days of Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($frequentItems as $index => $item)
                                @php
                                    $globalIndex = ($frequentItems->currentPage() - 1) * $frequentItems->perPage() + $loop->iteration - 1;
                                @endphp
                                <tr>
                                    <td data-column="id">
                                        @if($globalIndex < 3)
                                            <span class="badge bg-{{ $globalIndex == 0 ? 'warning' : ($globalIndex == 1 ? 'secondary' : 'danger') }}">
                                                {{ $globalIndex + 1 }}
                                            </span>
                                        @else
                                            <span class="text-muted">{{ $globalIndex + 1 }}</span>
                                        @endif
                                    </td>
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
                                        @if($item['product_id'])
                                            <a href="{{ route('products.show', $item['product_id']) }}" class="fw-semibold">
                                                {{ $item['product_name'] }}
                                            </a>
                                            <span class="d-block fs-11 text-muted">SKU: {{ $item['product_sku'] }}</span>
                                        @else
                                            <span class="fw-semibold">{{ $item['product_name'] }}</span>
                                        @endif
                                    </td>
                                    <td data-column="category">
                                        <span class="badge bg-soft-secondary text-secondary">{{ $item['category_name'] }}</span>
                                    </td>
                                    <td data-column="last_purchase_quantity">{{ $item['last_purchase_quantity'] }}</td>
                                    <td data-column="last_purchase">{{ \Carbon\Carbon::parse($item['last_purchase_date'])->format('M d, Y') }}</td>
                                    <td data-column="last_order">{{ \Carbon\Carbon::parse($item['last_order_date'])->format('M d, Y') }}</td>
                                    <td data-column="sold_quantity" class="text-end fw-bold text-success">{{ number_format($item['total_quantity']) }}</td>
                                    <td data-column="stock" class="text-end">
                                        @if($item['current_stock'] <= 0)
                                            <span class="badge bg-danger">0</span>
                                        @elseif($item['current_stock'] < 10)
                                            <span class="badge bg-warning text-dark">{{ number_format($item['current_stock'], 2) }}</span>
                                        @else
                                            {{ number_format($item['current_stock'], 2) }}
                                        @endif
                                    </td>
                                    <td data-column="orders" class="text-end">{{ number_format($item['order_count']) }}</td>
                                    <td data-column="revenue" class="text-end fw-bold">{{ number_format($item['total_revenue'], 2) }}</td>
                                    <td data-column="average_price" class="text-end">{{ number_format($item['avg_unit_price'], 2) }}</td>
                                    <td data-column="average_order" class="text-end">{{ number_format($item['avg_per_order'], 2) }}</td>
                                    <td data-column="days_of_stock" class="text-end">
                                        @if($item['days_of_stock'])
                                            @if($item['days_of_stock'] < 7)
                                                <span class="text-danger fw-bold">{{ $item['days_of_stock'] }} days</span>
                                            @elseif($item['days_of_stock'] < 30)
                                                <span class="text-warning">{{ $item['days_of_stock'] }} days</span>
                                            @else
                                                {{ $item['days_of_stock'] }} days
                                            @endif
                                        @else
                                            <span class="text-danger">Out of Stock</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="feather-package" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No sales data found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($frequentItems->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="fw-bold">Totals</td>
                                    <td class="text-end fw-bold">{{ number_format($frequentItems->sum('total_quantity')) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($frequentItems->sum('order_count')) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($frequentItems->sum('total_revenue'), 2) }}</td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @if($frequentItems->total() > 0)
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            Showing {{ $frequentItems->firstItem() }} to {{ $frequentItems->lastItem() }} of {{ $frequentItems->total() }} items
                        </div>
                        <div>
                            {{ $frequentItems->appends(request()->query())->links('pagination::bootstrap-5') }}
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
