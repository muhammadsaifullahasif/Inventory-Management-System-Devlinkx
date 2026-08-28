@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Shipping Expenses Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Shipping Expenses Report</li>
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
    @php
        $tabQuery = request()->except(['source', 'group_by', 'carrier_id', 'page', 'grouped_page', 'sort', 'direction', 'item_sort', 'item_direction']);
    @endphp

    <!-- Overview: both sources combined, regardless of active tab -->
    <div class="col-12 mb-3">
        <div class="row">
            <div class="col-md-4">
                <div class="card bg-soft-secondary">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1 small">Combined Shipping Expenses @include('partials.info-tooltip', ['text' => 'eBay total_cost + System total_cost, filtered by date/channel only (ignores which tab is active and any carrier filter).'])</h6>
                                <h4 class="mb-0 fw-bold">{{ number_format($overview['combined_total_cost'], 2) }}</h4>
                                <small class="text-muted">{{ $overview['combined_label_count'] }} labels total</small>
                            </div>
                            <div class="avatar-text avatar-md bg-secondary text-white rounded">
                                <i class="feather-package"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1 small">eBay-Generated Labels @include('partials.info-tooltip', ['text' => 'Sum of orders.ebay_shipping_label_cost (rolled up from the eBay Finance API sync) for orders with that field > 0 in range. Cost % of revenue = this total / sum of orders.total for those orders x 100.'])</h6>
                                <h4 class="mb-0 fw-bold">{{ number_format($overview['ebay']['total_cost'], 2) }}</h4>
                                <small class="text-muted">{{ $overview['ebay']['label_count'] }} labels &middot; avg {{ number_format($overview['ebay']['avg_cost'], 2) }} &middot; {{ number_format($overview['ebay']['cost_pct_of_revenue'], 1) }}% of revenue</small>
                            </div>
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded">
                                <i class="feather-tag"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1 small">System-Generated Labels @include('partials.info-tooltip', ['text' => 'Sum of orders.shipping_cost for orders with label_generated_at and shipping_id both set (our own FedEx/USPS integration). shipping_cost is overwritten with the actual carrier charge at label generation - only the latest value is available if a label was regenerated.'])</h6>
                                <h4 class="mb-0 fw-bold">{{ number_format($overview['system']['total_cost'], 2) }}</h4>
                                <small class="text-muted">{{ $overview['system']['label_count'] }} labels &middot; avg {{ number_format($overview['system']['avg_cost'], 2) }} &middot; {{ number_format($overview['system']['cost_pct_of_revenue'], 1) }}% of revenue</small>
                            </div>
                            <div class="avatar-text avatar-md bg-soft-warning text-warning rounded">
                                <i class="feather-truck"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Source Tabs -->
    <div class="col-12 mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link {{ $source === 'ebay' ? 'active' : '' }}"
                   href="{{ route('reports.shipping-expenses', array_merge($tabQuery, ['source' => 'ebay'])) }}">
                    <i class="feather-tag me-1"></i> eBay-Generated Labels
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $source === 'system' ? 'active' : '' }}"
                   href="{{ route('reports.shipping-expenses', array_merge($tabQuery, ['source' => 'system'])) }}">
                    <i class="feather-truck me-1"></i> System-Generated Labels
                </a>
            </li>
        </ul>
    </div>

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
                    <form action="{{ route('reports.shipping-expenses') }}" method="GET">
                        <input type="hidden" name="source" value="{{ $source }}">
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
                            @if ($source === 'system')
                                <div class="col-md-2">
                                    <label class="form-label">Carrier</label>
                                    <select name="carrier_id" class="form-select form-select-sm">
                                        <option value="">All Carriers</option>
                                        @foreach ($carriers as $carrier)
                                            <option value="{{ $carrier->id }}" {{ $carrierId == $carrier->id ? 'selected' : '' }}>
                                                {{ $carrier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-md-2">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Sales Channel</option>
                                    @if ($source === 'system')
                                        <option value="carrier" {{ $groupBy == 'carrier' ? 'selected' : '' }}>Carrier</option>
                                    @endif
                                    <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.shipping-expenses', ['source' => $source]) }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                                @if($orders->isNotEmpty())
                                    <a href="{{ route('reports.shipping-expenses.export', request()->query()) }}" class="btn btn-success btn-sm">
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

    @if ($source === 'system')
        <div class="col-12 mb-3">
            <div class="alert alert-warning py-2 px-3 mb-0 small">
                <i class="feather-alert-triangle me-1"></i>
                Label cost reflects the order's current <code>shipping_cost</code> value, which is overwritten with the carrier's
                actual charge when a label is generated. If a label was regenerated or the order re-shipped, only the latest cost is available.
            </div>
        </div>
    @endif

    <!-- Grouped Report Data -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-layers me-2"></i>
                    Shipping Cost by {{ ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'name', 'label' => $groupBy === 'carrier' ? 'Carrier' : ($groupBy === 'date' ? 'Date' : 'Sales Channel')])
                                @include('partials.sortable-th', ['column' => 'label_count', 'label' => 'Labels', 'class' => 'text-center'])
                                @include('partials.sortable-th', ['column' => 'total_cost', 'label' => 'Total Cost', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'avg_cost', 'label' => 'Avg Cost', 'class' => 'text-end'])
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['label_count'] }}</span>
                                    </td>
                                    <td class="text-end text-danger fw-semibold">{{ number_format($group['total_cost'], 2) }}</td>
                                    <td class="text-end">{{ number_format($group['avg_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="feather-truck" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No shipping labels found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($reportData->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <th>Totals</th>
                                    <th class="text-center">{{ $summary['label_count'] }}</th>
                                    <th class="text-end text-danger">{{ number_format($summary['total_cost'], 2) }}</th>
                                    <th class="text-end">{{ number_format($summary['avg_cost'], 2) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Label List -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Label Details</h5>
                <span class="badge bg-soft-secondary text-secondary">{{ $orders->total() }} total labels</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'date', 'label' => 'Order Date', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'order_number', 'label' => 'Order #', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'channel', 'label' => 'Channel', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @if ($source === 'system')
                                    @include('partials.sortable-th', ['column' => 'carrier', 'label' => 'Carrier', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                    @include('partials.sortable-th', ['column' => 'label_date', 'label' => 'Label Generated', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @endif
                                @include('partials.sortable-th', ['column' => 'tracking', 'label' => 'Tracking #', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'label_cost', 'label' => 'Label Cost', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'total', 'label' => 'Order Total', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
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
                                    @if ($source === 'system')
                                        <td>{{ $order->shippingCarrier->name ?? '-' }}</td>
                                        <td><span class="fs-12 text-muted">{{ $order->label_generated_at ? $order->label_generated_at->format('M d, Y H:i') : '-' }}</span></td>
                                    @endif
                                    <td>{{ $order->tracking_number ?? '-' }}</td>
                                    <td class="text-end text-danger fw-semibold">{{ number_format($order->{$costField} ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($order->total, 2) }}</td>
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
                                        No shipping labels found.
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
                            Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} labels
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
