@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">View Account</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('chart-of-accounts.index') }}">Chart of Accounts</a></li>
                <li class="breadcrumb-item">{{ $chartOfAccount->name }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Accounts</span>
                    </a>
                    @can('chart-of-accounts-edit')
                        <a href="{{ route('chart-of-accounts.edit', $chartOfAccount) }}" class="btn btn-primary">
                            <i class="feather-edit me-2"></i>
                            <span>Edit Account</span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <!-- Account Details Card -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-file-text me-2"></i>Account Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Code:</th>
                            <td><code>{{ $chartOfAccount->code }}</code></td>
                        </tr>
                        <tr>
                            <th>Name:</th>
                            <td class="fw-semibold">{{ $chartOfAccount->name }}</td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>{{ ucfirst($chartOfAccount->type) }}</td>
                        </tr>
                        <tr>
                            <th>Nature:</th>
                            <td>
                                <span class="badge bg-soft-{{ $chartOfAccount->nature == 'asset' ? 'primary' : ($chartOfAccount->nature == 'liability' ? 'warning' : ($chartOfAccount->nature == 'expense' ? 'danger' : 'success')) }} text-{{ $chartOfAccount->nature == 'asset' ? 'primary' : ($chartOfAccount->nature == 'liability' ? 'warning' : ($chartOfAccount->nature == 'expense' ? 'danger' : 'success')) }}">
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
                                    <span class="badge bg-soft-success text-success">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>System account:</th>
                            <td>
                                @if ($chartOfAccount->is_system)
                                    <span class="badge bg-soft-secondary text-secondary">Yes</span>
                                @else
                                    No
                                @endif
                            </td>
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
                        <h5 class="card-title"><i class="feather-credit-card me-2"></i>Bank Details</h5>
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
                                    <td><code>{{ $chartOfAccount->account_number }}</code></td>
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
                                    <th>IBAN:</th>
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
                    <h5 class="card-title"><i class="feather-dollar-sign me-2"></i>Balance Summary</h5>
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
                    <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Recent Transactions</h5>
                    @if ($chartOfAccount->journalLines()->count() > 10)
                        <a href="{{ route('general-ledger.account', $chartOfAccount) }}" class="btn btn-sm btn-light-brand">
                            <i class="feather-external-link me-1"></i>View All
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if ($recentTransactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Entry #</th>
                                        <th>Description</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentTransactions as $line)
                                        <tr>
                                            <td><span class="fs-12 text-muted">{{ $line->journalEntry->entry_date->format('M d, Y') }}</span></td>
                                            <td>
                                                <a href="{{ route('journal-entries.show', $line->journalEntry) }}" class="fw-semibold">
                                                    {{ $line->journalEntry->entry_number }}
                                                </a>
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
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="feather-book-open text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">No transactions found for this account.</p>
                        </div>
                    @endif
                </div>
            </div>

            @if ($chartOfAccount->isGroup() && $chartOfAccount->children->count() > 0)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="feather-layers me-2"></i>Child Accounts</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Balance</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($chartOfAccount->children as $child)
                                        <tr>
                                            <td><code>{{ $child->code }}</code></td>
                                            <td>
                                                <span class="fw-semibold">{{ $child->name }}</span>
                                                @if ($child->is_bank_cash)
                                                    <span class="badge bg-soft-info text-info">Bank/Cash</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($child->is_active)
                                                    <span class="badge bg-soft-success text-success">Active</span>
                                                @else
                                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($child->is_bank_cash)
                                                    <span class="{{ $child->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ number_format($child->current_balance, 2) }}
                                                    </span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="hstack gap-2 justify-content-end">
                                                    <a href="{{ route('chart-of-accounts.show', $child) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                </div>
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
