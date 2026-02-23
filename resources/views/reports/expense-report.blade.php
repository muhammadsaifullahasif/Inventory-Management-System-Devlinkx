@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Expense Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Expense Report</li>
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
                    <form method="GET" action="{{ route('reports.expense') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-3">
                                <label for="group_id" class="form-label">Expense Group</label>
                                <select name="group_id" id="group_id" class="form-select form-select-sm">
                                    <option value="">All Groups</option>
                                    @foreach ($expenseGroups as $group)
                                        <option value="{{ $group->id }}" {{ $groupId == $group->id ? 'selected' : '' }}>
                                            {{ $group->code }} - {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Generate
                                </button>
                                <a href="{{ route('reports.expense') }}" class="btn btn-light-brand btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-soft-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Expenses</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($grandTotal, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-danger text-white rounded">
                            <i class="feather-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-soft-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Expense Groups</h6>
                            <h3 class="mb-0 fw-bold">{{ $reportData->count() }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-soft-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Report Period</h6>
                            <h4 class="mb-0 fw-bold">{{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d M, Y') }}</h4>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Card -->
    <div class="col-12">
        <div class="card" id="reportContent">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-pie-chart me-2"></i>Expense Breakdown: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}
                </h5>
            </div>
            <div class="card-body p-0">
                @if ($reportData->isEmpty())
                    <div class="text-center py-5">
                        <i class="feather-info text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No expenses found for the selected period.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th class="text-center">Bills</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportData as $group)
                                    <!-- Group Header -->
                                    <tr class="table-light fw-bold">
                                        <td>{{ $group['code'] }}</td>
                                        <td colspan="2">{{ $group['name'] }}</td>
                                        <td class="text-end">{{ number_format($group['total'], 2) }}</td>
                                        <td class="text-end">
                                            {{ $grandTotal > 0 ? number_format(($group['total'] / $grandTotal) * 100, 1) : '0.0' }}%
                                        </td>
                                    </tr>
                                    <!-- Group Items -->
                                    @foreach ($group['items'] as $item)
                                        <tr>
                                            <td class="ps-4"><code>{{ $item['code'] }}</code></td>
                                            <td class="ps-4">{{ $item['name'] }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-secondary text-secondary">{{ $item['bill_count'] }}</span>
                                            </td>
                                            <td class="text-end">{{ number_format($item['total_amount'], 2) }}</td>
                                            <td class="text-end text-muted">
                                                {{ $grandTotal > 0 ? number_format(($item['total_amount'] / $grandTotal) * 100, 1) : '0.0' }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="text-end">Grand Total:</td>
                                    <td class="text-end">{{ number_format($grandTotal, 2) }}</td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tfoot>
                        </table>
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
