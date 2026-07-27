@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Journal Entries</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Journal Entries</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('journal-entries.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Entry # or Narration" value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="reference_type" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    <option value="bill" {{ request('reference_type') == 'bill' ? 'selected' : '' }}>Bill</option>
                                    <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payment</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('journal-entries.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Entry #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Narration</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr>
                                    <td>
                                        <a href="{{ route('journal-entries.show', $entry) }}" class="fw-semibold">
                                            {{ $entry->entry_number }}
                                        </a>
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ $entry->entry_date->format('M d, Y') }}</span></td>
                                    <td>
                                        @if ($entry->reference_type === 'bill')
                                            <span class="badge bg-soft-warning text-warning">Bill</span>
                                        @elseif ($entry->reference_type === 'payment')
                                            <span class="badge bg-soft-success text-success">Payment</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($entry->reference_type) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($entry->narration, 50) }}</td>
                                    <td class="text-end">{{ number_format($entry->total_debit, 2) }}</td>
                                    <td class="text-end">{{ number_format($entry->total_credit, 2) }}</td>
                                    <td>
                                        @if ($entry->is_posted)
                                            <span class="badge bg-soft-success text-success">Posted</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">Draft</span>
                                        @endif
                                        @if (!$entry->isBalanced())
                                            <span class="badge bg-soft-danger text-danger">Unbalanced</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('journal-entries.show', $entry) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="feather-book text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3">No journal entries found.</p>
                                        <p class="text-muted small">Journal entries are created automatically when bills and payments are posted.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($entries->hasPages())
            <div class="card-footer">
                {{ $entries->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>
@endsection
