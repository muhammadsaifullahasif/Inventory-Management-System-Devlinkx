@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Edit Accounts</h1>
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
    <!-- Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ $chartOfAccount->code }} - {{ $chartOfAccount->name }}</h5>
                    @if($chartOfAccount->is_system)
                    <span class="badge bg-secondary">System Account</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($chartOfAccount->is_system)
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        This is a system account. Only description and status can be modified.
                    </div>
                    @endif

                    <form action="{{ route('chart-of-accounts.update', $chartOfAccount) }}" method="POST">
                        @csrf
                        @method('PUT')

                        @if(!$chartOfAccount->is_system)
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parent_id" class="form-label">Parent Group <span class="text-danger">*</span></label>
                                <select name="parent_id" id="parent_id" class="form-control @error('parent_id') is-invalid @enderror" required>
                                    <option value="">Select Group</option>
                                    @foreach($groups as $group)
                                    <option value="{{ $group->id }}" 
                                            data-nature="{{ $group->nature }}"
                                            {{ old('parent_id', $chartOfAccount->parent_id) == $group->id ? 'selected' : '' }}>
                                        {{ $group->code }} - {{ $group->name }} ({{ ucfirst($group->nature) }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="nature_display" class="form-label">Nature</label>
                                <input type="text" id="nature_display" class="form-control" 
                                       value="{{ ucfirst($chartOfAccount->nature) }}" readonly disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="code" 
                                       class="form-control @error('code') is-invalid @enderror" 
                                       value="{{ old('code', $chartOfAccount->code) }}" 
                                       required>
                                @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name', $chartOfAccount->name) }}"
                                       required>
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" rows="2" 
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $chartOfAccount->description) }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" 
                                       class="form-check-input" value="1"
                                       {{ old('is_active', $chartOfAccount->is_active) ? 'checked' : '' }}>
                                <label for="is_active" class="form-check-label">
                                    Active
                                </label>
                            </div>
                        </div>

                        @if(!$chartOfAccount->is_system)
                        <!-- Bank/Cash Account Fields -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_bank_cash" id="is_bank_cash" 
                                       class="form-check-input" value="1"
                                       {{ old('is_bank_cash', $chartOfAccount->is_bank_cash) ? 'checked' : '' }}>
                                <label for="is_bank_cash" class="form-check-label">
                                    This is a Bank or Cash account
                                </label>
                            </div>
                        </div>

                        <div id="bank-fields" class="border rounded p-3 mb-3" 
                             style="display: {{ $chartOfAccount->is_bank_cash ? 'block' : 'none' }};">
                            <h6 class="mb-3">Bank Account Details</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" id="bank_name" 
                                           class="form-control @error('bank_name') is-invalid @enderror"
                                           value="{{ old('bank_name', $chartOfAccount->bank_name) }}">
                                    @error('bank_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" name="account_number" id="account_number" 
                                           class="form-control @error('account_number') is-invalid @enderror"
                                           value="{{ old('account_number', $chartOfAccount->account_number) }}">
                                    @error('account_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="branch" class="form-label">Branch</label>
                                    <input type="text" name="branch" id="branch" 
                                           class="form-control @error('branch') is-invalid @enderror"
                                           value="{{ old('branch', $chartOfAccount->branch) }}">
                                    @error('branch')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="iban" class="form-label">IBAN</label>
                                    <input type="text" name="iban" id="iban" 
                                           class="form-control @error('iban') is-invalid @enderror"
                                           value="{{ old('iban', $chartOfAccount->iban) }}">
                                    @error('iban')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="opening_balance" class="form-label">Opening Balance</label>
                                    <input type="number" name="opening_balance" id="opening_balance" 
                                           class="form-control @error('opening_balance') is-invalid @enderror"
                                           value="{{ old('opening_balance', $chartOfAccount->opening_balance) }}"
                                           step="0.01" min="0">
                                    @error('opening_balance')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Current Balance: {{ number_format($chartOfAccount->current_balance, 2) }}</small>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Update Account
                            </button>
                            <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x-lg"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
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
                            <th>Created:</th>
                            <td>{{ $chartOfAccount->created_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td>{{ $chartOfAccount->updated_at->format('M d, Y') }}</td>
                        </tr>
                        @if($chartOfAccount->is_bank_cash)
                        <tr>
                            <th>Current Balance:</th>
                            <td class="{{ $chartOfAccount->current_balance >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                {{ number_format($chartOfAccount->current_balance, 2) }}
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($chartOfAccount->journalLines()->count() > 0)
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0 text-warning">
                        <i class="bi bi-exclamation-triangle"></i> Warning
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        This account has <strong>{{ $chartOfAccount->journalLines()->count() }}</strong> transaction(s). 
                        Changes to this account will affect financial reports.
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const parentSelect = document.getElementById('parent_id');
            const natureDisplay = document.getElementById('nature_display');
            const isBankCash = document.getElementById('is_bank_cash');
            const bankFields = document.getElementById('bank-fields');

            // Update nature display when parent changes
            if (parentSelect) {
                parentSelect.addEventListener('change', function() {
                    const selected = this.options[this.selectedIndex];
                    const nature = selected.dataset.nature || '';
                    natureDisplay.value = nature.charAt(0).toUpperCase() + nature.slice(1);
                });
            }

            // Toggle bank fields
            if (isBankCash) {
                isBankCash.addEventListener('change', function() {
                    bankFields.style.display = this.checked ? 'block' : 'none';
                });
            }
        });
    </script>
@endpush
