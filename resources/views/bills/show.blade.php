@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Bill: {{ $bill->bill_number }}</h1>
                    @can('bills-add')
                        <a href="{{ route('bills.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add New Bill</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('bills.index') }}">Bills</a></li>
                        <li class="breadcrumb-item active">{{ $bill->bill_number }}</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="row mb-4">
        <div class="col-md-6"></div>
        <div class="col-md-6 text-end">
            @if ($bill->status === 'draft')
                @can('bills-post')
                    <form action="{{ route('bills.post', $bill) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success" onclick="return confirm('Post this bill?')">
                            <i class="fas fa-check mr-1"></i>Post Bill
                        </button>
                    </form>
                @endcan
            @endif

            @if ($bill->canEdit())
                @can('bills-edit')
                    <a href="{{ route('bills.edit', $bill) }}" class="btn btn-primary">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </a>
                @endcan
            @endif

            {{-- @if ($bill->isPayable())
                @can('payments-add')
                    <a href="{{ route('payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-success">
                        <i class="fas fa-money-bill mr-1"></i>Make Payment
                    </a>
                @endcan
            @endif --}}

            <a href="{{ route('bills.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Bill Details -->
        <div class="col-lg-8">
            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Supplier</h6>
                            <h5>{{ $bill->supplier->full_name }}</h5>
                            @if ($bill->supplier->email)
                                <p class="mb-0 text-muted">{{ $bill->supplier->email }}</p>
                            @endif
                            @if ($bill->supplier->phone)
                                <p class="mb-0 text-muted">{{ $bill->supplier->phone }}</p>
                            @endif
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted">Bill Number</h6>
                            <h5>{{ $bill->bill_number }}</h5>
                            <p class="mb-0">
                                <strong>DAte:</strong> {{ $bill->bill_date->format('M d, Y') }}
                            </p>
                            @if ($bill->due_date)
                                <p class="mb-0 {{ $bill->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                    <strong>Due:</strong> {{ $bill->due_date->format('M d, Y') }}
                                    @if ($bill->isOverdue())
                                        <span class="badge bg-danger">Overdue</span>
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
                    <h5 class="card-title mb-0">Expense Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
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
                                            <span class="text-muted">{{ $item->expenseAccount->parent->name }}</span>
                                            {{ $item->expenseAccount->name }}
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
                        <h5 class="card-title mb-0">Notes</h5>
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
                        <h5 class="card-title mb-0">Payment History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
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
                                                <a href="{{ route('payments.show', $payment) }}">
                                                    {{ $payment->payment_number }}
                                                </a>
                                            </td>
                                            <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                                            <td>{{ $payment->paymentAccount->name }}</td>
                                            <td>{{ $payment->reference ?? '-' }}</td>
                                            <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="4" class="text-end fw-bold">Total Paid:</td>
                                        <td class="text-end fw-bold">{{ number_format($bill->paid_amount, 2) }}</td>
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
                        <h5 class="card-title mb-0">Journal Entry</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Entry #: {{ $bill->journalEntry->entry_number }} |
                            Date: {{ $bill->journalEntry->entry_date->format('M d, Y') }}
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <td class="text-end">Debit</td>
                                        <td class="text-end">Credit</td>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($bill->journalEntry->lines as $line)
                                        <tr>
                                            <td>{{ $line->account->code }} - {{ $line->account->name }}</td>
                                            <td>{{ $line->description }}</td>
                                            <td class="text-end">
                                                {{ $line->debit > 0 ? number_format($line->debit) : '-' }}
                                            </td>
                                            <td class="text-end">
                                                {{ $line->credit > 0 ? number_format($line->credit) : '-' }}
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
                    <h5 class="card-title mb-0">Status</h5>
                </div>
                <div class="card-body text-center">
                    @if ($bill->status === 'draft')
                        <span class="badge bg-secondary fs-5 px-4 py-2">Draft</span>
                        <p class="text-muted mt-2 mb-0">This bill has not been posted yet.</p>
                    @elseif ($bill->status === 'unpaid')
                        <span class="badge bg-warning text-dark fs-5 px-4 py-2">Unpaid</span>
                        <p class="text-muted mt-2 mb-0">Payment pending.</p>
                    @elseif ($bill->status === 'partially_paid')
                        <span class="badge bg-info fs-5 px-4 py-2">Partially Paid</span>
                        <p class="text-muted mt-2 mb-0">{{ number_format($bill->remaining_amount, 2) }}</p>
                    @else
                        <span class="badge bg-success fs-5 px-4 py-2">Paid</span>
                        <p class="text-muted mt-2 mb-0">Fully paid.</p>
                    @endif
                </div>
            </div>

            <!-- Summary Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Summary</h5>
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
                    <h5 class="card-title mb-0">Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Created:</th>
                            <td>{{ $bill->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td>{{ $bill->createdBy?->name ?? 'System' }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td>{{ $bill->updated_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
