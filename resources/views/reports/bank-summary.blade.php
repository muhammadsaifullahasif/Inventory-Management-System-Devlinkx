@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Bank & Cash Summary</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Bank & Cash Summary</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <button type="button" class="btn btn-primary" onclick="printReport()">
                        <i class="feather-printer me-2"></i>Print
                    </button>
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
                    <form method="GET" action="{{ route('reports.bank-summary') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.bank-summary') }}" class="btn btn-light-brand btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-soft-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Opening Balance</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($totalOpening, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-briefcase"></i>
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
                            <h6 class="text-muted mb-1">Total Inflow</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($totalInflow, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-success text-white rounded">
                            <i class="feather-arrow-down-circle"></i>
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
                            <h6 class="text-muted mb-1">Total Outflow</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($totalOutflow, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-danger text-white rounded">
                            <i class="feather-arrow-up-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-soft-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Closing Balance</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($totalClosing, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-primary text-white rounded">
                            <i class="feather-credit-card"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Table Card -->
    <div class="col-12">
        <div class="card" id="reportContent">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-credit-card me-2"></i>Bank & Cash Summary: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
                </h5>
            </div>
            <div class="card-body p-0">
                @if ($accountSummaries->isEmpty())
                    <div class="text-center py-5">
                        <i class="feather-info text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No bank or cash accounts found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Bank</th>
                                    <th class="text-center">Transactions</th>
                                    <th class="text-end">Opening</th>
                                    <th class="text-end">Inflow (Dr)</th>
                                    <th class="text-end">Outflow (Cr)</th>
                                    <th class="text-end">Closing</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accountSummaries as $account)
                                    <tr>
                                        <td><code>{{ $account['code'] }}</code></td>
                                        <td>
                                            <a href="{{ route('general-ledger.index', ['account_id' => $account['id'], 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="fw-semibold">
                                                {{ $account['name'] }}
                                            </a>
                                        </td>
                                        <td>{{ $account['bank_name'] ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-secondary text-secondary">{{ $account['transaction_count'] }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($account['opening_balance'], 2) }}</td>
                                        <td class="text-end">
                                            @if ($account['inflow'] > 0)
                                                <span class="text-success">{{ number_format($account['inflow'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($account['outflow'] > 0)
                                                <span class="text-danger">{{ number_format($account['outflow'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">{{ number_format($account['closing_balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4" class="text-end">Totals:</td>
                                    <td class="text-end">{{ number_format($totalOpening, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalInflow, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalOutflow, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalClosing, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Per-Account Detail Cards -->
                    <div class="p-3">
                        <h5 class="mb-3"><i class="feather-layers me-2"></i>Account Details</h5>
                        <div class="row">
                            @foreach ($accountSummaries as $account)
                                <div class="col-md-4">
                                    <div class="card mb-3 border">
                                        <div class="card-header py-2">
                                            <strong>{{ $account['name'] }}</strong>
                                            @if ($account['bank_name'])
                                                <small class="text-muted d-block">{{ $account['bank_name'] }}{{ $account['account_number'] ? ' - ' . $account['account_number'] : '' }}</small>
                                            @endif
                                        </div>
                                        <div class="card-body py-2">
                                            <table class="table table-sm table-borderless mb-0">
                                                <tr>
                                                    <td class="text-muted">Opening:</td>
                                                    <td class="text-end">{{ number_format($account['opening_balance'], 2) }}</td>
                                                </tr>
                                                <tr class="text-success">
                                                    <td>Inflow:</td>
                                                    <td class="text-end">+ {{ number_format($account['inflow'], 2) }}</td>
                                                </tr>
                                                <tr class="text-danger">
                                                    <td>Outflow:</td>
                                                    <td class="text-end">- {{ number_format($account['outflow'], 2) }}</td>
                                                </tr>
                                                <tr class="fw-bold border-top">
                                                    <td>Closing:</td>
                                                    <td class="text-end">{{ number_format($account['closing_balance'], 2) }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function printReport() {
            const content = document.getElementById('reportContent').innerHTML;
            const win = window.open('', '_blank');
            win.document.write(`
                <html>
                <head>
                    <title>Bank & Cash Summary</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
                    <style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>
                </head>
                <body>
                    <h3 class="text-center mb-1">Bank & Cash Summary</h3>
                    <p class="text-center text-muted mb-4">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</p>
                    ${content}
                    <script>window.print();<\/script>
                </body>
                </html>
            `);
            win.document.close();
        }
    </script>
@endpush
