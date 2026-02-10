@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Payment: {{ $payment->payment_number }}</h1>
                    @can('payments-add')
                        <a href="{{ route('payments.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Payment</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Payments</a></li>
                        <li class="breadcrumb-item active">{{ $payment->payment_number }}</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-12 text-right d-grid gap-2">
            @can('payments-delete')
                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="btn btn-danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-transparent border-0 text-white p-0" onclick="return confirm('Delete this payment? This will reverse the payment and update the bill balance.')">
                        <i class="fas fa-trash mr-1"></i>Delete & Reverse
                    </button>
                </form>
            @endcan
            <a href="{{ route('payments.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Payment Details -->
        <div class="col-lg-8">
            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Paid To</h6>
                            <h5>{{ $payment->bill->supplier->full_name }}</h5>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <h6 class="text-muted">Payment Number</h6>
                            <h5>{{ $payment->payment_number }}</h5>
                            <p class="mb-0"><strong>Date:</strong> {{ $payment->payment_date->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Info Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Payment to {{ $payment->bill->supplier->full_name }}</td>
                                    <td>Accounts Payable</td>
                                    <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-right">-</td>
                                </tr>
                                <tr>
                                    <td>
                                        Payment via {{ $payment->paymentAccount->name }}
                                        @if ($payment->reference)
                                            <br><small class="text-muted">Ref: {{ $payment->reference }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $payment->paymentAccount->name }}</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="2" class="text-right fw-bold">Totals:</td>
                                    <td class="text-right fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-right fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Related Bill Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Related Bill</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tr>
                                <th width="30%">Bill Number:</th>
                                <td>
                                    <a href="{{ route('bills.show', $payment->bill) }}">
                                        {{ $payment->bill->bill_number }}
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>Bill Date:</th>
                                <td>{{ $payment->bill->bill_date->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <th>Bill Total:</th>
                                <td>{{ number_format($payment->bill->total_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Balance Remaining:</th>
                                <td class="{{ $payment->bill->remaining_amount > 0 ? 'text-danger' : 'text-success' }} fw-bold">
                                    {{ number_format($payment->bill->remaining_amount, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <th>Bill Status:</th>
                                <td>
                                    @if ($payment->bill->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif ($payment->bill->status === 'partially_paid')
                                        <span class="badge bg-info">Partially Paid</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ ucfirst($payment->bill->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            @if ($payment->notes)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $payment->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Journal Entry Card -->
            @if ($payment->journalEntry)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Journal Entry</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Entry #: {{ $payment->journalEntry->entry_number }} |
                            Date: {{ $payment->journalEntry->entry_date->format('M d, Y') }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <th class="text-right">Debit</th>
                                        <th class="text-right">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payment->journalEntry->lines as $line)
                                        <tr>
                                            <td>{{ $line->account->code }} - {{ $line->account->name }}</td>
                                            <td>{{ $line->description }}</td>
                                            <td class="text-right">
                                                {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                            </td>
                                            <td class="text-right">
                                                {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-right fw-bold">Totals:</td>
                                        <td class="text-right fw-bold">{{ number_format($payment->journalEntry->total_debit, 2) }}</td>
                                        <td class="text-right fw-bold">{{ number_format($payment->journalEntry->total_credit, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Amount Card -->
            <div class="card mb-4 bg-success text-white">
                <div class="card-body text-center">
                    <h6>Payment Amount</h6>
                    <h2 class="mb-0">{{ number_format($payment->amount, 2) }}</h2>
                </div>
            </div>

            <!-- Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Info</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if ($payment->status === 'posted')
                                    <span class="badge bg-success">Posted</span>
                                @else
                                    <span class="badge bg-secondary">Draft</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Method:</th>
                            <td>
                                @if ($payment->payment_method === 'bank')
                                    <span class="badge bg-primary">Bank</span>
                                @else
                                    <span class="badge bg-success">Cash</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Account:</th>
                            <td>{{ $payment->paymentAccount->name }}</td>
                        </tr>
                        @if ($payment->reference)
                            <tr>
                                <th>Reference:</th>
                                <td><code>{{ $payment->reference }}</code></td>
                            </tr>
                        @endif
                        <tr>
                            <th>Created:</th>
                            <td>{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td>{{ $payment->createdBy?->name ?? 'System' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
