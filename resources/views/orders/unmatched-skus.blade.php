@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Unmatched SKUs</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">Unmatched SKUs</li>
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
                    <div class="text-muted fs-12">Unmatched Order Items</div>
                    <h4 class="mb-0">{{ $stats['total_unmatched'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Distinct Unmatched SKUs</div>
                    <h4 class="mb-0">{{ $stats['distinct_skus'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('orders.unmatched-skus') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-12 text-muted">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="SKU, title, order #"
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
                    <label class="form-label fs-12 text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="feather-search me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('orders.unmatched-skus') }}" class="btn btn-light-brand btn-sm">
                        <i class="feather-x me-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Items list -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Title</th>
                            <th>Order #</th>
                            <th>Channel</th>
                            <th>Order Date</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><span class="fw-semibold text-danger">{{ $item->sku }}</span></td>
                                <td>{{ $item->title }}</td>
                                <td>
                                    @if($item->order)
                                        <a href="{{ route('orders.show', $item->order_id) }}">{{ $item->order->order_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $item->order->salesChannel->name ?? '—' }}</td>
                                <td>{{ optional($item->order->order_date)->format('M d, Y') ?? '—' }}</td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->total_price, 2) }}</td>
                                <td class="text-end">
                                    @if($item->order)
                                        <a href="{{ route('orders.show', $item->order_id) }}" class="btn btn-xs btn-light-brand">
                                            <i class="feather-eye me-1"></i>View Order
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No unmatched SKUs found — every order item is linked to a system product.</td>
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
