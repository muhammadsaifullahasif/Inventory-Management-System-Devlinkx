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
                                    <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d M, Y H:i') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Paid At:</td>
                                    <td>{{ $order->paid_at ? \Carbon\Carbon::parse($order->paid_at)->format('d M, Y H:i') : 'N/A' }}</td>
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
                                    <td>{{ \Carbon\Carbon::parse($order->shipped_at)->format('d M, Y H:i') }}</td>
                                </tr>
                                @endif
                                @if($order->tracking_number)
                                <tr>
                                    <td class="text-muted">Tracking:</td>
                                    <td>
                                        <strong>{{ $order->shipping_carrier }}</strong>:
                                        @if($order->tracking_url)
                                            <a href="{{ $order->tracking_url }}" target="_blank" class="text-primary">
                                                {{ $order->tracking_number }} <i class="feather-external-link fs-10"></i>
                                            </a>
                                        @else
                                            {{ $order->tracking_number }}
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
                                            {{ \Carbon\Carbon::parse($order->delivered_at)->format('d M, Y H:i') }}
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
                                    <th style="width: 50px;">#</th>
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
                                        <td>{{ $index + 1 }}</td>
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

                    @if($order->order_status !== 'cancelled')
                        <button type="button" class="btn btn-danger w-100" id="cancelOrderBtn">
                            <i class="feather-x me-1"></i> Cancel Order
                        </button>
                    @endif

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
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <small class="text-muted">Items</small>
                            <div>{{ $order->items->count() }} item(s), Qty: {{ $order->items->sum('quantity') }}</div>
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

                    <!-- Items & Dimensions (server-rendered — product_meta already loaded) -->
                    <div class="card mb-3">
                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                            <h6 class="card-title mb-0"><i class="feather-package me-2"></i>Items & Dimensions</h6>
                            <small class="text-muted">Edit weight/dims to override product defaults</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size:12px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center" style="width:40px;">Qty</th>
                                            <th class="text-center" style="width:90px;">Weight</th>
                                            <th class="text-center" style="width:90px;">L</th>
                                            <th class="text-center" style="width:90px;">W</th>
                                            <th class="text-center" style="width:90px;">H</th>
                                        </tr>
                                    </thead>
                                    <tbody id="showItemsDimTbody">
                                        @foreach($order->items as $item)
                                            @php
                                                $meta   = $item->product?->product_meta ?? [];
                                                $weight = (float) ($meta['weight'] ?? 0);
                                                $length = (float) ($meta['length'] ?? 0);
                                                $width  = (float) ($meta['width']  ?? 0);
                                                $height = (float) ($meta['height'] ?? 0);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ \Illuminate\Support\Str::limit($item->title, 40) }}</strong>
                                                    <span class="d-block text-muted fs-11">{{ $item->sku ?? 'N/A' }}</span>
                                                </td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td><input type="number" class="form-control form-control-sm show-dim-input"
                                                    data-item-id="{{ $item->id }}" data-dim="weight"
                                                    value="{{ $weight }}" step="0.01" min="0" placeholder="0"></td>
                                                <td><input type="number" class="form-control form-control-sm show-dim-input"
                                                    data-item-id="{{ $item->id }}" data-dim="length"
                                                    value="{{ $length }}" step="0.1" min="0" placeholder="0"></td>
                                                <td><input type="number" class="form-control form-control-sm show-dim-input"
                                                    data-item-id="{{ $item->id }}" data-dim="width"
                                                    value="{{ $width }}" step="0.1" min="0" placeholder="0"></td>
                                                <td><input type="number" class="form-control form-control-sm show-dim-input"
                                                    data-item-id="{{ $item->id }}" data-dim="height"
                                                    value="{{ $height }}" step="0.1" min="0" placeholder="0"></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
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
                                        <p class="mb-2">Tracking Number: <strong id="showTrackingNumber"></strong></p>
                                        <a href="#" id="showDownloadLabelLink" class="btn btn-primary btn-sm" target="_blank">
                                            <i class="feather-download me-1"></i> Download Label
                                        </a>
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
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {

            // ----------------------------------------------------------------
            // Get Rates — collect dimension overrides, populate service dropdown
            // ----------------------------------------------------------------
            $('#showGetRatesBtn').on('click', function() {
                var carrierId = $('#showRateCarrierId').val();
                if (!carrierId) {
                    alert('Please select a carrier first.');
                    return;
                }

                // Collect dimension overrides
                var itemOverrides = [];
                $('#showItemsDimTbody tr').each(function() {
                    var itemId = $(this).find('.show-dim-input').first().data('item-id');
                    var entry  = { order_item_id: itemId };
                    $(this).find('.show-dim-input').each(function() {
                        entry[$(this).data('dim')] = parseFloat($(this).val()) || 0;
                    });
                    itemOverrides.push(entry);
                });

                $('#showRatesResult').show();
                $('#showRatesLoading').show();
                $('#showRatesError').hide().text('');
                $('#showRatesServiceWrap').hide();
                $('#showRatesTableBody').empty();
                $('#showShipperInfo').hide();

                $.ajax({
                    url: '{{ route('orders.shipping-rates') }}',
                    type: 'POST',
                    data: {
                        _token:     '{{ csrf_token() }}',
                        order_id:   {{ $order->id }},
                        carrier_id: carrierId,
                        items:      itemOverrides
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

                // Collect dimension overrides
                var itemOverrides = [];
                $('#showItemsDimTbody tr').each(function() {
                    var itemId = $(this).find('.show-dim-input').first().data('item-id');
                    var entry  = { order_item_id: itemId };
                    $(this).find('.show-dim-input').each(function() {
                        entry[$(this).data('dim')] = parseFloat($(this).val()) || 0;
                    });
                    itemOverrides.push(entry);
                });

                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating Label...');

                $.ajax({
                    url: '{{ route('orders.generate-label', $order->id) }}',
                    type: 'POST',
                    data: {
                        _token:       '{{ csrf_token() }}',
                        carrier_id:   carrierId,
                        service_code: serviceCode,
                        items:        itemOverrides
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#showTrackingNumber').text(response.tracking_number);
                            $('#showDownloadLabelLink').attr('href', response.label_url);
                            $('#showLabelResult').show();
                            $btn.hide();

                            // Reload after 3 seconds to update order status
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            alert(response.message || 'Failed to generate label.');
                            $btn.prop('disabled', false).html('<i class="fas fa-print mr-1"></i> Generate Label & Mark Shipped');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON?.message || 'Failed to generate label.';
                        alert(msg);
                        $btn.prop('disabled', false).html('<i class="fas fa-print mr-1"></i> Generate Label & Mark Shipped');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Cancel order
            // ----------------------------------------------------------------
            $('#cancelOrderBtn').on('click', function() {
                if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                    $.ajax({
                        url: '{{ route('orders.cancel', $order->id) }}',
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

        });
    </script>
@endpush
