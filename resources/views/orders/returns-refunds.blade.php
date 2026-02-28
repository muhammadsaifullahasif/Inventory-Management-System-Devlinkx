@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Returns & Refunds</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">Returns & Refunds</li>
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
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-warning text-warning rounded">
                            <i class="feather-rotate-ccw fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0">{{ $stats['active_returns'] }}</h5>
                            <small class="text-muted">Active Returns</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-danger text-danger rounded">
                            <i class="feather-x-circle fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0">{{ $stats['pending_cancellations'] }}</h5>
                            <small class="text-muted">Pending Cancellations</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-info text-info rounded">
                            <i class="feather-dollar-sign fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0">{{ $stats['currency'] }} {{ number_format($stats['total_refunded'], 2) }}</h5>
                            <small class="text-muted">Total Refunded</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-secondary text-secondary rounded">
                            <i class="feather-check-circle fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h5 class="mb-0">{{ $stats['refunded_orders'] }}</h5>
                            <small class="text-muted">Refunded Orders</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    <form action="{{ route('orders.returns-refunds') }}" method="GET">
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
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    <option value="return" {{ request('type') == 'return' ? 'selected' : '' }}>Returns</option>
                                    <option value="cancellation" {{ request('type') == 'cancellation' ? 'selected' : '' }}>Cancellations</option>
                                    <option value="refund" {{ request('type') == 'refund' ? 'selected' : '' }}>Refunds</option>
                                    <option value="partial_refund" {{ request('type') == 'partial_refund' ? 'selected' : '' }}>Partial Refunds</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('orders.returns-refunds') }}" class="btn btn-light-brand btn-sm">
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
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order #</th>
                                <th>Channel</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Refunded</th>
                                <th>Reason</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td>{{ $order->id }}</td>
                                    <td>
                                        <a href="{{ route('orders.show', $order->id) }}" class="fw-semibold text-primary">
                                            {{ $order->order_number }}
                                        </a>
                                        @if($order->ebay_order_id)
                                            <span class="d-block fs-11 text-muted">eBay: {{ \Illuminate\Support\Str::limit($order->ebay_order_id, 20) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->salesChannel)
                                            <span class="badge bg-soft-info text-info">{{ $order->salesChannel->name }}</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">Local</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $order->buyer_name ?? 'N/A' }}</span>
                                        @if($order->buyer_email)
                                            <span class="d-block fs-11 text-muted">{{ $order->buyer_email }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $typeLabel = 'N/A';
                                            $typeColor = 'secondary';

                                            if ($order->return_status) {
                                                $typeLabel = 'Return';
                                                $typeColor = 'warning';
                                            } elseif ($order->order_status === 'cancelled' || $order->hasPendingCancellation()) {
                                                $typeLabel = 'Cancellation';
                                                $typeColor = 'danger';
                                            } elseif ($order->isPartiallyRefunded()) {
                                                $typeLabel = 'Partial Refund';
                                                $typeColor = 'info';
                                            } elseif ($order->isRefunded()) {
                                                $typeLabel = 'Full Refund';
                                                $typeColor = 'primary';
                                            }
                                        @endphp
                                        <span class="badge bg-soft-{{ $typeColor }} text-{{ $typeColor }}">{{ $typeLabel }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $statusLabel = 'Unknown';
                                            $statusColor = 'secondary';

                                            if ($order->return_status) {
                                                $statusLabel = ucfirst(str_replace('_', ' ', $order->return_status));
                                                $statusColor = match(strtolower($order->return_status)) {
                                                    'open', 'pending', 'awaiting' => 'warning',
                                                    'closed', 'completed' => 'success',
                                                    'cancelled', 'declined' => 'danger',
                                                    default => 'info'
                                                };
                                            } elseif ($order->order_status === 'cancelled') {
                                                $statusLabel = 'Cancelled';
                                                $statusColor = 'danger';
                                            } elseif ($order->hasPendingCancellation()) {
                                                $statusLabel = 'Pending';
                                                $statusColor = 'warning';
                                            } elseif ($order->refund_status) {
                                                $statusLabel = ucfirst($order->refund_status);
                                                $statusColor = $order->refund_status === 'completed' ? 'success' : 'info';
                                            }
                                        @endphp
                                        <span class="badge bg-soft-{{ $statusColor }} text-{{ $statusColor }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        @if($order->total_refunded > 0)
                                            <span class="text-success fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($order->total_refunded, 2) }}</span>
                                            @if($order->isPartiallyRefunded())
                                                <span class="d-block fs-11 text-muted">of {{ $order->currency ?? 'USD' }} {{ number_format($order->total, 2) }}</span>
                                            @endif
                                        @elseif($order->refund_amount > 0)
                                            <span class="text-success fw-semibold">{{ $order->currency ?? 'USD' }} {{ number_format($order->refund_amount, 2) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->return_reason)
                                            <span class="fs-12" data-bs-toggle="tooltip" title="{{ $order->return_reason }}">
                                                {{ \Illuminate\Support\Str::limit($order->return_reason, 25) }}
                                            </span>
                                        @elseif($order->cancellation_reason)
                                            <span class="fs-12" data-bs-toggle="tooltip" title="{{ $order->cancellation_reason }}">
                                                {{ \Illuminate\Support\Str::limit($order->cancellation_reason, 25) }}
                                            </span>
                                        @else
                                            <span class="text-muted fs-12">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $dateToShow = $order->return_requested_at ?? $order->cancellation_requested_at ?? $order->refund_initiated_at ?? $order->updated_at;
                                        @endphp
                                        @if($dateToShow)
                                            <span class="fs-12">{{ \Carbon\Carbon::parse($dateToShow)->format('d M, Y') }}</span>
                                            <span class="d-block fs-11 text-muted">{{ \Carbon\Carbon::parse($dateToShow)->format('H:i') }}</span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('orders.show', $order->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Order">
                                                <i class="feather-eye"></i>
                                            </a>

                                            {{-- Refund Button (for non-refunded paid orders) --}}
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

                                            {{-- Cancel Button (for cancellable orders) --}}
                                            @if($order->canBeCancelled())
                                                <a href="javascript:void(0);"
                                                    class="avatar-text avatar-md text-danger cancel-btn"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-number="{{ $order->order_number }}"
                                                    data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Cancel Order">
                                                    <i class="feather-x-circle"></i>
                                                </a>
                                            @endif

                                            {{-- Approve Cancellation (for pending cancellations) --}}
                                            @if($order->hasPendingCancellation())
                                                <a href="javascript:void(0);"
                                                    class="avatar-text avatar-md text-success approve-cancel-btn"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-number="{{ $order->order_number }}"
                                                    data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}"
                                                    data-ebay-order-id="{{ $order->ebay_order_id }}"
                                                    data-sales-channel-id="{{ $order->sales_channel_id }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Approve Cancellation">
                                                    <i class="feather-check-circle"></i>
                                                </a>
                                                <a href="javascript:void(0);"
                                                    class="avatar-text avatar-md text-warning reject-cancel-btn"
                                                    data-order-id="{{ $order->id }}"
                                                    data-order-number="{{ $order->order_number }}"
                                                    data-is-ebay="{{ $order->isEbayOrder() ? '1' : '0' }}"
                                                    data-ebay-order-id="{{ $order->ebay_order_id }}"
                                                    data-sales-channel-id="{{ $order->sales_channel_id }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Reject Cancellation">
                                                    <i class="feather-x"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">No returns, cancellations, or refunds found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($orders->hasPages())
            <div class="card-footer">
                {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
            @endif
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

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="feather-alert-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone.
                    </div>

                    <p>Are you sure you want to cancel order <strong id="cancelOrderNumber"></strong>?</p>

                    <form id="cancelForm">
                        <input type="hidden" name="order_id" id="cancelOrderId">
                        <input type="hidden" name="is_ebay" id="cancelIsEbay">

                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="cancelReason" name="reason" required>
                                <option value="">Select a reason...</option>
                                <option value="BUYER_ASKED_CANCEL">Buyer Requested Cancellation</option>
                                <option value="OUT_OF_STOCK">Out of Stock</option>
                                <option value="BUYER_NO_SHOW">Buyer No Show (Pickup)</option>
                                <option value="PRICING_ERROR">Pricing Error</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="cancelComment" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="cancelComment" name="comment" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="submitCancelBtn">
                        <i class="feather-x-circle me-1"></i> Cancel Order
                    </button>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {

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
                            line_items: [], // Would need item selection for eBay partial
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
            // Cancel Order Modal
            // ----------------------------------------------------------------
            $(document).on('click', '.cancel-btn', function() {
                var $btn = $(this);

                $('#cancelOrderId').val($btn.data('order-id'));
                $('#cancelOrderNumber').text($btn.data('order-number'));
                $('#cancelIsEbay').val($btn.data('is-ebay'));

                // Reset form
                $('#cancelForm')[0].reset();

                var cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
                cancelModal.show();
            });

            // Submit cancellation
            $('#submitCancelBtn').on('click', function() {
                var orderId = $('#cancelOrderId').val();
                var reason = $('#cancelReason').val();
                var comment = $('#cancelComment').val();

                if (!reason) {
                    alert('Please select a cancellation reason.');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Processing...');

                $.ajax({
                    url: '/orders/' + orderId + '/cancel',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        reason: reason + (comment ? ' - ' + comment : '')
                    },
                    success: function(response) {
                        if (response.success) {
                            bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to cancel order');
                            $btn.prop('disabled', false).html('<i class="feather-x-circle me-1"></i> Cancel Order');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to cancel order: ' + (xhr.responseJSON?.message || 'Unknown error'));
                        $btn.prop('disabled', false).html('<i class="feather-x-circle me-1"></i> Cancel Order');
                    }
                });
            });

            // ----------------------------------------------------------------
            // Approve/Reject Cancellation
            // ----------------------------------------------------------------
            $(document).on('click', '.approve-cancel-btn', function() {
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var orderNumber = $btn.data('order-number');
                var isEbay = $btn.data('is-ebay') === '1' || $btn.data('is-ebay') === 1;

                if (!confirm('Approve cancellation for order ' + orderNumber + '?')) {
                    return;
                }

                var url, data;

                if (isEbay) {
                    var salesChannelId = $btn.data('sales-channel-id');
                    var ebayOrderId = $btn.data('ebay-order-id');
                    url = '/api/ebay/cancellations/' + salesChannelId + '/' + ebayOrderId + '/approve';
                    data = { _token: '{{ csrf_token() }}' };
                } else {
                    url = '/orders/' + orderId + '/cancel';
                    data = {
                        _token: '{{ csrf_token() }}',
                        reason: 'Cancellation approved by admin'
                    };
                }

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to approve cancellation');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to approve cancellation: ' + (xhr.responseJSON?.message || 'Unknown error'));
                    }
                });
            });

            $(document).on('click', '.reject-cancel-btn', function() {
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var orderNumber = $btn.data('order-number');
                var isEbay = $btn.data('is-ebay') === '1' || $btn.data('is-ebay') === 1;

                var rejectReason = prompt('Enter reason for rejecting cancellation for order ' + orderNumber + ':');

                if (!rejectReason) {
                    return;
                }

                var url, data;

                if (isEbay) {
                    var salesChannelId = $btn.data('sales-channel-id');
                    var ebayOrderId = $btn.data('ebay-order-id');
                    url = '/api/ebay/cancellations/' + salesChannelId + '/' + ebayOrderId + '/reject';
                    data = {
                        _token: '{{ csrf_token() }}',
                        reason: rejectReason
                    };
                } else {
                    // For local orders, just update the status
                    url = '/orders/' + orderId;
                    data = {
                        _token: '{{ csrf_token() }}',
                        _method: 'PUT',
                        order_status: 'processing',
                        cancel_status: null
                    };
                }

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to reject cancellation');
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to reject cancellation: ' + (xhr.responseJSON?.message || 'Unknown error'));
                    }
                });
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });

        });
    </script>
@endpush
