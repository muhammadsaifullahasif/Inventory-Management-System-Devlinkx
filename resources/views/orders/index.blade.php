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
                                            <button type="button" class="btn btn-primary btn-sm ship-btn" data-id="{{ $order->id }}" title="Mark as Shipped">
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
    <div class="modal fade" id="shipModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Order as Shipped</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="shipForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Shipping Carrier</label>
                            <input type="text" name="shipping_carrier" class="form-control" required placeholder="e.g., UPS, FedEx, USPS">
                        </div>
                        <div class="form-group">
                            <label>Tracking Number</label>
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
            // Ship order
            $(document).on('click', '.ship-btn', function(e) {
                var orderId = $(this).data('id');
                $('#shipForm').attr('action', '/orders/' + orderId + '/ship');
                $('#shipModal').modal('show');
            });

            // Cancel order
            $(document).on('click', '.cancel-btn', function(e) {
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

            // Ship form submit
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
