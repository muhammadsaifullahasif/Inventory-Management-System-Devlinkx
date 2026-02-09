@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2"> View Accounts</h1>
                    @can('chart-of-accounts-add')
                        <a href="{{ route('chart-of-accounts.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Account</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Chart of Accounts</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="row">
        <!-- Account Details Card -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Account Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover table-sm">
                        <tr>
                            <th width="40%">Code:</th>
                            <td><code>{{ $chartOfAccount->code }}</code></td>
                        </tr>
                        <tr>
                            <th>Name:</th>
                            <td>{{ $chartOfAccount->name }}</td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>{{ ucfirst($chartOfAccount->type) }}</td>
                        </tr>
                        <tr>
                            <th>Nature:</th>
                            <td>
                                <span class="badge bg-{{ $chartOfAccount->nature == 'asset' ? 'primary' : ($chartOfAccount->nature == 'liability' ? 'warning' : ($chartOfAccount->nature == 'expense' ? 'danger' : 'success')) }}">
                                    {{ ucfirst($chartOfAccount->nature) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Parent Group:</th>
                            <td>{{ $chartOfAccount->parent?->name ?? 'None (Root)' }}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if ($chartOfAccount->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>System account:</th>
                            <td>{{ $chartOfAccount->is_system ? 'Yes' : 'No' }}</td>
                        </tr>
                    </table>

                    @if ($chartOfAccount->description)
                        <hr>
                        <h6>Description</h6>
                        <p class="text-muted small mb-0">{{ $chartOfAccount->description }}</p>
                    @endif
                </div>
            </div>

            @if ($chartOfAccount->is_bank_cash)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bank Details</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            @if ($chartOfAccount->bank_name)
                                <tr>
                                    <th>Bank Name:</th>
                                    <td>{{ $chartOfAccount->bank_name }}</td>
                                </tr>
                            @endif
                            @if ($chartOfAccount->account_number)
                                <tr>
                                    <th>Account No:</th>
                                    <td>{{ $chartOfAccount->account_number }}</td>
                                </tr>
                            @endif
                            @if ($chartOfAccount->branch)
                                <tr>
                                    <th>Branch:</th>
                                    <td>{{ $chartOfAccount->branch }}</td>
                                </tr>
                            @endif
                            @if ($chartOfAccount->iban)
                                <tr>
                                    <th>IBAN</th>
                                    <td><code>{{ $chartOfAccount->iban }}</code></td>
                                </tr>
                            @endif
                            <tr>
                                <th>Opening Balance:</th>
                                <td>{{ number_format($chartOfAccount->opening_balance, 2) }}</td>
                            </tr>
                            <tr class="table-light">
                                <th>Current Balance:</th>
                                <td class="{{ $chartOfAccount->current_balance >= 0 ? 'text-success' : 'text-danger' }} fw-bold fs-5">
                                    {{ number_format($chartOfAccount->current_balance, 2) }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Balance Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Balance Summary</h5>
                </div>
                <div class="card-body text-center">
                    <h2 class="{{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($balance, 2) }}
                    </h2>
                    <p class="text-muted mb-0">Calculated Balance</p>
                </div>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Transactions</h5>
                    @if ($chartOfAccount->journalLines()->count() > 10)
                        <a href="{{ route('general-ledger.account', $chartOfAccount) }}" class="btn btn-outline-primary btn-sm">View All</a>
                    @endif
                </div>
                <div class="card-body">
                    @if ($recentTransactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Entry #</th>
                                        <th>Description</th>
                                        <th class="text-right">Debit</th>
                                        <th class="text-right">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentTransactions as $line)
                                        <tr>
                                            <td>{{ $line->journalEntry->entry_date->format('M d, Y') }}</td>
                                            <td>
                                                <a href="{{ route('journal-entries.show', $line->journalEntry) }}">
                                                    {{ $line->journalEntry->entry_number }}
                                                </a>
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
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-book text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No transactions found for this account.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if ($chartOfAccount->isGroup() && $chartOfAccount->children->count() > 0)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Child Accounts</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Balance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chartOfAccount->children as $child)
                                        <tr>
                                            <td><code>{{ $child->code }}</code></td>
                                            <td>
                                                {{ $child->name }}
                                                @if ($child->is_bank_cash)
                                                    <span class="badge bg-info">Bank/Cash</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($child->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($child->is_bank_cash)
                                                    {{ number_format($child->current_balance, 2) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('chart-of-accounts.show', $child) }}" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
