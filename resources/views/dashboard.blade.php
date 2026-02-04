@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">Dashboard</li>
                        <li class="breadcrumb-item">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#widgetSettingsModal">
                                <i class="fas fa-cog"></i> Customize
                            </button>
                        </li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Summary Cards Row 1 -->
    <div class="row">
        @if($widgetSettings['revenue_today']['enabled'] ?? true)
        <!-- Revenue Today -->
        <div class="col-lg-3 col-6" data-widget="revenue_today">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>${{ number_format($stats['revenue_today'], 2) }}</h3>
                    <p>Revenue Today</p>
                </div>
                <div class="icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <a href="{{ route('orders.index') }}" class="small-box-footer">View Orders <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        @endif

        @if($widgetSettings['revenue_month']['enabled'] ?? true)
        <!-- Revenue This Month -->
        <div class="col-lg-3 col-6" data-widget="revenue_month">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>${{ number_format($stats['revenue_this_month'], 2) }}</h3>
                    <p>Revenue This Month
                        @if($stats['revenue_growth'] > 0)
                            <span class="badge badge-light"><i class="fas fa-arrow-up text-success"></i> {{ $stats['revenue_growth'] }}%</span>
                        @elseif($stats['revenue_growth'] < 0)
                            <span class="badge badge-light"><i class="fas fa-arrow-down text-danger"></i> {{ abs($stats['revenue_growth']) }}%</span>
                        @endif
                    </p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <a href="{{ route('orders.index') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        @endif

        @if($widgetSettings['orders_today']['enabled'] ?? true)
        <!-- Orders Today -->
        <div class="col-lg-3 col-6" data-widget="orders_today">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['orders_today'] }}</h3>
                    <p>Orders Today</p>
                </div>
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <a href="{{ route('orders.index') }}" class="small-box-footer">View Orders <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        @endif

        @if($widgetSettings['orders_month']['enabled'] ?? true)
        <!-- Orders This Month -->
        <div class="col-lg-3 col-6" data-widget="orders_month">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $stats['orders_this_month'] }}</h3>
                    <p>Orders This Month
                        @if($stats['orders_growth'] > 0)
                            <span class="badge badge-light"><i class="fas fa-arrow-up text-success"></i> {{ $stats['orders_growth'] }}%</span>
                        @elseif($stats['orders_growth'] < 0)
                            <span class="badge badge-light"><i class="fas fa-arrow-down text-danger"></i> {{ abs($stats['orders_growth']) }}%</span>
                        @endif
                    </p>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <a href="{{ route('orders.index') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        @endif
    </div>

    <!-- Summary Cards Row 2 -->
    <div class="row">
        @if($widgetSettings['pending_orders']['enabled'] ?? true)
        <!-- Pending Orders -->
        <div class="col-lg-3 col-6" data-widget="pending_orders">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending Orders</span>
                    <span class="info-box-number">{{ $stats['pending_orders'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['processing_orders']['enabled'] ?? true)
        <!-- Processing Orders -->
        <div class="col-lg-3 col-6" data-widget="processing_orders">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-cog"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Processing</span>
                    <span class="info-box-number">{{ $stats['processing_orders'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['shipped_orders']['enabled'] ?? true)
        <!-- Shipped Orders -->
        <div class="col-lg-3 col-6" data-widget="shipped_orders">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-shipping-fast"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Shipped</span>
                    <span class="info-box-number">{{ $stats['shipped_orders'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['stock_value']['enabled'] ?? true)
        <!-- Total Stock Value -->
        <div class="col-lg-3 col-6" data-widget="stock_value">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-warehouse"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Stock Value</span>
                    <span class="info-box-number">${{ number_format($stats['total_stock_value'], 2) }}</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Summary Cards Row 3 -->
    <div class="row">
        @if($widgetSettings['total_products']['enabled'] ?? true)
        <!-- Total Products -->
        <div class="col-lg-3 col-6" data-widget="total_products">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-boxes text-primary"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Products</span>
                    <span class="info-box-number">{{ $stats['total_products'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['active_channels']['enabled'] ?? true)
        <!-- Active Sales Channels -->
        <div class="col-lg-3 col-6" data-widget="active_channels">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-store text-success"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Channels</span>
                    <span class="info-box-number">{{ $stats['active_sales_channels'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['total_suppliers']['enabled'] ?? true)
        <!-- Total Suppliers -->
        <div class="col-lg-3 col-6" data-widget="total_suppliers">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-truck text-info"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Suppliers</span>
                    <span class="info-box-number">{{ $stats['total_suppliers'] }}</span>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['purchases_month']['enabled'] ?? true)
        <!-- Purchases This Month -->
        <div class="col-lg-3 col-6" data-widget="purchases_month">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-file-invoice text-warning"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Purchases (Month)</span>
                    <span class="info-box-number">{{ $stats['purchases_this_month'] }}</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Charts Row -->
    <div class="row">
        @if($widgetSettings['sales_chart']['enabled'] ?? true)
        <!-- Sales Chart (Last 30 Days) -->
        <div class="col-lg-8" data-widget="sales_chart">
            <div class="card">
                <div class="card-header border-0">
                    <div class="d-flex justify-content-between">
                        <h3 class="card-title"><i class="fas fa-chart-area mr-2"></i>Sales Overview (Last 30 Days)</h3>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="250"></canvas>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['order_status_chart']['enabled'] ?? true)
        <!-- Order Status Pie Chart -->
        <div class="col-lg-4" data-widget="order_status_chart">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Orders by Status</h3>
                </div>
                <div class="card-body">
                    <canvas id="orderStatusChart" height="250"></canvas>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if($widgetSettings['monthly_chart']['enabled'] ?? true)
    <!-- Monthly Comparison Chart -->
    <div class="row" data-widget="monthly_chart">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Monthly Comparison (Last 6 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tables Row -->
    <div class="row">
        @if($widgetSettings['top_products']['enabled'] ?? true)
        <!-- Top Selling Products -->
        <div class="col-lg-6" data-widget="top_products">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-trophy mr-2 text-warning"></i>Top Selling Products (30 Days)</h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 400px;">
                    <table class="table table-striped table-valign-middle table-sm">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Sold</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.edit', $product->id) }}">
                                            {{ Illuminate\Support\Str::limit($product->name, 30) }}
                                        </a>
                                    </td>
                                    <td><small class="text-muted">{{ $product->sku }}</small></td>
                                    <td class="text-center"><span class="badge badge-primary">{{ $product->total_sold }}</span></td>
                                    <td class="text-right">${{ number_format($product->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No sales data available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($widgetSettings['low_stock']['enabled'] ?? true)
        <!-- Low Stock Products -->
        <div class="col-lg-6" data-widget="low_stock">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Low Stock Alert</h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 400px;">
                    <table class="table table-striped table-valign-middle table-sm">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th class="text-center">Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStockProducts as $product)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.edit', $product->id) }}">
                                            {{ Illuminate\Support\Str::limit($product->name, 30) }}
                                        </a>
                                    </td>
                                    <td><small class="text-muted">{{ $product->sku }}</small></td>
                                    <td class="text-center">
                                        @if($product->total_stock == 0)
                                            <span class="badge badge-danger">Out of Stock</span>
                                        @else
                                            <span class="badge badge-warning">{{ $product->total_stock }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('purchases.create') }}" class="btn btn-xs btn-outline-primary">
                                            <i class="fas fa-plus"></i> Restock
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">All products are well stocked</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Orders & Sales by Channel -->
    <div class="row">
        @if($widgetSettings['recent_orders']['enabled'] ?? true)
        <!-- Recent Orders -->
        <div class="col-lg-8" data-widget="recent_orders">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-shopping-bag mr-2"></i>Recent Orders</h3>
                    <div class="card-tools">
                        <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-valign-middle table-sm">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Channel</th>
                                <th class="text-center">Items</th>
                                <th class="text-right">Total</th>
                                <th class="text-center">Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('orders.show', $order->id) }}">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td>{{ Illuminate\Support\Str::limit($order->buyer_name ?? $order->buyer_email ?? 'N/A', 20) }}</td>
                                    <td><small>{{ $order->salesChannel->name ?? 'N/A' }}</small></td>
                                    <td class="text-center">{{ $order->items->count() }}</td>
                                    <td class="text-right">${{ number_format($order->total, 2) }}</td>
                                    <td class="text-center">
                                        @switch($order->order_status)
                                            @case('pending')
                                                <span class="badge badge-secondary">Pending</span>
                                                @break
                                            @case('processing')
                                                <span class="badge badge-info">Processing</span>
                                                @break
                                            @case('shipped')
                                                <span class="badge badge-primary">Shipped</span>
                                                @break
                                            @case('delivered')
                                                <span class="badge badge-success">Delivered</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-danger">Cancelled</span>
                                                @break
                                            @case('refunded')
                                                <span class="badge badge-warning">Refunded</span>
                                                @break
                                            @default
                                                <span class="badge badge-light">{{ ucfirst($order->order_status) }}</span>
                                        @endswitch
                                    </td>
                                    <td><small>{{ $order->created_at->format('M d, H:i') }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No recent orders</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Sales by Channel & Recent Purchases Column -->
        <div class="col-lg-4">
            @if($widgetSettings['sales_by_channel']['enabled'] ?? true)
            <!-- Sales by Channel -->
            <div class="card" data-widget="sales_by_channel">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-store mr-2"></i>Sales by Channel (30 Days)</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($salesByChannel as $channel)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $channel->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $channel->order_count }} orders</small>
                                </div>
                                <span class="badge badge-success badge-pill">${{ number_format($channel->total_revenue, 2) }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No sales data</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            @endif

            @if($widgetSettings['recent_purchases']['enabled'] ?? true)
            <!-- Recent Purchases -->
            <div class="card mt-3" data-widget="recent_purchases">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Recent Purchases</h3>
                    <div class="card-tools">
                        <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentPurchases as $purchase)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('purchases.edit', $purchase->id) }}">
                                        #{{ $purchase->id }}
                                    </a>
                                    <small class="text-muted">{{ $purchase->created_at->format('M d') }}</small>
                                </div>
                                <small>
                                    {{ $purchase->supplier->name ?? 'N/A' }} - {{ $purchase->warehouse->name ?? 'N/A' }}
                                </small>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted">No recent purchases</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Widget Settings Modal -->
    <div class="modal fade" id="widgetSettingsModal" tabindex="-1" aria-labelledby="widgetSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="widgetSettingsModalLabel">
                        <i class="fas fa-th-large mr-2"></i>Customize Dashboard Widgets
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('dashboard.widgets.update') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted mb-3">Select which widgets to display on your dashboard.</p>

                        <h6 class="text-uppercase text-muted mb-2">Summary Cards</h6>
                        <div class="row mb-3">
                            @foreach(['revenue_today', 'revenue_month', 'orders_today', 'orders_month', 'pending_orders', 'processing_orders', 'shipped_orders', 'stock_value', 'total_products', 'active_channels', 'total_suppliers', 'purchases_month'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <hr>

                        <h6 class="text-uppercase text-muted mb-2">Charts</h6>
                        <div class="row mb-3">
                            @foreach(['sales_chart', 'order_status_chart', 'monthly_chart'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <hr>

                        <h6 class="text-uppercase text-muted mb-2">Tables & Lists</h6>
                        <div class="row mb-3">
                            @foreach(['top_products', 'low_stock', 'recent_orders', 'sales_by_channel', 'recent_purchases'] as $widget)
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="widget_{{ $widget }}" name="widgets[{{ $widget }}]" {{ ($widgetSettings[$widget]['enabled'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="widget_{{ $widget }}">{{ $widgetSettings[$widget]['label'] ?? ucwords(str_replace('_', ' ', $widget)) }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('dashboard.widgets.reset') }}" class="btn btn-outline-secondary mr-auto"
                           onclick="event.preventDefault(); document.getElementById('reset-widgets-form').submit();">
                            <i class="fas fa-undo mr-1"></i>Reset to Default
                        </a>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form for reset -->
    <form id="reset-widgets-form" action="{{ route('dashboard.widgets.reset') }}" method="POST" style="display: none;">
        @csrf
    </form>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($widgetSettings['sales_chart']['enabled'] ?? true)
        // Sales Chart (Line/Area Chart)
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) {
            new Chart(salesCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: @json($salesChartData['labels']),
                    datasets: [{
                        label: 'Revenue ($)',
                        data: @json($salesChartData['revenue']),
                        borderColor: 'rgb(40, 167, 69)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    }, {
                        label: 'Orders',
                        data: @json($salesChartData['orders']),
                        borderColor: 'rgb(0, 123, 255)',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
        @endif

        @if($widgetSettings['order_status_chart']['enabled'] ?? true)
        // Order Status Pie Chart
        const orderStatusCtx = document.getElementById('orderStatusChart');
        if (orderStatusCtx) {
            const ordersByStatus = @json($ordersByStatus);
            const statusLabels = Object.keys(ordersByStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' '));
            const statusData = Object.values(ordersByStatus);
            const statusColors = {
                'pending': '#6c757d',
                'processing': '#17a2b8',
                'shipped': '#007bff',
                'delivered': '#28a745',
                'cancelled': '#dc3545',
                'refunded': '#ffc107',
                'ready_for_pickup': '#20c997',
                'cancellation_requested': '#fd7e14'
            };
            const backgroundColors = Object.keys(ordersByStatus).map(s => statusColors[s] || '#6c757d');

            new Chart(orderStatusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: backgroundColors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        @endif

        @if($widgetSettings['monthly_chart']['enabled'] ?? true)
        // Monthly Comparison Chart
        const monthlyCtx = document.getElementById('monthlyChart');
        if (monthlyCtx) {
            new Chart(monthlyCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($monthlyComparison['labels']),
                    datasets: [{
                        label: 'Revenue ($)',
                        data: @json($monthlyComparison['revenue']),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgb(40, 167, 69)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Orders',
                        data: @json($monthlyComparison['orders']),
                        backgroundColor: 'rgba(0, 123, 255, 0.7)',
                        borderColor: 'rgb(0, 123, 255)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
        @endif
    });
</script>
@endpush
