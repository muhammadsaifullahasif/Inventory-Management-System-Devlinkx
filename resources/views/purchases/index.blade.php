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
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Purchase Number</th>
                                <th>Supplier</th>
                                <th>Warehouse</th>
                                <th>Total</th>
                                <th>Total Products</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchases as $purchase)
                                <tr>
                                    <td>{{ $purchase->id }}</td>
                                    <td><span class="fw-semibold">{{ $purchase->purchase_number }}</span></td>
                                    <td>{{ (($purchase->supplier->last_name != '') ? $purchase->supplier->first_name . ' ' . $purchase->supplier->last_name : $purchase->supplier->first_name) }}</td>
                                    <td><span class="badge bg-soft-info text-info">{{ $purchase->warehouse->name }}</span></td>
                                    <td><span class="fw-semibold">${{ number_format($purchase->purchase_items->sum(function($item) { return $item->quantity * $item->price; }), 2) }}</span></td>
                                    <td><span class="badge bg-soft-primary text-primary">{{ $purchase->purchase_items->sum('quantity') }}</span></td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($purchase->created_at)->format('M d, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
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
                                    <td colspan="8" class="text-center py-4 text-muted">No purchases found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($purchases->hasPages())
            <div class="card-footer">
                {{ $purchases->links('pagination::bootstrap-5') }}
            </div>
            @endif
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
@endpush
