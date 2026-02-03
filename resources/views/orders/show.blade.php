@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Order Details</h1>
                    <span class="badge badge-{{ $order->order_status === 'cancelled' ? 'danger' : 'primary' }} ml-2">
                        {{ strtoupper($order->order_status ?? 'N/A') }}
                    </span>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                        <li class="breadcrumb-item active">{{ $order->order_number }}</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="row">
        <!-- Order Summary -->
        <div class="col-md-8">
            <!-- Order Info Card -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Order Information</h3>
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
                                    <td><code>{{ $order->ebay_order_id }}</code></td>
                                </tr>
                                @endif
                                <tr>
                                    <td class="text-muted">Sales Channel:</td>
                                    <td>
                                        @if($order->salesChannel)
                                            <span class="badge badge-info">{{ $order->salesChannel->name }}</span>
                                        @else
                                            <span class="badge badge-secondary">N/A</span>
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
                                        <span class="badge badge-{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $order->order_status ?? 'N/A')) }}</span>
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
                                        <span class="badge badge-{{ $paymentColor }}">{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'N/A')) }}</span>
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
                                        <span class="badge badge-{{ $fulfillmentColor }}">{{ ucfirst(str_replace('_', ' ', $order->fulfillment_status ?? 'N/A')) }}</span>
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
                                        <strong>{{ $order->shipping_carrier }}</strong>: {{ $order->tracking_number }}
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Card -->
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-shopping-cart mr-2"></i>Order Items ({{ $order->items->count() }})</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Item</th>
                                <th>SKU</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->title }}</strong>
                                        @if($item->variation_attributes)
                                            <br>
                                            <small class="text-muted">
                                                @foreach($item->variation_attributes as $key => $value)
                                                    {{ $key }}: {{ $value }}{{ !$loop->last ? ', ' : '' }}
                                                @endforeach
                                            </small>
                                        @endif
                                        @if($item->ebay_item_id)
                                            <br><small class="text-muted">eBay Item: {{ $item->ebay_item_id }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $item->sku ?? 'N/A' }}
                                        @if($item->product)
                                            <br><a href="{{ route('products.show', $item->product_id) }}" class="text-primary"><small>View Product</small></a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-right">{{ $item->currency ?? 'USD' }} {{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-right">{{ $item->currency ?? 'USD' }} {{ number_format($item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                                <td class="text-right">{{ $order->currency ?? 'USD' }} {{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Shipping:</strong></td>
                                <td class="text-right">{{ $order->currency ?? 'USD' }} {{ number_format($order->shipping_cost, 2) }}</td>
                            </tr>
                            @if($order->tax > 0)
                            <tr>
                                <td colspan="5" class="text-right"><strong>Tax:</strong></td>
                                <td class="text-right">{{ $order->currency ?? 'USD' }} {{ number_format($order->tax, 2) }}</td>
                            </tr>
                            @endif
                            @if($order->discount > 0)
                            <tr>
                                <td colspan="5" class="text-right"><strong>Discount:</strong></td>
                                <td class="text-right text-danger">-{{ $order->currency ?? 'USD' }} {{ number_format($order->discount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Order Meta Data -->
            @if(count($metaData) > 0)
            <div class="card card-outline card-secondary collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-database mr-2"></i>Additional Details</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        @if(isset($metaData['shipping_address']))
                        <div class="col-md-6">
                            <h6><strong>Shipping Address (Meta)</strong></h6>
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
                        <div class="col-md-6">
                            <h6><strong>Shipping Service</strong></h6>
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
                        <div class="col-md-6">
                            <h6><strong>Transaction Status</strong></h6>
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
                        <div class="col-md-6">
                            <h6><strong>Payment Details</strong></h6>
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
                        <div class="col-md-12">
                            <h6><strong>Tax Details</strong></h6>
                            <pre class="bg-light p-2" style="font-size: 11px; max-height: 200px; overflow: auto;">{{ json_encode($metaData['tax_details'], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                        @endif

                        @if(isset($metaData['seller_info']))
                        <div class="col-md-6">
                            <h6><strong>Seller Info</strong></h6>
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
            @endif
        </div>

        <!-- Customer & Shipping Sidebar -->
        <div class="col-md-4">
            <!-- Customer Card -->
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user mr-2"></i>Customer</h3>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $order->buyer_name ?? 'N/A' }}</strong></p>
                    @if($order->buyer_username)
                        <p class="mb-1 text-muted"><small>Username: {{ $order->buyer_username }}</small></p>
                    @endif
                    @if($order->buyer_email)
                        <p class="mb-1"><i class="fas fa-envelope mr-1"></i> <a href="mailto:{{ $order->buyer_email }}">{{ $order->buyer_email }}</a></p>
                    @endif
                    @if($order->buyer_phone)
                        <p class="mb-0"><i class="fas fa-phone mr-1"></i> {{ $order->buyer_phone }}</p>
                    @endif
                </div>
            </div>

            <!-- Shipping Address Card -->
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i>Shipping Address</h3>
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
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-comment mr-2"></i>Buyer Message</h3>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $order->buyer_checkout_message }}</p>
                </div>
            </div>
            @endif

            <!-- Actions Card -->
            <div class="card card-outline card-dark">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs mr-2"></i>Actions</h3>
                </div>
                <div class="card-body">
                    @if($order->fulfillment_status !== 'fulfilled' && $order->order_status !== 'cancelled')
                        <button type="button" class="btn btn-primary btn-block mb-2" data-toggle="modal" data-target="#shipModal">
                            <i class="fas fa-shipping-fast mr-1"></i> Mark as Shipped
                        </button>
                    @endif

                    @if($order->order_status !== 'cancelled')
                        <button type="button" class="btn btn-danger btn-block" id="cancelOrderBtn">
                            <i class="fas fa-times mr-1"></i> Cancel Order
                        </button>
                    @endif

                    <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-block mt-2">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Ship Order Modal -->
    <div class="modal fade" id="shipModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Order as Shipped</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="shipForm" action="{{ route('orders.ship', $order->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Shipping Carrier <span class="text-danger">*</span></label>
                            <input type="text" name="shipping_carrier" class="form-control" required placeholder="e.g., UPS, FedEx, USPS">
                        </div>
                        <div class="form-group">
                            <label>Tracking Number <span class="text-danger">*</span></label>
                            <input type="text" name="tracking_number" class="form-control" required placeholder="Enter tracking number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as Shipped</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Cancel order
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

            // Ship form submit via AJAX
            $('#shipForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#shipModal').modal('hide');
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
