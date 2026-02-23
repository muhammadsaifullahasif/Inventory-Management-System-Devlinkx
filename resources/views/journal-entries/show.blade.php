@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Journal Entry: {{ $journalEntry->entry_number }}</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('journal-entries.index') }}">Journal Entries</a></li>
                <li class="breadcrumb-item">{{ $journalEntry->entry_number }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @if ($reference)
                        @if ($journalEntry->reference_type === 'bill')
                            <a href="{{ route('bills.show', $reference) }}" class="btn btn-info">
                                <i class="feather-file-text me-2"></i>View Bill
                            </a>
                        @elseif ($journalEntry->reference_type === 'payment')
                            <a href="{{ route('payments.show', $reference) }}" class="btn btn-info">
                                <i class="feather-credit-card me-2"></i>View Payment
                            </a>
                        @endif
                    @endif
                    <a href="{{ route('journal-entries.index') }}" class="btn btn-light-brand">
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
        <!-- Entry Details -->
        <div class="col-lg-8">
            <!-- Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="40%">Entry Number:</th>
                                    <td class="fw-semibold">{{ $journalEntry->entry_number }}</td>
                                </tr>
                                <tr>
                                    <th>Entry Date:</th>
                                    <td><span class="fs-12 text-muted">{{ $journalEntry->entry_date->format('M d, Y') }}</span></td>
                                </tr>
                                <tr>
                                    <th>Source Type:</th>
                                    <td>
                                        @if ($journalEntry->reference_type === 'bill')
                                            <span class="badge bg-soft-warning text-warning">Bill Entry</span>
                                        @elseif ($journalEntry->reference_type === 'payment')
                                            <span class="badge bg-soft-success text-success">Payment Entry</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($journalEntry->reference_type) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="40%">Status:</th>
                                    <td>
                                        @if ($journalEntry->is_posted)
                                            <span class="badge bg-soft-success text-success">Posted</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $journalEntry->createdBy?->name ?? 'System' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td><span class="fs-12 text-muted">{{ $journalEntry->created_at->format('M d, Y H:i') }}</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Narration Card -->
            @if ($journalEntry->narration)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-file-text me-2"></i>Narration</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $journalEntry->narration }}</p>
                    </div>
                </div>
            @endif

            <!-- Journal Lines Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-list me-2"></i>Entry Lines</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account Code</th>
                                    <th>Account Name</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($journalEntry->lines as $index => $line)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><code>{{ $line->account->code }}</code></td>
                                        <td>
                                            <a href="{{ route('general-ledger.index', ['account_id' => $line->account->id]) }}">
                                                {{ $line->account->name }}
                                            </a>
                                        </td>
                                        <td>{{ $line->description ?? '-' }}</td>
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
                                    <td colspan="4" class="text-end fw-bold">Totals:</td>
                                    <td class="text-end fw-bold">{{ number_format($journalEntry->total_debit, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($journalEntry->total_credit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if (!$journalEntry->isBalanced())
                        <div class="alert alert-soft-danger m-3 mb-0">
                            <i class="feather-alert-triangle me-2"></i>
                            <strong>Warning:</strong> This entry is not balanced. Total Debit ({{ number_format($journalEntry->total_debit, 2) }}) does not equal Total Credit ({{ number_format($journalEntry->total_credit, 2) }}).
                        </div>
                    @endif
                </div>
            </div>

            <!-- Source Document Card -->
            @if ($reference)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-link me-2"></i>Source Document</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            @if ($journalEntry->reference_type === 'bill')
                                <tr>
                                    <th width="30%">Bill Number:</th>
                                    <td>
                                        <a href="{{ route('bills.show', $reference) }}" class="fw-semibold">
                                            {{ $reference->bill_number }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Supplier:</th>
                                    <td>{{ $reference->supplier->full_name }}</td>
                                </tr>
                                <tr>
                                    <th>Bill Amount:</th>
                                    <td>{{ number_format($reference->total_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Bill Status:</th>
                                    <td>
                                        @if ($reference->status === 'paid')
                                            <span class="badge bg-soft-success text-success">Paid</span>
                                        @elseif ($reference->status === 'draft')
                                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning">{{ ucfirst(str_replace('_', ' ', $reference->status)) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @elseif ($journalEntry->reference_type === 'payment')
                                <tr>
                                    <th width="30%">Payment Number:</th>
                                    <td>
                                        <a href="{{ route('payments.show', $reference) }}" class="fw-semibold">
                                            {{ $reference->payment_number }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Bill:</th>
                                    <td>
                                        <a href="{{ route('bills.show', $reference->bill) }}">
                                            {{ $reference->bill->bill_number }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Amount:</th>
                                    <td>{{ number_format($reference->amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td>{{ ucfirst($reference->payment_method) }} - {{ $reference->paymentAccount->name }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Summary Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-bar-chart-2 me-2"></i>Entry Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Total Debit:</th>
                            <td class="text-end fw-bold text-success">{{ number_format($journalEntry->total_debit, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Total Credit:</th>
                            <td class="text-end fw-bold text-danger">{{ number_format($journalEntry->total_credit, 2) }}</td>
                        </tr>
                        <tr class="{{ $journalEntry->isBalanced() ? 'table-success' : 'table-danger' }}">
                            <th>Balanced:</th>
                            <td class="text-end">
                                @if ($journalEntry->isBalanced())
                                    <i class="feather-check-circle text-success me-1"></i>Yes
                                @else
                                    <i class="feather-x-circle text-danger me-1"></i>No
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Accounts Affected Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-layers me-2"></i>Accounts Affected</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($journalEntry->lines as $line)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <code>{{ $line->account->code }}</code>
                                    <span class="ms-1">{{ $line->account->name }}</span>
                                </span>
                                <span>
                                    @if ($line->debit > 0)
                                        <span class="badge bg-soft-success text-success">Dr {{ number_format($line->debit, 2) }}</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger">Cr {{ number_format($line->credit, 2) }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
