@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Out of Stock Orders</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">Out of Stock Orders</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Orders Placed While Out of Stock</div>
                    <h4 class="mb-0">{{ $stats['total_orders'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Out of Stock Order Items</div>
                    <h4 class="mb-0">{{ $stats['total_items'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('orders.out-of-stock') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-12 text-muted">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, SKU, title"
                        value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-12 text-muted">Sales Channel</label>
                    <select name="sales_channel_id" class="form-select form-select-sm">
                        <option value="">All Channels</option>
                        @foreach($salesChannels as $channel)
                            <option value="{{ $channel->id }}" @selected(request('sales_channel_id') == $channel->id)>{{ $channel->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">Shipment #</label>
                    <select name="shipment_id" class="form-select form-select-sm">
                        <option value="">All Shipments</option>
                        @foreach($shipments as $shipment)
                            <option value="{{ $shipment->id }}" @selected(request('shipment_id') == $shipment->id)>{{ $shipment->purchase_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="feather-search me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('orders.out-of-stock') }}" class="btn btn-light-brand btn-sm">
                        <i class="feather-x me-2"></i>
                        Clear
                    </a>
                    <a href="{{ route('orders.out-of-stock.export', request()->query()) }}" class="btn btn-success btn-sm">
                        <i class="feather-download me-2"></i>
                        Export
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders list -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Order #</th>
                            <th>Channel</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th class="text-end">Out of Stock Items</th>
                            <th>Last Shipment #</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php $order = $item->order; @endphp
                            <tr>
                                <td>
                                    <div>{{ $item->title }}</div>
                                    <span class="fs-12 fw-semibold text-danger">{{ $item->sku }}</span>
                                </td>
                                <td>
                                    @if($order)
                                        <a href="{{ route('orders.show', $order->id) }}">{{ $order->order_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $order->salesChannel->name ?? '—' }}</td>
                                <td>{{ optional($order?->order_date)->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $order ? ucfirst($order->order_status) : '—' }}</td>
                                <td class="text-end">{{ (float) $item->quantity }}</td>
                                <td>
                                    @if($item->last_purchase_id)
                                        <a href="{{ route('purchases.show', $item->last_purchase_id) }}">{{ $item->last_purchase_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No orders were placed while out of stock.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
