@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">General Ledger</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">General Ledger</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('general-ledger.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="" class="form-label">Account <span class="text-danger">*</span></label>
                    <select name="account_id" class="form-control" required>
                        <option value="">Select Account</option>
                        @foreach ($groups as $group)
                            <optgroup label="{{ $group->code }} - {{ $group->name }} ({{ ucfirst($group->nature) }})">
                                @foreach ($group->children as $child)
                                    <option value="{{ $child->id }}" {{ $selectedAccountId == $child->id ? 'selected' : '' }}>
                                        {{ $child->code }} - {{ $child->name }}
                                        @if ($child->is_bank_cash)
                                            (Bal: {{ number_format($child->current_balance, 2) }})
                                        @endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="" class="form-label">From Date:</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label for="" class="form-label">To Date:</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="{{ route('general-ledger.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if ($account)
        <!-- Account Header -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-1">{{ $account->code }} - {{ $account->name }}</h4>
                        <p class="text-muted mb-0">
                            <span class="badge bg-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : 'success')) }}">
                                {{ ucfirst($account->nature) }}
                            </span>
                            @if ($account->parent)
                                | Group: {{ $account->parent->name }}
                            @endif
                            @if ($account->is_bank_cash)
                                | <span class="badge bg-info">Bank/Cash Account</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6 text-md-right">
                        <h5 class="text-muted mb-0">Closing Balance</h5>
                        <h3 class="{{ $runningBalance >= 0 ? 'text-success' : 'text-danger' }} mb-0">
                            {{ number_format(abs($runningBalance), 2) }}
                            <small>{{ $runningBalance >= 0 ? 'Dr' : 'Cr' }}</small>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Entry #</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            <tr class="table-secondary">
                                <td>
                                    @if ($dateFrom)
                                        {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td colspan="3"><strong>Opening Balance</strong></td>
                                <td class="text-right">-</td>
                                <td class="text-right">-</td>
                                <td class="text-right fw-bold">{{ number_format(abs($openingBalance), 2) }}</td>
                            </tr>

                            @forelse ($transactions as $line)
                                <tr>
                                    <td>{{ $line->journalEntry->entry->date->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('journal-entries.show', $line->journalEntry) }}">
                                            {{ $line->journalEntry->entry_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($line->journalEntry->reference_type === 'bill')
                                            <span class="badge bg-warning text-dang">Bill</span>
                                        @elseif ($line->journalEntry->reference_type === 'payment')
                                            <span class="badge bg-success">Payment</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($line->journalEntry->reference_type) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $line->description ?? $line->journalEntry->narration }}</td>
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
                                    <td class="text-right fw-bold {{ $line->running_balance >= 0 ? '' : 'text-danger' }}">
                                        {{ number_format(abs($line->running_balance), 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">
                                        No transactions found for the selected period.
                                    </td>
                                </tr>
                            @endforelse

                            <!-- Closing Balance -->
                            <tr class="table-dark">
                                <td colspan="4" class="text-right fw-bold">Totals / Closing Balance:</td>
                                <td class="text-right fw-bold">{{ number_format($totalDebit, 2) }}</td>
                                <td class="text-right fw-bold">{{ number_format($totalCredit, 2) }}</td>
                                <td class="text-right fw-bold">{{ number_format(abs($runningBalance), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @else
        <!-- No Account Selected -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3">Select an Account</h4>
                <p class="text-muted">Choose an account from the dropdown above to view its ledger.</p>
            </div>
        </div>
    @endif
@endsection
