@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add New Account</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('chart-of-accounts.index') }}">Chart of Accounts</a></li>
                <li class="breadcrumb-item">Add Account</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Accounts</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <!-- Form Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Account Details</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('chart-of-accounts.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parent_id" class="form-label">Parent Group <span class="text-danger">*</span></label>
                                <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror" required>
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
                                <label for="code" class="form-label">
                                    Account Code <span class="text-danger">*</span>
                                    <small class="text-muted">(auto-generated)</small>
                                </label>
                                <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="Select a group first" required>
                                @error('code')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">You can modify if needed</small>
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

                        <!-- Bank/Cash Account Fields (Auto-show when Banks group is selected) -->
                        <input type="hidden" name="is_bank_cash" id="is_bank_cash" value="0">

                        <div id="bank-fields" class="border rounded p-3 mb-3" style="display: none;">
                            <h6 class="mb-3"><i class="feather-credit-card me-2"></i>Bank Account Details</h6>
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
                                <i class="feather-save me-2"></i>Save Account
                            </button>
                            <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-light-brand">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help Card -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-help-circle me-2"></i>Help</h5>
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
            const codeInput = document.getElementById('code');
            const isBankCash = document.getElementById('is_bank_cash');
            const bankFields = document.getElementById('bank-fields');

            // Update nature display and auto-generate code when parent changes
            parentSelect.addEventListener('change', function() {
                const parentId = this.value;
                const selected = this.options[this.selectedIndex];
                const nature = selected.dataset.nature || '';
                const groupName = selected.text.toLowerCase();

                // Update nature display
                natureDisplay.value = nature.charAt(0).toUpperCase() + nature.slice(1);

                // Show bank fields when "Banks" or "Cash" group is selected
                const isBankGroup = groupName.includes('bank') || groupName.includes('cash');
                if (isBankGroup) {
                    bankFields.style.display = 'block';
                    isBankCash.value = '1';
                } else {
                    bankFields.style.display = 'none';
                    isBankCash.value = '0';
                }

                // Fetch next code if parent is selected
                if (parentId) {
                    fetchNextCode(parentId);
                } else {
                    codeInput.value = '';
                }
            });

            // Fetch next available code via AJAX
            function fetchNextCode(parentId) {
                codeInput.classList.add('is-loading');
                codeInput.placeholder = 'Generating...';

                fetch(`{{ url('chart-of-accounts-next-code') }}/${parentId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        codeInput.value = data.code;
                    }
                })
                .catch(error => {
                    console.error('Error fetching next code:', error);
                })
                .finally(() => {
                    codeInput.classList.remove('is-loading');
                    codeInput.placeholder = 'e.g., 6008';
                });
            }

            // Trigger on page load if parent is pre-selected
            if (parentSelect.value) {
                parentSelect.dispatchEvent(new Event('change'));
            }

        });
    </script>
@endpush
