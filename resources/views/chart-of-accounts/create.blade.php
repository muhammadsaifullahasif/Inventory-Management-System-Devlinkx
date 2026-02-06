@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Add New Accounts</h1>
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
                <div class="card-header">
                    <h5 class="card-title mb-0">Account Details</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('chart-of-accounts.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parent_id" class="form-label">Parent Group <span class="text-danger">*</span></label>
                                <select name="parent_id" id="parent_id" class="form-control @error('parent_id') is-invalid @enderror" required>
                                    <option value="">Select Group</option>
                                    @foreach ($groups as $group)
                                        <option value="{{ $group->id }}" data-nature="{{ $group->nature }}" {{ (old('parent_id') ?? $selectedGroup) == $group->id ? 'selected' : '' }}>
                                            {{ $group->code }} - {{ $group->name }} ({{ ucfirst($group->nature) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="nature_display" class="form-label">Nature</label>
                                <input type="text" id="nature_display" class="form-control" readonly disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="code" class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="e.g., 6008" required>
                                @error('code')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label for="name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g., Travel Expense">
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="Optional description...">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Bank/Cash Account Fields -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_bank_cash" id="is_bank_cash" class="form-check-input" value="1" {{ old('is_bank_cash') ? 'checked' : '' }}>
                                <label for="is_bank_cash" class="form-check-label">This is a Bank or Cash account</label>
                            </div>
                        </div>

                        <div id="bank-fields" class="border rounded p-3 mb-3" style="display: none;">
                            <h6 class="mb-3">Bank Account Details</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" id="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ old('bank_name') }}" placeholder="e.g., MCB, HBL">

                                    @error('bank_name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" name="account_number" id="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number') }}">
                                    @error('account_number')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="branch" class="form-label">Branch</label>
                                    <input type="text" name="branch" id="branch" class="form-control @error('branch') is-invalid @enderror" value="{{ old('branch') }}">
                                    @error('branch')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="iban" class="form-label">IBAN</label>
                                    <input type="text" name="iban" id="iban" class="form-control @error('iban') is-invalid @enderror" value="{{ old('iban') }}">
                                    @error('iban')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="opening_balance" class="form-label">Opening Balance</label>
                                    <input type="number" name="opening_balance" id="opening_balance" class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance', 0) }}" step="0.01" min="0">
                                    @error('opening_balance')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Save Account
                            </button>
                            <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help Card -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Help</h5>
                </div>
                <div class="card-body">
                    <h6>Account Types</h6>
                    <ul class="small">
                        <li><strong>Asset:</strong> Cash, Bank, Inventory, Receivables</li>
                        <li><strong>Liability:</strong> Payables, Loans</li>
                        <li><strong>Revenue:</strong> Sales, Income</li>
                        <li><strong>Expense:</strong> Costs, Operating Expenses</li>
                    </ul>

                    <h6 class="mt-3">Account Codes</h6>
                    <p class="small text-muted">
                        Use a consistent numbering system. For example:
                    </p>
                    <ul class="small">
                        <li>1xxx - Assets</li>
                        <li>2xxx - Liabilities</li>
                        <li>4xxx - Revenue</li>
                        <li>5xxx-8xxx - Expenses</li>
                    </ul>
                </div>
            </div>
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
            parentSelect.addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const nature = selected.dataset.nature || '';
                natureDisplay.value = nature.charAt(0).toUpperCase() + nature.slice(1);
            });

            // Trigger on page load
            if (parentSelect.value) {
                parentSelect.dispatchEvent(new Event('change'));
            }

            // Toggle bank fields
            isBankCash.addEventListener('change', function() {
                bankFields.style.display = this.checked ? 'block' : 'none';
            });

            // Trigger on page load
            if (isBankCash.checked) {
                bankFields.style.display = 'block';
            }
        });
    </script>
@endpush
