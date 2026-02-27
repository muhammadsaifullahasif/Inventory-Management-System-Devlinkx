@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Purchase Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Purchase Report</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('reports.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('reports.purchase') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select form-select-sm">
                                    <option value="">All Suppliers</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" class="form-select form-select-sm">
                                    <option value="">All Warehouses</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ $warehouseId == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="partial" {{ $status == 'partial' ? 'selected' : '' }}>Partial</option>
                                    <option value="received" {{ $status == 'received' ? 'selected' : '' }}>Received</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="supplier" {{ $groupBy == 'supplier' ? 'selected' : '' }}>Supplier</option>
                                    <option value="warehouse" {{ $groupBy == 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                    <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Product</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.purchase') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <!-- Purchase Summary -->
        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-soft-primary">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Total Purchases</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['total_purchases'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-primary text-white rounded">
                                    <i class="feather-shopping-bag"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-warning">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Pending</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['pending_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-warning text-white rounded">
                                    <i class="feather-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-info">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Partial</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['partial_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-info text-white rounded">
                                    <i class="feather-loader"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-soft-success">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-1 small">Received</h6>
                                    <h4 class="mb-0 fw-bold">{{ $summary['received_count'] }}</h4>
                                </div>
                                <div class="avatar-text avatar-md bg-success text-white rounded">
                                    <i class="feather-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Total Ordered Value</h6>
                            <h4 class="mb-0 fw-bold text-primary">{{ number_format($summary['total_ordered_value'], 2) }}</h4>
                            <small class="text-muted">{{ number_format($summary['total_ordered_qty'], 0) }} units</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Total Received Value</h6>
                            <h4 class="mb-0 fw-bold text-success">{{ number_format($summary['total_received_value'], 2) }}</h4>
                            <small class="text-muted">{{ number_format($summary['total_received_qty'], 0) }} units</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">Pending Value</h6>
                            <h4 class="mb-0 fw-bold text-warning">{{ number_format($summary['pending_value'], 2) }}</h4>
                            <small class="text-muted">{{ number_format($summary['total_ordered_qty'] - $summary['total_received_qty'], 0) }} units</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounting Sync Summary -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-dollar-sign me-2"></i>Accounting Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Related Bills:</td>
                            <td class="text-end fw-semibold">{{ $accountingSummary['total_bills'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Billed:</td>
                            <td class="text-end fw-semibold">{{ number_format($accountingSummary['total_billed_amount'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Paid:</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($accountingSummary['total_paid_amount'], 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Outstanding:</td>
                            <td class="text-end fw-bold text-danger">{{ number_format($accountingSummary['outstanding_amount'], 2) }}</td>
                        </tr>
                    </table>
                    <hr>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Purchases vs Bills Diff:</span>
                        @php
                            $diff = $summary['total_received_value'] - $accountingSummary['total_billed_amount'];
                        @endphp
                        <span class="{{ $diff >= 0 ? 'text-warning' : 'text-success' }} fw-semibold">
                            {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2) }}
                        </span>
                    </div>
                    <small class="text-muted">
                        @if ($diff > 0)
                            Received stock not yet billed
                        @elseif ($diff < 0)
                            Billed more than received
                        @else
                            Fully reconciled
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Grouped Report Data -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-layers me-2"></i>
                    Purchases by {{ ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ ucfirst($groupBy) }}</th>
                                @if ($groupBy === 'product')
                                    <th>SKU</th>
                                    <th class="text-end">Avg Price</th>
                                @endif
                                <th class="text-center">Purchases</th>
                                <th class="text-end">Ordered Qty</th>
                                <th class="text-end">Received Qty</th>
                                <th class="text-end">Ordered Value</th>
                                <th class="text-end">Received Value</th>
                                <th class="text-end">Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] }}</td>
                                    @if ($groupBy === 'product')
                                        <td><code>{{ $group['sku'] ?? '-' }}</code></td>
                                        <td class="text-end">{{ number_format($group['avg_price'], 2) }}</td>
                                    @endif
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['purchase_count'] }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($group['ordered_qty'], 0) }}</td>
                                    <td class="text-end">{{ number_format($group['received_qty'], 0) }}</td>
                                    <td class="text-end">{{ number_format($group['ordered_value'], 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($group['received_value'], 2) }}</td>
                                    <td class="text-end text-warning">{{ number_format($group['ordered_value'] - $group['received_value'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $groupBy === 'product' ? 9 : 7 }}" class="text-center py-5 text-muted">
                                        <i class="feather-shopping-bag" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No purchases found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($reportData->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <th>Totals</th>
                                    @if ($groupBy === 'product')
                                        <th></th>
                                        <th></th>
                                    @endif
                                    <th class="text-center">{{ $summary['total_purchases'] }}</th>
                                    <th class="text-end">{{ number_format($summary['total_ordered_qty'], 0) }}</th>
                                    <th class="text-end">{{ number_format($summary['total_received_qty'], 0) }}</th>
                                    <th class="text-end">{{ number_format($summary['total_ordered_value'], 2) }}</th>
                                    <th class="text-end text-success">{{ number_format($summary['total_received_value'], 2) }}</th>
                                    <th class="text-end text-warning">{{ number_format($summary['pending_value'], 2) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Purchase List -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Purchase Details</h5>
                <span class="badge bg-soft-secondary text-secondary">{{ $purchases->count() }} purchases</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Warehouse</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Ordered Value</th>
                                <th class="text-end">Received Value</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                @php
                                    $orderedValue = $purchase->purchase_items->sum(fn($item) => $item->quantity * $item->price);
                                    $receivedValue = $purchase->purchase_items->sum(fn($item) => $item->received_quantity * $item->price);
                                @endphp
                                <tr>
                                    <td><span class="fs-12 text-muted">{{ $purchase->created_at->format('M d, Y') }}</span></td>
                                    <td class="fw-semibold">{{ $purchase->purchase_number }}</td>
                                    <td>{{ $purchase->supplier->full_name ?? '-' }}</td>
                                    <td>{{ $purchase->warehouse->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-secondary text-secondary">{{ $purchase->purchase_items->count() }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($orderedValue, 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($receivedValue, 2) }}</td>
                                    <td class="text-center">
                                        @if ($purchase->purchase_status === 'pending')
                                            <span class="badge bg-soft-warning text-warning">Pending</span>
                                        @elseif ($purchase->purchase_status === 'partial')
                                            <span class="badge bg-soft-info text-info">Partial</span>
                                        @elseif ($purchase->purchase_status === 'received')
                                            <span class="badge bg-soft-success text-success">Received</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($purchase->purchase_status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="hstack gap-2 justify-content-center">
                                            <a href="{{ route('purchases.show', $purchase->id) }}" class="avatar-text avatar-md" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No purchases found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
