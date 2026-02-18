@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Orders</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Orders</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Filters -->
    <div class="card card-outline card-primary mb-3">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('orders.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, Email, Name..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Sales Channel</label>
                            <select name="sales_channel_id" class="form-control form-control-sm">
                                <option value="">All Channels</option>
                                @foreach($salesChannels as $channel)
                                    <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="order_status" class="form-control form-control-sm">
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
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Order #</th>
                            <th>Channel</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Address Type</th>
                            <th>Order Date</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td>{{ $order->id }}</td>
                                <td>
                                    <a href="{{ route('orders.show', $order->id) }}">
                                        {{ $order->order_number }}
                                    </a>
                                    @if($order->ebay_order_id)
                                        <br><small class="text-muted">eBay: {{ \Illuminate\Support\Str::limit($order->ebay_order_id, 20) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($order->salesChannel)
                                        <span class="badge badge-info">{{ $order->salesChannel->name }}</span>
                                    @else
                                        <span class="badge badge-secondary">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $order->buyer_name ?? 'N/A' }}</strong>
                                    @if($order->buyer_email)
                                        <br><small class="text-muted">{{ $order->buyer_email }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $order->items->count() }} item(s)
                                    <br><small class="text-muted">Qty: {{ $order->items->sum('quantity') }}</small>
                                </td>
                                <td>
                                    <strong>{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</strong>
                                </td>
                                <td>
                                    @php
                                        // Consolidated status: single badge based on payment + fulfillment
                                        $isPaid       = in_array($order->payment_status, ['paid']);
                                        $isShipped    = in_array($order->fulfillment_status, ['fulfilled', 'partially_fulfilled'])
                                                        || in_array($order->order_status, ['shipped', 'delivered', 'ready_for_pickup']);
                                        $isCancelled  = in_array($order->order_status, ['cancelled', 'cancellation_requested']);
                                        $isRefunded   = $order->order_status === 'refunded' || $order->payment_status === 'refunded';

                                        if ($isRefunded) {
                                            $statusLabel = 'Refunded';
                                            $statusColor = 'secondary';
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
                                    <span class="badge badge-{{ $statusColor }}">{{ $statusLabel }}</span>
                                </td>
                                <td>
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
                                        <span class="badge badge-{{ $addrColor }}">{{ $addrType }}</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->order_date)
                                        {{ \Carbon\Carbon::parse($order->order_date)->format('d M, Y') }}
                                        <br><small class="text-muted">{{ \Carbon\Carbon::parse($order->order_date)->format('H:i') }}</small>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-success btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($order->fulfillment_status !== 'fulfilled' && $order->order_status !== 'cancelled')
                                            <button type="button" class="btn btn-primary btn-sm ship-btn"
                                                data-id="{{ $order->id }}"
                                                data-order-number="{{ $order->order_number }}"
                                                data-customer="{{ $order->buyer_name ?? 'N/A' }}"
                                                data-email="{{ $order->buyer_email ?? '' }}"
                                                data-address="{{ implode(', ', array_filter([$order->shipping_address_line1, $order->shipping_city, $order->shipping_state, $order->shipping_postal_code, $order->shipping_country])) }}"
                                                data-items="{{ $order->items->count() }} item(s), Qty: {{ $order->items->sum('quantity') }}"
                                                data-total="{{ ($order->currency ?? 'USD') . ' ' . number_format($order->total, 2) }}"
                                                title="Mark as Shipped">
                                                <i class="fas fa-shipping-fast"></i>
                                            </button>
                                        @endif
                                        @if($order->order_status !== 'cancelled')
                                            <button type="button" class="btn btn-danger btn-sm cancel-btn" data-id="{{ $order->id }}" title="Cancel Order">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">No orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <!-- Ship Order Modal -->
    <div class="modal fade" id="shipModal" tabindex="-1" role="dialog" aria-labelledby="shipModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shipModalLabel">Ship Order</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <!-- Order Details (populated via JS) -->
                    <div id="shipOrderDetails" class="mb-3" style="display:none;">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="font-weight-bold mb-1">Customer</h6>
                                <p class="mb-0" id="shipCustomerName"></p>
                                <small class="text-muted" id="shipCustomerEmail"></small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="font-weight-bold mb-1">Ship To</h6>
                                <p class="mb-0 small" id="shipAddress"></p>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Order #</small>
                                <div id="shipOrderNumber" class="font-weight-bold"></div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Items</small>
                                <div id="shipItemCount"></div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Order Total</small>
                                <div id="shipOrderTotal" class="font-weight-bold"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Items & Dimensions -->
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="fas fa-boxes mr-1"></i> Items &amp; Dimensions</h6>
                            <div class="card-tools">
                                <small class="text-muted">Edit weight/dims to override product defaults</small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="itemsLoading" class="text-center py-3">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Loading items...
                            </div>
                            <div id="itemsTableWrap" style="display:none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center" style="width:40px;">Qty</th>
                                                <th class="text-center" style="width:90px;">Weight</th>
                                                <th class="text-center" style="width:90px;">L</th>
                                                <th class="text-center" style="width:90px;">W</th>
                                                <th class="text-center" style="width:90px;">H</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsDimTbody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="itemsLoadError" class="alert alert-warning py-2 small mx-2 my-2 mb-0" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- Rate Quote Section -->
                    <div class="card card-outline card-info mb-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="fas fa-dollar-sign mr-1"></i> Get Shipping Rates</h6>
                        </div>
                        <div class="card-body py-2">
                            <div class="form-row align-items-end">
                                <div class="col-md-8">
                                    <label class="small mb-1">Carrier</label>
                                    <select id="rateCarrierId" class="form-control form-control-sm">
                                        <option value="">-- Select carrier --</option>
                                        @foreach($shippingCarriers as $carrier)
                                            <option value="{{ $carrier->id }}" {{ $carrier->is_default ? 'selected' : '' }}>
                                                {{ $carrier->name }} ({{ strtoupper($carrier->type) }}){{ $carrier->is_default ? ' ★' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" id="getRatesBtn" class="btn btn-info btn-sm btn-block mt-1">
                                        <i class="fas fa-search-dollar mr-1"></i> Get Rates
                                    </button>
                                </div>
                            </div>

                            <!-- Rates Result -->
                            <div id="ratesResult" class="mt-3" style="display:none;">
                                <div id="ratesLoading" class="text-center py-2" style="display:none;">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Fetching rates...
                                </div>
                                <div id="ratesError" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                                <div id="ratesTable" style="display:none;">
                                    <table class="table table-sm table-bordered mb-0 mt-2">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Service</th>
                                                <th class="text-right">Est. Cost</th>
                                                <th class="text-center">Transit</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ratesTbody"></tbody>
                                    </table>
                                    <small class="text-muted d-block mt-1"><i class="fas fa-info-circle mr-1"></i>Rates are estimates only. Actual cost may vary.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mark as Shipped Section -->
                    <div class="card card-outline card-success mb-0">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="fas fa-shipping-fast mr-1"></i> Mark as Shipped</h6>
                        </div>
                        <div class="card-body py-2">
                            <form id="shipForm" method="POST">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small mb-1">Shipping Carrier <span class="text-danger">*</span></label>
                                            <input type="text" name="shipping_carrier" id="shipCarrierName" class="form-control form-control-sm" required placeholder="e.g., FedEx, UPS, USPS">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="small mb-1">Tracking Number <span class="text-danger">*</span></label>
                                            <input type="text" name="tracking_number" class="form-control form-control-sm" required placeholder="Enter tracking number">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-secondary btn-sm mr-2" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check mr-1"></i> Mark as Shipped
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {

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
                $('#ratesTable').hide();
                $('#ratesTbody').empty();

                // Reset items panel
                $('#itemsDimTbody').empty();
                $('#itemsTableWrap').hide();
                $('#itemsLoadError').hide().text('');
                $('#itemsLoading').show();

                // Store current order id for rate lookup
                $('#getRatesBtn').data('order-id', orderId);

                $('#shipModal').modal('show');

                // Load items with dimensions via AJAX
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
                                '<td><input type="number" class="form-control form-control-sm dim-input" ' +
                                    'data-item-id="' + item.order_item_id + '" data-dim="weight" ' +
                                    'value="' + item.weight + '" step="0.01" min="0" placeholder="0"></td>' +
                                '<td><input type="number" class="form-control form-control-sm dim-input" ' +
                                    'data-item-id="' + item.order_item_id + '" data-dim="length" ' +
                                    'value="' + item.length + '" step="0.1" min="0" placeholder="0"></td>' +
                                '<td><input type="number" class="form-control form-control-sm dim-input" ' +
                                    'data-item-id="' + item.order_item_id + '" data-dim="width" ' +
                                    'value="' + item.width + '" step="0.1" min="0" placeholder="0"></td>' +
                                '<td><input type="number" class="form-control form-control-sm dim-input" ' +
                                    'data-item-id="' + item.order_item_id + '" data-dim="height" ' +
                                    'value="' + item.height + '" step="0.1" min="0" placeholder="0"></td>' +
                                '</tr>';
                            $('#itemsDimTbody').append(row);
                        });
                        $('#itemsTableWrap').show();
                    },
                    error: function() {
                        $('#itemsLoading').hide();
                        $('#itemsLoadError').text('Could not load item dimensions.').show();
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

                // Collect dimension overrides from the items table
                var itemOverrides = [];
                $('#itemsDimTbody tr').each(function() {
                    var itemId = $(this).find('.dim-input').first().data('item-id');
                    var entry  = { order_item_id: itemId };
                    $(this).find('.dim-input').each(function() {
                        entry[$(this).data('dim')] = parseFloat($(this).val()) || 0;
                    });
                    itemOverrides.push(entry);
                });

                $('#ratesResult').show();
                $('#ratesLoading').show();
                $('#ratesError').hide().text('');
                $('#ratesTable').hide();
                $('#ratesTbody').empty();

                $.ajax({
                    url: '{{ route('orders.shipping-rates') }}',
                    type: 'POST',
                    data: {
                        _token:     '{{ csrf_token() }}',
                        order_id:   orderId,
                        carrier_id: carrierId,
                        items:      itemOverrides
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
                        $.each(rates, function(i, rate) {
                            var cost    = rate.amount !== null ? rate.currency + ' ' + parseFloat(rate.amount).toFixed(2) : 'N/A';
                            var transit = rate.transit_days ? rate.transit_days + ' day(s)' : '-';
                            $('#ratesTbody').append(
                                '<tr>' +
                                '<td>' + $('<span>').text(rate.service_name).html() + '</td>' +
                                '<td class="text-right font-weight-bold">' + $('<span>').text(cost).html() + '</td>' +
                                '<td class="text-center">' + $('<span>').text(transit).html() + '</td>' +
                                '</tr>'
                            );
                        });
                        $('#ratesTable').show();

                        // Auto-fill carrier name in ship form from selected dropdown
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
