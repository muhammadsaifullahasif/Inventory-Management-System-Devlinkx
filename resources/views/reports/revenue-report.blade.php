@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Revenue Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Revenue Report</li>
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
                    <form action="{{ route('reports.revenue') }}" method="GET">
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
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.revenue') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                                @if($orders->isNotEmpty())
                                    <a href="{{ route('reports.revenue.export', request()->query()) }}" class="btn btn-success btn-sm">
                                        <i class="feather-download me-2"></i>Export to Excel
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-soft-success">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1 small">Gross Revenue @include('partials.info-tooltip', ['text' => 'Sum of orders.total for orders with payment_status paid OR refunded (a refund deducts from revenue already recognized, it doesnt erase the sale) in the date range.'])</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['gross_revenue'], 2) }}</h4>
                        </div>
                        <div class="avatar-text avatar-md bg-success text-white rounded">
                            <i class="feather-dollar-sign"></i>
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
                            <h6 class="text-muted mb-1 small">Total Refunds @include('partials.info-tooltip', ['text' => 'Sum of orders.total_refunded for the same paid+refunded order set.'])</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['total_refunds'], 2) }}</h4>
                        </div>
                        <div class="avatar-text avatar-md bg-danger text-white rounded">
                            <i class="feather-corner-up-left"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-soft-primary">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1 small">Net Revenue @include('partials.info-tooltip', ['text' => 'Gross Revenue minus Total Refunds.'])</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['net_revenue'], 2) }}</h4>
                        </div>
                        <div class="avatar-text avatar-md bg-primary text-white rounded">
                            <i class="feather-trending-up"></i>
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
                            <h6 class="text-muted mb-1 small">Refund Rate @include('partials.info-tooltip', ['text' => 'Total Refunds / Gross Revenue x 100.'])</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['refund_rate'], 1) }}%</h4>
                        </div>
                        <div class="avatar-text avatar-md bg-info text-white rounded">
                            <i class="feather-percent"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Total Orders @include('partials.info-tooltip', ['text' => 'Count of all orders in range, any payment/order status.'])</h6>
                    <h4 class="mb-0 fw-bold">{{ $summary['total_orders'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Paid / Refunded @include('partials.info-tooltip', ['text' => 'Count of orders with payment_status=paid, and separately payment_status=refunded, within Total Orders. Partially refunded = isPartiallyRefunded() true (total_refunded > 0 but < total).'])</h6>
                    <h4 class="mb-0 fw-bold">{{ $summary['paid_count'] }} / {{ $summary['refunded_count'] }}</h4>
                    <small class="text-muted">{{ $summary['partially_refunded_count'] }} partially refunded</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Avg Order Value (Net) @include('partials.info-tooltip', ['text' => 'Net Revenue / count of paid+refunded orders.'])</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['average_order_value'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Sale Lines @include('partials.info-tooltip', ['text' => 'Sum of item quantity across paid+refunded orders, counting a bundle as 1 unit (its summary line) - bundle component lines excluded here.'])</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['sale_lines'], 0) }}</h4>
                    <small class="text-muted" data-bs-toggle="tooltip" title="Physical piece count - a bundle's components count individually instead of the summary line.">{{ number_format($summary['total_items_sold'], 0) }} total items sold</small>
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
                    Revenue by {{ ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                    @if (in_array($groupBy, ['product', 'category']))
                        <span class="badge bg-soft-secondary text-secondary ms-1">Gross revenue only - refunds tracked at order level</span>
                    @endif
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @php $isItemGrouping = in_array($groupBy, ['product', 'category']); @endphp
                                @if ($groupBy === 'date')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Date'])
                                @elseif ($groupBy === 'product')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Product'])
                                    @include('partials.sortable-th', ['column' => 'sku', 'label' => 'SKU'])
                                @elseif ($groupBy === 'category')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Category'])
                                @else
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Sales Channel'])
                                @endif
                                @if (!$isItemGrouping)
                                    @include('partials.sortable-th', ['column' => 'order_count', 'label' => 'Orders', 'class' => 'text-center'])
                                @else
                                    @include('partials.sortable-th', ['column' => 'order_count', 'label' => 'Lines', 'class' => 'text-center'])
                                @endif
                                @include('partials.sortable-th', ['column' => $isItemGrouping ? 'quantity_sold' : 'items_sold', 'label' => 'Qty Sold', 'class' => 'text-end'])
                                @if ($isItemGrouping)
                                    @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'])
                                @else
                                    @include('partials.sortable-th', ['column' => 'gross_revenue', 'label' => 'Gross Revenue', 'class' => 'text-end'])
                                    @include('partials.sortable-th', ['column' => 'total_refunds', 'label' => 'Refunds', 'class' => 'text-end'])
                                    @include('partials.sortable-th', ['column' => 'net_revenue', 'label' => 'Net Revenue', 'class' => 'text-end'])
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
                                    @else
                                        <td class="fw-semibold">{{ $group['name'] }}</td>
                                    @endif
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['order_count'] }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($isItemGrouping ? $group['quantity_sold'] : $group['items_sold'], 0) }}</td>
                                    @if ($isItemGrouping)
                                        <td class="text-end text-success fw-semibold">{{ number_format($group['total_revenue'], 2) }}</td>
                                    @else
                                        <td class="text-end">{{ number_format($group['gross_revenue'], 2) }}</td>
                                        <td class="text-end text-danger">{{ number_format($group['total_refunds'], 2) }}</td>
                                        <td class="text-end text-success fw-semibold">{{ number_format($group['net_revenue'], 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-dollar-sign" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No orders found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($reportData->isNotEmpty() && !$isItemGrouping)
                            <tfoot>
                                <tr class="table-light">
                                    <th>Totals</th>
                                    <th class="text-center">{{ $summary['total_orders'] }}</th>
                                    <th class="text-end">{{ number_format($summary['total_items_sold'], 0) }}</th>
                                    <th class="text-end">{{ number_format($summary['gross_revenue'], 2) }}</th>
                                    <th class="text-end text-danger">{{ number_format($summary['total_refunds'], 2) }}</th>
                                    <th class="text-end text-success">{{ number_format($summary['net_revenue'], 2) }}</th>
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
                <span class="badge bg-soft-secondary text-secondary">{{ $orders->total() }} total orders</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'date', 'label' => 'Date', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'order_number', 'label' => 'Order #', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'channel', 'label' => 'Channel', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'buyer', 'label' => 'Buyer', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'total', 'label' => 'Gross', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'refunded', 'label' => 'Refunded', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'net', 'label' => 'Net', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'payment', 'label' => 'Payment', 'class' => 'text-center', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'status', 'label' => 'Status', 'class' => 'text-center', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><span class="fs-12 text-muted">{{ $order->order_date ? $order->order_date->format('M d, Y') : '-' }}</span></td>
                                    <td class="fw-semibold">
                                        <a href="{{ route('orders.show', $order->id) }}">{{ $order->order_number }}</a>
                                        @if ($order->ebay_order_id)
                                            <br><small class="text-muted fw-normal">eBay: {{ $order->ebay_order_id }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $order->salesChannel->name ?? 'Direct' }}</td>
                                    <td>{{ $order->buyer_name ?? $order->buyer_username ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($order->total, 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($order->total_refunded ?? 0, 2) }}</td>
                                    <td class="text-end fw-semibold text-success">{{ number_format($order->total - ($order->total_refunded ?? 0), 2) }}</td>
                                    <td class="text-center">
                                        @if ($order->isPartiallyRefunded() && !$order->isRefunded())
                                            <span class="badge bg-soft-info text-info">Partially Refunded</span>
                                        @elseif ($order->payment_status === 'paid')
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
                                        <div class="hstack gap-2 justify-content-center">
                                            <a href="{{ route('orders.show', $order->id) }}" class="avatar-text avatar-md" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        No orders found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($orders->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} orders
                        </div>
                        <div>
                            {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
