@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Net Profit Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Net Profit Report</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('reports.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('reports.net-profit') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sales Channel</label>
                                <select name="channel_id" class="form-select form-select-sm">
                                    <option value="">All Channels</option>
                                    @foreach ($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ $channelId == $channel->id ? 'selected' : '' }}>
                                            {{ $channel->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Sales Channel</option>
                                    <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label d-block">Category</label>
                                <div class="dropdown" id="categoryFilterDropdown" style="position: relative;">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" id="categoryDropdownBtn">
                                        <span id="categoryDropdownLabel">
                                            @if(empty($categoryIds))
                                                All Categories
                                            @else
                                                {{ count($categoryIds) }} Selected
                                            @endif
                                        </span>
                                    </button>
                                    <div class="dropdown-menu p-2" id="categoryDropdownMenu" style="max-height: 250px; overflow-y: auto; min-width: 220px;">
                                        @forelse($categories as $category)
                                            <div class="form-check">
                                                <input class="form-check-input category-filter-checkbox" type="checkbox" name="category_id[]" value="{{ $category->id }}" id="net_profit_report_category_{{ $category->id }}"
                                                    {{ in_array($category->id, $categoryIds) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="net_profit_report_category_{{ $category->id }}">
                                                    {{ $category->name }}
                                                </label>
                                            </div>
                                        @empty
                                            <div class="text-muted px-1">No categories found</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.net-profit') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                                <a href="{{ route('reports.net-profit.export', request()->query()) }}" class="btn btn-success btn-sm">
                                    <i class="feather-download me-2"></i>Export to Excel
                                </a>
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
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Net Revenue @include('partials.info-tooltip', ['text' => 'Same definition as the Revenue Report: sum of orders.total for paid+refunded orders, minus sum of orders.total_refunded, in range.'])</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['net_revenue'], 2) }}</h4>
                    <small class="text-muted">gross {{ number_format($summary['gross_revenue'], 2) }} &middot; refunds {{ number_format($summary['total_refunds'], 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Gross Profit @include('partials.info-tooltip', ['text' => 'Net Revenue minus COGS (order_items.cost_at_sale x quantity, for the same paid+refunded order set, requiring inventory_updated=true).'])</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['gross_profit'], 2) }}</h4>
                    <small class="text-muted">COGS {{ number_format($summary['cogs'], 2) }} &middot; {{ number_format($summary['gross_margin'], 1) }}% margin</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card {{ $summary['net_profit'] >= 0 ? 'bg-soft-success' : 'bg-soft-danger' }}">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Net Profit @include('partials.info-tooltip', ['text' => 'Gross Profit minus eBay Fees minus Shipping Costs minus Operating Expenses. See the P&L table below for the full breakdown.'])</h6>
                    <h4 class="mb-0 fw-bold {{ $summary['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($summary['net_profit'], 2) }}</h4>
                    <small class="text-muted">{{ number_format($summary['net_margin'], 1) }}% net margin</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Waterfall P&L -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-bar-chart-2 me-2"></i>Profit &amp; Loss
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Gross Revenue</td>
                                <td class="text-end">{{ number_format($summary['gross_revenue'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">(-) Refunds</td>
                                <td class="text-end text-danger">({{ number_format($summary['total_refunds'], 2) }})</td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-semibold">Net Revenue</td>
                                <td class="text-end fw-semibold">{{ number_format($summary['net_revenue'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">(-) COGS</td>
                                <td class="text-end text-danger">({{ number_format($summary['cogs'], 2) }})</td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-semibold">Gross Profit</td>
                                <td class="text-end fw-semibold">{{ number_format($summary['gross_profit'], 2) }} <span class="text-muted small">({{ number_format($summary['gross_margin'], 1) }}%)</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">(-) eBay Fees <span class="text-muted small">(transaction {{ number_format($summary['ebay_transaction_fee'], 2) }} + ad {{ number_format($summary['ebay_ad_fee'], 2) }} + other {{ number_format($summary['ebay_other_fees'], 2) }})</span></td>
                                <td class="text-end text-danger">({{ number_format($summary['ebay_fees'], 2) }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">(-) Shipping Costs <span class="text-muted small">(eBay labels {{ number_format($summary['shipping_costs_ebay'], 2) }} + system labels {{ number_format($summary['shipping_costs_system'], 2) }})</span></td>
                                <td class="text-end text-danger">({{ number_format($summary['shipping_costs'], 2) }})</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-4">(-) Operating Expenses <span class="text-muted small">(posted bills against expense accounts only - excludes Purchase Order/inventory-asset bills, already in COGS)</span></td>
                                <td class="text-end text-danger">({{ number_format($summary['operating_expenses'], 2) }})</td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-bold">Net Profit</td>
                                <td class="text-end fw-bold {{ $summary['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($summary['net_profit'], 2) }} <span class="small">({{ number_format($summary['net_margin'], 1) }}% margin)</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="alert alert-info py-2 px-3 mb-0 small">
            <i class="feather-info me-1"></i>
            The channel/date breakdown below shows <strong>Contribution Profit</strong> (Net Revenue - COGS - eBay Fees - Shipping) -
            it excludes Operating Expenses, which aren't attributable to a single channel or order date. Grouping by date also means
            eBay fee rows land on the day eBay posted the fee, not the order date, so daily fee/revenue alignment can be approximate.
        </div>
    </div>

    <!-- Grouped Report Data -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-layers me-2"></i>
                    Contribution Profit by {{ ucfirst($groupBy) }}
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'name', 'label' => $groupBy === 'date' ? 'Date' : ($groupBy === 'category' ? 'Category' : 'Sales Channel')])
                                @include('partials.sortable-th', ['column' => 'net_revenue', 'label' => 'Net Revenue', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'cogs', 'label' => 'COGS', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'ebay_fees', 'label' => 'eBay Fees', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'shipping_costs', 'label' => 'Shipping', 'class' => 'text-end'])
                                @include('partials.sortable-th', ['column' => 'contribution_profit', 'label' => 'Contribution Profit', 'class' => 'text-end'])
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] }}</td>
                                    <td class="text-end">{{ number_format($group['net_revenue'], 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($group['cogs'], 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($group['ebay_fees'], 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($group['shipping_costs'], 2) }}</td>
                                    <td class="text-end fw-semibold {{ $group['contribution_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($group['contribution_profit'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="feather-bar-chart-2" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No data found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($reportData->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <th>Totals</th>
                                    <th class="text-end">{{ number_format($summary['net_revenue'], 2) }}</th>
                                    <th class="text-end text-danger">{{ number_format($summary['cogs'], 2) }}</th>
                                    <th class="text-end text-danger">{{ number_format($summary['ebay_fees'], 2) }}</th>
                                    <th class="text-end text-danger">{{ number_format($summary['shipping_costs'], 2) }}</th>
                                    <th class="text-end {{ ($summary['net_revenue'] - $summary['cogs'] - $summary['ebay_fees'] - $summary['shipping_costs']) >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($summary['net_revenue'] - $summary['cogs'] - $summary['ebay_fees'] - $summary['shipping_costs'], 2) }}
                                    </th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var btn = document.getElementById('categoryDropdownBtn');
        var menu = document.getElementById('categoryDropdownMenu');
        var label = document.getElementById('categoryDropdownLabel');
        var checkboxes = document.querySelectorAll('.category-filter-checkbox');

        if (!btn || !menu) {
            return;
        }

        function updateLabel() {
            var checkedCount = document.querySelectorAll('.category-filter-checkbox:checked').length;
            label.textContent = checkedCount > 0 ? checkedCount + ' Selected' : 'All Categories';
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', updateLabel);
        });

        document.addEventListener('click', function () {
            menu.classList.remove('show');
        });
    })();
</script>
@endpush
