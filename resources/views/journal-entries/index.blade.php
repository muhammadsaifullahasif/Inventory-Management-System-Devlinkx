@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Journal Entries</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Journal Entries</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('journal-entries.index') }}" method="GET" class="row g-3">
                <div class="col-md-3 mb-3">
                    <label for="" class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Entry # or Narration" value="{{ request('search') }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">Type</label>
                    <select name="reference_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="bill" {{ request('reference_type') == 'bill' ? 'selected' : '' }}>Bill</option>
                        <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payment</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="" class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Entry #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Narration</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th>Status</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>
                                    <a href="{{ route('journal-entries.show', $entry) }}">
                                        {{ $entry->entry_number }}
                                    </a>
                                </td>
                                <td>{{ $entry->entry_date->format('M d, Y') }}</td>
                                <td>
                                    @if ($entry->reference_type === 'bill')
                                        <span class="badge bg-warning text-dark">Bill</span>
                                    @elseif ($entry->reference_type === 'payment')
                                        <span class="badge bg-success">Payment</span>
                                    @else
                                        <span class="badge bg-secondary">{{ unfirst($entry->reference_type) }}</span>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($entry->narration, 50) }}</td>
                                <td class="text-right">{{ number_format($entry->total_debit, 2) }}</td>
                                <td class="text-right">{{ number_format($entry->total_credit, 2) }}</td>
                                <td>
                                    @if ($entry->is_posted)
                                        <span class="badge bg-success">Posted</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                    @if (!$entry->isBalanced())
                                        <span class="badge bg-danger">Unbalanced</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('journal-entries.show', $entry) }}" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-journal-whills text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">No journal entries found.</p>
                                    <p class="text-muted small">Journal entries are created automatically when bills and payments are posted.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
@endsection
