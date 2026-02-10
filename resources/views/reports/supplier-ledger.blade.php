@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Supplier Ledger</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                        <li class="breadcrumb-item active">Supplier Ledger</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.supplier-ledger') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="form-control" required>
                        <option value="">Select Supplier</option>
                        @foreach ($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ $supplierId == $sup->id ? 'selected' : '' }}>
                                {{ $sup->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>Generate
                    </button>
                    <a href="{{ route('reports.supplier-ledger') }}" class="btn btn-outline-secondary ml-1">Reset</a>
                    @if ($supplier)
                        <button type="button" class="btn btn-outline-success ml-1" onclick="printReport()">
                            <i class="fas fa-print mr-1"></i>Print
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($supplier)
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h4>{{ $supplier->full_name }}</h4>
                        <p>Supplier</p>
                    </div>
                    <div class="icon"><i class="fas fa-user-tie"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h4>{{ number_format($totalBills, 2) }}</h4>
                        <p>Total Bills</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-invoice"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h4>{{ number_format($totalPayments, 2) }}</h4>
                        <p>Total Payments</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-check-alt"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h4>{{ number_format($openingBalance + $totalBills - $totalPayments, 2) }}</h4>
                        <p>Closing Balance</p>
                    </div>
                    <div class="icon"><i class="fas fa-balance-scale"></i></div>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="card" id="reportContent">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    Ledger: {{ $supplier->full_name }}
                    @if ($dateFrom || $dateTo)
                        <small class="text-muted">
                            ({{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('M d, Y') : 'Start' }}
                            to
                            {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('M d, Y') : 'Today' }})
                        </small>
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr class="table-dark">
                                <th>Date</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Description</th>
                                <th class="text-right">Debit (Bill)</th>
                                <th class="text-right">Credit (Payment)</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Opening Balance Row -->
                            @if ($openingBalance != 0 || $dateFrom)
                                <tr class="table-light fw-bold">
                                    <td>{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('M d, Y') : '-' }}</td>
                                    <td colspan="4">Opening Balance</td>
                                    <td></td>
                                    <td class="text-right">{{ number_format($openingBalance, 2) }}</td>
                                </tr>
                            @endif

                            @php $runningBalance = $openingBalance; @endphp

                            @forelse ($transactions as $txn)
                                @php
                                    $runningBalance += ($txn['debit'] - $txn['credit']);
                                @endphp
                                <tr>
                                    <td>{{ $txn['date']->format('M d, Y') }}</td>
                                    <td>
                                        @if ($txn['type'] === 'bill')
                                            <span class="badge bg-warning text-dark">Bill</span>
                                        @else
                                            <span class="badge bg-success">Payment</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($txn['type'] === 'bill')
                                            <a href="{{ route('bills.show', $txn['reference_id']) }}">
                                                {{ $txn['reference'] }}
                                            </a>
                                        @else
                                            <a href="{{ route('payments.show', $txn['reference_id']) }}">
                                                {{ $txn['reference'] }}
                                            </a>
                                        @endif
                                    </td>
                                    <td>{{ $txn['description'] }}</td>
                                    <td class="text-right">
                                        {{ $txn['debit'] > 0 ? number_format($txn['debit'], 2) : '-' }}
                                    </td>
                                    <td class="text-right">
                                        {{ $txn['credit'] > 0 ? number_format($txn['credit'], 2) : '-' }}
                                    </td>
                                    <td class="text-right fw-bold">{{ number_format($runningBalance, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        No transactions found for this supplier in the selected period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td colspan="4" class="text-right">Totals:</td>
                                <td class="text-right">{{ number_format($totalBills, 2) }}</td>
                                <td class="text-right">{{ number_format($totalPayments, 2) }}</td>
                                <td class="text-right">{{ number_format($openingBalance + $totalBills - $totalPayments, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Select a supplier to view their ledger</h5>
                <p class="text-muted">Choose a supplier from the dropdown above and click Generate.</p>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        function printReport() {
            const content = document.getElementById('reportContent').innerHTML;
            const win = window.open('', '_blank');
            win.document.write(`
                <html>
                <head>
                    <title>Supplier Ledger - {{ $supplier?->full_name }}</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
                    <style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>
                </head>
                <body>
                    <h3 class="text-center mb-1">Supplier Ledger</h3>
                    <p class="text-center text-muted mb-4">{{ $supplier?->full_name }}</p>
                    ${content}
                    <script>window.print();<\/script>
                </body>
                </html>
            `);
            win.document.close();
        }
    </script>
@endpush