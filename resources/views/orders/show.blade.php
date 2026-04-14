@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Order Details</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">{{ $order->order_number }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <span class="badge bg-soft-{{ $order->order_status === 'cancelled' ? 'danger' : 'primary' }} text-{{ $order->order_status === 'cancelled' ? 'danger' : 'primary' }}">
                        {{ strtoupper($order->order_status ?? 'N/A') }}
                    </span>
                    <a href="{{ route('orders.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Orders</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <!-- Order Summary -->
        <div class="col-md-8">
            <!-- Order Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-file-text me-2"></i>Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" style="width: 140px;">Order Number:</td>
                                    <td><strong>{{ $order->order_number }}</strong></td>
                                </tr>
                                @if($order->ebay_order_id)
                                <tr>
                                    <td class="text-muted">eBay Order ID:</td>
                                    <td><code class="fs-12">{{ $order->ebay_order_id }}</code></td>
                                </tr>
                                @endif
                                <tr>
                                    <td class="text-muted">Sales Channel:</td>
                                    <td>
                                        @if($order->salesChannel)
                                            <span class="badge bg-soft-info text-info">{{ $order->salesChannel->name }}</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Order Date:</td>
                                    <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d M, Y h:i A') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Paid At:</td>
                                    <td>{{ $order->paid_at ? \Carbon\Carbon::parse($order->paid_at)->format('d M, Y h:i A') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted" style="width: 140px;">Order Status:</td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'shipped' => 'primary',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger',
                                                'refunded' => 'secondary',
                                                'ready_for_pickup' => 'info',
                                                'cancellation_requested' => 'warning',
                                            ];
                                            $statusColor = $statusColors[$order->order_status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-soft-{{ $statusColor }} text-{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $order->order_status ?? 'N/A')) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Status:</td>
                                    <td>
                                        @php
                                            $paymentColors = [
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'refunded' => 'info',
                                                'failed' => 'danger',
                                                'awaiting_payment' => 'warning',
                                            ];
                                            $paymentColor = $paymentColors[$order->payment_status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-soft-{{ $paymentColor }} text-{{ $paymentColor }}">{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'N/A')) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Fulfillment:</td>
                                    <td>
                                        @php
                                            $fulfillmentColors = [
                                                'unfulfilled' => 'danger',
                                                'partially_fulfilled' => 'warning',
                                                'fulfilled' => 'success',
                                                'ready_for_pickup' => 'info',
                                            ];
                                            $fulfillmentColor = $fulfillmentColors[$order->fulfillment_status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-soft-{{ $fulfillmentColor }} text-{{ $fulfillmentColor }}">{{ ucfirst(str_replace('_', ' ', $order->fulfillment_status ?? 'N/A')) }}</span>
                                    </td>
                                </tr>
                                @if($order->shipped_at)
                                <tr>
                                    <td class="text-muted">Shipped At:</td>
                                    <td>{{ \Carbon\Carbon::parse($order->shipped_at)->format('d M, Y h:i A') }}</td>
                                </tr>
                                @endif
                                @php
                                    $allTrackingNumbers = $order->getAllTrackingNumbers();
                                @endphp
                                @if(count($allTrackingNumbers) > 0 || $order->tracking_number)
                                <tr>
                                    <td class="text-muted align-top">Tracking:</td>
                                    <td>
                                        @if(count($allTrackingNumbers) > 1)
                                            {{-- Multi-package tracking --}}
                                            @foreach($allTrackingNumbers as $index => $pkg)
                                                <div class="mb-1">
                                                    <span class="badge bg-soft-info text-info">Package {{ $index + 1 }}</span>
                                                    <strong>{{ $pkg['carrier'] ?? $order->shipping_carrier }}</strong>:
                                                    @if($order->tracking_url)
                                                        <a href="{{ $order->tracking_url }}{{ $pkg['tracking_number'] }}" target="_blank" class="text-primary">
                                                            {{ $pkg['tracking_number'] }} <i class="feather-external-link fs-10"></i>
                                                        </a>
                                                    @else
                                                        {{ $pkg['tracking_number'] }}
                                                    @endif
                                                </div>
                                            @endforeach
                                        @elseif($order->tracking_number)
                                            {{-- Single tracking --}}
                                            <strong>{{ $order->shipping_carrier }}</strong>:
                                            @if($order->tracking_url)
                                                <a href="{{ $order->tracking_url }}" target="_blank" class="text-primary">
                                                    {{ $order->tracking_number }} <i class="feather-external-link fs-10"></i>
                                                </a>
                                            @else
                                                {{ $order->tracking_number }}
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if($order->delivered_at)
                                <tr>
                                    <td class="text-muted">Delivered At:</td>
                                    <td>
                                        <span class="text-success">
                                            <i class="feather-check-circle me-1"></i>
                                            {{ \Carbon\Carbon::parse($order->delivered_at)->format('d M, Y h:i A') }}
                                        </span>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-shopping-cart me-2"></i>Order Items ({{ $order->items->count() }})</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Image</th>
                                    <th>Item</th>
                                    <th>SKU</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $index => $item)
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
                                            <span class="fw-semibold">{{ $item->title }}</span>
                                            @if($item->variation_attributes)
                                                <span class="d-block fs-11 text-muted">
                                                    @foreach($item->variation_attributes as $key => $value)
                                                        {{ $key }}: {{ $value }}{{ !$loop->last ? ', ' : '' }}
                                                    @endforeach
                                                </span>
                                            @endif
                                            @if($item->ebay_item_id)
                                                <span class="d-block fs-11 text-muted">eBay Item: {{ $item->ebay_item_id }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-12">{{ $item->sku ?? 'N/A' }}</span>
                                            @if($item->product)
                                                <a href="{{ route('products.show', $item->product_id) }}" class="d-block text-primary fs-11">View Product</a>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">{{ $item->currency ?? 'USD' }} {{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end">{{ $item->currency ?? 'USD' }} {{ number_format($item->total_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end">{{ $order->currency ?? 'USD' }} {{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Shipping:</strong></td>
                                    <td class="text-end">{{ $order->currency ?? 'USD' }} {{ number_format($order->shipping_cost, 2) }}</td>
                                </tr>
                                @if($order->tax > 0)
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                    <td class="text-end">{{ $order->currency ?? 'USD' }} {{ number_format($order->tax, 2) }}</td>
                                </tr>
                                @endif
                                @if($order->discount > 0)
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                    <td class="text-end text-danger">-{{ $order->currency ?? 'USD' }} {{ number_format($order->discount, 2) }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong class="text-primary">{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Meta Data -->
            @if(count($metaData) > 0)
            <div class="card mt-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title"><i class="feather-database me-2"></i>Additional Details</h5>
                    <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#metaCollapse">
                        <i class="feather-chevron-down"></i>
                    </a>
                </div>
                <div class="collapse" id="metaCollapse">
                    <div class="card-body">
                        <div class="row">
                            @if(isset($metaData['shipping_address']))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Shipping Address (Meta)</h6>
                                <table class="table table-sm table-borderless">
                                    @foreach($metaData['shipping_address'] as $key => $value)
                                        @if(!empty($value))
                                        <tr>
                                            <td class="text-muted" style="width: 120px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                            @endif

                            @if(isset($metaData['shipping_service_selected']))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Shipping Service</h6>
                                <table class="table table-sm table-borderless">
                                    @foreach($metaData['shipping_service_selected'] as $key => $value)
                                        @if(!empty($value) && $value !== '0')
                                        <tr>
                                            <td class="text-muted" style="width: 150px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                            @endif

                            @if(isset($metaData['transaction_status']))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Transaction Status</h6>
                                <table class="table table-sm table-borderless">
                                    @foreach($metaData['transaction_status'] as $key => $value)
                                        @if(!empty($value))
                                        <tr>
                                            <td class="text-muted" style="width: 150px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                            @endif

                            @if(isset($metaData['payment_details']))
                            <div class="col-md-6 mb-3">
                                <h6 class="fw-bold">Payment Details</h6>
                                <table class="table table-sm table-borderless">
                                    @foreach($metaData['payment_details'] as $key => $value)
                                        @if(!empty($value) && $value !== '0')
                                        <tr>
                                            <td class="text-muted" style="width: 180px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                            @endif

                            @if(isset($metaData['tax_details']))
                            <div class="col-md-12 mb-3">
                                <h6 class="fw-bold">Tax Details</h6>
                                <pre class="bg-light p-2 rounded" style="font-size: 11px; max-height: 200px; overflow: auto;">{{ json_encode($metaData['tax_details'], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            @endif

                            @if(isset($metaData['seller_info']))
                            <div class="col-md-6">
                                <h6 class="fw-bold">Seller Info</h6>
                                <table class="table table-sm table-borderless">
                                    @foreach($metaData['seller_info'] as $key => $value)
                                        @if(!empty($value))
                                        <tr>
                                            <td class="text-muted" style="width: 120px;">{{ ucfirst(str_replace('_', ' ', $key)) }}:</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Customer & Shipping Sidebar -->
        <div class="col-md-4">
            <!-- Customer Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-user me-2"></i>Customer</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $order->buyer_name ?? 'N/A' }}</strong></p>
                    @if($order->buyer_username)
                        <p class="mb-1 text-muted fs-12">Username: {{ $order->buyer_username }}</p>
                    @endif
                    @if($order->buyer_email)
                        <p class="mb-1"><i class="feather-mail me-1 fs-12"></i> <a href="mailto:{{ $order->buyer_email }}">{{ $order->buyer_email }}</a></p>
                    @endif
                    @if($order->buyer_phone)
                        <p class="mb-0"><i class="feather-phone me-1 fs-12"></i> {{ $order->buyer_phone }}</p>
                    @endif
                </div>
            </div>

            <!-- Shipping Address Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-map-pin me-2"></i>Shipping Address</h5>
                </div>
                <div class="card-body">
                    <address class="mb-0">
                        <strong>{{ $order->shipping_name ?? 'N/A' }}</strong><br>
                        @if($order->shipping_address_line1)
                            {{ $order->shipping_address_line1 }}<br>
                        @endif
                        @if($order->shipping_address_line2)
                            {{ $order->shipping_address_line2 }}<br>
                        @endif
                        @if($order->shipping_city || $order->shipping_state || $order->shipping_postal_code)
                            {{ $order->shipping_city }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }} {{ $order->shipping_postal_code }}<br>
                        @endif
                        @if($order->shipping_country_name || $order->shipping_country)
                            {{ $order->shipping_country_name ?? $order->shipping_country }}
                        @endif
                    </address>
                </div>
            </div>

            <!-- Buyer Message -->
            @if($order->buyer_checkout_message)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-message-square me-2"></i>Buyer Message</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $order->buyer_checkout_message }}</p>
                </div>
            </div>
            @endif

            <!-- Actions Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-settings me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    @if($order->fulfillment_status !== 'fulfilled' && $order->order_status !== 'cancelled')
                        <button type="button" class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#shipModal">
                            <i class="feather-truck me-1"></i> Mark as Shipped
                        </button>
                    @endif

                    @php
                        $shippingPackages = $order->getAllTrackingNumbers();
                        $hasLabels = count($shippingPackages) > 0 || !is_null($order->shipping_label_path);
                    @endphp
                    @if ($order->order_status === 'shipped' && $order->fulfillment_status === 'fulfilled' && $hasLabels)
                        @if(count($shippingPackages) > 1)
                            {{-- Multi-package labels --}}
                            <div class="mb-3">
                                <label class="form-label small fw-semibold mb-2">
                                    <i class="feather-package me-1"></i> Shipping Labels ({{ count($shippingPackages) }} packages)
                                </label>
                                <div class="d-grid gap-2">
                                    {{-- Download/Print All Labels --}}
                                    <button type="button" class="btn btn-primary btn-sm" id="openAllLabelsBtn">
                                        <i class="feather-printer me-1"></i> Open All Labels for Printing
                                    </button>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="feather-download me-1"></i> Download Individual
                                        </button>
                                        <ul class="dropdown-menu">
                                            @foreach($shippingPackages as $index => $pkg)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('orders.label', ['id' => $order->id, 'package' => $index]) }}" target="_blank">
                                                        <i class="feather-file me-2"></i> Package {{ $index + 1 }}
                                                        <small class="text-muted ms-1">({{ $pkg['tracking_number'] }})</small>
                                                    </a>
                                                </li>
                                            @endforeach
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item" href="#" id="downloadAllLabelsLink">
                                                    <i class="feather-download-cloud me-2"></i> Download All Labels
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                {{-- Individual package checkboxes for selective printing --}}
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small class="text-muted d-block mb-2">Select packages to print:</small>
                                    @foreach($shippingPackages as $index => $pkg)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input package-label-check" type="checkbox" value="{{ $index }}" id="pkgCheck{{ $index }}" checked
                                                data-label-url="{{ route('orders.label', ['id' => $order->id, 'package' => $index]) }}">
                                            <label class="form-check-label small" for="pkgCheck{{ $index }}">
                                                Pkg {{ $index + 1 }}
                                            </label>
                                        </div>
                                    @endforeach
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="printSelectedLabelsBtn">
                                        <i class="feather-printer me-1"></i> Print Selected
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Single label --}}
                            <a href="{{ route('orders.label', $order->id) }}" class="btn btn-primary w-100 mb-2" target="_blank">
                                <i class="feather-download me-1"></i> Download Shipping Label
                            </a>
                        @endif
                        <button type="button" class="btn btn-warning w-100 mb-2" id="cancelLabelBtn"
                            data-order-id="{{ $order->id }}"
                            data-order-number="{{ $order->order_number }}"
                            data-tracking="{{ $order->tracking_number }}"
                            data-package-count="{{ count($shippingPackages) }}"
                            data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}">
                            <i class="feather-x-circle me-1"></i> Cancel Label(s) & Remove Tracking
                        </button>
                    @endif

                    {{-- @if($order->order_status !== 'cancelled')
                        <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="feather-x me-1"></i> Cancel Order
                        </button>
                    @endif --}}

                    <a href="{{ route('orders.index') }}" class="btn btn-light-brand w-100 mt-2">
                        <i class="feather-arrow-left me-1"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('modals')
    <!-- Ship Order Modal -->
    <div class="modal fade" id="shipModal" tabindex="-1" aria-labelledby="showShipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="showShipModalLabel">Ship Order &mdash; {{ $order->order_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- Order Summary -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-1">Customer</h6>
                            <p class="mb-0">{{ $order->buyer_name ?? 'N/A' }}</p>
                            @if($order->buyer_email)
                                <small class="text-muted">{{ $order->buyer_email }}</small>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-1">Ship To</h6>
                            <p class="mb-0 small">
                                {{ implode(', ', array_filter([
                                    $order->shipping_address_line1,
                                    $order->shipping_city,
                                    $order->shipping_state,
                                    $order->shipping_postal_code,
                                    $order->shipping_country,
                                ])) ?: 'No address on file' }}
                            </p>
                        </div>
                    </div>
                    @php
                        // Count only main items (bundles + regular products), exclude bundle components
                        $modalMainItems = $order->items->filter(fn($item) => !$item->bundle_product_id || $item->is_bundle_summary);
                    @endphp
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <small class="text-muted">Items</small>
                            <div>{{ $modalMainItems->count() }} item(s), Qty: {{ $modalMainItems->sum('quantity') }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Order Total</small>
                            <div class="fw-bold">{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</div>
                        </div>
                        @if($order->address_type && $order->address_validated_at)
                        <div class="col-md-4">
                            <small class="text-muted">Address Type</small>
                            <div>
                                @php
                                    $addrColors = ['BUSINESS'=>'primary','RESIDENTIAL'=>'success','MIXED'=>'warning','UNKNOWN'=>'secondary'];
                                @endphp
                                <span class="badge bg-soft-{{ $addrColors[$order->address_type] ?? 'secondary' }} text-{{ $addrColors[$order->address_type] ?? 'secondary' }}">{{ $order->address_type }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                    <hr class="my-2">

                    <!-- Order Items (Display Only) -->
                    <div class="card mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="feather-shopping-bag me-2"></i>Order Items</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size:12px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center" style="width:60px;">Qty</th>
                                            <th class="text-end" style="width:100px;">Price</th>
                                        </tr>
                                    </thead>
                                    @php
                                        $shippingItems = $order->items->filter(fn($item) => !$item->bundle_product_id || $item->is_bundle_summary);
                                    @endphp
                                    <tbody>
                                        @foreach($shippingItems as $item)
                                            <tr>
                                                <td>
                                                    <strong>{{ \Illuminate\Support\Str::limit($item->title, 40) }}</strong>
                                                    <span class="d-block text-muted fs-11">{{ $item->sku ?? 'N/A' }}</span>
                                                </td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">${{ number_format($item->unit_price ?? 0, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
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
                                    <select id="showWeightUnit" class="form-select form-select-sm">
                                        <option value="lbs" selected>Pounds (lbs)</option>
                                        <option value="kg">Kilograms (kg)</option>
                                        <option value="oz">Ounces (oz)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Dimension Unit</label>
                                    <select id="showDimensionUnit" class="form-select form-select-sm">
                                        <option value="in" selected>Inches (in)</option>
                                        <option value="cm">Centimeters (cm)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Number of Packages</label>
                                    <select id="showPackageCount" class="form-select form-select-sm">
                                        <option value="1" selected>1 Package</option>
                                        <option value="2">2 Packages</option>
                                        <option value="3">3 Packages</option>
                                        <option value="4">4 Packages</option>
                                        <option value="5">5 Packages</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Package Dimension Fields Container -->
                            <div id="showPackageDimensionsContainer">
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

                            <!-- Shipper info (shown after rates are fetched) -->
                            <div id="showShipperInfo" class="alert alert-light py-2 small mb-2" style="display:none;">
                                <i class="feather-home me-1 text-muted"></i>
                                <strong>Ship From:</strong> <span id="showShipperAddress"></span>
                            </div>

                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Carrier</label>
                                    <select id="showRateCarrierId" class="form-select form-select-sm">
                                        <option value="">-- Select carrier --</option>
                                        @foreach($shippingCarriers as $carrier)
                                            <option value="{{ $carrier->id }}" {{ $carrier->is_default ? 'selected' : '' }}>
                                                {{ $carrier->name }} ({{ strtoupper($carrier->type) }}){{ $carrier->is_default ? ' ★' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" id="showGetRatesBtn" class="btn btn-info btn-sm w-100">
                                        <i class="feather-search me-1"></i> Get Rates
                                    </button>
                                </div>
                            </div>

                            <!-- Rates Result -->
                            <div id="showRatesResult" class="mt-3" style="display:none;">
                                <div id="showRatesLoading" class="text-center py-2" style="display:none;">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Fetching rates...
                                </div>
                                <div id="showRatesError" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                                <div id="showRatesServiceWrap" style="display:none;">
                                    <label class="form-label small mb-1">Select Service</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-2" id="showRatesTable">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th style="width:40px;"></th>
                                                    <th>Service</th>
                                                    <th class="text-end" style="width:120px;">Cost</th>
                                                    <th class="text-center" style="width:100px;">Transit</th>
                                                </tr>
                                            </thead>
                                            <tbody id="showRatesTableBody"></tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-1"><i class="feather-info me-1"></i>Rates are estimates only. Actual cost may vary.</small>

                                    <!-- Generate Label Button -->
                                    <button type="button" id="showGenerateLabelBtn" class="btn btn-success w-100 mt-3" style="display:none;" disabled>
                                        <i class="feather-printer me-1"></i> Generate Label & Mark Shipped
                                    </button>

                                    <!-- Label Result (shown after label is generated) -->
                                    <div id="showLabelResult" class="alert alert-success mt-3" style="display:none;">
                                        <h6 class="mb-2"><i class="feather-check-circle me-1"></i> Label Generated Successfully!</h6>
                                        <p class="mb-2">Tracking Number(s): <strong id="showTrackingNumber"></strong></p>
                                        <div id="showDownloadLabelLinks">
                                            <a href="#" id="showDownloadLabelLink" class="btn btn-primary btn-sm" target="_blank">
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
                                <form id="shipForm" action="{{ route('orders.ship', $order->id) }}" method="POST">
                                    @csrf
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Shipping Carrier <span class="text-danger">*</span></label>
                                            <input type="text" name="shipping_carrier" id="showShipCarrierName" class="form-control form-control-sm" required placeholder="e.g., FedEx, UPS, USPS">
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

    <!-- Cancel Order Modal -->
    {{-- <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Order &mdash; {{ $order->order_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="cancelOrderForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="feather-alert-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone. The order will be cancelled and inventory will be restored.
                        </div>

                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="cancelReason" name="reason" required>
                                <option value="">Select a reason...</option>
                                <option value="Out of stock">Out of stock</option>
                                <option value="Buyer requested cancellation">Buyer requested cancellation</option>
                                <option value="Address issue">Address issue</option>
                                <option value="Shipping issue">Shipping issue</option>
                                <option value="Pricing error">Pricing error</option>
                                <option value="Item damaged">Item damaged</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3" id="otherReasonContainer" style="display: none;">
                            <label for="otherReason" class="form-label">Please specify</label>
                            <textarea class="form-control" id="otherReason" name="other_reason" rows="3" placeholder="Enter cancellation reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="feather-x me-1"></i> Cancel Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div> --}}
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {

            // ----------------------------------------------------------------
            // Unit label updates when unit selectors change
            // ----------------------------------------------------------------
            $('#showWeightUnit').on('change', function() {
                var unit = $(this).val();
                $('#weightUnitLabel').text('(' + unit + ')');
            });

            $('#showDimensionUnit').on('change', function() {
                var unit = $(this).val();
                $('#dimUnitLabelL, #dimUnitLabelW, #dimUnitLabelH').text('(' + unit + ')');
            });

            // ----------------------------------------------------------------
            // Get Rates — collect package dimensions, populate service dropdown
            // ----------------------------------------------------------------
            $('#showGetRatesBtn').on('click', function() {
                var carrierId = $('#showRateCarrierId').val();
                if (!carrierId) {
                    alert('Please select a carrier first.');
                    return;
                }

                // Collect package dimensions
                var packages = [];
                $('.show-package-dim-row').each(function() {
                    var declared_value = '';
                    if ($(this).find('.liability-checkbox').is(':checked')) {
                        declared_value = parseFloat($(this).find('.declared-value').val());
                    }
                    packages.push({
                        weight: parseFloat($(this).find('.show-pkg-weight').val()) || 1,
                        length: parseFloat($(this).find('.show-pkg-length').val()) || 12,
                        width:  parseFloat($(this).find('.show-pkg-width').val()) || 12,
                        height: parseFloat($(this).find('.show-pkg-height').val()) || 12,
                        customer_reference: $(this).find('.show-pkg-reference').val() || '', 
                        declared_value: declared_value, 
                    });
                });

                if (packages.length === 0) {
                    alert('Please enter package dimensions.');
                    return;
                }

                $('#showRatesResult').show();
                $('#showRatesLoading').show();
                $('#showRatesError').hide().text('');
                $('#showRatesServiceWrap').hide();
                $('#showRatesTableBody').empty();
                $('#showShipperInfo').hide();

                // Get selected units
                var weightUnit = $('#showWeightUnit').val();
                var dimensionUnit = $('#showDimensionUnit').val();

                $.ajax({
                    url: '{{ route('orders.shipping-rates') }}',
                    type: 'POST',
                    data: {
                        _token:        '{{ csrf_token() }}',
                        order_id:      {{ $order->id }},
                        carrier_id:    carrierId,
                        packages:      packages,
                        weight_unit:   weightUnit,
                        dimension_unit: dimensionUnit
                    },
                    success: function(response) {
                        $('#showRatesLoading').hide();
                        if (!response.success) {
                            $('#showRatesError').text(response.message || 'Failed to fetch rates.').show();
                            return;
                        }
                        var rates = response.rates || [];
                        if (rates.length === 0) {
                            $('#showRatesError').text('No rates returned. Check carrier credentials and address.').show();
                            return;
                        }

                        // Show shipper address if available
                        if (response.shipper) {
                            $('#showShipperAddress').text(response.shipper);
                            $('#showShipperInfo').show();
                        }

                        // Populate rates table with radio buttons
                        $('#showRatesTableBody').empty();
                        $.each(rates, function(i, rate) {
                            var cost = rate.amount !== null
                                ? rate.currency + ' ' + parseFloat(rate.amount).toFixed(2)
                                : 'N/A';
                            var transit = rate.transit_days || '-';
                            var row = '<tr class="show-rate-row" style="cursor:pointer;">' +
                                '<td class="text-center align-middle">' +
                                    '<input type="radio" name="showServiceRadio" value="' + rate.service_code + '"' +
                                    ' data-amount="' + (rate.amount || '') + '"' +
                                    ' data-currency="' + (rate.currency || 'USD') + '"' +
                                    ' data-transit="' + (rate.transit_days || '') + '"' +
                                    ' data-name="' + rate.service_name + '">' +
                                '</td>' +
                                '<td>' + rate.service_name + '</td>' +
                                '<td class="text-right">' + cost + '</td>' +
                                '<td class="text-center">' + transit + '</td>' +
                                '</tr>';
                            $('#showRatesTableBody').append(row);
                        });
                        $('#showRatesServiceWrap').show();
                        $('#showGenerateLabelBtn').hide().prop('disabled', true);
                        $('#showLabelResult').hide();

                        // Auto-select default service if available
                        if (response.default_service) {
                            var $defaultRadio = $('input[name="showServiceRadio"][value="' + response.default_service + '"]');
                            if ($defaultRadio.length) {
                                $defaultRadio.prop('checked', true).trigger('change');
                            }
                        }

                        // Auto-fill carrier name in manual ship form
                        var carrierLabel = $('#showRateCarrierId option:selected').text()
                            .replace(' ★', '').replace(/\s*\(.*\)$/, '').trim();
                        $('#showShipCarrierName').val(carrierLabel);
                    },
                    error: function(xhr) {
                        $('#showRatesLoading').hide();
                        var msg = xhr.responseJSON?.message || 'Failed to fetch rates.';
                        $('#showRatesError').text(msg).show();
                    }
                });
            });

            // ----------------------------------------------------------------
            // Service selection — clicking row selects radio, enable Generate Label
            // ----------------------------------------------------------------
            $(document).on('click', '.show-rate-row', function() {
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });

            $(document).on('change', 'input[name="showServiceRadio"]', function() {
                if ($(this).is(':checked')) {
                    $('#showGenerateLabelBtn').show().prop('disabled', false);
                }
            });

            // ----------------------------------------------------------------
            // Package Count Change Handler
            // ----------------------------------------------------------------
            $('#showPackageCount').on('change', function() {
                updateShowPackageDimensionFields();
            });

            function updateShowPackageDimensionFields() {
                var count = parseInt($('#showPackageCount').val()) || 1;
                var weightUnit = $('#showWeightUnit').val() || 'lbs';
                var dimUnit = $('#showDimensionUnit').val() || 'in';
                var $container = $('#showPackageDimensionsContainer');
                $container.empty();

                for (var i = 0; i < count; i++) {
                    var pkgLabel = count > 1 ? 'Package ' + (i + 1) : 'Package';
                    var html = `
                        <div class="border rounded p-2 mb-2 bg-white show-package-dim-row" data-package="${i}">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-primary">${pkgLabel}</span>
                                <small class="text-muted">Enter weight and box dimensions</small>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-3">
                                    <label class="form-label small mb-1">Weight (${weightUnit}) *</label>
                                    <input type="number" step="0.1" min="0.1" class="form-control form-control-sm show-pkg-weight" data-pkg="${i}" value="1" required>
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">L (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm show-pkg-length" data-pkg="${i}" value="12">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">W (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm show-pkg-width" data-pkg="${i}" value="12">
                                </div>
                                <div class="col-3">
                                    <label class="form-label small mb-1">H (${dimUnit})</label>
                                    <input type="number" step="0.1" min="1" class="form-control form-control-sm show-pkg-height" data-pkg="${i}" value="12">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Customer Reference <small class="text-muted">(max 30 chars, shown on label)</small></label>
                                    <input type="text" class="form-control form-control-sm show-pkg-reference" data-pkg="${i}" maxlength="30" placeholder="Auto-generated from item names if empty">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input liability-checkbox" id="show-package-${i}" data-pkg="${i}">
                                        <label class="form-check-label" for="show-package-${i}">Purchase a higher limit of liability from FedEx</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Declared Value ($)</label>
                                    <input type="number" id="show-package-${i}-input" class="form-control form-control-sm declared-value" data-pkg="${i}" step="0.01" min="0" placeholder="Enter declared value" disabled>
                                </div>
                            </div>
                        </div>
                    `;
                    $container.append(html);
                }
            }

            // Update package dimension labels when units change
            $('#showWeightUnit, #showDimensionUnit').on('change', function() {
                updateShowPackageDimensionFields();
            });

            // Toggle declared value input when liability checkbox changes
            $(document).on('change', '.liability-checkbox', function() {
                if ($(this).is(':checked')) {
                    $('#' + $(this).attr('id') + '-input').removeAttr('disabled');
                } else {
                    $('#' + $(this).attr('id') + '-input').attr('disabled', 'disabled').val('');
                }
            });

            // Initialize package dimension fields when modal opens
            $('#shipModal').on('shown.bs.modal', function() {
                updateShowPackageDimensionFields();
            });

            // ----------------------------------------------------------------
            // Generate Label button click
            // ----------------------------------------------------------------
            $('#showGenerateLabelBtn').on('click', function() {
                var $radio = $('input[name="showServiceRadio"]:checked');
                if (!$radio.length) {
                    alert('Please select a service first.');
                    return;
                }

                var carrierId   = $('#showRateCarrierId').val();
                var serviceCode = $radio.val();
                var packageCount = parseInt($('#showPackageCount').val()) || 1;

                // Get selected units
                var weightUnit = $('#showWeightUnit').val();
                var dimensionUnit = $('#showDimensionUnit').val();

                // Collect package data
                var packages = [];
                $('.show-package-dim-row').each(function() {
                    var declared_value = '';
                    if ($(this).find('.liability-checkbox').is(':checked')) {
                        declared_value = parseFloat($(this).find('.declared-value').val());
                    }
                    packages.push({
                        weight: parseFloat($(this).find('.show-pkg-weight').val()) || 1,
                        length: parseFloat($(this).find('.show-pkg-length').val()) || 12,
                        width:  parseFloat($(this).find('.show-pkg-width').val()) || 12,
                        height: parseFloat($(this).find('.show-pkg-height').val()) || 12,
                        customer_reference: $(this).find('.show-pkg-reference').val() || '',
                        declared_value: declared_value
                    });
                });

                var $btn = $(this);
                var labelText = packageCount > 1 ? packageCount + ' Labels' : 'Label';
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating ' + labelText + '...');

                $.ajax({
                    url: '{{ route('orders.generate-multi-labels', $order->id) }}',
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
                            $('#showTrackingNumber').text(trackingText);

                            // Create download links for all packages
                            var $linksContainer = $('#showDownloadLabelLinks');
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

                            $('#showLabelResult').show();
                            $btn.hide();

                            // Reload after 4 seconds to update order status
                            setTimeout(function() {
                                location.reload();
                            }, 4000);
                        } else {
                            alert(response.message || 'Failed to generate labels.');
                            $btn.prop('disabled', false).html('<i class="feather-printer me-1"></i> Generate Label(s) & Mark Shipped');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Failed to generate label.';
                        alert(msg);
                        $btn.prop('disabled', false).html('<i class="feather-printer me-1"></i> Generate Label(s) & Mark Shipped');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Cancel order - Show/hide other reason field
            // ----------------------------------------------------------------
            // $('#cancelReason').on('change', function() {
            //     if ($(this).val() === 'Other') {
            //         $('#otherReasonContainer').show();
            //         $('#otherReason').prop('required', true);
            //     } else {
            //         $('#otherReasonContainer').hide();
            //         $('#otherReason').prop('required', false);
            //     }
            // });

            // ----------------------------------------------------------------
            // Cancel shipping label
            // ----------------------------------------------------------------
            // $('#cancelLabelBtn').on('click', function() {
            //     var $btn = $(this);
            //     var orderId = $btn.data('order-id');
            //     var orderNumber = $btn.data('order-number');
            //     var trackingNumber = $btn.data('tracking');
            //     var packageCount = parseInt($btn.data('package-count')) || 1;
            //     var isEbay = $btn.data('is-ebay') === '1';

            //     var labelText = packageCount > 1 ? packageCount + ' shipping labels' : 'the shipping label';
            //     var confirmMsg = 'Are you sure you want to cancel ' + labelText + ' for order ' + orderNumber + '?\n\n';
            //     if (packageCount > 1) {
            //         confirmMsg += 'This order has ' + packageCount + ' packages/tracking numbers.\n\n';
            //     } else {
            //         confirmMsg += 'Tracking #: ' + trackingNumber + '\n\n';
            //     }
            //     confirmMsg += 'This will:\n';
            //     confirmMsg += '- Void ' + (packageCount > 1 ? 'all labels' : 'the label') + ' with FedEx\n';
            //     confirmMsg += '- Remove all tracking information from the order\n';
            //     confirmMsg += '- Restore inventory\n';
            //     confirmMsg += '- Revert order status to Processing\n';
            //     if (isEbay) {
            //         confirmMsg += '- Remove tracking from eBay\n';
            //     }

            //     if (!confirm(confirmMsg)) {
            //         return;
            //     }

            //     $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Cancelling Label...');

            //     $.ajax({
            //         url: '/orders/' + orderId + '/cancel-label',
            //         type: 'POST',
            //         data: {
            //             _token: '{{ csrf_token() }}'
            //         },
            //         success: function(response) {
            //             if (response.success) {
            //                 var message = response.message;
            //                 if (response.ebay_sync) {
            //                     if (response.ebay_sync.success) {
            //                         message += '\n\n✓ Successfully removed tracking from eBay';
            //                     } else {
            //                         message += '\n\n⚠ Warning: Failed to remove tracking from eBay';
            //                         if (response.ebay_sync.message) {
            //                             message += '\nReason: ' + response.ebay_sync.message;
            //                         }
            //                     }
            //                 }
            //                 alert(message);
            //                 location.reload();
            //             } else {
            //                 alert(response.message || 'Failed to cancel label');
            //                 $btn.prop('disabled', false).html('<i class="feather-x-circle me-1"></i> Cancel Label & Remove Tracking');
            //             }
            //         },
            //         error: function(xhr) {
            //             alert('Failed to cancel label: ' + (xhr.responseJSON?.message || 'Unknown error'));
            //             $btn.prop('disabled', false).html('<i class="feather-x-circle me-1"></i> Cancel Label & Remove Tracking');
            //         }
            //     });
            // });

            // ----------------------------------------------------------------
            // Cancel order form submit
            // ----------------------------------------------------------------
            // $('#cancelOrderForm').on('submit', function(e) {
            //     e.preventDefault();

            //     var reason = $('#cancelReason').val();
            //     if (reason === 'Other') {
            //         reason = $('#otherReason').val();
            //     }

            //     if (!reason) {
            //         alert('Please select or enter a cancellation reason');
            //         return;
            //     }

            //     var $submitBtn = $(this).find('button[type="submit"]');
            //     $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Cancelling...');

            //     $.ajax({
            //         url: '{{ route('orders.cancel', $order->id) }}',
            //         type: 'POST',
            //         data: {
            //             _token: '{{ csrf_token() }}',
            //             reason: reason
            //         },
            //         success: function(response) {
            //             if (response.success) {
            //                 // Close the modal
            //                 $('#cancelModal').modal('hide');

            //                 // Show success message with eBay sync status
            //                 var message = response.message;
            //                 if (response.ebay_sync) {
            //                     if (response.ebay_sync.success) {
            //                         message += '\n\n✓ Successfully synced to eBay';
            //                     } else {
            //                         message += '\n\n⚠ Warning: eBay sync failed';
            //                         if (response.ebay_sync.message) {
            //                             message += '\nReason: ' + response.ebay_sync.message;
            //                         }
            //                         message += '\n\nNote: The order has been cancelled locally. You may need to manually cancel it on eBay Seller Hub.';
            //                     }
            //                 }

            //                 alert(message);
            //                 location.reload();
            //             } else {
            //                 alert(response.message || 'Failed to cancel order');
            //                 $submitBtn.prop('disabled', false).html('<i class="feather-x me-1"></i> Cancel Order');
            //             }
            //         },
            //         error: function(xhr) {
            //             alert('Failed to cancel order: ' + (xhr.responseJSON?.message || 'Unknown error'));
            //             $submitBtn.prop('disabled', false).html('<i class="feather-x me-1"></i> Cancel Order');
            //         }
            //     });
            // });

            // ----------------------------------------------------------------
            // Ship form submit via AJAX
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
            // Multi-package label handling
            // ----------------------------------------------------------------

            // Open all labels in new tabs for printing
            $('#openAllLabelsBtn').on('click', function() {
                var labelUrls = [];
                $('.package-label-check').each(function() {
                    labelUrls.push($(this).data('label-url'));
                });

                if (labelUrls.length === 0) {
                    alert('No labels found.');
                    return;
                }

                // Open each label in a new tab with a small delay to prevent popup blocking
                labelUrls.forEach(function(url, index) {
                    setTimeout(function() {
                        window.open(url, '_blank');
                    }, index * 300); // 300ms delay between each
                });
            });

            // Print selected labels only
            $('#printSelectedLabelsBtn').on('click', function() {
                var selectedUrls = [];
                $('.package-label-check:checked').each(function() {
                    selectedUrls.push($(this).data('label-url'));
                });

                if (selectedUrls.length === 0) {
                    alert('Please select at least one package to print.');
                    return;
                }

                // Open selected labels in new tabs
                selectedUrls.forEach(function(url, index) {
                    setTimeout(function() {
                        window.open(url, '_blank');
                    }, index * 300);
                });
            });

            // Download all labels (opens all in new tabs for saving)
            $('#downloadAllLabelsLink').on('click', function(e) {
                e.preventDefault();
                var labelUrls = [];
                $('.package-label-check').each(function() {
                    labelUrls.push($(this).data('label-url'));
                });

                if (labelUrls.length === 0) {
                    alert('No labels found.');
                    return;
                }

                // Open each label in a new tab
                labelUrls.forEach(function(url, index) {
                    setTimeout(function() {
                        window.open(url, '_blank');
                    }, index * 300);
                });
            });

        });
    </script>
@endpush
