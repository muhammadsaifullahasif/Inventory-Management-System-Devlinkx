@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Create New Bill</h1>
                    @can('bills-add')
                        <a href="{{ route('bills.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add New Bill</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('bills.index') }}">Bills</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Form -->
    <form action="{{ route('bills.store') }}" method="POST" id="billForm">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Bill Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                <select name="supplier_id" id="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror" required>
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <span class="invalid-feedbak">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="bill_date" class="form-label">Bill Date <span class="text-danger">*</span></label>
                                <input type="date" name="bill_date" id="bill_date" class="form-control @error('bill_date') is-invalid @enderror" value="{{ old('bill_date', date('Y-m-d')) }}" required>
                                @error('bill_date')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}">
                                @error('due_date')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Items Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Expense Items</h5>
                            <button type="button" class="btn btn-primary btn-sm" id="addItemBtn">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th width="200">Expense Group</th>
                                        <th width="200">Account Head</th>
                                        <th>Description</th>
                                        <th width="150">Amount</th>
                                        <th width="50"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- Items will be added here -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-right fw-bold">Total:</td>
                                        <td class="fw-bold">
                                            <span id="totalAmount">0.00</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Notes Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Optional notes...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Items:</th>
                                <td class="text-right"><span id="itemCount">0</span></td>
                            </tr>
                            <tr class="table-light">
                                <th>Total Amount:</th>
                                <td class="text-right fw-bold"><span id="summaryTotal">0.00</span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="status" id="statusField" value="draft">
                        <input type="hidden" name="action" id="actionField" value="save">

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-secondary" onclick="setAction('draft', 'save')">
                                <i class="fas fa-file mr-1"></i>Save as Draft
                            </button>
                            <button type="submit" class="btn btn-primary" onclick="setAction('unpaid', 'save')">
                                <i class="fas fa-check mr-1"></i>Save & Post
                            </button>
                            <button type="submit" class="btn btn-outline-primary" onclick="setAction('unpaid', 'save_new')">
                                <i class="fas fa-plus mr-1"></i>Save & New
                            </button>
                            <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Item Row Template -->
    <template id="itemRowTemplate">
        <tr class="item-row">
            <td>
                <select name="items[INDEX][expense_group_id]" class="form-control form-control-sm expense-group" required>
                    <option value="">Select Group</option>
                    @foreach ($expenseGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <div class="account-input-wrapper">
                    <input type="hidden" name="items[INDEX][expense_account_id]" class="expense-account-id">
                    <input type="text" name="items[INDEX][expense_account_name]" class="form-control form-control-sm account-search" placeholder="Search or type new..." autocomplete="off" required>
                    <div class="account-dropdown"></div>
                </div>
            </td>
            <td>
                <input type="text" name="items[INDEX][description]" class="form-control form-control-sm" placeholder="Description" required>
            </td>
            <td>
                <input type="number" name="items[INDEX][amount]" class="form-control form-control-sm item-amount" placeholder="0.00" step="0.01" min="0.01" required>
            </td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    </template>
@endsection

@php
    $expenseAccountsJson = $expenseGroups->mapWithKeys(function($group) {
        return [$group->id => $group->children->map(function($child) {
            return ['id' => $child->id, 'code' => $child->code, 'name' => $child->name];
        })];
    });
@endphp

@push('scripts')
    <script>
        const expenseAccountsByGroup = @json($expenseAccountsJson);

        let itemIndex = 0;

        document.addEventListener('DOMContentLoaded', function() {
            addItemRow();

            document.getElementById('addItemBtn').addEventListener('click', () => addItemRow());

            // Delegate events on itemsBody
            const itemsBody = document.getElementById('itemsBody');

            itemsBody.addEventListener('click', function(e) {
                if (e.target.closest('.remove-item')) {
                    removeItemRow(e.target.closest('.item-row'));
                }
            });

            itemsBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('expense-group')) {
                    // Group changed — clear the account input
                    const row = e.target.closest('.item-row');
                    const accountIdInput = row.querySelector('.expense-account-id');
                    const accountSearch = row.querySelector('.account-search');
                    accountIdInput.value = '';
                    accountSearch.value = '';
                    accountSearch.classList.remove('has-account');
                }
                if (e.target.classList.contains('item-amount')) {
                    updateTotals();
                }
            });

            itemsBody.addEventListener('input', function(e) {
                if (e.target.classList.contains('item-amount')) {
                    updateTotals();
                }
                if (e.target.classList.contains('account-search')) {
                    onAccountInput(e.target);
                }
            });

            // Focus — show dropdown
            itemsBody.addEventListener('focusin', function(e) {
                if (e.target.classList.contains('account-search')) {
                    showAccountDropdown(e.target);
                }
            });

            // Close dropdown on click outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.account-input-wrapper')) {
                    document.querySelectorAll('.account-dropdown').forEach(d => d.style.display = 'none');
                }
            });
        });

        function addItemRow(data = null) {
            const template = document.getElementById('itemRowTemplate');
            const clone = template.content.cloneNode(true);

            clone.querySelectorAll('[name*="INDEX"]').forEach(el => {
                el.name = el.name.replace('INDEX', itemIndex);
            });

            document.getElementById('itemsBody').appendChild(clone);

            // If data provided, populate
            if (data) {
                const addedRow = document.getElementById('itemsBody').lastElementChild;
                const groupSelect = addedRow.querySelector('.expense-group');
                const accountIdInput = addedRow.querySelector('.expense-account-id');
                const accountSearch = addedRow.querySelector('.account-search');
                const descInput = addedRow.querySelector('[name*="description"]');
                const amountInput = addedRow.querySelector('[name*="amount"]');

                groupSelect.value = data.expense_group_id;
                accountIdInput.value = data.expense_account_id;

                // Find account name from local data
                const groupAccounts = expenseAccountsByGroup[data.expense_group_id] || [];
                const account = groupAccounts.find(a => a.id == data.expense_account_id);
                if (account) {
                    accountSearch.value = account.code + ' - ' + account.name;
                    accountSearch.classList.add('has-account');
                }

                descInput.value = data.description;
                amountInput.value = data.amount;
            }

            itemIndex++;
            updateTotals();
        }

        function removeItemRow(row) {
            const tbody = document.getElementById('itemsBody');
            if (tbody.querySelectorAll('.item-row').length > 1) {
                row.remove();
                updateTotals();
            } else {
                alert('At least one item is required.');
            }
        }

        function showAccountDropdown(input) {
            const row = input.closest('.item-row');
            const groupId = row.querySelector('.expense-group').value;
            const dropdown = row.querySelector('.account-dropdown');

            if (!groupId) {
                dropdown.innerHTML = '<div class="dd-empty">Please select an Expense Group first</div>';
                dropdown.style.display = 'block';
                return;
            }

            renderDropdown(input, groupId, input.value.trim());
            dropdown.style.display = 'block';
        }

        function onAccountInput(input) {
            const row = input.closest('.item-row');
            const groupId = row.querySelector('.expense-group').value;
            const accountIdInput = row.querySelector('.expense-account-id');
            const dropdown = row.querySelector('.account-dropdown');

            // Clear the hidden ID (user is typing / changing)
            accountIdInput.value = '';
            input.classList.remove('has-account');

            if (!groupId) {
                dropdown.innerHTML = '<div class="dd-empty">Please select an Expense Group first</div>';
                dropdown.style.display = 'block';
                return;
            }

            renderDropdown(input, groupId, input.value.trim());
            dropdown.style.display = 'block';
        }

        function renderDropdown(input, groupId, searchText) {
            const row = input.closest('.item-row');
            const dropdown = row.querySelector('.account-dropdown');
            const accounts = expenseAccountsByGroup[groupId] || [];

            let filtered = accounts;
            if (searchText) {
                const lower = searchText.toLowerCase();
                filtered = accounts.filter(a =>
                    a.name.toLowerCase().includes(lower) ||
                    a.code.toLowerCase().includes(lower)
                );
            }

            let html = '';

            // Show matching accounts
            filtered.forEach(account => {
                html += `<div class="dd-item" data-id="${account.id}" data-code="${account.code}" data-name="${account.name}">
                    <code>${account.code}</code> - ${account.name}
                </div>`;
            });

            // If search text exists and no exact name match, show "create new" option
            if (searchText && searchText.length >= 2) {
                const exactMatch = accounts.find(a => a.name.toLowerCase() === searchText.toLowerCase());
                if (!exactMatch) {
                    html += `<div class="dd-new" data-new-name="${searchText}">
                        <i class="fas fa-plus mr-1"></i> Create "<strong>${searchText}</strong>" as new account
                    </div>`;
                }
            }

            if (!html) {
                html = '<div class="dd-empty">No accounts found. Type a name to create new.</div>';
            }

            dropdown.innerHTML = html;

            // Click handlers for dropdown items
            dropdown.querySelectorAll('.dd-item').forEach(item => {
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault(); // Prevent blur
                    selectExistingAccount(input, this.dataset.id, this.dataset.code, this.dataset.name);
                });
            });

            dropdown.querySelectorAll('.dd-new').forEach(item => {
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectNewAccount(input, this.dataset.newName);
                });
            });
        }

        function selectExistingAccount(input, id, code, name) {
            const row = input.closest('.item-row');
            const accountIdInput = row.querySelector('.expense-account-id');
            const dropdown = row.querySelector('.account-dropdown');

            accountIdInput.value = id;
            input.value = code + ' - ' + name;
            input.classList.add('has-account');
            dropdown.style.display = 'none';
        }

        function selectNewAccount(input, name) {
            const row = input.closest('.item-row');
            const accountIdInput = row.querySelector('.expense-account-id');
            const dropdown = row.querySelector('.account-dropdown');

            accountIdInput.value = ''; // Empty = create new on save
            input.value = name;
            input.classList.remove('has-account');
            dropdown.style.display = 'none';
        }

        function updateTotals() {
            let total = 0;
            document.querySelectorAll('.item-amount').forEach(input => {
                total += parseFloat(input.value) || 0;
            });

            document.getElementById('totalAmount').textContent = total.toFixed(2);
            document.getElementById('summaryTotal').textContent = total.toFixed(2);
            document.getElementById('itemCount').textContent = document.querySelectorAll('.item-row').length;
        }

        function setAction(status, action) {
            document.getElementById('statusField').value = status;
            document.getElementById('actionField').value = action;
        }
    </script>
@endpush

@push('styles')
    <style>
        .account-input-wrapper {
            position: relative;
        }

        .account-dropdown {
            display: none;
            position: absolute;
            z-index: 1050;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 0.25rem 0.25rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .account-dropdown .dd-item {
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.82rem;
        }

        .account-dropdown .dd-item:hover {
            background-color: #e9ecef;
        }

        .account-dropdown .dd-new {
            padding: 5px 10px;
            color: #28a745;
            font-size: 0.82rem;
            cursor: pointer;
            border-top: 1px solid #eee;
        }

        .account-dropdown .dd-new:hover {
            background-color: #e8f5e9;
        }

        .account-dropdown .dd-empty {
            padding: 5px 10px;
            color: #999;
            font-size: 0.82rem;
        }

        .account-search.has-account {
            background-color: #f0fdf4;
        }
    </style>
@endpush
