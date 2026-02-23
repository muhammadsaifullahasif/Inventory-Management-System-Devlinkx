@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Dashboard</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Dashboard</li>
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
                    <a href="javascript:void(0);" class="btn btn-icon btn-light-brand" data-bs-toggle="modal" data-bs-target="#widgetSettingsModal">
                        <i class="feather-settings"></i>
                    </a>
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
    <!-- Summary Cards Row 1 -->
    @if(($widgetSettings['revenue_today']['enabled'] ?? true) || ($widgetSettings['revenue_month']['enabled'] ?? true) || ($widgetSettings['orders_today']['enabled'] ?? true) || ($widgetSettings['orders_month']['enabled'] ?? true))
    <div class="col-12">
        <div class="row">
            @if($widgetSettings['revenue_today']['enabled'] ?? true)
            <!-- Revenue Today -->
            <div class="col-xxl-3 col-md-6" data-widget="revenue_today">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-primary text-primary">
                                    <i class="feather-dollar-sign fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Revenue Today</span>
                                    <span class="fs-24 fw-bolder d-block">${{ number_format($stats['revenue_today'], 2) }}</span>
                                </div>
                            </div>
                            <a href="{{ route('orders.index') }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Orders">
                                <i class="feather-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['revenue_month']['enabled'] ?? true)
            <!-- Revenue This Month -->
            <div class="col-xxl-3 col-md-6" data-widget="revenue_month">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-success text-success">
                                    <i class="feather-trending-up fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Revenue This Month</span>
                                    <span class="fs-24 fw-bolder d-block">${{ number_format($stats['revenue_this_month'], 2) }}</span>
                                </div>
                            </div>
                            @if($stats['revenue_growth'] > 0)
                                <div class="badge bg-soft-success text-success">
                                    <i class="feather-arrow-up fs-10 me-1"></i>
                                    <span>{{ $stats['revenue_growth'] }}%</span>
                                </div>
                            @elseif($stats['revenue_growth'] < 0)
                                <div class="badge bg-soft-danger text-danger">
                                    <i class="feather-arrow-down fs-10 me-1"></i>
                                    <span>{{ abs($stats['revenue_growth']) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['orders_today']['enabled'] ?? true)
            <!-- Orders Today -->
            <div class="col-xxl-3 col-md-6" data-widget="orders_today">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-warning text-warning">
                                    <i class="feather-shopping-cart fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Orders Today</span>
                                    <span class="fs-24 fw-bolder d-block">{{ $stats['orders_today'] }}</span>
                                </div>
                            </div>
                            <a href="{{ route('orders.index') }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Orders">
                                <i class="feather-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['orders_month']['enabled'] ?? true)
            <!-- Orders This Month -->
            <div class="col-xxl-3 col-md-6" data-widget="orders_month">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-danger text-danger">
                                    <i class="feather-calendar fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Orders This Month</span>
                                    <span class="fs-24 fw-bolder d-block">{{ $stats['orders_this_month'] }}</span>
                                </div>
                            </div>
                            @if($stats['orders_growth'] > 0)
                                <div class="badge bg-soft-success text-success">
                                    <i class="feather-arrow-up fs-10 me-1"></i>
                                    <span>{{ $stats['orders_growth'] }}%</span>
                                </div>
                            @elseif($stats['orders_growth'] < 0)
                                <div class="badge bg-soft-danger text-danger">
                                    <i class="feather-arrow-down fs-10 me-1"></i>
                                    <span>{{ abs($stats['orders_growth']) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Summary Cards Row 2 -->
    @if(($widgetSettings['pending_orders']['enabled'] ?? true) || ($widgetSettings['processing_orders']['enabled'] ?? true) || ($widgetSettings['shipped_orders']['enabled'] ?? true) || ($widgetSettings['stock_value']['enabled'] ?? true))
    <div class="col-12">
        <div class="row">
            @if($widgetSettings['pending_orders']['enabled'] ?? true)
            <!-- Pending Orders -->
            <div class="col-xxl-3 col-md-6" data-widget="pending_orders">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-warning text-warning">
                                <i class="feather-clock"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Pending Orders</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['pending_orders'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['processing_orders']['enabled'] ?? true)
            <!-- Processing Orders -->
            <div class="col-xxl-3 col-md-6" data-widget="processing_orders">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-info text-info">
                                <i class="feather-settings"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Processing</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['processing_orders'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['shipped_orders']['enabled'] ?? true)
            <!-- Shipped Orders -->
            <div class="col-xxl-3 col-md-6" data-widget="shipped_orders">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-success text-success">
                                <i class="feather-truck"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Shipped</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['shipped_orders'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['stock_value']['enabled'] ?? true)
            <!-- Total Stock Value -->
            <div class="col-xxl-3 col-md-6" data-widget="stock_value">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded bg-soft-primary text-primary">
                                <i class="feather-package"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Stock Value</span>
                                <span class="fs-20 fw-bolder d-block">${{ number_format($stats['total_stock_value'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Summary Cards Row 3 -->
    @if(($widgetSettings['total_products']['enabled'] ?? true) || ($widgetSettings['active_channels']['enabled'] ?? true) || ($widgetSettings['total_suppliers']['enabled'] ?? true) || ($widgetSettings['purchases_month']['enabled'] ?? true))
    <div class="col-12">
        <div class="row">
            @if($widgetSettings['total_products']['enabled'] ?? true)
            <!-- Total Products -->
            <div class="col-xxl-3 col-md-6" data-widget="total_products">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded">
                                <i class="feather-box"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Total Products</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['total_products'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['active_channels']['enabled'] ?? true)
            <!-- Active Sales Channels -->
            <div class="col-xxl-3 col-md-6" data-widget="active_channels">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded">
                                <i class="feather-shopping-bag"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Active Channels</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['active_sales_channels'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['total_suppliers']['enabled'] ?? true)
            <!-- Total Suppliers -->
            <div class="col-xxl-3 col-md-6" data-widget="total_suppliers">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded">
                                <i class="feather-users"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Suppliers</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['total_suppliers'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($widgetSettings['purchases_month']['enabled'] ?? true)
            <!-- Purchases This Month -->
            <div class="col-xxl-3 col-md-6" data-widget="purchases_month">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg rounded">
                                <i class="feather-file-text"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Purchases (Month)</span>
                                <span class="fs-20 fw-bolder d-block">{{ $stats['purchases_this_month'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Charts Row -->
    @if($widgetSettings['sales_chart']['enabled'] ?? true)
    <div class="col-xxl-8" data-widget="sales_chart">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-bar-chart-2 me-2"></i>Sales Overview (Last 30 Days)
                </h5>
            </div>
            <div class="card-body">
                <div id="salesChart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
    @endif

    @if($widgetSettings['order_status_chart']['enabled'] ?? true)
    <div class="col-xxl-4" data-widget="order_status_chart">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-pie-chart me-2"></i>Orders by Status
                </h5>
            </div>
            <div class="card-body">
                <div id="orderStatusChart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
    @endif

    @if($widgetSettings['monthly_chart']['enabled'] ?? true)
    <!-- Monthly Comparison Chart -->
    <div class="col-12" data-widget="monthly_chart">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-bar-chart me-2"></i>Monthly Comparison (Last 6 Months)
                </h5>
            </div>
            <div class="card-body">
                <div id="monthlyChart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tables Row -->
    @if($widgetSettings['top_products']['enabled'] ?? true)
    <div class="col-xxl-6" data-widget="top_products">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-award text-warning me-2"></i>Top Selling Products (30 Days)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Sold</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.edit', $product->id) }}" class="text-dark">
                                            {{ Illuminate\Support\Str::limit($product->name, 30) }}
                                        </a>
                                    </td>
                                    <td><span class="text-muted fs-12">{{ $product->sku }}</span></td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $product->total_sold }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">${{ number_format($product->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No sales data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($widgetSettings['low_stock']['enabled'] ?? true)
    <div class="col-xxl-6" data-widget="low_stock">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-alert-triangle text-danger me-2"></i>Low Stock Alert
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStockProducts as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.edit', $product->id) }}" class="text-dark">
                                            {{ Illuminate\Support\Str::limit($product->name, 30) }}
                                        </a>
                                    </td>
                                    <td><span class="text-muted fs-12">{{ $product->sku }}</span></td>
                                    <td class="text-center">
                                        @if($product->total_stock == 0)
                                            <span class="badge bg-soft-danger text-danger">Out of Stock</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning">{{ $product->total_stock }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('purchases.create') }}" class="btn btn-sm btn-light-brand">
                                            <i class="feather-plus me-1"></i>Restock
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">All products are well stocked</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Orders & Sales by Channel -->
    @if($widgetSettings['recent_orders']['enabled'] ?? true)
    <div class="col-xxl-8" data-widget="recent_orders">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title">
                    <i class="feather-shopping-bag me-2"></i>Recent Orders
                </h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-light-brand">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Channel</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('orders.show', $order->id) }}" class="fw-semibold">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td>{{ Illuminate\Support\Str::limit($order->buyer_name ?? $order->buyer_email ?? 'N/A', 20) }}</td>
                                    <td><span class="fs-12 text-muted">{{ $order->salesChannel->name ?? 'N/A' }}</span></td>
                                    <td class="text-center">{{ $order->items->count() }}</td>
                                    <td class="text-end fw-semibold">${{ number_format($order->total, 2) }}</td>
                                    <td class="text-center">
                                        @switch($order->order_status)
                                            @case('pending')
                                                <span class="badge bg-soft-secondary text-secondary">Pending</span>
                                                @break
                                            @case('processing')
                                                <span class="badge bg-soft-info text-info">Processing</span>
                                                @break
                                            @case('shipped')
                                                <span class="badge bg-soft-primary text-primary">Shipped</span>
                                                @break
                                            @case('delivered')
                                                <span class="badge bg-soft-success text-success">Delivered</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                                @break
                                            @case('refunded')
                                                <span class="badge bg-soft-warning text-warning">Refunded</span>
                                                @break
                                            @default
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($order->order_status) }}</span>
                                        @endswitch
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ $order->created_at->format('M d, H:i') }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No recent orders</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Sales by Channel & Recent Purchases Column -->
    <div class="col-xxl-4">
        @if($widgetSettings['sales_by_channel']['enabled'] ?? true)
        <div class="card mb-4" data-widget="sales_by_channel">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-shopping-bag me-2"></i>Sales by Channel (30 Days)
                </h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($salesByChannel as $channel)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-semibold d-block">{{ $channel->name }}</span>
                                <span class="text-muted fs-12">{{ $channel->order_count }} orders</span>
                            </div>
                            <span class="badge bg-soft-success text-success fs-12">${{ number_format($channel->total_revenue, 2) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">No sales data</li>
                    @endforelse
                </ul>
            </div>
        </div>
        @endif

        @if($widgetSettings['recent_purchases']['enabled'] ?? true)
        <div class="card" data-widget="recent_purchases">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title">
                    <i class="feather-file-text me-2"></i>Recent Purchases
                </h5>
                <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-light-brand">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentPurchases as $purchase)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('purchases.edit', $purchase->id) }}" class="fw-semibold">
                                    #{{ $purchase->id }}
                                </a>
                                <span class="text-muted fs-12">{{ $purchase->created_at->format('M d') }}</span>
                            </div>
                            <span class="text-muted fs-12">
                                {{ $purchase->supplier->name ?? 'N/A' }} - {{ $purchase->warehouse->name ?? 'N/A' }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-4">No recent purchases</li>
                    @endforelse
                </ul>
            </div>
        </div>
        @endif
    </div>

    <!-- Hidden form for reset -->
    <form id="reset-widgets-form" action="{{ route('dashboard.widgets.reset') }}" method="POST" style="display: none;">
        @csrf
    </form>
@endsection

@push('modals')
    <!-- Widget Settings Modal -->
    <div class="modal fade" id="widgetSettingsModal" tabindex="-1" aria-labelledby="widgetSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="widgetSettingsModalLabel">
                        <i class="feather-grid me-2"></i>Customize Dashboard Widgets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('dashboard.widgets.update') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted mb-4">Select which widgets to display on your dashboard.</p>

                        <h6 class="text-uppercase text-muted fs-11 fw-bold mb-3">Summary Cards</h6>
                        <div class="row mb-4">
                            @foreach(['revenue_today', 'revenue_month', 'orders_today', 'orders_month', 'pending_orders', 'processing_orders', 'shipped_orders', 'stock_value', 'total_products', 'active_channels', 'total_suppliers', 'purchases_month'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <h6 class="text-uppercase text-muted fs-11 fw-bold mb-3">Charts</h6>
                        <div class="row mb-4">
                            @foreach(['sales_chart', 'order_status_chart', 'monthly_chart'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <h6 class="text-uppercase text-muted fs-11 fw-bold mb-3">Tables & Lists</h6>
                        <div class="row mb-3">
                            @foreach(['top_products', 'low_stock', 'recent_orders', 'sales_by_channel', 'recent_purchases'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('dashboard.widgets.reset') }}" class="btn btn-outline-secondary me-auto"
                           onclick="event.preventDefault(); document.getElementById('reset-widgets-form').submit();">
                            <i class="feather-refresh-cw me-1"></i>Reset to Default
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($widgetSettings['sales_chart']['enabled'] ?? true)
        // Sales Chart (ApexCharts)
        var salesOptions = {
            series: [{
                name: 'Revenue ($)',
                type: 'area',
                data: @json($salesChartData['revenue'])
            }, {
                name: 'Orders',
                type: 'line',
                data: @json($salesChartData['orders'])
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            colors: ['#28a745', '#007bff'],
            stroke: {
                curve: 'smooth',
                width: [2, 2]
            },
            fill: {
                type: ['gradient', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.1,
                }
            },
            labels: @json($salesChartData['labels']),
            yaxis: [{
                title: {
                    text: 'Revenue ($)',
                },
            }, {
                opposite: true,
                title: {
                    text: 'Orders'
                }
            }],
            tooltip: {
                shared: true,
                intersect: false,
            },
            legend: {
                position: 'top'
            }
        };
        var salesChart = new ApexCharts(document.querySelector("#salesChart"), salesOptions);
        salesChart.render();
        @endif

        @if($widgetSettings['order_status_chart']['enabled'] ?? true)
        // Order Status Pie Chart (ApexCharts)
        var ordersByStatus = @json($ordersByStatus);
        var statusLabels = Object.keys(ordersByStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' '));
        var statusData = Object.values(ordersByStatus);
        var statusColors = {
            'pending': '#6c757d',
            'processing': '#17a2b8',
            'shipped': '#007bff',
            'delivered': '#28a745',
            'cancelled': '#dc3545',
            'refunded': '#ffc107',
            'ready_for_pickup': '#20c997',
            'cancellation_requested': '#fd7e14'
        };
        var backgroundColors = Object.keys(ordersByStatus).map(s => statusColors[s] || '#6c757d');

        var orderStatusOptions = {
            series: statusData,
            chart: {
                type: 'donut',
                height: 350
            },
            labels: statusLabels,
            colors: backgroundColors,
            legend: {
                position: 'bottom'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%'
                    }
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        };
        var orderStatusChart = new ApexCharts(document.querySelector("#orderStatusChart"), orderStatusOptions);
        orderStatusChart.render();
        @endif

        @if($widgetSettings['monthly_chart']['enabled'] ?? true)
        // Monthly Comparison Chart (ApexCharts)
        var monthlyOptions = {
            series: [{
                name: 'Revenue ($)',
                data: @json($monthlyComparison['revenue'])
            }, {
                name: 'Orders',
                data: @json($monthlyComparison['orders'])
            }],
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            colors: ['#28a745', '#007bff'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 4
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: @json($monthlyComparison['labels']),
            },
            yaxis: [{
                title: {
                    text: 'Revenue ($)'
                }
            }, {
                opposite: true,
                title: {
                    text: 'Orders'
                }
            }],
            fill: {
                opacity: 1
            },
            legend: {
                position: 'top'
            },
            tooltip: {
                y: {
                    formatter: function (val, { seriesIndex }) {
                        return seriesIndex === 0 ? "$" + val.toLocaleString() : val
                    }
                }
            }
        };
        var monthlyChart = new ApexCharts(document.querySelector("#monthlyChart"), monthlyOptions);
        monthlyChart.render();
        @endif
    });
</script>
@endpush
