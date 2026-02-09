@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Edit Bill: {{ $bill->bill_number }}</h1>
                    @can('bills-add')
                        <a href="{{ route('bills.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add New Bill</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('bills.index') }}">Bills</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Form -->
    <form action="{{ route('bills.update', $bill) }}" method="POST" id="billForm">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Bill Details Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Bill Details</h5>
                        @if ($bill->status === 'draft')
                            <span class="badge bg-secondary">Draft</span>
                        @else
                            <span class="badge bg-warning">Unpaid</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                <select name="supplier_id" id="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror" required>
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $bill->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="bill_date" class="form-label">Bill Date <span class="text-danger">*</span></label>
                                <input type="date" name="bill_date" id="bill_date" class="form-control @error('bill_date') is-invalid @enderror" value="{{ old('bill_date', $bill->bill_date->format('Y-m-d')) }}" required>
                                @error('bill_date')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', $bill->due_date?->format('Y-m-d')) }}">
                                @error('due_date')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Items Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Expense Items</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                            <i class="fas fa-plus mr-1"></i>Add Item
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="itemsTable">
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
                                    <!-- Existing items will be loaded here -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                        <td class="fw-bold"><span id="totalAmount">0.00</span></td>
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
                        <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Optional notes...">{{ old('notes', $bill->notes) }}</textarea>
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
                                <th>Bill Number:</th>
                                <td class="text-end">{{ $bill->bill_number }}</td>
                            </tr>
                            <tr>
                                <th>Items:</th>
                                <td class="text-end"><span id="itemCount">0</span></td>
                            </tr>
                            <tr class="table-light">
                                <th>Total Amount:</th>
                                <td class="text-end fs-5 fw-bold"><span id="summaryTotal">0.00</span></td>
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
                        <input type="hidden" name="status" id="statusField" value="{{ $bill->status }}">

                        <div class="d-grid gap-2">
                            @if ($bill->status === 'draft')
                                <button type="submit" class="btn btn-secondary" onclick="setStatus('draft')">
                                    <i class="fas fa-file mr-1"></i>Save as Draft
                                </button>
                                <button type="submit" class="btn btn-primary" onclick="setStatus('unpaid')">
                                    <i class="fas fa-check mr-1"></i>Save & Post
                                </button>
                            @else
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check mr-1"></i>Update Bill
                                </button>
                            @endif
                            <a href="{{ route('bills.show', $bill) }}" class="btn btn-outline-secondary">
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
                <select name="items[INDEX][expense_account_id]" class="form-control form-control-sm expense-account" required>
                    <option value="">Select Account</option>
                </select>
            </td>
            <td>
                <input type="text" name="items[INDEX][description]" class="form-control form-control-sm" placeholder="Description" required>
            </td>
            <td>
                <input type="number" name="items[INDEX][amount]" class="form-control form-control-sm item-amount" placeholder="0.00" step="0.01" min="0.01" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger remove-item">
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

    $existingItemsJson = $bill->items->map(function($item) {
        return [
            'expense_group_id' => $item->expenseAccount->parent_id,
            'expense_account_id' => $item->expense_account_id,
            'description' => $item->description,
            'amount' => $item->amount,
        ];
    });
@endphp

@push('scripts')
    <script>
        //Expense accounts data
        const expenseAccountsByGroup = @json($expenseAccountsJson);

        // Existing items
        const existingItems = @json($existingItemsJson);

        let itemIndex = 0;

        document.addEventListener('DOMContentLoaded', function() {
            // Load existing items
            if (existingItems.length > 0) {
                existingItems.forEach(item => {
                    addItemRow(item);
                });
            } else {
                addItemRow();
            }

            // Add item button
            document.getElementById('addItemBtn').addEventListener('click', () => addItemRow());

            // Delegate events
            document.getElementById('itemsBody').addEventListener('click', function(e) {
                if (e.target.closest('.remove-item')) {
                    removeItemRow(e.target.closest('.item-row'));
                }
            });

            document.getElementById('itemsBody').addEventListener('change', function(e) {
                if (e.target.classList.contains('expense-group')) {
                    updateExpenseAccounts(e.target);
                }
                if (e.target.classList.contains('item-amount')) {
                    updateTotals();
                }
            });
        });

        function addItemRow(data = null) {
            const template = document.getElementById('itemRowTemplate');
            const clone = template.content.cloneNode(true);

            // Update index
            clone.querySelectorAll('[name*="INDEX"]').forEach(el => {
                el.name = el.name.replace('INDEX', itemIndex);
            });

            const row = clone.querySelector('.item-row');
            document.getElementById('itemsBody').appendChild(clone);

            // If data provided, populate the row
            if (data) {
                const addedRow = document.getElementById('itemsBody').lastElementChild;
                const groupSelect = addedRow.querySelector('.expense-group');
                const accountSelect = addedRow.querySelector('.expense-account');
                const descInput = addedRow.querySelector('[name*="description"]');
                const amountInput = addedRow.querySelector('[name*="amount"]');

                // Set group
                groupSelect.value = data.expense_group_id;

                // Populate accounts dropdown
                updateExpenseAccounts(groupSelect, data.expense_account_id);

                // Set other values
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
                alert('At leaset one item is required.');
            }
        }

        function updateExpenseAccounts(groupSelect, selectedAccountId = null) {
            const row = groupSelect.closest('.item-row');
            const accountSelect = row.querySelector('.expense-account');
            const groupId = groupSelect.value;

            accountSelect.innerHTML = '<option value="">Select Account</option>';

            if (groupId && expenseAccountsByGroup[groupId]) {
                expenseAccountsByGroup[groupId].forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.code} - ${account.name}`;
                    if (selectedAccountId && account.id == selectedAccountId) {
                        option.selected = true;
                    }
                    accountSelect.appendChild(option);
                });
            }
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

        function setStatus(status) {
            document.getElementById('statusField').value = status;
        }
    </script>
@endpush
