@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Purchases</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Purchases</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.import') }}" class="btn btn-light-brand">
                        <i class="feather-upload me-2"></i>
                        <span>Import</span>
                    </a>
                    @can('add purchases')
                    <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Purchase</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
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
                    <form action="{{ route('purchases.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Purchase Number..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select form-select-sm">
                                    <option value="">All Suppliers</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->first_name }} {{ $supplier->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" class="form-select form-select-sm">
                                    <option value="">All Warehouses</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="purchase_status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ request('purchase_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="received" {{ request('purchase_status') == 'received' ? 'selected' : '' }}>Received</option>
                                    <option value="cancelled" {{ request('purchase_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('purchases.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">Showing {{ $purchases->firstItem() ?? 0 }} - {{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchases Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete purchases')
                    @include('partials.bulk-actions-bar', ['itemName' => 'purchases'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $purchaseColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'purchase_number', 'label' => 'Purchase Number', 'default' => true],
                            ['key' => 'supplier', 'label' => 'Supplier', 'default' => true],
                            ['key' => 'warehouse', 'label' => 'Warehouse', 'default' => true],
                            ['key' => 'status', 'label' => 'Status', 'default' => true],
                            ['key' => 'total', 'label' => 'Total', 'default' => true],
                            ['key' => 'received', 'label' => 'Received', 'default' => true],
                            ['key' => 'created_at', 'label' => 'Created At', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'purchaseTable', 'cookieName' => 'purchase_columns', 'columns' => $purchaseColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="purchaseTable">
                        <thead>
                            <tr>
                                @can('delete purchases')
                                    <th class="ps-3" style="width: 40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectAll" title="Select all on this page">
                                                <label for="selectAll" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                    </th>
                                @endcan
                                @php
                                    $currentSort = request('sort_by', 'id');
                                    $currentOrder = request('sort_order', 'desc');
                                    $sortableColumns = [
                                        'id' => ['label' => '#', 'column' => 'id', 'style' => '', 'sort' => true],
                                        'purchase_number' => ['label' => 'Purchase Number', 'column' => 'purchase_number', 'style' => '', 'sort' => true],
                                        'supplier' => ['label' => 'Supplier', 'column' => 'supplier', 'style' => '', 'sort' => false],
                                        'warehouse' => ['label' => 'Warehouse', 'column' => 'warehouse', 'style' => '', 'sort' => false],
                                        'status' => ['label' => 'Status', 'column' => 'status', 'style' => '', 'sort' => false],
                                        'total' => ['label' => 'Total', 'column' => 'total', 'style' => '', 'sort' => true],
                                        'receiveds' => ['label' => 'Received', 'column' => 'received', 'style' => '', 'sort' => false],
                                        'created_at' => ['label' => 'Created At', 'column' => 'created_at', 'style' => '', 'sort' => true],
                                    ];
                                @endphp
                                @foreach ($sortableColumns as $key => $col)
                                    <th data-column="{{ $key }}" @if($col['style']) style="{{ $col['style'] }}" @endif>
                                        @if($col['sort'])
                                            @php
                                                $isActive = $currentSort === $col['column'];
                                                $nextOrder = ($isActive && $currentOrder === 'asc') ? 'desc' : 'asc';
                                                $sortUrl = request()->fullUrlWithQuery(['sort_by' => $col['column'], 'sort_order' => $nextOrder]);
                                            @endphp
                                            <a href="{{ $sortUrl }}" class="d-flex align-items-center text-dark text-decoration-none sortable-header {{ $isActive ? 'active' : '' }}">
                                                {{ $col['label'] }}
                                                <span class="sort-arrows ms-1">
                                                    @if($isActive)
                                                        @if($currentOrder === 'asc')
                                                            <i class="feather-arrow-up fs-12"></i>
                                                        @else
                                                            <i class="feather-arrow-down fs-12"></i>
                                                        @endif
                                                    @else
                                                        <i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>
                                                    @endif
                                                </span>
                                            </a>
                                        @else
                                            {{ $col['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                @php
                                    $totalOrdered = $purchase->purchase_items->sum('quantity');
                                    $totalReceived = $purchase->purchase_items->sum('received_quantity');
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'partial' => 'info',
                                        'received' => 'success',
                                        'cancelled' => 'danger',
                                    ];
                                    $statusColor = $statusColors[$purchase->purchase_status] ?? 'secondary';
                                @endphp
                                <tr>
                                    @can('delete purchases')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $purchase->id }}" data-purchase-id="{{ $purchase->id }}">
                                                    <label for="{{ $purchase->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="purchase-id-input" value="{{ $purchase->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $purchase->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $purchase->id }}</td>
                                    <td data-column="purchase_number"><span class="fw-semibold">{{ $purchase->purchase_number }}</span></td>
                                    <td data-column="supplier">{{ (($purchase->supplier->last_name != '') ? $purchase->supplier->first_name . ' ' . $purchase->supplier->last_name : $purchase->supplier->first_name) }}</td>
                                    <td data-column="warehouse"><span class="badge bg-soft-info text-info">{{ $purchase->warehouse->name }}</span></td>
                                    <td data-column="status"><span class="badge bg-{{ $statusColor }}">{{ ucfirst($purchase->purchase_status ?? 'pending') }}</span></td>
                                    <td data-column="total"><span class="fw-semibold">${{ number_format($purchase->purchase_items->sum(function($item) { return $item->quantity * $item->price; }), 2) }}</span></td>
                                    <td data-column="received">
                                        <span class="badge bg-soft-primary text-primary">{{ number_format($totalReceived, 0) }} / {{ number_format($totalOrdered, 0) }}</span>
                                    </td>
                                    <td data-column="created_at"><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($purchase->created_at)->format('M d, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('purchases.receive', $purchase->id) }}" class="avatar-text avatar-md text-success" data-bs-toggle="tooltip" title="Receive Stock">
                                                <i class="feather-download"></i>
                                            </a>
                                            {{-- @if($purchase->purchase_status !== 'received')
                                                <a href="{{ route('purchases.receive', $purchase->id) }}" class="avatar-text avatar-md text-success" data-bs-toggle="tooltip" title="Receive Stock">
                                                    <i class="feather-download"></i>
                                                </a>
                                            @endif --}}
                                            <a href="{{ route('purchases.show', $purchase->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                            <a href="{{ route('purchases.edit', $purchase->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                <i class="feather-edit-3"></i>
                                            </a>
                                            <form action="{{ route('purchases.destroy', $purchase->id) }}" method="POST" id="purchase-{{ $purchase->id }}-delete-form">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <a href="javascript:void(0)" data-id="{{ $purchase->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">No purchases found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div>
                    @include('partials.per-page-dropdown', ['perPage' => $perPage])
                </div>
                <div>
                    {{ $purchases->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            $(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
                if (confirm('Are you sure to delete the record?')) {
                    $('#purchase-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>

    @can('delete purchases')
        @include('partials.bulk-delete-scripts', ['routeName' => 'purchases.bulk-delete', 'itemName' => 'purchases'])
    @endcan
@endpush
