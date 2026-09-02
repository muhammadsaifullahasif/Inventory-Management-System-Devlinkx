@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ asset('vendors/css/select2.min.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('vendors/css/select2-theme.min.css') }}" />
@endpush

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Order</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.show', $order->id) }}">{{ $order->order_number }}</a></li>
                <li class="breadcrumb-item">Edit</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('orders.show', $order->id) }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Order</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <!-- Order Items / Product Matching -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-package me-2"></i>Order Items &mdash; Match Products by SKU</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-12">
                        Choose the correct product for each line item. Items whose SKU didn't match automatically show
                        no product below &mdash; select one and save to link it. Once a product is linked, saving will
                        record the sale, deduct stock and post revenue/COGS for that item (skipped automatically if it
                        was already recorded before).
                    </p>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>SKU</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th style="min-width: 260px;">Matched Product</th>
                                    <th class="text-center">Sale Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->items as $item)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ \Illuminate\Support\Str::limit($item->title, 45) }}</span>
                                            @if($item->is_bundle_summary)
                                                <span class="d-block fs-11 text-muted">Bundle</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->sku)
                                                <code class="fs-11">{{ $item->sku }}</code>
                                            @else
                                                <span class="text-muted fs-11">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ $order->currency ?? 'USD' }} {{ number_format($item->unit_price, 2) }}</td>
                                        <td>
                                            <select class="form-select item-product-select" data-item-id="{{ $item->id }}">
                                                <option value="">-- Not matched --</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}" {{ $item->product_id == $product->id ? 'selected' : '' }}>
                                                        {{ $product->name }} ({{ $product->sku }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            @if($item->inventory_updated)
                                                <span class="badge bg-soft-success text-success">Recorded</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No items on this order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Order Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-settings me-2"></i>Order Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Order Status</label>
                        <select id="order_status" class="form-select">
                            @foreach(['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'ready_for_pickup' => 'Ready for Pickup', 'cancellation_requested' => 'Cancellation Requested', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'] as $value => $label)
                                <option value="{{ $value }}" {{ $order->order_status == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select id="payment_status" class="form-select">
                            @foreach(['pending' => 'Pending', 'awaiting_payment' => 'Awaiting Payment', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $value => $label)
                                <option value="{{ $value }}" {{ $order->payment_status == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fulfillment Status</label>
                        <select id="fulfillment_status" class="form-select">
                            @foreach(['unfulfilled' => 'Unfulfilled', 'partially_fulfilled' => 'Partially Fulfilled', 'fulfilled' => 'Fulfilled', 'ready_for_pickup' => 'Ready for Pickup'] as $value => $label)
                                <option value="{{ $value }}" {{ $order->fulfillment_status == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shipping Carrier</label>
                        <input type="text" id="shipping_carrier" class="form-control" value="{{ $order->shipping_carrier }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tracking Number</label>
                        <input type="text" id="tracking_number" class="form-control" value="{{ $order->tracking_number }}">
                    </div>

                    <button type="button" id="saveOrderBtn" class="btn btn-primary w-100">
                        <i class="feather-save me-2"></i>Save Changes
                    </button>
                    <a href="{{ route('orders.show', $order->id) }}" class="btn btn-light-brand w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function() {
            $('.item-product-select').select2({
                placeholder: 'Search and select a product...',
                allowClear: true,
                width: '100%',
                theme: 'bootstrap-5'
            });

            $('#saveOrderBtn').on('click', function() {
                var $btn = $(this);
                var items = [];

                $('.item-product-select').each(function() {
                    items.push({
                        id: $(this).data('item-id'),
                        product_id: $(this).val() || null
                    });
                });

                var payload = {
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT',
                    order_status: $('#order_status').val(),
                    payment_status: $('#payment_status').val(),
                    fulfillment_status: $('#fulfillment_status').val(),
                    shipping_carrier: $('#shipping_carrier').val(),
                    tracking_number: $('#tracking_number').val(),
                    items: items
                };

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

                $.ajax({
                    url: '{{ route('orders.update', $order->id) }}',
                    type: 'POST',
                    data: payload,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '{{ route('orders.show', $order->id) }}';
                        } else {
                            window.location.reload();
                        }
                    },
                    error: function() {
                        window.location.reload();
                    }
                });
            });
        });
    </script>
@endpush
