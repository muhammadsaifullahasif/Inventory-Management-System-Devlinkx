@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Chart of Accounts</h1>
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
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('chart-of-accounts.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="" class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by name or code..."
                        value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label for="" class="form-label">Nature</label>
                    <select name="nature" class="form-control">
                        <option value="">All Types</option>
                        <option value="asset" {{ request('nature') == 'asset' ? 'selected' : '' }}>Asset</option>
                        <option value="liability" {{ request('nature') == 'liability' ? 'selected' : '' }}>Liability</option>
                        <option value="equity" {{ request('nature') == 'equity' ? 'selected' : '' }}>Equity</option>
                        <option value="revenue" {{ request('nature') == 'revenue' ? 'selected' : '' }}>Revenue</option>
                        <option value="expense" {{ request('nature') == 'expense' ? 'selected' : '' }}>Expense</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card">
        <div class="card-body">
            @if ($isFiltered)
                <!-- Flat List View (for search results) -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Group</th>
                                <th>Nature</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($accounts as $account)
                                <tr>
                                    <td><code>{{ $account->code }}</code></td>
                                    <td>
                                        {{ $account->name }}
                                        @if ($account->is_bank_cash)
                                            <span class="badge bg-info">Bank/Cash</span>
                                        @endif
                                        @if ($account->is_system)
                                            <span class="badge bg-secondary">System</span>
                                        @endif
                                    </td>
                                    <td>{{ $account->parent?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $account->nature == 'asset' ? 'primary' : ($account->nature == 'liability' ? 'warning' : ($account->nature == 'expense' ? 'danger' : ($account->nature == 'revenue' ? 'success' : 'secondary'))) }}">
                                            {{ ucfirst($account->nature) }}
                                        </span>
                                    </td>
                                    <td>{{ ucfirst($account->type) }}</td>
                                    <td>
                                        @if ($account->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @include('chart-of-accounts._actions', ['account' => $account])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">No accounts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Tree View -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm" id="coa-tree">
                        <thead>
                            <tr>
                                <th width="120">Code</th>
                                <th>Account Name</th>
                                <th width="120">Nature</th>
                                <th width="120">Balance</th>
                                <th width="100">Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $group)
                                <!-- Group Row -->
                                <tr class="table-light fw-bold group-row" data-group-id="{{ $group->id }}">
                                    <td><code>{{ $group->code }}</code></td>
                                    <td>
                                        <span class="toggle-icon" style="cursor: pointer;">
                                            <i class="bi bi-chevron-down"></i>
                                        </span>
                                        {{ $group->name }}
                                        @if ($group->is_system)
                                            <span class="badge bg-secondary">System</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $group->nature == 'asset' ? 'primary' : ($group->nature == 'liability' ? 'warning' : ($group->nature == 'expense' ? 'danger' : ($group->nature == 'revenue' ? 'success' : 'secondary'))) }}">
                                            {{ ucfirst($group->nature) }}
                                        </span>
                                    </td>
                                    <td>-</td>
                                    <td>
                                        @if ($group->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can('chart-of-accounts-add')
                                            <a href="{{ route('chart-of-accounts.create', ['group_id' => $group->id]) }}" class="btn btn-outline-primary btn-sm" title="Add Account">
                                                <i class="bi bi-plus"></i>
                                            </a>
                                        @endcan
                                        @include('chart-of-accounts._actions',['account' => $group])
                                    </td>
                                </tr>

                                <!-- Child Account Row -->
                                @foreach ($group->children as $account)
                                    <tr class="child-row" data-parent-id="{{ $group->id }}">
                                        <td class="ps-4"><code>{{ $account->code }}</code></td>
                                        <td class="ps-5">
                                            <i class="bi bi-arrow-return-right text-muted me-2"></i>
                                            {{ $account->name }}
                                            @if ($account->is_bank_cash)
                                                <span class="badge bg-info">Bank/Cash</span>
                                            @endif
                                            @if ($account->is_system)
                                                <span class="badge bg-secondary">System</span>
                                            @endif
                                        </td>
                                        <td>-</td>
                                        <td>
                                            @if ($account->is_bank_cash)
                                                <span class="{{ $account->current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($account->current_balance, 2) }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if ($account->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
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
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle group expansion
            document.querySelectorAll('.group-row').forEach(function(row) {
                row.querySelector('.toggle-icon')?.addEventListener('click', function() {
                    const groupId = row.dataset.groupId;
                    const icon = this.querySelector('i');
                    const childRows = document.querySelectorAll(`.child-row[data-parent-id="${groupId}"]`);

                    childRows.forEach(function(childRow) {
                        childRow.classList.toggle('d-none');
                    });

                    icon.classList.toggle('bi-chevron-down');
                    icon.classList.toggle('bi-chevron-right');
                });
            });
        });
    </script>
@endpush
