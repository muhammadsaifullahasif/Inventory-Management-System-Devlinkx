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
        <!-- Revenue Today -->
        <div class="col-lg-3 col-6">
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

        <!-- Revenue This Month -->
        <div class="col-lg-3 col-6">
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

        <!-- Orders Today -->
        <div class="col-lg-3 col-6">
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

        <!-- Orders This Month -->
        <div class="col-lg-3 col-6">
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
    </div>

    <!-- Summary Cards Row 2 -->
    <div class="row">
        <!-- Pending Orders -->
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending Orders</span>
                    <span class="info-box-number">{{ $stats['pending_orders'] }}</span>
                </div>
            </div>
        </div>

        <!-- Processing Orders -->
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-cog"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Processing</span>
                    <span class="info-box-number">{{ $stats['processing_orders'] }}</span>
                </div>
            </div>
        </div>

        <!-- Shipped Orders -->
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-shipping-fast"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Shipped</span>
                    <span class="info-box-number">{{ $stats['shipped_orders'] }}</span>
                </div>
            </div>
        </div>

        <!-- Total Stock Value -->
        <div class="col-lg-3 col-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-warehouse"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Stock Value</span>
                    <span class="info-box-number">${{ number_format($stats['total_stock_value'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row 3 -->
    <div class="row">
        <!-- Total Products -->
        <div class="col-lg-3 col-6">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-boxes text-primary"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Products</span>
                    <span class="info-box-number">{{ $stats['total_products'] }}</span>
                </div>
            </div>
        </div>

        <!-- Active Sales Channels -->
        <div class="col-lg-3 col-6">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-store text-success"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Channels</span>
                    <span class="info-box-number">{{ $stats['active_sales_channels'] }}</span>
                </div>
            </div>
        </div>

        <!-- Total Suppliers -->
        <div class="col-lg-3 col-6">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-truck text-info"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Suppliers</span>
                    <span class="info-box-number">{{ $stats['total_suppliers'] }}</span>
                </div>
            </div>
        </div>

        <!-- Purchases This Month -->
        <div class="col-lg-3 col-6">
            <div class="info-box bg-light">
                <span class="info-box-icon"><i class="fas fa-file-invoice text-warning"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Purchases (Month)</span>
                    <span class="info-box-number">{{ $stats['purchases_this_month'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Sales Chart (Last 30 Days) -->
        <div class="col-lg-8">
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

        <!-- Order Status Pie Chart -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Orders by Status</h3>
                </div>
                <div class="card-body">
                    <canvas id="orderStatusChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Comparison Chart -->
    <div class="row">
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

    <!-- Tables Row -->
    <div class="row">
        <!-- Top Selling Products -->
        <div class="col-lg-6">
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

        <!-- Low Stock Products -->
        <div class="col-lg-6">
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
    </div>

    <!-- Recent Orders & Sales by Channel -->
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-8">
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

        <!-- Sales by Channel -->
        <div class="col-lg-4">
            <div class="card">
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

            <!-- Recent Purchases -->
            <div class="card mt-3">
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
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sales Chart (Line/Area Chart)
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
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

        // Order Status Pie Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
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

        new Chart(orderStatusCtx, {
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

        // Monthly Comparison Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
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
    });
</script>
@endpush