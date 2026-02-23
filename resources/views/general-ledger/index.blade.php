@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">General Ledger</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">General Ledger</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
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
                    <form action="{{ route('general-ledger.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Account</label>
                                <select name="account_id" class="form-select form-select-sm">
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
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('general-ledger.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($showAll)
        {{-- ==================== ALL ACCOUNTS VIEW ==================== --}}

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-soft-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Total Debit</h6>
                                <h3 class="mb-0 fw-bold">{{ number_format($totalDebit, 2) }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-primary text-white rounded">
                                <i class="feather-trending-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-soft-danger">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Total Credit</h6>
                                <h3 class="mb-0 fw-bold">{{ number_format($totalCredit, 2) }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-danger text-white rounded">
                                <i class="feather-trending-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-soft-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Total Entries</h6>
                                <h3 class="mb-0 fw-bold">{{ $transactions->count() }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-info text-white rounded">
                                <i class="feather-list"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Accounts Ledger Table -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="feather-book-open me-2"></i>All Accounts: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entry #</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transactions as $line)
                                    <tr>
                                        <td><span class="fs-12 text-muted">{{ $line->journalEntry->entry_date->format('M d, Y') }}</span></td>
                                        <td>
                                            <a href="{{ route('journal-entries.show', $line->journalEntry) }}" class="fw-semibold">
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
                                                <span class="badge bg-soft-warning text-warning">Bill</span>
                                            @elseif ($line->journalEntry->reference_type === 'payment')
                                                <span class="badge bg-soft-success text-success">Payment</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($line->journalEntry->reference_type) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $line->description ?? $line->journalEntry->narration }}</td>
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
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="feather-book-open" style="font-size: 3rem;"></i>
                                            <p class="mt-3">No transactions found for the selected period.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($transactions->isNotEmpty())
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="5" class="text-end fw-bold">Totals:</td>
                                        <td class="text-end fw-bold">{{ number_format($totalDebit, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($totalCredit, 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- ==================== SINGLE ACCOUNT VIEW ==================== --}}

        <!-- Account Header -->
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-1 fw-bold">{{ $account->code }} - {{ $account->name }}</h4>
                            <p class="text-muted mb-0">
                                <span class="badge bg-soft-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : 'success')) }} text-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : 'success')) }}">
                                    {{ ucfirst($account->nature) }}
                                </span>
                                @if ($account->parent)
                                    <span class="ms-2">Group: {{ $account->parent->name }}</span>
                                @endif
                                @if ($account->is_bank_cash)
                                    <span class="badge bg-soft-info text-info ms-2">Bank/Cash Account</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h6 class="text-muted mb-1">Closing Balance</h6>
                            <h3 class="{{ $runningBalance >= 0 ? 'text-success' : 'text-danger' }} mb-0 fw-bold">
                                {{ number_format(abs($runningBalance), 2) }}
                                <small class="fs-6">{{ $runningBalance >= 0 ? 'Dr' : 'Cr' }}</small>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Account Ledger Table -->
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Entry #</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Opening Balance -->
                                <tr class="table-light">
                                    <td>
                                        @if ($dateFrom)
                                            <span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td colspan="3"><strong>Opening Balance</strong></td>
                                    <td class="text-end">-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end fw-bold">{{ number_format(abs($openingBalance), 2) }}</td>
                                </tr>

                                @forelse ($transactions as $line)
                                    <tr>
                                        <td><span class="fs-12 text-muted">{{ $line->journalEntry->entry_date->format('M d, Y') }}</span></td>
                                        <td>
                                            <a href="{{ route('journal-entries.show', $line->journalEntry) }}" class="fw-semibold">
                                                {{ $line->journalEntry->entry_number }}
                                            </a>
                                        </td>
                                        <td>
                                            @if ($line->journalEntry->reference_type === 'bill')
                                                <span class="badge bg-soft-warning text-warning">Bill</span>
                                            @elseif ($line->journalEntry->reference_type === 'payment')
                                                <span class="badge bg-soft-success text-success">Payment</span>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($line->journalEntry->reference_type) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $line->description ?? $line->journalEntry->narration }}</td>
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
                                        <td class="text-end fw-bold {{ $line->running_balance >= 0 ? '' : 'text-danger' }}">
                                            {{ number_format(abs($line->running_balance), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No transactions found for the selected period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Totals / Closing Balance:</td>
                                    <td class="text-end fw-bold">{{ number_format($totalDebit, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($totalCredit, 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format(abs($runningBalance), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
