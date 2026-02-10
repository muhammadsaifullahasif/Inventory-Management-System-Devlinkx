@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Record Payment</h1>
                    @can('payments-add')
                        <a href="{{ route('payments.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Payment</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('payments.index') }}">Payments</a></li>
                        <li class="breadcrumb-item active">New Payment</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <form action="{{ route('payments.store') }}" method="POST" id="paymentForm">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Bill Selection Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Bill</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="bill_id" class="form-label">Bill <span class="text-danger">*</span></label>
                            <select name="bill_id" id="bill_id" class="form-control @error('bill_id') is-invalid @enderror">
                                <option value="">Select a Bill</option>
                                @foreach ($bills as $bill)
                                    <option value="{{ $bill->id }}"
                                            data-supplier="{{ $bill->supplier->full_name }}"
                                            data-total="{{ $bill->total_amount }}"
                                            data-paid="{{ $bill->paid_amount }}"
                                            data-remaining="{{ $bill->remaining_amount }}"
                                            data-date="{{ $bill->bill_date->format('M d, Y') }}"
                                            data-due="{{ $bill->due_date?->format('M d, Y') ?? 'N/A' }}"
                                            data-overdue="{{ $bill->isOverdue() ? '1' : '0' }}"
                                            {{ (old('bill_id') ?? $selectedBillId) == $bill->id ? 'selected' : '' }}>
                                        {{ $bill->bill_number }} - {{ $bill->supplier->full_name }} (Balance: {{ number_format($bill->remaining_amount, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('bill_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Bill Details (shown after selection) -->
                        <div id="billDetails" class="border rounded p-3" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table-table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Supplier:</th>
                                            <td id="billSupplier"></td>
                                        </tr>
                                        <tr>
                                            <th>Bill Date:</th>
                                            <td id="billDate"></td>
                                        </tr>
                                        <tr>
                                            <th>Due Date:</th>
                                            <td id="billDueDate"></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <th>Total Amount:</th>
                                            <td id="billTotal" class="text-right"></td>
                                        </tr>
                                        <tr>
                                            <th>Already Paid:</th>
                                            <td id="billPaid" class="text-right text-success"></td>
                                        </tr>
                                        <tr class="table-warning">
                                            <th>Remaining:</th>
                                            <td id="billRemaining" class="text-right fw-bold"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                @error('payment_date')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="0.00" step="0.01" min="0.01" required>
                                @error('amount')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Max: <span id="maxAmount">0.00</span></small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline">
                                        <input type="radio" name="payment_method" id="method_bank" value="bank" class="form-check-input" {{ old('payment_method', 'bank') === 'bank' ? 'checked' : '' }}>
                                        <label for="method_bank" class="form-check-label">Bank</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="radio" name="payment_method" id="method_cash" value="cash" class="form-check-input" {{ old('payment_method' === 'cash' ? 'checked' : '') }}>
                                        <label for="method_cash" class="form-check-label">Cash</label>
                                    </div>
                                </div>
                                @error('payment_method')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_account_id" class="form-label">Account <span class="text-danger">*</span></label>
                                <select name="payment_account_id" id="payment_account_id" class="form-control @error('payment_account_id') is-invalid @enderror" required>
                                    <option value="">Select Account</option>
                                </select>
                                @error('payment_account_id')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <small class="text-muted">Balance: <span id="accountBalance">-</span></small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="reference" class="form-label">Reference (Cheque #, Transaction ID)</label>
                                <input type="text" name="reference" id="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" placeholder="e.g., CHQ-001234">
                                @error('reference')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Optional notes...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Payment Summary Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Bill:</th>
                                <td class="text-right"><span id="summaryBill">-</span></td>
                            </tr>
                            <tr>
                                <th>Supplier</th>
                                <td class="text-right"><span id="summarySupplier">-</span></td>
                            </tr>
                            <tr>
                                <th>Remaining:</th>
                                <td class="text-right"><span id="summaryRemaining">-</span></td>
                            </tr>
                            <tr class="table-light">
                                <th>Paying:</th>
                                <td class="text-right fs-5 fw-bold text-success"><span id="summaryAmount">0.00</span></td>
                            </tr>
                            <tr>
                                <th>After Payment:</th>
                                <td class="text-right"><span id="summaryAfter">-</span></td>
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
                        <input type="hidden" name="status" id="statusField" value="posted">
                        <input type="hidden" name="action" id="actionField" value="save">

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" onclick="setAction('posted', 'save')">
                                <i class="fas fa-check mr-1"></i>Save & Post
                            </button>
                            <button type="submit" class="btn btn-outline-primary" onclick="setAction('posted', 'save_new')">
                                <i class="fas fa-plus mr-1"></i>Save & New
                            </button>
                            <button type="submit" class="btn btn-secondary" onclick="setAction('draft', 'save')">
                                <i class="fas fa-file mr-1"></i>Save as Draft
                            </button>
                            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@php
    // Group bank/cash accounts by parent (Banks vs Cash in Hand)
    $groupedAccounts = $bankCashAccounts->groupBy(function($account) {
        return $account->parent ? $account->parent->name : 'Other';
    });

    $accountsJson = $bankCashAccounts->map(function($account) {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'bank_name' => $account->bank_name,
            'current_balance' => $account->current_balance,
            'parent_name' => $account->parent ? $account->parent->name : 'Other',
            'is_bank' => $account->parent && str_contains(strtolower($account->parent->name), 'bank'),
        ];
    });
@endphp

@push('scripts')
    <script>
        const bankCashAccounts = @json($accountsJson);
        let currentRemaining = 0;

        document.addEventListener('DOMContentLoaded', function() {
            const billSelect = document.getElementById('bill_id');
            const amountInput = document.getElementById('amount');
            const methodRadios = document.querySelectorAll('input[name="payment_method"]');

            // Bill selection change
            billSelect.addEventListener('change', function(){
                updateBillDetails();
            });

            // Payment method change
            methodRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateAccountDropdown();
                });
            });

            // Account change
            amountInput.addEventListener('input', updateSummary);

            // Account change
            document.getElementById('payment_account_id').addEventListener('change', function() {
                const accountId = this.value;
                const account = bankCashAccounts.find(a => a.id == accountId);
                const balanceSpan = document.getElementById('accountBalance');
                balanceSpan.textContent = account ? parseFloat(account.current_balance).toFixed(2) : '-';
            });

            // Initialize if bill is pre-selected
            if (billSelect.value) {
                updateBillDetails();
            }

            // Initialize account dropdown
            updateAccountDropdown();
        });

        function updateBillDetails() {
            const select = document.getElementById('bill_id');
            const option = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('billDetails');

            if (!select.value) {
                detailsDiv.style.display = 'none';
                currentRemaining = 0;
                updateSummary();
                return;
            }

            const supplier = option.dataset.supplier;
            const total = parseFloat(option.dataset.total);
            const paid = parseFloat(option.dataset.paid);
            const remaining = parseFloat(option.dataset.remaining);
            const date = option.dataset.date;
            const dueDate = option.dataset.due;
            const isOverdue = option.dataset.overdue === '1';

            document.getElementById('billSupplier').textContent = supplier;
            document.getElementById('billDate').textContent = date;
            document.getElementById('billDueDate').innerHTML = dueDate + (isOverdue ? '<span class="badge bg-danger">Overdue</span>' : '');
            document.getElementById('billTotal').textContent = total.toFixed(2);
            document.getElementById('billPaid').textContent = paid.toFixed(2);
            document.getElementById('billRemaining').textContent = remaining.toFixed(2);

            currentRemaining = remaining;
            document.getElementById('maxAmount').textContent = remaining.toFixed(2);
            document.getElementById('amount').max = remaining;

            // Update summary
            document.getElementById('summaryBill').textContent = option.textContent.split(' - ')[0];
            document.getElementById('summarySupplier').textContent = supplier;
            document.getElementById('summaryRemaining').textContent = remaining.toFixed(2);

            detailsDiv.style.display = 'block';

            // Auto-fill full remaining amount
            if (!document.getElementById('amount').value) {
                document.getElementById('amount').value = remaining.toFixed(2);
            }

            updateSummary();
        }

        function updateAccountDropdown() {
            const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'bank';
            const select = document.getElementById('payment_account_id');
            const currentValue = select.value;

            select.innerHTML = '<option value="">Select Account</option>';

            const filtered = bankCashAccounts.filter(account => {
                if (method === 'bank') {
                    return account.is_bank;
                } else {
                    return !account.is_bank;
                }
            });

            filtered.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                const bankLabel = account.bank_name ? `(${account.bank_name})` : '';
                option.textContent = `${account.name}${bankLabel} - Bal: ${parseFloat(account.current_balance).toFixed(2)}`;
                if (account.id == currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            // Reset balance display
            document.getElementById('accountBalance').textContent = '-';
        }

        function updateSummary() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;

            document.getElementById('summaryAmount').textContent = amount.toFixed(2);

            const afterPayment = currentRemaining - amount;
            const afterSpan = document.getElementById('summaryAfter');

            if (currentRemaining > 0) {
                afterSpan.textContent = afterPayment.toFixed(2);
                afterSpan.className = afterPayment <= 0 ? 'text-success fw-bold' : '';
            } else {
                afterSpan.textContent = '-';
            }

            // Warn if overpaying
            const amountInput = document.getElementById('amount');
            if (amount > currentRemaining && currentRemaining > 0) {
                amountInput.classList.add('is-invalid');
            } else {
                amountInput.classList.remove('is-invalid');
            }
        }

        function setAction(status, action) {
            document.getElementById('statusField').value = status;
            document.getElementById('actionField').value = action;
        }
    </script>
@endpush
