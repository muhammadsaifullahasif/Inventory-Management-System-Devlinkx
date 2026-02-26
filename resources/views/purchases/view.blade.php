@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Purchase Details</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item">Purchase Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Purchases</span>
                    </a>
                    @if($purchase->purchase_status !== 'received')
                    <a href="{{ route('purchases.receive', $purchase->id) }}" class="btn btn-success">
                        <i class="feather-download me-2"></i>
                        <span>Receive Stock</span>
                    </a>
                    @endif
                    @can('edit purchases')
                    <a href="{{ route('purchases.edit', $purchase->id) }}" class="btn btn-light-brand">
                        <i class="feather-edit me-2"></i>
                        <span>Edit</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@push('styles')
<style>
    .fully-received { background-color: #d4edda !important; }
    .partially-received { background-color: #fff3cd !important; }
</style>
@endpush

@section('content')
    <div class="row">
        <!-- Purchase Info -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Purchase Information</h5>
                    @php
                        $statusColors = [
                            'pending' => 'warning',
                            'partial' => 'info',
                            'received' => 'success',
                            'cancelled' => 'danger',
                        ];
                        $statusColor = $statusColors[$purchase->purchase_status] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $statusColor }} fs-12">{{ ucfirst($purchase->purchase_status ?? 'pending') }}</span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label text-muted">Purchase Number</label>
                            <p class="fw-semibold mb-0">{{ $purchase->purchase_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Supplier</label>
                            <p class="fw-semibold mb-0">{{ $purchase->supplier->first_name }} {{ $purchase->supplier->last_name }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Warehouse</label>
                            <p class="fw-semibold mb-0"><span class="badge bg-soft-info text-info">{{ $purchase->warehouse->name }}</span></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label text-muted">Created Date</label>
                            <p class="mb-0">{{ $purchase->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        @if($purchase->purchase_note)
                        <div class="col-md-8">
                            <label class="form-label text-muted">Purchase Note</label>
                            <p class="mb-0">{{ $purchase->purchase_note }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Receiving Summary -->
        <div class="col-md-4">
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
                        $totalValue = $purchase->purchase_items->sum(function($item) { return $item->quantity * $item->price; });
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Progress</span>
                            <span class="fw-semibold">{{ $percentReceived }}%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentReceived }}%"></div>
                        </div>
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Total Ordered:</td>
                            <td class="text-end fw-semibold">{{ number_format($totalOrdered, 0) }} units</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Received:</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($totalReceived, 0) }} units</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pending:</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format($totalPending, 0) }} units</td>
                        </tr>
                        <tr>
                            <td colspan="2"><hr class="my-2"></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Value:</td>
                            <td class="text-end fw-semibold text-primary">${{ number_format($totalValue, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Items -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Purchase Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th class="text-center">Ordered</th>
                            <th class="text-center">Received</th>
                            <th class="text-center">Pending</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                            <th>Rack</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="purchaseProductsTable">
                        @foreach ($purchase->purchase_items as $item)
                            @php
                                $ordered = (float) $item->quantity;
                                $received = (float) $item->received_quantity;
                                $pending = $ordered - $received;
                                $isFullyReceived = $received >= $ordered;
                                $isPartiallyReceived = $received > 0 && $received < $ordered;
                            @endphp
                            <tr class="{{ $isFullyReceived ? 'fully-received' : ($isPartiallyReceived ? 'partially-received' : '') }}">
                                <td>{{ $loop->index + 1 }}</td>
                                <td><span class="fs-12 fw-semibold">{{ $item->sku }}</span></td>
                                <td>
                                    <span class="fw-semibold">{{ $item->name }}</span>
                                    @if($item->barcode)
                                    <span class="d-block fs-11 text-muted">{{ $item->barcode }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fw-semibold">{{ number_format($ordered, 0) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-soft-success text-success">{{ number_format($received, 0) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-soft-warning text-warning">{{ number_format($pending, 0) }}</span>
                                </td>
                                <td class="text-end">${{ number_format($item->price, 2) }}</td>
                                <td class="text-end fw-semibold">${{ number_format($item->quantity * $item->price, 2) }}</td>
                                <td>
                                    @if($item->rack)
                                        <span class="badge bg-soft-secondary text-secondary">{{ $item->rack->name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isFullyReceived)
                                        <span class="badge bg-success">Received</span>
                                    @elseif($isPartiallyReceived)
                                        <span class="badge bg-warning">Partial</span>
                                    @else
                                        <span class="badge bg-secondary">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="7" class="text-end"><strong>Total:</strong></td>
                            <td class="text-end"><strong class="text-primary">${{ number_format($totalValue, 2) }}</strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection
