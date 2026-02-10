@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Payments</h1>
                    @can('payments-add')
                        <a href="{{ route('payments.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Payment</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payments</li>
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
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Payments</h6>
                    <h3 class="mb-0 text-right">{{ number_format($totalPayments, 2) }}</h3>
                    <small>All time</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">This Month</h6>
                    <h3 class="mb-0 text-right">{{ number_format($monthlyPayments, 2) }}</h3>
                    <small>{{ now()->format('F Y') }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('payments.index') }}" method="GET" class="row g-3">
                <div class="col-md-3 mb-3">
                    <label for="" class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Payment #, Bill #, or Supplier" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="" class="form-label">Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="">All Methods</option>
                        <option value="bank" {{ request('payment_method') == 'bank' ? 'selected' : '' }}>Bank</option>
                        <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">Account</label>
                    <select name="payment_account_id" class="form-control">
                        <option value="">All Accounts</option>
                        @foreach ($bankCashAccounts as $account)
                            <option value="{{ $account->id }}" {{ request('payment_account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-1">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Date</th>
                            <th>Bill #</th>
                            <th>Supplier</th>
                            <th>Account</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="text-right">Amount</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>
                                    <a href="{{ route('payments.show', $payment) }}">{{ $payment->payment_number }}</a>
                                </td>
                                <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('bills.show', $payment->bill) }}">
                                        {{ $payment->bill->bill_number }}
                                    </a>
                                </td>
                                <td>{{ $payment->bill->supplier->full_name }}</td>
                                <td>{{ $payment->paymentAccount->name }}</td>
                                <td>
                                    @if ($payment->payment_method === 'bank')
                                        <span class="badge bg-primary">Bank</span>
                                    @else
                                        <span class="badge bg-success">Cash</span>
                                    @endif
                                </td>
                                <td>{{ $payment->reference ?? '-' }}</td>
                                <td class="text-right fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('payments.show', $payment) }}" class="btn btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        @can('payments-delete')
                                            <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline btn btn-danger">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="bg-transparent border-0 text-white p-0" onclick="return confirm('Delete this payment? This will reverse the payment and update the bill balance.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fas fa-money-bill-wave text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No payments found.</p>
                                    @can('payments-add')
                                        <a href="{{ route('payments.create') }}" class="btn btn-primary">
                                            <i class="fas fa-plus mr-1"></i>Record First Payment
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {{ $payments->links() }}
            </div>
        </div>
    </div>
@endsection
