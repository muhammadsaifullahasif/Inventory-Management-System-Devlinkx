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
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
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
                                @if($orders->isNotEmpty())
                                    <a href="{{ route('reports.sales.export', request()->query()) }}" class="btn btn-success btn-sm">
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
        <!-- Sales Summary -->
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-soft-primary">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Total Orders @include('partials.info-tooltip', ['text' => 'Count of all orders with order_date in the selected range, matching channel/status filters - any payment or order status.'])</h6>
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
                                    <h6 class="text-muted mb-1 small">Paid Orders @include('partials.info-tooltip', ['text' => 'Count of orders in range with payment_status = paid.'])</h6>
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
                                    <h6 class="text-muted mb-1 small">Shipped @include('partials.info-tooltip', ['text' => 'Count of orders in range with order_status = shipped.'])</h6>
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
                                    <h6 class="text-muted mb-1 small">Cancelled @include('partials.info-tooltip', ['text' => 'Count of orders in range with order_status = cancelled.'])</h6>
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
                            <h6 class="text-muted mb-1 small">Total Revenue @include('partials.info-tooltip', ['text' => 'Sum of orders.total for paid orders only in range.'])</h6>
                            <h4 class="mb-0 fw-bold text-success">{{ number_format($summary['total_revenue'], 2) }}</h4>
                            <small class="text-muted" data-bs-toggle="tooltip" title="Sale Lines: 1 per sold unit, a bundle counts once (its summary line). Total Items Sold: physical piece count, a bundle's components count individually instead.">
                                {{ $summary['sale_lines'] }} sale lines &middot; {{ $summary['total_items_sold'] }} items sold
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Avg Order Value @include('partials.info-tooltip', ['text' => 'Total Revenue / Paid Orders count.'])</h6>
                            <h4 class="mb-0 fw-bold text-primary">{{ number_format($summary['average_order_value'], 2) }}</h4>
                            <small class="text-muted">per order</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Shipping Collected @include('partials.info-tooltip', ['text' => 'Sum of orders.shipping_cost for paid orders (buyer-charged shipping fee at order time - note this field gets overwritten with the actual carrier label cost once a label is generated, see Shipping Expenses Report).'])</h6>
                            <h4 class="mb-0 fw-bold">{{ number_format($summary['total_shipping'], 2) }}</h4>
                            <small class="text-muted">from paid orders</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Tax Collected @include('partials.info-tooltip', ['text' => 'Sum of orders.tax for paid orders in range.'])</h6>
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
                            <td class="text-muted">Sales Revenue: @include('partials.info-tooltip', ['text' => 'Same as Total Revenue above - sum of orders.total for paid orders in range.'])</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($summary['total_revenue'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Expenses (Bills): @include('partials.info-tooltip', ['text' => 'Sum of ALL bills.total_amount with status unpaid/partially_paid/paid and bill_date in range - every posted bill regardless of chart-of-account nature, including Purchase Order/inventory-asset bills. Not restricted to true operating-expense accounts (unlike Net Profit Report).'])</td>
                            <td class="text-end fw-semibold text-danger">{{ number_format($accountingSummary['total_bills'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Payments Made: @include('partials.info-tooltip', ['text' => 'Sum of posted Payment records (status=posted) with payment_date in range - money paid out to suppliers.'])</td>
                            <td class="text-end fw-semibold">{{ number_format($accountingSummary['total_payments_out'], 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Net Income: @include('partials.info-tooltip', ['text' => 'Sales Revenue minus Expenses (Bills). Does not subtract COGS, eBay fees, or shipping costs separately - see Net Profit Report for the full P&L.'])</td>
                            <td class="text-end fw-bold {{ $accountingSummary['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($accountingSummary['net_income'], 2) }}
                            </td>
                        </tr>
                    </table>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Gross Margin: @include('partials.info-tooltip', ['text' => 'Net Income / Sales Revenue x 100.'])</span>
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
                                @php $isItemGrouping = in_array($groupBy, ['product', 'category']); @endphp
                                @if ($groupBy === 'date')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Date'])
                                @elseif ($groupBy === 'product')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Product'])
                                    @include('partials.sortable-th', ['column' => 'sku', 'label' => 'SKU'])
                                    @include('partials.sortable-th', ['column' => 'avg_price', 'label' => 'Avg Price', 'class' => 'text-end'])
                                @elseif ($groupBy === 'category')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Category'])
                                @else
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Sales Channel'])
                                @endif
                                @include('partials.sortable-th', ['column' => 'order_count', 'label' => 'Orders', 'class' => 'text-center'])
                                @if (!$isItemGrouping)
                                    @include('partials.sortable-th', ['column' => 'paid_count', 'label' => 'Paid', 'class' => 'text-center'])
                                @endif
                                @include('partials.sortable-th', ['column' => $isItemGrouping ? 'quantity_sold' : 'items_sold', 'label' => $isItemGrouping ? 'Qty Sold' : 'Items Sold', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'])
                                @if ($groupBy === 'channel')
                                    @include('partials.sortable-th', ['column' => 'total_shipping', 'label' => 'Shipping', 'class' => 'text-end'])
                                    @include('partials.sortable-th', ['column' => 'total_tax', 'label' => 'Tax', 'class' => 'text-end'])
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
                                    @if (!$isItemGrouping)
                                        <td class="text-center">
                                            <span class="badge bg-soft-success text-success">{{ $group['paid_count'] }}</span>
                                        </td>
                                    @endif
                                    <td class="text-end">{{ number_format($isItemGrouping ? $group['quantity_sold'] : $group['items_sold'], 0) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($group['total_revenue'], 2) }}</td>
                                    @if ($groupBy === 'channel')
                                        <td class="text-end">{{ number_format($group['total_shipping'], 2) }}</td>
                                        <td class="text-end">{{ number_format($group['total_tax'], 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $groupBy === 'product' ? 6 : ($groupBy === 'channel' ? 8 : ($groupBy === 'category' ? 4 : 5)) }}" class="text-center py-5 text-muted">
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
                                    @if (!$isItemGrouping)
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
                                @include('partials.sortable-th', ['column' => 'items', 'label' => 'Items', 'class' => 'text-center', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'total', 'label' => 'Total', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
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
                                    <td class="text-center">
                                        <span class="badge bg-soft-secondary text-secondary">{{ $order->items->count() }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($order->total, 2) }}</td>
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
                                    <td colspan="9" class="text-center py-4 text-muted">
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
