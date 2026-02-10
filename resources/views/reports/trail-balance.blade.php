@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Trial Balance</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                        <li class="breadcrumb-item active">Trial Balance</li>
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
            <form method="GET" action="{{ route('reports.trial-balance') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label for="as_of_date" class="form-label">As of Date</label>
                    <input type="date" name="as_of_date" id="as_of_date" class="form-control" value="{{ $asOfDate }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>Generate
                    </button>
                    <a href="{{ route('reports.trial-balance') }}" class="btn btn-outline-secondary ml-1">Reset</a>
                </div>
                <div class="col-md-6 text-right">
                    <button type="button" class="btn btn-outline-success" onclick="printReport()">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report -->
    <div class="card" id="reportContent">
        <div class="card-header">
            <h5 class="card-title mb-0">Trial Balance as of {{ \Carbon\Carbon::parse($asOfDate)->format('M d, Y') }}</h5>
        </div>
        <div class="card-body">
            @if ($accounts->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No accounts with balances found.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr class="table-dark">
                                <th>Code</th>
                                <th>Account Name</th>
                                <th>Group</th>
                                <th>Nature</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <td><code>{{ $account['code'] }}</code></td>
                                    <td>
                                        <a href="{{ route('general-ledger.index', ['account_id' => $account['id']]) }}">
                                            {{ $account['name'] }}
                                        </a>
                                    </td>
                                    <td>{{ $account['group'] }}</td>
                                    <td>
                                        <span class="badge bg-{{ $account['nature'] === 'expense' ? 'danger' : ($account['nature'] === 'asset' ? 'primary' : ($account['nature'] === 'liability' ? 'warning' : ($account['nature'] === 'revenue' ? 'success' : 'secondary'))) }}">
                                            {{ ucfirst($account['nature']) }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        {{ $account['debit'] > 0 ? number_format($account['debit'], 2) : '-' }}
                                    </td>
                                    <td class="text-right">
                                        {{ $account['credit'] > 0 ? number_format($account['credit'], 2) : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td colspan="4" class="text-right">Totals:</td>
                                <td class="text-right">{{ number_format($totalDebit, 2) }}</td>
                                <td class="text-right">{{ number_format($totalCredit, 2) }}</td>
                            </tr>
                            @if (abs($totalDebit - $totalCredit) > 0.01)
                                <tr class="table-danger">
                                    <td colspan="4" class="text-right fw-bold">Difference:</td>
                                    <td colspan="2" class="text-right fw-bold">
                                        {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                                    </td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                @if (abs($totalDebit - $totalCredit) < 0.01)
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-check-circle mr-1"></i>
                        Trial Balance is balanced. Total Debit equals Total Credit.
                    </div>
                @else
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Trial Balance is NOT balanced. Difference: {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                    </div>
                @endif
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
                    <title>Trial Balance - {{ \Carbon\Carbon::parse($asOfDate)->format('M d, Y') }}</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
                    <style>
                        body { padding: 20px; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <h3 class="text-center mb-1">Trial Balance</h3>
                    <p class="text-center text-muted mb-4">As of {{ \Carbon\Carbon::parse($asOfDate)->format('M d, Y') }}</p>
                    ${content}
                    <script>window.print();<\/script>
                </body>
                </html>
            `);
            win.document.close();
        }
    </script>
@endpush