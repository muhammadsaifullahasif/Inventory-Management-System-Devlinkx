@extends('layouts.app')

@section('header')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">COGS Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">COGS Report</li>
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
                    <form action="{{ route('reports.cogs') }}" method="GET">
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
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control form-control-sm" value="{{ $sku ?? '' }}" placeholder="Search by SKU">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Sales Channel</option>
                                    <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                                    <option value="order" {{ $groupBy == 'order' ? 'selected' : '' }}>Order</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.cogs') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                                @if($reportData->isNotEmpty())
                                    <a href="{{ route('reports.cogs.export', request()->query()) }}" class="btn btn-success btn-sm">
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
        <div class="col-lg-12">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-soft-primary">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Total Items Sold</h6>
                                    <h4 class="mb-0 fw-bold">{{ number_format($summary['total_items_sold'], 0) }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-primary text-white rounded">
                                    <i class="feather-package"></i>
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
                                    <h6 class="text-muted mb-1 small">Total Revenue</h6>
                                    <h4 class="mb-0 fw-bold">{{ number_format($summary['total_revenue'], 2) }}</h4>
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
                                    <h6 class="text-muted mb-1 small">Total COGS</h6>
                                    <h4 class="mb-0 fw-bold">{{ number_format($summary['total_cogs'], 2) }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-danger text-white rounded">
                                    <i class="feather-trending-down"></i>
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
                                    <h6 class="text-muted mb-1 small">Gross Profit</h6>
                                    <h4 class="mb-0 fw-bold {{ $summary['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($summary['gross_profit'], 2) }}
                                    </h4>
                                    <small class="text-muted">
                                        {{ number_format($summary['gross_margin'], 1) }}% margin
                                        @if(($summary['refunded_orders_count'] ?? 0) > 0)
                                            &middot; {{ $summary['refunded_orders_count'] }} refunded order(s) excluded
                                        @endif
                                    </small>
                                </div>
                                <div class="avatar-text avatar-md bg-info text-white rounded">
                                    <i class="feather-trending-up"></i>
                                </div>
                            </div>
                        </div>
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
                    COGS by {{ ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @php $gp = ['pageParam' => 'grouped_page']; @endphp
                                @if ($groupBy === 'product')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Product'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'sku', 'label' => 'SKU'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'quantity_sold', 'label' => 'Qty Sold', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'avg_cost', 'label' => 'Avg Cost', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_cogs', 'label' => 'Total COGS', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_profit', 'label' => 'Gross Profit', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_margin', 'label' => 'Margin %', 'class' => 'text-end'] + $gp)
                                @elseif ($groupBy === 'channel')
                                    @include('partials.sortable-th', ['column' => 'name', 'label' => 'Sales Channel'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'items_sold', 'label' => 'Items Sold', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_cogs', 'label' => 'Total COGS', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_profit', 'label' => 'Gross Profit', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_margin', 'label' => 'Margin %', 'class' => 'text-end'] + $gp)
                                @elseif ($groupBy === 'date')
                                    @include('partials.sortable-th', ['column' => 'date', 'label' => 'Date'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'items_sold', 'label' => 'Items Sold', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_cogs', 'label' => 'Total COGS', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_profit', 'label' => 'Gross Profit', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_margin', 'label' => 'Margin %', 'class' => 'text-end'] + $gp)
                                @else
                                    @include('partials.sortable-th', ['column' => 'order_number', 'label' => 'Order #'] + $gp)
                                    <th>eBay Order ID</th>
                                    @include('partials.sortable-th', ['column' => 'order_date', 'label' => 'Date'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'channel', 'label' => 'Channel'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'items_count', 'label' => 'Items', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_cogs', 'label' => 'Total COGS', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'total_revenue', 'label' => 'Revenue', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_profit', 'label' => 'Gross Profit', 'class' => 'text-end'] + $gp)
                                    @include('partials.sortable-th', ['column' => 'gross_margin', 'label' => 'Margin %', 'class' => 'text-end'] + $gp)
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $item)
                                <tr>
                                    @if ($groupBy === 'product')
                                        <td class="fw-semibold">
                                            @if(!empty($item['product_id']))
                                                <a href="{{ route('products.show', $item['product_id']) }}"><span style="white-space: normal; width: 300px; display: block;">{{ $item['name'] }}</span></a>
                                            @else
                                                {{ $item['name'] }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($item['product_id']))
                                                <a href="{{ route('products.show', $item['product_id']) }}"><code>{{ $item['sku'] ?? '-' }}</code></a>
                                            @else
                                                <code>{{ $item['sku'] ?? '-' }}</code>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($item['quantity_sold'], 0) }}</td>
                                        <td class="text-end">{{ number_format($item['avg_cost'], 2) }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ number_format($item['total_cogs'], 2) }}</td>
                                        <td class="text-end text-success">{{ number_format($item['total_revenue'], 2) }}</td>
                                        <td class="text-end fw-semibold {{ $item['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($item['gross_profit'], 2) }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge {{ $item['gross_margin'] >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                                {{ number_format($item['gross_margin'], 1) }}%
                                            </span>
                                        </td>
                                    @elseif ($groupBy === 'channel')
                                        <td class="fw-semibold">{{ $item['name'] }}</td>
                                        <td class="text-end">{{ number_format($item['items_sold'], 0) }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ number_format($item['total_cogs'], 2) }}</td>
                                        <td class="text-end text-success">{{ number_format($item['total_revenue'], 2) }}</td>
                                        <td class="text-end fw-semibold {{ $item['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($item['gross_profit'], 2) }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge {{ $item['gross_margin'] >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                                {{ number_format($item['gross_margin'], 1) }}%
                                            </span>
                                        </td>
                                    @elseif ($groupBy === 'date')
                                        <td class="fw-semibold">{{ $item['formatted_date'] }}</td>
                                        <td class="text-end">{{ number_format($item['items_sold'], 0) }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ number_format($item['total_cogs'], 2) }}</td>
                                        <td class="text-end text-success">{{ number_format($item['total_revenue'], 2) }}</td>
                                        <td class="text-end fw-semibold {{ $item['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($item['gross_profit'], 2) }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge {{ $item['gross_margin'] >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                                {{ number_format($item['gross_margin'], 1) }}%
                                            </span>
                                        </td>
                                    @else
                                        <td class="fw-semibold">
                                            @if(!empty($item['order_id']))
                                                <a href="{{ route('orders.show', $item['order_id']) }}">{{ $item['order_number'] }}</a>
                                            @else
                                                {{ $item['order_number'] }}
                                            @endif
                                            @if($item['is_refunded'] ?? false)
                                                <span class="badge bg-soft-warning text-warning ms-1">Refunded</span>
                                            @endif
                                        </td>
                                        <td>{{ $item['ebay_order_id'] ?? '-' }}</td>
                                        <td>{{ $item['formatted_date'] }}</td>
                                        <td>{{ $item['channel'] }}</td>
                                        <td class="text-end">{{ number_format($item['items_count'], 0) }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ number_format($item['total_cogs'], 2) }}</td>
                                        <td class="text-end text-success">{{ number_format($item['total_revenue'], 2) }}</td>
                                        <td class="text-end fw-semibold {{ $item['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($item['gross_profit'], 2) }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge {{ $item['gross_margin'] >= 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                                {{ number_format($item['gross_margin'], 1) }}%
                                            </span>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $groupBy === 'product' ? 8 : ($groupBy === 'order' ? 9 : 6) }}" class="text-center py-5 text-muted">
                                        <i class="feather-inbox" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No COGS data found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($reportData->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $reportData->firstItem() }} to {{ $reportData->lastItem() }} of {{ $reportData->total() }} items
                        </div>
                        <div>
                            {{ $reportData->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Detailed Item List -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Detailed Item List</h5>
                <span class="badge bg-soft-secondary text-secondary">{{ $paginatedItems->total() }} items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @php $ip = ['sortParam' => 'item_sort', 'dirParam' => 'item_direction', 'pageParam' => 'items_page']; @endphp
                                @include('partials.sortable-th', ['column' => 'date', 'label' => 'Date'] + $ip)
                                @include('partials.sortable-th', ['column' => 'order_number', 'label' => 'Order #'] + $ip)
                                <th>eBay Order ID</th>
                                @include('partials.sortable-th', ['column' => 'channel', 'label' => 'Channel'] + $ip)
                                @include('partials.sortable-th', ['column' => 'product', 'label' => 'Product'] + $ip)
                                @include('partials.sortable-th', ['column' => 'sku', 'label' => 'SKU'] + $ip)
                                @include('partials.sortable-th', ['column' => 'qty', 'label' => 'Qty', 'class' => 'text-center'] + $ip)
                                @include('partials.sortable-th', ['column' => 'cost_unit', 'label' => 'Cost/Unit', 'class' => 'text-end'] + $ip)
                                @include('partials.sortable-th', ['column' => 'total_cogs', 'label' => 'Total COGS', 'class' => 'text-end'] + $ip)
                                @include('partials.sortable-th', ['column' => 'revenue', 'label' => 'Revenue', 'class' => 'text-end'] + $ip)
                                @include('partials.sortable-th', ['column' => 'profit', 'label' => 'Profit', 'class' => 'text-end'] + $ip)
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginatedItems as $item)
                                @php
                                    $isCancelled = $item->order_status === 'cancelled';
                                    $isRefunded = in_array($item->order_status, ['cancelled', 'refunded']) || $item->payment_status === 'refunded';
                                    $itemCogs = $isCancelled ? 0 : ($item->cost_at_sale ?? 0) * $item->quantity;
                                    $itemRevenue = $isRefunded ? 0 : (float) $item->total_price;
                                @endphp
                                <tr class="{{ $isRefunded ? 'text-muted' : '' }}">
                                    <td><span class="fs-12 text-muted">{{ $item->order->order_date ? $item->order->order_date->format('M d, Y') : '-' }}</span></td>
                                    <td class="fw-semibold">
                                        <a href="{{ route('orders.show', $item->order->id) }}">{{ $item->order->order_number }}</a>
                                        @if($isRefunded)
                                            <span class="badge bg-soft-warning text-warning ms-1">Refunded</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->order->ebay_order_id ?? '-' }}</td>
                                    <td><span class="fs-12">{{ $item->order->salesChannel->name ?? '-' }}</span></td>
                                    <td><span style="white-space: normal; width: 300px; display: block;">{{ $item->product->name ?? $item->title }}</span></td>
                                    <td>
                                        @if($item->product_id)
                                            <a href="{{ route('products.show', $item->product_id) }}"><code>{{ $item->sku }}</code></a>
                                        @else
                                            <code>{{ $item->sku ?? '-' }}</code>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ number_format($item->cost_at_sale ?? 0, 2) }}</td>
                                    <td class="text-end {{ $isRefunded ? '' : 'text-danger' }}">{{ number_format($itemCogs, 2) }}</td>
                                    <td class="text-end {{ $isRefunded ? '' : 'text-success' }}">{{ number_format($itemRevenue, 2) }}</td>
                                    <td class="text-end fw-semibold {{ $isRefunded ? '' : (($itemRevenue - $itemCogs) >= 0 ? 'text-success' : 'text-danger') }}">
                                        {{ number_format($itemRevenue - $itemCogs, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($paginatedItems->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $paginatedItems->firstItem() }} to {{ $paginatedItems->lastItem() }} of {{ $paginatedItems->total() }} items
                        </div>
                        <div>
                            {{ $paginatedItems->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
