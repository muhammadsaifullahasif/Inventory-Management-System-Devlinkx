@extends('layouts.app')

@push('styles')
<style>
    .sortable-header {
        cursor: pointer;
        white-space: nowrap;
        transition: color 0.15s ease;
    }
    .sortable-header:hover {
        color: var(--bs-primary) !important;
    }
    .sortable-header.active {
        color: var(--bs-primary) !important;
        font-weight: 600;
    }
    .sort-arrows {
        display: inline-flex;
        align-items: center;
    }
</style>
@endpush

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Audit Trail</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Audit Trail</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                @can('manage audit-settings')
                <a href="{{ route('audit-logs.settings.edit') }}" class="btn btn-light-brand">
                    <i class="feather-settings me-2"></i>
                    <span>Settings</span>
                </a>
                @endcan
            </div>
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
                    <form action="{{ route('audit-logs.index') }}" method="GET" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Actor / IP</label>
                                <input type="text" name="actor" class="form-control form-control-sm" placeholder="Name, email, IP..." value="{{ $filters['actor'] ?? '' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Event</label>
                                <select name="event" class="form-select form-select-sm">
                                    <option value="">All Events</option>
                                    @foreach($eventTypes as $type)
                                        <option value="{{ $type }}" {{ ($filters['event'] ?? '') == $type ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Actor Type</label>
                                <select name="actor_type" id="actorTypeSelect" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    @foreach($actorTypes as $type)
                                        <option value="{{ $type }}" {{ ($filters['actor_type'] ?? '') == $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">User</label>
                                <select name="actor_id" id="actorUserSelect" class="form-select form-select-sm" {{ ($filters['actor_type'] ?? '') === 'user' ? '' : 'disabled' }}>
                                    <option value="">All Users</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ (string) ($filters['actor_id'] ?? '') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Model</label>
                                <input type="text" name="auditable_type" class="form-control form-control-sm" placeholder="App\Models\Product" value="{{ $filters['auditable_type'] ?? '' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search"></i>
                                    Filter
                                </button>
                                <a href="{{ route('audit-logs.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x"></i>
                                    Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Log Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                <span class="text-muted fs-12">{{ $logs->total() }} results (live + archived)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-top mb-0">
                        <thead>
                            <tr>
                                @php
                                    $sortableColumns = [
                                        'created_at' => 'Date / Time',
                                        'event' => 'Event',
                                        'actor_label' => 'Performed By',
                                        'auditable_type' => 'Model',
                                    ];
                                @endphp
                                @foreach($sortableColumns as $column => $label)
                                    <th>
                                        @php
                                            $isActive = $sortBy === $column;
                                            $nextOrder = ($isActive && $sortOrder === 'asc') ? 'desc' : 'asc';
                                            $sortUrl = request()->fullUrlWithQuery(['sort_by' => $column, 'sort_order' => $nextOrder]);
                                        @endphp
                                        <a href="{{ $sortUrl }}" class="d-flex align-items-center text-dark text-decoration-none sortable-header {{ $isActive ? 'active' : '' }}">
                                            {{ $label }}
                                            <span class="sort-arrows ms-1">
                                                @if($isActive)
                                                    <i class="feather-arrow-{{ $sortOrder === 'asc' ? 'up' : 'down' }} fs-12"></i>
                                                @else
                                                    <i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>
                                                @endif
                                            </span>
                                        </a>
                                    </th>
                                @endforeach
                                <th>Actor Type</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr class="cursor-pointer" onclick="window.location='{{ route('audit-logs.show', $log->uuid) }}'" style="cursor: pointer;">
                                    <td>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                    @php
                                        $dangerEvents = ['deleted', 'login_failed', 'order_cancelled', 'label_cancelled'];
                                        $warningEvents = ['order_refunded', 'order_partially_refunded'];
                                        $badgeColor = in_array($log->event, $dangerEvents) ? 'danger' : (in_array($log->event, $warningEvents) ? 'warning' : 'primary');
                                    @endphp
                                    <td><span class="badge bg-light-{{ $badgeColor }} text-{{ $badgeColor }}">{{ ucfirst(str_replace('_', ' ', $log->event)) }}</span></td>
                                    <td>{{ $log->actor_label ?? '—' }}</td>
                                    <td>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                                    <td>{{ ucfirst($log->actor_type) }}</td>
                                    <td>{{ $log->ip_address ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-end">
                {{ $logs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var actorTypeSelect = document.getElementById('actorTypeSelect');
        var actorUserSelect = document.getElementById('actorUserSelect');

        function syncUserSelectState() {
            var isUserType = actorTypeSelect.value === 'user';
            actorUserSelect.disabled = !isUserType;
            if (!isUserType) {
                actorUserSelect.value = '';
            }
        }

        actorTypeSelect.addEventListener('change', syncUserSelectState);
    })();
</script>
@endpush
