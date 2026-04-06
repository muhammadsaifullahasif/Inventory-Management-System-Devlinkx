@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Orders</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Orders</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <span class="text-muted fs-12">{{ $orders->total() }} orders</span>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('orders.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, Email, Name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sales Channel</label>
                                <select name="sales_channel_id" class="form-select form-select-sm">
                                    <option value="">All Channels</option>
                                    @foreach($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="order_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="awaiting_payment" {{ request('order_status') == 'awaiting_payment' ? 'selected' : '' }}>Awaiting Payment</option>
                                    <option value="processing" {{ request('order_status') == 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="shipped" {{ request('order_status') == 'shipped' ? 'selected' : '' }}>Shipped / Fulfilled</option>
                                    <option value="delivered" {{ request('order_status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="ready_for_pickup" {{ request('order_status') == 'ready_for_pickup' ? 'selected' : '' }}>Ready for Pickup</option>
                                    <option value="cancellation_requested" {{ request('order_status') == 'cancellation_requested' ? 'selected' : '' }}>Cancellation Requested</option>
                                    <option value="cancelled" {{ request('order_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    <option value="refunded" {{ request('order_status') == 'refunded' ? 'selected' : '' }}>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ship By Deadline</label>
                                <select name="shipment_deadline" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="overdue" {{ request('shipment_deadline') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                                    <option value="today" {{ request('shipment_deadline') == 'today' ? 'selected' : '' }}>Due Today</option>
                                    <option value="tomorrow" {{ request('shipment_deadline') == 'tomorrow' ? 'selected' : '' }}>Due Tomorrow</option>
                                    <option value="this_week" {{ request('shipment_deadline') == 'this_week' ? 'selected' : '' }}>Due This Week</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('orders.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete orders')
                    @include('partials.bulk-actions-bar', ['itemName' => 'orders'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $orderColumns = [
                            ['key' => 'id', 'label' => 'ID', 'default' => true],
                            ['key' => 'order_number', 'label' => 'Order #', 'default' => true],
                            ['key' => 'channel', 'label' => 'Channel', 'default' => true],
                            ['key' => 'customer', 'label' => 'Customer', 'default' => true],
                            ['key' => 'items', 'label' => 'Items', 'default' => true],
                            ['key' => 'total', 'label' => 'Total', 'default' => true],
                            ['key' => 'status', 'label' => 'Status', 'default' => true],
                            ['key' => 'address_type', 'label' => 'Address Type', 'default' => false],
                            ['key' => 'order_date', 'label' => 'Order Date', 'default' => true],
                            ['key' => 'ship_by', 'label' => 'Ship By', 'default' => true],
                            ['key' => 'shipped_date', 'label' => 'Shipped Date', 'default' => false],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'ordersTable', 'cookieName' => 'orders_columns', 'columns' => $orderColumns])
                    <button type="button" class="avatar-text avatar-md text-secondary" id="toggleAllSubtablesBtn" data-bs-toggle="tooltip" title="Expand/Collapse all order items">
                        <i class="feather-list" id="toggleAllIcon"></i>
                    </button>
                    <button type="button" class="avatar-text avatar-md text-primary" id="syncEbayStatusBtn" data-bs-toggle="tooltip" title="Sync eBay order statuses (cancel/refund/return)">
                        <i class="feather-refresh-cw"></i>
                    </button>
                    <button type="button" class="avatar-text avatar-md text-warning" id="closeFedExBtn" data-bs-toggle="tooltip" title="FedEx End of Day - Close shipments and generate manifest">
                        <i class="feather-package"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="ordersTable">
                        <thead>
                            <tr>
                                @can('delete orders')
                                    <th class="ps-3" style="width: 40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectAll" title="Select all on this page">
                                                <label for="selectAll" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                    </th>
                                @endcan
                                <th style="width: 40px;"></th>
                                <th data-column="id">#</th>
                                <th data-column="order_number">Order #</th>
                                <th data-column="channel">Channel</th>
                                <th data-column="customer">Customer</th>
                                <th data-column="items">Items</th>
                                <th data-column="total">Total</th>
                                <th data-column="status">Status</th>
                                <th data-column="address_type">Address Type</th>
                                <th data-column="order_date">Order Date</th>
                                <th data-column="ship_by">Ship By</th>
                                <th data-column="shipped_date">Shipped Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    @can('delete orders')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $order->id }}" data-order-id="{{ $order->id }}">
                                                    <label for="{{ $order->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="order-id-input" value="{{ $order->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td class="text-center">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm expand-items-btn" data-order-id="{{ $order->id }}" data-bs-toggle="tooltip" title="View Items">
                                            <i class="feather-chevron-right expand-icon"></i>
                                        </a>
                                    </td>
                                    <td data-column="id">{{ $order->id }}</td>
                                    <td data-column="order_number">
                                        <a href="{{ route('orders.show', $order->id) }}" class="fw-semibold text-primary">
                                            {{ $order->order_number }}
                                        </a>
                                        @if($order->ebay_order_id)
                                            <span class="d-block fs-11 text-muted">eBay: {{ \Illuminate\Support\Str::limit($order->ebay_order_id, 20) }}</span>
                                        @endif
                                    </td>
                                    <td data-column="channel">
                                        @if($order->salesChannel)
                                            <span class="badge bg-soft-info text-info">{{ $order->salesChannel->name }}</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">N/A</span>
                                        @endif
                                    </td>
                                    <td data-column="customer">
                                        <span class="fw-semibold">{{ $order->buyer_name ?? 'N/A' }}</span>
                                        @if($order->buyer_email)
                                            <span class="d-block fs-11 text-muted">{{ $order->buyer_email }}</span>
                                        @endif
                                    </td>
                                    @php
                                        // Count only main items (bundles + regular products), exclude bundle components
                                        $mainItems = $order->items->filter(fn($item) => !$item->bundle_product_id || $item->is_bundle_summary);
                                        $itemCount = $mainItems->count();
                                        $totalQty = $mainItems->sum('quantity');
                                    @endphp
                                    <td data-column="items">
                                        <span>{{ $itemCount }} item(s)</span>
                                        <span class="d-block fs-11 text-muted">Qty: {{ $totalQty }}</span>
                                    </td>
                                    <td data-column="total">
                                        <span class="fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</span>
                                    </td>
                                    <td data-column="status">
                                        @php
                                            $isPaid       = in_array($order->payment_status, ['paid']);
                                            $isShipped    = in_array($order->fulfillment_status, ['fulfilled', 'partially_fulfilled'])
                                                            || in_array($order->order_status, ['shipped', 'delivered', 'ready_for_pickup']);
                                            $isCancelled  = in_array($order->order_status, ['cancelled', 'cancellation_requested']);
                                            $isRefunded   = $order->order_status === 'refunded' || $order->payment_status === 'refunded';
                                            $isPartiallyRefunded = $order->isPartiallyRefunded();

                                            if ($isRefunded) {
                                                $statusLabel = 'Refunded';
                                                $statusColor = 'secondary';
                                            } elseif ($isPartiallyRefunded) {
                                                $statusLabel = 'Partial Refund';
                                                $statusColor = 'info';
                                            } elseif ($isCancelled) {
                                                $statusLabel = ucfirst(str_replace('_', ' ', $order->order_status));
                                                $statusColor = 'danger';
                                            } elseif (!$isPaid) {
                                                $statusLabel = 'Awaiting Payment';
                                                $statusColor = 'warning';
                                            } elseif ($isPaid && !$isShipped) {
                                                $statusLabel = 'Processing';
                                                $statusColor = 'info';
                                            } else {
                                                $statusLabel = 'Shipped / Fulfilled';
                                                $statusColor = 'success';
                                            }
                                        @endphp
                                        <span class="badge bg-soft-{{ $statusColor }} text-{{ $statusColor }}">{{ $statusLabel }}</span>
                                        @if($isPartiallyRefunded)
                                            <span class="d-block fs-11 text-muted">{{ $order->currency ?? 'USD' }} {{ number_format($order->total_refunded, 2) }} refunded</span>
                                        @endif
                                    </td>
                                    <td data-column="address_type">
                                        @php
                                            $addrColors = [
                                                'BUSINESS'    => 'primary',
                                                'RESIDENTIAL' => 'success',
                                                'MIXED'       => 'warning',
                                                'UNKNOWN'     => 'secondary',
                                            ];
                                            $addrType  = $order->address_type ?? 'UNKNOWN';
                                            $addrColor = $addrColors[$addrType] ?? 'secondary';
                                        @endphp
                                        @if ($order->address_validated_at)
                                            <span class="badge bg-soft-{{ $addrColor }} text-{{ $addrColor }}">{{ $addrType }}</span>
                                        @else
                                            <span class="text-muted fs-12">-</span>
                                        @endif
                                    </td>
                                    <td data-column="order_date">
                                        @if($order->order_date)
                                            <span class="fs-12">{{ \Carbon\Carbon::parse($order->order_date)->format('d M, Y') }}</span>
                                            <span class="d-block fs-11 text-muted">{{ \Carbon\Carbon::parse($order->order_date)->format('h:i A') }}</span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td data-column="ship_by">
                                        @php
                                            $deadlineStatus = $order->getShipmentDeadlineStatus();
                                            $deadlineColors = [
                                                'overdue' => 'danger',
                                                'urgent' => 'warning',
                                                'upcoming' => 'info',
                                                'ok' => 'success',
                                            ];
                                            $deadlineLabels = [
                                                'overdue' => 'OVERDUE',
                                                'urgent' => 'URGENT',
                                                'upcoming' => 'Soon',
                                                'ok' => '',
                                            ];
                                        @endphp
                                        @if($order->shipment_deadline && $deadlineStatus)
                                            <span class="fs-12 {{ $deadlineStatus === 'overdue' ? 'text-danger fw-bold' : ($deadlineStatus === 'urgent' ? 'text-warning fw-semibold' : '') }}">
                                                {{ $order->shipment_deadline->format('d M, Y') }}
                                            </span>
                                            @if($deadlineLabels[$deadlineStatus])
                                                <span class="d-block">
                                                    <span class="badge bg-soft-{{ $deadlineColors[$deadlineStatus] }} text-{{ $deadlineColors[$deadlineStatus] }} fs-10">
                                                        {{ $deadlineLabels[$deadlineStatus] }}
                                                    </span>
                                                </span>
                                            @else
                                                <span class="d-block fs-11 text-muted">{{ $order->shipment_deadline->diffForHumans() }}</span>
                                            @endif
                                        @elseif($order->shipment_deadline)
                                            <span class="fs-12 text-muted">{{ $order->shipment_deadline->format('d M, Y') }}</span>
                                        @else
                                            <span class="text-muted fs-12">-</span>
                                        @endif
                                    </td>
                                    <td data-column="shipped_date">
                                        @if($order->shipped_at)
                                            <span class="fs-12 text-success">{{ $order->shipped_at->format('d M, Y') }}</span>
                                            <span class="d-block fs-11 text-muted">{{ $order->shipped_at->format('H:i') }}</span>
                                        @else
                                            <span class="text-muted fs-12">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('orders.show', $order->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                            @if($order->fulfillment_status !== 'fulfilled' && $order->order_status !== 'cancelled')
                                                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary ship-btn"
                                                    data-id="{{ $order->id }}"
                                                    data-order-number="{{ $order->order_number }}"
                                                    data-customer="{{ $order->buyer_name ?? 'N/A' }}"
                                                    data-email="{{ $order->buyer_email ?? '' }}"
                                                    data-address="{{ implode(', ', array_filter([$order->shipping_address_line1, $order->shipping_city, $order->shipping_state, $order->shipping_postal_code, $order->shipping_country])) }}"
                                                    data-items="{{ $mainItems->count() }} item(s), Qty: {{ $mainItems->sum('quantity') }}"
                                                    data-total="{{ ($order->currency ?? 'USD') . ' ' . number_format($order->total, 2) }}"
                                                    data-bs-toggle="tooltip" title="Mark as Shipped">
                                                    <i class="feather-truck"></i>
                                                </a>
                                            @endif
                                            @if ($order->order_status === 'shipped' && $order->fulfillment_status === 'fulfilled' && !is_null($order->shipping_label_path))
                                                <a href="{{ route('orders.label', $order->id) }}"
                                                   class="avatar-text avatar-md text-primary shipping-label"
                                                   target="_blank"
                                                   data-bs-toggle="tooltip"
                                                   title="Download Shipping Label">
                                                    <i class="feather-download"></i>
                                                </a>
                                                <a href="javascript:void(0);"
                                                   class="avatar-text avatar-md text-warning cancel-label-btn"
                                                   data-id="{{ $order->id }}"
                                                   data-order-number="{{ $order->order_number }}"
                                                   data-tracking="{{ $order->tracking_number }}"
                                                   data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}"
                                                   data-bs-toggle="tooltip"
                                                   title="Cancel Label & Remove Tracking">
                                                    <i class="feather-x-circle"></i>
                                                </a>
                                            @endif
                                            {{-- Refund Button --}}
                                            @if($order->canBeRefunded() && !$order->isRefunded())
                                                <a href="javascript:void(0);"
                                                    class="avatar-text avatar-md text-success refund-btn"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-number="{{ $order->order_number }}"
                                                    data-order-total="{{ $order->total }}"
                                                    data-total-refunded="{{ $order->total_refunded ?? 0 }}"
                                                    data-refundable="{{ $order->getRefundableAmount() }}"
                                                    data-currency="{{ $order->currency ?? 'USD' }}"
                                                    data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}"
                                                    data-ebay-order-id="{{ $order->ebay_order_id }}"
                                                    data-sales-channel-id="{{ $order->sales_channel_id }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Issue Refund">
                                                    <i class="feather-dollar-sign"></i>
                                                </a>
                                            @endif
                                            @if($order->order_status !== 'cancelled' && $order->order_status !== 'refunded')
                                                <a href="javascript:void(0);" class="avatar-text avatar-md text-danger cancel-btn" data-id="{{ $order->id }}" data-bs-toggle="tooltip" title="Cancel Order">
                                                    <i class="feather-x"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <!-- Order Items Subtable Row (Hidden by default) -->
                                <tr class="order-items-row" id="order-items-{{ $order->id }}" style="display: none;">
                                    <td colspan="14" class="p-0 bg-light">
                                        <div class="p-3">
                                            <h6 class="mb-2 fw-bold"><i class="feather-package me-2"></i>Order Items</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0 bg-white">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 60px;">Image</th>
                                                            <th style="width: 300px;">Item Name</th>
                                                            <th style="width: 200px;">SKU</th>
                                                            <th style="width: 100px;" class="text-center">Qty</th>
                                                            <th style="width: 150px;" class="text-end">Unit Price</th>
                                                            <th style="width: 150px;" class="text-end">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($order->items as $item)
                                                            @if($item->is_bundle_summary)
                                                                <!-- Bundle Summary Row -->
                                                                <tr class="table-primary">
                                                                    <td class="text-center">
                                                                        @php
                                                                            $itemImageUrl = $item->product?->getImageUrl();
                                                                        @endphp
                                                                        @if($itemImageUrl)
                                                                            <img src="{{ $itemImageUrl }}" alt="{{ $item->title }}" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                                                        @else
                                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                                <i class="feather-package text-primary"></i>
                                                                            </div>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <span style="white-space: normal; width: 300px; display: block;" class="fw-bold">
                                                                            <i class="feather-package me-1"></i>{{ $item->title }}
                                                                        </span>
                                                                        <span class="badge bg-primary text-white fs-11 mt-1">Bundle</span>
                                                                        @if ($item->ebay_item_id)
                                                                            <span class="d-block fs-11 text-muted mt-1">{{ $item->ebay_item_id }}</span>
                                                                        @endif
                                                                    </td>
                                                                    <td><code class="fs-11">{{ $item->sku }}</code></td>
                                                                    <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                                                    <td class="text-end fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($item->unit_price, 2) }}</td>
                                                                    <td class="text-end fw-bold">{{ $order->currency ?? 'USD' }} {{ number_format($item->total_price, 2) }}</td>
                                                                </tr>

                                                                <!-- Bundle Components -->
                                                                @php
                                                                    $components = $order->items->where('bundle_product_id', $item->product_id)
                                                                                               ->where('is_bundle_summary', false);
                                                                @endphp
                                                                @foreach($components as $component)
                                                                    <tr class="bg-light text-muted">
                                                                        <td></td>
                                                                        <td>
                                                                            <span style="white-space: normal; width: 300px; display: block; padding-left: 20px;">
                                                                                ↳ {{ $component->title }}
                                                                            </span>
                                                                        </td>
                                                                        <td><code class="fs-11 text-muted">{{ $component->sku }}</code></td>
                                                                        <td class="text-center">{{ $component->quantity }}</td>
                                                                        <td class="text-end text-muted">-</td>
                                                                        <td class="text-end text-muted">-</td>
                                                                    </tr>
                                                                @endforeach
                                                            @elseif(!$item->bundle_product_id)
                                                                <!-- Regular Product Row -->
                                                                <tr>
                                                                    <td class="text-center">
                                                                        @php
                                                                            $itemImageUrl = $item->product?->getImageUrl();
                                                                        @endphp
                                                                        @if($itemImageUrl)
                                                                            <img src="{{ $itemImageUrl }}" alt="{{ $item->title }}" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                                                        @else
                                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                                <i class="feather-image text-muted"></i>
                                                                            </div>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <span style="white-space: normal; width: 300px; display: block;" class="fw-semibold">{{ \Illuminate\Support\Str::limit($item->title, 50) }}</span>
                                                                        @if ($item->ebay_item_id)
                                                                            <span class="d-block fs-11 text-muted">{{ $item->ebay_item_id }}</span>
                                                                        @endif
                                                                        @if($item->variation_attributes)
                                                                            <span class="d-block fs-11 text-muted">
                                                                                @foreach($item->variation_attributes as $attr => $val)
                                                                                    {{ $attr }}: {{ $val }}@if(!$loop->last), @endif
                                                                                @endforeach
                                                                            </span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @if($item->sku)
                                                                            <code class="fs-11">{{ $item->sku }}</code>
                                                                        @else
                                                                            <span class="text-muted fs-11">-</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-center fw-semibold">{{ $item->quantity }}</td>
                                                                    <td class="text-end">{{ $order->currency ?? 'USD' }} {{ number_format($item->unit_price, 2) }}</td>
                                                                    <td class="text-end fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($item->total_price, 2) }}</td>
                                                                </tr>
                                                            @endif
                                                        @empty
                                                            <tr>
                                                                <td colspan="7" class="text-center text-muted py-3">No items found.</td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                    @php
                                                        // Only count main items for footer totals (exclude bundle components)
                                                        $footerItems = $order->items->filter(fn($item) => !$item->bundle_product_id || $item->is_bundle_summary);
                                                    @endphp
                                                    @if($footerItems->count() > 0)
                                                        <tfoot class="table-light">
                                                            <tr>
                                                                <td colspan="3"></td>
                                                                <td class="text-center fw-bold">{{ $footerItems->sum('quantity') }}</td>
                                                                <td class="text-end"></td>
                                                                <td class="text-end fw-bold">{{ $order->currency ?? 'USD' }} {{ number_format($footerItems->sum('total_price'), 2) }}</td>
                                                            </tr>
                                                        </tfoot>
                                                    @endif
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center py-4 text-muted">No orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div>
                    @include('partials.per-page-dropdown', ['perPage' => $perPage])
                </div>
                <div>
                    {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

@endsection

@push('modals')
    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="refundModalLabel">Issue Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Order</label>
                        <p class="mb-0" id="refundOrderNumber"></p>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Order Total</label>
                            <p class="mb-0 fw-semibold" id="refundOrderTotal"></p>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Already Refunded</label>
                            <p class="mb-0 fw-semibold text-info" id="refundAlreadyRefunded"></p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Refundable Amount</label>
                        <p class="mb-0 fw-bold text-success fs-5" id="refundableAmount"></p>
                    </div>

                    <hr>

                    <form id="refundForm">
                        <input type="hidden" name="order_id" id="refundOrderId">
                        <input type="hidden" name="is_ebay" id="refundIsEbay">
                        <input type="hidden" name="ebay_order_id" id="refundEbayOrderId">
                        <input type="hidden" name="sales_channel_id" id="refundSalesChannelId">

                        <div class="mb-3">
                            <label class="form-label">Refund Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="refund_type" id="refundTypeFull" value="full" checked>
                                <label class="form-check-label" for="refundTypeFull">
                                    Full Refund
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="refund_type" id="refundTypePartial" value="partial">
                                <label class="form-check-label" for="refundTypePartial">
                                    Partial Refund
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="partialAmountWrap" style="display:none;">
                            <label for="refundAmount" class="form-label">Refund Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="refundCurrency">USD</span>
                                <input type="number" class="form-control" id="refundAmount" name="amount" step="0.01" min="0.01" placeholder="0.00">
                            </div>
                            <small class="text-muted">Maximum: <span id="maxRefundAmount"></span></small>
                        </div>

                        <div class="mb-3">
                            <label for="refundReason" class="form-label">Reason</label>
                            <select class="form-select" id="refundReason" name="reason">
                                <option value="BUYER_CANCEL">Buyer Requested Cancellation</option>
                                <option value="ITEM_NOT_RECEIVED">Item Not Received</option>
                                <option value="ITEM_NOT_AS_DESCRIBED">Item Not As Described</option>
                                <option value="DEFECTIVE_ITEM">Defective Item</option>
                                <option value="WRONG_ITEM">Wrong Item Sent</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="refundComment" class="form-label">Comment (Optional)</label>
                            <textarea class="form-control" id="refundComment" name="comment" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="submitRefundBtn">
                        <i class="feather-dollar-sign me-1"></i> Issue Refund
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ship Order Modal -->
    <div class="modal fade" id="shipModal" tabindex="-1" aria-labelledby="shipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shipModalLabel">Ship Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- Order Details (populated via JS) -->
                    <div id="shipOrderDetails" class="mb-3" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-1">Customer</h6>
                                <p class="mb-0" id="shipCustomerName"></p>
                                <small class="text-muted" id="shipCustomerEmail"></small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-1">Ship To</h6>
                                <p class="mb-0 small" id="shipAddress"></p>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Order #</small>
                                <div id="shipOrderNumber" class="fw-bold"></div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Items</small>
                                <div id="shipItemCount"></div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Order Total</small>
                                <div id="shipOrderTotal" class="fw-bold"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items (Display Only) -->
                    <div class="card mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="feather-shopping-bag me-2"></i>Order Items</h6>
                        </div>
                        <div class="card-body p-0">
                            <div id="itemsLoading" class="text-center py-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading items...
                            </div>
                            <div id="itemsTableWrap" style="display:none;">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0" style="font-size:12px;">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center" style="width:60px;">Qty</th>
                                                <th class="text-end" style="width:100px;">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsDisplayTbody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="itemsLoadError" class="alert alert-warning py-2 small mx-2 my-2 mb-0" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Package Dimensions -->
                    <div class="card mb-3">
                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                            <h6 class="card-title mb-0"><i class="feather-package me-2"></i>Package Dimensions</h6>
                            <small class="text-muted">Enter the total weight and dimensions of your package(s)</small>
                        </div>
                        <div class="card-body py-2">
                            <!-- Unit Selectors -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Weight Unit</label>
                                    <select id="indexWeightUnit" class="form-select form-select-sm">
                                        <option value="lbs" selected>Pounds (lbs)</option>
                                        <option value="kg">Kilograms (kg)</option>
                                        <option value="oz">Ounces (oz)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Dimension Unit</label>
                                    <select id="indexDimensionUnit" class="form-select form-select-sm">
                                        <option value="in" selected>Inches (in)</option>
                                        <option value="cm">Centimeters (cm)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Number of Packages</label>
                                    <select id="packageCount" class="form-select form-select-sm">
                                        <option value="1" selected>1 Package</option>
                                        <option value="2">2 Packages</option>
                                        <option value="3">3 Packages</option>
                                        <option value="4">4 Packages</option>
                                        <option value="5">5 Packages</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Package Dimension Fields Container -->
                            <div id="packageDimensionsContainer">
                                <!-- Will be populated dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Rate Quote Section -->
                    <div class="card mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="feather-dollar-sign me-2"></i>Get Shipping Rates</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label small mb-1">Carrier</label>
                                    <select id="rateCarrierId" class="form-select form-select-sm">
                                        <option value="">-- Select carrier --</option>
                                        @foreach($shippingCarriers as $carrier)
                                            <option value="{{ $carrier->id }}" {{ $carrier->is_default ? 'selected' : '' }}>
                                                {{ $carrier->name }} ({{ strtoupper($carrier->type) }}){{ $carrier->is_default ? ' ★' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" id="getRatesBtn" class="btn btn-info btn-sm w-100">
                                        <i class="feather-search me-1"></i> Get Rates
                                    </button>
                                </div>
                            </div>

                            <!-- Rates Result -->
                            <div id="shipperInfo" class="alert alert-light py-2 small mb-0 mt-2" style="display:none;">
                                <i class="feather-home me-1 text-muted"></i>
                                <strong>Ship From:</strong> <span id="shipperAddress"></span>
                            </div>
                            <div id="ratesResult" class="mt-2" style="display:none;">
                                <div id="ratesLoading" class="text-center py-2" style="display:none;">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Fetching rates...
                                </div>
                                <div id="ratesError" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                                <div id="ratesServiceWrap" style="display:none;">
                                    <label class="form-label small mb-1">Select Service</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-2" id="ratesTable">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="width:40px;"></th>
                                                    <th>Service</th>
                                                    <th class="text-end" style="width:120px;">Cost</th>
                                                    <th class="text-center" style="width:100px;">Transit</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ratesTableBody"></tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-1"><i class="feather-info me-1"></i>Rates are estimates only. Actual cost may vary based on package count.</small>

                                    <!-- Generate Label Button -->
                                    <button type="button" id="generateLabelBtn" class="btn btn-success w-100 mt-3" style="display:none;" disabled>
                                        <i class="feather-printer me-1"></i> Generate Label(s) & Mark Shipped
                                    </button>

                                    <!-- Label Result (shown after label is generated) -->
                                    <div id="labelResult" class="alert alert-success mt-3" style="display:none;">
                                        <h6 class="mb-2"><i class="feather-check-circle me-1"></i> Label Generated Successfully!</h6>
                                        <p class="mb-2">Tracking Number(s): <strong id="trackingNumber"></strong></p>
                                        <div id="downloadLabelLinks">
                                            <a href="#" id="downloadLabelLink" class="btn btn-primary btn-sm" target="_blank">
                                                <i class="feather-download me-1"></i> Download Label
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Mark as Shipped Section (optional, for manual tracking entry) -->
                    <div class="card mb-0">
                        <div class="card-header py-2" data-bs-toggle="collapse" data-bs-target="#manualShipSection" style="cursor:pointer;">
                            <h6 class="card-title mb-0 d-flex align-items-center justify-content-between">
                                <span><i class="feather-edit-3 me-2"></i>Manual Entry (Already Have Tracking?)</span>
                                <i class="feather-chevron-down"></i>
                            </h6>
                        </div>
                        <div id="manualShipSection" class="collapse">
                            <div class="card-body py-2">
                                <form id="shipForm" method="POST">
                                    @csrf
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Shipping Carrier <span class="text-danger">*</span></label>
                                            <input type="text" name="shipping_carrier" id="shipCarrierName" class="form-control form-control-sm" required placeholder="e.g., FedEx, UPS, USPS">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Tracking Number <span class="text-danger">*</span></label>
                                            <input type="text" name="tracking_number" class="form-control form-control-sm" required placeholder="Enter tracking number">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-3 gap-2">
                                        <button type="button" class="btn btn-light-brand btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="feather-check me-1"></i> Mark as Shipped
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {

            // ----------------------------------------------------------------
            // Expand/Collapse Order Items Subtable
            // ----------------------------------------------------------------
            $(document).on('click', '.expand-items-btn', function() {
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var $icon = $btn.find('.expand-icon');
                var $itemsRow = $('#order-items-' + orderId);

                if ($itemsRow.is(':visible')) {
                    // Collapse
                    $itemsRow.slideUp(200);
                    $icon.removeClass('feather-chevron-down').addClass('feather-chevron-right');
                    $btn.removeClass('bg-soft-primary');
                } else {
                    // Expand
                    $itemsRow.slideDown(200);
                    $icon.removeClass('feather-chevron-right').addClass('feather-chevron-down');
                    $btn.addClass('bg-soft-primary');
                }
            });

            // ----------------------------------------------------------------
            // Toggle All Subtables (Expand/Collapse All)
            // ----------------------------------------------------------------
            var allSubtablesExpanded = false;
            $('#toggleAllSubtablesBtn').on('click', function() {
                var $btn = $(this);
                var $icon = $('#toggleAllIcon');

                if (allSubtablesExpanded) {
                    // Collapse all
                    $('.order-items-row').slideUp(200);
                    $('.expand-items-btn').each(function() {
                        $(this).find('.expand-icon').removeClass('feather-chevron-down').addClass('feather-chevron-right');
                        $(this).removeClass('bg-soft-primary');
                    });
                    $icon.removeClass('feather-minimize-2').addClass('feather-list');
                    $btn.removeClass('text-primary').addClass('text-secondary');
                    $btn.attr('data-bs-original-title', 'Expand all order items');
                    allSubtablesExpanded = false;
                } else {
                    // Expand all
                    $('.order-items-row').slideDown(200);
                    $('.expand-items-btn').each(function() {
                        $(this).find('.expand-icon').removeClass('feather-chevron-right').addClass('feather-chevron-down');
                        $(this).addClass('bg-soft-primary');
                    });
                    $icon.removeClass('feather-list').addClass('feather-minimize-2');
                    $btn.removeClass('text-secondary').addClass('text-primary');
                    $btn.attr('data-bs-original-title', 'Collapse all order items');
                    allSubtablesExpanded = true;
                }
            });

            // ----------------------------------------------------------------
            // Refund Modal
            // ----------------------------------------------------------------
            $(document).on('click', '.refund-btn', function() {
                var $btn = $(this);

                $('#refundOrderId').val($btn.data('order-id'));
                $('#refundOrderNumber').text($btn.data('order-number'));
                $('#refundOrderTotal').text($btn.data('currency') + ' ' + parseFloat($btn.data('order-total')).toFixed(2));
                $('#refundAlreadyRefunded').text($btn.data('currency') + ' ' + parseFloat($btn.data('total-refunded')).toFixed(2));
                $('#refundableAmount').text($btn.data('currency') + ' ' + parseFloat($btn.data('refundable')).toFixed(2));
                $('#refundCurrency').text($btn.data('currency'));
                $('#maxRefundAmount').text($btn.data('currency') + ' ' + parseFloat($btn.data('refundable')).toFixed(2));
                $('#refundAmount').attr('max', $btn.data('refundable'));
                $('#refundIsEbay').val($btn.data('is-ebay'));
                $('#refundEbayOrderId').val($btn.data('ebay-order-id'));
                $('#refundSalesChannelId').val($btn.data('sales-channel-id'));

                // Reset form
                $('#refundForm')[0].reset();
                $('#refundTypeFull').prop('checked', true);
                $('#partialAmountWrap').hide();

                var refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
                refundModal.show();
            });

            // Toggle partial amount field
            $('input[name="refund_type"]').on('change', function() {
                if ($(this).val() === 'partial') {
                    $('#partialAmountWrap').show();
                    $('#refundAmount').prop('required', true);
                } else {
                    $('#partialAmountWrap').hide();
                    $('#refundAmount').prop('required', false);
                }
            });

            // Submit refund
            $('#submitRefundBtn').on('click', function() {
                var orderId = $('#refundOrderId').val();
                var isEbay = $('#refundIsEbay').val() === '1';
                var refundType = $('input[name="refund_type"]:checked').val();
                var amount = refundType === 'partial' ? parseFloat($('#refundAmount').val()) : null;
                var reason = $('#refundReason').val();
                var comment = $('#refundComment').val();

                if (refundType === 'partial' && (!amount || amount <= 0)) {
                    alert('Please enter a valid refund amount.');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Processing...');

                var url, data;

                if (isEbay) {
                    var salesChannelId = $('#refundSalesChannelId').val();
                    var ebayOrderId = $('#refundEbayOrderId').val();

                    if (refundType === 'partial') {
                        url = '/api/ebay/refunds/' + salesChannelId + '/' + ebayOrderId + '/partial';
                        data = {
                            _token: '{{ csrf_token() }}',
                            line_items: [],
                            reason: reason,
                            comment: comment
                        };
                    } else {
                        url = '/api/ebay/refunds/' + salesChannelId + '/' + ebayOrderId;
                        data = {
                            _token: '{{ csrf_token() }}',
                            reason: reason,
                            comment: comment
                        };
                    }
                } else {
                    // Local order refund (using web routes)
                    if (refundType === 'partial') {
                        url = '/orders/' + orderId + '/refund/partial';
                        data = {
                            _token: '{{ csrf_token() }}',
                            amount: amount,
                            reason: reason,
                            comment: comment
                        };
                    } else {
                        url = '/orders/' + orderId + '/refund';
                        data = {
                            _token: '{{ csrf_token() }}',
                            reason: reason,
                            comment: comment
                        };
                    }
                }

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Modal.getInstance(document.getElementById('refundModal')).hide();
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to process refund');
                            $btn.prop('disabled', false).html('<i class="feather-dollar-sign me-1"></i> Issue Refund');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to process refund: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        $btn.prop('disabled', false).html('<i class="feather-dollar-sign me-1"></i> Issue Refund');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Unit label updates when unit selectors change
            // ----------------------------------------------------------------
            $('#indexWeightUnit').on('change', function() {
                var unit = $(this).val();
                $('#indexWeightUnitLabel').text('(' + unit + ')');
            });

            $('#indexDimensionUnit').on('change', function() {
                var unit = $(this).val();
                $('#indexDimUnitLabelL, #indexDimUnitLabelW, #indexDimUnitLabelH').text('(' + unit + ')');
            });

            // ----------------------------------------------------------------
            // Open ship modal — populate order details from data attributes
            // then load items/dimensions via AJAX
            // ----------------------------------------------------------------
            $(document).on('click', '.ship-btn', function() {
                var $btn = $(this);
                var orderId = $btn.data('id');

                // Populate order summary panel
                $('#shipCustomerName').text($btn.data('customer'));
                $('#shipCustomerEmail').text($btn.data('email'));
                $('#shipAddress').text($btn.data('address'));
                $('#shipOrderNumber').text($btn.data('order-number'));
                $('#shipItemCount').text($btn.data('items'));
                $('#shipOrderTotal').text($btn.data('total'));
                $('#shipOrderDetails').show();

                // Pre-fill carrier name from selected rate carrier dropdown label
                var $carrierOpt = $('#rateCarrierId option:selected');
                if ($carrierOpt.val()) {
                    $('#shipCarrierName').val($carrierOpt.text().replace(' ★', '').replace(/\s*\(.*\)$/, '').trim());
                }

                // Wire up ship form action for this order
                $('#shipForm').attr('action', '/orders/' + orderId + '/ship');

                // Reset rates panel
                $('#ratesResult').hide();
                $('#ratesError').hide().text('');
                $('#ratesLoading').hide();
                $('#ratesServiceWrap').hide();
                $('#ratesTableBody').empty();
                $('#generateLabelBtn').hide().prop('disabled', true);
                $('#labelResult').hide();
                $('#shipperInfo').hide();
                $('#shipperAddress').text('');

                // Reset items panel
                $('#itemsDisplayTbody').empty();
                $('#itemsTableWrap').hide();
                $('#itemsLoadError').hide().text('');
                $('#itemsLoading').show();

                // Store current order id for rate lookup
                $('#getRatesBtn').data('order-id', orderId);

                // Reset package count and initialize package dimension fields
                $('#packageCount').val('1');
                updatePackageDimensionFields();

                var shipModal = new bootstrap.Modal(document.getElementById('shipModal'));
                shipModal.show();

                // Load items for display (no dimensions needed, just for reference)
                $.ajax({
                    url: '/orders/' + orderId + '/rate-info',
                    type: 'GET',
                    success: function(response) {
                        $('#itemsLoading').hide();
                        if (!response.success || !response.items.length) {
                            $('#itemsLoadError').text('No items found for this order.').show();
                            return;
                        }
                        $.each(response.items, function(i, item) {
                            var row =
                                '<tr>' +
                                '<td><strong>' + $('<span>').text(item.title).html() + '</strong>' +
                                    '<br><small class="text-muted">' + $('<span>').text(item.sku).html() + '</small></td>' +
                                '<td class="text-center">' + item.quantity + '</td>' +
                                '<td class="text-end">$' + parseFloat(item.price || 0).toFixed(2) + '</td>' +
                                '</tr>';
                            $('#itemsDisplayTbody').append(row);
                        });
                        $('#itemsTableWrap').show();
                    },
                    error: function() {
                        $('#itemsLoading').hide();
                        $('#itemsLoadError').text('Could not load order items.').show();
                    }
                });
            });

            // ----------------------------------------------------------------
            // Get shipping rates (estimate only — no shipment created)
            // ----------------------------------------------------------------
            $('#getRatesBtn').on('click', function() {
                var orderId   = $(this).data('order-id');
                var carrierId = $('#rateCarrierId').val();

                if (!carrierId) {
                    alert('Please select a carrier first.');
                    return;
                }

                // Collect package dimensions
                var packages = [];
                $('.package-dim-row').each(function() {
                    var declared_value = '';
                    if ($(this).find('.liability-checkbox').is(':checked')) {
                        declared_value = parseFloat($(this).find('.declared_value'));
                    }
                    packages.push({
                        weight: parseFloat($(this).find('.pkg-weight').val()) || 1,
                        length: parseFloat($(this).find('.pkg-length').val()) || 12,
                        width:  parseFloat($(this).find('.pkg-width').val()) || 12,
                        height: parseFloat($(this).find('.pkg-height').val()) || 12,
                        customer_reference: $(this).find('.pkg-reference').val() || '', 
                        declared_value: declared_value, 
                    });
                });

                if (packages.length === 0) {
                    alert('Please enter package dimensions.');
                    return;
                }

                $('#ratesResult').show();
                $('#ratesLoading').show();
                $('#ratesError').hide().text('');
                $('#ratesServiceWrap').hide();
                $('#ratesTableBody').empty();
                $('#generateLabelBtn').hide().prop('disabled', true);
                $('#labelResult').hide();

                // Get selected units
                var weightUnit = $('#indexWeightUnit').val();
                var dimensionUnit = $('#indexDimensionUnit').val();

                $.ajax({
                    url: '{{ route('orders.shipping-rates') }}',
                    type: 'POST',
                    data: {
                        _token:        '{{ csrf_token() }}',
                        order_id:      orderId,
                        carrier_id:    carrierId,
                        packages:      packages,
                        weight_unit:   weightUnit,
                        dimension_unit: dimensionUnit
                    },
                    success: function(response) {
                        $('#ratesLoading').hide();
                        if (!response.success) {
                            $('#ratesError').text(response.message || 'Failed to fetch rates.').show();
                            return;
                        }
                        var rates = response.rates || [];
                        if (rates.length === 0) {
                            $('#ratesError').text('No rates returned. Check carrier credentials and address.').show();
                            return;
                        }

                        // Show shipper address if available
                        if (response.shipper) {
                            $('#shipperAddress').text(response.shipper);
                            $('#shipperInfo').show();
                        }

                        // Populate rates table with radio buttons
                        $('#ratesTableBody').empty();
                        $.each(rates, function(i, rate) {
                            var cost = rate.amount !== null
                                ? rate.currency + ' ' + parseFloat(rate.amount).toFixed(2)
                                : 'N/A';
                            var transit = rate.transit_days || '-';
                            var row = '<tr class="rate-row" style="cursor:pointer;">' +
                                '<td class="text-center align-middle">' +
                                    '<input type="radio" name="serviceRadio" value="' + rate.service_code + '"' +
                                    ' data-amount="' + (rate.amount || '') + '"' +
                                    ' data-currency="' + (rate.currency || 'USD') + '"' +
                                    ' data-transit="' + (rate.transit_days || '') + '"' +
                                    ' data-name="' + rate.service_name + '">' +
                                '</td>' +
                                '<td>' + rate.service_name + '</td>' +
                                '<td class="text-right">' + cost + '</td>' +
                                '<td class="text-center">' + transit + '</td>' +
                                '</tr>';
                            $('#ratesTableBody').append(row);
                        });
                        $('#ratesServiceWrap').show();
                        $('#generateLabelBtn').hide().prop('disabled', true);
                        $('#labelResult').hide();

                        // Auto-select default service if available
                        if (response.default_service) {
                            var $defaultRadio = $('input[name="serviceRadio"][value="' + response.default_service + '"]');
                            if ($defaultRadio.length) {
                                $defaultRadio.prop('checked', true).trigger('change');
                            }
                        }

                        // Auto-fill carrier name in manual ship form
                        var carrierLabel = $('#rateCarrierId option:selected').text()
                            .replace(' ★', '').replace(/\s*\(.*\)$/, '').trim();
                        $('#shipCarrierName').val(carrierLabel);
                    },
                    error: function(xhr) {
                        $('#ratesLoading').hide();
                        var msg = xhr.responseJSON?.message || 'Failed to fetch rates.';
                        $('#ratesError').text(msg).show();
                    }
                });
            });

            // ----------------------------------------------------------------
            // Service selection — clicking row selects radio, enable Generate Label
            // ----------------------------------------------------------------
            $(document).on('click', '.rate-row', function() {
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });

            $(document).on('change', 'input[name="serviceRadio"]', function() {
                if ($(this).is(':checked')) {
                    $('#generateLabelBtn').show().prop('disabled', false);
                }
            });

            // ----------------------------------------------------------------
            // Package Count Change Handler
            // ----------------------------------------------------------------
            $('#packageCount').on('change', function() {
                updatePackageDimensionFields();
            });

            function updatePackageDimensionFields() {
                var count = parseInt($('#packageCount').val()) || 1;
                var weightUnit = $('#indexWeightUnit').val() || 'lbs';
                var dimUnit = $('#indexDimensionUnit').val() || 'in';
                var $container = $('#packageDimensionsContainer');
                $container.empty();

                for (var i = 0; i < count; i++) {
                    var pkgLabel = count > 1 ? 'Package ' + (i + 1) : 'Package';
                    var html = `
                        <div class="border rounded p-2 mb-2 bg-white package-dim-row" data-package="${i}">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary">${pkgLabel}</span>
                                <small class="text-muted">Enter weight and box dimensions</small>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-3">
                                    <label class="form-label small mb-1">Weight (${weightUnit}) *</label>
                                    <input type="number" step="0.1" min="0.1" class="form-control form-control-sm pkg-weight" data-pkg="${i}" value="1" required>
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">L (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm pkg-length" data-pkg="${i}" value="12">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">W (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm pkg-width" data-pkg="${i}" value="12">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">H (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm pkg-height" data-pkg="${i}" value="12">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Customer Reference <small class="text-muted">(max 30 chars, shown on label)</small></label>
                                    <input type="text" class="form-control form-control-sm pkg-reference" data-pkg="${i}" maxlength="30" placeholder="Auto-generated from item names if empty">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input liability-checkbox" id="package-${i}" data-pkg="${i}">
                                        <label class="form-check-label" for="package-${i}">Purchase a higher limit of liability from FedEx</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="number" id="package-${i}-input" class="form-control declared-value form-control-sm" data-pkg="${i}" disabled>
                                </div>
                            </div>
                        </div>
                    `;
                    $container.append(html);
                }
            }

            // Update package dimension labels when units change
            $('#indexWeightUnit, #indexDimensionUnit').on('change', function() {
                updatePackageDimensionFields();
            });

            $(document).on('change', '.liability-checkbox', function() {
                if ($(this).is(':checked')) {
                    $('#' + $(this).attr('id') + '-input').removeAttr('disabled');
                } else {
                    $('#' + $(this).attr('id') + '-input').attr('disabled', 'disabled').val('');
                }
            });

            // ----------------------------------------------------------------
            // Generate Label button click
            // ----------------------------------------------------------------
            $('#generateLabelBtn').on('click', function() {
                var $radio = $('input[name="serviceRadio"]:checked');
                if (!$radio.length) {
                    alert('Please select a service first.');
                    return;
                }

                var orderId     = $('#getRatesBtn').data('order-id');
                var carrierId   = $('#rateCarrierId').val();
                var serviceCode = $radio.val();
                var packageCount = parseInt($('#packageCount').val()) || 1;

                // Get selected units
                var weightUnit = $('#indexWeightUnit').val();
                var dimensionUnit = $('#indexDimensionUnit').val();

                // Collect package data
                var packages = [];
                $('.package-dim-row').each(function() {
                    var declared_value = '';
                    if ($(this).find('.liability-checkbox').is(':checked')) {
                        declared_value = $(this).find('.declared_value');
                    }
                    packages.push({
                        weight: parseFloat($(this).find('.pkg-weight').val()) || 1,
                        length: parseFloat($(this).find('.pkg-length').val()) || 12,
                        width:  parseFloat($(this).find('.pkg-width').val()) || 12,
                        height: parseFloat($(this).find('.pkg-height').val()) || 12,
                        customer_reference: $(this).find('.pkg-reference').val() || '', 
                        declared_value: declared_value
                    });
                });

                var $btn = $(this);
                var labelText = packageCount > 1 ? packageCount + ' Labels' : 'Label';
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating ' + labelText + '...');

                $.ajax({
                    url: '/orders/' + orderId + '/generate-multi-labels',
                    type: 'POST',
                    data: {
                        _token:         '{{ csrf_token() }}',
                        carrier_id:     carrierId,
                        service_code:   serviceCode,
                        package_count:  packageCount,
                        packages:       packages,
                        weight_unit:    weightUnit,
                        dimension_unit: dimensionUnit
                    },
                    success: function(response) {
                        if (response.success) {
                            // Display all tracking numbers
                            var trackingText = response.tracking_numbers.join(', ');
                            $('#trackingNumber').text(trackingText);

                            // Create download links for all packages
                            var $linksContainer = $('#downloadLabelLinks');
                            $linksContainer.empty();

                            if (response.label_urls.length === 1) {
                                $linksContainer.append(
                                    '<a href="' + response.label_urls[0] + '" class="btn btn-primary btn-sm" target="_blank">' +
                                    '<i class="feather-download me-1"></i> Download Label</a>'
                                );
                                window.open(response.label_urls[0], '_blank');
                            } else {
                                response.label_urls.forEach(function(url, index) {
                                    $linksContainer.append(
                                        '<a href="' + url + '" class="btn btn-primary btn-sm me-1 mb-1" target="_blank">' +
                                        '<i class="feather-download me-1"></i> Package ' + (index + 1) +
                                        '</a>'
                                    );
                                    // Open each label in new window
                                    window.open(url, '_blank');
                                });
                            }

                            $('#labelResult').show();
                            $btn.hide();

                            // Reload after 4 seconds to update order status
                            // setTimeout(function() {
                            //     location.reload();
                            // }, 4000);
                        } else {
                            alert(response.message || 'Failed to generate labels.');
                            $btn.prop('disabled', false).html('<i class="feather-printer me-1"></i> Generate Label(s) & Mark Shipped');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Failed to generate labels.';
                        alert(msg);
                        $btn.prop('disabled', false).html('<i class="feather-printer me-1"></i> Generate Label(s) & Mark Shipped');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Cancel order
            // ----------------------------------------------------------------
            $(document).on('click', '.cancel-btn', function() {
                var orderId = $(this).data('id');
                if (confirm('Are you sure you want to cancel this order?')) {
                    $.ajax({
                        url: '/orders/' + orderId + '/cancel',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            reason: 'Cancelled by admin'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.message || 'Failed to cancel order');
                            }
                        },
                        error: function(xhr) {
                            alert('Failed to cancel order: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        }
                    });
                }
            });

            // ----------------------------------------------------------------
            // Cancel shipping label
            // ----------------------------------------------------------------
            $(document).on('click', '.cancel-label-btn', function() {
                var $btn = $(this);
                var orderId = $btn.data('id');
                var orderNumber = $btn.data('order-number');
                var trackingNumber = $btn.data('tracking');
                var isEbay = $btn.data('is-ebay') === '1';

                var confirmMsg = 'Are you sure you want to cancel the shipping label for order ' + orderNumber + '?\n\n';
                confirmMsg += 'Tracking #: ' + trackingNumber + '\n\n';
                confirmMsg += 'This will:\n';
                confirmMsg += '- Void the label with FedEx\n';
                confirmMsg += '- Remove tracking information from the order\n';
                confirmMsg += '- Restore inventory\n';
                confirmMsg += '- Revert order status to Processing\n';
                if (isEbay) {
                    confirmMsg += '- Remove tracking from eBay\n';
                }

                if (!confirm(confirmMsg)) {
                    return;
                }

                $btn.addClass('disabled').find('i').removeClass('feather-x-circle').addClass('spinner-border spinner-border-sm');

                $.ajax({
                    url: '/orders/' + orderId + '/cancel-label',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to cancel label');
                            $btn.removeClass('disabled').find('i').removeClass('spinner-border spinner-border-sm').addClass('feather-x-circle');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to cancel label: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        $btn.removeClass('disabled').find('i').removeClass('spinner-border spinner-border-sm').addClass('feather-x-circle');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Submit ship form (mark as shipped)
            // ----------------------------------------------------------------
            $('#shipForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Modal.getInstance(document.getElementById('shipModal')).hide();
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to update order');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to update order: ' + (xhr.responseJSON?.message || 'Unknown error'));
                    }
                });
            });

            // ----------------------------------------------------------------
            // Sync eBay Order Status button
            // ----------------------------------------------------------------
            $('#syncEbayStatusBtn').on('click', function() {
                var $btn = $(this);
                var originalHtml = $btn.html();

                $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Syncing...');

                $.ajax({
                    url: '{{ route('orders.sync-ebay-status') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.html('<i class="feather-check me-1"></i> Queued!');
                            setTimeout(function() {
                                $btn.prop('disabled', false).html(originalHtml);
                            }, 3000);
                        } else {
                            alert(response.message || 'Failed to start sync');
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to start sync: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // ----------------------------------------------------------------
            // FedEx End of Day Close button
            // ----------------------------------------------------------------
            $('#closeFedExBtn').on('click', function() {
                if (!confirm('Close FedEx shipments for today? This will commit all shipments created today and generate the manifest for pickup.')) {
                    return;
                }

                var $btn = $(this);
                var originalHtml = $btn.html();

                $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i>');

                $.ajax({
                    url: '{{ route('orders.close-fedex-shipments') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            var msg = response.message;
                            if (response.confirmation_number) {
                                msg += '\n\nConfirmation #: ' + response.confirmation_number;
                            }
                            if (response.manifest_path) {
                                msg += '\n\nManifest saved to: ' + response.manifest_path;
                            }
                            alert(msg);
                            $btn.html('<i class="feather-check"></i>');
                            setTimeout(function() {
                                $btn.prop('disabled', false).html(originalHtml);
                            }, 3000);
                        } else {
                            alert(response.message || 'Failed to close shipments');
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to close shipments: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });

        });
    </script>

    @can('delete orders')
        @include('partials.bulk-delete-scripts', ['routeName' => 'orders.bulk-delete', 'itemName' => 'orders'])
    @endcan
@endpush
