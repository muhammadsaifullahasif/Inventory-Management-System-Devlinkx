@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Receive Stock</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item">Receive Stock</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Purchases</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@push('styles')
<style>
    .receive-input {
        width: 100px;
    }
    .status-badge {
        font-size: 0.75rem;
    }
    .fully-received {
        background-color: #d4edda !important;
    }
    .partially-received {
        background-color: #fff3cd !important;
    }
</style>
@endpush

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="feather-alert-triangle me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Purchase Info Card -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-file-text me-2"></i>Purchase Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 120px;">Purchase #:</td>
                            <td><strong>{{ $purchase->purchase_number }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Supplier:</td>
                            <td>{{ $purchase->supplier->first_name }} {{ $purchase->supplier->last_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Warehouse:</td>
                            <td><span class="badge bg-soft-info text-info">{{ $purchase->warehouse->name }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'partial' => 'info',
                                        'received' => 'success',
                                        'cancelled' => 'danger',
                                    ];
                                    $statusColor = $statusColors[$purchase->purchase_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ ucfirst($purchase->purchase_status) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td>{{ $purchase->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                    @if($purchase->purchase_note)
                        <hr class="my-3">
                        <p class="text-muted mb-1">Note:</p>
                        <p class="mb-0">{{ $purchase->purchase_note }}</p>
                    @endif
                </div>
            </div>

            <!-- Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-pie-chart me-2"></i>Receiving Summary</h5>
                </div>
                <div class="card-body">
                    @php
                        $totalOrdered = $purchase->purchase_items->sum('quantity');
                        $totalReceived = $purchase->purchase_items->sum('received_quantity');
                        $totalPending = $totalOrdered - $totalReceived;
                        $percentReceived = $totalOrdered > 0 ? round(($totalReceived / $totalOrdered) * 100) : 0;
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Progress</span>
                            <span class="fw-semibold">{{ $percentReceived }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentReceived }}%"></div>
                        </div>
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Total Ordered:</td>
                            <td class="text-end fw-semibold">{{ number_format($totalOrdered, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Received:</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($totalReceived, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pending:</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format($totalPending, 0) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Receive Items Card -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Items to Receive</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="receiveAllBtn">
                        <i class="feather-check-square me-1"></i>Receive All Pending
                    </button>
                </div>
                <div class="card-body p-0">
                    <form action="{{ route('purchases.receive.store', $purchase->id) }}" method="POST" id="receiveForm">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Product</th>
                                        <th style="width: 90px;" class="text-center">Ordered</th>
                                        <th style="width: 90px;" class="text-center">Received</th>
                                        <th style="width: 90px;" class="text-center">Pending</th>
                                        <th style="width: 120px;" class="text-center">Receive Qty</th>
                                        <th style="width: 140px;">Rack</th>
                                        <th style="width: 80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchase->purchase_items as $index => $item)
                                        @php
                                            $ordered = (float) $item->quantity;
                                            $received = (float) $item->received_quantity;
                                            $pending = $ordered - $received;
                                            $isFullyReceived = $received >= $ordered;
                                            $isPartiallyReceived = $received > 0 && $received < $ordered;
                                        @endphp
                                        <tr class="{{ $isFullyReceived ? 'fully-received' : ($isPartiallyReceived ? 'partially-received' : '') }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $item->name }}</div>
                                                <small class="text-muted">SKU: {{ $item->sku }}</small>
                                                <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item->id }}">
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-semibold">{{ number_format($ordered, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-success text-success">{{ number_format($received, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-warning text-warning pending-qty" data-pending="{{ $pending }}">{{ number_format($pending, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if($isFullyReceived)
                                                    <input type="hidden" name="items[{{ $index }}][receive_quantity]" value="0">
                                                    <input type="number" value="0" min="0" max="0"
                                                           class="form-control form-control-sm receive-input text-center bg-light"
                                                           readonly>
                                                @else
                                                    <input type="number" name="items[{{ $index }}][receive_quantity]"
                                                           value="0" min="0" max="{{ (int) $pending }}" step="1"
                                                           class="form-control form-control-sm receive-input text-center receive-qty-input"
                                                           data-max="{{ (int) $pending }}">
                                                @endif
                                            </td>
                                            <td>
                                                @if($isFullyReceived)
                                                    <input type="hidden" name="items[{{ $index }}][rack_id]" value="{{ $item->rack_id }}">
                                                    <select class="form-select form-select-sm bg-light" disabled>
                                                        @foreach ($racks as $rack)
                                                            <option value="{{ $rack->id }}" {{ $item->rack_id == $rack->id ? 'selected' : '' }}>
                                                                {{ $rack->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <select name="items[{{ $index }}][rack_id]" class="form-select form-select-sm">
                                                        @foreach ($racks as $rack)
                                                            <option value="{{ $rack->id }}" {{ $item->rack_id == $rack->id ? 'selected' : '' }}>
                                                                {{ $rack->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </td>
                                            <td>
                                                @if($isFullyReceived)
                                                    <span class="badge bg-success status-badge">Received</span>
                                                @elseif($isPartiallyReceived)
                                                    <span class="badge bg-warning status-badge">Partial</span>
                                                @else
                                                    <span class="badge bg-secondary status-badge">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">Total to receive this session: </span>
                                <strong id="totalToReceive" class="text-primary">0</strong>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="feather-check me-2"></i>Confirm Receive
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function(){
    // Calculate total to receive
    function calculateTotal() {
        var total = 0;
        $('.receive-qty-input').each(function(){
            var val = parseFloat($(this).val()) || 0;
            total += val;
        });
        $('#totalToReceive').text(total);

        // Disable submit if nothing to receive
        if (total <= 0) {
            $('#submitBtn').prop('disabled', true).addClass('btn-secondary').removeClass('btn-primary');
        } else {
            $('#submitBtn').prop('disabled', false).addClass('btn-primary').removeClass('btn-secondary');
        }
    }

    // Update total on input change
    $(document).on('input change', '.receive-qty-input', function(){
        var max = parseFloat($(this).attr('data-max')) || 0;
        var val = parseFloat($(this).val()) || 0;

        // Ensure value doesn't exceed max
        if (val > max) {
            $(this).val(Math.floor(max));
        }
        if (val < 0) {
            $(this).val(0);
        }

        calculateTotal();
    });

    // Receive all pending button
    $('#receiveAllBtn').click(function(e){
        e.preventDefault();
        $('.receive-qty-input').each(function(){
            var max = parseFloat($(this).attr('data-max')) || 0;
            $(this).val(Math.floor(max));
        });
        calculateTotal();
    });

    // Initial calculation
    calculateTotal();

    // Form validation
    $('#receiveForm').submit(function(e){
        var total = 0;
        $('.receive-qty-input').each(function(){
            total += parseFloat($(this).val()) || 0;
        });

        if (total <= 0) {
            e.preventDefault();
            alert('Please enter at least one quantity to receive.');
            return false;
        }
    });
});
</script>
@endpush
