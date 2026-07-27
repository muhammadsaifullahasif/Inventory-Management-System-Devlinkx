@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Payments</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Payments</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('payments-add')
                    <a href="{{ route('payments.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Payment</span>
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
        <div class="col-md-6">
            <div class="card bg-soft-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Payments</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($totalPayments, 2) }}</h3>
                            <small class="text-muted">All time</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-primary text-white rounded">
                            <i class="feather-credit-card"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-soft-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">This Month</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($monthlyPayments, 2) }}</h3>
                            <small class="text-muted">{{ now()->format('F Y') }}</small>
                        </div>
                        <div class="avatar-text avatar-lg bg-success text-white rounded">
                            <i class="feather-calendar"></i>
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
                    <form action="{{ route('payments.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Payment #, Bill #, or Supplier" value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Method</label>
                                <select name="payment_method" class="form-select form-select-sm">
                                    <option value="">All Methods</option>
                                    <option value="bank" {{ request('payment_method') == 'bank' ? 'selected' : '' }}>Bank</option>
                                    <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Account</label>
                                <select name="payment_account_id" class="form-select form-select-sm">
                                    <option value="">All Accounts</option>
                                    @foreach ($bankCashAccounts as $account)
                                        <option value="{{ $account->id }}" {{ request('payment_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}
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
                                <a href="{{ route('payments.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Date</th>
                                <th>Bill #</th>
                                <th>Supplier</th>
                                <th>Account</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>
                                        <a href="{{ route('payments.show', $payment) }}" class="fw-semibold">
                                            {{ $payment->payment_number }}
                                        </a>
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ $payment->payment_date->format('M d, Y') }}</span></td>
                                    <td>
                                        <a href="{{ route('bills.show', $payment->bill) }}">
                                            {{ $payment->bill->bill_number }}
                                        </a>
                                    </td>
                                    <td>{{ $payment->bill->supplier->full_name }}</td>
                                    <td>{{ $payment->paymentAccount->name }}</td>
                                    <td>
                                        @if ($payment->payment_method === 'bank')
                                            <span class="badge bg-soft-primary text-primary">Bank</span>
                                        @else
                                            <span class="badge bg-soft-success text-success">Cash</span>
                                        @endif
                                    </td>
                                    <td>{{ $payment->reference ?? '-' }}</td>
                                    <td class="text-end fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('payments.show', $payment) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                            @can('payments-delete')
                                                <form action="{{ route('payments.destroy', $payment) }}" method="POST" id="payment-{{ $payment->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $payment->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete & Reverse">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="feather-credit-card text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3">No payments found.</p>
                                        @can('payments-add')
                                            <a href="{{ route('payments.create') }}" class="btn btn-primary">
                                                <i class="feather-plus me-2"></i>Record First Payment
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($payments->hasPages())
            <div class="card-footer">
                {{ $payments->links('pagination::bootstrap-5') }}
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
                if (confirm('Delete this payment? This will reverse the payment and update the bill balance.')) {
                    $('#payment-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
@endpush
