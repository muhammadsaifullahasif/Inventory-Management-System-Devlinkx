@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">eBay Expenses Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">eBay Expenses Report</li>
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
                    <form action="{{ route('reports.ebay-expenses') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                            </div>
                            <div class="col-md-2">
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
                                <label class="form-label">Fee Category</label>
                                <select name="fee_category" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    <option value="sale" {{ $feeCategory == 'sale' ? 'selected' : '' }}>Final Value Fee (Sale)</option>
                                    <option value="marketplace_fee_adjustment" {{ $feeCategory == 'marketplace_fee_adjustment' ? 'selected' : '' }}>Marketplace Fee Adjustment</option>
                                    <option value="shipping_label" {{ $feeCategory == 'shipping_label' ? 'selected' : '' }}>Shipping Label</option>
                                    <option value="ad_fee" {{ $feeCategory == 'ad_fee' ? 'selected' : '' }}>Promoted Listings (Ad Fee)</option>
                                    <option value="refund" {{ $feeCategory == 'refund' ? 'selected' : '' }}>Refund</option>
                                    <option value="other" {{ $feeCategory == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Fee Category</option>
                                    <option value="channel" {{ $groupBy == 'channel' ? 'selected' : '' }}>Sales Channel</option>
                                    <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.ebay-expenses') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                                @if($transactions->isNotEmpty())
                                    <a href="{{ route('reports.ebay-expenses.export', request()->query()) }}" class="btn btn-success btn-sm">
                                        <i class="feather-download me-2"></i>Export to Excel
                                    </a>
                                @endif
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
            <div class="card bg-soft-danger">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Total Expenses</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['total_expenses'], 2) }}</h4>
                    <small class="text-muted">{{ $summary['transaction_count'] }} transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Final Value / Transaction Fees</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['transaction_fee'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Shipping Label Cost</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['shipping_label'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Ad Fees (Promoted Listings)</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['ad_fee'], 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Other Fees</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['other_fees'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3">
            <div class="card bg-soft-warning">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Refunds Issued</h6>
                    <h4 class="mb-0 fw-bold">{{ number_format($summary['refund'], 2) }}</h4>
                    <small class="text-muted">informational, not counted in Total Expenses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3">
            <div class="card">
                <div class="card-body py-3">
                    <h6 class="text-muted mb-1 small">Unmatched Transactions</h6>
                    <h4 class="mb-0 fw-bold">{{ $summary['unmatched_count'] }}</h4>
                    <small class="text-muted">no local order match</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="alert alert-info py-2 px-3 mb-0 small">
            <i class="feather-info me-1"></i>
            Sourced directly from eBay's Finance API sync (<code>ebay_finance_transactions</code>), filtered by transaction date.
            Fee buckets match the per-order earnings breakdown shown on each order's detail page.
        </div>
    </div>

    <!-- Grouped Report Data -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-layers me-2"></i>
                    Expenses by {{ $groupBy === 'category' ? 'Fee Category' : ucfirst($groupBy) }}
                    <span class="badge bg-soft-primary text-primary ms-2">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'name', 'label' => $groupBy === 'category' ? 'Fee Category' : ($groupBy === 'channel' ? 'Sales Channel' : 'Date')])
                                @include('partials.sortable-th', ['column' => 'transaction_count', 'label' => 'Transactions', 'class' => 'text-center'])
                                @include('partials.sortable-th', ['column' => 'amount', 'label' => 'Amount', 'class' => 'text-end'])
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reportData as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['transaction_count'] }}</span>
                                    </td>
                                    <td class="text-end {{ $group['amount'] < 0 ? 'text-success' : 'text-danger' }} fw-semibold">{{ number_format($group['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="feather-dollar-sign" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No eBay finance transactions found for the selected period.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Transaction List -->
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Transaction Details</h5>
                <span class="badge bg-soft-secondary text-secondary">{{ $transactions->total() }} total transactions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @include('partials.sortable-th', ['column' => 'date', 'label' => 'Date', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'order_number', 'label' => 'Order', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'channel', 'label' => 'Channel', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'category', 'label' => 'Category', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'booking', 'label' => 'Booking', 'class' => 'text-center', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                @include('partials.sortable-th', ['column' => 'amount', 'label' => 'Amount', 'class' => 'text-end', 'sortParam' => 'item_sort', 'dirParam' => 'item_direction'])
                                <th class="text-end">Net Impact</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $categoryLabels = [
                                    'sale' => 'Final Value Fee (Sale)',
                                    'marketplace_fee_adjustment' => 'Marketplace Fee Adjustment',
                                    'shipping_label' => 'Shipping Label',
                                    'ad_fee' => 'Promoted Listings (Ad Fee)',
                                    'refund' => 'Refund',
                                ];
                            @endphp
                            @forelse ($transactions as $transaction)
                                @php
                                    $signedAmount = $transaction->booking_entry === 'CREDIT' ? (float) $transaction->amount : -(float) $transaction->amount;
                                    $cost = -$signedAmount;
                                    $netImpact = match ($transaction->fee_category) {
                                        'sale' => (float) ($transaction->total_fee_amount ?? 0),
                                        'refund' => (float) $transaction->amount + (float) ($transaction->total_fee_amount ?? 0),
                                        default => $cost,
                                    };
                                @endphp
                                <tr>
                                    <td><span class="fs-12 text-muted">{{ $transaction->transaction_date ? $transaction->transaction_date->format('M d, Y H:i') : '-' }}</span></td>
                                    <td>
                                        @if ($transaction->order)
                                            <a href="{{ route('orders.show', $transaction->order->id) }}">{{ $transaction->order->order_number }}</a>
                                        @else
                                            <span class="text-muted" title="No matching local order">{{ $transaction->ebay_order_id ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $transaction->salesChannel->name ?? '-' }}</td>
                                    <td>{{ $categoryLabels[$transaction->fee_category] ?? 'Other' }}</td>
                                    <td class="text-center">
                                        @if ($transaction->booking_entry === 'CREDIT')
                                            <span class="badge bg-soft-success text-success">Credit</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">Debit</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($transaction->amount, 2) }}</td>
                                    <td class="text-end {{ $netImpact < 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                        {{ number_format($netImpact, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if ($transaction->order)
                                            <div class="hstack gap-2 justify-content-center">
                                                <a href="{{ route('orders.show', $transaction->order->id) }}" class="avatar-text avatar-md" title="View Order">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        No transactions found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($transactions->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} transactions
                        </div>
                        <div>
                            {{ $transactions->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
