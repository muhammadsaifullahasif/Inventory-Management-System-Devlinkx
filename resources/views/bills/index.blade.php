@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Bills</h1>
                    @can('bills-add')
                        <a href="{{ route('bills.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add New Bill</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bills</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">Unpaid Bills</h6>
                    <h3 class="mb-0 text-right">{{ $statistics['unpaid_bills'] + $statistics['partially_paid_bills'] }}</h3>
                    <small>Total: {{ number_format($statistics['total_payable'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Overdue</h6>
                    <h3 class="mb-0 text-right">{{ $statistics['overdue_bills'] }}</h3>
                    <small>Amount: {{ number_format($statistics['overdue_amount'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h6 class="card-title">Draft</h6>
                    <h3 class="mb-0 text-right">{{ $statistics['draft_bills'] }}</h3>
                    <small>Pending posting</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Paid</h6>
                    <h3 class="mb-0 text-right">{{ $statistics['paid_bills'] }}</h3>
                    <small>Completed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('bills.index') }}" method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="" class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Bill # or Supplier" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label for="" class="form-label">Status</label>
                    <select name="status" id="" class="form-control">
                        <option value="">All Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="partially_paid" {{ request('status') == 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="" class="form-label">Supplier</label>
                    <select name="supplier_id" id="" class="form-control">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="" class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label for="" class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="{{ route('bills.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Due Date</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Balance</th>
                            <th>Status</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bills as $bill)
                            <tr class="{{ $bill->isOverdue() ? 'table-danger' : '' }}">
                                <td>
                                    <a href="{{ route('bills.show', $bill) }}">
                                        {{ $bill->bill_number }}
                                    </a>
                                </td>
                                <td>{{ $bill->bill_date->format('M d, Y') }}</td>
                                <td>{{ $bill->supplier->full_name }}</td>
                                <td>
                                    @if ($bill->due_date)
                                        <span class="{{ $bill->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                            {{ $bill->due_date->format('M d, Y') }}
                                        </span>
                                        @if ($bill->isOverdue())
                                            <i class="fas fa-exclamation-circle text-danger" title="Overdue"></i>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($bill->total_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($bill->paid_amount, 2) }}</td>
                                <td class="text-right fw-bold">{{ number_format($bill->remaining_amount, 2) }}</td>
                                <td>
                                    @if ($bill->status === 'draft')
                                        <span class="badge bg-secondary">Draft</span>
                                    @elseif ($bill->status === 'unpaid')
                                        <span class="badge bg-warning text-dark">Unpaid</span>
                                    @elseif ($bill->status === 'partially_paid')
                                        <span class="badge bg-info">Partial</span>
                                    @else
                                        <span class="badge bg-success">Paid</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('bills.show', $bill) }}" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a>

                                        @if ($bill->status === 'draft')
                                            @can('bills-post')
                                                <form action="{{ route('bills.post', $bill) }}" method="POST" class="d-inline btn btn-success btn-sm">
                                                    @csrf
                                                    <button class="bg-transparent border-0 text-white p-0" title="Post Bill" onclick="return confirm('Post this bill?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif

                                        @if ($bill->canEdit())
                                            @can('bills-edit')
                                                <a href="{{ route('bills.edit', $bill) }}" class="btn btn-primary btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endcan
                                        @endif

                                        {{-- @if ($bill->isPayable())
                                            @can('payments-add')
                                                <a href="{{ route('payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-success" title="Pay">
                                                    <i class="fas fa-money-bill"></i>
                                                </a>
                                            @endcan
                                        @endif --}}

                                        @if ($bill->canDelete())
                                            @can('bills-delete')
                                                <form action="{{ route('bills.destroy', $bill) }}" method="POST" class="d-inline btn btn-danger btn-sm">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="bg-transparent border-0 text-white p-0" title="Delete" onclick="return confirm('Are you sure you want to delete this bill?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-receipt text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No bills found.</p>
                                    @can('bills-add')
                                        <a href="{{ route('bills.create') }}" class="btn btn-primary">
                                            <i class="fas fa-plus mr-1"></i>Create First Bill
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-end">
                {{ $bills->links() }}
            </div>
        </div>
    </div>
@endsection
