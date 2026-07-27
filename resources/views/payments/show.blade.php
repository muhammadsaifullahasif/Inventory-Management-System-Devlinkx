@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Payment: {{ $payment->payment_number }}</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Payments</a></li>
                <li class="breadcrumb-item">{{ $payment->payment_number }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('payments-delete')
                        <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this payment? This will reverse the payment and update the bill balance.')">
                                <i class="feather-trash-2 me-2"></i>Delete & Reverse
                            </button>
                        </form>
                    @endcan
                    @can('payments-add')
                        <a href="{{ route('payments.create') }}" class="btn btn-primary">
                            <i class="feather-plus me-2"></i>Add Payment
                        </a>
                    @endcan
                    <a href="{{ route('payments.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <!-- Payment Details -->
        <div class="col-lg-8">
            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-1">Paid To</h6>
                            <h5 class="fw-bold">{{ $payment->bill->supplier->full_name }}</h5>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted mb-1">Payment Number</h6>
                            <h5 class="fw-bold">{{ $payment->payment_number }}</h5>
                            <p class="mb-0 fs-12"><strong>Date:</strong> {{ $payment->payment_date->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Info Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-list me-2"></i>Payment Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Payment to {{ $payment->bill->supplier->full_name }}</td>
                                    <td>Accounts Payable</td>
                                    <td class="text-end text-success">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-end">-</td>
                                </tr>
                                <tr>
                                    <td>
                                        Payment via {{ $payment->paymentAccount->name }}
                                        @if ($payment->reference)
                                            <br><small class="text-muted">Ref: {{ $payment->reference }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $payment->paymentAccount->name }}</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end text-danger">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="2" class="text-end fw-bold">Totals:</td>
                                    <td class="text-end fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Related Bill Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-file-text me-2"></i>Related Bill</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="30%">Bill Number:</th>
                            <td>
                                <a href="{{ route('bills.show', $payment->bill) }}" class="fw-semibold">
                                    {{ $payment->bill->bill_number }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Bill Date:</th>
                            <td><span class="fs-12 text-muted">{{ $payment->bill->bill_date->format('M d, Y') }}</span></td>
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
                                    <span class="badge bg-soft-success text-success">Paid</span>
                                @elseif ($payment->bill->status === 'partially_paid')
                                    <span class="badge bg-soft-info text-info">Partially Paid</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning">{{ ucfirst($payment->bill->status) }}</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Notes Card -->
            @if ($payment->notes)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-file-text me-2"></i>Notes</h5>
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
                        <h5 class="card-title"><i class="feather-book me-2"></i>Journal Entry</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3 fs-12">
                            Entry #: <a href="{{ route('journal-entries.show', $payment->journalEntry) }}" class="fw-semibold">{{ $payment->journalEntry->entry_number }}</a> |
                            Date: {{ $payment->journalEntry->entry_date->format('M d, Y') }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payment->journalEntry->lines as $line)
                                        <tr>
                                            <td><code>{{ $line->account->code }}</code> - {{ $line->account->name }}</td>
                                            <td>{{ $line->description }}</td>
                                            <td class="text-end">
                                                @if ($line->debit > 0)
                                                    <span class="text-success">{{ number_format($line->debit, 2) }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if ($line->credit > 0)
                                                    <span class="text-danger">{{ number_format($line->credit, 2) }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-end fw-bold">Totals:</td>
                                        <td class="text-end fw-bold">{{ number_format($payment->journalEntry->total_debit, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($payment->journalEntry->total_credit, 2) }}</td>
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
            <div class="card mb-4 bg-soft-success">
                <div class="card-body text-center">
                    <h6 class="text-muted">Payment Amount</h6>
                    <h2 class="mb-0 text-success fw-bold">{{ number_format($payment->amount, 2) }}</h2>
                </div>
            </div>

            <!-- Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-info me-2"></i>Payment Info</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if ($payment->status === 'posted')
                                    <span class="badge bg-soft-success text-success">Posted</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Method:</th>
                            <td>
                                @if ($payment->payment_method === 'bank')
                                    <span class="badge bg-soft-primary text-primary">Bank</span>
                                @else
                                    <span class="badge bg-soft-success text-success">Cash</span>
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
                            <td><span class="fs-12 text-muted">{{ $payment->created_at->format('M d, Y H:i') }}</span></td>
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
