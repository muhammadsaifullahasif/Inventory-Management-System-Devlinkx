@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Supplier Ledger</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Supplier Ledger</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @if ($supplier)
                        <button type="button" class="btn btn-primary" onclick="printReport()">
                            <i class="feather-printer me-2"></i>Print
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filter Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-chevron-down"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form method="GET" action="{{ route('reports.supplier-ledger') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                <select name="supplier_id" id="supplier_id" class="form-select form-select-sm" required>
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
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-5 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.supplier-ledger') }}" class="btn btn-light-brand btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($supplier)
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-soft-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Supplier</h6>
                                <h5 class="mb-0 fw-bold">{{ $supplier->full_name }}</h5>
                            </div>
                            <div class="avatar-text avatar-lg bg-info text-white rounded">
                                <i class="feather-user"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-danger">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Total Bills</h6>
                                <h3 class="mb-0 fw-bold">{{ number_format($totalBills, 2) }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-danger text-white rounded">
                                <i class="feather-file-text"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Total Payments</h6>
                                <h3 class="mb-0 fw-bold">{{ number_format($totalPayments, 2) }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-success text-white rounded">
                                <i class="feather-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-soft-warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Closing Balance</h6>
                                <h3 class="mb-0 fw-bold">{{ number_format($openingBalance + $totalBills - $totalPayments, 2) }}</h3>
                            </div>
                            <div class="avatar-text avatar-lg bg-warning text-white rounded">
                                <i class="feather-bar-chart-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Table Card -->
        <div class="col-12">
            <div class="card" id="reportContent">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="feather-book me-2"></i>Ledger: {{ $supplier->full_name }}
                        @if ($dateFrom || $dateTo)
                            <small class="text-muted">
                                ({{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('M d, Y') : 'Start' }}
                                to
                                {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('M d, Y') : 'Today' }})
                            </small>
                        @endif
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit (Bill)</th>
                                    <th class="text-end">Credit (Payment)</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Opening Balance Row -->
                                @if ($openingBalance != 0 || $dateFrom)
                                    <tr class="table-light fw-bold">
                                        <td><span class="fs-12 text-muted">{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('M d, Y') : '-' }}</span></td>
                                        <td colspan="4">Opening Balance</td>
                                        <td></td>
                                        <td class="text-end">{{ number_format($openingBalance, 2) }}</td>
                                    </tr>
                                @endif

                                @php $runningBalance = $openingBalance; @endphp

                                @forelse ($transactions as $txn)
                                    @php
                                        $runningBalance += ($txn['debit'] - $txn['credit']);
                                    @endphp
                                    <tr>
                                        <td><span class="fs-12 text-muted">{{ $txn['date']->format('M d, Y') }}</span></td>
                                        <td>
                                            @if ($txn['type'] === 'bill')
                                                <span class="badge bg-soft-warning text-warning">Bill</span>
                                            @else
                                                <span class="badge bg-soft-success text-success">Payment</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($txn['type'] === 'bill')
                                                <a href="{{ route('bills.show', $txn['reference_id']) }}" class="fw-semibold">
                                                    {{ $txn['reference'] }}
                                                </a>
                                            @else
                                                <a href="{{ route('payments.show', $txn['reference_id']) }}" class="fw-semibold">
                                                    {{ $txn['reference'] }}
                                                </a>
                                            @endif
                                        </td>
                                        <td>{{ $txn['description'] }}</td>
                                        <td class="text-end">
                                            @if ($txn['debit'] > 0)
                                                <span class="text-success">{{ number_format($txn['debit'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($txn['credit'] > 0)
                                                <span class="text-danger">{{ number_format($txn['credit'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">{{ number_format($runningBalance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No transactions found for this supplier in the selected period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4" class="text-end">Totals:</td>
                                    <td class="text-end">{{ number_format($totalBills, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalPayments, 2) }}</td>
                                    <td class="text-end">{{ number_format($openingBalance + $totalBills - $totalPayments, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="feather-users text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">Select a supplier to view their ledger</h5>
                    <p class="text-muted">Choose a supplier from the dropdown above and click Generate.</p>
                </div>
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
