@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Chart of Accounts</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Chart of Accounts</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('chart-of-accounts-add')
                    <a href="{{ route('chart-of-accounts.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Account</span>
                    </a>
                    @endcan
                </div>
            </div>
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
                    <form action="{{ route('chart-of-accounts.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or code..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nature</label>
                                <select name="nature" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    <option value="asset" {{ request('nature') == 'asset' ? 'selected' : '' }}>Asset</option>
                                    <option value="liability" {{ request('nature') == 'liability' ? 'selected' : '' }}>Liability</option>
                                    <option value="equity" {{ request('nature') == 'equity' ? 'selected' : '' }}>Equity</option>
                                    <option value="revenue" {{ request('nature') == 'revenue' ? 'selected' : '' }}>Revenue</option>
                                    <option value="expense" {{ request('nature') == 'expense' ? 'selected' : '' }}>Expense</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Search
                                </button>
                                <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                @if ($isFiltered)
                    <!-- Flat List View (for search results) -->
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Group</th>
                                    <th>Nature</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($accounts as $account)
                                    <tr>
                                        <td><code>{{ $account->code }}</code></td>
                                        <td>
                                            <span class="fw-semibold">{{ $account->name }}</span>
                                            @if ($account->is_bank_cash)
                                                <span class="badge bg-soft-info text-info">Bank/Cash</span>
                                            @endif
                                            @if ($account->is_system)
                                                <span class="badge bg-soft-secondary text-secondary">System</span>
                                            @endif
                                        </td>
                                        <td>{{ $account->parent?->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge bg-soft-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : ($account->nature == 'revenue' ? 'success' : 'secondary'))) }} text-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : ($account->nature == 'revenue' ? 'success' : 'secondary'))) }}">
                                                {{ ucfirst($account->nature) }}
                                            </span>
                                        </td>
                                        <td>{{ ucfirst($account->type) }}</td>
                                        <td>
                                            @if ($account->is_active)
                                                <span class="badge bg-soft-success text-success">Active</span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            @include('chart-of-accounts._actions', ['account' => $account])
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No accounts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <!-- Tree View -->
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="coa-tree">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Code</th>
                                    <th>Account Name</th>
                                    <th style="width: 120px;">Nature</th>
                                    <th style="width: 120px;">Balance</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 150px;" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $group)
                                    <!-- Group Row -->
                                    <tr class="table-light group-row" data-group-id="{{ $group->id }}">
                                        <td><code>{{ $group->code }}</code></td>
                                        <td>
                                            <span class="toggle-icon me-2" style="cursor: pointer;">
                                                <i class="feather-chevron-down"></i>
                                            </span>
                                            <span class="fw-bold">{{ $group->name }}</span>
                                            @if ($group->is_system)
                                                <span class="badge bg-soft-secondary text-secondary">System</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-{{ $group->nature == 'asset' ? 'primary' : ($group->nature == 'liability' ? 'warning' : ($group->nature == 'expense' ? 'danger' : ($group->nature == 'revenue' ? 'success' : 'secondary'))) }} text-{{ $group->nature == 'asset' ? 'primary' : ($group->nature == 'liability' ? 'warning' : ($group->nature == 'expense' ? 'danger' : ($group->nature == 'revenue' ? 'success' : 'secondary'))) }}">
                                                {{ ucfirst($group->nature) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php $groupBalance = $group->getCalculatedBalance(); @endphp
                                            <span class="fw-bold {{ $groupBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($groupBalance, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($group->is_active)
                                                <span class="badge bg-soft-success text-success">Active</span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack gap-2 justify-content-end">
                                                @can('chart-of-accounts-add')
                                                    <a href="{{ route('chart-of-accounts.create', ['group_id' => $group->id]) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Add Account">
                                                        <i class="feather-plus"></i>
                                                    </a>
                                                @endcan
                                                @include('chart-of-accounts._actions', ['account' => $group])
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Child Account Rows -->
                                    @foreach ($group->children as $account)
                                        <tr class="child-row" data-parent-id="{{ $group->id }}">
                                            <td class="ps-4"><code>{{ $account->code }}</code></td>
                                            <td class="ps-5">
                                                <i class="feather-corner-down-right text-muted me-2"></i>
                                                {{ $account->name }}
                                                @if ($account->is_bank_cash)
                                                    <span class="badge bg-soft-info text-info">Bank/Cash</span>
                                                @endif
                                                @if ($account->is_system)
                                                    <span class="badge bg-soft-secondary text-secondary">System</span>
                                                @endif
                                            </td>
                                            <td>-</td>
                                            <td>
                                                @php $accountBalance = $account->getCalculatedBalance(); @endphp
                                                <span class="{{ $accountBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($accountBalance, 2) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($account->is_active)
                                                    <span class="badge bg-soft-success text-success">Active</span>
                                                @else
                                                    <span class="badge bg-soft-danger text-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @include('chart-of-accounts._actions', ['account' => $account])
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.group-row').forEach(function(row) {
                row.querySelector('.toggle-icon')?.addEventListener('click', function() {
                    const groupId = row.dataset.groupId;
                    const icon = this.querySelector('i');
                    const childRows = document.querySelectorAll(`.child-row[data-parent-id="${groupId}"]`);

                    childRows.forEach(function(childRow) {
                        childRow.classList.toggle('d-none');
                    });

                    icon.classList.toggle('feather-chevron-down');
                    icon.classList.toggle('feather-chevron-right');
                });
            });
        });
    </script>
@endpush
