@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Trial Balance</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Trial Balance</li>
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
                    <form method="GET" action="{{ route('reports.trial-balance') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="as_of_date" class="form-label">As of Date</label>
                                <input type="date" name="as_of_date" id="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.trial-balance') }}" class="btn btn-light-brand btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Card -->
    <div class="col-12">
        <div class="card" id="reportContent">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-bar-chart-2 me-2"></i>Trial Balance as of {{ \Carbon\Carbon::parse($asOfDate)->format('M d, Y') }}</h5>
            </div>
            <div class="card-body p-0">
                @if ($accounts->isEmpty())
                    <div class="text-center py-5">
                        <i class="feather-info text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No accounts with balances found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Group</th>
                                    <th>Nature</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    <tr>
                                        <td><code>{{ $account['code'] }}</code></td>
                                        <td>
                                            <a href="{{ route('general-ledger.index', ['account_id' => $account['id']]) }}" class="fw-semibold">
                                                {{ $account['name'] }}
                                            </a>
                                        </td>
                                        <td>{{ $account['group'] }}</td>
                                        <td>
                                            <span class="badge bg-soft-{{ $account['nature'] === 'expense' ? 'danger' : ($account['nature'] === 'asset' ? 'primary' : ($account['nature'] === 'liability' ? 'warning' : ($account['nature'] === 'revenue' ? 'success' : 'secondary'))) }} text-{{ $account['nature'] === 'expense' ? 'danger' : ($account['nature'] === 'asset' ? 'primary' : ($account['nature'] === 'liability' ? 'warning' : ($account['nature'] === 'revenue' ? 'success' : 'secondary'))) }}">
                                                {{ ucfirst($account['nature']) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if ($account['debit'] > 0)
                                                <span class="text-success">{{ number_format($account['debit'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($account['credit'] > 0)
                                                <span class="text-danger">{{ number_format($account['credit'], 2) }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4" class="text-end">Totals:</td>
                                    <td class="text-end">{{ number_format($totalDebit, 2) }}</td>
                                    <td class="text-end">{{ number_format($totalCredit, 2) }}</td>
                                </tr>
                                @if (abs($totalDebit - $totalCredit) > 0.01)
                                    <tr class="table-danger">
                                        <td colspan="4" class="text-end fw-bold">Difference:</td>
                                        <td colspan="2" class="text-end fw-bold">
                                            {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                                        </td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>

                    @if (abs($totalDebit - $totalCredit) < 0.01)
                        <div class="alert alert-soft-success m-3 mb-0">
                            <i class="feather-check-circle me-2"></i>
                            Trial Balance is balanced. Total Debit equals Total Credit.
                        </div>
                    @else
                        <div class="alert alert-soft-danger m-3 mb-0">
                            <i class="feather-alert-triangle me-2"></i>
                            Trial Balance is NOT balanced. Difference: {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                        </div>
                    @endif
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
