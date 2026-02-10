@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Journal Entry: {{ $journalEntry->entry_number }}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('journal-entries.index') }}">Journal Entries</a></li>
                        <li class="breadcrumb-item active">{{ $journalEntry->entry_number }}</li>
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
        <div class="col-12 text-right">
            @if ($reference)
                @if ($journalEntry->reference_type === 'bill')
                    <a href="{{ route('bills.show', $reference) }}" class="btn btn-info">
                        <i class="fas fa-file-invoice mr-1"></i>View Bill
                    </a>
                @elseif ($journalEntry->reference_type === 'payment')
                    <a href="{{ route('payments.show', $reference) }}" class="btn btn-info">
                        <i class="fas fa-money-check mr-1"></i>View Payment
                    </a>
                @endif
            @endif
            <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back
            </a>
        </div>
    </div>

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
                                    <td>{{ $journalEntry->entry_number }}</td>
                                </tr>
                                <tr>
                                    <th>Entry Date:</th>
                                    <td>{{ $journalEntry->entry_date->format('M d, Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Source Type:</th>
                                    <td>
                                        @if ($journalEntry->reference_type === 'bill')
                                            <span class="badge bg-warning text-dark">Bill Entry</span>
                                        @elseif ($journalEntry->reference_type === 'payment')
                                            <span class="badge bg-success">Payment Entry</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($journalEntry->reference_type) }}</span>
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
                                            <span class="badge bg-success">Posted</span>
                                        @else
                                            <span class="badge bg-secondary">Draft</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $journalEntry->createdBy?->name ?? 'System' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $journalEntry->created_at->format('M d, Y H:i') }}</td>
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
                        <h5 class="card-title mb-0">Narration</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $journalEntry->narration }}</p>
                    </div>
                </div>
            @endif

            <!-- Journal Lines Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Entry Lines</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account Code</th>
                                    <th>Account Name</th>
                                    <th>Description</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
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
                                        <td class="text-right">
                                            @if ($line->debit > 0)
                                                {{ number_format($line->debit, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($line->credit > 0)
                                                {{ number_format($line->credit, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="4" class="text-right fw-bold">Totals:s</td>
                                    <td class="text-right fw-bold">{{ number_format($journalEntry->total_debit, 2) }}</td>
                                    <td class="text-right fw-bold">{{ number_format($journalEntry->total_credit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if (!$journalEntry->isBalanced())
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>Warning:</strong> This entry is not balanced. Total Debit ({{ number_format($journalEntry->total_debit, 2) }}) does not equal Total Credit ({{ number_format($journalEntry->total_credit, 2) }}).
                        </div>
                    @endif
                </div>
            </div>

            <!-- Source Document Card -->
            @if ($reference)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Source Document</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            @if ($journalEntry->reference_type === 'bill')
                                <tr>
                                    <th width="30%">Bill Number:</th>
                                    <td>
                                        <a href="{{ route('bills.show', $reference) }}">
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
                                        <span class="badge bg-{{ $reference->status === 'paid' ? 'success' : ($reference->status === 'draft' ? 'secondary' : 'warnings') }}">
                                            {{ ucfirst(str_replace('_', ' ', $reference->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @elseif ($journalEntry->reference_type === 'payment')
                                <tr>
                                    <th width="30%">Payment Number:</th>
                                    <td>
                                        <a href="{{ route('payments.show', $reference) }}">
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
                    <h5 class="card-title mb-0">Entry Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Total Debit:</th>
                            <td class="text-right fw-bold">{{ number_format($journalEntry->total_debit, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Total Credit:</th>
                            <td class="text-right fw-bold">{{ number_format($journalEntry->total_credit, 2) }}</td>
                        </tr>
                        <tr class="{{ $journalEntry->isBalanced() ? 'table-success' : 'table-danger' }}">
                            <th>Balanced:</th>
                            <td class="text-right">
                                @if ($journalEntry->isBalanced())
                                    <i class="fas fa-check-circle text-success mr-1"></i>Yes
                                @else
                                    <i class="fas fa-times-circle text-danger mr-1"></i>No
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Accounts Affected Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Accounts Affected</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach ($journalEntry->lines as $line)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    <code>{{ $line->account->code }}</code>
                                    {{ $line->account->name }}
                                </span>
                                <span>
                                    @if ($line->debit > 0)
                                        <span class="text-primary">Dr {{ number_format($line->debit, 2) }}</span>
                                    @else
                                        <span class="text-danger">Cr {{ number_format($line->credit, 2) }}</span>
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
