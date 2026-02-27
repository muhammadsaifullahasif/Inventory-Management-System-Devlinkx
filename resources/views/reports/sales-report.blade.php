@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Sales Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Sales Report</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('reports.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Reports</span>
                    </a>
                </div>
            </div>
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
                    <form action="{{ route('reports.sales') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sales Channel</label>
                                <select name="channel_id" class="form-select form-select-sm">
                                    <option value="">All Channels</option>
                                    @foreach ($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ $channelId == $channel->id ? 'selected' : '' }}>
                                            {{ $channel->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order Status</label>
                                <select name="order_status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ $orderStatus == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ $orderStatus == 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="shipped" {{ $orderStatus == 'shipped' ? 'selected' : '' }}>Shipped</option>
                                    <option value="delivered" {{ $orderStatus == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $orderStatus == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ $paymentStatus == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="paid" {{ $paymentStatus == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="refunded" {{ $paymentStatus == 'refunded' ? 'selected' : '' }}>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Sales Channel</option>
                                    <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.sales') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
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
        <!-- Sales Summary -->
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-soft-primary">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Total Orders</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['total_orders'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-primary text-white rounded">
                                    <i class="feather-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-success">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Paid Orders</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['paid_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-success text-white rounded">
                                    <i class="feather-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-info">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Shipped</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['shipped_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-info text-white rounded">
                                    <i class="feather-truck"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-danger">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Cancelled</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['cancelled_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-danger text-white rounded">
                                    <i class="feather-x-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Total Revenue</h6>
                            <h4 class="mb-0 fw-bold text-success">{{ number_format($summary['total_revenue'], 2) }}</h4>
                            <small class="text-muted">{{ $summary['total_items_sold'] }} items sold</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Avg Order Value</h6>
                            <h4 class="mb-0 fw-bold text-primary">{{ number_format($summary['average_order_value'], 2) }}</h4>
                            <small class="text-muted">per order</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Shipping Collected</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['total_shipping'], 2) }}</h4>
                            <small class="text-muted">from paid orders</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Tax Collected</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['total_tax'], 2) }}</h4>
                            <small class="text-muted">from paid orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounting Sync Summary -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-dollar-sign me-2"></i>Profit & Loss Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Sales Revenue:</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($summary['total_revenue'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Expenses (Bills):</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format($accountingSummary['total_bills'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Payments Made:</td>
                            <td class="text-end fw-semibold">{{ number_format($accountingSummary['total_payments_out'], 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Net Income:</td>
                            <td class="text-end fw-bold {{ $accountingSummary['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($accountingSummary['net_income'], 2) }}
                            </td>
                        </tr>
                    </table>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Gross Margin:</span>
                        <span class="fw-bold {{ $accountingSummary['gross_margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($accountingSummary['gross_margin'], 1) }}%
                        </span>
                    </div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar {{ $accountingSummary['gross_margin'] >= 0 ? 'bg-success' : 'bg-danger' }}"
                             style="width: {{ min(abs($accountingSummary['gross_margin']), 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grouped Report Data -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-layers me-2"></i>
                    Sales by {{ ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @if ($groupBy === 'date')
                                    <th>Date</th>
                                @elseif ($groupBy === 'product')
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th class="text-end">Avg Price</th>
                                @else
                                    <th>Sales Channel</th>
                                @endif
                                <th class="text-center">Orders</th>
                                @if ($groupBy !== 'product')
                                    <th class="text-center">Paid</th>
                                @endif
                                <th class="text-end">{{ $groupBy === 'product' ? 'Qty Sold' : 'Items Sold' }}</th>
                                <th class="text-end">Revenue</th>
                                @if ($groupBy === 'channel')
                                    <th class="text-end">Shipping</th>
                                    <th class="text-end">Tax</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $group)
                                <tr>
                                    @if ($groupBy === 'date')
                                        <td class="fw-semibold">{{ $group['formatted_date'] }}</td>
                                    @elseif ($groupBy === 'product')
                                        <td class="fw-semibold">{{ $group['name'] }}</td>
                                        <td><code>{{ $group['sku'] ?? '-' }}</code></td>
                                        <td class="text-end">{{ number_format($group['avg_price'], 2) }}</td>
                                    @else
                                        <td class="fw-semibold">{{ $group['name'] }}</td>
                                    @endif
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['order_count'] }}</span>
                                    </td>
                                    @if ($groupBy !== 'product')
                                        <td class="text-center">
                                            <span class="badge bg-soft-success text-success">{{ $group['paid_count'] }}</span>
                                        </td>
                                    @endif
                                    <td class="text-end">{{ number_format($groupBy === 'product' ? $group['quantity_sold'] : $group['items_sold'], 0) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($groupBy === 'product' ? $group['total_revenue'] : $group['total_revenue'], 2) }}</td>
                                    @if ($groupBy === 'channel')
                                        <td class="text-end">{{ number_format($group['total_shipping'], 2) }}</td>
                                        <td class="text-end">{{ number_format($group['total_tax'], 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $groupBy === 'product' ? 6 : ($groupBy === 'channel' ? 8 : 5) }}" class="text-center py-5 text-muted">
                                        <i class="feather-shopping-cart" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No orders found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($reportData->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <th>Totals</th>
                                    @if ($groupBy === 'product')
                                        <th></th>
                                        <th></th>
                                    @endif
                                    <th class="text-center">{{ $summary['total_orders'] }}</th>
                                    @if ($groupBy !== 'product')
                                        <th class="text-center">{{ $summary['paid_count'] }}</th>
                                    @endif
                                    <th class="text-end">{{ number_format($summary['total_items_sold'], 0) }}</th>
                                    <th class="text-end text-success">{{ number_format($summary['total_revenue'], 2) }}</th>
                                    @if ($groupBy === 'channel')
                                        <th class="text-end">{{ number_format($summary['total_shipping'], 2) }}</th>
                                        <th class="text-end">{{ number_format($summary['total_tax'], 2) }}</th>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Order List -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Order Details</h5>
                <span class="badge bg-soft-secondary text-secondary">{{ $orders->count() }} orders</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order #</th>
                                <th>Channel</th>
                                <th>Buyer</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Payment</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders->take(50) as $order)
                                <tr>
                                    <td><span class="fs-12 text-muted">{{ $order->order_date ? $order->order_date->format('M d, Y') : '-' }}</span></td>
                                    <td class="fw-semibold">{{ $order->order_number }}</td>
                                    <td>{{ $order->salesChannel->name ?? 'Direct' }}</td>
                                    <td>{{ $order->buyer_name ?? $order->buyer_username ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-secondary text-secondary">{{ $order->items->count() }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($order->total, 2) }}</td>
                                    <td class="text-center">
                                        @if ($order->payment_status === 'paid')
                                            <span class="badge bg-soft-success text-success">Paid</span>
                                        @elseif ($order->payment_status === 'pending')
                                            <span class="badge bg-soft-warning text-warning">Pending</span>
                                        @elseif ($order->payment_status === 'refunded')
                                            <span class="badge bg-soft-danger text-danger">Refunded</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($order->payment_status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($order->order_status === 'pending')
                                            <span class="badge bg-soft-warning text-warning">Pending</span>
                                        @elseif ($order->order_status === 'processing')
                                            <span class="badge bg-soft-info text-info">Processing</span>
                                        @elseif ($order->order_status === 'shipped')
                                            <span class="badge bg-soft-primary text-primary">Shipped</span>
                                        @elseif ($order->order_status === 'delivered')
                                            <span class="badge bg-soft-success text-success">Delivered</span>
                                        @elseif ($order->order_status === 'cancelled')
                                            <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($order->order_status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-light-brand" title="View">
                                            <i class="feather-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No orders found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($orders->count() > 50)
                    <div class="card-footer text-center">
                        <span class="text-muted small">Showing 50 of {{ $orders->count() }} orders. Apply filters to narrow results.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
