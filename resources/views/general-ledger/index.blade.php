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
                    <label class="form-label">Account</label>
                    <select name="account_id" class="form-control">
                        <option value="all" {{ $selectedAccountId === 'all' ? 'selected' : '' }}>-- All Accounts --</option>
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
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="{{ route('general-ledger.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo mr-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if ($showAll)
        {{-- ==================== ALL ACCOUNTS VIEW ==================== --}}

        <!-- Summary Bar -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h4>{{ number_format($totalDebit, 2) }}</h4>
                        <p>Total Debit</p>
                    </div>
                    <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h4>{{ number_format($totalCredit, 2) }}</h4>
                        <p>Total Credit</p>
                    </div>
                    <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h4>{{ $transactions->count() }}</h4>
                        <p>Total Entries</p>
                    </div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
        </div>

        <!-- All Accounts Ledger Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    All Accounts: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr class="table-dark">
                                <th>Date</th>
                                <th>Entry #</th>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $line)
                                <tr>
                                    <td>{{ $line->journalEntry->entry_date->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('journal-entries.show', $line->journalEntry) }}">
                                            {{ $line->journalEntry->entry_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('general-ledger.index', ['account_id' => $line->account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}">
                                            <code>{{ $line->account->code }}</code> {{ $line->account->name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($line->journalEntry->reference_type === 'bill')
                                            <span class="badge bg-warning text-dark">Bill</span>
                                        @elseif ($line->journalEntry->reference_type === 'payment')
                                            <span class="badge bg-success">Payment</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($line->journalEntry->reference_type) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $line->description ?? $line->journalEntry->narration }}</td>
                                    <td class="text-right">
                                        {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                    </td>
                                    <td class="text-right">
                                        {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">
                                        No transactions found for the selected period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($transactions->isNotEmpty())
                            <tfoot>
                                <tr class="table-dark fw-bold">
                                    <td colspan="5" class="text-right">Totals:</td>
                                    <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

    @else
        {{-- ==================== SINGLE ACCOUNT VIEW ==================== --}}

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

        <!-- Single Account Ledger Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr class="table-dark">
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
                            <!-- Opening Balance -->
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
                                    <td>{{ $line->journalEntry->entry_date->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('journal-entries.show', $line->journalEntry) }}">
                                            {{ $line->journalEntry->entry_number }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($line->journalEntry->reference_type === 'bill')
                                            <span class="badge bg-warning text-dark">Bill</span>
                                        @elseif ($line->journalEntry->reference_type === 'payment')
                                            <span class="badge bg-success">Payment</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($line->journalEntry->reference_type) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $line->description ?? $line->journalEntry->narration }}</td>
                                    <td class="text-right">
                                        {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                    </td>
                                    <td class="text-right">
                                        {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
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
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td colspan="4" class="text-right">Totals / Closing Balance:</td>
                                <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                                <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                                <td class="text-right">{{ number_format(abs($runningBalance), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
