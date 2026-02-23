@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bills</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Bills</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('bills-add')
                    <a href="{{ route('bills.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Bill</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-soft-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Unpaid Bills</h6>
                            <h3 class="mb-0 fw-bold">{{ $statistics['unpaid_bills'] + $statistics['partially_paid_bills'] }}</h3>
                            <small class="text-muted">Total: {{ number_format($statistics['total_payable'], 2) }}</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-file-text"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-soft-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Overdue</h6>
                            <h3 class="mb-0 fw-bold">{{ $statistics['overdue_bills'] }}</h3>
                            <small class="text-muted">Amount: {{ number_format($statistics['overdue_amount'], 2) }}</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-danger text-white rounded">
                            <i class="feather-alert-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-soft-secondary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Draft</h6>
                            <h3 class="mb-0 fw-bold">{{ $statistics['draft_bills'] }}</h3>
                            <small class="text-muted">Pending posting</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-secondary text-white rounded">
                            <i class="feather-edit-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-soft-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Paid</h6>
                            <h3 class="mb-0 fw-bold">{{ $statistics['paid_bills'] }}</h3>
                            <small class="text-muted">Completed</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-success text-white rounded">
                            <i class="feather-check-circle"></i>
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
                    <form action="{{ route('bills.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Bill # or Supplier" value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                    <option value="partially_paid" {{ request('status') == 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select form-select-sm">
                                    <option value="">All Suppliers</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('bills.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Due Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bills as $bill)
                                <tr class="{{ $bill->isOverdue() ? 'table-danger' : '' }}">
                                    <td>
                                        <a href="{{ route('bills.show', $bill) }}" class="fw-semibold">
                                            {{ $bill->bill_number }}
                                        </a>
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ $bill->bill_date->format('M d, Y') }}</span></td>
                                    <td>{{ $bill->supplier->full_name }}</td>
                                    <td>
                                        @if ($bill->due_date)
                                            <span class="{{ $bill->isOverdue() ? 'text-danger fw-bold' : 'fs-12 text-muted' }}">
                                                {{ $bill->due_date->format('M d, Y') }}
                                            </span>
                                            @if ($bill->isOverdue())
                                                <i class="feather-alert-circle text-danger ms-1"></i>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($bill->total_amount, 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($bill->paid_amount, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($bill->remaining_amount, 2) }}</td>
                                    <td>
                                        @if ($bill->status === 'draft')
                                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                        @elseif ($bill->status === 'unpaid')
                                            <span class="badge bg-soft-warning text-warning">Unpaid</span>
                                        @elseif ($bill->status === 'partially_paid')
                                            <span class="badge bg-soft-info text-info">Partial</span>
                                        @else
                                            <span class="badge bg-soft-success text-success">Paid</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('bills.show', $bill) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>

                                            @if ($bill->status === 'draft')
                                                @can('bills-post')
                                                    <form action="{{ route('bills.post', $bill) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="avatar-text avatar-md text-success border-0 bg-transparent" data-bs-toggle="tooltip" title="Post Bill" onclick="return confirm('Post this bill?')">
                                                            <i class="feather-check-circle"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif

                                            @if ($bill->canEdit())
                                                @can('bills-edit')
                                                    <a href="{{ route('bills.edit', $bill) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                        <i class="feather-edit-3"></i>
                                                    </a>
                                                @endcan
                                            @endif

                                            @if ($bill->canDelete())
                                                @can('bills-delete')
                                                    <form action="{{ route('bills.destroy', $bill) }}" method="POST" id="bill-{{ $bill->id }}-delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <a href="javascript:void(0)" data-id="{{ $bill->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                        <i class="feather-trash-2"></i>
                                                    </a>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="feather-file-text text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3">No bills found.</p>
                                        @can('bills-add')
                                            <a href="{{ route('bills.create') }}" class="btn btn-primary">
                                                <i class="feather-plus me-2"></i>Create First Bill
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($bills->hasPages())
            <div class="card-footer">
                {{ $bills->links('pagination::bootstrap-5') }}
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
                if (confirm('Are you sure you want to delete this bill?')) {
                    $('#bill-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
@endpush
