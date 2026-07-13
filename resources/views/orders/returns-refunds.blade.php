@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Returns &amp; Refunds</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">Returns &amp; Refunds</li>
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
                    <div class="text-muted fs-12">Active Returns</div>
                    <h4 class="mb-0">{{ $stats['active_returns'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Pending Cancellations</div>
                    <h4 class="mb-0">{{ $stats['pending_cancellations'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Total Refunded</div>
                    <h4 class="mb-0">{{ $stats['currency'] }} {{ number_format($stats['total_refunded'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted fs-12">Refunded Orders</div>
                    <h4 class="mb-0">{{ $stats['refunded_orders'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('orders.returns-refunds') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-12 text-muted">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Order #, buyer, email"
                        value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">Sales Channel</label>
                    <select name="sales_channel_id" class="form-select">
                        <option value="">All Channels</option>
                        @foreach($salesChannels as $channel)
                            <option value="{{ $channel->id }}" @selected(request('sales_channel_id') == $channel->id)>{{ $channel->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="return" @selected(request('type') == 'return')>Return</option>
                        <option value="cancellation" @selected(request('type') == 'cancellation')>Cancellation</option>
                        <option value="refund" @selected(request('type') == 'refund')>Full Refund</option>
                        <option value="partial_refund" @selected(request('type') == 'partial_refund')>Partial Refund</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" @selected(request('status') == 'pending')>Pending</option>
                        <option value="processing" @selected(request('status') == 'processing')>Processing</option>
                        <option value="completed" @selected(request('status') == 'completed')>Completed</option>
                        <option value="cancelled" @selected(request('status') == 'cancelled')>Cancelled/Declined</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="feather-search"></i></button>
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
                            <th>Order</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Return Items / Restock</th>
                            <th>Refund</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('orders.show', $order->id) }}"><strong>{{ $order->order_number }}</strong></a>
                                    <div class="fs-11 text-muted">{{ $order->buyer_name ?? $order->buyer_email }}</div>
                                </td>
                                <td>
                                    @if($order->salesChannel)
                                        <span class="badge bg-soft-info text-info">{{ $order->salesChannel->name }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Local</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->return_status)
                                        <span class="badge bg-soft-warning text-warning">Return: {{ str_replace('_', ' ', $order->return_status) }}</span>
                                    @endif
                                    @if($order->order_status === 'cancelled' || $order->cancel_status)
                                        <span class="badge bg-soft-danger text-danger">Cancelled</span>
                                    @endif
                                    @if($order->refund_status)
                                        <span class="badge bg-soft-primary text-primary">Refund: {{ str_replace('_', ' ', $order->refund_status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($order->returns as $return)
                                        <div class="mb-1">
                                            <span class="badge bg-soft-{{ $return->source === 'ebay' ? 'info' : 'secondary' }} text-{{ $return->source === 'ebay' ? 'info' : 'secondary' }} fs-10">
                                                {{ strtoupper($return->source) }} #{{ $return->id }}
                                            </span>
                                            <span class="badge bg-soft-{{ $return->status === 'item_received' ? 'success' : 'warning' }} text-{{ $return->status === 'item_received' ? 'success' : 'warning' }} fs-10">
                                                {{ str_replace('_', ' ', $return->status) }}
                                            </span>
                                            <div class="fs-11 text-muted">
                                                @foreach($return->items as $ri)
                                                    {{ $ri->orderItem->title ?? 'Item' }} (qty {{ $ri->quantity }})
                                                    @if($ri->restocked)
                                                        <i class="feather-check-circle text-success" title="Restocked"></i>
                                                    @else
                                                        <i class="feather-clock text-muted" title="Not restocked"></i>
                                                    @endif
                                                    <br>
                                                @endforeach
                                            </div>
                                            @if($return->status !== 'item_received' && $return->status !== 'closed')
                                                <button type="button" class="btn btn-xs btn-light-success mark-received-btn"
                                                    data-return-id="{{ $return->id }}">
                                                    <i class="feather-package me-1"></i>Mark Received &amp; Restock
                                                </button>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-muted fs-11">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($order->total_refunded > 0)
                                        <strong>{{ $order->currency ?? 'USD' }} {{ number_format($order->total_refunded, 2) }}</strong>
                                        <div class="fs-11 text-muted">of {{ number_format($order->total, 2) }}</div>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-xs btn-light-brand create-return-btn"
                                        data-order-id="{{ $order->id }}"
                                        data-order-number="{{ $order->order_number }}"
                                        data-items='@json($order->items->map(fn($i) => ["id" => $i->id, "title" => $i->title, "quantity" => $i->quantity]))'>
                                        <i class="feather-corner-up-left me-1"></i>New Return
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No returns, cancellations, or refunds found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <!-- Create Return Modal -->
    <div class="modal fade" id="createReturnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createReturnForm">
                    <div class="modal-header">
                        <h5 class="modal-title">New Return — <span id="crOrderNumber"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Items to return</label>
                            <div id="crItemsList"></div>
                            <div class="fs-11 text-muted mt-1">Select the item(s) coming back. Full ordered quantity of each selected item will be returned.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" id="crReason" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea class="form-control" id="crNotes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var createReturnModal = new bootstrap.Modal(document.getElementById('createReturnModal'));
    var currentOrderId = null;

    $('.create-return-btn').on('click', function () {
        currentOrderId = $(this).data('order-id');
        $('#crOrderNumber').text($(this).data('order-number'));

        var items = $(this).data('items') || [];
        var $list = $('#crItemsList').empty();
        items.forEach(function (item) {
            $list.append(
                '<div class="form-check">' +
                    '<input class="form-check-input cr-item" type="checkbox" value="' + item.id + '" id="crItem' + item.id + '">' +
                    '<label class="form-check-label" for="crItem' + item.id + '">' + item.title + ' (qty ' + item.quantity + ')</label>' +
                '</div>'
            );
        });

        createReturnModal.show();
    });

    $('#createReturnForm').on('submit', function (e) {
        e.preventDefault();

        var itemIds = $('.cr-item:checked').map(function () { return $(this).val(); }).get();
        if (itemIds.length === 0) {
            alert('Select at least one item to return');
            return;
        }

        var payload = {
            _token: '{{ csrf_token() }}',
            reason: $('#crReason').val(),
            notes: $('#crNotes').val(),
            items: itemIds.map(function (id) { return { order_item_id: id }; })
        };

        $.ajax({
            url: '/orders/' + currentOrderId + '/returns',
            type: 'POST',
            data: payload,
            success: function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Failed to create return');
                }
            },
            error: function (xhr) {
                alert('Failed to create return: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    });

    $('.mark-received-btn').on('click', function () {
        if (!confirm('Mark item(s) received and restock inventory now?')) {
            return;
        }

        var $btn = $(this);
        var returnId = $btn.data('return-id');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Restocking...');

        $.ajax({
            url: '/orders/returns/' + returnId + '/mark-received',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Failed to mark received');
                    $btn.prop('disabled', false).html('<i class="feather-package me-1"></i>Mark Received &amp; Restock');
                }
            },
            error: function (xhr) {
                alert('Failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
                $btn.prop('disabled', false).html('<i class="feather-package me-1"></i>Mark Received &amp; Restock');
            }
        });
    });
});
</script>
@endpush
