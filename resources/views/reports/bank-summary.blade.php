@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Bank & Cash Summary</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                        <li class="breadcrumb-item active">Bank & Cash Summary</li>
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
            <form method="GET" action="{{ route('reports.bank-summary') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>Generate
                    </button>
                    <a href="{{ route('reports.bank-summary') }}" class="btn btn-outline-secondary ml-1">Reset</a>
                    <button type="button" class="btn btn-outline-success ml-1" onclick="printReport()">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h4>{{ number_format($totalOpening, 2) }}</h4>
                    <p>Opening Balance</p>
                </div>
                <div class="icon"><i class="fas fa-wallet"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h4>{{ number_format($totalInflow, 2) }}</h4>
                    <p>Total Inflow</p>
                </div>
                <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h4>{{ number_format($totalOutflow, 2) }}</h4>
                    <p>Total Outflow</p>
                </div>
                <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h4>{{ number_format($totalClosing, 2) }}</h4>
                    <p>Closing Balance</p>
                </div>
                <div class="icon"><i class="fas fa-university"></i></div>
            </div>
        </div>
    </div>

    <!-- Report Table -->
    <div class="card" id="reportContent">
        <div class="card-header">
            <h5 class="card-title mb-0">
                Bank & Cash Summary: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
            </h5>
        </div>
        <div class="card-body">
            @if ($accountSummaries->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No bank or cash accounts found.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr class="table-dark">
                                <th>Code</th>
                                <th>Account Name</th>
                                <th>Bank</th>
                                <th class="text-center">Transactions</th>
                                <th class="text-right">Opening</th>
                                <th class="text-right">Inflow (Dr)</th>
                                <th class="text-right">Outflow (Cr)</th>
                                <th class="text-right">Closing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accountSummaries as $account)
                                <tr>
                                    <td><code>{{ $account['code'] }}</code></td>
                                    <td>
                                        <a href="{{ route('general-ledger.index', ['account_id' => $account['id'], 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}">
                                            {{ $account['name'] }}
                                        </a>
                                    </td>
                                    <td>{{ $account['bank_name'] ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $account['transaction_count'] }}</span>
                                    </td>
                                    <td class="text-right">{{ number_format($account['opening_balance'], 2) }}</td>
                                    <td class="text-right text-success">
                                        {{ $account['inflow'] > 0 ? number_format($account['inflow'], 2) : '-' }}
                                    </td>
                                    <td class="text-right text-danger">
                                        {{ $account['outflow'] > 0 ? number_format($account['outflow'], 2) : '-' }}
                                    </td>
                                    <td class="text-right fw-bold">{{ number_format($account['closing_balance'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td colspan="4" class="text-right">Totals:</td>
                                <td class="text-right">{{ number_format($totalOpening, 2) }}</td>
                                <td class="text-right">{{ number_format($totalInflow, 2) }}</td>
                                <td class="text-right">{{ number_format($totalOutflow, 2) }}</td>
                                <td class="text-right">{{ number_format($totalClosing, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Per-Account Detail Cards -->
                <h5 class="mt-4 mb-3">Account Details</h5>
                <div class="row">
                    @foreach ($accountSummaries as $account)
                        <div class="col-md-4">
                            <div class="card mb-3">
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
                                            <td class="text-right">{{ number_format($account['opening_balance'], 2) }}</td>
                                        </tr>
                                        <tr class="text-success">
                                            <td>Inflow:</td>
                                            <td class="text-right">+ {{ number_format($account['inflow'], 2) }}</td>
                                        </tr>
                                        <tr class="text-danger">
                                            <td>Outflow:</td>
                                            <td class="text-right">- {{ number_format($account['outflow'], 2) }}</td>
                                        </tr>
                                        <tr class="fw-bold border-top">
                                            <td>Closing:</td>
                                            <td class="text-right">{{ number_format($account['closing_balance'], 2) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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