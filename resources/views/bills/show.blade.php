@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bill: {{ $bill->bill_number }}</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('bills.index') }}">Bills</a></li>
                <li class="breadcrumb-item">{{ $bill->bill_number }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @if ($bill->status === 'draft')
                        @can('bills-post')
                            <form action="{{ route('bills.post', $bill) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-success" onclick="return confirm('Post this bill?')">
                                    <i class="feather-check me-2"></i>Post Bill
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if ($bill->canEdit())
                        @can('bills-edit')
                            <a href="{{ route('bills.edit', $bill) }}" class="btn btn-primary">
                                <i class="feather-edit me-2"></i>Edit
                            </a>
                        @endcan
                    @endif

                    <a href="{{ route('bills.index') }}" class="btn btn-light-brand">
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
        <!-- Bill Details -->
        <div class="col-lg-8">
            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-1">Supplier</h6>
                            <h5 class="fw-bold">{{ $bill->supplier->full_name }}</h5>
                            @if ($bill->supplier->email)
                                <p class="mb-0 text-muted fs-12">{{ $bill->supplier->email }}</p>
                            @endif
                            @if ($bill->supplier->phone)
                                <p class="mb-0 text-muted fs-12">{{ $bill->supplier->phone }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted mb-1">Bill Number</h6>
                            <h5 class="fw-bold">{{ $bill->bill_number }}</h5>
                            <p class="mb-0 fs-12">
                                <strong>Date:</strong> {{ $bill->bill_date->format('M d, Y') }}
                            </p>
                            @if ($bill->due_date)
                                <p class="mb-0 fs-12 {{ $bill->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                    <strong>Due:</strong> {{ $bill->due_date->format('M d, Y') }}
                                    @if ($bill->isOverdue())
                                        <span class="badge bg-soft-danger text-danger">Overdue</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-list me-2"></i>Expense Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bill->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="text-muted fs-12">{{ $item->expenseAccount->parent->name }}</span><br>
                                            <span class="fw-semibold">{{ $item->expenseAccount->name }}</span>
                                        </td>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold fs-5">{{ number_format($bill->total_amount, 2) }}</td>
                                </tr>
                                @if ($bill->paid_amount > 0)
                                    <tr>
                                        <td colspan="3" class="text-end">Paid:</td>
                                        <td class="text-end text-success">-{{ number_format($bill->paid_amount, 2) }}</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td colspan="3" class="text-end fw-bold">Balance Due:</td>
                                        <td class="text-end fw-bold fs-5">{{ number_format($bill->remaining_amount, 2) }}</td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            @if ($bill->notes)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-file-text me-2"></i>Notes</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $bill->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Payments Card -->
            @if ($bill->payments->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-credit-card me-2"></i>Payment History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Date</th>
                                        <th>Account</th>
                                        <th>Reference</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($bill->payments as $payment)
                                        <tr>
                                            <td>
                                                <a href="{{ route('payments.show', $payment) }}" class="fw-semibold">
                                                    {{ $payment->payment_number }}
                                                </a>
                                            </td>
                                            <td><span class="fs-12 text-muted">{{ $payment->payment_date->format('M d, Y') }}</span></td>
                                            <td>{{ $payment->paymentAccount->name }}</td>
                                            <td>{{ $payment->reference ?? '-' }}</td>
                                            <td class="text-end text-success">{{ number_format($payment->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold">Total Paid:</td>
                                        <td class="text-end fw-bold text-success">{{ number_format($bill->paid_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Journal Entry Card -->
            @if ($bill->journalEntry)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-book me-2"></i>Journal Entry</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3 fs-12">
                            Entry #: <a href="{{ route('journal-entries.show', $bill->journalEntry) }}" class="fw-semibold">{{ $bill->journalEntry->entry_number }}</a> |
                            Date: {{ $bill->journalEntry->entry_date->format('M d, Y') }}
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
                                    @foreach ($bill->journalEntry->lines as $line)
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
                                        <td class="text-end fw-bold">{{ number_format($bill->journalEntry->total_debit, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($bill->journalEntry->total_credit, 2) }}</td>
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
            <!-- Status Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-info me-2"></i>Status</h5>
                </div>
                <div class="card-body text-center">
                    @if ($bill->status === 'draft')
                        <span class="badge bg-soft-secondary text-secondary fs-6 px-4 py-2">Draft</span>
                        <p class="text-muted mt-2 mb-0">This bill has not been posted yet.</p>
                    @elseif ($bill->status === 'unpaid')
                        <span class="badge bg-soft-warning text-warning fs-6 px-4 py-2">Unpaid</span>
                        <p class="text-muted mt-2 mb-0">Payment pending.</p>
                    @elseif ($bill->status === 'partially_paid')
                        <span class="badge bg-soft-info text-info fs-6 px-4 py-2">Partially Paid</span>
                        <p class="text-muted mt-2 mb-0">Remaining: {{ number_format($bill->remaining_amount, 2) }}</p>
                    @else
                        <span class="badge bg-soft-success text-success fs-6 px-4 py-2">Paid</span>
                        <p class="text-muted mt-2 mb-0">Fully paid.</p>
                    @endif
                </div>
            </div>

            <!-- Summary Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-dollar-sign me-2"></i>Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Total Amount:</th>
                            <td class="text-end">{{ number_format($bill->total_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Paid Amount:</td>
                            <td class="text-end text-success">{{ number_format($bill->paid_amount, 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <th>Balance Due:</th>
                            <td class="text-end fw-bold fs-5 {{ $bill->remaining_amount > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format($bill->remaining_amount, 2) }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-clock me-2"></i>Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Created:</th>
                            <td><span class="fs-12 text-muted">{{ $bill->created_at->format('M d, Y H:i') }}</span></td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td>{{ $bill->createdBy?->name ?? 'System' }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><span class="fs-12 text-muted">{{ $bill->updated_at->format('M d, Y H:i') }}</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
