@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Expense Report</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                        <li class="breadcrumb-item active">Expense Report</li>
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
            <form method="GET" action="{{ route('reports.expense') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-3">
                    <label for="group_id" class="form-label">Expense Group</label>
                    <select name="group_id" id="group_id" class="form-control">
                        <option value="">All Groups</option>
                        @foreach ($expenseGroups as $group)
                            <option value="{{ $group->id }}" {{ $groupId == $group->id ? 'selected' : '' }}>
                                {{ $group->code }} - {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-1"></i>Generate
                    </button>
                    <a href="{{ route('reports.expense') }}" class="btn btn-outline-secondary ml-1">Reset</a>
                    <button type="button" class="btn btn-outline-success ml-1" onclick="printReport()">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h4>{{ number_format($grandTotal, 2) }}</h4>
                    <p>Total Expenses</p>
                </div>
                <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-info">
                <div class="inner">
                    <h4>{{ $reportData->count() }}</h4>
                    <p>Expense Groups</p>
                </div>
                <div class="icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h4>{{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d M, Y') }}</h4>
                    <p>Report Period</p>
                </div>
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>
    </div>

    <!-- Report -->
    <div class="card" id="reportContent">
        <div class="card-header">
            <h5 class="card-title mb-0">
                Expense Breakdown: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
            </h5>
        </div>
        <div class="card-body">
            @if ($reportData->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No expenses found for the selected period.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr class="table-dark">
                                <th>Code</th>
                                <th>Account Name</th>
                                <th class="text-center">Bills</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportData as $group)
                                <!-- Group Header -->
                                <tr class="table-secondary fw-bold">
                                    <td>{{ $group['code'] }}</td>
                                    <td colspan="2">{{ $group['name'] }}</td>
                                    <td class="text-right">{{ number_format($group['total'], 2) }}</td>
                                    <td class="text-right">
                                        {{ $grandTotal > 0 ? number_format(($group['total'] / $grandTotal) * 100, 1) : '0.0' }}%
                                    </td>
                                </tr>
                                <!-- Group Items -->
                                @foreach ($group['items'] as $item)
                                    <tr>
                                        <td class="pl-4"><code>{{ $item['code'] }}</code></td>
                                        <td class="pl-4">{{ $item['name'] }}</td>
                                        <td class="text-center">{{ $item['bill_count'] }}</td>
                                        <td class="text-right">{{ number_format($item['total_amount'], 2) }}</td>
                                        <td class="text-right text-muted">
                                            {{ $grandTotal > 0 ? number_format(($item['total_amount'] / $grandTotal) * 100, 1) : '0.0' }}%
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td colspan="3" class="text-right">Grand Total:</td>
                                <td class="text-right">{{ number_format($grandTotal, 2) }}</td>
                                <td class="text-right">100%</td>
                            </tr>
                        </tfoot>
                    </table>
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
                    <title>Expense Report</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
                    <style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>
                </head>
                <body>
                    <h3 class="text-center mb-1">Expense Report</h3>
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